<?php
/**
 * Helper controller
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_HelperController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        try {
            $dir = Mage::helper('webinterpret_connector')->getModuleBridgeDir();
            $path = $dir . DS . 'helper.php';
            if (!file_exists($path)) {
                $this->_forward('defaultNoRoute');
                return;
            }
            require_once $path;
            Webinterpret_Bridge2Cart_Helper::handleRequest();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        die();
    }

    public function installAction()
    {
        $response = array();
        $response['status'] = 'success';
        $response['response_id'] = md5(uniqid(mt_rand(), true));
        if (!Mage::helper('webinterpret_connector')->installBridgeHelper()) {
            $response['status'] = 'error';
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        die();
    }
}
