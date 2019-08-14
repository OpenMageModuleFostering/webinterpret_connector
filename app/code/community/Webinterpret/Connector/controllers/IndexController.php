<?php

/**
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
            Webinterpret_Connector_Helper_Verifier::verifyRequestWebInterpretSignature();
            define('M1_TOKEN', Mage::getStoreConfig('webinterpret_connector/key'));
            if (!isset($_POST['store_root'])) {
                $_POST['store_root'] = Mage::getBaseDir('base');
            }
            $dir = Mage::helper('webinterpret_connector')->getModuleBridgeDir();
            $path = $dir . DS . 'bridge.php';
            if (!file_exists($path)) {
                $this->_forward('defaultNoRoute');
                return;
            }
            require_once $path;
        } catch (Webinterpret_Connector_Model_SignatureException $e) {
            header('HTTP/1.0 403 Forbidden');
            echo $e->getMessage();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        die;
    }
}
