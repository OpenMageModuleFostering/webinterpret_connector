<?php

namespace WebInterpret\Toolkit\StatusApi;

use WebInterpret\Bridge\eCommercePlatformBridge;
use WebInterpret\Bridge\WordpressBridge;

class PlatformDiagnostics extends AbstractStatusApiDiagnostics
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
            'platform_version' => \Mage::getVersion(),
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

        $stores = \Mage::app()->getStores();
        /**
         * @var \Mage_Core_Model_Store $store
         */
        foreach ($stores as $store) {
            $storeLocale = \Mage::getStoreConfig(\Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $store->getId());
            $storeBaseCountry = \Mage::getStoreConfig('general/country/default', $store->getId());

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

        $websites = \Mage::app()->getWebsites();
        /**
         * @var \Mage_Core_Model_Website $website
         */
        foreach ($websites as $website) {
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
        $modules = (array)\Mage::getConfig()->getNode('modules')->children();

        return array_keys($modules);
    }
}
