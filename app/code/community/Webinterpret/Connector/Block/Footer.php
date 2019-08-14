<?php
/**
 * Footer block
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_Block_Footer extends Mage_Core_Block_Template
{
    public function getFooterHtml()
    {
        return Mage::getStoreConfig('webinterpret_connector/footer') . PHP_EOL;
    }

    public function getPluginId()
    {
        return Mage::getStoreConfig('webinterpret_connector/plugin_id');
    }
}
