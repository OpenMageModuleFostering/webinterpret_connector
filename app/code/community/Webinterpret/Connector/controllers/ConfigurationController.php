<?php

/**
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_ConfigurationController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        try {
            Webinterpret_Connector_Helper_Verifier::verifyRequestWebInterpretSignature();

            if ($_GET['action'] === 'update-option') {
                $this->handleUpdateOptionRequest();
            }
        } catch (Webinterpret_Connector_Model_SignatureException $e) {
            header('HTTP/1.0 403 Forbidden');
            echo $e->getMessage();
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
        die;
    }

    /**
     * Update given option
     */
    private function handleUpdateOptionRequest()
    {
        try {
            if (!isset($_POST['key']) || !isset($_POST['value'])) {
                throw new Exception('Please provide $key and $value to update.');
            }

            $key = filter_var($_POST['key'], FILTER_SANITIZE_STRING);
            $value = filter_var($_POST['value'], FILTER_SANITIZE_STRING);

            if (strpos($key, 'webinterpret_connector/') === 0) {
                $key = substr($key, strlen('webinterpret_connector/'));
            }

            if (preg_match('/[^a-z_]/', $key)) {
                throw new Exception('Invalid characters (only a-z and underscore are allowed)');
            }

            if( $key === 'public_key' ) {
                $value = str_replace('\\n', "\n", $value);
            }

            $key = 'webinterpret_connector/'.$key;

            echo 'Setting key: '.$key.' to value: '.$value.PHP_EOL;

            Mage::getConfig()->saveConfig($key, $value);
            Mage::getConfig()->reinit();

            echo 'Setting successfully updated!'.PHP_EOL;
        } catch (Exception $e) {
            echo 'ERROR: '.$e->getMessage().PHP_EOL;
        }
    }
}

