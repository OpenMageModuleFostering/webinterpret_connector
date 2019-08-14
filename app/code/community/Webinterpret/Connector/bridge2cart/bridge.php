<?php
/**
 * Bridge
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */

require_once 'preloader.php';

class M1_Bridge_Action_Update
{
  var $uri = "BRIDGE_DOWNLOAD_LINK";

  var $pathToTmpDir;
  
  var $pathToFile = __FILE__;
  
  function M1_Bridge_Action_Update()
  {
    $this->pathToTmpDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . "temp_c2c";
  }

  function perform($bridge)
  {
    $response = new stdClass();
    if ( !($this->_checkBridgeDirPermission() && $this->_checkBridgeFilePermission()) ) {
      $response->is_error = true;
      $response->message = "Bridge Update couldn't be performed. Please change permission for bridge folder to 777 and bridge.php file inside it to 666";
      echo serialize($response);die;  
    }


    if ( ($data = $this->_downloadFile()) === false ) {
      $response->is_error = true;
      $response->message = "Bridge Version is outdated. Files couldn't be updated automatically. Please set write permission or re-upload files manually.";
      echo serialize($response);die;
    }

    if ( !$this->_writeToFile($data, $this->pathToFile) ) {
      $response->is_error = true;
      $response->message = "Couln't create file in temporary folder or file is write protected.";
      echo serialize($response);die;
    }

    $response->is_error = false;
    $response->message = "Bridge successfully updated to latest version";
    echo serialize($response);
    die;
  }

  function _fetch( $uri )
  {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $uri);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    $response = new stdClass();

    $response->body           = curl_exec($ch);
    $response->http_code      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response->content_type   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $response->content_length = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

    curl_close($ch);

    return $response;
  }

  function _checkBridgeDirPermission()
  {
    if (!is_writeable(dirname(__FILE__))) {
      @chmod(dirname(__FILE__), 0777);
    }
    return is_writeable(dirname(__FILE__));
  }

  function _checkBridgeFilePermission()
  {
    $pathToFile = dirname(__FILE__) . DIRECTORY_SEPARATOR . "bridge.php";
    if (!is_writeable($pathToFile)) {
      @chmod($pathToFile, 0666);
    }
    return is_writeable($pathToFile);
  }

  function _createTempDir()
  {
    @mkdir($this->pathToTmpDir, 0777);
    return file_exists($this->pathToTmpDir);
  }

  function _removeTempDir()
  {
    @unlink($this->pathToTmpDir . DIRECTORY_SEPARATOR . "bridge.php_c2c");
    @rmdir($this->pathToTmpDir);
    return !file_exists($this->pathToTmpDir);
  }

  function _downloadFile()
  {
    $file = $this->_fetch($this->uri);
    if ( $file->http_code == 200 ) {
      return $file;
    }
    return false;
  }

  function _writeToFile($data, $file)
  {
    if (function_exists("file_put_contents")) {
      $bytes = file_put_contents($file, $data->body);
      return $bytes == $data->content_length;
    }

    $handle = @fopen($file, 'w+');
    $bytes = fwrite($handle, $data->body);
    @fclose($handle);

    return $bytes == $data->content_length;

  }

}

