<?php

/*-----------------------------------------------------------------------------+
| MagneticOne                                                                  |
| Copyright (c) 2012 MagneticOne.com <contact@magneticone.com>                 |
| All rights reserved                                                          |
+------------------------------------------------------------------------------+
| PLEASE READ  THE FULL TEXT OF SOFTWARE LICENSE AGREEMENT IN THE "license.txt"|
| FILE PROVIDED WITH THIS DISTRIBUTION. THE AGREEMENT TEXT IS ALSO AVAILABLE   |
| AT THE FOLLOWING URL: http://www.magneticone.com/store/license.php           |
|                                                                              |
| THIS  AGREEMENT  EXPRESSES  THE  TERMS  AND CONDITIONS ON WHICH YOU MAY USE  |
| THIS SOFTWARE   PROGRAM   AND  ASSOCIATED  DOCUMENTATION   THAT  MAGNETICONE |
| (hereinafter  referred to as "THE AUTHOR") IS FURNISHING  OR MAKING          |
| AVAILABLE TO YOU WITH  THIS  AGREEMENT  (COLLECTIVELY,  THE  "SOFTWARE").    |
| PLEASE   REVIEW   THE  TERMS  AND   CONDITIONS  OF  THIS  LICENSE AGREEMENT  |
| CAREFULLY   BEFORE   INSTALLING   OR  USING  THE  SOFTWARE.  BY INSTALLING,  |
| COPYING   OR   OTHERWISE   USING   THE   SOFTWARE,  YOU  AND  YOUR  COMPANY  |
| (COLLECTIVELY,  "YOU")  ARE  ACCEPTING  AND AGREEING  TO  THE TERMS OF THIS  |
| LICENSE   AGREEMENT.   IF  YOU    ARE  NOT  WILLING   TO  BE  BOUND BY THIS  |
| AGREEMENT, DO  NOT INSTALL OR USE THE SOFTWARE.  VARIOUS   COPYRIGHTS   AND  |
| OTHER   INTELLECTUAL   PROPERTY   RIGHTS    PROTECT   THE   SOFTWARE.  THIS  |
| AGREEMENT IS A LICENSE AGREEMENT THAT GIVES  YOU  LIMITED  RIGHTS   TO  USE  |
| THE  SOFTWARE   AND  NOT  AN  AGREEMENT  FOR SALE OR FOR  TRANSFER OF TITLE. |
| THE AUTHOR RETAINS ALL RIGHTS NOT EXPRESSLY GRANTED BY THIS AGREEMENT.       |
|                                                                              |
| The Developer of the Code is MagneticOne,                                    |
| Copyright (C) 2006 - 2016 All Rights Reserved.                               |
+------------------------------------------------------------------------------+
|                                                                              |
|                            ATTENTION!                                        |
+------------------------------------------------------------------------------+
| By our Terms of Use you agreed not to change, modify, add, or remove portions|
| of Bridge Script source code as it is owned by MagneticOne company.          |
| You agreed not to use, reproduce, modify, adapt, publish, translate          |
| the Bridge Script source code into any form, medium, or technology           |
| now known or later developed throughout the universe.                        |
|                                                                              |
| Full text of our TOS located at                                              |
|                       https://www.api2cart.com/terms-of-service               |
+-----------------------------------------------------------------------------*/


/**
 * Class M1_Bridge_Action_Update
 */
class M1_Bridge_Action_Update
{
    private $_pathToTmpDir;
    private $_pathToFile = __FILE__;

    /**
     * M1_Bridge_Action_Update constructor.
     */
    public function __construct()
    {
        $this->_pathToTmpDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . "temp_c2c";
    }

    /**
     * @param M1_Bridge $bridge
     */
    public function perform(M1_Bridge $bridge)
    {
        $response = new stdClass();
        if (!($this->_checkBridgeDirPermission() && $this->_checkBridgeFilePermission())) {
            $response->code = 1;
            $response->message = "Bridge Update couldn't be performed. " .
                "Please change permission for bridge folder to 777 and bridge.php file inside it to 666";
            echo serialize($response);
            die;
        }

        if (($data = $this->_downloadFile()) === false) {
            $response->code = 1;
            $response->message = "Bridge Version is outdated. Files couldn't be updated automatically. " .
                "Please set write permission or re-upload files manually.";
            echo serialize($response);
            die;
        }

        if (!$this->_writeToFile($data, $this->_pathToFile)) {
            $response->code = 1;
            $response->message = "Couln't create file in temporary folder or file is write protected.";
            echo serialize($response);
            die;
        }

        $response->code = 0;
        $response->message = "Bridge successfully updated to latest version";
        echo json_encode($response);
        die;
    }

    /**
     * @param $uri
     * @return stdClass
     */
    private function _fetch($uri)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = new stdClass();

        $response->body = curl_exec($ch);
        $response->httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $response->contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $response->contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

        curl_close($ch);

        return $response;
    }

    /**
     * @return bool
     */
    private function _checkBridgeDirPermission()
    {
        if (!is_writeable(dirname(__FILE__))) {
            @chmod(dirname(__FILE__), 0777);
        }
        return is_writeable(dirname(__FILE__));
    }

    /**
     * @return bool
     */
    private function _checkBridgeFilePermission()
    {
        $pathToFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . "bridge.php";
        if (!is_writeable($pathToFile)) {
            @chmod($pathToFile, 0666);
        }
        return is_writeable($pathToFile);
    }

    /**
     * @return bool
     */
    public function _createTempDir()
    {
        @mkdir($this->_pathToTmpDir, 0777);
        return file_exists($this->_pathToTmpDir);
    }

    /**
     * @return bool
     */
    public function _removeTempDir()
    {
        @unlink($this->_pathToTmpDir . DIRECTORY_SEPARATOR . "bridge.php_c2c");
        @rmdir($this->_pathToTmpDir);
        return !file_exists($this->_pathToTmpDir);
    }

    /**
     * @return bool|stdClass
     */
    private function _downloadFile()
    {
        $file = $this->_fetch(M1_BRIDGE_DOWNLOAD_LINK);
        if ($file->httpCode == 200) {
            return $file;
        }
        return false;
    }

    /**
     * @param $data
     * @param $file
     * @return bool
     */
    private function _writeToFile($data, $file)
    {
        if (function_exists("file_put_contents")) {
            $bytes = file_put_contents($file, $data->body);
            return $bytes == $data->contentLength;
        }

        $handle = @fopen($file, 'w+');
        $bytes = fwrite($handle, $data->body);
        @fclose($handle);

        return $bytes == $data->contentLength;

    }

}

/**
 * Class M1_Bridge_Action_Query
 */
class M1_Bridge_Action_Query
{

    /**
     * @param M1_Bridge $bridge
     * @return bool
     */
    public function perform(M1_Bridge $bridge)
    {
        if (isset($_POST['query']) && isset($_POST['fetchMode'])) {
            $query = base64_decode($_POST['query']);

            $res = $bridge->query($query, (int)$_POST['fetchMode']);

            if (is_array($res['result']) || is_bool($res['result'])) {
                $result = serialize(array(
                    'res' => $res['result'],
                    'fetchedFields' => @$res['fetchedFields'],
                    'insertId' => $bridge->getLink()->getLastInsertId(),
                    'affectedRows' => $bridge->getLink()->getAffectedRows(),
                ));

                echo base64_encode($result);
            } else {
                echo base64_encode($res['message']);
            }
        } else {
            return false;
        }
    }
}

/**
 * Class M1_Bridge_Action_Getconfig
 */
class M1_Bridge_Action_Getconfig
{

    /**
     * @param $val
     * @return int
     */
    private function parseMemoryLimit($val)
    {
        $valInt = (int)$val;
        $last = strtolower($val[strlen($val) - 1]);

        switch ($last) {
            case 'g':
                $valInt *= 1024;
            case 'm':
                $valInt *= 1024;
            case 'k':
                $valInt *= 1024;
        }

        return $valInt;
    }

    /**
     * @return mixed
     */
    private function getMemoryLimit()
    {
        $memoryLimit = trim(@ini_get('memory_limit'));
        if (strlen($memoryLimit) === 0) {
            $memoryLimit = "0";
        }
        $memoryLimit = $this->parseMemoryLimit($memoryLimit);

        $maxPostSize = trim(@ini_get('post_max_size'));
        if (strlen($maxPostSize) === 0) {
            $maxPostSize = "0";
        }
        $maxPostSize = $this->parseMemoryLimit($maxPostSize);

        $suhosinMaxPostSize = trim(@ini_get('suhosin.post.max_value_length'));
        if (strlen($suhosinMaxPostSize) === 0) {
            $suhosinMaxPostSize = "0";
        }
        $suhosinMaxPostSize = $this->parseMemoryLimit($suhosinMaxPostSize);

        if ($suhosinMaxPostSize == 0) {
            $suhosinMaxPostSize = $maxPostSize;
        }

        if ($maxPostSize == 0) {
            $suhosinMaxPostSize = $maxPostSize = $memoryLimit;
        }

        return min($suhosinMaxPostSize, $maxPostSize, $memoryLimit);
    }

    /**
     * @return bool
     */
    private function isZlibSupported()
    {
        return function_exists('gzdecode');
    }

    /**
     * @param $bridge
     */
    public function perform(M1_Bridge $bridge)
    {
        if (!defined("DEFAULT_LANGUAGE_ISO2")) {
            define("DEFAULT_LANGUAGE_ISO2", ""); //variable for Interspire cart
        }

        $result = array(
            "images" => array(
                "imagesPath" => $bridge->config->imagesDir, // path to images folder - relative to store root
                "categoriesImagesPath" => $bridge->config->categoriesImagesDir,
                "categoriesImagesPaths" => $bridge->config->categoriesImagesDirs,
                "productsImagesPath" => $bridge->config->productsImagesDir,
                "productsImagesPaths" => $bridge->config->productsImagesDirs,
                "manufacturersImagesPath" => $bridge->config->manufacturersImagesDir,
                "manufacturersImagesPaths" => $bridge->config->manufacturersImagesDirs,
            ),
            "languages" => $bridge->config->languages,
            "baseDirFs" => M1_STORE_BASE_DIR,    // filesystem path to store root
            "bridgeVersion" => M1_BRIDGE_VERSION,
            "defaultLanguageIso2" => DEFAULT_LANGUAGE_ISO2,
            "databaseName" => $bridge->config->dbname,
            "memoryLimit" => $this->getMemoryLimit(),
            "zlibSupported" => $this->isZlibSupported(),
            //"orderStatus"           => $bridge->config->orderStatus,
            "cartVars" => $bridge->config->cartVars,
        );

        echo serialize($result);
    }

}

/**
 * Class M1_Bridge_Action_Batchsavefile
 */
class M1_Bridge_Action_Batchsavefile extends M1_Bridge_Action_Savefile
{

    /**
     * @param M1_Bridge $bridge
     */
    public function perform(M1_Bridge $bridge)
    {
        $result = array();

        foreach ($_POST['files'] as $fileInfo) {
            $result[$fileInfo['id']] = $this->_saveFile(
                $fileInfo['source'],
                $fileInfo['target'],
                (int)$fileInfo['width'],
                (int)$fileInfo['height'],
                $fileInfo['local_source']
            );
        }

        echo serialize($result);
    }

}

/**
 * Class M1_Bridge_Action_Deleteimages
 */
class M1_Bridge_Action_Deleteimages
{

    /**
     * @param M1_Bridge $bridge
     */
    public function perform(M1_Bridge $bridge)
    {
        switch ($bridge->config->cartType) {
            case "Pinnacle361":
                $this->_pinnacleDeleteImages($bridge);
                break;
            case "Prestashop11":
                $this->_prestaShopDeleteImages($bridge);
                break;
            case 'Summercart3' :
                $this->_summercartDeleteImages($bridge);
                break;
        }
    }

    /**
     * @param $bridge
     */
    private function _pinnacleDeleteImages(M1_Bridge $bridge)
    {
        $dirs = array(
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'catalog/',
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'manufacturers/',
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'products/',
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'products/thumbs/',
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'products/secondary/',
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'products/preview/',
        );

        $ok = true;

        foreach ($dirs as $dir) {

            if (!file_exists($dir)) {
                continue;
            }

            $dirHandle = opendir($dir);

            while (false !== ($file = readdir($dirHandle))) {
                if ($file != "." && $file != ".." && !preg_match("/^readme\.txt?$/", $file) && !preg_match("/\.bak$/i",
                        $file)
                ) {
                    $file_path = $dir . $file;
                    if (is_file($file_path)) {
                        if (!rename($file_path, $file_path . ".bak")) {
                            $ok = false;
                        }
                    }
                }
            }

            closedir($dirHandle);

        }

        if ($ok) {
            print "OK";
        } else {
            print "ERROR";
        }
    }

    /**
     * @param $bridge
     */
    private function _prestaShopDeleteImages(M1_Bridge $bridge)
    {
        $dirs = array(
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'c/',
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'p/',
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'm/',
        );

        $ok = true;

        foreach ($dirs as $dir) {

            if (!file_exists($dir)) {
                continue;
            }

            $dirHandle = opendir($dir);

            while (false !== ($file = readdir($dirHandle))) {
                if ($file != "." && $file != ".." && preg_match("/(\d+).*\.jpg?$/", $file)) {
                    $file_path = $dir . $file;
                    if (is_file($file_path)) {
                        if (!rename($file_path, $file_path . ".bak")) {
                            $ok = false;
                        }
                    }
                }
            }

            closedir($dirHandle);

        }

        if ($ok) {
            print "OK";
        } else {
            print "ERROR";
        }
    }

    /**
     * @param $bridge
     */
    private function _summercartDeleteImages(M1_Bridge $bridge)
    {
        $dirs = array(
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'categoryimages/',
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'manufacturer/',
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'productimages/',
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'productthumbs/',
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'productboximages/',
            M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'productlargeimages/',
        );

        $ok = true;

        foreach ($dirs as $dir) {

            if (!file_exists($dir)) {
                continue;
            }

            $dirHandle = opendir($dir);

            while (false !== ($file = readdir($dirHandle))) {
                if (($file != ".") && ($file != "..") && !preg_match("/\.bak$/i", $file)) {
                    $file_path = $dir . $file;
                    if (is_file($file_path)) {
                        if (!rename($file_path, $file_path . ".bak")) {
                            $ok = false;
                        }
                    }
                }
            }

            closedir($dirHandle);

        }

        if ($ok) {
            print "OK";
        } else {
            print "ERROR";
        }
    }
}

/**
 * Class M1_Bridge_Action_Cubecart
 */
class M1_Bridge_Action_Cubecart
{

    /**
     * @param M1_Bridge $bridge
     */
    public function perform(M1_Bridge $bridge)
    {
        $dirHandle = opendir(M1_STORE_BASE_DIR . 'language/');

        $languages = array();

        while ($dirEntry = readdir($dirHandle)) {
            if (!is_dir(M1_STORE_BASE_DIR . 'language/' . $dirEntry) || $dirEntry == '.'
                || $dirEntry == '..' || strpos($dirEntry, "_") !== false
            ) {
                continue;
            }

            $lang['id'] = $dirEntry;
            $lang['iso2'] = $dirEntry;

            $cnfile = "config.inc.php";

            if (!file_exists(M1_STORE_BASE_DIR . 'language/' . $dirEntry . '/' . $cnfile)) {
                $cnfile = "config.php";
            }

            if (!file_exists(M1_STORE_BASE_DIR . 'language/' . $dirEntry . '/' . $cnfile)) {
                continue;
            }

            $str = file_get_contents(M1_STORE_BASE_DIR . 'language/' . $dirEntry . '/' . $cnfile);
            preg_match("/" . preg_quote('$langName') . "[\s]*=[\s]*[\"\'](.*)[\"\'];/", $str, $match);

            if (isset($match[1])) {
                $lang['name'] = $match[1];
                $languages[] = $lang;
            }
        }

        echo serialize($languages);
    }
}

/**
 * Class M1_Bridge_Action_Mysqlver
 */
class M1_Bridge_Action_Mysqlver
{

