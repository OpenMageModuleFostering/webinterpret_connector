<?php

namespace WebInterpret\Toolkit\StatusApi;

class StatusApi
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
     * @param AbstractStatusApiDiagnostics $tester
     */
    public function addTester(AbstractStatusApiDiagnostics $tester)
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