class M1_Bridge_Action_Query
{
  function perform($bridge)
  {
    if (isset($_POST['query']) && isset($_POST['fetchMode'])) {
      $query = base64_decode($_POST['query']);

      $res = $bridge->query($query, (int)$_POST['fetchMode']);

      if (is_array($res['result']) || is_bool($res['result'])) {
        $result = serialize(array(
          'res'           => $res['result'],
          'fetchedFields' => @$res['fetchedFields'],
          'insertId'      => $bridge->getLink()->getLastInsertId(),
          'affectedRows'  => $bridge->getLink()->getAffectedRows(),
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

class M1_Bridge_Action_Getconfig
{

  function parseMemoryLimit($val)
  {
    $last = strtolower($val[strlen($val)-1]);
    switch($last) {
      case 'g':
          $val *= 1024;
      case 'm':
          $val *= 1024;
      case 'k':
          $val *= 1024;
    }

    return $val;
  }

  function getMemoryLimit()
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

  function isZlibSupported()
  {
    return function_exists('gzdecode');
  }

  function perform($bridge)
  {
    if (!defined("DEFAULT_LANGUAGE_ISO2")) {
      define("DEFAULT_LANGUAGE_ISO2", ""); //variable for Interspire cart
    }

    $result = array(
      "images" => array(
        "imagesPath"                => $bridge->config->imagesDir, // path to images folder - relative to store root
        "categoriesImagesPath"      => $bridge->config->categoriesImagesDir,
        "categoriesImagesPaths"     => $bridge->config->categoriesImagesDirs,
        "productsImagesPath"        => $bridge->config->productsImagesDir,
        "productsImagesPaths"       => $bridge->config->productsImagesDirs,
        "manufacturersImagesPath"   => $bridge->config->manufacturersImagesDir,
        "manufacturersImagesPaths"  => $bridge->config->manufacturersImagesDirs,
      ),
      "languages"             => $bridge->config->languages,
      "baseDirFs"             => M1_STORE_BASE_DIR,    // filesystem path to store root
      "defaultLanguageIso2"   => DEFAULT_LANGUAGE_ISO2,
      "databaseName"          => $bridge->config->Dbname,
      "memoryLimit"           => $this->getMemoryLimit(),
      "zlibSupported"         => $this->isZlibSupported(),
      //"orderStatus"           => $bridge->config->orderStatus,
      "cartVars"              => $bridge->config->cartVars,
    );

    echo serialize($result);
  }

}


class M1_Bridge_Action_Batchsavefile extends M1_Bridge_Action_Savefile
{
  function perform($bridge) {
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

class M1_Bridge_Action_Deleteimages
{
  function perform($bridge)
  {
    switch($bridge->config->cartType) {
      case "Pinnacle361":
        $this->_PinnacleDeleteImages($bridge);
      break;
      case "Prestashop11":
        $this->_PrestaShopDeleteImages($bridge);
      break;
      case 'Summercart3' :
        $this->_SummercartDeleteImages($bridge);
      break;
    }
  }

  function _PinnacleDeleteImages($bridge)
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

    foreach($dirs as $dir) {

      if( !file_exists( $dir ) ) {
        continue;
      }

      $dirHandle = opendir($dir);

      while (false !== ($file = readdir($dirHandle))) {
        if ($file != "." && $file != ".." && !preg_match("/^readme\.txt?$/",$file) && !preg_match("/\.bak$/i",$file)) {
          $file_path = $dir . $file;
          if( is_file($file_path) ) {
            if(!rename($file_path, $file_path.".bak")) $ok = false;
          }
        }
      }

      closedir($dirHandle);

    }

    if ($ok) print "OK";
    else print "ERROR";
  }

  function _PrestaShopDeleteImages($bridge)
  {
    $dirs = array(
      M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'c/',
      M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'p/',
      M1_STORE_BASE_DIR . $bridge->config->imagesDir . 'm/',
    );

    $ok = true;

    foreach($dirs as $dir) {

      if( !file_exists( $dir ) ) {
        continue;
      }

      $dirHandle = opendir($dir);

      while (false !== ($file = readdir($dirHandle))) {
        if ($file != "." && $file != ".." && preg_match( "/(\d+).*\.jpg?$/",$file )) {
          $file_path = $dir . $file;
          if( is_file($file_path) ) {
            if(!rename($file_path, $file_path.".bak")) $ok = false;
          }
        }
      }

      closedir($dirHandle);

    }

    if ($ok) print "OK";
    else print "ERROR";
  }

  function _SummercartDeleteImages($bridge)
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

    foreach($dirs as $dir) {

      if( !file_exists( $dir ) ) {
        continue;
      }

      $dirHandle = opendir($dir);

      while (false !== ($file = readdir($dirHandle))) {
        if (($file != ".") && ($file != "..") && !preg_match("/\.bak$/i",$file) ) {
          $file_path = $dir . $file;
          if( is_file($file_path) ) {
            if(!rename($file_path, $file_path.".bak")) $ok = false;
          }
        }
      }

      closedir($dirHandle);

    }

    if ($ok) print "OK";
    else print "ERROR";
  }
}

class M1_Bridge_Action_Cubecart
{
  function perform($bridge)
  {
    $dirHandle = opendir(M1_STORE_BASE_DIR . 'language/');

    $languages = array();

    while ($dirEntry = readdir($dirHandle)) {
      if (!is_dir(M1_STORE_BASE_DIR . 'language/' . $dirEntry) || $dirEntry == '.' || $dirEntry == '..' || strpos($dirEntry, "_") !== false) {
        continue;
      }

      $lang['id'] = $dirEntry;
      $lang['iso2'] = $dirEntry;

      $cnfile = "config.inc.php";

      if (!file_exists(M1_STORE_BASE_DIR . 'language/' . $dirEntry . '/'. $cnfile)) {
        $cnfile = "config.php";
      }

      if (!file_exists( M1_STORE_BASE_DIR . 'language/' . $dirEntry . '/'. $cnfile)) {
        continue;
      }

      $str = file_get_contents(M1_STORE_BASE_DIR . 'language/' . $dirEntry . '/'.$cnfile);
      preg_match("/".preg_quote('$langName')."[\s]*=[\s]*[\"\'](.*)[\"\'];/", $str, $match);

      if (isset($match[1])) {
        $lang['name'] = $match[1];
        $languages[] = $lang;
      }
    }

    echo serialize($languages);
  }
}

class M1_Bridge_Action_Mysqlver
{
  function perform($bridge)
  {
    $m = array();
    preg_match('/^(\d+)\.(\d+)\.(\d+)/', mysql_get_server_info($bridge->getLink()), $m);
    echo sprintf("%d%02d%02d", $m[1], $m[2], $m[3]);
  }
}

class M1_Bridge_Action_Clearcache
{
  function perform($bridge)
  {
    switch($bridge->config->cartType) {
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
   *
   * @var $fileExclude - name file in format pregmatch
   */

  function _removeGarbage($dirs = array(), $fileExclude = '')
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

        if ((trim($fileExclude) != '') && preg_match("/^" .$fileExclude . "?$/", $file)) {
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

  function _magentoClearCache()
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

  function _InterspireClearCache()
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

  function _CubecartClearCache()
  {
    $ok = true;

    if (file_exists(M1_STORE_BASE_DIR . 'cache')) {
      $dirHandle = opendir(M1_STORE_BASE_DIR . 'cache/');

      while (false !== ($file = readdir($dirHandle))) {
        if ($file != "." && $file != ".." && !preg_match("/^index\.html?$/", $file) && !preg_match("/^\.htaccess?$/", $file)) {
          if (is_file( M1_STORE_BASE_DIR . 'cache/' . $file)) {
            if (!unlink(M1_STORE_BASE_DIR . 'cache/' . $file)) {
              $ok = false;
            }
          }
        }
      }

      closedir($dirHandle);
    }

    if (file_exists(M1_STORE_BASE_DIR.'includes/extra/admin_cat_cache.txt')) {
      unlink(M1_STORE_BASE_DIR.'includes/extra/admin_cat_cache.txt');
    }

    if ($ok) {
      echo 'OK';
    } else {
      echo 'ERROR';
    }
  }

  function _PrestashopClearCache()
  {
    $dirs = array(
      M1_STORE_BASE_DIR . 'tools/smarty/compile/',
      M1_STORE_BASE_DIR . 'tools/smarty/cache/',
      M1_STORE_BASE_DIR . 'img/tmp/'
    );

    $this->_removeGarbage($dirs, 'index\.php');
  }

  function _OpencartClearCache()
  {
    $dirs = array(
      M1_STORE_BASE_DIR . 'system/cache/',
    );

    $this->_removeGarbage($dirs, 'index\.html');
  }

  function _Xtcommerce4ClearCache()
  {
    $dirs = array(
      M1_STORE_BASE_DIR . 'cache/',
    );

    $this->_removeGarbage($dirs, 'index\.html');
  }

  function _ubercartClearCache()
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

  function _tomatocartClearCache()
  {
    $dirs = array(
      M1_STORE_BASE_DIR . 'includes/work/',
    );

    $this->_removeGarbage($dirs, '\.htaccess');
  }

  /**
   * Try chage permissions actually :)
   */
  function _virtuemartClearCache()
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

  function _Oscommerce3ClearCache()
  {
    $dirs = array(
      M1_STORE_BASE_DIR . 'osCommerce/OM/Work/Cache/',
    );

    $this->_removeGarbage($dirs, '\.htaccess');
  }

  function _GambioClearCache()
  {
    $dirs = array(
      M1_STORE_BASE_DIR . 'cache/',
    );

    $this->_removeGarbage($dirs, 'index\.html');
  }

  function _OxidClearCache()
  {
    $dirs = array(
      M1_STORE_BASE_DIR . 'tmp/',
    );

    $this->_removeGarbage($dirs, '\.htaccess');
  }

  function _XcartClearCache()
  {
    $dirs = array(
      M1_STORE_BASE_DIR . 'var/cache/',
    );

    $this->_removeGarbage($dirs, '\.htaccess');
  }

  function _CscartClearCache()
  {
    $dir = M1_STORE_BASE_DIR . 'var/cache/';
    $res = $this->removeDirRec($dir);

    if ($res) {
      echo 'OK';
    } else {
      echo 'ERROR';
    }
  }

  function _Prestashop15ClearCache()
  {
    $dirs = array(
      M1_STORE_BASE_DIR . 'cache/smarty/compile/',
      M1_STORE_BASE_DIR . 'cache/smarty/cache/',
      M1_STORE_BASE_DIR . 'img/tmp/'
    );

    $this->_removeGarbage($dirs, 'index\.php');
  }

  function removeDirRec($dir)
  {
    $result = true;

    if ($objs = glob($dir."/*")) {
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


class M1_Bridge_Action_Basedirfs
{
  function perform($bridge)
  {
    echo M1_STORE_BASE_DIR;
  }
}

class M1_Bridge_Action_Phpinfo
{
  function perform($bridge)
  {
    phpinfo();
  }
}


class M1_Bridge_Action_Savefile
{
  var $_imageType = null;

  function perform($bridge)
  {
    $source      = $_POST['src'];
    $destination = $_POST['dst'];
    $width       = (int)$_POST['width'];
    $height      = (int)$_POST['height'];
    $local       = $_POST['local_source'];

    echo $this->_saveFile($source, $destination, $width, $height, $local);
  }

  function _saveFile($source, $destination, $width, $height, $local = '')
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
      $this->_scaled2( $destination, $width, $height );
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

  function _copyLocal($source, $destination, $width, $height)
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

  function _loadImage($filename, $skipJpg = true)
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

  function _saveImage($image, $filename, $imageType = IMAGETYPE_JPEG, $compression = 85, $permissions = null)
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

  function _createFile($source, $destination)
  {
    if ($this->_create_dir(dirname($destination)) !== false) {
      $destination = M1_STORE_BASE_DIR . $destination;
      $body = base64_decode($source);
      if ($body === false || file_put_contents($destination, $body) === false) {
        return '[BRIDGE ERROR] File save failed!';
      }

      return 'OK';
    }

    return '[BRIDGE ERROR] Directory creation failed!';
  }

  function _saveFileLocal($source, $destination)
  {
    $srcInfo = parse_url($source);
    $src = rtrim($_SERVER['DOCUMENT_ROOT'], "/") . $srcInfo['path'];

    if ($this->_create_dir(dirname($destination)) !== false) {
      $dst = M1_STORE_BASE_DIR . $destination;

      if (!@copy($src, $dst)) {
        return $this->_saveFileCurl($source, $destination);
      }

    } else {
      return "[BRIDGE ERROR] Directory creation failed!";
    }

    return "OK";
  }

  function _saveFileCurl($source, $destination)
  {
    $source = $this->_escapeSource($source);
    if ($this->_create_dir(dirname($destination)) !== false) {
      $destination = M1_STORE_BASE_DIR . $destination;

      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $source);
      curl_setopt($ch, CURLOPT_HEADER, 0);
      curl_setopt($ch, CURLOPT_TIMEOUT, 60);
      curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1");
      curl_setopt($ch, CURLOPT_NOBODY, true);
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
      @chmod($destination, 0777);

      return "OK";

    } else {
      return "[BRIDGE ERROR] Directory creation failed!";
    }
  }

  function _escapeSource($source)
  {
    return str_replace(" ", "%20", $source);
  }

  function _create_dir($dir) {
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

  function _isSameHost($source)
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
   * @param $image     - GD image object
   * @param $filename  - store sorce pathfile ex. M1_STORE_BASE_DIR . '/img/c/2.gif';
   * @param $type      - IMAGETYPE_JPEG, IMAGETYPE_GIF or IMAGETYPE_PNG
   * @param $extension - file extension, this use for jpg or jpeg extension in prestashop
   *
   * @return true if success or false if no
   */
  function _convert($image, $filename, $type = IMAGETYPE_JPEG, $extension = '')
  {
    $end = pathinfo($filename, PATHINFO_EXTENSION);

    if ($extension == '') {
      $extension = image_type_to_extension($type, false);
    }

    if ($end == $extension) {
      return true;
    }

    $width  = imagesx($image);
    $height = imagesy($image);

    $newImage = imagecreatetruecolor($width, $height);

    /* Allow to keep nice look even if resized */
    $white = imagecolorallocate($newImage, 255, 255, 255);
    imagefill($newImage, 0, 0, $white);
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $width, $height, $width, $height );
    imagecolortransparent($newImage, $white);

    $pathSave = rtrim($filename, $end);

    $pathSave .= $extension;

    return $this->_saveImage($newImage, $pathSave, $type);
  }

  function _scaled($destination, $width, $height)
  {
    $image = $this->_loadImage($destination, false);

    if ($image === false) {
      return;
    }

    $originWidth  = imagesx( $image );
    $originHeight = imagesy( $image );

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

  //scaled2 method optimizet for prestashop
  function _scaled2($destination, $destWidth, $destHeight)
  {
    $method = 0;

    $sourceImage = $this->_loadImage($destination, false);

    if ($sourceImage === false) {
      return "IMAGE NOT SUPPORTED";
    }

    $sourceWidth  = imagesx($sourceImage);
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
        $destWidth = ((intval($method) == 0 ) ? $destWidth : $nextWidth);
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

    imagecopyresampled($destImage, $sourceImage, $borderWidth, $borderHeight, 0, 0, $nextWidth, $nextHeight, $sourceWidth, $sourceHeight);
    imagecolortransparent($destImage, $white);

    return $this->_saveImage($destImage, $destination, $this->_imageType, 100) ? "OK" : "CAN'T SCALE IMAGE";
  }
}

/**
 * @package  api2cart
 * @author   Vasul Babiy (v.babyi@magneticone.com)
 * @license  Not public license
 * @link     https://www.api2cart.com
 */

class M1_Mysqli
{
  var $config = null; // config adapter
  var $result = array();
  var $dataBaseHandle = null;

  /**
   * mysql constructor
   *
   * @param M1_Config_Adapter $config
   * @return M1_Mysql
   */
  function M1_Mysqli($config)
  {
    $this->config = $config;
  }

  /**
   * @return bool|null|resource
   */
  function getDataBaseHandle()
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
  function connect()
  {
    $triesCount = 10;
    $link = null;
    $host = $this->config->Host . ($this->config->Port ? ':' . $this->config->Port : '');
    $password = stripslashes($this->config->Password);

    while (!$link) {
      if (!$triesCount--) {
        break;
      }

      $link = @mysqli_connect($host, $this->config->Username, $password);
      if (!$link) {
        sleep(5);
      }
    }

    if ($link) {
      mysqli_select_db($link, $this->config->Dbname);
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
  function localQuery($sql)
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
  function query($sql, $fetchType)
  {
    $result = array(
      'result'        => null,
      'message'       => ''
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
      @mysqli_query($dataBaseHandle, "SET NAMES " . @mysqli_real_escape_string($dataBaseHandle, $_REQUEST['set_names']));
      @mysqli_query($dataBaseHandle, "SET CHARACTER SET " . @mysqli_real_escape_string($dataBaseHandle, $_REQUEST['set_names']));
      @mysqli_query($dataBaseHandle, "SET CHARACTER_SET_CONNECTION=" . @mysqli_real_escape_string($dataBaseHandle, $_REQUEST['set_names']));
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
          @mysqli_query($dataBaseHandle, "SET NAMES " . @mysqli_real_escape_string($dataBaseHandle, $_REQUEST['set_names']));
          @mysqli_query($dataBaseHandle, "SET CHARACTER SET " . @mysqli_real_escape_string($dataBaseHandle, $_REQUEST['set_names']));
          @mysqli_query($dataBaseHandle, "SET CHARACTER_SET_CONNECTION=" . @mysqli_real_escape_string($dataBaseHandle, $_REQUEST['set_names']));
        }

        // execute query once again
        $res = mysqli_query($dataBaseHandle, $sql);
      }
    }

    if (($errno = mysqli_errno($dataBaseHandle)) != 0) {
      $result['message'] = '[ERROR] MySQLi Query Error: ' . $errno . ', ' . mysqli_error($dataBaseHandle);
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

    $result['result']        = $rows;
    $result['fetchedFields'] = $fetchedFields;

    mysqli_free_result($res);

    return $result;
  }

  /**
   * @return int
   */
  function getLastInsertId()
  {
    return mysqli_insert_id($this->dataBaseHandle);
  }

  /**
   * @return int
   */
  function getAffectedRows()
  {
    return mysqli_affected_rows($this->dataBaseHandle);
  }

  /**
   * @return void
   */
  function __destruct()
  {
    if ($this->dataBaseHandle) {
      mysqli_close($this->dataBaseHandle);
    }

    $this->dataBaseHandle = null;
  }

}


class M1_Config_Adapter_Woocommerce extends M1_Config_Adapter
{
  function M1_Config_Adapter_Woocommerce()
  {
    //@include_once M1_STORE_BASE_DIR . "wp-config.php";
    if (file_exists(M1_STORE_BASE_DIR . 'wp-config.php')) {
      $config = file_get_contents(M1_STORE_BASE_DIR . 'wp-config.php');
    } else {
      $config = file_get_contents(dirname(M1_STORE_BASE_DIR) . '/wp-config.php');
    }

    preg_match('/define\s*\(\s*\'DB_NAME\',\s*\'(.+)\'\s*\)\s*;/', $config, $match);
    $this->Dbname   = $match[1];
    preg_match('/define\s*\(\s*\'DB_USER\',\s*\'(.+)\'\s*\)\s*;/', $config, $match);
    $this->Username = $match[1];
    preg_match('/define\s*\(\s*\'DB_PASSWORD\',\s*\'(.*)\'\s*\)\s*;/', $config, $match);
    $this->Password = $match[1];
    preg_match('/define\s*\(\s*\'DB_HOST\',\s*\'(.+)\'\s*\)\s*;/', $config, $match);
    $this->setHostPort( $match[1] );
    preg_match('/\$table_prefix\s*=\s*\'(.*)\'\s*;/', $config, $match);
    $this->TblPrefix = $match[1];
    $version = $this->getCartVersionFromDb("option_value", "options", "option_name = 'woocommerce_db_version'");
    
    if ( $version != '' ) {
      $this->cartVars['dbVersion'] = $version;
    }    
    
    $this->cartVars['categoriesDirRelative'] = 'images/categories/';
    $this->cartVars['productsDirRelative'] = 'images/products/';    
    $this->imagesDir = "wp-content/uploads/images/";
    $this->categoriesImagesDir    = $this->imagesDir . 'categories/';
    $this->productsImagesDir      = $this->imagesDir . 'products/';
    $this->manufacturersImagesDir = $this->imagesDir;		
  }
}



class M1_Config_Adapter_Ubercart extends M1_Config_Adapter
{
  function M1_Config_Adapter_Ubercart()
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
      $url['host'] = $url['host'] .':'. $url['port'];
    }

    $this->setHostPort( $url['host'] );
    $this->Dbname   = ltrim( $url['path'], '/' );
    $this->Username = $url['user'];
    $this->Password = $url['pass'];

    $this->imagesDir = "/sites/default/files/";
    if( !file_exists( M1_STORE_BASE_DIR . $this->imagesDir ) ) {
      $this->imagesDir = "/files";
    }

    if ( file_exists(M1_STORE_BASE_DIR . "/modules/ubercart/uc_cart/uc_cart.info") ) {
      $str = file_get_contents(M1_STORE_BASE_DIR . "/modules/ubercart/uc_cart/uc_cart.info");
      if ( preg_match('/version\s+=\s+".+-(.+)"/', $str, $match) != 0 ) {
        $this->cartVars['dbVersion'] = $match[1];
        unset($match);
      }
    }

    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;
  }
}



class M1_Config_Adapter_Cubecart3 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Cubecart3()
  {
    include_once(M1_STORE_BASE_DIR . 'includes/global.inc.php');

    $this->setHostPort($glob['dbhost']);
    $this->Dbname = $glob['dbdatabase'];
    $this->Username = $glob['dbusername'];
    $this->Password = $glob['dbpassword'];

    $this->imagesDir = 'images/uploads';
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;
  }
}

class M1_Config_Adapter_JooCart extends M1_Config_Adapter
{
  function M1_Config_Adapter_JooCart()
  {
    require_once M1_STORE_BASE_DIR . "/configuration.php";

    if (class_exists("JConfig")) {

      $jconfig = new JConfig();

      $this->setHostPort($jconfig->host);
      $this->Dbname   = $jconfig->db;
      $this->Username = $jconfig->user;
      $this->Password = $jconfig->password;

    } else {

      $this->setHostPort($mosConfig_host);
      $this->Dbname   = $mosConfig_db;
      $this->Username = $mosConfig_user;
      $this->Password = $mosConfig_password;
    }


    $this->imagesDir              = "components/com_opencart/image/";
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;
  }
}


class M1_Config_Adapter_Prestashop11 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Prestashop11()
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
        if ((strpos($line, '_DB_') !== false) || (strpos($line, '_PS_IMG_DIR_') !== false) || (strpos($line, '_PS_VERSION_') !== false)) {
          $execute .= " " . $line;
        }
      }
    }

    define( '_PS_ROOT_DIR_', M1_STORE_BASE_DIR );
    eval($execute);

    $this->setHostPort(_DB_SERVER_);
    $this->Dbname   = _DB_NAME_;
    $this->Username = _DB_USER_;
    $this->Password = _DB_PASSWD_;

    if (defined('_PS_IMG_DIR_') && defined('_PS_ROOT_DIR_')) {

      preg_match("/(\/\w+\/)$/i", _PS_IMG_DIR_, $m);
      $this->imagesDir = $m[1];

    } else {
      $this->imagesDir = "/img/";
    }

    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;

    if (defined('_PS_VERSION_')) {
      $this->cartVars['dbVersion'] = _PS_VERSION_;
    }
  }
}



class M1_Config_Adapter_Ubercart3 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Ubercart3()
  {
    @include_once M1_STORE_BASE_DIR . "sites/default/settings.php";

    $url = $databases['default']['default'];

    $url['username'] = urldecode($url['username']);
    $url['password'] = isset($url['password']) ? urldecode($url['password']) : '';
    $url['host'] = urldecode($url['host']);
    $url['database'] = urldecode($url['database']);
    if (isset($url['port'])) {
      $url['host'] = $url['host'] .':'. $url['port'];
    }

    $this->setHostPort( $url['host'] );
    $this->Dbname   = ltrim( $url['database'], '/' );
    $this->Username = $url['username'];
    $this->Password = $url['password'];

    $this->imagesDir = "/sites/default/files/";
    if (!file_exists( M1_STORE_BASE_DIR . $this->imagesDir )) {
      $this->imagesDir = "/files";
    }

    $fileInfo = M1_STORE_BASE_DIR . "/modules/ubercart/uc_cart/uc_cart.info";
    if (file_exists( $fileInfo )) {
      $str = file_get_contents( $fileInfo );
      if (preg_match('/version\s+=\s+".+-(.+)"/', $str, $match) != 0) {
        $this->cartVars['dbVersion'] = $match[1];
        unset($match);
      }
    }

    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;
  }
}


class M1_Config_Adapter_XCart extends M1_Config_Adapter
{
  function M1_Config_Adapter_XCart()
  {
    define('XCART_START', 1);

    $config = file_get_contents(M1_STORE_BASE_DIR . "config.php");

    preg_match('/\$sql_host.+\'(.+)\';/', $config, $match);
    $this->setHostPort( $match[1] );
    preg_match('/\$sql_user.+\'(.+)\';/', $config, $match);
    $this->Username = $match[1];
    preg_match('/\$sql_db.+\'(.+)\';/', $config, $match);
    $this->Dbname   = $match[1];
    preg_match('/\$sql_password.+\'(.*)\';/', $config, $match);
    $this->Password = $match[1];

    $this->imagesDir = 'images/'; // xcart starting from 4.1.x hardcodes images location
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;

    if(file_exists(M1_STORE_BASE_DIR . "VERSION")) {
      $version = file_get_contents(M1_STORE_BASE_DIR . "VERSION");
      $this->cartVars['dbVersion'] = preg_replace('/(Version| |\\n)/','',$version);
    }

  }
}

class M1_Config_Adapter_Cubecart extends M1_Config_Adapter
{
  function M1_Config_Adapter_Cubecart()
  {
    include_once(M1_STORE_BASE_DIR . 'includes/global.inc.php');

    $this->setHostPort($glob['dbhost']);
    $this->Dbname = $glob['dbdatabase'];
    $this->Username = $glob['dbusername'];
    $this->Password = $glob['dbpassword'];

    $this->imagesDir = 'images';
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
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
      if (is_dir(M1_STORE_BASE_DIR . 'language/' . $dirEntry) || $dirEntry == '.' || $dirEntry == '..' || strpos($dirEntry, "_") !== false || $xmlflag) {
        continue;
      }
      $configXml = simplexml_load_file(M1_STORE_BASE_DIR . 'language/'.$dirEntry);
      if ($configXml->info->title){
        $lang['name'] = (string)$configXml->info->title;
        $lang['code'] = substr((string)$configXml->info->code,0,2);
        $lang['locale'] = substr((string)$configXml->info->code,0,2);
        $lang['currency'] = (string)$configXml->info->default_currency;
        $lang['fileName'] = str_replace(".xml","",$dirEntry);
        $languages[] = $lang;
      }
    }
    if (!empty($languages)) {
      $this->cartVars['languages'] = $languages;
    }
    if ( file_exists(M1_STORE_BASE_DIR  . 'ini.inc.php') ) {
      $conf = file_get_contents (M1_STORE_BASE_DIR . 'ini.inc.php');
      preg_match("/ini\['ver'\].*/", $conf, $match);
      if (isset($match[0]) && !empty($match[0])) {
        preg_match("/\d.*/", $match[0], $project);
          if (isset($project[0]) && !empty($project[0])) {
            $version = $project[0];
            $version = str_replace(array(" ","-","_","'",");",";",")"), "", $version);
            if ($version != '') {
              $this->cartVars['dbVersion'] = strtolower($version);
            }
          }
      } else {
        preg_match("/define\('CC_VERSION.*/", $conf, $match);
        if (isset($match[0]) && !empty($match[0])) {
          preg_match("/\d.*/", $match[0], $project);
          if (isset($project[0]) && !empty($project[0])){
            $version = $project[0];
            $version = str_replace(array(" ","-","_","'",");",";",")"), "", $version);
            if ($version != '') {
              $this->cartVars['dbVersion'] = strtolower($version);
            }
          }
        }

      }
    } elseif ( file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . 'ini.inc.php') ) {
      $conf = file_get_contents (M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . 'ini.inc.php');
      preg_match("/ini\['ver'\].*/", $conf, $match);
      if (isset($match[0]) && !empty($match[0])) {
        preg_match("/\d.*/", $match[0], $project);
        if (isset($project[0]) && !empty($project[0])) {
          $version = $project[0];
          $version = str_replace(array(" ","-","_","'",");",";",")"), "", $version);
          if ($version != '') {
            $this->cartVars['dbVersion'] = strtolower($version);
          }
        }
      } else {
        preg_match("/define\('CC_VERSION.*/", $conf, $match);
        if (isset($match[0]) && !empty($match[0])) {
          preg_match("/\d.*/", $match[0], $project);
          if (isset($project[0]) && !empty($project[0])) {
            $version = $project[0];
            $version = str_replace(array(" ","-","_","'",");",";",")"), "", $version);
            if ($version != '') {
              $this->cartVars['dbVersion'] = strtolower($version);
            }
          }
        }
      }
    }
  }
}

class M1_Config_Adapter_WebAsyst extends M1_Config_Adapter
{
  function M1_Config_Adapter_WebAsyst()
  {
    $config = simplexml_load_file(M1_STORE_BASE_DIR . 'kernel/wbs.xml');

    $dbKey = (string)$config->FRONTEND['dbkey'];

    $config = simplexml_load_file(M1_STORE_BASE_DIR . 'dblist'. '/' . strtoupper($dbKey) . '.xml');

    $host = (string)$config->DBSETTINGS['SQLSERVER'];

    $this->setHostPort($host);
    $this->Dbname = (string)$config->DBSETTINGS['DB_NAME'];
    $this->Username = (string)$config->DBSETTINGS['DB_USER'];
    $this->Password = (string)$config->DBSETTINGS['DB_PASSWORD'];

    $this->imagesDir = 'published/publicdata/'.strtoupper($dbKey).'/attachments/SC/products_pictures';
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;

    if ( isset($config->VERSIONS['SYSTEM']) ) {
      $this->cartVars['dbVersion'] = (string)$config->VERSIONS['SYSTEM'];
    }
  }

}

class M1_Config_Adapter_Squirrelcart242 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Squirrelcart242()
  {
    include_once(M1_STORE_BASE_DIR . 'squirrelcart/config.php');

    $this->setHostPort($sql_host);
    $this->Dbname      = $db;
    $this->Username    = $sql_username;
    $this->Password    = $sql_password;

    $this->imagesDir                 = $img_path;
    $this->categoriesImagesDir       = $img_path . "/categories";
    $this->productsImagesDir         = $img_path . "/products";
    $this->manufacturersImagesDir    = $img_path;

    $version = $this->getCartVersionFromDb("DB_Version", "Store_Information", "record_number = 1");
    if ( $version != '' ) {
      $this->cartVars['dbVersion'] = $version;
    }
  }
}

class M1_Config_Adapter_Opencart14 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Opencart14()
  {
    include_once( M1_STORE_BASE_DIR . "/config.php");

    if( defined('DB_HOST') ) {
      $this->setHostPort(DB_HOST);
    } else {
      $this->setHostPort(DB_HOSTNAME);
    }

    if( defined('DB_USER') ) {
      $this->Username = DB_USER;
    } else {
      $this->Username = DB_USERNAME;
    }

    $this->Password = DB_PASSWORD;

    if( defined('DB_NAME') ) {
      $this->Dbname   = DB_NAME;
    } else {
      $this->Dbname   = DB_DATABASE;
    }

    $indexFileContent = '';
    $startupFileContent = '';

    if ( file_exists(M1_STORE_BASE_DIR . "/index.php") ) {
      $indexFileContent = file_get_contents(M1_STORE_BASE_DIR . "/index.php");
    }

    if (file_exists(M1_STORE_BASE_DIR . "/system/startup.php")) {
      $startupFileContent = file_get_contents(M1_STORE_BASE_DIR . "/system/startup.php");
    }

    if ( preg_match("/define\('\VERSION\'\, \'(.+)\'\)/", $indexFileContent, $match) == 0 ) {
      preg_match("/define\('\VERSION\'\, \'(.+)\'\)/", $startupFileContent, $match);
    }      

    if ( count($match) > 0 ) {
      $this->cartVars['dbVersion'] = $match[1];
      unset($match);
    }

    $this->imagesDir              = "/image/";
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;

  }
}



class M1_Config_Adapter_Litecommerce extends M1_Config_Adapter
{
  function M1_Config_Adapter_Litecommerce()
  {
    if ((file_exists(M1_STORE_BASE_DIR .'/etc/config.php'))){
      $file = M1_STORE_BASE_DIR .'/etc/config.php';
      $this->imagesDir = "/images"; 
      $this->categoriesImagesDir    = $this->imagesDir."/category";
      $this->productsImagesDir      = $this->imagesDir."/product";
      $this->manufacturersImagesDir = $this->imagesDir;
    } elseif(file_exists(M1_STORE_BASE_DIR .'/modules/lc_connector/litecommerce/etc/config.php')) {
      $file = M1_STORE_BASE_DIR .'/modules/lc_connector/litecommerce/etc/config.php';
      $this->imagesDir = "/modules/lc_connector/litecommerce/images"; 
      $this->categoriesImagesDir    = $this->imagesDir."/category";
      $this->productsImagesDir      = $this->imagesDir."/product";
      $this->manufacturersImagesDir = $this->imagesDir;
    }

    $settings = parse_ini_file($file);
    $this->Host      = $settings['hostspec'];
    $this->setHostPort($settings['hostspec']);
    $this->Username  = $settings['username'];
    $this->Password  = $settings['password'];
    $this->Dbname    = $settings['database'];
    $this->TblPrefix = $settings['table_prefix'];

    $version = $this->getCartVersionFromDb("value", "config", "name = 'version'");	
    if ( $version != '' ) {
      $this->cartVars['dbVersion'] = $version;
    }
  }
}



class M1_Config_Adapter_Oxid extends M1_Config_Adapter
{
  function M1_Config_Adapter_Oxid()
  {
    //@include_once M1_STORE_BASE_DIR . "config.inc.php";
    $config = file_get_contents(M1_STORE_BASE_DIR . "config.inc.php");
    preg_match("/dbName(.+)?=(.+)?\'(.+)\';/", $config, $match);
    $this->Dbname   = $match[3];
    preg_match("/dbUser(.+)?=(.+)?\'(.+)\';/", $config, $match);
    $this->Username = $match[3];
    preg_match("/dbPwd(.+)?=(.+)?\'(.+)\';/", $config, $match);
    $this->Password = isset($match[3])?$match[3]:'';
    preg_match("/dbHost(.+)?=(.+)?\'(.*)\';/", $config, $match);
    $this->setHostPort($match[3]);

    //check about last slash
    $this->imagesDir = "out/pictures/";
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;

    //add key for decoding config values in oxid db
    //check slash
    $key_config_file = file_get_contents(M1_STORE_BASE_DIR .'/core/oxconfk.php');
    preg_match("/sConfigKey(.+)?=(.+)?\"(.+)?\";/", $key_config_file, $match);
    $this->cartVars['sConfigKey'] = $match[3];
    $version = $this->getCartVersionFromDb("OXVERSION", "oxshops", "OXACTIVE=1 LIMIT 1" );
    if ( $version != '' ) {
      $this->cartVars['dbVersion'] = $version;
    } 
  }
}



class M1_Config_Adapter_XtcommerceVeyton extends M1_Config_Adapter
{
  function M1_Config_Adapter_XtcommerceVeyton()
  {
    define('_VALID_CALL','TRUE');
    define('_SRV_WEBROOT','TRUE');
    require_once M1_STORE_BASE_DIR
                . 'conf'
                . DIRECTORY_SEPARATOR
                . 'config.php';

    require_once M1_STORE_BASE_DIR
                . 'conf'
                . DIRECTORY_SEPARATOR
                . 'paths.php';

    $this->setHostPort(_SYSTEM_DATABASE_HOST);
    $this->Dbname = _SYSTEM_DATABASE_DATABASE;
    $this->Username = _SYSTEM_DATABASE_USER;
    $this->Password = _SYSTEM_DATABASE_PWD;
    $this->imagesDir = _SRV_WEB_IMAGES;
    $this->TblPrefix = DB_PREFIX . "_";

    $version = $this->getCartVersionFromDb("config_value", "config", "config_key = '_SYSTEM_VERSION'");
    if ( $version != '' ) {
      $this->cartVars['dbVersion'] = $version;
    }

    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;
  }
}


class M1_Config_Adapter_SSPremium extends M1_Config_Adapter
{
  function M1_Config_Adapter_SSPremium()
  {
    if ( file_exists(M1_STORE_BASE_DIR . 'cfg/connect.inc.php') ){
      $config = file_get_contents(M1_STORE_BASE_DIR . 'cfg/connect.inc.php');
      preg_match("/define\(\'DB_NAME\', \'(.+)\'\);/", $config, $match);
      $this->Dbname   = $match[1];
      preg_match("/define\(\'DB_USER\', \'(.+)\'\);/", $config, $match);
      $this->Username = $match[1];
      preg_match("/define\(\'DB_PASS\', \'(.*)\'\);/", $config, $match);
      $this->Password = $match[1];
      preg_match("/define\(\'DB_HOST\', \'(.+)\'\);/", $config, $match);
      $this->setHostPort( $match[1] );

      $this->imagesDir = "products_pictures/";
      $this->categoriesImagesDir    = $this->imagesDir;
      $this->productsImagesDir      = $this->imagesDir;
      $this->manufacturersImagesDir = $this->imagesDir;

      $version = $this->getCartVersionFromDb("value", "SS_system", "varName = 'version_number'");
      if ( $version != '' ) {
        $this->cartVars['dbVersion'] = $version;
      }
    } else {
      $config = include M1_STORE_BASE_DIR . "wa-config/db.php";
      $this->Dbname   = $config['default']['database'];
      $this->Username = $config['default']['user'];
      $this->Password = $config['default']['password'];
      $this->setHostPort($config['default']['host']);

      $this->imagesDir = "products_pictures/";
      $this->categoriesImagesDir    = $this->imagesDir;
      $this->productsImagesDir      = $this->imagesDir;
      $this->manufacturersImagesDir = $this->imagesDir;
      $this->cartVars['dbVersion'] = '5.0';
    }

  }

}

class M1_Config_Adapter_Virtuemart113 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Virtuemart113()
  {
    require_once M1_STORE_BASE_DIR . "/configuration.php";

