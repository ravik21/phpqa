<?php
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EdmondsCommerce\PHPQA\PHPUnit\TestDox;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;
use PHPUnit\Framework\Warning;
use PHPUnit\Runner\PhptTestCase;
use PHPUnit\TextUI\ResultPrinter;
use PHPUnit\Util\TestDox\NamePrettifier;
use SebastianBergmann\Timer\Timer;

/**
 * This printer is for CLI output only. For the classes that output to file, html and xml,
 * please refer to the PHPUnit\Util\TestDox namespace
 */
class CliTestDoxPrinter extends ResultPrinter
{
    private const SYMBOL_SKIP = '→';
    /**
     * @var \EdmondsCommerce\PHPQA\PHPUnit\TestDox\TestResult
     */
    private $currentTestResult;
    /**
     * @var \EdmondsCommerce\PHPQA\PHPUnit\TestDox\TestResult
     */
    private $previousTestResult;
    /**
     * @var \EdmondsCommerce\PHPQA\PHPUnit\TestDox\TestResult[]
     */
    private $nonSuccessfulTestResults = [];
    /**
     * @var NamePrettifier
     */
    private $prettifier;

    /**
     * CliTestDoxPrinter constructor.
     *
     * @param null|mixed $out
     * @param bool       $verbose
     * @param string     $colors
     * @param bool       $debug
     * @param int        $numberOfColumns
     * @param bool       $reverse
     */
    public function __construct(
        $out = null,
        bool $verbose = false,
        string $colors = self::COLOR_DEFAULT,
        bool $debug = false,
        int $numberOfColumns = 80,
        bool $reverse = false
    ) {
        parent::__construct($out, $verbose, $colors, $debug, $numberOfColumns, $reverse);

        $this->prettifier = new NamePrettifier();
    }

    public function startTest(Test $test): void
    {
        $class = \get_class($test);

        if ($test instanceof TestCase) {
            $annotations = $test->getAnnotations();

            if (isset($annotations['class']['testdox'][0])) {
                $className = $annotations['class']['testdox'][0];
            } else {
                $className = $this->prettifier->prettifyTestClass($class);
            }

            if (isset($annotations['method']['testdox'][0])) {
                $testMethod = $annotations['method']['testdox'][0];
            } else {
                $testMethod = $this->prettifier->prettifyTestMethod((string)$test->getName(false));
            }

            $testMethod .= \substr($test->getDataSetAsString(false), 5);
        } elseif ($test instanceof PhptTestCase) {
            $className  = $class;
            $testMethod = $test->getName();
        } else {
            return;
        }

        $this->currentTestResult = new \EdmondsCommerce\PHPQA\PHPUnit\TestDox\TestResult(
            function (string $color, string $buffer) {
                return $this->formatWithColor($color, $buffer);
            },
            $className,
            $testMethod
        );

        parent::startTest($test);
    }

    public function endTest(Test $test, float $time): void
    {
        if (!$test instanceof TestCase && !$test instanceof PhptTestCase) {
            return;
        }

        parent::endTest($test, $time);

        $this->currentTestResult->setRuntime($time);

        $this->write($this->currentTestResult->toString($this->previousTestResult, $this->verbose));

        $this->previousTestResult = $this->currentTestResult;

        if (!$this->currentTestResult->isTestSuccessful()) {
            $this->nonSuccessfulTestResults[] = $this->currentTestResult;
        }
    }

    public function addError(Test $test, \Throwable $t, float $time): void
    {
        if (null === $this->currentTestResult) {
            throw new \RuntimeException(
                'Error in ' . __METHOD__ . ': '
                . $t->getMessage() . "\n\n" . $t->getTraceAsString(),
                $t->getCode(),
                $t
            );
        }
        $this->currentTestResult->fail(
            $this->formatWithColor('fg-yellow', '✘'),
            (string)$t
        );
    }

    public function addWarning(Test $test, Warning $e, float $time): void
    {
        $this->currentTestResult->fail(
            $this->formatWithColor('fg-yellow', '✘'),
            (string)$e
        );
    }

    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $this->currentTestResult->fail(
            $this->formatWithColor('fg-red', '✘'),
            (string)$e
        );
    }

    public function addIncompleteTest(Test $test, \Throwable $t, float $time): void
    {
        $this->currentTestResult->fail(
            $this->formatWithColor('fg-yellow', '∅'),
            (string)$t,
            true
        );
    }

    public function addRiskyTest(Test $test, \Throwable $t, float $time): void
    {
        $this->currentTestResult->fail(
            $this->formatWithColor('fg-yellow', '☢'),
            (string)$t,
            true
        );
    }

    public function addSkippedTest(Test $test, \Throwable $t, float $time): void
    {
        $this->currentTestResult->fail(
            $this->formatWithColor('fg-yellow', self::SYMBOL_SKIP),
            (string)$t,
            true
        );
    }


    public function writeProgress(string $progress): void
    {
    }

    public function flush(): void
    {
    }

    public function printResult(TestResult $result): void
    {
        $this->printHeader();

        $this->printNonSuccessfulTestsSummary($result->count());

        $this->printFooter($result);
    }

    protected function printHeader(): void
    {
        $this->write("\n" . Timer::resourceUsage() . "\n\n");
    }

    private function printNonSuccessfulTestsSummary(int $numberOfExecutedTests): void
    {
        $numberOfNonSuccessfulTests = \count($this->nonSuccessfulTestResults);

        if ($numberOfNonSuccessfulTests === 0) {
            return;
        }

        if (($numberOfNonSuccessfulTests / $numberOfExecutedTests) >= 0.7) {
            return;
        }

        $this->write(
            $this->formatWithColor(
                'fg-yellow',
                "Summary of non-successful tests:"
            )
            . "\n\n"
        );

        $previousTestResult = null;

        $skippedTests = 0;
        foreach ($this->nonSuccessfulTestResults as $testResult) {
            $line = $testResult->toString($previousTestResult, $this->verbose);
            if (false !== \strpos($line, self::SYMBOL_SKIP)) {
                $skippedTests++;
                continue;
            }
            $this->write($line);

            $previousTestResult = $testResult;
        }
        if ($skippedTests > 0) {
            $this->write(
                $this->formatWithColor(
                    'fg-yellow',
                    "\n" . self::SYMBOL_SKIP . ' Skipped Tests: ' . $skippedTests . "\n\n"
                )
            );
        }
    }
}
