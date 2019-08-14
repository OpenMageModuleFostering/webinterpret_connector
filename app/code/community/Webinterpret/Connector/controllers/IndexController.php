<?php
/**
 * Index controller
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_IndexController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        try {
            $dir = Mage::helper('webinterpret_connector')->getModuleBridgeDir();
            $path = $dir . DS . 'bridge.php';
            if (!file_exists($path)) {
                $this->_forward('defaultNoRoute');
                return;
            }
            require_once $path;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        die();
    }
}