    if (class_exists("JConfig")) {

      $jconfig = new JConfig();

      $this->setHostPort($jconfig->host);
      $this->Dbname   = $jconfig->db;
      $this->Username = $jconfig->user;
      $this->Password = $jconfig->password;

    } else {

      $this->setHostPort($mosConfig_host);
      $this->Dbname   = $mosConfig_db;
      $this->Username = $mosConfig_user;
      $this->Password = $mosConfig_password;
    }

    if ( file_exists(M1_STORE_BASE_DIR . "/administrator/components/com_virtuemart/version.php") ) {
      $ver = file_get_contents(M1_STORE_BASE_DIR . "/administrator/components/com_virtuemart/version.php");
      if (preg_match('/\$RELEASE.+\'(.+)\'/', $ver, $match) != 0) {
        $this->cartVars['dbVersion'] = $match[1];
        unset($match);
      }
    }

    $this->imagesDir = "components/com_virtuemart/shop_image";
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;

    if ( is_dir( M1_STORE_BASE_DIR . 'images/stories/virtuemart/product' ) ) {
      $this->imagesDir = 'images/stories/virtuemart';
      $this->productsImagesDir      = $this->imagesDir . '/product';
      $this->categoriesImagesDir    = $this->imagesDir . '/category';
      $this->manufacturersImagesDir  = $this->imagesDir . '/manufacturer';
    }

  }
}


