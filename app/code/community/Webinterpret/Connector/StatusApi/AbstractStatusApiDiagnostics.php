<?php

/**
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
abstract class Webinterpret_Connector_StatusApi_AbstractStatusApiDiagnostics
{
    /**
     * @var bool
     */
    protected $testsCompleted = false;

    /**
     * @var array Associative array with test results
     */
    protected $testsResults = array();

    /**
     * Gets the name of the testing unit (used when combining responses from multiple testers)
     *
     * @return string
     */
    abstract public function getName();

    /**
     * Returns array with tests results. If tests were not run yet, they will be launched automatically
     *
     * @return array
     */
    public function getTestsResults()
    {
        if (!$this->testsCompleted) {
            $this->runDiagnostics();
        }

        return $this->testsResults;
    }

    /**
     * Returns tests results
     *
     * @return array
     */
    abstract protected function getTestsResult();

    /**
     * Runs all of the tests and stores the result (can be used to re-run tests to update results if needed)
     *
     * @return bool
     */
    protected function runDiagnostics()
    {
        try {
            $this->testsResults = $this->getTestsResult();

            $this->testsCompleted = true;
        } catch (\Exception $e) {
            // fixme log & handle error
            return false;
        }

        return true;
    }
}