    /**
     * @param $bridge
     */
    public function perform(M1_Bridge $bridge)
    {
        $message = array();
        preg_match('/^(\d+)\.(\d+)\.(\d+)/', mysql_get_server_info($bridge->getLink()), $message);
        echo sprintf("%d%02d%02d", $message[1], $message[2], $message[3]);
    }
}

/**
 * Class M1_Bridge_Action_Clearcache
 */
class M1_Bridge_Action_Clearcache
{

    /**
     * @param M1_Bridge $bridge
     */
    public function perform(M1_Bridge $bridge)
    {
        switch ($bridge->config->cartType) {
            case "Cubecart":
                $this->_CubecartClearCache();
                break;
            case "Prestashop11":
                $this->_PrestashopClearCache();
                break;
            case "Interspire":
                $this->_InterspireClearCache();
                break;
            case "Opencart14" :
                $this->_OpencartClearCache();
                break;
            case "XtcommerceVeyton" :
                $this->_Xtcommerce4ClearCache();
                break;
            case "Ubercart" :
                $this->_ubercartClearCache();
                break;
            case "Tomatocart" :
                $this->_tomatocartClearCache();
                break;
            case "Virtuemart113" :
                $this->_virtuemartClearCache();
                break;
            case "Magento1212" :
                //$this->_magentoClearCache();
                break;
            case "Oscommerce3":
                $this->_Oscommerce3ClearCache();
                break;
            case "Oxid":
                $this->_OxidClearCache();
                break;
            case "XCart":
                $this->_XcartClearCache();
                break;
            case "Cscart203":
                $this->_CscartClearCache();
                break;
            case "Prestashop15":
                $this->_Prestashop15ClearCache();
                break;
            case "Gambio":
                $this->_GambioClearCache();
                break;
        }
    }

    /**
     * @param array $dirs
     * @param string $fileExclude - name file in format pregmatch
     * @return bool
     */
    private function _removeGarbage($dirs = array(), $fileExclude = '')
    {
        $result = true;

        foreach ($dirs as $dir) {

            if (!file_exists($dir)) {
                continue;
            }

            $dirHandle = opendir($dir);

            while (false !== ($file = readdir($dirHandle))) {
                if ($file == "." || $file == "..") {
                    continue;
                }

                if ((trim($fileExclude) != '') && preg_match("/^" . $fileExclude . "?$/", $file)) {
                    continue;
                }

                if (is_dir($dir . $file)) {
                    continue;
                }

                if (!unlink($dir . $file)) {
                    $result = false;
                }
            }

            closedir($dirHandle);
        }

        if ($result) {
            echo 'OK';
        } else {
            echo 'ERROR';
        }

        return $result;
    }

    private function _magentoClearCache()
    {
        chdir('../');

        $indexes = array(
            'catalog_product_attribute',
            'catalog_product_price',
            'catalog_url',
            'catalog_product_flat',
            'catalog_category_flat',
            'catalog_category_product',
            'catalogsearch_fulltext',
            'cataloginventory_stock',
            'tag_summary'
        );

        $phpExecutable = getPHPExecutable();
        if ($phpExecutable) {
            foreach ($indexes as $index) {
                exec($phpExecutable . " shell/indexer.php --reindex $index", $out);
            }
            echo 'OK';
        } else {
            echo 'Error: can not find PHP executable file.';
        }

        echo 'OK';
    }

    private function _InterspireClearCache()
    {
        $res = true;
        $file = M1_STORE_BASE_DIR . 'cache' . DIRECTORY_SEPARATOR . 'datastore' . DIRECTORY_SEPARATOR . 'RootCategories.php';
        if (file_exists($file)) {
            if (!unlink($file)) {
                $res = false;
            }
        }
        if ($res === true) {
            echo "OK";
        } else {
            echo "ERROR";
        }
    }

    private function _CubecartClearCache()
    {
        $ok = true;

        if (file_exists(M1_STORE_BASE_DIR . 'cache')) {
            $dirHandle = opendir(M1_STORE_BASE_DIR . 'cache/');

            while (false !== ($file = readdir($dirHandle))) {
                if ($file != "." && $file != ".." && !preg_match("/^index\.html?$/",
                        $file) && !preg_match("/^\.htaccess?$/", $file)
                ) {
                    if (is_file(M1_STORE_BASE_DIR . 'cache/' . $file)) {
                        if (!unlink(M1_STORE_BASE_DIR . 'cache/' . $file)) {
                            $ok = false;
                        }
                    }
                }
            }

            closedir($dirHandle);
        }

        if (file_exists(M1_STORE_BASE_DIR . 'includes/extra/admin_cat_cache.txt')) {
            unlink(M1_STORE_BASE_DIR . 'includes/extra/admin_cat_cache.txt');
        }

        if ($ok) {
            echo 'OK';
        } else {
            echo 'ERROR';
        }
    }

    private function _PrestashopClearCache()
    {
        $dirs = array(
            M1_STORE_BASE_DIR . 'tools/smarty/compile/',
            M1_STORE_BASE_DIR . 'tools/smarty/cache/',
            M1_STORE_BASE_DIR . 'img/tmp/'
        );

        $this->_removeGarbage($dirs, 'index\.php');
    }

    private function _OpencartClearCache()
    {
        $dirs = array(
            M1_STORE_BASE_DIR . 'system/cache/',
        );

        $this->_removeGarbage($dirs, 'index\.html');
    }

    private function _Xtcommerce4ClearCache()
    {
        $dirs = array(
            M1_STORE_BASE_DIR . 'cache/',
        );

        $this->_removeGarbage($dirs, 'index\.html');
    }

    private function _ubercartClearCache()
    {
        $dirs = array(
            M1_STORE_BASE_DIR . 'sites/default/files/imagecache/product/',
            M1_STORE_BASE_DIR . 'sites/default/files/imagecache/product_full/',
            M1_STORE_BASE_DIR . 'sites/default/files/imagecache/product_list/',
            M1_STORE_BASE_DIR . 'sites/default/files/imagecache/uc_category/',
            M1_STORE_BASE_DIR . 'sites/default/files/imagecache/uc_thumbnail/',
        );

        $this->_removeGarbage($dirs);
    }

    private function _tomatocartClearCache()
    {
        $dirs = array(
            M1_STORE_BASE_DIR . 'includes/work/',
        );

        $this->_removeGarbage($dirs, '\.htaccess');
    }

    /**
     * Try chage permissions actually :)
     */
    private function _virtuemartClearCache()
    {
        $pathToImages = 'components/com_virtuemart/shop_image';

        $dirParts = explode("/", $pathToImages);
        $path = M1_STORE_BASE_DIR;
        foreach ($dirParts as $item) {
            if ($item == '') {
                continue;
            }

            $path .= $item . DIRECTORY_SEPARATOR;
            @chmod($path, 0755);
        }
    }

    private function _Oscommerce3ClearCache()
    {
        $dirs = array(
            M1_STORE_BASE_DIR . 'osCommerce/OM/Work/Cache/',
        );

        $this->_removeGarbage($dirs, '\.htaccess');
    }

    private function _GambioClearCache()
    {
        $dirs = array(
            M1_STORE_BASE_DIR . 'cache/',
        );

        $this->_removeGarbage($dirs, 'index\.html');
    }

    private function _OxidClearCache()
    {
        $dirs = array(
            M1_STORE_BASE_DIR . 'tmp/',
        );

        $this->_removeGarbage($dirs, '\.htaccess');
    }

    private function _XcartClearCache()
    {
        $dirs = array(
            M1_STORE_BASE_DIR . 'var/cache/',
        );

        $this->_removeGarbage($dirs, '\.htaccess');
    }

    private function _CscartClearCache()
    {
        $dir = M1_STORE_BASE_DIR . 'var/cache/';
        $res = $this->removeDirRec($dir);

        if ($res) {
            echo 'OK';
        } else {
            echo 'ERROR';
        }
    }

    private function _Prestashop15ClearCache()
    {
        $dirs = array(
            M1_STORE_BASE_DIR . 'cache/smarty/compile/',
            M1_STORE_BASE_DIR . 'cache/smarty/cache/',
            M1_STORE_BASE_DIR . 'img/tmp/'
        );

        $this->_removeGarbage($dirs, 'index\.php');
    }

    /**
     * @param $dir
     * @return bool
     */
    private function removeDirRec($dir)
    {
        $result = true;

        if ($objs = glob($dir . "/*")) {
            foreach ($objs as $obj) {
                if (is_dir($obj)) {
                    //print "IS DIR! START RECURSIVE FUNCTION.\n";
                    $this->removeDirRec($obj);
                } else {
                    if (!unlink($obj)) {
                        //print "!UNLINK FILE: ".$obj."\n";
                        $result = false;
                    }
                }
            }
        }
        if (!rmdir($dir)) {
            //print "ERROR REMOVE DIR: ".$dir."\n";
            $result = false;
        }

        return $result;
    }
}

class M1_Bridge_Action_Multiquery
{

    protected $_lastInsertIds = array();
    protected $_result = false;

    /**
     * @param M1_Bridge $bridge
     * @return bool|null
     */
    public function perform(M1_Bridge $bridge)
    {
        if (isset($_POST['queries']) && isset($_POST['fetchMode'])) {

            $queries = unserialize(base64_decode($_POST['queries']));
            $result = false;
            $count = 0;

            foreach ($queries as $queryId => $query) {

                if ($count++ > 0) {
                    $query = preg_replace_callback('/_A2C_LAST_\{([a-zA-Z0-9_\-]{1,32})\}_INSERT_ID_/',
                        array($this, '_replace'), $query);
                    $query = preg_replace_callback('/A2C_USE_FIELD_\{([\w\d\s\-]+)\}_FROM_\{([a-zA-Z0-9_\-]{1,32})\}_QUERY/',
                        array($this, '_replaceWithValues'), $query);
                }

                $res = $bridge->query($query, (int)$_POST['fetchMode']);
                if (is_array($res['result']) || is_bool($res['result'])) {

                    $queryRes = array(
                        'res' => $res['result'],
                        'fetchedFields' => @$res['fetchedFields'],
                        'insertId' => $bridge->getLink()->getLastInsertId(),
                        'affectedRows' => $bridge->getLink()->getAffectedRows(),
                    );

                    $this->_result[$queryId] = $queryRes;
                    $this->_lastInsertIds[$queryId] = $queryRes['insertId'];

                } else {
                    echo base64_encode($res['message']);
                    return false;
                }
            }
            echo base64_encode(serialize($this->_result));
        } else {
            return false;
        }
    }

    protected function _replace($matches)
    {
        return $this->_lastInsertIds[$matches[1]];
    }

    protected function _replaceWithValues($matches)
    {
        $values = array();
        if (isset($this->_result[$matches[2]]['res'])) {
            foreach ($this->_result[$matches[2]]['res'] as $row) {
                $values[] = addslashes($row[$matches[1]]);
            }
        }

        return '"' . implode('","', array_unique($values)) . '"';
    }

}

/**
 * Class M1_Bridge_Action_Basedirfs
 */
class M1_Bridge_Action_Basedirfs
{

    /**
     * @param M1_Bridge $bridge
     */
    public function perform(M1_Bridge $bridge)
    {
        echo M1_STORE_BASE_DIR;
    }
}

/**
 * Class M1_Bridge_Action_Phpinfo
 */
class M1_Bridge_Action_Phpinfo
{

    /**
     * @param M1_Bridge $bridge
     */
    public function perform(M1_Bridge $bridge)
    {
        phpinfo();
    }
}


/**
 * Class M1_Bridge_Action_Savefile
 */
class M1_Bridge_Action_Savefile
{
    protected $_imageType = null;

    /**
     * @param $bridge
     */
    public function perform(M1_Bridge $bridge)
    {
        $source = $_POST['src'];
        $destination = $_POST['dst'];
        $width = (int)$_POST['width'];
        $height = (int)$_POST['height'];
        $local = $_POST['local_source'];

        echo $this->_saveFile($source, $destination, $width, $height, $local);
    }

    /**
     * @param $source
     * @param $destination
     * @param $width
     * @param $height
     * @param string $local
     * @return string
     */
    public function _saveFile($source, $destination, $width, $height, $local = '')
    {
        if (trim($local) != '') {

            if ($this->_copyLocal($local, $destination, $width, $height)) {
                return "OK";
            }

        }

        if (!preg_match('/^https?:\/\//i', $source)) {
            $result = $this->_createFile($source, $destination);
        } elseif ($this->_isSameHost($source)) {
            $result = $this->_saveFileLocal($source, $destination);
        } else {
            $result = $this->_saveFileCurl($source, $destination);
        }

        if ($result != "OK") {
            return $result;
        }

        $destination = M1_STORE_BASE_DIR . $destination;

        if ($width != 0 && $height != 0) {
            $this->_scaled2($destination, $width, $height);
        }

        if ($this->cartType == "Prestashop11") {
            // convert destination.gif(png) to destination.jpg
            $imageGd = $this->_loadImage($destination);

            if ($imageGd === false) {
                return $result;
            }

            if (!$this->_convert($imageGd, $destination, IMAGETYPE_JPEG, 'jpg')) {
                return "CONVERT FAILED";
            }
        }

        return $result;
    }

    /**
     * @param $source
     * @param $destination
     * @param $width
     * @param $height
     * @return bool
     */
    private function _copyLocal($source, $destination, $width, $height)
    {
        $source = M1_STORE_BASE_DIR . $source;
        $destination = M1_STORE_BASE_DIR . $destination;

        if (!@copy($source, $destination)) {
            return false;
        }

        if ($width != 0 && $height != 0) {
            $this->_scaled2($destination, $width, $height);
        }

        return true;
    }

    /**
     * @param $filename
     * @param bool $skipJpg
     * @return bool|resource
     */
    private function _loadImage($filename, $skipJpg = true)
    {
        $imageInfo = @getimagesize($filename);
        if ($imageInfo === false) {
            return false;
        }

        $this->_imageType = $imageInfo[2];

        switch ($this->_imageType) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($filename);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($filename);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($filename);
                break;
            default:
                return false;
        }

        if ($skipJpg && ($this->_imageType == IMAGETYPE_JPEG)) {
            return false;
        }

