<?php

namespace WebInterpret\Toolkit\StatusApi;

class PluginDiagnostics extends AbstractStatusApiDiagnostics
{

    /**
     * @var \Mage_Core_Model_App
     */
    private $app;

    /**
     * @var \Mage_Core_Model_Config
     */
    private $config;

    /**
     * @param \Mage_Core_Model_Config $config
     */
    public function __construct(\Mage_Core_Model_App $app, \Mage_Core_Model_Config $config)
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
            'store_extender_enabled' => $this->isStoreExtenderEnabled(),
            'backend_redirector_enabled' => $this->isBackendRedirectorEnabled(),
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
     * @return bool
     */
    private function isStoreExtenderEnabled()
    {
        return (bool)$this->app->getStore()->getConfig('webinterpret_connector/store_extender_enabled"');
    }

    /**
     * @return bool
     */
    private function isBackendRedirectorEnabled()
    {
        return (bool)$this->app->getStore()->getConfig('webinterpret_connector/backend_redirector_enabled');
    }

    /**
     * @return array
     */
    private function getDatabaseSettings()
    {
        return $this->app->getStore()->getConfig('webinterpret_connector');
    }
}