class M1_Config_Adapter_Hhgmultistore extends M1_Config_Adapter
{
  function M1_Config_Adapter_Hhgmultistore()
  {
    define('SITE_PATH','');
    define('WEB_PATH','');
    require_once M1_STORE_BASE_DIR . "core/config/configure.php";
    require_once M1_STORE_BASE_DIR . "core/config/paths.php";

    $baseDir = "/store_files/1/";
    $this->imagesDir = $baseDir . DIR_WS_IMAGES;

    $this->categoriesImagesDir    = $baseDir . DIR_WS_CATEGORIE_IMAGES;
    $this->productsImagesDirs['info']  = $baseDir . DIR_WS_PRODUCT_INFO_IMAGES;
    $this->productsImagesDirs['org']   = $baseDir . DIR_WS_PRODUCT_ORG_IMAGES;
    $this->productsImagesDirs['thumb'] = $baseDir . DIR_WS_PRODUCT_THUMBNAIL_IMAGES;
    $this->productsImagesDirs['popup'] = $baseDir . DIR_WS_PRODUCT_POPUP_IMAGES;

    $this->manufacturersImagesDirs['img'] = $baseDir . DIR_WS_MANUFACTURERS_IMAGES;
    $this->manufacturersImagesDirs['org'] = $baseDir . DIR_WS_MANUFACTURERS_ORG_IMAGES;

    $this->Host     = DB_SERVER;
    $this->Username = DB_SERVER_USERNAME;
    $this->Password = DB_SERVER_PASSWORD;
    $this->Dbname   = DB_DATABASE;

    if ( file_exists(M1_STORE_BASE_DIR . "/core/config/conf.hhg_startup.php") ) {
      $ver = file_get_contents(M1_STORE_BASE_DIR . "/core/config/conf.hhg_startup.php");
      if (preg_match('/PROJECT_VERSION.+\((.+)\)\'\)/', $ver, $match) != 0) {
        $this->cartVars['dbVersion'] = $match[1];
        unset($match);
      }
    }
  }
}