        return $image;
    }

    /**
     * @param $image
     * @param $filename
     * @param int $imageType
     * @param int $compression
     * @param null $permissions
     * @return bool
     */
    private function _saveImage($image, $filename, $imageType = IMAGETYPE_JPEG, $compression = 85, $permissions = null)
    {
        $result = true;
        if ($imageType == IMAGETYPE_JPEG) {
            $result = imagejpeg($image, $filename, $compression);
        } elseif ($imageType == IMAGETYPE_GIF) {
            $result = imagegif($image, $filename);
        } elseif ($imageType == IMAGETYPE_PNG) {
            $result = imagepng($image, $filename);
        }

        if ($permissions != null) {
            chmod($filename, $permissions);
        }

        imagedestroy($image);

        return $result;
    }

    /**
     * @param $source
     * @param $destination
     * @return string
     */
    private function _createFile($source, $destination)
    {
        if ($this->_createDir(dirname($destination)) !== false) {
            $destination = M1_STORE_BASE_DIR . $destination;
            $body = base64_decode($source);
            if ($body === false || file_put_contents($destination, $body) === false) {
                return '[BRIDGE ERROR] File save failed!';
            }

            return 'OK';
        }

        return '[BRIDGE ERROR] Directory creation failed!';
    }

    /**
     * @param $source
     * @param $destination
     * @return string
     */
    private function _saveFileLocal($source, $destination)
    {
        $srcInfo = parse_url($source);
        $src = rtrim($_SERVER['DOCUMENT_ROOT'], "/") . $srcInfo['path'];

        if ($this->_createDir(dirname($destination)) !== false) {
            $dst = M1_STORE_BASE_DIR . $destination;

            if (!@copy($src, $dst)) {
                return $this->_saveFileCurl($source, $destination);
            }

        } else {
            return "[BRIDGE ERROR] Directory creation failed!";
        }

        return "OK";
    }

    /**
     * @param $source
     * @param $destination
     * @return string
     */
    private function _saveFileCurl($source, $destination)
    {
        $source = $this->_escapeSource($source);
        if ($this->_createDir(dirname($destination)) !== false) {
            $destination = M1_STORE_BASE_DIR . $destination;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $source);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_USERAGENT,
                "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            $httpResponseCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($httpResponseCode != 200) {
                curl_close($ch);
                return "[BRIDGE ERROR] Bad response received from source, HTTP code $httpResponseCode!";
            }

            $dst = @fopen($destination, "wb");
            if ($dst === false) {
                return "[BRIDGE ERROR] Can't create  $destination!";
            }
            curl_setopt($ch, CURLOPT_NOBODY, false);
            curl_setopt($ch, CURLOPT_FILE, $dst);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_exec($ch);
            if (($error_no = curl_errno($ch)) != CURLE_OK) {
                return "[BRIDGE ERROR] $error_no: " . curl_error($ch);
            }
            curl_close($ch);
            @chmod($destination, 0755);

            return "OK";

        } else {
            return "[BRIDGE ERROR] Directory creation failed!";
        }
    }

    /**
     * @param $source
     * @return mixed
     */
    private function _escapeSource($source)
    {
        return str_replace(" ", "%20", $source);
    }

    /**
     * @param $dir
     * @return bool
     */
    private function _createDir($dir)
    {
        $dirParts = explode("/", $dir);
        $path = M1_STORE_BASE_DIR;
        foreach ($dirParts as $item) {
            if ($item == '') {
                continue;
            }
            $path .= $item . DIRECTORY_SEPARATOR;
            if (!is_dir($path)) {
                $res = @mkdir($path);
                if (!$res) {
                    return false;
                }
            }
            @chmod($path, 0777);
        }
        return true;
    }

    /**
     * @param $source
     * @return bool
     */
    private function _isSameHost($source)
    {
        $srcInfo = parse_url($source);

        if (preg_match('/\.php$/', $srcInfo['path'])) {
            return false;
        }

        $hostInfo = parse_url("http://" . $_SERVER['HTTP_HOST']);
        if (@$srcInfo['host'] == $hostInfo['host']) {
            return true;
        }

        return false;
    }

    /**
     * @param resource $image - GD image object
     * @param string $filename - store sorce pathfile ex. M1_STORE_BASE_DIR . '/img/c/2.gif';
     * @param int $type - IMAGETYPE_JPEG, IMAGETYPE_GIF or IMAGETYPE_PNG
     * @param string $extension - file extension, this use for jpg or jpeg extension in prestashop
     *
     * @return true if success or false if no
     */
    private function _convert($image, $filename, $type = IMAGETYPE_JPEG, $extension = '')
    {
        $end = pathinfo($filename, PATHINFO_EXTENSION);

        if ($extension == '') {
            $extension = image_type_to_extension($type, false);
        }

        if ($end == $extension) {
            return true;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $newImage = imagecreatetruecolor($width, $height);

        /* Allow to keep nice look even if resized */
        $white = imagecolorallocate($newImage, 255, 255, 255);
        imagefill($newImage, 0, 0, $white);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $width, $height, $width, $height);
        imagecolortransparent($newImage, $white);

        $pathSave = rtrim($filename, $end);

        $pathSave .= $extension;

        return $this->_saveImage($newImage, $pathSave, $type);
    }

    /**
     * @param $destination
     * @param $width
     * @param $height
     * @return string|void
     */
    private function _scaled($destination, $width, $height)
    {
        $image = $this->_loadImage($destination, false);

        if ($image === false) {
            return;
        }

        $originWidth = imagesx($image);
        $originHeight = imagesy($image);

        $rw = (int)$height * (int)$originWidth / (int)$originHeight;
        $useHeight = ($rw <= $width);

        if ($useHeight) {
            $width = (int)$rw;
        } else {
            $height = (int)((int)($width) * (int)($originHeight) / (int)($originWidth));
        }

        $newImage = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($newImage, 255, 255, 255);
        imagefill($newImage, 0, 0, $white);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $width, $height, $originWidth, $originHeight);
        imagecolortransparent($newImage, $white);

        return $this->_saveImage($newImage, $destination, $this->_imageType, 100) ? "OK" : "CAN'T SCALE IMAGE";
    }

    /**
     * scaled2 method optimizet for prestashop
     *
     * @param $destination
     * @param $destWidth
     * @param $destHeight
     * @return string
     */
    private function _scaled2($destination, $destWidth, $destHeight)
    {
        $method = 0;

        $sourceImage = $this->_loadImage($destination, false);

        if ($sourceImage === false) {
            return "IMAGE NOT SUPPORTED";
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        $widthDiff = $destWidth / $sourceWidth;
        $heightDiff = $destHeight / $sourceHeight;

        if ($widthDiff > 1 && $heightDiff > 1) {
            $nextWidth = $sourceWidth;
            $nextHeight = $sourceHeight;
        } else {
            if (intval($method) == 2 || (intval($method) == 0 AND $widthDiff > $heightDiff)) {
                $nextHeight = $destHeight;
                $nextWidth = intval(($sourceWidth * $nextHeight) / $sourceHeight);
                $destWidth = ((intval($method) == 0) ? $destWidth : $nextWidth);
            } else {
                $nextWidth = $destWidth;
                $nextHeight = intval($sourceHeight * $destWidth / $sourceWidth);
                $destHeight = (intval($method) == 0 ? $destHeight : $nextHeight);
            }
        }

        $borderWidth = intval(($destWidth - $nextWidth) / 2);
        $borderHeight = intval(($destHeight - $nextHeight) / 2);

        $destImage = imagecreatetruecolor($destWidth, $destHeight);

        $white = imagecolorallocate($destImage, 255, 255, 255);
        imagefill($destImage, 0, 0, $white);

        imagecopyresampled($destImage, $sourceImage, $borderWidth, $borderHeight, 0, 0, $nextWidth, $nextHeight,
            $sourceWidth, $sourceHeight);
        imagecolortransparent($destImage, $white);

        return $this->_saveImage($destImage, $destination, $this->_imageType, 100) ? "OK" : "CAN'T SCALE IMAGE";
    }
}

class M1_Mysqli
{
    public $config = null; // config adapter
    public $result = array();
    public $dataBaseHandle = null;

    /**
     * mysql constructor
     *
     * @param M1_Config_Adapter $config
     * @return M1_Mysql
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @return bool|null|resource
     */
    private function getDataBaseHandle()
    {
        if ($this->dataBaseHandle) {
            return $this->dataBaseHandle;
        }

        $this->dataBaseHandle = $this->connect();

        if (!$this->dataBaseHandle) {
            exit('[ERROR] MySQLi Query Error: Can not connect to DB');
        }

        return $this->dataBaseHandle;
    }

    /**
     * @return bool|null|resource
     */
    private function connect()
    {
        $triesCount = 5;
        $link = null;
        $password = stripslashes($this->config->password);

        while (!$link) {
            if (!$triesCount--) {
                break;
            }

            $link = @mysqli_connect(
                $this->config->host,
                $this->config->username,
                $password,
                $this->config->dbname,
                $this->config->port ? $this->config->port : null,
                $this->config->sock
            );

            if (!$link) {
                sleep(2);
            }
        }

        if ($link) {
            mysqli_select_db($link, $this->config->dbname);
        } else {
            return false;
        }

        return $link;
    }

    /**
     * @param $sql
     *
     * @return array|bool|mysqli_result
     */
    public function localQuery($sql)
    {
        $result = array();
        $dataBaseHandle = $this->getDataBaseHandle();

        $sth = mysqli_query($dataBaseHandle, $sql);

        if (is_bool($sth)) {
            return $sth;
        }

        while (($row = mysqli_fetch_assoc($sth))) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param $sql
     * @param $fetchType
     *
     * @return array
     */
    public function query($sql, $fetchType)
    {
        $result = array(
            'result' => null,
            'message' => '',
            'fetchedFields' => ''
        );

        $dataBaseHandle = $this->getDataBaseHandle();

        if (!$dataBaseHandle) {
            $result['message'] = '[ERROR] MySQLi Query Error: Can not connect to DB';
            return $result;
        }

        if (isset($_GET['disable_checks'])) {
            $this->localQuery('SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0');
            $this->localQuery("SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
        }

        if (isset($_REQUEST['set_names'])) {
            mysqli_set_charset($dataBaseHandle, $_REQUEST['set_names']);
        }

        $fetchMode = MYSQLI_ASSOC;
        switch ($fetchType) {
            case 3:
                $fetchMode = MYSQLI_BOTH;
                break;
            case 2:
                $fetchMode = MYSQLI_NUM;
                break;
            case 1:
                $fetchMode = MYSQLI_ASSOC;
                break;
            default:
                break;
        }

        $res = mysqli_query($dataBaseHandle, $sql);

        $triesCount = 10;
        while (mysqli_errno($dataBaseHandle) == 2013) {
            if (!$triesCount--) {
                break;
            }
            // reconnect
            $dataBaseHandle = $this->getDataBaseHandle();
            if ($dataBaseHandle) {

                if (isset($_REQUEST['set_names'])) {
                    mysqli_set_charset($dataBaseHandle, $_REQUEST['set_names']);
                }

                // execute query once again
                $res = mysqli_query($dataBaseHandle, $sql);
            }
        }

        if (($errno = mysqli_errno($dataBaseHandle)) != 0) {
            $result['message'] = '[ERROR] MySQLi Query Error: ' . $errno . ', ' . mysqli_error($dataBaseHandle);
            return $result;
        }

        if (is_bool($res)) {
            $result['result'] = $res;
            return $result;
        }

        $fetchedFields = array();
        while ($field = mysqli_fetch_field($res)) {
            $fetchedFields[] = $field;
        }

        $rows = array();
        while ($row = mysqli_fetch_array($res, $fetchMode)) {
            $rows[] = $row;
        }

        if (isset($_GET['disable_checks'])) {
            $this->localQuery("SET SQL_MODE=IFNULL(@OLD_SQL_MODE,'')");
            $this->localQuery("SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS,0)");
        }

        $result['result'] = $rows;
        $result['fetchedFields'] = $fetchedFields;

        mysqli_free_result($res);

        return $result;
    }

    /**
     * @return int
     */
    public function getLastInsertId()
    {
        return mysqli_insert_id($this->dataBaseHandle);
    }

    /**
     * @return int
     */
    public function getAffectedRows()
    {
        return mysqli_affected_rows($this->dataBaseHandle);
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        if ($this->dataBaseHandle) {
            mysqli_close($this->dataBaseHandle);
        }

        $this->dataBaseHandle = null;
    }

}


/**
 * Class M1_Config_Adapter_Ubercart
 */
class M1_Config_Adapter_Ubercart extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Ubercart constructor.
     */
    public function __construct()
    {
        @include_once M1_STORE_BASE_DIR . "sites/default/settings.php";

        $url = parse_url($db_url);

        $url['user'] = urldecode($url['user']);
        // Test if database url has a password.
        $url['pass'] = isset($url['pass']) ? urldecode($url['pass']) : '';
        $url['host'] = urldecode($url['host']);
        $url['path'] = urldecode($url['path']);
        // Allow for non-standard MySQL port.
        if (isset($url['port'])) {
            $url['host'] = $url['host'] . ':' . $url['port'];
        }

        $this->setHostPort($url['host']);
        $this->dbname = ltrim($url['path'], '/');
        $this->username = $url['user'];
        $this->password = $url['pass'];

        $this->imagesDir = "/sites/default/files/";
        if (!file_exists(M1_STORE_BASE_DIR . $this->imagesDir)) {
            $this->imagesDir = "/files";
        }

        if (file_exists(M1_STORE_BASE_DIR . "/modules/ubercart/uc_cart/uc_cart.info")) {
            $str = file_get_contents(M1_STORE_BASE_DIR . "/modules/ubercart/uc_cart/uc_cart.info");
            if (preg_match('/version\s+=\s+".+-(.+)"/', $str, $match) != 0) {
                $this->cartVars['dbVersion'] = $match[1];
                unset($match);
            }
        }

        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;
    }

}


/**
 * Class M1_Config_Adapter_Cubecart3
 */
class M1_Config_Adapter_Cubecart3 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Cubecart3 constructor.
     */
    public function __construct()
    {
        include_once(M1_STORE_BASE_DIR . 'includes/global.inc.php');

        $this->setHostPort($glob['dbhost']);
        $this->dbname = $glob['dbdatabase'];
        $this->username = $glob['dbusername'];
        $this->password = $glob['dbpassword'];

        $this->imagesDir = 'images/uploads';
        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;
    }
}

/**
 * Class M1_Config_Adapter_JooCart
 */
class M1_Config_Adapter_JooCart extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_JooCart constructor.
     */
    public function __construct()
    {
        require_once M1_STORE_BASE_DIR . "/configuration.php";

        if (class_exists("JConfig")) {

            $jconfig = new JConfig();

            $this->setHostPort($jconfig->host);
            $this->dbname = $jconfig->db;
            $this->username = $jconfig->user;
            $this->password = $jconfig->password;

        } else {

            $this->setHostPort($mosConfig_host);
            $this->dbname = $mosConfig_db;
            $this->username = $mosConfig_user;
            $this->password = $mosConfig_password;
        }

        $this->imagesDir = "components/com_opencart/image/";
        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;
    }

}


/**
 * Class M1_Config_Adapter_Prestashop11
 */
class M1_Config_Adapter_Prestashop11 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Prestashop11 constructor.
     */
    public function __construct()
    {
        $confFileOne = file_get_contents(M1_STORE_BASE_DIR . "/config/settings.inc.php");
        $confFileTwo = file_get_contents(M1_STORE_BASE_DIR . "/config/config.inc.php");

        $filesLines = array_merge(explode("\n", $confFileOne), explode("\n", $confFileTwo));

        $execute = '$currentDir = \'\';';

        $isComment = false;
        foreach ($filesLines as $line) {
            $startComment = preg_match("/^(\/\*)/", $line);
            $endComment = preg_match("/(\*\/)$/", $line);

            if ($isComment) {
                if ($endComment) {
                    $isComment = false;
                }
                continue;
            } elseif ($startComment) {
                $isComment = true;
                if ($endComment) {
                    $isComment = false;
                }
                continue;
            }

            if (preg_match("/^(\s*)define\(/i", $line)) {
                if ((strpos($line, '_DB_') !== false) || (strpos($line, '_PS_IMG_DIR_') !== false)
                    || (strpos($line, '_PS_VERSION_') !== false)
                ) {
                    $execute .= " " . $line;
                }
            }
        }

        define('_PS_ROOT_DIR_', M1_STORE_BASE_DIR);
        eval($execute);

        $this->setHostPort(_DB_SERVER_);
        $this->dbname = _DB_NAME_;
        $this->username = _DB_USER_;
        $this->password = _DB_PASSWD_;

        if (defined('_PS_IMG_DIR_') && defined('_PS_ROOT_DIR_')) {

            preg_match("/(\/\w+\/)$/i", _PS_IMG_DIR_, $m);
            $this->imagesDir = $m[1];

        } else {
            $this->imagesDir = "/img/";
        }

        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;

        if (defined('_PS_VERSION_')) {
            $this->cartVars['dbVersion'] = _PS_VERSION_;
        }
    }

}


/**
 * Class M1_Config_Adapter_Ubercart3
 */
class M1_Config_Adapter_Ubercart3 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Ubercart3 constructor.
     */
    public function __construct()
    {
        @include_once M1_STORE_BASE_DIR . "sites/default/settings.php";

        $url = $databases['default']['default'];

        $url['username'] = urldecode($url['username']);
        $url['password'] = isset($url['password']) ? urldecode($url['password']) : '';
        $url['host'] = urldecode($url['host']);
        $url['database'] = urldecode($url['database']);
        if (isset($url['port'])) {
            $url['host'] = $url['host'] . ':' . $url['port'];
        }

        $this->setHostPort($url['host']);
        $this->dbname = ltrim($url['database'], '/');
        $this->username = $url['username'];
        $this->password = $url['password'];

        $this->imagesDir = "/sites/default/files/";
        if (!file_exists(M1_STORE_BASE_DIR . $this->imagesDir)) {
            $this->imagesDir = "/files";
        }

        $fileInfo = M1_STORE_BASE_DIR . "/modules/ubercart/uc_cart/uc_cart.info";
        if (file_exists($fileInfo)) {
            $str = file_get_contents($fileInfo);
            if (preg_match('/version\s+=\s+".+-(.+)"/', $str, $match) != 0) {
                $this->cartVars['dbVersion'] = $match[1];
                unset($match);
            }
        }

        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;
    }

}


