<?php
/**
 * Observer
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_Model_Observer
{
    /**
     * Install bridge when system configuration is saved
     */
    public function hookIntoAdminSystemConfigChangedSectionWebinterpretConnector($observer)
    {
        try {
            Mage::helper('webinterpret_connector')->saveConfig();
        } catch (Exception $e) {}

        return $observer;
    }

    /**
     * Fix "404 Error Page Not Found" after installing a new extension
     */
    public function hookIntoAdminhtmlInitSystemConfig($observer)
    {
        try {
            $session = Mage::getSingleton('admin/session');
            if ($session) {
                $session->setAcl(Mage::getResourceModel('admin/acl')->loadAcl());
            }
        } catch (Exception $e) {}

        return $observer;
    }

    /**
     * Display global notifications for admin
     */
    public function hookIntoWebinterpretConnectorNotificationsBefore($observer)
    {
        if (!Mage::helper('webinterpret_connector')->isGlobalNotificationsEnabled()) {
            return $observer;
        }

        $notifications = Mage::getSingleton('webinterpret_connector/notification');

        if (Mage::helper('webinterpret_connector')->isEnabled()) {
            if (Mage::getSingleton('admin/session')->isAllowed('system/config')) {
                if (Mage::helper('webinterpret_connector')->isInstallationMode()) {
                    $url = Mage::getModel('adminhtml/url')->getUrl('/system_config/edit/section/webinterpret_connector');
                    $notifications->addMessage(Mage::helper('webinterpret_connector')->__("To continue installing Webinterpret, <a href=\"%s\">click here</a>", $url));
                }
            }
        }

        return $observer;
    }
}