class M1_Config_Adapter_Magento1212 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Magento1212()
  {
    /**
     * @var SimpleXMLElement
     */
    $config = simplexml_load_file(M1_STORE_BASE_DIR . 'app/etc/local.xml');
    $statuses = simplexml_load_file(M1_STORE_BASE_DIR . 'app/code/core/Mage/Sales/etc/config.xml');

    $version =  $statuses->modules->Mage_Sales->version;

    $result = array();

    if( version_compare($version, '1.4.0.25') < 0 ) {
      $statuses = $statuses->global->sales->order->statuses;
      foreach ( $statuses->children() as $status ) {
        $result[$status->getName()] = (string) $status->label;
      }
    }

    if ( file_exists(M1_STORE_BASE_DIR . "app/Mage.php") ) {
      $ver = file_get_contents(M1_STORE_BASE_DIR . "app/Mage.php");
      if ( preg_match("/getVersionInfo[^}]+\'major\' *=> *\'(\d+)\'[^}]+\'minor\' *=> *\'(\d+)\'[^}]+\'revision\' *=> *\'(\d+)\'[^}]+\'patch\' *=> *\'(\d+)\'[^}]+}/s", $ver, $match) == 1 ) {
        $mageVersion = $match[1] . '.' . $match[2] . '.' . $match[3] . '.' . $match[4];
        $this->cartVars['dbVersion'] = $mageVersion;
        unset($match);
      }
    }

    $this->cartVars['orderStatus'] = $result;
    $this->cartVars['AdminUrl']    = (string)$config->admin->routers->adminhtml->args->frontName;

    $this->setHostPort((string) $config->global->resources->default_setup->connection->host);
    $this->Username = (string) $config->global->resources->default_setup->connection->username;
    $this->Dbname   = (string) $config->global->resources->default_setup->connection->dbname;
    $this->Password = (string) $config->global->resources->default_setup->connection->password;

    $this->imagesDir              = 'media/';
    $this->categoriesImagesDir    = $this->imagesDir . "catalog/category/";
    $this->productsImagesDir      = $this->imagesDir . "catalog/product/";
    $this->manufacturersImagesDir = $this->imagesDir;
    @unlink(M1_STORE_BASE_DIR . 'app/etc/use_cache.ser');
  }
}

class M1_Config_Adapter_Interspire extends M1_Config_Adapter
{
  function M1_Config_Adapter_Interspire()
  {
    require_once M1_STORE_BASE_DIR . "config/config.php";

    $this->setHostPort($GLOBALS['ISC_CFG']["dbServer"]);
    $this->Username = $GLOBALS['ISC_CFG']["dbUser"];
    $this->Password = $GLOBALS['ISC_CFG']["dbPass"];
    $this->Dbname   = $GLOBALS['ISC_CFG']["dbDatabase"];

    $this->imagesDir = $GLOBALS['ISC_CFG']["ImageDirectory"];
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;

    define('DEFAULT_LANGUAGE_ISO2',$GLOBALS['ISC_CFG']["Language"]);

    $version = $this->getCartVersionFromDb("database_version", $GLOBALS['ISC_CFG']["tablePrefix"] . "config", '1');
    if ( $version != '' ) {
      $this->cartVars['dbVersion'] = $version;
    }
  }
}

class M1_Config_Adapter_Pinnacle361 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Pinnacle361()
  {
    include_once M1_STORE_BASE_DIR . 'content/engine/engine_config.php';

    $this->imagesDir = 'images/';
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;

    //$this->Host = DB_HOST;
    $this->setHostPort(DB_HOST);
    $this->Dbname = DB_NAME;
    $this->Username = DB_USER;
    $this->Password = DB_PASSWORD;

    $version = $this->getCartVersionFromDb("value", (defined('DB_PREFIX') ? DB_PREFIX : '') . "settings", "name = 'AppVer'");
    if ( $version != '' ) {
      $this->cartVars['dbVersion'] = $version;
    }
  }
}



class M1_Config_Adapter_Oscommerce22ms2 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Oscommerce22ms2()
  {
    $cur_dir = getcwd();

    chdir(M1_STORE_BASE_DIR);

    @require_once M1_STORE_BASE_DIR
                . "includes" . DIRECTORY_SEPARATOR
                . "configure.php";

    chdir($cur_dir);

    $this->imagesDir = DIR_WS_IMAGES;
    
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    if ( defined('DIR_WS_PRODUCT_IMAGES') ) {
      $this->productsImagesDir = DIR_WS_PRODUCT_IMAGES;
    }
    if ( defined('DIR_WS_ORIGINAL_IMAGES') ) {
      $this->productsImagesDir = DIR_WS_ORIGINAL_IMAGES;
    }
    $this->manufacturersImagesDir = $this->imagesDir;

    //$this->Host      = DB_SERVER;
    $this->setHostPort(DB_SERVER);
    $this->Username  = DB_SERVER_USERNAME;
    $this->Password  = DB_SERVER_PASSWORD;
    $this->Dbname    = DB_DATABASE;
    chdir(M1_STORE_BASE_DIR);
    if ( file_exists(M1_STORE_BASE_DIR  . "includes" . DIRECTORY_SEPARATOR . 'application_top.php') ) {
      $conf = file_get_contents (M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "application_top.php");
      preg_match("/define\('PROJECT_VERSION.*/", $conf, $match);
      if (isset($match[0]) && !empty($match[0])) {
        preg_match("/\d.*/", $match[0], $project);
        if (isset($project[0]) && !empty($project[0])) {
          $version = $project[0];
          $version = str_replace(array(" ","-","_","'",");"), "", $version);
           if ($version != '') {
             $this->cartVars['dbVersion'] = strtolower($version);
           }
         }
      } else {
        //if another oscommerce based cart
        if ( file_exists(M1_STORE_BASE_DIR  . "includes" . DIRECTORY_SEPARATOR . 'version.php') ) {
          @require_once M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "version.php";
            if (defined('PROJECT_VERSION') && PROJECT_VERSION != '' ) {
              $version = PROJECT_VERSION;
              preg_match("/\d.*/", $version, $vers);
              if (isset($vers[0]) && !empty($vers[0])) {
                $version = $vers[0];
                $version = str_replace(array(" ","-","_"), "", $version);
                if ($version != '') {
                  $this->cartVars['dbVersion'] = strtolower($version);
                }
              }
              //if zen_cart
            } else {
              if (defined('PROJECT_VERSION_MAJOR') && PROJECT_VERSION_MAJOR != '' ) {
                $this->cartVars['dbVersion'] = PROJECT_VERSION_MAJOR;
              }
              if (defined('PROJECT_VERSION_MINOR') && PROJECT_VERSION_MINOR != '' ) {
                $this->cartVars['dbVersion'] .= '.' . PROJECT_VERSION_MINOR;
              }
            }
        }
      }
    }
    chdir($cur_dir);
  }
}



class M1_Config_Adapter_Tomatocart extends M1_Config_Adapter
{
  function M1_Config_Adapter_Tomatocart()
  {
    $config = file_get_contents(M1_STORE_BASE_DIR . "includes/configure.php");
    preg_match("/define\(\'DB_DATABASE\', \'(.+)\'\);/", $config, $match);
    $this->Dbname   = $match[1];
    preg_match("/define\(\'DB_SERVER_USERNAME\', \'(.+)\'\);/", $config, $match);
    $this->Username = $match[1];
    preg_match("/define\(\'DB_SERVER_PASSWORD\', \'(.*)\'\);/", $config, $match);
    $this->Password = $match[1];
    preg_match("/define\(\'DB_SERVER\', \'(.+)\'\);/", $config, $match);
    $this->setHostPort( $match[1] );

    preg_match("/define\(\'DIR_WS_IMAGES\', \'(.+)\'\);/", $config, $match);
    $this->imagesDir = $match[1];

    $this->categoriesImagesDir    = $this->imagesDir.'categories/';
    $this->productsImagesDir      = $this->imagesDir.'products/';
    $this->manufacturersImagesDir = $this->imagesDir . 'manufacturers/';
    if ( file_exists(M1_STORE_BASE_DIR  . "includes" . DIRECTORY_SEPARATOR . 'application_top.php') ) {
      $conf = file_get_contents (M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "application_top.php");
      preg_match("/define\('PROJECT_VERSION.*/", $conf, $match);

      if (isset($match[0]) && !empty($match[0])) {
        preg_match("/\d.*/", $match[0], $project);
        if (isset($project[0]) && !empty($project[0])) {
          $version = $project[0];
          $version = str_replace(array(" ","-","_","'",");"), "", $version);
          if ($version != '') {
            $this->cartVars['dbVersion'] = strtolower($version);
          }
        }
      } else {
        //if another version
        if ( file_exists(M1_STORE_BASE_DIR  . "includes" . DIRECTORY_SEPARATOR . 'version.php') ) {
          @require_once M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "version.php";
          if (defined('PROJECT_VERSION') && PROJECT_VERSION != '' ) {
            $version = PROJECT_VERSION;
            preg_match("/\d.*/", $version, $vers);
            if (isset($vers[0]) && !empty($vers[0])) {
              $version = $vers[0];
              $version = str_replace(array(" ","-","_"), "", $version);
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



class M1_Config_Adapter_Sunshop4 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Sunshop4()
  {
    @require_once M1_STORE_BASE_DIR
                . "include" . DIRECTORY_SEPARATOR
                . "config.php";

    $this->imagesDir = "images/products/";

    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;

    if ( defined('ADMIN_DIR') ) {
      $this->cartVars['AdminUrl'] = ADMIN_DIR;
    }

    $this->setHostPort($servername);
    $this->Username  = $dbusername;
    $this->Password  = $dbpassword;
    $this->Dbname    = $dbname;

    if (isset($dbprefix)) {
      $this->TblPrefix = $dbprefix;
    }

    $version = $this->getCartVersionFromDb("value", "settings", "name = 'version'");
    if ( $version != '' ) {
      $this->cartVars['dbVersion'] = $version;
    }

  }
}



class miSettings {
  var $arr;

  function singleton() {
    static $instance = null;
    if ( $instance == null ) {
      $instance = new miSettings();
    }
    return $instance;
  }

  function setArray($arr)
  {
    $this->arr[] = $arr;
  }

  function getArray()
  {
    return $this->arr;
  }

}

class M1_Config_Adapter_Summercart3 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Summercart3()
  {
    @include_once M1_STORE_BASE_DIR . "include/miphpf/Config.php";

    $instance = miSettings::singleton();

    $data = $instance->getArray();

    $this->setHostPort($data[0]['MI_DEFAULT_DB_HOST']);
    $this->Dbname   = $data[0]['MI_DEFAULT_DB_NAME'];
    $this->Username = $data[0]['MI_DEFAULT_DB_USER'];
    $this->Password = $data[0]['MI_DEFAULT_DB_PASS'];
    $this->imagesDir = "/userfiles/";

    $this->categoriesImagesDir    = $this->imagesDir . "categoryimages";
    $this->productsImagesDir      = $this->imagesDir . "productimages";
    $this->manufacturersImagesDir = $this->imagesDir . "manufacturer";

    if ( file_exists(M1_STORE_BASE_DIR . "/include/VERSION") ) {
      $indexFileContent = file_get_contents(M1_STORE_BASE_DIR . "/include/VERSION");
      $this->cartVars['dbVersion'] = trim($indexFileContent);
    }

  }
}



class M1_Config_Adapter_Oscommerce3 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Oscommerce3()
  {
    $file = M1_STORE_BASE_DIR .'/osCommerce/OM/Config/settings.ini';
    $settings=parse_ini_file($file);
    $this->imagesDir = "/public/"; 
    $this->categoriesImagesDir    = $this->imagesDir."/categories";
    $this->productsImagesDir      = $this->imagesDir."/products";
    $this->manufacturersImagesDir = $this->imagesDir;

    $this->Host      = $settings['db_server'];
    $this->setHostPort($settings['db_server_port']);
    $this->Username  = $settings['db_server_username'];
    $this->Password  = $settings['db_server_password'];
    $this->Dbname    = $settings['db_database'];
  }
}



class M1_Config_Adapter_Prestashop15 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Prestashop15()
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
        if ((strpos($line, '_DB_') !== false) || (strpos($line, '_PS_IMG_DIR_') !== false) || (strpos($line, '_PS_VERSION_') !== false)) {
          $execute .= " " . $line;
        }
      }
    }

    define( '_PS_ROOT_DIR_', M1_STORE_BASE_DIR );
    eval($execute);

    $this->setHostPort(_DB_SERVER_);
    $this->Dbname   = _DB_NAME_;
    $this->Username = _DB_USER_;
    $this->Password = _DB_PASSWD_;

    if (defined('_PS_IMG_DIR_') && defined('_PS_ROOT_DIR_')) {

      preg_match("/(\/\w+\/)$/i", _PS_IMG_DIR_ ,$m);
      $this->imagesDir = $m[1];

    } else {
      $this->imagesDir = "/img/";
    }

    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;

    if (defined('_PS_VERSION_')) {
      $this->cartVars['dbVersion'] = _PS_VERSION_;
    }
  }
}