/**
 * Class M1_Config_Adapter_XCart
 */
class M1_Config_Adapter_XCart extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_XCart constructor.
     */
    public function __construct()
    {
        define('XCART_START', 1);

        $config = file_get_contents(M1_STORE_BASE_DIR . "config.php");

        try {
            preg_match('/\$sql_host.+\'(.+)\';/', $config, $match);
            $this->setHostPort($match[1]);
            preg_match('/\$sql_user.+\'(.+)\';/', $config, $match);
            $this->username = $match[1];
            preg_match('/\$sql_db.+\'(.+)\';/', $config, $match);
            $this->dbname = $match[1];
            preg_match('/\$sql_password.+\'(.*)\';/', $config, $match);
            $this->password = $match[1];
        } catch (Exception $e) {
            die('ERROR_READING_STORE_CONFIG_FILE');
        }

        $this->imagesDir = 'images/'; // xcart starting from 4.1.x hardcodes images location
        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;

        if (file_exists(M1_STORE_BASE_DIR . "VERSION")) {
            $version = file_get_contents(M1_STORE_BASE_DIR . "VERSION");
            $this->cartVars['dbVersion'] = preg_replace('/(Version| |\\n)/', '', $version);
        }

    }
}

/**
 * Class M1_Config_Adapter_Cubecart
 */
class M1_Config_Adapter_Cubecart extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Cubecart constructor.
     */
    public function __construct()
    {
        include_once(M1_STORE_BASE_DIR . 'includes/global.inc.php');

        $this->setHostPort($glob['dbhost']);
        $this->dbname = $glob['dbdatabase'];
        $this->username = $glob['dbusername'];
        $this->password = $glob['dbpassword'];

        $this->imagesDir = 'images';
        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;
        $dirHandle = opendir(M1_STORE_BASE_DIR . 'language/');
        //settings for cube 5
        $languages = array();
        while ($dirEntry = readdir($dirHandle)) {
            $info = pathinfo($dirEntry);
            $xmlflag = false;

            if (isset($info['extension'])) {
                $xmlflag = strtoupper($info['extension']) != "XML" ? true : false;
            }
            if (is_dir(M1_STORE_BASE_DIR . 'language/' . $dirEntry) || $dirEntry == '.' || $dirEntry == '..' || strpos($dirEntry,
                    "_") !== false || $xmlflag
            ) {
                continue;
            }
            $configXml = simplexml_load_file(M1_STORE_BASE_DIR . 'language/' . $dirEntry);
            if ($configXml->info->title) {
                $lang['name'] = (string)$configXml->info->title;
                $lang['code'] = substr((string)$configXml->info->code, 0, 2);
                $lang['locale'] = substr((string)$configXml->info->code, 0, 2);
                $lang['currency'] = (string)$configXml->info->default_currency;
                $lang['fileName'] = str_replace(".xml", "", $dirEntry);
                $languages[] = $lang;
            }
        }
        if (!empty($languages)) {
            $this->cartVars['languages'] = $languages;
        }

        $conf = false;
        if (file_exists(M1_STORE_BASE_DIR . 'ini.inc.php')) {
            $conf = file_get_contents(M1_STORE_BASE_DIR . 'ini.inc.php');
        } elseif (file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . 'ini.inc.php')) {
            $conf = file_get_contents(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . 'ini.inc.php');
        }

        if ($conf !== false) {
            preg_match('/\$ini\[[\'"]ver[\'"]\]\s*=\s*[\'"](.*?)[\'"]\s*;/', $conf, $match);
            if (isset($match[1]) && !empty($match[1])) {
                $this->cartVars['dbVersion'] = strtolower($match[1]);
            } else {
                preg_match("/define\(['\"]CC_VERSION['\"]\s*,\s*['\"](.*?)['\"]\)/", $conf, $match);
                if (isset($match[1]) && !empty($match[1])) {
                    $this->cartVars['dbVersion'] = strtolower($match[1]);
                }
            }
        }
    }
}

/**
 * Class M1_Config_Adapter_WebAsyst
 */
class M1_Config_Adapter_WebAsyst extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_WebAsyst constructor.
     */
    public function __construct()
    {
        $config = simplexml_load_file(M1_STORE_BASE_DIR . 'kernel/wbs.xml');

        $dbKey = (string)$config->FRONTEND['dbkey'];

        $config = simplexml_load_file(M1_STORE_BASE_DIR . 'dblist' . '/' . strtoupper($dbKey) . '.xml');

        $host = (string)$config->DBSETTINGS['SQLSERVER'];

        $this->setHostPort($host);
        $this->dbname = (string)$config->DBSETTINGS['DB_NAME'];
        $this->username = (string)$config->DBSETTINGS['DB_USER'];
        $this->password = (string)$config->DBSETTINGS['DB_PASSWORD'];

        $this->imagesDir = 'published/publicdata/' . strtoupper($dbKey) . '/attachments/SC/products_pictures';
        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;

        if (isset($config->VERSIONS['SYSTEM'])) {
            $this->cartVars['dbVersion'] = (string)$config->VERSIONS['SYSTEM'];
        }
    }

}

/**
 * Class M1_Config_Adapter_Squirrelcart242
 */
class M1_Config_Adapter_Squirrelcart242 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Squirrelcart242 constructor.
     */
    public function __construct()
    {
        include_once(M1_STORE_BASE_DIR . 'squirrelcart/config.php');

        $this->setHostPort($sql_host);
        $this->dbname = $db;
        $this->username = $sql_username;
        $this->password = $sql_password;

        $this->imagesDir = $img_path;
        $this->categoriesImagesDir = $img_path . "/categories";
        $this->productsImagesDir = $img_path . "/products";
        $this->manufacturersImagesDir = $img_path;

        $version = $this->getCartVersionFromDb("DB_Version", "Store_Information", "record_number = 1");
        if ($version != '') {
            $this->cartVars['dbVersion'] = $version;
        }
    }
}

/**
 * Class M1_Config_Adapter_Opencart14
 */
class M1_Config_Adapter_Opencart14 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Opencart14 constructor.
     */
    public function __construct()
    {
        include_once(M1_STORE_BASE_DIR . "/config.php");

        if (defined('DB_HOST')) {
            $this->setHostPort(DB_HOST);
        } else {
            $this->setHostPort(DB_HOSTNAME);
        }

        if (defined('DB_USER')) {
            $this->username = DB_USER;
        } else {
            $this->username = DB_USERNAME;
        }

        $this->password = DB_PASSWORD;

        if (defined('DB_NAME')) {
            $this->dbname = DB_NAME;
        } else {
            $this->dbname = DB_DATABASE;
        }

        $indexFileContent = '';
        $startupFileContent = '';

        if (file_exists(M1_STORE_BASE_DIR . "/index.php")) {
            $indexFileContent = file_get_contents(M1_STORE_BASE_DIR . "/index.php");
        }

        if (file_exists(M1_STORE_BASE_DIR . "/system/startup.php")) {
            $startupFileContent = file_get_contents(M1_STORE_BASE_DIR . "/system/startup.php");
        }

        if (preg_match("/define\('\VERSION\'\, \'(.+)\'\)/", $indexFileContent, $match) == 0) {
            preg_match("/define\('\VERSION\'\, \'(.+)\'\)/", $startupFileContent, $match);
        }

        if (count($match) > 0) {
            $this->cartVars['dbVersion'] = $match[1];
            unset($match);
        }

        $this->imagesDir = "/image/";
        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;

    }

}


/**
 * Class M1_Config_Adapter_Litecommerce
 */
class M1_Config_Adapter_Litecommerce extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Litecommerce constructor.
     */
    public function __construct()
    {
        if ((file_exists(M1_STORE_BASE_DIR . '/etc/config.php'))) {
            $file = M1_STORE_BASE_DIR . '/etc/config.php';
            $this->imagesDir = "/images";
            $this->categoriesImagesDir = $this->imagesDir . "/category";
            $this->productsImagesDir = $this->imagesDir . "/product";
            $this->manufacturersImagesDir = $this->imagesDir;
        } elseif (file_exists(M1_STORE_BASE_DIR . '/modules/lc_connector/litecommerce/etc/config.php')) {
            $file = M1_STORE_BASE_DIR . '/modules/lc_connector/litecommerce/etc/config.php';
            $this->imagesDir = "/modules/lc_connector/litecommerce/images";
            $this->categoriesImagesDir = $this->imagesDir . "/category";
            $this->productsImagesDir = $this->imagesDir . "/product";
            $this->manufacturersImagesDir = $this->imagesDir;
        }

        $settings = parse_ini_file($file, true);
        $settings = $settings['database_details'];
        $this->host = $settings['hostspec'];
        $this->setHostPort($settings['hostspec']);
        $this->username = $settings['username'];
        $this->password = $settings['password'];
        $this->dbname = $settings['database'];
        $this->tblPrefix = $settings['table_prefix'];

        $version = $this->getCartVersionFromDb("value", "config", "name = 'version'");
        if ($version != '') {
            $this->cartVars['dbVersion'] = $version;
        }
    }

}


/**
 * Class M1_Config_Adapter_Oxid
 */
class M1_Config_Adapter_Oxid extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Oxid constructor.
     */
    public function __construct()
    {
        //@include_once M1_STORE_BASE_DIR . "config.inc.php";
        $config = file_get_contents(M1_STORE_BASE_DIR . "config.inc.php");
        try {
            preg_match("/dbName(.+)?=(.+)?\'(.+)\';/", $config, $match);
            $this->dbname = $match[3];
            preg_match("/dbUser(.+)?=(.+)?\'(.+)\';/", $config, $match);
            $this->username = $match[3];
            preg_match("/dbPwd(.+)?=(.+)?\'(.+)\';/", $config, $match);
            $this->password = isset($match[3]) ? $match[3] : '';
            preg_match("/dbHost(.+)?=(.+)?\'(.*)\';/", $config, $match);
            $this->setHostPort($match[3]);
        } catch (Exception $e) {
            die('ERROR_READING_STORE_CONFIG_FILE');
        }

        //check about last slash
        $this->imagesDir = "out/pictures/";
        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;

        //add key for decoding config values in oxid db
        //check slash
        $keyConfigFile = file_get_contents(M1_STORE_BASE_DIR . '/core/oxconfk.php');
        preg_match("/sConfigKey(.+)?=(.+)?\"(.+)?\";/", $keyConfigFile, $match);
        $this->cartVars['sConfigKey'] = $match[3];
        $version = $this->getCartVersionFromDb("OXVERSION", "oxshops", "OXACTIVE=1 LIMIT 1");
        if ($version != '') {
            $this->cartVars['dbVersion'] = $version;
        }
    }

}


/**
 * Class M1_Config_Adapter_XtcommerceVeyton
 */
class M1_Config_Adapter_XtcommerceVeyton extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_XtcommerceVeyton constructor.
     */
    public function __construct()
    {
        define('_VALID_CALL', 'TRUE');
        define('_SRV_WEBROOT', 'TRUE');
        require_once M1_STORE_BASE_DIR
            . 'conf'
            . DIRECTORY_SEPARATOR
            . 'config.php';

        require_once M1_STORE_BASE_DIR
            . 'conf'
            . DIRECTORY_SEPARATOR
            . 'paths.php';

        $this->setHostPort(_SYSTEM_DATABASE_HOST);
        $this->dbname = _SYSTEM_DATABASE_DATABASE;
        $this->username = _SYSTEM_DATABASE_USER;
        $this->password = _SYSTEM_DATABASE_PWD;
        $this->imagesDir = _SRV_WEB_IMAGES;
        $this->tblPrefix = DB_PREFIX . "_";

        $version = $this->getCartVersionFromDb("config_value", "config", "config_key = '_SYSTEM_VERSION'");
        if ($version != '') {
            $this->cartVars['dbVersion'] = $version;
        }

        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;
    }

}


/**
 * Class M1_Config_Adapter_SSPremium
 */
class M1_Config_Adapter_SSPremium extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_SSPremium constructor.
     */
    public function __construct()
    {
        if (file_exists(M1_STORE_BASE_DIR . 'cfg/connect.inc.php')) {
            $config = file_get_contents(M1_STORE_BASE_DIR . 'cfg/connect.inc.php');
            preg_match("/define\(\'DB_NAME\', \'(.+)\'\);/", $config, $match);
            $this->dbname = $match[1];
            preg_match("/define\(\'DB_USER\', \'(.+)\'\);/", $config, $match);
            $this->username = $match[1];
            preg_match("/define\(\'DB_PASS\', \'(.*)\'\);/", $config, $match);
            $this->password = $match[1];
            preg_match("/define\(\'DB_HOST\', \'(.+)\'\);/", $config, $match);
            $this->setHostPort($match[1]);

            $this->imagesDir = "products_pictures/";
            $this->categoriesImagesDir = $this->imagesDir;
            $this->productsImagesDir = $this->imagesDir;
            $this->manufacturersImagesDir = $this->imagesDir;

            $version = $this->getCartVersionFromDb("value", "SS_system", "varName = 'version_number'");
            if ($version != '') {
                $this->cartVars['dbVersion'] = $version;
            }
        } else {
            $config = include M1_STORE_BASE_DIR . "wa-config/db.php";
            $this->dbname = $config['default']['database'];
            $this->username = $config['default']['user'];
            $this->password = $config['default']['password'];
            $this->setHostPort($config['default']['host']);

            $this->imagesDir = "products_pictures/";
            $this->categoriesImagesDir = $this->imagesDir;
            $this->productsImagesDir = $this->imagesDir;
            $this->manufacturersImagesDir = $this->imagesDir;
            $this->cartVars['dbVersion'] = '5.0';
        }

    }

}

/**
 * Class M1_Config_Adapter_Virtuemart113
 */
class M1_Config_Adapter_Virtuemart113 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Virtuemart113 constructor.
     */
    public function __construct()
    {
        require_once M1_STORE_BASE_DIR . "/configuration.php";

        if (class_exists("JConfig")) {

            $jconfig = new JConfig();

            $this->setHostPort($jconfig->host);
            $this->dbname = $jconfig->db;
            $this->username = $jconfig->user;
            $this->password = $jconfig->password;

        } else {

            $this->setHostPort($mosConfig_host);
            $this->dbname = $mosConfig_db;
            $this->username = $mosConfig_user;
            $this->password = $mosConfig_password;
        }

        if (file_exists(M1_STORE_BASE_DIR . "/administrator/components/com_virtuemart/version.php")) {
            $ver = file_get_contents(M1_STORE_BASE_DIR . "/administrator/components/com_virtuemart/version.php");
            if (preg_match('/\$RELEASE.+\'(.+)\'/', $ver, $match) != 0) {
                $this->cartVars['dbVersion'] = $match[1];
                unset($match);
            }
        }

        $this->imagesDir = "components/com_virtuemart/shop_image";
        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;

        if (is_dir(M1_STORE_BASE_DIR . 'images/stories/virtuemart/product')) {
            $this->imagesDir = 'images/stories/virtuemart';
            $this->productsImagesDir = $this->imagesDir . '/product';
            $this->categoriesImagesDir = $this->imagesDir . '/category';
            $this->manufacturersImagesDir = $this->imagesDir . '/manufacturer';
        }
    }

}


/**
 * Class M1_Config_Adapter_Hhgmultistore
 */
