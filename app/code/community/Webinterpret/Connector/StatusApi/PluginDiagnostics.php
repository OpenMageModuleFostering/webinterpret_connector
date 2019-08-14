<?php

/**
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_StatusApi_PluginDiagnostics
    extends Webinterpret_Connector_StatusApi_AbstractStatusApiDiagnostics
{
    /**
     * @var Mage_Core_Model_App
     */
    private $app;

    /**
     * @var Mage_Core_Model_Config
     */
    private $config;

    /**
     * @param Mage_Core_Model_Config $config
     */
    public function __construct(Mage_Core_Model_App $app, Mage_Core_Model_Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'plugin';
    }

    /**
     * @inheritdoc
     */
    protected function getTestsResult()
    {
        return array(
            'plugin_version' => $this->getPluginVersion(),
            'plugin_enabled' => $this->isPluginEnabled(),
            'store_token' => $this->getStoreToken(),
            'updates_dir' => null,
            'is_updatable' => $this->isUpdatable(),
            'active_settings' => $this->getDatabaseSettings(),
        );
    }

    /**
     * @return string
     */
    private function getPluginVersion()
    {
        return (string)$this->config->getNode()->modules->Webinterpret_Connector->version;
    }

    /**
     * @return bool
     */
    private function isUpdatable()
    {
        return (bool)$this->app->getStore()->getConfig('webinterpret_connector/automatic_updates_enabled');
    }

    /**
     * @return bool
     */
    private function isPluginEnabled()
    {
        return (bool)$this->app->getStore()->getConfig('webinterpret_connector/enabled');
    }

    /**
     * @return string|null
     */
    private function getStoreToken()
    {
        return (string)$this->app->getStore()->getConfig('webinterpret_connector/key');
    }

    /**
     * @return array
     */
    private function getDatabaseSettings()
    {
        $config = (array)$this->app->getStore()->getConfig('webinterpret_connector');
        $config['remote_assets_url'] = Mage::helper('webinterpret_connector')->getRemoteAssetsUrl();

        return $config;
    }
}
