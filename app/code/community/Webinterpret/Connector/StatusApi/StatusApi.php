<?php

/**
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_StatusApi_StatusApi
{
    /**
     * @var array of AbstractStatusApiDiagnostics testers
     */
    private $testers;

    public function __construct()
    {
        $this->testers = array();
    }

    /**
     * Adds a tester to the testing suite
     *
     * @param Webinterpret_Connector_StatusApi_AbstractStatusApiDiagnostics $tester
     */
    public function addTester(Webinterpret_Connector_StatusApi_AbstractStatusApiDiagnostics $tester)
    {
        $this->testers[] = $tester;
    }

    /**
     * Runs tests on all of the attached testers and returns the result
     *
     * @return string JSON encoded array with diagnostics information
     */
    public function getJsonTestResults()
    {
        $result = array();

        foreach ($this->testers as $tester) {
            $result[$tester->getName()] = $tester->getTestsResults();
        }

        return json_encode($result);
    }
}