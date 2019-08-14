<?php

class Webinterpret_Connector_Block_Adminhtml_System_Config_Fieldset_Disabled
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml($element)
    {
        $element->setDisabled('disabled');
        return parent::_getElementHtml($element);
    }
}
