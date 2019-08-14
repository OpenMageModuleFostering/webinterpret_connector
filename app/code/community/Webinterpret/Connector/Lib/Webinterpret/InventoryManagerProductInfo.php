<?php

namespace WebinterpretConnector\Webinterpret;

/**
 * Product information fetched from Inventory Manager
 */
class InventoryManagerProductInfo {

	/**
	 * @var \stdClass Response from Inventory Manager
	 */
	private $originalResponse;

	/**
	 * InventoryManagerProductInfo constructor.
	 *
	 * @param \stdClass $inventoryManagerResponse
	 */
	public function __construct(\stdClass $inventoryManagerResponse) {
		$this->originalResponse = $inventoryManagerResponse;
	}

	/**
	 * Checks whether product is available (based on response from Inventory Manager)
	 *
	 * @return bool
	 */
	public function isProductAvailable() {
		if ($this->propertyExists('available') && $this->get('available') === true) {
			return true;
		}

		return false;
	}

	/**
	 * Checks whether automatic redirection is enabled on Inventory Manager's side
	 *
	 * @return bool
	 */
	public function isAutoRedirectEnabled() {
		if ($this->propertyExists('auto_redirect') && $this->get('auto_redirect') === true) {
			return true;
		}

		return false;
	}

	/**
	 * Returns locale code
	 *
	 * @return null|string
	 */
	public function getLocaleCode() {
		if ($this->propertyExists('locale_code')) {
			return $this->get('locale_code');
		}

		return null;
	}

	/**
	 * Returns product international URL
	 *
	 * @return null|string
	 */
	public function getProductUrl() {
		if ($this->propertyExists('url')) {
			return $this->get('url');
		}

		return null;
	}

	/**
	 * Checks if the property exists in the response
	 *
	 * @param $property_name
	 *
	 * @return bool
	 */
	private function propertyExists($property_name) {
		return (property_exists($this->originalResponse, $property_name) === true);
	}

	/**
	 * Returns a chosen property from the response
	 *
	 * @param $property_name
	 *
	 * @return mixed
	 */
	private function get($property_name) {
		return $this->originalResponse->{$property_name};
	}
}
