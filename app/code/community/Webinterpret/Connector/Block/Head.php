<?php
/**
 * Head block
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_Block_Head extends Mage_Core_Block_Template
{
    public function getHeadHtml()
    {
        return Mage::getStoreConfig('webinterpret_connector/head') . PHP_EOL;
    }

    public function getLoaderUrl()
    {
        return Mage::helper('webinterpret_connector')->getRemoteAssetsUrl() . '/common/js/webinterpret-loader.js';
    }
}
