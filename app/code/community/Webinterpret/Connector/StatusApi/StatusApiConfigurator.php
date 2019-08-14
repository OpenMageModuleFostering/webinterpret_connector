<?php

/**
 * Factory for preconfigured StatusApi objects
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_StatusApi_StatusApiConfigurator
{
    /**
     * @var Mage_Core_Model_App
     */
    private $app;

    /**
     * @var Mage_Core_Model_Config
     */
    private $config;

    public function __construct(Mage_Core_Model_App $app, Mage_Core_Model_Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Returns StatusApi with full suite of diagnostics testers preconfigured
     *
     * @return Webinterpret_Connector_StatusApi_StatusApi
     */
    public function getExtendedStatusApi()
    {
        $statusApi = new Webinterpret_Connector_StatusApi_StatusApi();
        $statusApi->addTester(new Webinterpret_Connector_StatusApi_PluginDiagnostics($this->app, $this->config));
        $statusApi->addTester(new Webinterpret_Connector_StatusApi_PlatformDiagnostics());
        $statusApi->addTester(new Webinterpret_Connector_StatusApi_SystemDiagnostics());

        return $statusApi;
    }
}