class M1_Config_Adapter_Hhgmultistore extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Hhgmultistore constructor.
     */
    public function __construct()
    {
        define('SITE_PATH', '');
        define('WEB_PATH', '');
        require_once M1_STORE_BASE_DIR . "core/config/configure.php";
        require_once M1_STORE_BASE_DIR . "core/config/paths.php";

        $baseDir = "/store_files/1/";
        $this->imagesDir = $baseDir . DIR_WS_IMAGES;

        $this->categoriesImagesDir = $baseDir . DIR_WS_CATEGORIE_IMAGES;
        $this->productsImagesDirs['info'] = $baseDir . DIR_WS_PRODUCT_INFO_IMAGES;
        $this->productsImagesDirs['org'] = $baseDir . DIR_WS_PRODUCT_ORG_IMAGES;
        $this->productsImagesDirs['thumb'] = $baseDir . DIR_WS_PRODUCT_THUMBNAIL_IMAGES;
        $this->productsImagesDirs['popup'] = $baseDir . DIR_WS_PRODUCT_POPUP_IMAGES;

        $this->manufacturersImagesDirs['img'] = $baseDir . DIR_WS_MANUFACTURERS_IMAGES;
        $this->manufacturersImagesDirs['org'] = $baseDir . DIR_WS_MANUFACTURERS_ORG_IMAGES;

        $this->host = DB_SERVER;
        $this->username = DB_SERVER_USERNAME;
        $this->password = DB_SERVER_PASSWORD;
        $this->dbname = DB_DATABASE;

        if (file_exists(M1_STORE_BASE_DIR . "/core/config/conf.hhg_startup.php")) {
            $ver = file_get_contents(M1_STORE_BASE_DIR . "/core/config/conf.hhg_startup.php");
            if (preg_match('/PROJECT_VERSION.+\((.+)\)\'\)/', $ver, $match) != 0) {
                $this->cartVars['dbVersion'] = $match[1];
                unset($match);
            }
        }
    }

}


/**
 * Class M1_Config_Adapter_Wordpress
 */
class M1_Config_Adapter_Wordpress extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Wordpress constructor.
     */
    public function __construct()
    {
        if (file_exists(M1_STORE_BASE_DIR . 'wp-config.php')) {
            $config = file_get_contents(M1_STORE_BASE_DIR . 'wp-config.php');
        } else {
            $config = file_get_contents(dirname(M1_STORE_BASE_DIR) . '/wp-config.php');
        }

        preg_match('/define\s*\(\s*[\'"]DB_NAME[\'"],\s*[\'"](.+)[\'"]\s*\)\s*;/', $config, $dbnameMatch);
        preg_match('/define\s*\(\s*[\'"]DB_USER[\'"],\s*[\'"](.+)[\'"]\s*\)\s*;/', $config, $usernameMatch);
        preg_match('/define\s*\(\s*[\'"]DB_PASS(WORD)?[\'"],\s*[\'"](.*)[\'"]\s*\)\s*;/', $config, $passwordMatch);
        preg_match('/define\s*\(\s*[\'"]DB_HOST[\'"],\s*[\'"](.+)[\'"]\s*\)\s*;/', $config, $hostMatch);
        preg_match('/\$table_prefix\s*=\s*\'(.*)\'\s*;/', $config, $tblPrefixMatch);
        if (preg_match('/define\s*\(\s*[\'"]UPLOADS[\'"],\s*[\'"](.+)[\'"]\s*\)\s*;/', $config,
                $match) && isset($match[1])
        ) {
            $this->imagesDir = preg_replace('/\'\.\'/', '', $match[1]);
        } else {
            $this->imagesDir = 'wp-content' . DIRECTORY_SEPARATOR . 'uploads';
        }

        if (isset($dbnameMatch[1]) && isset($usernameMatch[1])
            && isset($passwordMatch[2]) && isset($hostMatch[1]) && isset($tblPrefixMatch[1])
        ) {
            $this->dbname = $dbnameMatch[1];
            $this->username = $usernameMatch[1];
            $this->password = $passwordMatch[2];
            $this->setHostPort($hostMatch[1]);
            $this->tblPrefix = $tblPrefixMatch[1];
        } elseif (!$this->_tryLoadConfigs()) {
            die('ERROR_READING_STORE_CONFIG_FILE');
        }


        $cartPlugins = $this->getCartVersionFromDb("option_value", "options",
            "option_name = 'active_plugins'");

        if ($cartPlugins) {
            $cartPlugins = unserialize($cartPlugins);

            foreach ($cartPlugins as $plugin) {
                if (isset($_GET['cart_id'])) {
                    if ($_GET['cart_id'] == 'Woocommerce' && strpos($plugin, 'woocommerce') !== false) {
                        $this->_setWoocommerceData();
                        return;
                    } elseif ($_GET['cart_id'] == 'WPecommerce' && strpos($plugin, 'wp-e-commerce') !== false
                        || $_GET['cart_id'] == 'WPecommerce' && strpos($plugin, 'wp-ecommerce') !== false
                    ) {
                        $this->_setWpecommerceData();
                        return;
                    }
                } else {
                    if (strpos($plugin, 'woocommerce') !== false) {
                        $this->_setWoocommerceData();
                        return;
                    } elseif (strpos($plugin, 'wp-e-commerce') !== false || strpos($plugin, 'wp-ecommerce') !== false) {
                        $this->_setWpecommerceData();
                        return;
                    }
                }
            }
        }
        die ("CART_PLUGIN_IS_NOT_DETECTED");
    }

    protected function _setWoocommerceData()
    {
        $version = $this->getCartVersionFromDb("option_value", "options", "option_name = 'woocommerce_db_version'");

        if ($version != '') {
            $this->cartVars['dbVersion'] = $version;
        }

        $this->cartVars['categoriesDirRelative'] = 'images/categories/';
        $this->cartVars['productsDirRelative'] = 'images/products/';
        $this->imagesDir = "wp-content/uploads/";
    }

    protected function _setWpecommerceData()
    {
        $version = $this->getCartVersionFromDb("option_value", "options", "option_name = 'wpsc_version'");
        if ($version != '') {
            $this->cartVars['dbVersion'] = $version;
        } else {
            $filePath = M1_STORE_BASE_DIR . "wp-content" . DIRECTORY_SEPARATOR . "plugins" . DIRECTORY_SEPARATOR
                . "wp-shopping-cart" . DIRECTORY_SEPARATOR . "wp-shopping-cart.php";
            if (file_exists($filePath)) {
                $conf = file_get_contents($filePath);
                preg_match("/define\('WPSC_VERSION.*/", $conf, $match);
                if (isset($match[0]) && !empty($match[0])) {
                    preg_match("/\d.*/", $match[0], $project);
                    if (isset($project[0]) && !empty($project[0])) {
                        $version = $project[0];
                        $version = str_replace(array(" ", "-", "_", "'", ");", ")", ";"), "", $version);
                        if ($version != '') {
                            $this->cartVars['dbVersion'] = strtolower($version);
                        }
                    }
                }
            }
        }

        if (file_exists(M1_STORE_BASE_DIR . "wp-content/plugins/shopp/Shopp.php")
            || file_exists(M1_STORE_BASE_DIR . "wp-content/plugins/wp-e-commerce/editor.php")
        ) {
            $this->imagesDir = "wp-content/uploads/wpsc/";
            $this->categoriesImagesDir = $this->imagesDir . 'category_images/';
            $this->productsImagesDir = $this->imagesDir . 'product_images/';
            $this->manufacturersImagesDir = $this->imagesDir;
        } elseif (file_exists(M1_STORE_BASE_DIR . "wp-content/plugins/wp-e-commerce/wp-shopping-cart.php")) {
            $this->imagesDir = "wp-content/uploads/";
            $this->categoriesImagesDir = $this->imagesDir . "wpsc/category_images/";
            $this->productsImagesDir = $this->imagesDir;
            $this->manufacturersImagesDir = $this->imagesDir;
        } else {
            $this->imagesDir = "images/";
            $this->categoriesImagesDir = $this->imagesDir;
            $this->productsImagesDir = $this->imagesDir;
            $this->manufacturersImagesDir = $this->imagesDir;
        }
    }

    protected function _tryLoadConfigs()
    {
        try {
            if (file_exists(M1_STORE_BASE_DIR . 'wp-config.php')) {
                require_once(M1_STORE_BASE_DIR . 'wp-config.php');
            } else {
                require_once(dirname(M1_STORE_BASE_DIR) . '/wp-config.php');
            }

            if (defined('DB_NAME') && defined('DB_USER') && defined('DB_HOST')) {
                $this->dbname = DB_NAME;
                $this->username = DB_USER;
                $this->setHostPort(DB_HOST);
            } else {
                return false;
            }

            if (defined('DB_PASSWORD')) {
                $this->password = DB_PASSWORD;
            } elseif (defined('DB_PASS')) {
                $this->password = DB_PASS;
            } else {
                return false;
            }

            if (defined('WP_CONTENT_DIR')) {
                $this->imagesDir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads';
            } elseif (defined('UPLOADS')) {
                $this->imagesDir = UPLOADS;
            } else {
                $this->imagesDir = 'wp-content' . DIRECTORY_SEPARATOR . 'uploads';
            }

            if (isset($table_prefix)) {
                $this->tblPrefix = $table_prefix;
            }
        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}


/**
 * Class M1_Config_Adapter_Magento1212
 */
class M1_Config_Adapter_Magento1212 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Magento1212 constructor.
     */
    public function __construct()
    {
        // MAGENTO 2.X
        if (file_exists(M1_STORE_BASE_DIR . 'app/etc/env.php')) {
            /**
             * @var array
             */
            $config = @include(M1_STORE_BASE_DIR . 'app/etc/env.php');

            $this->cartVars['AdminUrl'] = (string)$config['backend']['frontName'];

            $db = array();
            foreach ($config['db']['connection'] as $connection) {
                if ($connection['active'] == 1) {
                    $db = $connection;
                    break;
                }
            }

            $this->setHostPort((string)$db['host']);
            $this->username = (string)$db['username'];
            $this->dbname = (string)$db['dbname'];
            $this->password = (string)$db['password'];

            if (file_exists(M1_STORE_BASE_DIR . 'composer.json')) {
                $string = file_get_contents(M1_STORE_BASE_DIR . 'composer.json');
                $json = json_decode($string, true);
                $this->cartVars['dbVersion'] = $json['version'];
            } else {
                if (file_exists(M1_STORE_BASE_DIR . 'vendor/magento/framework/AppInterface.php')) {
                    @include M1_STORE_BASE_DIR . 'vendor/magento/framework/AppInterface.php';

                    if (defined('\Magento\Framework\AppInterface::VERSION')) {
                        $this->cartVars['dbVersion'] = \Magento\Framework\AppInterface::VERSION;
                    } else {
                        $this->cartVars['dbVersion'] = '2.0';
                    }
                } else {
                    $this->cartVars['dbVersion'] = '2.0';
                }
            }

            if (isset($db['initStatements']) && $db['initStatements'] != '') {
                $this->cartVars['dbCharSet'] = $db['initStatements'];
            }

            $this->imagesDir = 'pub/media/';
            $this->categoriesImagesDir = $this->imagesDir . 'catalog/category/';
            $this->productsImagesDir = $this->imagesDir . 'catalog/product/';
            $this->manufacturersImagesDir = $this->imagesDir;

            return;
        }

        /**
         * @var SimpleXMLElement
         */
        $config = simplexml_load_file(M1_STORE_BASE_DIR . 'app/etc/local.xml');
        $statuses = simplexml_load_file(M1_STORE_BASE_DIR . 'app/code/core/Mage/Sales/etc/config.xml');

        $version = $statuses->modules->Mage_Sales->version;

        $result = array();

        if (version_compare($version, '1.4.0.25') < 0) {
            $statuses = $statuses->global->sales->order->statuses;
            foreach ($statuses->children() as $status) {
                $result[$status->getName()] = (string)$status->label;
            }
        }

        if (file_exists(M1_STORE_BASE_DIR . "app/Mage.php")) {
            $ver = file_get_contents(M1_STORE_BASE_DIR . "app/Mage.php");
            if (preg_match("/getVersionInfo[^}]+\'major\' *=> *\'(\d+)\'[^}]+\'minor\' *=> *\'(\d+)\'[^}]+\'revision\' *=> *\'(\d+)\'[^}]+\'patch\' *=> *\'(\d+)\'[^}]+}/s",
                    $ver, $match) == 1
            ) {
                $mageVersion = $match[1] . '.' . $match[2] . '.' . $match[3] . '.' . $match[4];
                $this->cartVars['dbVersion'] = $mageVersion;
                unset($match);
            }
        }

        $this->cartVars['orderStatus'] = $result;
        $this->cartVars['AdminUrl'] = (string)$config->admin->routers->adminhtml->args->frontName;

        $this->setHostPort((string)$config->global->resources->default_setup->connection->host);
        $this->username = (string)$config->global->resources->default_setup->connection->username;
        $this->dbname = (string)$config->global->resources->default_setup->connection->dbname;
        $this->password = (string)$config->global->resources->default_setup->connection->password;

        $this->imagesDir = 'media/';
        $this->categoriesImagesDir = $this->imagesDir . "catalog/category/";
        $this->productsImagesDir = $this->imagesDir . "catalog/product/";
        $this->manufacturersImagesDir = $this->imagesDir;
        @unlink(M1_STORE_BASE_DIR . 'app/etc/use_cache.ser');
    }
}

/**
 * Class M1_Config_Adapter_Interspire
 */
class M1_Config_Adapter_Interspire extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Interspire constructor.
     */
    public function __construct()
    {
        require_once M1_STORE_BASE_DIR . "config/config.php";

        $this->setHostPort($GLOBALS['ISC_CFG']["dbServer"]);
        $this->username = $GLOBALS['ISC_CFG']["dbUser"];
        $this->password = $GLOBALS['ISC_CFG']["dbPass"];
        $this->dbname = $GLOBALS['ISC_CFG']["dbDatabase"];

        $this->imagesDir = $GLOBALS['ISC_CFG']["ImageDirectory"];
        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;

        define('DEFAULT_LANGUAGE_ISO2', $GLOBALS['ISC_CFG']["Language"]);

        $version = $this->getCartVersionFromDb("database_version", $GLOBALS['ISC_CFG']["tablePrefix"] . "config", '1');
        if ($version != '') {
            $this->cartVars['dbVersion'] = $version;
        }
    }
}

/**
 * Class M1_Config_Adapter_Pinnacle361
 */
class M1_Config_Adapter_Pinnacle361 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Pinnacle361 constructor.
     */
    public function __construct()
    {
        include_once M1_STORE_BASE_DIR . 'content/engine/engine_config.php';

        $this->imagesDir = 'images/';
        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;

        //$this->Host = DB_HOST;
        $this->setHostPort(DB_HOST);
        $this->dbname = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASSWORD;

        $version = $this->getCartVersionFromDb(
            "value",
            (defined('DB_PREFIX') ? DB_PREFIX : '') . "settings",
            "name = 'AppVer'"
        );
        if ($version != '') {
            $this->cartVars['dbVersion'] = $version;
        }
    }

}


/**
 * Class M1_Config_Adapter_Oscommerce22ms2
 */
class M1_Config_Adapter_Oscommerce22ms2 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Oscommerce22ms2 constructor.
     */
    public function __construct()
    {
        $curDir = getcwd();

        chdir(M1_STORE_BASE_DIR);

        @require_once M1_STORE_BASE_DIR
            . "includes" . DIRECTORY_SEPARATOR
            . "configure.php";

        chdir($curDir);

        $this->imagesDir = DIR_WS_IMAGES;

        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        if (defined('DIR_WS_PRODUCT_IMAGES')) {
            $this->productsImagesDir = DIR_WS_PRODUCT_IMAGES;
        }
        if (defined('DIR_WS_ORIGINAL_IMAGES')) {
            $this->productsImagesDir = DIR_WS_ORIGINAL_IMAGES;
        }
        $this->manufacturersImagesDir = $this->imagesDir;

        //$this->Host      = DB_SERVER;
        $this->setHostPort(DB_SERVER);
        $this->username = DB_SERVER_USERNAME;
        $this->password = DB_SERVER_PASSWORD;
        $this->dbname = DB_DATABASE;
        chdir(M1_STORE_BASE_DIR);
        if (file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . 'application_top.php')) {
            $conf = file_get_contents(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "application_top.php");
            preg_match("/define\('PROJECT_VERSION.*/", $conf, $match);
            if (isset($match[0]) && !empty($match[0])) {
                preg_match("/\d.*/", $match[0], $project);
                if (isset($project[0]) && !empty($project[0])) {
                    $version = $project[0];
                    $version = str_replace(array(" ", "-", "_", "'", ");"), "", $version);
                    if ($version != '') {
                        $this->cartVars['dbVersion'] = strtolower($version);
                    }
                }
            } else {
                //if another oscommerce based cart
                if (file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . 'version.php')) {
                    @require_once M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "version.php";
                    if (defined('PROJECT_VERSION') && PROJECT_VERSION != '') {
                        $version = PROJECT_VERSION;
                        preg_match("/\d.*/", $version, $vers);
                        if (isset($vers[0]) && !empty($vers[0])) {
                            $version = $vers[0];
                            $version = str_replace(array(" ", "-", "_"), "", $version);
                            if ($version != '') {
                                $this->cartVars['dbVersion'] = strtolower($version);
                            }
                        }
                        //if zen_cart
                    } else {
                        if (defined('PROJECT_VERSION_MAJOR') && PROJECT_VERSION_MAJOR != '') {
                            $this->cartVars['dbVersion'] = PROJECT_VERSION_MAJOR;
                        }
                        if (defined('PROJECT_VERSION_MINOR') && PROJECT_VERSION_MINOR != '') {
                            $this->cartVars['dbVersion'] .= '.' . PROJECT_VERSION_MINOR;
                        }
                    }
                }
            }
        }
        chdir($curDir);
    }

}


