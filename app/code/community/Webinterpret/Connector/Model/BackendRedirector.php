<?php

use Buzz\Browser;
use Buzz\Client\Curl;
use Buzz\Client\FileGetContents;
use GeoIp2\Database\Reader;
use WebinterpretConnector\Webinterpret\InventoryManagerClient;
use WebinterpretConnector\Webinterpret\InventoryManagerProductInfo;
use WebinterpretConnector\Webinterpret\Toolkit\GeoIP;


/**
 * StoreExtender
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_Model_BackendRedirector extends Varien_Object
{
    /** @var GeoIP */
    private $geoIp;

    /** @var InventoryManagerClient */
    private $inventoryManagerClient;

    /** @var string */
    private $storeUrl;

    public function __construct()
    {
        require_once(Mage::getModuleDir('', 'Webinterpret_Connector') . '/Lib/autoload.php');
        spl_autoload_register(array($this, 'loadWebinterpretConnectorLib'), true, true);

        $this->storeUrl = Mage::helper('webinterpret_connector')->getStoreBaseUrl();

        $reader = new Reader(Mage::helper('webinterpret_connector')->getGeoip2DbPath());
        $this->geoIp = new GeoIP($reader);

        $browser = new Browser($this->getBrowserClient());
        $browser->getClient()->setTimeout(1);

        $this->inventoryManagerClient = new InventoryManagerClient(
            $browser,
            $this->storeUrl,
            Mage::getStoreConfig('webinterpret_connector/inventory_manager_url'));

        parent::__construct();
    }

    public function generateInternationalRedirectionUrlIfAvailable($productId)
    {
        // collect user information
        $clientIp = $this->geoIp->getClientIp();
        $clientCountryCode = $this->geoIp->getClientCountryCode();

        if ($clientIp === false || $clientCountryCode === false) {
            return null;
        }

        // only redirect international traffic
        if ($this->isDomesticTraffic($clientCountryCode)) {
            return null;
        }

        // check if product is available in Inventory Manager
        $productInfo = $this->inventoryManagerClient->fetchProductInfo($productId, $clientIp, $clientCountryCode);

        if (!($productInfo instanceof InventoryManagerProductInfo)) {
            return null;
        }

        // don't redirect if the product is not available in the international store or auto_redirect is not enabled
        if (!$productInfo->isProductAvailable()|| !$productInfo->isAutoRedirectEnabled()) {
            return null;
        };

        return $this->generateRedirectUrl($productInfo, $this->storeUrl, $productId);
    }

    public static function loadWebinterpretConnectorLib($class)
    {
        if (preg_match( '/^WebinterpretConnector\\\\/', $class)) {
            $path = Mage::getModuleDir('', 'Webinterpret_Connector');
            $libClassName = str_replace('\\', '/', substr($class, strlen('WebinterpretConnector\\')));
            require_once($path . '/Lib/' . $libClassName . '.php');
        }
    }

    /**
     * Checks if traffic is domestic based on store and user country codes
     *
     * @param $clientCountryCode
     *
     * @return bool
     */
    private function isDomesticTraffic($clientCountryCode) {
        $storeCountryCode = Mage::getStoreConfig('general/country/default');

        if ($clientCountryCode === $storeCountryCode) {
            return true;
        }

        return false;
    }

    /**
     * Generates URL BackendRedirector should redirect the user to
     *
     * @param InventoryManagerProductInfo $productInfo
     * @param $storeUrl
     * @param $productId
     *
     * @return string
     */
    private function generateRedirectUrl(InventoryManagerProductInfo $productInfo, $storeUrl, $productId) {
        // store extender?
        if (Mage::helper('webinterpret_connector')->isStoreExtenderEnabled()) {
            $redirectUrl = $this->generateUrlForStoreExtender($productInfo, $storeUrl, $productId);
        } else {
            $redirectUrl = $productInfo->getProductUrl();
        }

        if (is_null($redirectUrl)) {
            throw new \InvalidArgumentException('Missing redirect URL');
        }

        $redirectUrl = $this->appendUtmParamsToUrl($redirectUrl);

        return $redirectUrl;
    }

    /**
     * @param InventoryManagerProductInfo $productInfo
     * @param $storeUrl
     * @param $productId
     *
     * @return string
     */
    private function generateUrlForStoreExtender(
        InventoryManagerProductInfo $productInfo,
        $storeUrl,
        $productId
    ) {
        $localeCode = $productInfo->getLocaleCode();

        if (is_null($localeCode)) {
            throw new \InvalidArgumentException( 'Missing locale or URL' );
        }

        return $storeUrl . '/glopal/' . $localeCode . '/p-' . $productId . '.html';
    }

    /**
     * Appends utm_source, utm_medium and utm_campaign params to URL provided
     *
     * @param string $redirectUrl URL to append utm_* params to
     *
     * @return string
     */
    private function appendUtmParamsToUrl($redirectUrl) {
        $params = array(
            'utm_source'   => parse_url($this->storeUrl, PHP_URL_HOST),
            'utm_medium'   => 'br', // br = backend redirector
            'utm_campaign' => Mage::getStoreConfig('general/country/default'),
        );

        $argSeparator = (strpos( $redirectUrl, '?') === false ? '?' : '&');

        return $redirectUrl . $argSeparator . http_build_query($params);
    }

    private function getBrowserClient()
    {
        if (function_exists('curl_version')) {
            return new Curl();
        } else {
            return new FileGetContents();
        }
    }
}
