<?php

namespace WebInterpret\Toolkit\StatusApi;

/**
 * Factory for preconfigured StatusApi objects
 */
class StatusApiConfigurator
{
    /**
     * @var \Mage_Core_Model_App
     */
    private $app;

    /**
     * @var \Mage_Core_Model_Config
     */
    private $config;

    public function __construct(\Mage_Core_Model_App $app, \Mage_Core_Model_Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Returns StatusApi with full suite of diagnostics testers preconfigured
     *
     * @return StatusApi
     */
    public function getExtendedStatusApi()
    {
        $statusApi = new StatusApi();
        $statusApi->addTester($this->getPluginDiagnosticsTester());
        $statusApi->addTester($this->getPlatformDiagnosticsTester());
        $statusApi->addTester($this->getSystemDiagnosticsTester());

        return $statusApi;
    }

    /**
     * @return PluginDiagnostics
     */
    private function getPluginDiagnosticsTester()
    {
        $tester = new PluginDiagnostics($this->app, $this->config);

        return $tester;
    }

    /**
     * @return PlatformDiagnostics
     */
    private function getPlatformDiagnosticsTester()
    {
        $tester = new PlatformDiagnostics();

        return $tester;
    }

    /**
     * @return SystemDiagnostics
     */
    private function getSystemDiagnosticsTester()
    {
        $tester = new SystemDiagnostics();

        return $tester;
    }
}