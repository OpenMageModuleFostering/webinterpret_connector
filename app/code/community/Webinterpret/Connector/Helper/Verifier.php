<?php

use Webinterpret_Connector_Model_SignatureException as SignatureException;

/**
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_Helper_Verifier extends Mage_Core_Helper_Abstract
{
    /**
     * Verifies if the current request has been correctly signed by WebInterpret
     *
     * @return bool
     * @throws Exception
     */
    public static function verifyRequestWebInterpretSignature()
    {
        if (!Mage::helper('webinterpret_connector')->isSignatureVerificationEnabled()) {
            return true;
        }

        if (!function_exists('openssl_verify')) {
            throw new SignatureException('OpenSSL is required.');
        }

        if (empty($_SERVER['HTTP_WEBINTERPRET_REQUEST_ID']) || empty ($_SERVER['HTTP_WEBINTERPRET_SIGNATURE']) || empty ($_SERVER['HTTP_WEBINTERPRET_SIGNATURE_VERSION'])) {
            throw new SignatureException('Request needs to be signed by WebInterpret.');
        }

        $requestId = $_SERVER['HTTP_WEBINTERPRET_REQUEST_ID'];
        $signature = $_SERVER['HTTP_WEBINTERPRET_SIGNATURE'];
        $signature = base64_decode($signature);

        $version = $_SERVER['HTTP_WEBINTERPRET_SIGNATURE_VERSION'];

        if ($version == 2) {
            // Query data
            $queryArray = $_GET;
            ksort($queryArray);
            $queryData = serialize($queryArray);

            // Post data
            $postData = $_POST;
            ksort($postData);
            $postData = serialize($postData);

            $data = $requestId.$queryData.$postData;
        } else {
            throw new SignatureException('Not supported signature version.');
        }

        // Verify signature
        $publicKey = Mage::getStoreConfig('webinterpret_connector/public_key');

        $ok = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA1);

        if ($ok == 1) {
            return true;
        }
        if ($ok == 0) {
            throw new SignatureException('Incorrect signature.');
        }

        throw new SignatureException('Error checking signature');
    }
}