/**
 * Class M1_Config_Adapter_Tomatocart
 */
class M1_Config_Adapter_Tomatocart extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Tomatocart constructor.
     */
    public function __construct()
    {
        $config = file_get_contents(M1_STORE_BASE_DIR . "includes/configure.php");
        preg_match("/define\(\'DB_DATABASE\', \'(.+)\'\);/", $config, $match);
        $this->dbname = $match[1];
        preg_match("/define\(\'DB_SERVER_USERNAME\', \'(.+)\'\);/", $config, $match);
        $this->username = $match[1];
        preg_match("/define\(\'DB_SERVER_PASSWORD\', \'(.*)\'\);/", $config, $match);
        $this->password = $match[1];
        preg_match("/define\(\'DB_SERVER\', \'(.+)\'\);/", $config, $match);
        $this->setHostPort($match[1]);

        preg_match("/define\(\'DIR_WS_IMAGES\', \'(.+)\'\);/", $config, $match);
        $this->imagesDir = $match[1];

        $this->categoriesImagesDir = $this->imagesDir . 'categories/';
        $this->productsImagesDir = $this->imagesDir . 'products/';
        $this->manufacturersImagesDir = $this->imagesDir . 'manufacturers/';
        if (file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . 'application_top.php')) {
            $conf = file_get_contents(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "application_top.php");
            preg_match("/define\('PROJECT_VERSION.*/", $conf, $match);

            if (isset($match[0]) && !empty($match[0])) {
                preg_match("/\d.*/", $match[0], $project);
                if (isset($project[0]) && !empty($project[0])) {
                    $version = $project[0];
                    $version = str_replace(array(" ", "-", "_", "'", ");"), "", $version);
                    if ($version != '') {
                        $this->cartVars['dbVersion'] = strtolower($version);
                    }
                }
            } else {
                //if another version
                if (file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . 'version.php')) {
                    @require_once M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "version.php";
                    if (defined('PROJECT_VERSION') && PROJECT_VERSION != '') {
                        $version = PROJECT_VERSION;
                        preg_match("/\d.*/", $version, $vers);
                        if (isset($vers[0]) && !empty($vers[0])) {
                            $version = $vers[0];
                            $version = str_replace(array(" ", "-", "_"), "", $version);
                            if ($version != '') {
                                $this->cartVars['dbVersion'] = strtolower($version);
                            }
                        }
                    }
                }
            }
        }
    }

}


/**
 * Class M1_Config_Adapter_Sunshop4
 */
class M1_Config_Adapter_Sunshop4 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Sunshop4 constructor.
     */
    public function __construct()
    {
        @require_once M1_STORE_BASE_DIR
            . "include" . DIRECTORY_SEPARATOR
            . "config.php";

        $this->imagesDir = "images/products/";

        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;

        if (defined('ADMIN_DIR')) {
            $this->cartVars['AdminUrl'] = ADMIN_DIR;
        }

        $this->setHostPort($servername);
        $this->username = $dbusername;
        $this->password = $dbpassword;
        $this->dbname = $dbname;

        if (isset($dbprefix)) {
            $this->tblPrefix = $dbprefix;
        }

        $version = $this->getCartVersionFromDb("value", "settings", "name = 'version'");
        if ($version != '') {
            $this->cartVars['dbVersion'] = $version;
        }
    }

}


/**
 * Class miSettings
 */
class miSettings
{

    protected $_arr;

    /**
     * @return miSettings|null
     */
    public function singleton()
    {
        static $instance = null;
        if ($instance == null) {
            $instance = new miSettings();
        }
        return $instance;
    }

    /**
     * @param $arr
     */
    public function setArray($arr)
    {
        $this->_arr[] = $arr;
    }

    /**
     * @return mixed
     */
    public function getArray()
    {
        return $this->_arr;
    }

}

/**
 * Class M1_Config_Adapter_Summercart3
 */
class M1_Config_Adapter_Summercart3 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Summercart3 constructor.
     */
    public function __construct()
    {
        @include_once M1_STORE_BASE_DIR . "include/miphpf/Config.php";

        $miSettings = new miSettings();
        $instance = $miSettings->singleton();

        $data = $instance->getArray();

        $this->setHostPort($data[0]['MI_DEFAULT_DB_HOST']);
        $this->dbname = $data[0]['MI_DEFAULT_DB_NAME'];
        $this->username = $data[0]['MI_DEFAULT_DB_USER'];
        $this->password = $data[0]['MI_DEFAULT_DB_PASS'];
        $this->imagesDir = "/userfiles/";

        $this->categoriesImagesDir = $this->imagesDir . "categoryimages";
        $this->productsImagesDir = $this->imagesDir . "productimages";
        $this->manufacturersImagesDir = $this->imagesDir . "manufacturer";

        if (file_exists(M1_STORE_BASE_DIR . "/include/VERSION")) {
            $indexFileContent = file_get_contents(M1_STORE_BASE_DIR . "/include/VERSION");
            $this->cartVars['dbVersion'] = trim($indexFileContent);
        }
    }

}


/**
 * Class M1_Config_Adapter_Oscommerce3
 */
class M1_Config_Adapter_Oscommerce3 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Oscommerce3 constructor.
     */
    public function __construct()
    {
        $file = M1_STORE_BASE_DIR . '/osCommerce/OM/Config/settings.ini';
        $settings = parse_ini_file($file);
        $this->imagesDir = "/public/";
        $this->categoriesImagesDir = $this->imagesDir . "/categories";
        $this->productsImagesDir = $this->imagesDir . "/products";
        $this->manufacturersImagesDir = $this->imagesDir;

        $this->host = $settings['db_server'];
        $this->setHostPort($settings['db_server_port']);
        $this->username = $settings['db_server_username'];
        $this->password = $settings['db_server_password'];
        $this->dbname = $settings['db_database'];
    }

}


/**
 * Class M1_Config_Adapter_Prestashop15
 */
class M1_Config_Adapter_Prestashop15 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Prestashop15 constructor.
     */
    public function __construct()
    {
        $confFileOne = file_get_contents(M1_STORE_BASE_DIR . "/config/settings.inc.php");

        if (strpos($confFileOne, '_DB_SERVER_') !== false) {
            $confFileTwo = file_get_contents(M1_STORE_BASE_DIR . "/config/config.inc.php");

            $filesLines = array_merge(explode("\n", $confFileOne), explode("\n", $confFileTwo));

            $execute = '$currentDir = \'\';';
            $constArray = array();
            $isComment = false;
            foreach ($filesLines as $line) {
                $startComment = preg_match("/^(\/\*)/", $line);
                $endComment = preg_match("/(\*\/)$/", $line);

                if ($isComment) {
                    if ($endComment) {
                        $isComment = false;
                    }
                    continue;
                } elseif ($startComment) {
                    $isComment = true;
                    if ($endComment) {
                        $isComment = false;
                    }
                    continue;
                }

                if (preg_match("/^(\s*)define\(/i", $line)) {
                    if ((strpos($line, '_DB_') !== false) || (strpos($line, '_PS_IMG_DIR_') !== false)
                        || (strpos($line, '_PS_VERSION_') !== false)
                    ) {
                        $const = substr($line, strrpos($line, "'_"));
                        $const = substr($const, 0, strrpos($const, ", "));
                        if (!in_array($const, $constArray)) {
                            $execute .= " " . $line;
                            $constArray[] = $const;
                        }
                    }
                }
            }

            define('_PS_ROOT_DIR_', M1_STORE_BASE_DIR);
            eval($execute);
        } else {
            //load configs
            require_once(M1_STORE_BASE_DIR . "config/defines.inc.php");
            require_once(M1_STORE_BASE_DIR . "config/autoload.php");
            require_once(M1_STORE_BASE_DIR . "config/bootstrap.php");
        }

        $this->setHostPort(_DB_SERVER_);
        $this->dbname = _DB_NAME_;
        $this->username = _DB_USER_;
        $this->password = _DB_PASSWD_;

        if (defined('_PS_IMG_DIR_') && defined('_PS_ROOT_DIR_')) {

            preg_match("/(\/\w+\/)$/i", _PS_IMG_DIR_, $m);
            $this->imagesDir = $m[1];

        } else {
            $this->imagesDir = "/img/";
        }

        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;

        if (defined('_PS_VERSION_')) {
            $this->cartVars['dbVersion'] = _PS_VERSION_;
        }
    }

}


/**
 * Class M1_Config_Adapter_Gambio
 */
class M1_Config_Adapter_Gambio extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Gambio constructor.
     */
    public function __construct()
    {
        $curDir = getcwd();

        chdir(M1_STORE_BASE_DIR);

        @require_once M1_STORE_BASE_DIR . "includes/configure.php";

        chdir($curDir);

        $this->imagesDir = DIR_WS_IMAGES;

        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        if (defined('DIR_WS_PRODUCT_IMAGES')) {
            $this->productsImagesDir = DIR_WS_PRODUCT_IMAGES;
        }
        if (defined('DIR_WS_ORIGINAL_IMAGES')) {
            $this->productsImagesDir = DIR_WS_ORIGINAL_IMAGES;
        }
        $this->manufacturersImagesDir = $this->imagesDir;

        $this->host = DB_SERVER;
        //$this->setHostPort(DB_SERVER);
        $this->username = DB_SERVER_USERNAME;
        $this->password = DB_SERVER_PASSWORD;
        $this->dbname = DB_DATABASE;

        chdir(M1_STORE_BASE_DIR);
        if (file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . 'application_top.php')) {
            $conf = file_get_contents(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "application_top.php");
            preg_match("/define\('PROJECT_VERSION.*/", $conf, $match);
            if (isset($match[0]) && !empty($match[0])) {
                preg_match("/\d.*/", $match[0], $project);
                if (isset($project[0]) && !empty($project[0])) {
                    $version = $project[0];
                    $version = str_replace(array(" ", "-", "_", "'", ");"), "", $version);
                    if ($version != '') {
                        $this->cartVars['dbVersion'] = strtolower($version);
                    }
                }
            } else {
                //if another oscommerce based cart
                if (file_exists(M1_STORE_BASE_DIR . DIRECTORY_SEPARATOR . 'version_info.php')) {
                    @require_once M1_STORE_BASE_DIR . DIRECTORY_SEPARATOR . "version_info.php";
                    if (defined('PROJECT_VERSION') && PROJECT_VERSION != '') {
                        $version = PROJECT_VERSION;
                        preg_match("/\d.*/", $version, $vers);
                        if (isset($vers[0]) && !empty($vers[0])) {
                            $version = $vers[0];
                            $version = str_replace(array(" ", "-", "_"), "", $version);
                            if ($version != '') {
                                $this->cartVars['dbVersion'] = strtolower($version);
                            }
                        }
                        //if zen_cart
                    } else {
                        if (defined('PROJECT_VERSION_MAJOR') && PROJECT_VERSION_MAJOR != '') {
                            $this->cartVars['dbVersion'] = PROJECT_VERSION_MAJOR;
                        }
                        if (defined('PROJECT_VERSION_MINOR') && PROJECT_VERSION_MINOR != '') {
                            $this->cartVars['dbVersion'] .= '.' . PROJECT_VERSION_MINOR;
                        }
                    }
                }
            }
        }
        chdir($curDir);
    }

}


/**
 * Class M1_Config_Adapter_Shopware
 */
class M1_Config_Adapter_Shopware extends M1_Config_Adapter
{
    /**
     * M1_Config_Adapter_Shopware constructor.
     */
    public function __construct()
    {
        if (file_exists(M1_STORE_BASE_DIR . 'engine/Shopware/Application.php')) {
            $file = file_get_contents(M1_STORE_BASE_DIR . 'engine/Shopware/Application.php');
            if (preg_match('/const\s+VERSION\s*=\s*[\'"]([0-9.]+)[\'"]/', $file, $matches) && isset($matches[1])) {
                $this->cartVars['dbVersion'] = $matches[1];
            }
        }

        $configs = include(M1_STORE_BASE_DIR . "config.php");
        $this->setHostPort($configs['db']['host']);
        $this->username = $configs['db']['username'];
        $this->password = $configs['db']['password'];
        $this->dbname = $configs['db']['dbname'];
    }
}

/**
 * Class M1_Config_Adapter_AceShop
 */
class M1_Config_Adapter_AceShop extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_AceShop constructor.
     */
    public function __construct()
    {
        require_once M1_STORE_BASE_DIR . "/configuration.php";

        if (class_exists("JConfig")) {

            $jconfig = new JConfig();

            $this->setHostPort($jconfig->host);
            $this->dbname = $jconfig->db;
            $this->username = $jconfig->user;
            $this->password = $jconfig->password;

        } else {

            $this->setHostPort($mosConfig_host);
            $this->dbname = $mosConfig_db;
            $this->username = $mosConfig_user;
            $this->password = $mosConfig_password;
        }

        $this->imagesDir = "components/com_aceshop/opencart/image/";
        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;
    }

}


/**
 * Class M1_Config_Adapter_Cscart203
 */
class M1_Config_Adapter_Cscart203 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Cscart203 constructor.
     */
    public function __construct()
    {
        define("IN_CSCART", 1);
        define("CSCART_DIR", M1_STORE_BASE_DIR);
        define("AREA", 1);
        define("DIR_ROOT", M1_STORE_BASE_DIR);
        define("DIR_CSCART", M1_STORE_BASE_DIR);
        define('DS', DIRECTORY_SEPARATOR);
        define('BOOTSTRAP', '');
        require_once M1_STORE_BASE_DIR . 'config.php';
        defined('DIR_IMAGES') or define('DIR_IMAGES', DIR_ROOT . '/images/');

        //For CS CART 1.3.x
        if (isset($db_host) && isset($db_name) && isset($db_user) && isset($db_password)) {
            $this->setHostPort($db_host);
            $this->dbname = $db_name;
            $this->username = $db_user;
            $this->password = $db_password;
            $this->imagesDir = str_replace(M1_STORE_BASE_DIR, '', IMAGES_STORAGE_DIR);
        } else {

            $this->setHostPort($config['db_host']);
            $this->dbname = $config['db_name'];
            $this->username = $config['db_user'];
            $this->password = $config['db_password'];
            $this->imagesDir = str_replace(M1_STORE_BASE_DIR, '', DIR_IMAGES);
        }

        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;

        if (defined('MAX_FILES_IN_DIR')) {
            $this->cartVars['cs_max_files_in_dir'] = MAX_FILES_IN_DIR;
        }

        if (defined('PRODUCT_VERSION')) {
            $this->cartVars['dbVersion'] = PRODUCT_VERSION;
        }
    }

}


/**
 * Class M1_Config_Adapter_LemonStand
 */
