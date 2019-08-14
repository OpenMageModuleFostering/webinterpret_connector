<?php

use Buzz\Browser;
use Buzz\Client\Curl;
use Buzz\Client\FileGetContents;
use Buzz\Exception\ClientException;
use Buzz\Message\Response;

/**
 * StoreExtender
 *
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_Model_StoreExtender extends Varien_Object
{
    /**
     * @var string Prefix for cookies that should be forwarded to store extender proxy
     */
    private $cookiePrefix = 'wi_ws_store_ext_';

    public function __construct()
    {
        require_once(Mage::getModuleDir('', 'Webinterpret_Connector') . '/Lib/autoload.php');
        spl_autoload_register(array($this, 'loadStoreExtenderDependencies'), true, true);

        parent::__construct();
    }

    public static function loadStoreExtenderDependencies($class)
    {
        if (preg_match( '/^WebinterpretConnector\\\\/', $class)) {
            $path = Mage::getModuleDir('', 'Webinterpret_Connector');
            $libClassName = str_replace('\\', '/', substr($class, strlen('WebinterpretConnector\\')));
            require_once($path . '/Lib/' . $libClassName . '.php');
        }
    }

    public function parseRequest()
    {
        $storeExtenderUrl = Mage::getStoreConfig('webinterpret_connector/store_extender_url');
        $storeExtenderUrl .= '?originalUrl=' . urlencode(Mage::helper('core/url')->getCurrentUrl());

        $browser = new Browser($this->getBrowserClient());
        $browser->getClient()->setMaxRedirects(0);

        $method = $_SERVER['REQUEST_METHOD'];

        try {
            $response = $browser->call(
                $storeExtenderUrl,
                $_SERVER['REQUEST_METHOD'],
                $this->getRequestHeaders(),
                $this->getRequestContent($method)
            );
        } catch (ClientException $e) {
            // fallback - redirect to base store URL if there is problem with proxy
            header('Location: ' . Mage::getBaseUrl());
            die();
        }

        $this->outputResponseCode($response->getStatusCode());
        $this->outputHeaders($this->parseResponseHeadersToArray($response));
        echo $response->getContent();
        die;
    }

    /**
     * @param $method
     * @return string
     */
    private function getRequestContent($method)
    {
        $content = '';

        if (in_array($method, array('PUT', 'PATCH', 'DELETE'))) {
            $content = file_get_contents("php://input");
        } elseif ($method === 'POST') {
            $content = http_build_query($_POST);
        }
        return $content;
    }

    /**
     * @return array
     */
    private function getRequestHeaders()
    {
        $headers = array();

        $supportedHeaders = array(
            'HTTP_X_REQUESTED_WITH' => 'X-Requested-With',
            'HTTP_CONTENT_TYPE'     => 'Content-Type',
            'HTTP_CONNECTION'       => 'Connection',
            'HTTP_CACHE_CONTROL'    => 'Cache-Control',
            'HTTP_USER_AGENT'       => 'User-Agent',
            'HTTP_ACCEPT'           => 'Accept',
            'HTTP_ACCEPT_LANGUAGE'  => 'Accept-Language',
        );

        foreach ($supportedHeaders as $supportedHeader => $supportedHeaderKey) {
            if (isset($_SERVER[$supportedHeader])) {
                $headers[$supportedHeaderKey] = $_SERVER[$supportedHeader];
            }
        }

        $cookies = $this->parseRequestCookies();
        if (!empty($cookies)) {
            $headers['Cookie'] = $cookies;
        }

        return $headers;
    }

    private function parseRequestCookies()
    {
        $cookies = array();
        if (isset($_SERVER['HTTP_COOKIE'])) {
            $cookies = array_map('trim', explode(';', $_SERVER['HTTP_COOKIE']));
            $cookiePrefix = $this->cookiePrefix;
            $cookies = array_filter($cookies, function($cookie) use ($cookiePrefix) {
                return (strpos($cookie, $cookiePrefix) === 0);
            });
        }

        return implode('; ', $cookies);
    }

    /**
     * @param \Buzz\Message\Response $response
     * @return array
     */
    private function parseResponseHeadersToArray(Response $response)
    {
        $supportedHeaders = array(
            'Date',
            'Content-Type',
            'Content-Length',
            'Connection',
            'Keep-Alive',
            'Location',
            'Set-Cookie',
        );

        //reformat response headers
        $responseHeaders = array();

        foreach ($response->getHeaders() as $header) {
            list($headerName, $headerValue) = explode(':', $header, 2);
            if ($headerValue) {
                $responseHeaders[$headerName] = trim($headerValue);
            }
        }

        foreach ($responseHeaders as $header => $contents) {
            if (in_array($header, $supportedHeaders)) {
                $responseHeaders[] = $header . ': ' . $contents;
            }
            unset($responseHeaders[$header]);
        }

        return $responseHeaders;
    }

    /**
     * @param array $responseHeaders
     */
    private function outputHeaders($responseHeaders)
    {
        // remove previously set headers
        header_remove();

        foreach ($responseHeaders as $responseHeader) {
            header($responseHeader);
        }
    }

    /**
     * @param $code
     */
    private function outputResponseCode($code)
    {
        if (function_exists( 'http_response_code')) {
            http_response_code($code);
        } else {
            // X-PHP-Response-Code is a fake header name, we rely on the webserver to handle the 3rd parameter accordingly
            header('X-PHP-Response-Code: ' . $code, true, $code);
        }
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
