<?php
/**
 * Bridge config
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */

// Load helper
if (!class_exists('Webinterpret_Bridge2Cart_Helper')) {
    if (!defined('WI_HELPER_CALL_HANDLE_REQUEST')) {
        define('WI_HELPER_CALL_HANDLE_REQUEST', false);
    }
    require_once 'helper.php';
}

// Set M1 token
define("M1_TOKEN", Webinterpret_Bridge2Cart_Helper::getWebinterpretKey());
