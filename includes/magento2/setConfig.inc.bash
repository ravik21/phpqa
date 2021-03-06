source $DIR/../includes/generic/setConfig.inc.bash

#PHP Mess Detector Configs
phpmdConfigPath="$projectRoot/dev/tests/static/testsuite/Magento/Test/Php/_files/phpmd/ruleset.xml"

# coding Standard for checking
# checks for a folder called 'condingStandards' in the $projectConfigPath, falls back to the PSR2 standards
phpcsCodingStandardsNameOrPath="$projectRoot/vendor/magento/marketplace-eqp/MEQP2/"

##PHPUnit Configs
if [[ -f $projectRoot/dev/tests/unit/phpunit.xml ]]
then
    # Project root phpunit.xml trumps everything else
    phpUnitConfigPath=$projectRoot/dev/tests/unit/phpunit.xml
elif [[ -f $projectRoot/dev/tests/unit/phpunit.xml.dist ]]
then
    # Project root phpunit.xml trumps everything else
    phpUnitConfigPath=$projectRoot/dev/tests/unit/phpunit.xml.dist
else
    echo "No PHPUnit config was found at either:"
    echo "- $projectRoot/dev/tests/unit/phpunit.xml"
    echo "- $projectRoot/dev/tests/unit/phpunit.xml.dist"
fi
