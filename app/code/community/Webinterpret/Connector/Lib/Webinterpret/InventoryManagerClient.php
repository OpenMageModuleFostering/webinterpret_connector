<?php

namespace WebinterpretConnector\Webinterpret;

use Buzz\Browser;
use Buzz\Message\Response;

/**
 * Class InventoryManagerClient
 * @package WebinterpretConnector\Webinterpret
 */
class InventoryManagerClient
{
    /**
     * @var Browser
     */
    private $browser;

    /**
     * @var string
     */
    private $storeUrl;

    /**
     * @var string
     */
    private $inventoryManagerUrl;

    /**
     * InventoryManagerClient constructor.
     * @param Browser $browser
     * @param $storeUrl
     * @param $inventoryManagerUrl
     */
    public function __construct(Browser $browser, $storeUrl, $inventoryManagerUrl)
    {
        $this->browser = $browser;
        $this->storeUrl = $storeUrl;
        $this->inventoryManagerUrl = $inventoryManagerUrl;
    }

    /**
     * @param $productId
     * @return InventoryManagerProductInfo|bool
     */
    public function fetchProductInfo($productId, $clientIp, $clientCountryCode)
    {
        $productLookupUrl = $this->inventoryManagerUrl . '/product/' . $productId;
        $productLookupUrl .= '?' . http_build_query(array(
            'store_url' => $this->storeUrl,
        ));

        $headers = array(
            'Connection' => 'keep-alive',
            'Cache-Control' => 'no-cache',
            'Webinterpret-Visitor-Ip' => $clientIp,
            'Webinterpret-Visitor-Country' => $clientCountryCode,
        );

        $response = $this->browser->call(
            $productLookupUrl,
            'GET',
            $headers
        );

        if (!$this->isValidResponse($response)) {
            return false;
        }

        $jsonObject = json_decode($response->getContent());

        if (is_null($jsonObject)) {
            return false;
        }

        if ($this->isInventoryManagerStatusError($jsonObject)) {
            return false;
        }

        return new InventoryManagerProductInfo($jsonObject);
    }

    /**
     * Checks if response from the Inventory Manager API call is valid
     *
     * @param mixed $response
     *
     * @return bool
     */
    private function isValidResponse(Response $response)
    {
        if ($response->isEmpty()) {
            return false;
        };

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        return true;
    }

    /**
     * Checks if Inventory Manager returned a response with status => "error"
     *
     * @param \stdClass $jsonObject
     *
     * @return bool
     */
    private function isInventoryManagerStatusError($jsonObject) {
        if (property_exists($jsonObject, 'status') === true && $jsonObject->status === 'error') {
            return true;
        };

        return false;
    }
}
