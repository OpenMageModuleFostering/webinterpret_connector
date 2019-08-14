<?php
/**
 * Default helper
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function saveConfig()
    {
        Mage::getSingleton('adminhtml/session')->getMessages(true);

        // Validate key
        $key = $this->getWebinterpretKey();
        if (!$this->validateWebinterpretKey($key)) {
            Mage::getSingleton('adminhtml/session')->addError($this->__("Webinterpret Key is not correct. Please try again."));
            return;
        }

        Mage::getConfig()->saveConfig('webinterpret_connector/installation_mode', 0);
    }

    public function installBridgeHelper()
    {
        $repo = Mage::getStoreConfig('webinterpret_connector/file_repository');
        $dir = $this->getModuleBridgeDir();

        // Install helper.php
        $remoteFile = $repo . DS . 'bridge2cart' . DS . $this->getExtensionVersion() . DS . 'helper.php';
        $contents = $this->downloadFile($remoteFile);
        if ($contents === false) {
            return false;
        }
        $localFile = $dir . DS . 'helper.php';
        $contents = str_replace('exit();', '', $contents);
        if (@file_put_contents($localFile, $contents) === false) {
            return false;
        }

        return true;
    }

    public function downloadFile($url, $timeout = 10)
    {
        try {
            $method = $this->getDownloadMethod();

            if ($method == 'stream') {
                $ctx = stream_context_create(array(
                    'http'=>
                        array(
                            'timeout' => $timeout,
                        ),
                    'https'=>
                        array(
                            'timeout' => $timeout,
                        ),
                    )
                );
                $contents = @file_get_contents($url, false, $ctx);
                if ($contents !== false) {
                    return $contents;
                }
            }

            if ($method == 'curl') {

                $ch = curl_init();
                $headers = array(
                    "Accept: */*",
                );
                curl_setopt_array($ch, array(
                    CURLOPT_RETURNTRANSFER => 1,
                    CURLOPT_URL => $url,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_HTTPHEADER => $headers,
                ));

                $contents = curl_exec($ch);

                if (curl_errno($ch)) {
                    curl_close($ch);
                    return false;
                }

                curl_close($ch);
                return $contents;
            }
        } catch (Exception $e) {
            return false;
        }

        return false;
    }

    public function validateWebinterpretKey($key)
    {
        return (!empty($key) && strlen($key) == 32);
    }

    public function isEnabled()
    {
        return (bool)Mage::getStoreConfig('webinterpret_connector/enabled');
    }

    public function getWebinterpretKey()
    {
        $key = Mage::getStoreConfig('webinterpret_connector/key');
        $key2 = trim($key);
        if ($key != $key2) {
            Mage::getConfig()->saveConfig('webinterpret_connector/key', $key);
        }
        return $key2;
    }

    public function isInstallationMode()
    {
        return (bool)Mage::getStoreConfig('webinterpret_connector/installation_mode');
    }

    public function isAutomaticUpdatesEnabled()
    {
        return (bool)Mage::getStoreConfig('webinterpret_connector/automatic_updates_enabled');
    }

    public function isGlobalNotificationsEnabled()
    {
        return (bool)Mage::getStoreConfig('webinterpret_connector/global_notifications_enabled');
    }

    public function isStoreExtenderEnabled()
    {
        return (bool)Mage::getStoreConfig('webinterpret_connector/store_extender_enabled');
    }

    public function isFooterEnabled()
    {
        $module = $this->isEnabled();
        $feature = (bool)Mage::getStoreConfig('webinterpret_connector/footer_enabled');
        return $module && $feature;
    }

    public function getModuleBridgeDir()
    {
        $dir = Mage::getModuleDir('controllers', 'Webinterpret_Connector');
        $dir = realpath($dir . '/../bridge2cart');
        return $dir;
    }

    public function getBaseBridgeDir()
    {
        $dir = Mage::getBaseDir() . DS . 'bridge2cart';
        return $dir;
    }

    public function getStoreBaseUrl()
    {
        if (Mage::app()->getStore()->isCurrentlySecure()) {
            return Mage::getStoreConfig('web/secure/base_url');
        }
        return Mage::getStoreConfig('web/unsecure/base_url');
    }

    /**
     * Check if cookie restriction notice should be displayed
     *
     * Always return false for pre-1.7 magento
     *
     * @return bool
     */
    public function isUserNotAllowSaveCookie()
    {
        if (class_exists('Mage_Core_Helper_Cookie')) {
            return Mage::helper('core/cookie')->isUserNotAllowSaveCookie();
        }
        return false;
    }

    public function getMagentoVersionString()
    {
        $modules = (array)Mage::getConfig()->getNode('modules')->children();
        $edition = (array_key_exists('Enterprise_Enterprise', $modules)) ? 'Enterprise Edition' : 'Community Edition';
        $version = Mage::getVersion();
        return "Magento {$edition} {$version}";
    }

    public function getMagentoVersionNumber()
    {
        return Mage::getVersion();
    }

    public function isRemoteFilesAllowed()
    {
        if (ini_get('allow_url_fopen')) {
            return true;
        }
        if (function_exists('curl_version')) {
            return true;
        }

        return false;
    }

    public function getDownloadMethod()
    {
        if (ini_get('allow_url_fopen')) {
            return 'stream';
        }
        if (function_exists('curl_version')) {
            return 'curl';
        }

        return false;
    }

    public function isOpenSslEnabled()
    {
        return function_exists('openssl_verify');
    }

    public function selfTest()
    {
        $report = array();

        // Webinterpret Key
        $report['webinterpret_key']['label'] = $this->__('Webinterpret Key');
        $key = $this->getWebinterpretKey();
        if ($this->validateWebinterpretKey($key)) {
            $report['webinterpret_key']['info'] = $this->__('Key is valid');
        } else {
            $report['webinterpret_key']['errors'] = array();
            if (empty($key)) {
                $report['webinterpret_key']['errors'][] = $this->__('Key is missing. Please enter your Webinterpret Key. If you do not have one then <a href="https://webstores.webinterpret.com" target="_blank">click here to register</a>.');
            } else {
                $report['webinterpret_key']['errors'][] = $this->__('Incorrect key. Please try entering your key again or <a href="https://webstores.webinterpret.com" target="_blank">get a new key</a>');
            }
        }

        // Integration
        $report['integration']['label'] = $this->__('Integration');
        if ($this->isEnabled()) {
            $report['integration']['info'] = $this->__('Extension is ON');
        } else {
            $report['integration']['errors'] = array();
            $report['integration']['errors'][] = $this->__('Integration is turned OFF. Please set "Enabled" field to "Yes" and click the "Save Config" button.');
        }

        // Plugin version
        $report['plugin_version']['label'] = $this->__('Plugin');
        $report['plugin_version']['info'] = $this->getExtensionVersion();

        // PHP Version
        $report['php_version']['label'] = $this->__('PHP');
        $report['php_version']['info'] = phpversion();
        if (version_compare(phpversion(), '5.0.0', '<')) {
            $report['php_version']['errors'] = array();
            $report['php_version']['errors'][] = $this->__('Unsupported PHP version. Please upgrade to version 5 or higher.');
        }

        // Magento version
        $report['magento_version']['label'] = $this->__('Magento');
        $report['magento_version']['info'] = $this->getMagentoVersionString();
        if (version_compare($this->getMagentoVersionNumber(), '1.6', '<')) {
            $report['magento_version']['errors'] = array();
            $report['magento_version']['errors'][] = $this->__('Unsupported Magento version. Please upgrade to version 1.6 or higher.');
        }

        // OpenSSL
        $report['openssl']['label'] = $this->__('OpenSSL');
        if ($this->isOpenSslEnabled()) {
            $report['openssl']['info'] = OPENSSL_VERSION_TEXT;
        } else {
            $report['openssl']['errors'] = array();
            $report['openssl']['errors'][] = $this->__('OpenSSL not found. Please install OpenSSL on this server.');
        }

        // Remote files
        $report['download_method']['label'] = $this->__('Download Method');
        $method = $this->getDownloadMethod();
        if ($method == false) {
            $report['download_method']['errors'] = array();
            $report['download_method']['errors'][] = $this->__('No supported download method was found. Please enable allow_url_fopen in php.ini or install cURL on this server.');
        } else {
            $report['download_method']['info'] = $this->__('Using %s', $method);
        }

        // Extension files
        $report['extension_files']['label'] = $this->__('Extension Files');
        $files = array(
            array(
                'type' => 'dir',
                'path' => 'app/design/frontend/base/default/layout/webinterpret',
            ),
            array(
                'type' => 'dir',
                'path' => 'app/design/frontend/base/default/template/webinterpret',
            ),
            array(
                'type' => 'dir',
                'path' => 'app/design/adminhtml/default/default/layout/webinterpret',
            ),
            array(
                'type' => 'dir',
                'path' => 'app/design/adminhtml/default/default/template/webinterpret',
            ),
            array(
                'type' => 'dir',
                'path' => 'app/code/community/Webinterpret',
            ),
            array(
                'type' => 'dir',
                'path' => 'js/webinterpret',
            ),
            array(
                'type' => 'file',
                'path' => 'app/design/frontend/base/default/layout/webinterpret/connector.xml',
            ),
            array(
                'type' => 'file',
                'path' => 'app/design/frontend/base/default/template/webinterpret/connector/footer.phtml',
            ),
            array(
                'type' => 'file',
                'path' => 'app/design/frontend/base/default/template/webinterpret/connector/product_view.phtml',
            ),
            array(
                'type' => 'file',
                'path' => 'app/design/adminhtml/default/default/layout/webinterpret/connector.xml',
            ),
            array(
                'type' => 'file',
                'path' => 'app/design/adminhtml/default/default/template/webinterpret/system/config/activate.phtml',
            ),
            array(
                'type' => 'file',
                'path' => 'app/design/adminhtml/default/default/template/webinterpret/system/config/fieldset/banner.phtml',
            ),
            array(
                'type' => 'file',
                'path' => 'app/design/adminhtml/default/default/template/webinterpret/system/config/fieldset/status.phtml',
            ),
            array(
                'type' => 'file',
                'path' => 'app/etc/modules/Webinterpret_Connector.xml',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/Helper/Data.php',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/etc/system.xml',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/etc/adminhtml.xml',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/etc/config.xml',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/controllers/IndexController.php',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/controllers/HelperController.php',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/Block/Adminhtml/Notifications.php',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/Block/Adminhtml/System/Config/Fieldset/Status.php',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/Block/Product/View.php',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/Block/Footer.php',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/Model/Notification.php',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/Model/Observer.php',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/bridge2cart/bridge.php',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/bridge2cart/config.php',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/bridge2cart/helper.php',
            ),
            array(
                'type' => 'file',
                'path' => 'app/code/community/Webinterpret/Connector/bridge2cart/preloader.php',
            ),
        );

        // Check file permissions
        foreach ($files as $file) {
            $path = Mage::getBaseDir() . DS . $file['path'];

            if ($file['type'] == 'file') {
                if (file_exists($path)) {
                    if ($this->isAutomaticUpdatesEnabled()) {
                        if (!is_writeable($path)) {
                            // Attempt repair
                            @chmod($path, 0664);
                        }
                        if (!is_writeable($path)) {
                            $report['extension_files']['errors'][] = $this->__("File is not writable: %s<br>Please update the file permissions.", $path);
                        }
                    }
                } else {
                    $report['extension_files']['errors'][] = $this->__("File is missing: %s<br>Please upload the file or reinstall this extension using Magento Connect Manager.", $path);
                }
            }

            if ($file['type'] == 'dir') {
                if (file_exists($path) && $this->isAutomaticUpdatesEnabled()) {
                    if (!is_writeable($path)) {
                        // Attempt repair
                        @chmod($path, 0775);
                    }
                    if (!is_writeable($path)) {
                        $report['extension_files']['errors'][] = $this->__("Directory is not writable: %s<br>Please update the file permissions.", $path);
                    }
                }
            }
        }
        if (!isset($report['extension_files']['errors'])) {
            $report['extension_files']['info'] = $this->__('Extension is installed');
        }

        return $report;
    }

    public function getExtensionVersion()
    {
        return (string) Mage::getConfig()->getNode()->modules->Webinterpret_Connector->version;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function verifyRequest() {
        if (!function_exists( 'openssl_verify')) {
            throw new \Exception('OpenSSL is required.');
        }

        $requestId = $_SERVER['HTTP_WEBINTERPRET_REQUEST_ID'];
        $signature = $_SERVER['HTTP_WEBINTERPRET_SIGNATURE'];
        $signature = base64_decode( $signature );

        $version = $_SERVER['HTTP_WEBINTERPRET_SIGNATURE_VERSION'];
        if (empty( $version )) {
            $version = 1;
        }

        // Data
        $data = $requestId;
        if ($version == 2) {
            // Query data
            $queryArray = $_GET;
            ksort($queryArray);
            $queryData = serialize($queryArray);

            // Post data
            $postData = $_POST;
            ksort($postData);
            $postData = serialize($postData);

            $data = $requestId . $queryData . $postData;
        }

        // Verify signature
        $public_key = Mage::getStoreConfig('webinterpret_connector/public_key');
        $ok         = openssl_verify($data, $signature, $public_key, OPENSSL_ALGO_SHA1);

        if ($ok == 1) {
            return true;
        }
        if ($ok == 0) {
            throw new \Exception('Incorrect signature.');
        }

        throw new \Exception('Error checking signature');
    }
}
