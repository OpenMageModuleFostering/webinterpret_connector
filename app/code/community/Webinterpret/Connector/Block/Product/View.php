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
        $id = Mage::registry('current_product')->getId();
        if (isset($_GET['wi_product_id'])) {
            $id = $_GET['wi_product_id'];
        }
        return $id;
    }

    public function getStoreUrl()
    {
        $url = Mage::helper('webinterpret_connector')->getStoreBaseUrl();
        if (isset($_GET['wi_store_url'])) {
            $url = $_GET['wi_store_url'];
        }
        return $url;
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
