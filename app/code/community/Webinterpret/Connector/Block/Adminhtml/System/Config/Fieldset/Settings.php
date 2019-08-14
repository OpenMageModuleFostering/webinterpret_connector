<?php

/**
 * Custom renderer for Webinterpret status in System Configuration
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_Block_Adminhtml_System_Config_Fieldset_Settings extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        if (Mage::helper('webinterpret_connector')->isStoreRegistered()) {
            return parent::render($element);
        }
        return "";
    }
}