class M1_Config_Adapter_Gambio extends M1_Config_Adapter
{
  function M1_Config_Adapter_Gambio()
  {
    $cur_dir = getcwd();

    chdir(M1_STORE_BASE_DIR);

    @require_once M1_STORE_BASE_DIR . "includes/configure.php";

    chdir($cur_dir);

    $this->imagesDir = DIR_WS_IMAGES;

    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    if (defined('DIR_WS_PRODUCT_IMAGES')) {
      $this->productsImagesDir = DIR_WS_PRODUCT_IMAGES;
    }
    if (defined('DIR_WS_ORIGINAL_IMAGES')) {
      $this->productsImagesDir = DIR_WS_ORIGINAL_IMAGES;
    }
    $this->manufacturersImagesDir = $this->imagesDir;

    $this->Host      = DB_SERVER;
    //$this->setHostPort(DB_SERVER);
    $this->Username  = DB_SERVER_USERNAME;
    $this->Password  = DB_SERVER_PASSWORD;
    $this->Dbname    = DB_DATABASE;

    chdir(M1_STORE_BASE_DIR);
    if (file_exists(M1_STORE_BASE_DIR  . "includes" . DIRECTORY_SEPARATOR . 'application_top.php')) {
      $conf = file_get_contents (M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "application_top.php");
      preg_match("/define\('PROJECT_VERSION.*/", $conf, $match);
      if (isset($match[0]) && !empty($match[0])) {
        preg_match("/\d.*/", $match[0], $project);
        if (isset($project[0]) && !empty($project[0])) {
          $version = $project[0];
          $version = str_replace(array(" ","-","_","'",");"), "", $version);
          if ($version != '') {
            $this->cartVars['dbVersion'] = strtolower($version);
          }
        }
      } else {
        //if another oscommerce based cart
        if ( file_exists(M1_STORE_BASE_DIR . DIRECTORY_SEPARATOR . 'version_info.php') ) {
          @require_once M1_STORE_BASE_DIR . DIRECTORY_SEPARATOR . "version_info.php";
          if (defined('PROJECT_VERSION') && PROJECT_VERSION != '' ) {
            $version = PROJECT_VERSION;
            preg_match("/\d.*/", $version, $vers);
            if (isset($vers[0]) && !empty($vers[0])) {
              $version = $vers[0];
              $version = str_replace(array(" ","-","_"), "", $version);
              if ($version != '') {
                $this->cartVars['dbVersion'] = strtolower($version);
              }
            }
            //if zen_cart
          } else {
            if (defined('PROJECT_VERSION_MAJOR') && PROJECT_VERSION_MAJOR != '' ) {
              $this->cartVars['dbVersion'] = PROJECT_VERSION_MAJOR;
            }
            if (defined('PROJECT_VERSION_MINOR') && PROJECT_VERSION_MINOR != '' ) {
              $this->cartVars['dbVersion'] .= '.' . PROJECT_VERSION_MINOR;
            }
          }
        }
      }
    }
    chdir($cur_dir);
  }
}



class M1_Config_Adapter_Shopware extends M1_Config_Adapter
{
  function M1_Config_Adapter_Shopware()
  {
    $configs = include(M1_STORE_BASE_DIR . "config.php");
    $this->setHostPort($configs['db']['host']);
    $this->Username =  $configs['db']['username'];
    $this->Password =  $configs['db']['password'];
    $this->Dbname   =  $configs['db']['dbname'];
  }
}

class M1_Config_Adapter_AceShop extends M1_Config_Adapter
{
  function M1_Config_Adapter_AceShop()
  {
    require_once M1_STORE_BASE_DIR . "/configuration.php";

    if (class_exists("JConfig")) {

      $jconfig = new JConfig();

      $this->setHostPort($jconfig->host);
      $this->Dbname   = $jconfig->db;
      $this->Username = $jconfig->user;
      $this->Password = $jconfig->password;

    } else {

      $this->setHostPort($mosConfig_host);
      $this->Dbname   = $mosConfig_db;
      $this->Username = $mosConfig_user;
      $this->Password = $mosConfig_password;
    }


    $this->imagesDir = "components/com_aceshop/opencart/image/";
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;
  }
}


class M1_Config_Adapter_Cscart203 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Cscart203()
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
    if( isset( $db_host ) && isset($db_name) && isset($db_user) && isset($db_password) ) {
      $this->setHostPort($db_host);
      $this->Dbname = $db_name;
      $this->Username = $db_user;
      $this->Password = $db_password;
      $this->imagesDir = str_replace(M1_STORE_BASE_DIR, '', IMAGES_STORAGE_DIR );
    } else {

      $this->setHostPort($config['db_host']);
      $this->Dbname = $config['db_name'];
      $this->Username = $config['db_user'];
      $this->Password = $config['db_password'];
      $this->imagesDir = str_replace(M1_STORE_BASE_DIR, '', DIR_IMAGES);
    }

    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;

    if( defined('MAX_FILES_IN_DIR') ) {
      $this->cartVars['cs_max_files_in_dir'] = MAX_FILES_IN_DIR;
    }

    if( defined('PRODUCT_VERSION') ) {
      $this->cartVars['dbVersion'] = PRODUCT_VERSION;
    }
  }
}


class M1_Config_Adapter_WPecommerce extends M1_Config_Adapter
{
  function M1_Config_Adapter_WPecommerce()
  {
    //@include_once M1_STORE_BASE_DIR . "wp-config.php";
    $config = file_get_contents(M1_STORE_BASE_DIR . "wp-config.php");
    preg_match("/define\(\'DB_NAME\', \'(.+)\'\);/", $config, $match);
    $this->Dbname   = $match[1];
    preg_match("/define\(\'DB_USER\', \'(.+)\'\);/", $config, $match);
    $this->Username = $match[1];
    preg_match("/define\(\'DB_PASSWORD\', \'(.*)\'\);/", $config, $match);
    $this->Password = $match[1];
    preg_match("/define\(\'DB_HOST\', \'(.+)\'\);/", $config, $match);
    $this->setHostPort( $match[1] );
    preg_match("/(table_prefix)(.*)(')(.*)(')(.*)/", $config, $match);
    $this->TblPrefix = $match[4];
    $version = $this->getCartVersionFromDb("option_value", "options", "option_name = 'wpsc_version'");
    if ( $version != '' ) {
      $this->cartVars['dbVersion'] = $version;
    } else {
       if ( file_exists(M1_STORE_BASE_DIR . "wp-content".DIRECTORY_SEPARATOR."plugins".DIRECTORY_SEPARATOR."wp-shopping-cart".DIRECTORY_SEPARATOR."wp-shopping-cart.php")  ) {
         $conf = file_get_contents (M1_STORE_BASE_DIR . "wp-content".DIRECTORY_SEPARATOR."plugins".DIRECTORY_SEPARATOR."wp-shopping-cart".DIRECTORY_SEPARATOR."wp-shopping-cart.php");
         preg_match("/define\('WPSC_VERSION.*/", $conf, $match);
         if (isset($match[0]) && !empty($match[0])) {
           preg_match("/\d.*/", $match[0], $project);
           if (isset($project[0]) && !empty($project[0])) {
             $version = $project[0];
             $version = str_replace(array(" ","-","_","'",");",")",";"), "", $version);
             if ($version != '') {
               $this->cartVars['dbVersion'] = strtolower($version);
             }
           }
         }
       }
    }
    if ( file_exists(M1_STORE_BASE_DIR . "wp-content/plugins/shopp/Shopp.php") || file_exists(M1_STORE_BASE_DIR . "wp-content/plugins/wp-e-commerce/editor.php") ) {
      $this->imagesDir = "wp-content/uploads/wpsc/";
      $this->categoriesImagesDir    = $this->imagesDir.'category_images/';
      $this->productsImagesDir      = $this->imagesDir.'product_images/';
      $this->manufacturersImagesDir = $this->imagesDir;
    } elseif ( file_exists(M1_STORE_BASE_DIR . "wp-content/plugins/wp-e-commerce/wp-shopping-cart.php") ) {
      $this->imagesDir = "wp-content/uploads/";
      $this->categoriesImagesDir    = $this->imagesDir."wpsc/category_images/";
      $this->productsImagesDir      = $this->imagesDir;
      $this->manufacturersImagesDir = $this->imagesDir;
    } else {
      $this->imagesDir = "images/";
      $this->categoriesImagesDir    = $this->imagesDir;
      $this->productsImagesDir      = $this->imagesDir;
      $this->manufacturersImagesDir = $this->imagesDir;
    }
  }
}



class M1_Config_Adapter_LemonStand extends M1_Config_Adapter
{
  function M1_Config_Adapter_LemonStand()
  {
    include (M1_STORE_BASE_DIR . 'phproad/system/phpr.php');
    include (M1_STORE_BASE_DIR . 'phproad/modules/phpr/classes/phpr_securityframework.php');

    define('PATH_APP','');


    if(phpversion() > 5)
    {
      eval ('Phpr::$config = new MockConfig();	  	  
      Phpr::$config->set("SECURE_CONFIG_PATH", M1_STORE_BASE_DIR . "config/config.dat");
      $framework = Phpr_SecurityFramework::create();');
    }

    $config_content = $framework->get_config_content();

    $this->setHostPort($config_content['mysql_params']['host']);
    $this->Dbname   = $config_content['mysql_params']['database'];
    $this->Username = $config_content['mysql_params']['user'];
    $this->Password = $config_content['mysql_params']['password'];

    $this->categoriesImagesDir    = '/uploaded/thumbnails/';
    $this->productsImagesDir      = '/uploaded/';
    $this->manufacturersImagesDir = '/uploaded/thumbnails/';

    $version = $this->getCartVersionFromDb("version_str", "core_versions", "moduleId = 'shop'");
    $this->cartVars['dbVersion'] = $version;

  }
}

class MockConfig {
  var $_data = array();
  function set($key, $value)
  {
    $this->_data[$key] = $value;
  }
  
  function get($key, $default = 'default')
  {
    return isset($this->_data[$key]) ? $this->_data[$key] : $default;
  }
}

class M1_Config_Adapter_DrupalCommerce extends M1_Config_Adapter
{

