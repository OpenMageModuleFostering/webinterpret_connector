<?php
/**
 * Redirect block
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_Block_Product_View extends Mage_Core_Block_Template
{
    public function getProductId()
    {
        if (isset($_GET['wi_product_id'])) {
            return $_GET['wi_product_id'];
        }
        return Mage::registry('current_product')->getId();
    }

    public function getStoreUrl()
    {
        if (isset($_GET['wi_store_url'])) {
            return $_GET['wi_store_url'];
        }
        return Mage::helper('webinterpret_connector')->getStoreBaseUrl();
    }

    public function getStoreViewId()
    {
        return Mage::app()->getStore()->getId();
    }

    public function getLocaleCode()
    {
        return Mage::getStoreConfig('general/locale/code');
    }

    public function getPluginVersion()
    {
        return 'magento-' . Mage::helper('webinterpret_connector')->getExtensionVersion();
    }
}
