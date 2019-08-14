<?php

/**
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_StatusApi_PlatformDiagnostics
    extends Webinterpret_Connector_StatusApi_AbstractStatusApiDiagnostics
{
    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'platform';
    }

    /**
     * @inheritdoc
     */
    protected function getTestsResult()
    {
        return array(
            'platform_name' => 'magento',
            'platform_version' => Mage::getVersion(),
            'magento' => $this->getMagentoInfo(),
        );
    }

    /**
     * @return array
     */
    private function getMagentoInfo()
    {
        return array(
            'compiler_enabled' => defined('COMPILER_INCLUDE_PATH') ? true : false,
            'stores' => $this->getStoresInfo(),
            'websites' => $this->getWebsitesInfo(),
            'modules' => $this->getModulesInfo(),
        );
    }

    private function getStoresInfo()
    {
        $storesInfo = array();

        /** @var Mage_Core_Model_Store $store */
        foreach (Mage::app()->getStores() as $store) {
            $storeLocale = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $store->getId());
            $storeBaseCountry = Mage::getStoreConfig('general/country/default', $store->getId());

            $storesInfo[] = array(
                'store_id' => $store->getId(),
                'store_code' => $store->getCode(),
                'store_name' => $store->getName(),
                'store_base_url' => $store->getBaseUrl(),
                'store_locale' => $storeLocale,
                'store_base_country' => $storeBaseCountry,
                'store_website_id' => $store->getWebsiteId(),
                'store_group_id' => $store->getGroupId(),
                'store_active' => $store->getIsActive(),
            );
        }

        return $storesInfo;
    }

    private function getWebsitesInfo()
    {
        $websitesInfo = array();

        /** @var Mage_Core_Model_Website $website */
        foreach (Mage::app()->getWebsites() as $website) {
            $websitesInfo = array(
                'website_id' => $website->getId(),
                'website_code' => $website->getCode(),
                'website_name' => $website->getName(),
                'website_is_default' => $website->getIsDefault(),
            );
        }

        return $websitesInfo;
    }

    private function getModulesInfo()
    {
        $modules = (array)Mage::getConfig()->getNode('modules')->children();

        return array_keys($modules);
    }
}