  function M1_Config_Adapter_DrupalCommerce()
  {
    @include_once M1_STORE_BASE_DIR . "sites/default/settings.php";

    $url = $databases['default']['default'];

    $url['username'] = urldecode($url['username']);
    $url['password'] = isset($url['password']) ? urldecode($url['password']) : '';
    $url['host'] = urldecode($url['host']);
    $url['database'] = urldecode($url['database']);
    if (isset($url['port'])) {
      $url['host'] = $url['host'] .':'. $url['port'];
    }

    $this->setHostPort( $url['host'] );
    $this->Dbname   = ltrim( $url['database'], '/' );
    $this->Username = $url['username'];
    $this->Password = $url['password'];

    $this->imagesDir = "/sites/default/files/";
    if( !file_exists( M1_STORE_BASE_DIR . $this->imagesDir ) ) {
      $this->imagesDir = "/files";
    }


    $fileInfo = M1_STORE_BASE_DIR . "/sites/all/modules/commerce/commerce.info";
    if ( file_exists( $fileInfo ) ) {
      $str = file_get_contents( $fileInfo );
      if ( preg_match('/version\s+=\s+".+-(.+)"/', $str, $match) != 0 ) {
        $this->cartVars['dbVersion'] = $match[1];
        unset($match);
      }
    }

    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    $this->manufacturersImagesDir = $this->imagesDir;


  }
}

class M1_Config_Adapter_SSFree extends M1_Config_Adapter
{
  function M1_Config_Adapter_SSFree()
  {
    $config = file_get_contents(M1_STORE_BASE_DIR . 'cfg/connect.inc.php');
    preg_match("/define\(\'DB_NAME\', \'(.+)\'\);/", $config, $match);
    $this->Dbname   = $match[1];
    preg_match("/define\(\'DB_USER\', \'(.+)\'\);/", $config, $match);
    $this->Username = $match[1];
    preg_match("/define\(\'DB_PASS\', \'(.*)\'\);/", $config, $match);
    $this->Password = $match[1];
    preg_match("/define\(\'DB_HOST\', \'(.+)\'\);/", $config, $match);
    $this->setHostPort( $match[1] );

    $this->imagesDir = "products_pictures/";
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
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

class M1_Config_Adapter_Zencart137 extends M1_Config_Adapter
{
  function M1_Config_Adapter_Zencart137()
  {
    $cur_dir = getcwd();

    chdir(M1_STORE_BASE_DIR);

    @require_once M1_STORE_BASE_DIR
                . "includes" . DIRECTORY_SEPARATOR
                . "configure.php";

    chdir($cur_dir);

    $this->imagesDir = DIR_WS_IMAGES;
    
    $this->categoriesImagesDir    = $this->imagesDir;
    $this->productsImagesDir      = $this->imagesDir;
    if ( defined('DIR_WS_PRODUCT_IMAGES') ) {
      $this->productsImagesDir = DIR_WS_PRODUCT_IMAGES;
    }
    if ( defined('DIR_WS_ORIGINAL_IMAGES') ) {
      $this->productsImagesDir = DIR_WS_ORIGINAL_IMAGES;
    }
    $this->manufacturersImagesDir = $this->imagesDir;

    //$this->Host      = DB_SERVER;
    $this->setHostPort(DB_SERVER);
    $this->Username  = DB_SERVER_USERNAME;
    $this->Password  = DB_SERVER_PASSWORD;
    $this->Dbname    = DB_DATABASE;
    if ( file_exists(M1_STORE_BASE_DIR  . "includes" . DIRECTORY_SEPARATOR . 'version.php') ) {
       @require_once M1_STORE_BASE_DIR
              . "includes" . DIRECTORY_SEPARATOR
              . "version.php";
      $major = PROJECT_VERSION_MAJOR;
      $minor = PROJECT_VERSION_MINOR;
      if (defined('EXPECTED_DATABASE_VERSION_MAJOR') && EXPECTED_DATABASE_VERSION_MAJOR != '' ) {
        $major = EXPECTED_DATABASE_VERSION_MAJOR;
      }
      if (defined('EXPECTED_DATABASE_VERSION_MINOR') && EXPECTED_DATABASE_VERSION_MINOR != '' ) {
        $minor = EXPECTED_DATABASE_VERSION_MINOR;
      }

      if ( $major != '' && $minor != '' ) {
        $this->cartVars['dbVersion'] = $major.'.'.$minor;
      }

    }
  }
}




class M1_Config_Adapter
{
  var $Host                = 'localhost';
  var $Port                = null;//"3306";
  var $Username            = 'root';
  var $Password            = '';
  var $Dbname              = '';
  var $TblPrefix           = '';

  var $cartType                 = 'Oscommerce22ms2';
  var $imagesDir                = '';
  var $categoriesImagesDir      = '';
  var $productsImagesDir        = '';
  var $manufacturersImagesDir   = '';
  var $categoriesImagesDirs     = '';
  var $productsImagesDirs       = '';
  var $manufacturersImagesDirs  = '';

  var $languages   = array();
  var $cartVars    = array();

  function create()
  {
    if (isset($_GET["action"]) && $_GET["action"] == "update") {
      return null;
    }

    $cartType = $this->_detectCartType();
    $className = "M1_Config_Adapter_" . $cartType;

    $obj = new $className();
    $obj->cartType = $cartType;

    return $obj;
  }

  function _detectCartType()
  {
    // Zencart137
    if (file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "configure.php")
      && file_exists(M1_STORE_BASE_DIR . "ipn_main_handler.php")
    ) {
      return "Zencart137";
    }

    //osCommerce
    /* is if not tomatocart */
    if (file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "configure.php")
      && !file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "toc_constants.php")
    ) {
      return "Oscommerce22ms2";
    }

    if (file_exists(M1_STORE_BASE_DIR . "/includes/configure.php")) {
      return "Gambio";
    }

    //JooCart
    if (file_exists(M1_STORE_BASE_DIR . '/components/com_opencart/opencart.php')) {
      return 'JooCart';
    }

    //ACEShop
    if (file_exists(M1_STORE_BASE_DIR . '/components/com_aceshop/aceshop.php')) {
      return 'AceShop';
    }

    //Litecommerce
    if ((file_exists(M1_STORE_BASE_DIR .'/etc/config.php'))
      || (file_exists(M1_STORE_BASE_DIR .'/modules/lc_connector/litecommerce/etc/config.php'))
    ) {
      return "Litecommerce";
    }

    //Prestashop11
    if (file_exists(M1_STORE_BASE_DIR . "config/config.inc.php")) {
      if (file_exists(M1_STORE_BASE_DIR . "cache/class_index.php")) {
        return "Prestashop15";
      }
      return "Prestashop11";
    }

    /*
     * Virtuemart113
     */
    if (file_exists(M1_STORE_BASE_DIR . "configuration.php")) {
      return "Virtuemart113";
    }

    /*
     * Pinnacle361
     */
    if (file_exists(M1_STORE_BASE_DIR . 'content/engine/engine_config.php')) {
      return "Pinnacle361";
    }

    // Magento1212, we can be sure that PHP is >= 5.2.0
    if (file_exists(M1_STORE_BASE_DIR . 'app/etc/local.xml')) {
      return "Magento1212";
    }

    //Cubecart3
    if (file_exists(M1_STORE_BASE_DIR . 'includes/global.inc.php')) {
      return "Cubecart";
    }

    //Cscart203 - 3
    if (file_exists(M1_STORE_BASE_DIR . "config.local.php") || file_exists(M1_STORE_BASE_DIR . "partner.php")) {
      return "Cscart203";
    }

    //Opencart14
    if ((file_exists(M1_STORE_BASE_DIR . "system/startup.php")
        || (file_exists(M1_STORE_BASE_DIR . "common.php"))
        || (file_exists(M1_STORE_BASE_DIR . "library/locator.php"))
      ) && file_exists(M1_STORE_BASE_DIR . "config.php")
    ) {
      return "Opencart14";
    }

    //Shopware
    if (file_exists(M1_STORE_BASE_DIR . "config.php") && file_exists(M1_STORE_BASE_DIR . "shopware.php")) {
      return "Shopware";
    }

    //XCart
    if (file_exists(M1_STORE_BASE_DIR . "config.php")) {
      return "XCart";
    }

    //LemonStand
    if (file_exists(M1_STORE_BASE_DIR . "boot.php")) {
      return "LemonStand";
    }

    //Interspire
    if (file_exists(M1_STORE_BASE_DIR . "config/config.php")) {
      return "Interspire";
    }

    //Squirrelcart242
    if (file_exists(M1_STORE_BASE_DIR . 'squirrelcart/config.php')) {
      return "Squirrelcart242";
    }

    //Shopscript WebAsyst
    if (file_exists(M1_STORE_BASE_DIR . 'kernel/wbs.xml')) {
      return "WebAsyst";
    }

    //Shopscript Premium
    if (file_exists(M1_STORE_BASE_DIR . 'cfg/general.inc.php') && file_exists(M1_STORE_BASE_DIR . 'cfg/connect.inc.php')) {
      return "SSFree";
    }

    //Shopscript Premium
    if (file_exists(M1_STORE_BASE_DIR . 'cfg/connect.inc.php')) {
      return "SSPremium";
    }

    //ShopScript5
    if (file_exists(M1_STORE_BASE_DIR . 'wa.php') && file_exists(M1_STORE_BASE_DIR . 'wa-config/db.php')) {
      return "SSPremium";
    }

    //Summercart3
    if (file_exists(M1_STORE_BASE_DIR . 'sclic.lic') && file_exists(M1_STORE_BASE_DIR . 'include/miphpf/Config.php')) {
      return "Summercart3";
    }

    //XtcommerceVeyton
    if (file_exists(M1_STORE_BASE_DIR . 'conf/config.php')) {
      return "XtcommerceVeyton";
    }

    //Ubercart
    if (file_exists(M1_STORE_BASE_DIR . 'sites/default/settings.php' )) {
      if (file_exists( M1_STORE_BASE_DIR . '/modules/ubercart/uc_store/includes/coder_review_uc3x.inc')) {
        return "Ubercart3";
      } elseif (file_exists(M1_STORE_BASE_DIR . 'sites/all/modules/commerce/includes/commerce.controller.inc')) {
        return "DrupalCommerce";
      }

      return "Ubercart";
    }

    //Woocommerce
    if (file_exists(M1_STORE_BASE_DIR . 'wp-config.php')
      && file_exists(M1_STORE_BASE_DIR . 'wp-content/plugins/woocommerce/woocommerce.php')
    ) {
      return 'Woocommerce';
    }

    if (file_exists(dirname(M1_STORE_BASE_DIR) . '/wp-config.php')
      && file_exists(M1_STORE_BASE_DIR . 'wp-content/plugins/woocommerce/woocommerce.php')
    ) {
      return 'Woocommerce';
    }

    //WPecommerce
    if (file_exists(M1_STORE_BASE_DIR . 'wp-config.php')) {
      return 'WPecommerce';
    }

    //OXID e-shop
    if (file_exists( M1_STORE_BASE_DIR . 'config.inc.php')) {
      return 'Oxid';
    }

    //HHGMultistore
    if (file_exists(M1_STORE_BASE_DIR . 'core/config/configure.php')) {
      return 'Hhgmultistore';
    }

    //SunShop
    if (file_exists(M1_STORE_BASE_DIR . "include" . DIRECTORY_SEPARATOR . "config.php")
      || file_exists(M1_STORE_BASE_DIR . "include" . DIRECTORY_SEPARATOR . "db_mysql.php")
    ) {
      return "Sunshop4";
    }

    //Tomatocart
    if (file_exists(M1_STORE_BASE_DIR . "includes" . DIRECTORY_SEPARATOR . "configure.php")
      && file_exists(M1_STORE_BASE_DIR. "includes" . DIRECTORY_SEPARATOR . "toc_constants.php")
    ) {
      return 'Tomatocart';
    }

    die ("BRIDGE_ERROR_CONFIGURATION_NOT_FOUND");
  }

  function getAdapterPath($cartType)
  {
    return M1_STORE_BASE_DIR . M1_BRIDGE_DIRECTORY_NAME . DIRECTORY_SEPARATOR
      . "app" . DIRECTORY_SEPARATOR
      . "class" . DIRECTORY_SEPARATOR
      . "config_adapter" . DIRECTORY_SEPARATOR . $cartType . ".php";
  }

  function setHostPort($source)
  {
    $source = trim($source);

    if ($source == '') {
      $this->Host = 'localhost';
      return;
    }

    $conf = explode(":", $source);

    if (isset($conf[0]) && isset($conf[1])) {
      $this->Host = $conf[0];
      $this->Port = $conf[1];
    } elseif ($source[0] == '/') {
      $this->Host = 'localhost';
      $this->Port = $source;
    } else {
      $this->Host = $source;
    }
  }

  function connect()
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