class M1_Config_Adapter_LemonStand extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_LemonStand constructor.
     */
    public function __construct()
    {
        include(M1_STORE_BASE_DIR . 'phproad/system/phpr.php');
        include(M1_STORE_BASE_DIR . 'phproad/modules/phpr/classes/phpr_securityframework.php');

        define('PATH_APP', '');

        if (phpversion() > 5) {
            eval ('Phpr::$config = new MockConfig();	  	  
      Phpr::$config->set("SECURE_CONFIG_PATH", M1_STORE_BASE_DIR . "config/config.dat");
      $framework = Phpr_SecurityFramework::create();');
        }

        $config_content = $framework->get_config_content();

        $this->setHostPort($config_content['mysql_params']['host']);
        $this->dbname = $config_content['mysql_params']['database'];
        $this->username = $config_content['mysql_params']['user'];
        $this->password = $config_content['mysql_params']['password'];

        $this->categoriesImagesDir = '/uploaded/thumbnails/';
        $this->productsImagesDir = '/uploaded/';
        $this->manufacturersImagesDir = '/uploaded/thumbnails/';

        $version = $this->getCartVersionFromDb("version_str", "core_versions", "moduleId = 'shop'");
        $this->cartVars['dbVersion'] = $version;

    }

}

/**
 * Class MockConfig
 */
class MockConfig
{

    protected $_data = array();

    /**
     * @param $key
     * @param $value
     */
    public function set($key, $value)
    {
        $this->_data[$key] = $value;
    }

    /**
     * @param $key
     * @param string $default
     * @return mixed|string
     */
    public function get($key, $default = 'default')
    {
        return isset($this->_data[$key]) ? $this->_data[$key] : $default;
    }
}

/**
 * Class M1_Config_Adapter_DrupalCommerce
 */
class M1_Config_Adapter_DrupalCommerce extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_DrupalCommerce constructor.
     */
    public function __construct()
    {
        @include_once M1_STORE_BASE_DIR . "sites/default/settings.php";

        $url = $databases['default']['default'];

        $url['username'] = urldecode($url['username']);
        $url['password'] = isset($url['password']) ? urldecode($url['password']) : '';
        $url['host'] = urldecode($url['host']);
        $url['database'] = urldecode($url['database']);
        if (isset($url['port'])) {
            $url['host'] = $url['host'] . ':' . $url['port'];
        }

        $this->setHostPort($url['host']);
        $this->dbname = ltrim($url['database'], '/');
        $this->username = $url['username'];
        $this->password = $url['password'];

        $this->imagesDir = "/sites/default/files/";
        if (!file_exists(M1_STORE_BASE_DIR . $this->imagesDir)) {
            $this->imagesDir = "/files";
        }

        $fileInfo = M1_STORE_BASE_DIR . "/sites/all/modules/commerce/commerce.info";
        if (file_exists($fileInfo)) {
            $str = file_get_contents($fileInfo);
            if (preg_match('/version\s+=\s+".+-(.+)"/', $str, $match) != 0) {
                $this->cartVars['dbVersion'] = $match[1];
                unset($match);
            }
        }

        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;
    }
}

/**
 * Class M1_Config_Adapter_SSFree
 */
class M1_Config_Adapter_SSFree extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_SSFree constructor.
     */
    public function __construct()
    {
        $config = file_get_contents(M1_STORE_BASE_DIR . 'cfg/connect.inc.php');
        preg_match("/define\(\'DB_NAME\', \'(.+)\'\);/", $config, $match);
        $this->dbname = $match[1];
        preg_match("/define\(\'DB_USER\', \'(.+)\'\);/", $config, $match);
        $this->username = $match[1];
        preg_match("/define\(\'DB_PASS\', \'(.*)\'\);/", $config, $match);
        $this->password = $match[1];
        preg_match("/define\(\'DB_HOST\', \'(.+)\'\);/", $config, $match);
        $this->setHostPort($match[1]);

        $this->imagesDir = "products_pictures/";
        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        $this->manufacturersImagesDir = $this->imagesDir;

        $generalInc = file_get_contents(M1_STORE_BASE_DIR . 'cfg/general.inc.php');

        preg_match("/define\(\'CONF_CURRENCY_ISO3\', \'(.+)\'\);/", $generalInc, $match);
        if (count($match) != 0) {
            $this->cartVars['iso3Currency'] = $match[1];
        }

        preg_match("/define\(\'CONF_CURRENCY_ID_LEFT\', \'(.+)\'\);/", $generalInc, $match);
        if (count($match) != 0) {
            $this->cartVars['currencySymbolLeft'] = $match[1];
        }

        preg_match("/define\(\'CONF_CURRENCY_ID_RIGHT\', \'(.+)\'\);/", $generalInc, $match);
        if (count($match) != 0) {
            $this->cartVars['currencySymbolRight'] = $match[1];
        }
    }

}

/**
 * Class M1_Config_Adapter_Zencart137
 */
class M1_Config_Adapter_Zencart137 extends M1_Config_Adapter
{

    /**
     * M1_Config_Adapter_Zencart137 constructor.
     */
    public function __construct()
    {
        $curDir = getcwd();

        chdir(M1_STORE_BASE_DIR);

        @require_once M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "configure.php";
        if (file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . 'defined_paths.php')) {
            @require_once M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "defined_paths.php";
        }

        chdir($curDir);

        $this->imagesDir = DIR_WS_IMAGES;

        $this->categoriesImagesDir = $this->imagesDir;
        $this->productsImagesDir = $this->imagesDir;
        if (defined('DIR_WS_PRODUCT_IMAGES')) {
            $this->productsImagesDir = DIR_WS_PRODUCT_IMAGES;
        }
        if (defined('DIR_WS_ORIGINAL_IMAGES')) {
            $this->productsImagesDir = DIR_WS_ORIGINAL_IMAGES;
        }
        $this->manufacturersImagesDir = $this->imagesDir;

        //$this->Host      = DB_SERVER;
        $this->setHostPort(DB_SERVER);
        $this->username = DB_SERVER_USERNAME;
        $this->password = DB_SERVER_PASSWORD;
        $this->dbname = DB_DATABASE;
        if (file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . 'version.php')) {
            @require_once M1_STORE_BASE_DIR
                . "includes" . DIRECTORY_SEPARATOR
                . "version.php";
            $major = PROJECT_VERSION_MAJOR;
            $minor = PROJECT_VERSION_MINOR;
            if (defined('EXPECTED_DATABASE_VERSION_MAJOR') && EXPECTED_DATABASE_VERSION_MAJOR != '') {
                $major = EXPECTED_DATABASE_VERSION_MAJOR;
            }
            if (defined('EXPECTED_DATABASE_VERSION_MINOR') && EXPECTED_DATABASE_VERSION_MINOR != '') {
                $minor = EXPECTED_DATABASE_VERSION_MINOR;
            }

            if ($major != '' && $minor != '') {
                $this->cartVars['dbVersion'] = $major . '.' . $minor;
            }

        }
    }

}


class M1_Config_Adapter
{
    public $host = 'localhost';
    public $port = null;//"3306";
    public $sock = null;
    public $username = 'root';
    public $password = '';
    public $dbname = '';
    public $tblPrefix = '';

    public $cartType = 'Oscommerce22ms2';
    public $imagesDir = '';
    public $categoriesImagesDir = '';
    public $productsImagesDir = '';
    public $manufacturersImagesDir = '';
    public $categoriesImagesDirs = '';
    public $productsImagesDirs = '';
    public $manufacturersImagesDirs = '';

    public $languages = array();
    public $cartVars = array();

    /**
     * @return mixed
     */
    public function create()
    {
        $cartType = $this->_detectCartType();
        $className = "M1_Config_Adapter_" . $cartType;

        $obj = new $className();
        $obj->cartType = $cartType;

        return $obj;
    }

    /**
     * @return string
     */
    private function _detectCartType()
    {
        if (isset($_GET['cart_id'])) {
            $cartId = $_GET['cart_id'];
        } else {
            $cartId = '';
        }
        switch ($cartId) {
            default :
            case 'Prestashop':
                if (file_exists(M1_STORE_BASE_DIR . "config/config.inc.php")) {
                    if (file_exists(M1_STORE_BASE_DIR . "cache/class_index.php")
                        || file_exists(M1_STORE_BASE_DIR . "app/config/parameters.php")
                    ) {
                        return "Prestashop15";
                    }
                    return "Prestashop11";
                }
            case 'Ubercart':
                if (file_exists(M1_STORE_BASE_DIR . 'sites/default/settings.php')) {
                    if (file_exists(
                        M1_STORE_BASE_DIR
                        . '/modules/ubercart/uc_store/includes/coder_review_uc3x.inc'
                    )) {
                        return "Ubercart3";
                    } elseif (file_exists(
                        M1_STORE_BASE_DIR
                        . 'sites/all/modules/commerce/includes/commerce.controller.inc'
                    )) {
                        return "DrupalCommerce";
                    }
                    return "Ubercart";
                }
            case 'Woocommerce':
                if (file_exists(M1_STORE_BASE_DIR . 'wp-config.php')
                    && file_exists(
                        M1_STORE_BASE_DIR . 'wp-content/plugins/woocommerce/woocommerce.php'
                    )
                ) {
                    return 'Wordpress';
                }
            case 'WPecommerce':
                if (file_exists(M1_STORE_BASE_DIR . 'wp-config.php')) {
                    return 'Wordpress';
                }
            case 'Zencart137':
                if (file_exists(
                        M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR
                        . "configure.php"
                    )
                    && file_exists(M1_STORE_BASE_DIR . "ipn_main_handler.php")
                ) {
                    return "Zencart137";
                }
            case 'Oscommerce22ms2':
                if (file_exists(
                        M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR
                        . "configure.php"
                    )
                    && !file_exists(
                        M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR
                        . "toc_constants.php"
                    )
                ) {
                    return "Oscommerce22ms2";
                }
            case 'Gambio':
                if (file_exists(M1_STORE_BASE_DIR . "/includes/configure.php")) {
                    return "Gambio";
                }
            case 'JooCart':
                if (file_exists(
                    M1_STORE_BASE_DIR . '/components/com_opencart/opencart.php'
                )) {
                    return 'JooCart';
                }
            case 'AceShop':
                if (file_exists(
                    M1_STORE_BASE_DIR . '/components/com_aceshop/aceshop.php'
                )) {
                    return 'AceShop';
                }
            case 'Oxid':
                if (file_exists(M1_STORE_BASE_DIR . 'config.inc.php')) {
                    return 'Oxid';
                }
            case 'Virtuemart113':
                if (file_exists(M1_STORE_BASE_DIR . "configuration.php")) {
                    return "Virtuemart113";
                }
            case 'Pinnacle361':
                if (file_exists(
                    M1_STORE_BASE_DIR . 'content/engine/engine_config.php'
                )) {
                    return "Pinnacle361";
                }
            case 'Magento1212':
                if (file_exists(M1_STORE_BASE_DIR . 'app/etc/local.xml')
                    || @file_exists(M1_STORE_BASE_DIR . 'app/etc/env.php')
                ) {
                    return "Magento1212";
                }
            case 'Cubecart':
                if (file_exists(M1_STORE_BASE_DIR . 'includes/global.inc.php')) {
                    return "Cubecart";
                }
            case 'Cscart203':
                if (file_exists(M1_STORE_BASE_DIR . "config.local.php")
                    || file_exists(
                        M1_STORE_BASE_DIR . "partner.php"
                    )
                ) {
                    return "Cscart203";
                }
            case 'Opencart14':
                if ((file_exists(M1_STORE_BASE_DIR . "system/startup.php")
                        || (file_exists(M1_STORE_BASE_DIR . "common.php"))
                        || (file_exists(M1_STORE_BASE_DIR . "library/locator.php"))
                    )
                    && file_exists(M1_STORE_BASE_DIR . "config.php")
                ) {
                    return "Opencart14";
                }
            case 'Shopware':
                if (file_exists(M1_STORE_BASE_DIR . "config.php")
                    && file_exists(
                        M1_STORE_BASE_DIR . "shopware.php"
                    )
                ) {
                    return "Shopware";
                }
            case 'LemonStand':
                if (file_exists(M1_STORE_BASE_DIR . "boot.php")) {
                    return "LemonStand";
                }
            case 'Interspire':
                if (file_exists(M1_STORE_BASE_DIR . "config/config.php")) {
                    return "Interspire";
                }
            case 'Squirrelcart242':
                if (file_exists(M1_STORE_BASE_DIR . 'squirrelcart/config.php')) {
                    return "Squirrelcart242";
                }
            case 'WebAsyst':
                if (file_exists(M1_STORE_BASE_DIR . 'kernel/wbs.xml')) {
                    return "WebAsyst";
                }
            case 'SSFree':
                if (file_exists(M1_STORE_BASE_DIR . 'cfg/general.inc.php')
                    && file_exists(
                        M1_STORE_BASE_DIR . 'cfg/connect.inc.php'
                    )
                ) {
                    return "SSFree";
                }
            case 'SSPremium':
                //Shopscript Premium
                if (file_exists(M1_STORE_BASE_DIR . 'cfg/connect.inc.php')) {
                    return "SSPremium";
                }

                //ShopScript5
                if (file_exists(M1_STORE_BASE_DIR . 'wa.php')
                    && file_exists(
                        M1_STORE_BASE_DIR . 'wa-config/db.php'
                    )
                ) {
                    return "SSPremium";
                }
            case 'Summercart3':
                if (file_exists(M1_STORE_BASE_DIR . 'sclic.lic')
                    && file_exists(
                        M1_STORE_BASE_DIR . 'include/miphpf/Config.php'
                    )
                ) {
                    return "Summercart3";
                }
            case 'XtcommerceVeyton':
                if (file_exists(M1_STORE_BASE_DIR . 'conf/config.php')) {
                    return "XtcommerceVeyton";
                }
            case 'XCart':
                if (file_exists(M1_STORE_BASE_DIR . "config.php")) {
                    return "XCart";
                }
            case 'Litecommerce':
                if ((file_exists(M1_STORE_BASE_DIR . '/etc/config.php'))
                    || (file_exists(
                        M1_STORE_BASE_DIR
                        . '/modules/lc_connector/litecommerce/etc/config.php'
                    ))
                ) {
                    return "Litecommerce";
                }
            case 'Hhgmultistore':
                if (file_exists(M1_STORE_BASE_DIR . 'core/config/configure.php')) {
                    return 'Hhgmultistore';
                }
            case 'Sunshop4':
                if (file_exists(
                        M1_STORE_BASE_DIR . "include" . DIRECTORY_SEPARATOR . "config.php"
                    )
                    || file_exists(
                        M1_STORE_BASE_DIR . "include" . DIRECTORY_SEPARATOR
                        . "db_mysql.php"
                    )
                ) {
                    return "Sunshop4";
                }
            case 'Tomatocart':
                if (file_exists(
                        M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR
                        . "configure.php"
                    )
                    && file_exists(
                        M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR
                        . "toc_constants.php"
                    )
                ) {
                    return 'Tomatocart';
                }
        }
        die ("BRIDGE_ERROR_CONFIGURATION_NOT_FOUND");
    }

    /**
     * @param $cartType
     * @return string
     */
    public function getAdapterPath($cartType)
    {
        return M1_STORE_BASE_DIR . M1_BRIDGE_DIRECTORY_NAME . DIRECTORY_SEPARATOR
            . "app" . DIRECTORY_SEPARATOR
            . "class" . DIRECTORY_SEPARATOR
            . "config_adapter" . DIRECTORY_SEPARATOR . $cartType . ".php";
    }

    /**
     * @param $source
     */
    public function setHostPort($source)
    {
        $source = trim($source);

        if ($source == '') {
            $this->host = 'localhost';
            return;
        }

        if (strpos($source, '.sock') !== false) {
            $socket = ltrim($source, 'localhost:');
            $socket = ltrim($socket, '127.0.0.1:');

            $this->host = 'localhost';
            $this->sock = $socket;

            return;
        }

        $conf = explode(":", $source);

        if (isset($conf[0]) && isset($conf[1])) {
            $this->host = $conf[0];
            $this->port = $conf[1];
        } elseif ($source[0] == '/') {
            $this->host = 'localhost';
            $this->port = $source;
        } else {
            $this->host = $source;
        }
    }

    /**
     * @return bool|M1_Mysql|M1_Mysqli|M1_Pdo
     */
    public function connect()
    {
        if (function_exists('mysql_connect')) {
            $link = new M1_Mysql($this);
        } elseif (function_exists('mysqli_connect')) {
            $link = new M1_Mysqli($this);
        } elseif (extension_loaded('pdo_mysql')) {
            $link = new M1_Pdo($this);
        } else {
            $link = false;
        }

        return $link;
    }

