<?php
/**
 * Bridge helper
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

if (!function_exists('stripslashes_array')) {
    function stripslashes_array($array) {
        return is_array($array) ? array_map('stripslashes_array', $array) : stripslashes($array);
    }
}

if (!function_exists('lcfirst')) {
    function lcfirst($str) {
        $str[0] = strtolower($str[0]);
        return (string)$str;
    }
}

if (!isset($_SERVER)) {
    $_GET     = &$HTTP_GET_VARS;
    $_POST    = &$HTTP_POST_VARS;
    $_ENV     = &$HTTP_ENV_VARS;
    $_SERVER  = &$HTTP_SERVER_VARS;
    $_COOKIE  = &$HTTP_COOKIE_VARS;
    $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
}

if (get_magic_quotes_gpc()) {
    $_COOKIE  = stripslashes_array($_COOKIE);
    $_FILES   = stripslashes_array($_FILES);
    $_GET     = stripslashes_array($_GET);
    $_POST    = stripslashes_array($_POST);
    $_REQUEST = stripslashes_array($_REQUEST);
}

if (!function_exists('getallheaders'))  {
    function getallheaders()  {
        $headers = '';
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

class Webinterpret_Bridge2Cart_Helper
{
    const VERSION = '1.2.7.7';
    const PLATFORM_MAGENTO = 'magento';
    const PLATFORM_UNKNOWN = 'unknown';
    protected static $_storeBaseDir = null;

    public static function getVersion()
    {
        return self::VERSION;
    }

    public static function getStoreBaseDir()
    {
        if (!is_null(self::$_storeBaseDir)) {
            return self::$_storeBaseDir;
        }

        $dir = '';

        // Auto-detect Magento - Method: mod/_modules
        $dir = realpath(dirname(__FILE__));
        if (strpos($dir, 'mod/_modules') !== false) {
            if (strpos($dir, 'httpdocs') !== false) {
                $dir = substr($dir, 0, strpos($dir, 'httpdocs')) . 'httpdocs';
                $filename = realpath($dir . DS . 'app' . DS . 'Mage.php');
                if (file_exists($filename)) {
                    $dir = dirname(dirname($filename));
                    self::$_storeBaseDir = $dir;
                    return self::$_storeBaseDir;
                }
                $filename = realpath($dir . DS . 'pub' . DS . 'app' . DS . 'Mage.php');
                if (file_exists($filename)) {
                    $dir = dirname(dirname($filename));
                    self::$_storeBaseDir = $dir;
                    return self::$_storeBaseDir;
                }
            }
        }

        // Auto-detect Magento - Method: .modman
        $dir = realpath(dirname(__FILE__));
        if (strpos($dir, '.modman') !== false) {
            $dir = realpath(substr($dir, 0, strpos($dir, '.modman')));
            $filename = $dir . DS . 'app' . DS . 'Mage.php';
            if (file_exists($filename)) {
                $dir = dirname(dirname($filename));
                self::$_storeBaseDir = $dir;
                return self::$_storeBaseDir;
            }
            $filename = $dir . DS . 'shop' . DS . 'app' . DS . 'Mage.php';
            if (file_exists($filename)) {
                $dir = dirname(dirname($filename));
                self::$_storeBaseDir = $dir;
                return self::$_storeBaseDir;
            }
        }

        // Auto-detect Magento - Method: vendor
        $dir = realpath(dirname(__FILE__));
        if (strpos($dir, 'vendor') !== false) {
            $dir = realpath(substr($dir, 0, strpos($dir, 'vendor')));
            $filename = $dir . DS . 'app' . DS . 'Mage.php';
            if (file_exists($filename)) {
                $dir = dirname(dirname($filename));
                self::$_storeBaseDir = $dir;
                return self::$_storeBaseDir;
            }
            $filename = $dir . DS . 'htdocs' . DS . 'app' . DS . 'Mage.php';
            if (file_exists($filename)) {
                $dir = dirname(dirname($filename));
                self::$_storeBaseDir = $dir;
                return self::$_storeBaseDir;
            }
        }

        // Auto-detect Magento - Method: default
        // app/code/community/Webinterpret/Connector/bridge2cart
        $dir = realpath(dirname(__FILE__) . DS . '..' . DS . '..' . DS . '..' . DS . '..' . DS . '..' . DS . '..');
        $filename = $dir . DS . 'app' . DS . 'Mage.php';
        if (file_exists($filename)) {
            self::$_storeBaseDir = $dir;
            return self::$_storeBaseDir;
        }

        $iterator = new RecursiveDirectoryIterator($_SERVER['DOCUMENT_ROOT']);
        foreach (new RecursiveIteratorIterator($iterator) as $file) {
            if ($file->isFile() &&  $file->getFilename() == 'Mage.php' && 
                strpos($file->getPathname(), '/app/Mage.php') !== false) {
                $dir = dirname(dirname($file->getPathname()));
                self::$_storeBaseDir = $dir;
                return self::$_storeBaseDir;
            }
        }

        return false;
    }

    public static function getBridgeDir()
    {
        $dir = realpath(dirname(__FILE__));
        return $dir;
    }

    public static function getPluginDir()
    {
        $dir = realpath(dirname(__FILE__) . DS . '..');
        return $dir;
    }

    public static function getProcessOwner()
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            return posix_getpwuid(posix_geteuid());
        }
        return '-';
    }

    public static function getDownloadMethod()
    {
        if (ini_get('allow_url_fopen')) {
            return 'stream';
        }
        if (function_exists('curl_version')) {
            return 'curl';
        }

        return false;
    }

    public static function downloadFile($url, $timeout = 10)
    {
        try {
            $method = self::getDownloadMethod();

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

    public static function loadBridgeConfig()
    {
        if (defined('M1_TOKEN')) {
            return true;
        }
        $path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'config.php';
        if (!file_exists($path)) {
            return false;
        }
        require_once $path;
        return true;
    }

    public static function validateBridgeConfig()
    {
        $dir = self::getBridgeDir();
        $path = $dir . DS . 'config.php';
        if (!file_exists($path)) {
            return false;
        }
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return false;
        }
        if (strpos($contents, 'M1_TOKEN') === false) {
            return false;
        }
        if (strpos($contents, '%m1_token%') !== false) {
            return false;
        }

        return true;
    }

    public static function validateBridgeToken()
    {
        if (!isset($_GET['token'])) {
            throw new Exception('token parameter is required.');
        }
        if ($_GET['token'] == '%m1_token%') {
            throw new Exception('Invalid token.');
        }
        if (!defined('M1_TOKEN')) {
            self::loadBridgeConfig();
            if (!defined('M1_TOKEN')) {
                throw new Exception('Failed to load bridge configuration.');
            }
        }
        if (M1_TOKEN == '%m1_token%' || M1_TOKEN == '') {
            throw new Exception('Token has not been saved in bridge configuration.');
        }
        if ($_GET['token'] != M1_TOKEN) {
            throw new Exception('Incorrect token.');
        }

        return true;
    }

    public static function sendResourceNotFoundResponse()
    {
        header('HTTP/1.0 404 Not Found');
        header('Status: 404 Not Found');
        echo "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML 2.0//EN\">" . PHP_EOL;
        echo "<html><head>" . PHP_EOL;
        echo "<title>404 Not Found</title>" . PHP_EOL;
        echo "</head><body>" . PHP_EOL;
        echo "<h1>Response Not Found</h1>" . PHP_EOL;
        echo "<p>Requested resource could not be found.</p>" . PHP_EOL;
        echo "</body></html>" . PHP_EOL;
    }

    public static function handleRequest()
    {
        try {
            $action = isset($_GET['action']) ? $_GET['action'] : false;

            // Validate token
            $validateToken = true;
            $validateTokenExcluded = array('plugin.diagnostics');
            if (in_array($action, $validateTokenExcluded)) {
                $validateToken = false;
            }
            if ($validateToken) {
                self::validateBridgeToken();
            }

            // Process action
            if ($action) {

                // Dynamically call static method
                $method = lcfirst(str_replace(' ', '', ucwords(str_replace('.', ' ', $action)))) . 'Action';
                if (method_exists(__CLASS__, $method)) {
                    $function = __CLASS__ . '::' . $method;
                    $response = call_user_func($function);
                } else {
                    throw new Exception('Unknown action');
                }
            } else {
                throw new Exception('action parameter is required.');
            }
        } catch (Exception $e) {
            $response = self::newResponseError($e->getMessage());
        }

        self::sendJsonResponse($response);
        die();
    }

    public static function newResponse()
    {
        $response = array();
        $response['status'] = 'success';
        $response['response_id'] = md5(uniqid(mt_rand(), true));
        return $response;
    }

    public static function sendJsonResponse($response)
    {
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    public static function newResponseError($message)
    {
        $response = array();
        $response['status'] = 'error';
        $response['response_id'] = md5(uniqid(mt_rand(), true));
        $response['error_message'] = $message;
        return $response;
    }

    public static function pluginPingAction()
    {
        $response = self::newResponse();
        return $response;
    }

    public static function platformCacheInfoAction()
    {
        $response = self::newResponse();
        $response['cache'] = array();
        $platform = self::getPlatform();

        if ($platform == self::PLATFORM_MAGENTO) {
            try {
                self::loadMagento();
                $types = Mage::app()->getCacheInstance()->getTypes();
                foreach ($types as $type) {
                    $response['cache'][] = $type->getData();
                }
            } catch (Exception $e) {
                $response['status'] = 'error';
            }
        } else {
            $response['status'] = 'error';
            $response['error_message'] = 'Unsupported platform';
        }

        return $response;
    }

    public static function getPlatform()
    {
        // Detect Magento
        $filename = self::getStoreBaseDir() . DS . 'app' . DS . 'Mage.php';
        if (file_exists($filename)) {
            return self::PLATFORM_MAGENTO;
        }

        return self::PLATFORM_UNKNOWN;
    }

    public static function getWebinterpretKey()
    {
        $platform = self::getPlatform();
        if ($platform == self::PLATFORM_MAGENTO) {
            self::loadMagento();
            $key = Mage::getStoreConfig('webinterpret_connector/key');
        } else {
            throw new Exception("Method is not supported on this platform: $platform");
        }
        return $key;
    }

    public static function platformConfigurationUpdateAction()
    {
        $response = self::newResponse();
        $platform = self::getPlatform();

        if (!isset($_REQUEST['config_key'])) {
            throw new Exception('config_key parameter is required.');
        }
        $key = $_REQUEST['config_key'];

        if (!isset($_REQUEST['config_value'])) {
            throw new Exception('config_value parameter is required.');
        }
        $value = $_REQUEST['config_value'];

        if ($platform == self::PLATFORM_MAGENTO) {
            self::loadMagento();
            Mage::getConfig()->saveConfig($key, $value);
            Mage::getConfig()->reinit();
        } else {
            $response['status'] = 'error';
            $response['error_message'] = 'Unsupported platform';
        }

        return $response;
    }

    public static function pluginVersionAction()
    {
        $response = self::newResponse();
        $response['php_version'] = phpversion();
        $response['plugin_helper_version'] = self::getVersion();
        $platform = self::getPlatform();
        $baseDir = self::getStoreBaseDir();
        $response['platform'] = $platform;

        if ($platform == self::PLATFORM_MAGENTO) {
            self::loadMagento();
            $response['magento_version'] = Mage::getVersion();

            $modules = (array)Mage::getConfig()->getNode('modules')->children();
            $edition = (array_key_exists('Enterprise_Enterprise', $modules)) ? 'Enterprise Edition' : 'Community Edition';
            $version = Mage::getVersion();

            $response['magento_version'] = "{$edition} {$version}";

            $file = $baseDir . '/app/code/community/Webinterpret/Connector/etc/config.xml';
            if (file_exists($file)) {
                try {
                    $xml = simplexml_load_file($file);
                    $version = (string)$xml->modules[0]->Webinterpret_Connector[0]->version;
                    $response['plugin_connector_version'] = $version;
                } catch (Exception $e) {
                    $response['status'] = 'error';
                    $response['error_message'] = 'Failed to get Magento version';
                }
            }
        } else {
            $response['status'] = 'error';
            $response['error_message'] = 'Unsupported platform';
        }

        return $response;
    }

    public static function platformInfoAction()
    {
        $response = self::pluginVersionAction();

        $platform = self::getPlatform();
        $response['platform'] = $platform;
        $response['base_dir'] = self::getStoreBaseDir();
        $response['plugin_dir'] = self::getPluginDir();
        $response['bridge_dir'] = self::getBridgeDir();
        $response['download_method'] = self::getDownloadMethod();

        // Magento
        if ($platform == self::PLATFORM_MAGENTO) {
            $response['magento_compiler'] = self::getCompilerStatus();
        }

        return $response;
    }

    public static function pluginDiagnosticsAction()
    {
        $response = self::newResponse();
        $response['php_version'] = phpversion();
        $response['php_function_mysql_connect'] = function_exists('mysql_connect') ? 'yes' : 'no';
        $response['php_function_mysqli_connect'] = function_exists('mysqli_connect') ? 'yes' : 'no';
        $response['plugin_helper_version'] = self::getVersion();
        $baseDir = self::getStoreBaseDir();
        $response['base_dir'] = $baseDir;
        $platform = self::getPlatform();
        $response['platform'] = self::getPlatform();

        // Magento
        if ($platform == self::PLATFORM_MAGENTO) {
            self::loadMagento();

            // Get version
            $response['magento_version'] = Mage::getVersion();
            $modules = (array)Mage::getConfig()->getNode('modules')->children();
            $edition = (array_key_exists('Enterprise_Enterprise', $modules)) ? 'Enterprise Edition' : 'Community Edition';
            $version = Mage::getVersion();
            $response['magento_version'] = "{$edition} {$version}";

            // Check if module exists
            $response['magento_module_exists'] = 'no';
            $modules = (array)Mage::getConfig()->getNode('modules')->children();
            if (isset($modules['Webinterpret_Connector'])) {
                $response['magento_module_exists'] = 'yes';

                // Check if Webinterpret module has been enabled
                $response['magento_module_enabled'] = Mage::helper('core')->isModuleEnabled('Webinterpret_Connector') ? 'yes' : 'no';

                // Check which version has been installed
                $file = $baseDir . '/app/code/community/Webinterpret/Connector/etc/config.xml';
                if (file_exists($file)) {
                    try {
                        $xml = simplexml_load_file($file);
                        $version = (string)$xml->modules[0]->Webinterpret_Connector[0]->version;
                        $response['plugin_connector_version'] = $version;
                    } catch (Exception $e) {
                        $response['status'] = 'error';
                        $response['error_message'] = 'Failed to get Magento version';
                    }
                }
            }

            // Check file permissions
            $response['report'] = Mage::helper('webinterpret_connector')->selfTest();
        }

        return $response;
    }

    public static function pluginConfigurationInfoAction()
    {
        $response = self::newResponse();
        $platform = self::getPlatform();
        $response['platform'] = $platform;

        if ($platform == self::PLATFORM_MAGENTO) {
           $response['webinterpret_connector'] = Mage::getStoreConfig('webinterpret_connector');
        } else {
            $response['status'] = 'error';
            $response['error_message'] = 'Unsupported platform';
        }

        return $response;
    }

    public static function loadMagento()
    {
        if (class_exists('Mage')) {
            return true;
        }
        $storeBaseDir = self::getStoreBaseDir();
        $path = $storeBaseDir . DS . 'app' . DS .'Mage.php';
        if (!file_exists($path)) {
            return false;
        }
        require_once $path;
        return true;
    }

    public static function platformPluginsInfoAction()
    {
        $response = self::newResponse();
        $baseDir = self::getStoreBaseDir();
        $platform = self::getPlatform();

        // Magento
        if ($platform == self::PLATFORM_MAGENTO) {
            if (file_exists($baseDir . '/app/etc/local.xml')) {
                $dir = $baseDir . '/app/etc/modules';
                $files = scandir($dir);
                $plugins = array();
                foreach ($files as $key => $value) {
                    if (!in_array($value,array('.', '..')))  {
                        $file = $dir . DIRECTORY_SEPARATOR . $value;
                        if (is_file($file)) {
                            try {
                                $xml = simplexml_load_file($file);
                                if (is_object($xml) && $xml->modules->count() > 0 && $xml->modules[0]->count() > 0) {
                                    foreach ($xml->modules[0]->children() as $module) {
                                        $plugins[] = array(
                                            'name' => $module->getName(),
                                            'active' => (string)$module->active
                                        );
                                    }
                                }
                            } catch (Exception $e) {
                                // Skip
                            }
                        }
                    }
                }
                $response['plugins'] = $plugins;
            }
        } else {
            $response['status'] = 'error';
            $response['error_message'] = 'Unsupported platform';
        }

        return $response;
    }

    public static function platformConfigurationInfoAction()
    {
        $response = self::newResponse();
        $baseDir = self::getStoreBaseDir();

        if (file_exists($baseDir . '/app/etc/local.xml')) {
            $local = @file_get_contents($baseDir . '/app/etc/local.xml');
            if ($local === false) {
                $response['status'] = 'error';
                $response['error_message'] = 'Failed to read configuration file';
            } else {
                $response['local'] = base64_encode($local);
            }
        }

        return $response;
    }

    public static function systemPhpInfoAction()
    {
        phpinfo();
    }

    public static function getCompilerStatus()
    {
        $platform = self::getPlatform();
        if ($platform == self::PLATFORM_MAGENTO) {
            self::loadMagento();
            return defined('COMPILER_INCLUDE_PATH') ? 'enabled' : 'disabled';
        }

        return false;
    }

    public static function platformCompilerUpdateAction()
    {
        $response = self::newResponse();
        $platform = self::getPlatform();
        if ($platform == self::PLATFORM_MAGENTO) {
            self::loadMagento();

            // Status
            if (!isset($_REQUEST['status']) || empty($_REQUEST['status'])) {
                throw new Exception('status parameter is required');
            }
            $status = $_REQUEST['status'];

            // Enable or disable compiler
            $compiler = Mage::getModel('compiler/process');
            if ($status == 'enable') {
                $compiler->registerIncludePath();
            }
            if ($status == 'disable') {
                $compiler->registerIncludePath(false);
            }
        }

        return $response;
    }

    public static function platformCompilerRunAction()
    {
        $response = self::newResponse();
        $platform = self::getPlatform();
        if ($platform == self::PLATFORM_MAGENTO) {
            self::loadMagento();
            $compiler = Mage::getModel('compiler/process');
            $result = $compiler->validate();
            if (!empty($result)) {
                throw new Exception('Cannot run compliation process');
            }
            $compiler->run();
        }

        return $response;
    }

    public static function platformCompilerClearAction()
    {
        $response = self::newResponse();
        $platform = self::getPlatform();
        if ($platform == self::PLATFORM_MAGENTO) {
            self::loadMagento();
            $compiler = Mage::getModel('compiler/process');
            $compiler->clear();
        }

        return $response;
    }

    public static function platformConfigurationRefreshAction()
    {
        $response = self::newResponse();
        $platform = self::getPlatform();

        if ($platform == self::PLATFORM_MAGENTO) {
            self::loadMagento();
            Mage::getConfig()->reinit();
        }

        return $response;
    }

    public static function platformCacheRefreshAction()
    {
        $response = self::newResponse();
        $response['zend_cache'] = 0;
        $response['errors'] = array();
        $response['log'] = array();
        $platform = self::getPlatform();

        if ($platform == self::PLATFORM_MAGENTO) {
            self::loadMagento();

            $typeFilter = null;
            if (isset($_REQUEST['types'])) {
                if (is_array($_REQUEST['types'])) {
                    $typeFilter = $_REQUEST['types'];
                }
                if (is_string($_REQUEST['types'])) {
                    $typeFilter = array($_REQUEST['types']);
                }
            }

            // Clear cache - method 1
            if (is_null($typeFilter)) {
                $app = Mage::app();
                if ($app != null) {
                    $cache = $app->getCache();
                    if($cache != null) {
                        $cache->clean();
                    }
                }
            }

            // Clear cache - method 2
            $types = Mage::app()->getCacheInstance()->getTypes();
            foreach ($types as $type) {
                $typeId = $type->getId();
                if (is_array($typeFilter) && !in_array($typeId, $typeFilter)) {
                    continue; // skip
                }
                $response['log'][] = "Clear cache: $typeId";
                Mage::app()->getCacheInstance()->cleanType($type);
            }

            // Reload configuration
            Mage::getConfig()->reinit();

            // Clear Zend Cache
            if (is_null($typeFilter)) {
                if (extension_loaded('Zend Page Cache') && function_exists('page_cache_remove_all_cached_contents')) {
                    $response['zend_cache'] = 1;
                    page_cache_remove_all_cached_contents();
                }
            }
        } else {
            $response['status'] = 'error';
            $response['error_message'] = 'Unsupported platform';
        }

        return $response;
    }

    public static function systemFileListAction()
    {
        $response = self::newResponse();

        if (!isset($_REQUEST['dir'])) {
            throw new Exception('dir parameter is required');
        }
        $dir = $_REQUEST['dir'];
        if (!file_exists($dir)) {
            throw new Exception('No such file or directory');
        }

        $response['result'] = array();
        $iterator = new RecursiveDirectoryIterator($dir);
        foreach (new RecursiveIteratorIterator($iterator) as $fileInfo) {
            $response['result'][] = self::convertSplFileInfoToArray($fileInfo);
        }

        return $response;
    }

    public static function systemFileInfoAction()
    {
        $response = self::newResponse();

        if (!isset($_REQUEST['file'])) {
            throw new Exception('file parameter is required');
        }
        $file = $_REQUEST['file'];
        if (!file_exists($file)) {
            throw new Exception('No such file or directory');
        }

        $fileInfo = new SplFileInfo($file);
        $response['result'] = self::convertSplFileInfoToArray($fileInfo);
        return $response;
    }

    public static function systemFileRenameAction()
    {
        $response = self::newResponse();

        if (!isset($_REQUEST['oldname'])) {
            throw new Exception('oldname parameter is required');
        }
        $oldname = $_REQUEST['oldname'];

        if (!isset($_REQUEST['newname'])) {
            throw new Exception('newname parameter is required');
        }
        $newname = $_REQUEST['newname'];

        if (!file_exists($oldname)) {
            throw new Exception('No such file or directory');
        }

        if (!@rename($oldname, $newname)) {
            throw new Exception('Failed to rename file');
        }

        return $response;
    }

    public static function systemFileChmodAction()
    {
        $response = self::newResponse();

        if (!isset($_REQUEST['file'])) {
            throw new Exception('file parameter is required');
        }
        $file = $_REQUEST['file'];

        if (!isset($_REQUEST['mode'])) {
            throw new Exception('mode parameter is required');
        }
        $mode = $_REQUEST['mode'];

        if (!file_exists($file)) {
            throw new Exception('No such file or directory');
        }

        if (!@chmod($file, octdec($mode))) {
            throw new Exception('Failed to change file mode');
        }

        return $response;
    }

    public static function systemFileCopyAction()
    {
        $response = self::newResponse();

        // Source
        if (!isset($_REQUEST['source']) || empty($_REQUEST['source'])) {
            throw new Exception('source parameter is required');
        }
        $source = $_REQUEST['source'];

        if (filter_var($source, FILTER_VALIDATE_URL)) {
            $contents = self::downloadFile($source);
            if (!$contents) {
                throw new Exception('Failed to download file');
            }
        } else {
            if (!file_exists($source)) {
                throw new Exception('Source file does not exist');
            }
            $contents = @file_get_contents($source);
        }

        // Destination
        if (!isset($_REQUEST['dest']) || empty($_REQUEST['dest'])) {
            throw new Exception('dest parameter is required');
        }
        $dest = $_REQUEST['dest'];

        if (filter_var($dest, FILTER_VALIDATE_URL)) {
            throw new Exception('dest cannot be an URL');
        }

        // Write file
        if (@file_put_contents($dest, $contents) === false) {
            throw new Exception("Failed to write file: $dest");
        }
        @chmod($dest, 0664);

        return $response;
    }

    public static function systemFileMkdirAction()
    {
        $response = self::newResponse();

        if (!isset($_REQUEST['pathname'])) {
            throw new Exception('pathname parameter is required');
        }
        $pathname = $_REQUEST['pathname'];

        $mode = octdec('0775');
        if (isset($_REQUEST['mode'])) {
            $mode = octdec($_REQUEST['mode']);
        }

        $recursive = false;
        if (isset($_REQUEST['recursive'])) {
            $recursive = (bool)$_REQUEST['recursive'];
        }

        if (!@mkdir($pathname, $mode, $recursive)) {
            throw new Exception('Failed to make directory');
        }

        return $response;
    }

    public static function systemFileRmdirAction()
    {
        $response = self::newResponse();

        if (!isset($_REQUEST['pathname'])) {
            throw new Exception('pathname parameter is required');
        }
        $pathname = $_REQUEST['pathname'];

        if (!@rmdir($pathname)) {
            throw new Exception('Failed to remove directory');
        }

        return $response;
    }

    public static function convertSplFileInfoToArray($fileInfo)
    {
        $arr = array(
            'filename' => $fileInfo->getFilename(),
            'group' => $fileInfo->getGroup(),
            'owner' => $fileInfo->getOwner(),
            'path' => $fileInfo->getPath(),
            'pathname' => $fileInfo->getPathname(),
            'perms' => $fileInfo->getPerms(),
            'realpath' => $fileInfo->getRealPath(),
            'size' => $fileInfo->getSize(),
            'type' => $fileInfo->getType(),
            'is_dir' => $fileInfo->isDir(),
            'is_executable' => $fileInfo->isExecutable(),
            'is_file' => $fileInfo->isFile(),
            'is_link' => $fileInfo->isLink(),
            'is_readable' => $fileInfo->isReadable(),
            'is_writable' => $fileInfo->isWritable(),
        );
        if ($fileInfo->isLink()) {
            $arr['link_target'] = $fileInfo->getLinkTarget();
        }

        return $arr;
    }
}

if (!defined('WI_HELPER_CALL_HANDLE_REQUEST') || WI_HELPER_CALL_HANDLE_REQUEST) {
  Webinterpret_Bridge2Cart_Helper::handleRequest();
}