  function getCartVersionFromDb($field, $tableName, $where)
  {
    $version = '';

    $link = $this->connect();
    if (!$link) {
      return '[ERROR] MySQL Query Error: Can not connect to DB';
    }

    $result = $link->localQuery("
      SELECT " . $field . " AS version
      FROM " . $this->TblPrefix . $tableName . "
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
  var $_link      = null; //mysql connection link
  var $config     = null; //config adapter

  /**
   * Bridge constructor
   *
   * @param M1_Config_Adapter $config
   * @return M1_Bridge
   */
  function M1_Bridge($config)
  {
    $this->config = $config;

    if ($this->getAction() != "savefile" && $this->getAction() != "update") {
      $this->_link = $this->config->connect();
    }
  }

  function getTablesPrefix()
  {
    return $this->config->TblPrefix;
  }

  function getLink()
  {
    return $this->_link;
  }

  function query($sql, $fetchMode)
  {
    return $this->_link->query($sql, $fetchMode);
  }

  function getAction()
  {
    if (isset($_GET['action'])) {
      return str_replace('.', '', $_GET['action']);
    }

    return '';
  }

  function run()
  {
    $action = $this->getAction();

    if ($action != "update") {
      $this->_selfTest();
    }

    if ($action == "checkbridge") {
      echo "BRIDGE_OK";
      return;
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

  function isWritable($dir)
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

  function _destroy()
  {
    $this->_link = null;
  }

  function _checkPossibilityUpdate()
  {
    if (!is_writable(M1_STORE_BASE_DIR . "/" . M1_BRIDGE_DIRECTORY_NAME . "/")) {
      die("ERROR_TRIED_TO_PERMISSION" . M1_STORE_BASE_DIR . "/" . M1_BRIDGE_DIRECTORY_NAME . "/");
    }

    if (!is_writable(M1_STORE_BASE_DIR . "/". M1_BRIDGE_DIRECTORY_NAME . "/bridge.php")) {
      die("ERROR_TRIED_TO_PERMISSION_BRIDGE_FILE" . M1_STORE_BASE_DIR . "/" . M1_BRIDGE_DIRECTORY_NAME . "/bridge.php");
    }
  }

  function _selfTest()
  {
    if (!isset($_GET['ver']) || $_GET['ver'] != M1_BRIDGE_VERSION) {
      die ('ERROR_BRIDGE_VERSION_NOT_SUPPORTED');
    }

    if (isset($_GET['token']) && $_GET['token'] == M1_TOKEN) {
      // good :)
    } else {
      die('ERROR_INVALID_TOKEN');
    }

    if ((!isset($_GET['storetype']) || $_GET['storetype'] == 'target') && $this->getAction() == 'checkbridge') {

      if (trim($this->config->imagesDir) != "") {
        if (!file_exists(M1_STORE_BASE_DIR . $this->config->imagesDir) && is_writable(M1_STORE_BASE_DIR)) {
          if (!@mkdir(M1_STORE_BASE_DIR . $this->config->imagesDir, 0777, true)) {
            die('ERROR_TRIED_TO_CREATE_IMAGE_DIR' . M1_STORE_BASE_DIR . $this->config->imagesDir);
          }
        }

        if (!$this->isWritable(M1_STORE_BASE_DIR . $this->config->imagesDir)) {
          die('ERROR_NO_IMAGES_DIR '.M1_STORE_BASE_DIR . $this->config->imagesDir);
        }
      }

      if (trim($this->config->categoriesImagesDir) != "") {
        if (!file_exists(M1_STORE_BASE_DIR . $this->config->categoriesImagesDir) && is_writable(M1_STORE_BASE_DIR)) {
          if (!@mkdir(M1_STORE_BASE_DIR . $this->config->categoriesImagesDir, 0777, true)) {
            die('ERROR_TRIED_TO_CREATE_IMAGE_DIR' . M1_STORE_BASE_DIR . $this->config->categoriesImagesDir);
          }
        }

        if (!$this->isWritable(M1_STORE_BASE_DIR . $this->config->categoriesImagesDir)) {
          die('ERROR_NO_IMAGES_DIR '.M1_STORE_BASE_DIR . $this->config->categoriesImagesDir);
        }
      }

      if (trim($this->config->productsImagesDir) != "") {
        if (!file_exists(M1_STORE_BASE_DIR . $this->config->productsImagesDir) && is_writable(M1_STORE_BASE_DIR)) {
          if (!@mkdir(M1_STORE_BASE_DIR . $this->config->productsImagesDir, 0777, true)) {
            die('ERROR_TRIED_TO_CREATE_IMAGE_DIR' . M1_STORE_BASE_DIR . $this->config->productsImagesDir);
          }
        }

        if (!$this->isWritable(M1_STORE_BASE_DIR . $this->config->productsImagesDir)) {
          die('ERROR_NO_IMAGES_DIR '.M1_STORE_BASE_DIR . $this->config->productsImagesDir);
        }
      }

      if (trim($this->config->manufacturersImagesDir) != "") {
        if (!file_exists(M1_STORE_BASE_DIR . $this->config->manufacturersImagesDir) && is_writable(M1_STORE_BASE_DIR)) {
          if (!@mkdir(M1_STORE_BASE_DIR . $this->config->manufacturersImagesDir, 0777, true)) {
            die('ERROR_TRIED_TO_CREATE_IMAGE_DIR' . M1_STORE_BASE_DIR . $this->config->manufacturersImagesDir);
          }
        }

        if (!$this->isWritable(M1_STORE_BASE_DIR . $this->config->manufacturersImagesDir)) {
          die('ERROR_NO_IMAGES_DIR '.M1_STORE_BASE_DIR . $this->config->manufacturersImagesDir);
        }
      }
    }
  }
}


/**
 * @package  api2cart
 * @author   Vasul Babiy (v.babyi@magneticone.com)
 * @license  Not public license
 * @link     https://www.api2cart.com
 */

class M1_Mysql
{
  var $config = null; // config adapter
  var $result = array();
  var $dataBaseHandle = null;

  /**
   * mysql constructor
   *
   * @param M1_Config_Adapter $config
   * @return M1_Mysql
   */
  function M1_Mysql($config)
  {
    $this->config = $config;
  }

  /**
   * @return bool|null|resource
   */
  function getDataBaseHandle()
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
  function connect()
  {
    $triesCount = 10;
    $link = null;
    $host = $this->config->Host . ($this->config->Port ? ':' . $this->config->Port : '');
    $password = stripslashes($this->config->Password);

    while (!$link) {
      if (!$triesCount--) {
        break;
      }

      $link = @mysql_connect($host, $this->config->Username, $password);
      if (!$link) {
        sleep(5);
      }
    }

    if ($link) {
      mysql_select_db($this->config->Dbname, $link);
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
  function localQuery($sql)
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
   * @param string $sql       sql query
   * @param int    $fetchType fetch Type
   *
   * @return array
   */
  function query($sql, $fetchType)
  {
    $result = array(
      'result'  => null,
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
      @mysql_query("SET NAMES " . @mysql_real_escape_string($_REQUEST['set_names']), $dataBaseHandle);
      @mysql_query("SET CHARACTER SET " . @mysql_real_escape_string($_REQUEST['set_names']), $dataBaseHandle);
      @mysql_query("SET CHARACTER_SET_CONNECTION=" . @mysql_real_escape_string($_REQUEST['set_names']), $dataBaseHandle);
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
          @mysql_query("SET NAMES " . @mysql_real_escape_string($_REQUEST['set_names']), $dataBaseHandle);
          @mysql_query("SET CHARACTER SET " . @mysql_real_escape_string($_REQUEST['set_names']), $dataBaseHandle);
          @mysql_query("SET CHARACTER_SET_CONNECTION=" . @mysql_real_escape_string($_REQUEST['set_names']), $dataBaseHandle);
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

    $result['result']        = $rows;
    $result['fetchedFields'] = $fetchedFields;

    mysql_free_result($res);
    return $result;
  }

  /**
   * @return int
   */
  function getLastInsertId()
  {
    return mysql_insert_id($this->dataBaseHandle);
  }

  /**
   * @return int
   */
  function getAffectedRows()
  {
    return mysql_affected_rows($this->dataBaseHandle);
  }

  /**
   * @return void
   */
  function __destruct()
  {
    if ($this->dataBaseHandle) {
      mysql_close($this->dataBaseHandle);
    }

    $this->dataBaseHandle = null;
  }
}


/**
 * @package  api2cart
 * @author   Vasul Babiy (v.babyi@magneticone.com)
 * @license  Not public license
 * @link     https://www.api2cart.com
 */

class M1_Pdo
{
  var $config = null; // config adapter
  var $noResult = array('delete', 'update', 'move', 'truncate', 'insert', 'set', 'create', 'drop');
  var $dataBaseHandle = null;

  var $insertedId = 0;
  var $affectedRows = 0;

  /**
   * pdo constructor
   *
   * @param M1_Config_Adapter $config configuration
   * @return M1_Pdo
   */
  function M1_Pdo($config)
  {
    $this->config = $config;
  }

  /**
   * @return bool|null|PDO
   */
  function getDataBaseHandle()
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
  function connect()
  {
    $triesCount = 3;
    $host = $this->config->Host . ($this->config->Port ? ':' . $this->config->Port : '');
    $password = stripslashes($this->config->Password);
    $dbName = $this->config->Dbname;

    while ($triesCount) {
      try {
        $link = new PDO("mysql:host=$host; dbname=$dbName", $this->config->Username, $password);
        $link->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $link;

      } catch (PDOException $e) {
        $triesCount--;

        // fix invalid port
        $host = $this->config->Host;
      }
    }
    return false;
  }

  /**
   * @param string $sql sql query
   *
   * @return array|bool
   */
  function localQuery($sql)
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
   * @param string $sql       sql query
   * @param int    $fetchType fetch Type
   *
   * @return array
   */
  function query($sql, $fetchType)
  {
    $result = array(
      'result'        => null,
      'message'       => '',
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
      $this->affectedRows = $res->rowCount();
      $this->insertedId = $dataBaseHandle->lastInsertId();
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
  function getLastInsertId()
  {
    return $this->insertedId;
  }

  /**
   * @return int
   */
  function getAffectedRows()
  {
    return $this->affectedRows;
  }

  /**
   * @return  void
   */
  function __destruct()
  {
    $this->dataBaseHandle = null;
  }
}


define('M1_BRIDGE_VERSION', '21');

define('M1_BRIDGE_DIRECTORY_NAME', basename(getcwd()));

ini_set('display_errors', 1);
if (substr(phpversion(), 0, 1) == 5) {
  error_reporting(E_ALL & ~E_STRICT);
} else {
  error_reporting(E_ALL);
}

require_once 'config.php';

function stripslashes_array($array) {
  return is_array($array) ? array_map('stripslashes_array', $array) : stripslashes($array);
}

function getPHPExecutable() {
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

if (!isset($_SERVER))
{
   $_GET      = &$HTTP_GET_VARS;
   $_POST     = &$HTTP_POST_VARS;
   $_ENV      = &$HTTP_ENV_VARS;
   $_SERVER   = &$HTTP_SERVER_VARS;
   $_COOKIE   = &$HTTP_COOKIE_VARS;
   $_REQUEST  = array_merge($_GET, $_POST, $_COOKIE);
}

if (get_magic_quotes_gpc()) {
  $_COOKIE  = stripslashes_array($_COOKIE);
  $_FILES   = stripslashes_array($_FILES);
  $_GET     = stripslashes_array($_GET);
  $_POST    = stripslashes_array($_POST);
  $_REQUEST = stripslashes_array($_REQUEST);
}

if (isset($_SERVER['SCRIPT_FILENAME'])) {
  $scriptPath = $_SERVER['SCRIPT_FILENAME'];
  if ( isset($_SERVER['PATH_TRANSLATED'])  && $_SERVER['PATH_TRANSLATED'] != "" ) {
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