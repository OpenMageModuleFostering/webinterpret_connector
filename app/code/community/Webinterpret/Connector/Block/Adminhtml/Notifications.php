<?php
/**
 * Notifications block
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_Block_Adminhtml_Notifications extends Mage_Adminhtml_Block_Template
{
    public function _toHtml($className = "notification-global")
    {
        // Let other extensions add messages
        Mage::dispatchEvent('webinterpret_connector_notifications_before');

        // Get the global notification object
        $messages = Mage::getSingleton('webinterpret_connector/notification')->getMessages();
        $html = null;
        foreach ($messages as $message) {
            $html .= "<div class='$className'>" . $message . "</div>";
        }
        return $html;
    }
}
