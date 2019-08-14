<?php

/**
 * Custom renderer for Webinterpret status in System Configuration
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_Block_Adminhtml_System_Config_Fieldset_Status
    extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    protected $_template = 'webinterpret/system/config/fieldset/status.phtml';
    
    /**
     * Render fieldset html
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $html = $this->_getHeaderHtml($element);
        $html .= $this->toHtml();
        $html .= $this->_getFooterHtml($element);
        return $html;
    }

    public function getRemoteTestUrl()
    {
        $url = 'https://webstores.webinterpret.com/plugin/test';
        $storeUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
        $storeUrl = rtrim($storeUrl, '/');
        $url .= '?' . http_build_query(array('url' => $storeUrl));
        return $url;
    }
}
