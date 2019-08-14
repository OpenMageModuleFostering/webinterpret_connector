<?php
/**
 * Bridge preloader
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */

define('WI_HELPER_CALL_HANDLE_REQUEST', false);
require_once 'helper.php';

// Verify digital signature
if (function_exists('openssl_verify')) {
    try {
        if (!defined('WI_VERIFY_SIGNATURE') || (defined('WI_VERIFY_SIGNATURE') && WI_VERIFY_SIGNATURE)) {
            Webinterpret_Bridge2Cart_Helper::verifyRequest();
        }
    } catch (Exception $e) {
        Webinterpret_Bridge2Cart_Helper::sendResourceForbiddenResponse();
        die();
    }
}

// Validate config
if (Webinterpret_Bridge2Cart_Helper::validateBridgeConfig() === false) {
    die('invalid config');
    Webinterpret_Bridge2Cart_Helper::sendResourceForbiddenResponse();
    die();
}

// Prepare bridge to set M1_STORE_BASE_DIR
if (Webinterpret_Bridge2Cart_Helper::getStoreBaseDir() !== false) {
    $_SERVER['SCRIPT_FILENAME'] = Webinterpret_Bridge2Cart_Helper::getStoreBaseDir() . DS . 'bridge2cart' . DS . 'bridge.php';
    unset($_SERVER['PATH_TRANSLATED']);
} else {
    Webinterpret_Bridge2Cart_Helper::sendResourceNotFoundResponse();
    die();
}