    /**
     * @param $field
     * @param $tableName
     * @param $where
     * @return string
     */
    public function getCartVersionFromDb($field, $tableName, $where)
    {
        $version = '';

        $link = $this->connect();
        if (!$link) {
            return '[ERROR] MySQL Query Error: Can not connect to DB';
        }

        $result = $link->localQuery("
      SELECT " . $field . " AS version
      FROM " . $this->tblPrefix . $tableName . "
      WHERE " . $where
        );

        if (is_array($result) && isset($result[0]['version'])) {
            $version = $result[0]['version'];
        }

        return $version;
    }
}

class M1_Bridge
{
    protected $_link = null; //mysql connection link
    public $config = null; //config adapter

    /**
     * Bridge constructor
     *
     * M1_Bridge constructor.
     * @param $config
     */
    public function __construct(M1_Config_Adapter $config)
    {
        $this->config = $config;

        if ($this->getAction() != "savefile") {
            $this->_link = $this->config->connect();
        }
    }

    /**
     * @return mixed
     */
    public function getTablesPrefix()
    {
        return $this->config->tblPrefix;
    }

    /**
     * @return null
     */
    public function getLink()
    {
        return $this->_link;
    }

    /**
     * @param $sql
     * @param $fetchMode
     * @return mixed
     */
    public function query($sql, $fetchMode)
    {
        return $this->_link->query($sql, $fetchMode);
    }

    /**
     * @return mixed|string
     */
    private function getAction()
    {
        if (isset($_GET['action'])) {
            return str_replace('.', '', $_GET['action']);
        }

        return '';
    }

    public function run()
    {
        $action = $this->getAction();

        if ($action == "checkbridge") {
            echo "BRIDGE_OK";
            return;
        }

        if ($action != "update") {
            $this->_selfTest();
        }

        if ($action == "update") {
            $this->_checkPossibilityUpdate();
        }

        $className = "M1_Bridge_Action_" . ucfirst($action);
        if (!class_exists($className)) {
            echo 'ACTION_DO_NOT EXIST' . PHP_EOL;
            die;
        }

        $actionObj = new $className();
        @$actionObj->cartType = @$this->config->cartType;
        $actionObj->perform($this);
        $this->_destroy();
    }

    /**
     * @param $dir
     * @return bool
     */
    private function isWritable($dir)
    {
        if (!@is_dir($dir)) {
            return false;
        }

        $dh = @opendir($dir);

        if ($dh === false) {
            return false;
        }

        while (($entry = readdir($dh)) !== false) {
            if ($entry == "." || $entry == ".." || !@is_dir($dir . DIRECTORY_SEPARATOR . $entry)) {
                continue;
            }

            if (!$this->isWritable($dir . DIRECTORY_SEPARATOR . $entry)) {
                return false;
            }
        }

        if (!is_writable($dir)) {
            return false;
        }

        return true;
    }

    private function _destroy()
    {
        $this->_link = null;
    }

    private function _checkPossibilityUpdate()
    {
        if (!is_writable(M1_STORE_BASE_DIR . "/" . M1_BRIDGE_DIRECTORY_NAME . "/")) {
            die("ERROR_TRIED_TO_PERMISSION" . M1_STORE_BASE_DIR . "/" . M1_BRIDGE_DIRECTORY_NAME . "/");
        }

        if (isset($_GET['hash']) && $_GET['hash'] === M1_TOKEN) {
            // good :)
        } else {
            die('ERROR_INVALID_TOKEN');
        }

        if (!is_writable(M1_STORE_BASE_DIR . "/" . M1_BRIDGE_DIRECTORY_NAME . "/bridge.php")) {
            die("ERROR_TRIED_TO_PERMISSION_BRIDGE_FILE" . M1_STORE_BASE_DIR . "/" . M1_BRIDGE_DIRECTORY_NAME . "/bridge.php");
        }
    }

    private function _selfTest()
    {
        if (isset($_GET['token'])) {
            if ($_GET['token'] === M1_TOKEN) {
                // good :)
            } else {
                die('ERROR_INVALID_TOKEN');
            }
        } else {
            die('BRIDGE INSTALLED.<br /> Version: ' . M1_BRIDGE_VERSION);
        }
    }

}


class M1_Mysql
{
    public $config = null; // config adapter
    public $result = array();
    public $dataBaseHandle = null;

    /**
     * mysql constructor
     *
     * @param M1_Config_Adapter $config
     * @return M1_Mysql
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @return bool|null|resource
     */
    private function getDataBaseHandle()
    {
        if ($this->dataBaseHandle) {
            return $this->dataBaseHandle;
        }

        $this->dataBaseHandle = $this->connect();

        if (!$this->dataBaseHandle) {
            exit('[ERROR] MySQL Query Error: Can not connect to DB');
        }

        return $this->dataBaseHandle;
    }

    /**
     * @return bool|null|resource
     */
    private function connect()
    {
        $triesCount = 10;
        $link = null;
        $host = $this->config->host . ($this->config->port ? ':' . $this->config->port : '');

        if ($this->config->sock !== null) {
            $host = $this->config->host . ':' . $this->config->sock;
        }

        $password = stripslashes($this->config->password);

        while (!$link) {
            if (!$triesCount--) {
                break;
            }

            $link = @mysql_connect($host, $this->config->username, $password);
            if (!$link) {
                sleep(5);
            }
        }

        if ($link) {
            mysql_select_db($this->config->dbname, $link);
        } else {
            return false;
        }

        return $link;
    }

    /**
     * @param string $sql sql query
     *
     * @return array
     */
    public function localQuery($sql)
    {
        $result = array();
        $dataBaseHandle = $this->getDataBaseHandle();

        $sth = mysql_query($sql, $dataBaseHandle);

        if (is_bool($sth)) {
            return $sth;
        }

        while (($row = mysql_fetch_assoc($sth)) != false) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param string $sql sql query
     * @param int $fetchType fetch Type
     *
     * @return array
     */
    public function query($sql, $fetchType)
    {
        $result = array(
            'result' => null,
            'message' => '',
        );
        $dataBaseHandle = $this->getDataBaseHandle();

        if (!$dataBaseHandle) {
            $result['message'] = '[ERROR] MySQL Query Error: Can not connect to DB';
            return $result;
        }

        if (isset($_GET['disable_checks'])) {
            $this->localQuery('SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0');
            $this->localQuery("SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
        }

        if (isset($_REQUEST['set_names'])) {
            mysql_set_charset($_REQUEST['set_names'], $dataBaseHandle);
        }

        $fetchMode = MYSQL_ASSOC;
        switch ($fetchType) {
            case 3:
                $fetchMode = MYSQL_BOTH;
                break;
            case 2:
                $fetchMode = MYSQL_NUM;
                break;
            case 1:
                $fetchMode = MYSQL_ASSOC;
                break;
            default:
                break;
        }

        $res = mysql_query($sql, $dataBaseHandle);

        $triesCount = 10;
        while (mysql_errno($dataBaseHandle) == 2013) {
            if (!$triesCount--) {
                break;
            }
            // reconnect
            $dataBaseHandle = $this->getDataBaseHandle();
            if ($dataBaseHandle) {

                if (isset($_REQUEST['set_names'])) {
                    mysql_set_charset($_REQUEST['set_names'], $dataBaseHandle);
                }

                // execute query once again
                $res = mysql_query($sql, $dataBaseHandle);
            }
        }

        if (($errno = mysql_errno($dataBaseHandle)) != 0) {
            $result['message'] = '[ERROR] Mysql Query Error: ' . $errno . ', ' . mysql_error($dataBaseHandle);
            return $result;
        }

        if (!is_resource($res)) {
            $result['result'] = $res;
            return $result;
        }

        $fetchedFields = array();
        while (($field = mysql_fetch_field($res)) !== false) {
            $fetchedFields[] = $field;
        }

        $rows = array();
        while (($row = mysql_fetch_array($res, $fetchMode)) !== false) {
            $rows[] = $row;
        }

        if (isset($_GET['disable_checks'])) {
            $this->localQuery("SET SQL_MODE=IFNULL(@OLD_SQL_MODE,'')");
            $this->localQuery("SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS,0)");
        }

        $result['result'] = $rows;
        $result['fetchedFields'] = $fetchedFields;

        mysql_free_result($res);
        return $result;
    }

    /**
     * @return int
     */
    public function getLastInsertId()
    {
        return mysql_insert_id($this->dataBaseHandle);
    }

    /**
     * @return int
     */
    public function getAffectedRows()
    {
        return mysql_affected_rows($this->dataBaseHandle);
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        if ($this->dataBaseHandle) {
            mysql_close($this->dataBaseHandle);
        }

        $this->dataBaseHandle = null;
    }

}


class M1_Pdo
{
    public $config = null; // config adapter
    public $noResult = array('delete', 'update', 'move', 'truncate', 'insert', 'set', 'create', 'drop');
    public $dataBaseHandle = null;

    private $_insertedId = 0;
    private $_affectedRows = 0;

    /**
     * pdo constructor
     *
     * @param M1_Config_Adapter $config configuration
     * @return M1_Pdo
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @return bool|null|PDO
     */
    private function getDataBaseHandle()
    {
        if ($this->dataBaseHandle) {
            return $this->dataBaseHandle;
        }

        $this->dataBaseHandle = $this->connect();

        if (!$this->dataBaseHandle) {
            exit('[ERROR] MySQL Query Error: Can not connect to DB');
        }

        return $this->dataBaseHandle;
    }

    /**
     * @return bool|PDO
     */
    private function connect()
    {
        $triesCount = 3;
        $password = stripslashes($this->config->password);

        while ($triesCount) {
            try {
                $dsn = 'mysql:dbname=' . $this->config->dbname . ';host=' . $this->config->host;
                if ($this->config->port) {
                    $dsn .= ';port=' . $this->config->port;
                }
                if ($this->config->sock != null) {
                    $dsn .= ';unix_socket=' . $this->config->sock;
                }

                $link = new PDO($dsn, $this->config->username, $password);
                $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                return $link;

            } catch (PDOException $e) {
                $triesCount--;
                sleep(1);
            }
        }
        return false;
    }

    /**
     * @param string $sql sql query
     *
     * @return array|bool
     */
    public function localQuery($sql)
    {
        $result = array();
        $dataBaseHandle = $this->getDataBaseHandle();

        $sth = $dataBaseHandle->query($sql);

        foreach ($this->noResult as $statement) {
            if (!$sth || strpos(strtolower(trim($sql)), $statement) === 0) {
                return true;
            }
        }

        while (($row = $sth->fetch(PDO::FETCH_ASSOC)) != false) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param string $sql sql query
     * @param int $fetchType fetch Type
     *
     * @return array
     */
    public function query($sql, $fetchType)
    {
        $result = array(
            'result' => null,
            'message' => '',
            'fetchedFields' => array()
        );
        $dataBaseHandle = $this->getDataBaseHandle();

        if (isset($_GET['disable_checks'])) {
            $dataBaseHandle->exec('SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0');
            $dataBaseHandle->exec("SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
        }

        if (isset($_REQUEST['set_names'])) {
            $dataBaseHandle->exec("SET NAMES '" . ($_REQUEST['set_names']) . "'");
            $dataBaseHandle->exec("SET CHARACTER SET '" . ($_REQUEST['set_names']) . "'");
            $dataBaseHandle->exec("SET CHARACTER_SET_CONNECTION = '" . ($_REQUEST['set_names']) . "'");
        }

        switch ($fetchType) {
            case 3:
                $dataBaseHandle->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_BOTH);
                break;
            case 2:
                $dataBaseHandle->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_NUM);
                break;
            case 1:
            default:
                $dataBaseHandle->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                break;
        }

        try {
            $res = $dataBaseHandle->query($sql);
            $this->_affectedRows = $res->rowCount();
            $this->_insertedId = $dataBaseHandle->lastInsertId();
        } catch (PDOException $e) {
            $result['message'] = '[ERROR] Mysql Query Error: ' . $e->getCode() . ', ' . $e->getMessage();
            return $result;
        }

        foreach ($this->noResult as $statement) {
            if (!$res || strpos(strtolower(trim($sql)), $statement) === 0) {
                $result['result'] = true;
                return $result;
            }
        }

        $rows = array();
        while (($row = $res->fetch()) !== false) {
            $rows[] = $row;
        }

        if (isset($_GET['disable_checks'])) {
            $this->localQuery("SET SQL_MODE=IFNULL(@OLD_SQL_MODE,'')");
            $this->localQuery("SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS,0)");
        }

        $result['result'] = $rows;

        unset($res);
        return $result;
    }

    /**
     * @return string|int
     */
    public function getLastInsertId()
    {
        return $this->_insertedId;
    }

    /**
     * @return int
     */
    public function getAffectedRows()
    {
        return $this->_affectedRows;
    }

    /**
     * @return  void
     */
    public function __destruct()
    {
        $this->dataBaseHandle = null;
    }
}


define('M1_BRIDGE_VERSION', '29');
define('M1_BRIDGE_DOWNLOAD_LINK', 'https://api.api2cart.com/v1.0/bridge.download.file?update');
define('M1_BRIDGE_DIRECTORY_NAME', basename(getcwd()));

@ini_set('display_errors', 1);
if (substr(phpversion(), 0, 1) == 5) {
    error_reporting(E_ALL & ~E_STRICT);
} else {
    error_reporting(E_ALL);
}

require_once 'config.php';

/**
 * @param $array
 * @return array|string|stripslashes_array
 */
function stripslashes_array($array)
{
    return is_array($array) ? array_map('stripslashes_array', $array) : stripslashes($array);
}

function exceptions_error_handler($severity, $message, $filename, $lineno)
{
    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}

set_error_handler('exceptions_error_handler');

/**
 * @return bool|mixed|string
 */
function getPHPExecutable()
{
    $paths = explode(PATH_SEPARATOR, getenv('PATH'));
    $paths[] = PHP_BINDIR;
    foreach ($paths as $path) {
        // we need this for XAMPP (Windows)
        if (isset($_SERVER["WINDIR"]) && strstr($path, 'php.exe') && file_exists($path) && is_file($path)) {
            return $path;
        } else {
            $phpExecutable = $path . DIRECTORY_SEPARATOR . "php" . (isset($_SERVER["WINDIR"]) ? ".exe" : "");
            if (file_exists($phpExecutable) && is_file($phpExecutable)) {
                return $phpExecutable;
            }
        }
    }
    return false;
}

if (!isset($_SERVER)) {
    $_GET = &$HTTP_GET_VARS;
    $_POST = &$HTTP_POST_VARS;
    $_ENV = &$HTTP_ENV_VARS;
    $_SERVER = &$HTTP_SERVER_VARS;
    $_COOKIE = &$HTTP_COOKIE_VARS;
    $_REQUEST = array_merge($_GET, $_POST, $_COOKIE);
}

if (get_magic_quotes_gpc()) {
    $_COOKIE = stripslashes_array($_COOKIE);
    $_FILES = stripslashes_array($_FILES);
    $_GET = stripslashes_array($_GET);
    $_POST = stripslashes_array($_POST);
    $_REQUEST = stripslashes_array($_REQUEST);
}

if (isset($_POST['store_root'])) {
    define("M1_STORE_BASE_DIR", $_POST['store_root'] . DIRECTORY_SEPARATOR);
} elseif (isset($_SERVER['SCRIPT_FILENAME'])) {
    $scriptPath = $_SERVER['SCRIPT_FILENAME'];
    if (isset($_SERVER['PATH_TRANSLATED']) && $_SERVER['PATH_TRANSLATED'] != "") {
        $scriptPath = $_SERVER['PATH_TRANSLATED'];
    }
    define("M1_STORE_BASE_DIR", preg_replace('/[^\/\\\]*[\/\\\][^\/\\\]*$/', '', $scriptPath));
} else {
    //Windows IIS
    define("M1_STORE_BASE_DIR", preg_replace('/[^\/\\\]*[\/\\\][^\/\\\]*$/', '', realpath(dirname(__FILE__) . "/../")));
}

$adapter = new M1_Config_Adapter();
$bridge = new M1_Bridge($adapter->create());

if (!$bridge->getLink()) {
    die ('ERROR_BRIDGE_CANT_CONNECT_DB');
}

$bridge->run();
?>