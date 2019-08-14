<?php

/**
 * @category   Webinterpret
 * @package    Webinterpret_Connector
 * @author     Webinterpret Team <info@webinterpret.com>
 * @license    http://opensource.org/licenses/osl-3.0.php
 */
class Webinterpret_Connector_StatusApi_SystemDiagnostics
    extends Webinterpret_Connector_StatusApi_AbstractStatusApiDiagnostics
{
	/**
	 * @inheritdoc
	 */
	public function getName() {
		return 'system';
	}

	/**
	 * @inheritdoc
	 */
	protected function getTestsResult() {
		return array(
			'php_version'              => $this->getPhpVersion(),
			'curl_available'           => $this->isCurlAvailable(),
			'curl_version'             => $this->isCurlAvailable() ? $this->getCurlVersion() : null,
			'curl_ssl_support_enabled' => $this->isCurlAvailable() ? $this->isCurlSSLSupportEnabled() : null,
			'curl_ssl_version'         => $this->isCurlSSLSupportEnabled() ? $this->getCurlSSLVersion() : null,
			'openssl_available'        => $this->isOpenSSLAvailable(),
			'openssl_version'          => $this->isOpenSSLAvailable() ? $this->getOpenSSLVersion() : null,
			'db_support'               => $this->getDbSupport(),
			'allow_url_fopen_enabled'  => $this->isAllowUrlFopenEnabled(),
			'register_globals_enabled' => $this->isRegisterGlobalsEnabled(),
			'post_max_size'            => $this->getPostMaxSize(),
			'php_time_limit'           => $this->getMaxExecutionTime(),
			'opcache_enabled'          => $this->isOpcacheEnabled(),
			'apc_enabled'              => $this->isApcEnabled(),
			'current_datetime'         => date( 'Y-m-d H:i:s' ),
			'timezone'                 => date_default_timezone_get(),
			'php_sapi_name'            => $this->getPhpSapiName(),
			'is_hhvm'                  => $this->isHhvm(),
			'is_suhosin'               => $this->isSuhosin(),
			'operating_system'         => $this->getOsInfo(),
			'server'                   => $this->getServerInfo(),
			'apache'                   => $this->getApacheInfo(),
			'php'                      => $this->getPhpInformation(),
			'php_extensions'           => get_loaded_extensions(),

		);
	}

	/**
	 * @return string
	 */
	private function getPhpVersion() {
		return phpversion();
	}

	/**
	 * @return bool
	 */
	private function isCurlAvailable() {
		return extension_loaded( 'curl' ) && function_exists( 'curl_version' ) ? true : false;
	}

	/**
	 * @return array|null
	 */
	private function getCurlVersionInfoArray() {
		if ( ! function_exists( 'curl_version' ) ) {
			return null;
		}

		return curl_version();
	}

	/**
	 * @return string|null
	 */
	private function getCurlVersion() {
		$curlVersion = $this->getCurlVersionInfoArray();

		if ( ! is_array( $curlVersion ) || ! isset( $curlVersion['version'] ) ) {
			return null;
		}

		return $curlVersion['version'];
	}

	/**
	 * @return bool|null
	 */
	private function isCurlSSLSupportEnabled() {
		$curlVersion = $this->getCurlVersionInfoArray();

		if ( ! is_array( $curlVersion ) || ! isset( $curlVersion['features'] ) || ! defined( 'CURL_VERSION_SSL' ) ) {
			return null;
		}

		// checks if the CURL_VERSION_SSL bitwise flag is enabled
		return ( $curlVersion['features'] & CURL_VERSION_SSL ) == CURL_VERSION_SSL;
	}

	private function getCurlSSLVersion() {
		if ( ! $this->isOpenSSLAvailable() ) {
			return null;
		}

		$curl_version = $this->getCurlVersionInfoArray();

		if ( ! is_array( $curl_version ) || ! isset( $curl_version['ssl_version'] ) ) {
			return null;
		}

		return $curl_version['ssl_version'];
	}

	/**
	 * @return bool
	 */
	private function isOpenSSLAvailable() {
		return extension_loaded( 'openssl' );
	}

	/**
	 * @return null|string
	 */
	private function getOpenSSLVersion() {
		if ( ! $this->isOpenSSLAvailable() || ! defined( 'OPENSSL_VERSION_TEXT' ) ) {
			return null;
		}

		return OPENSSL_VERSION_TEXT;
	}

	/**
	 * @return array
	 */
	private function getDbSupport() {
		return array(
			'mysql'       => function_exists( 'mysql_connect' ),
			'mysqli'      => function_exists( 'mysqli_connect' ),
			'pg'          => function_exists( 'pg_connect' ),
			'pdo'         => extension_loaded( 'PDO' ),
			'pdo_mysql'   => extension_loaded( 'pdo_mysql' ),
			'pdo_pgsql'   => extension_loaded( 'pdo_pgsql' ),
			'pdo_drivers' => extension_loaded( 'PDO' ) ? \PDO::getAvailableDrivers() : null
		);
	}

	/**
	 * @return bool
	 */
	private function isAllowUrlFopenEnabled() {
		return ini_get( 'allow_url_fopen' ) == true;
	}

	/**
	 * @return bool
	 */
	private function isRegisterGlobalsEnabled() {
		return ini_get( 'register_globals' ) == true;
	}

	/**
	 * @return string
	 */
	private function getPostMaxSize() {
		return ini_get( 'post_max_size' );
	}

	/**
	 * @return string
	 */
	private function getMaxExecutionTime() {
		return ini_get( 'max_execution_time' );
	}

	/**
	 * @return bool
	 */
	private function isOpcacheEnabled() {
		return extension_loaded( 'Zend OPcache' ) && ini_get( 'opcache.enable' ) ? true : false;
	}

	/**
	 * @return bool
	 */
	private function isApcEnabled() {
		return extension_loaded( 'apc' ) && ini_get( 'apc.enabled' ) ? true : false;
	}

	/**
	 * @return string
	 */
	private function getPhpSapiName() {
		return php_sapi_name();
	}

	/**
	 * @return bool
	 */
	private function isHhvm() {
		return defined( 'HHVM_VERSION' ) ? true : false;
	}

	/**
	 * @return bool
	 */
	private function isSuhosin() {
		return extension_loaded( 'suhosin' );
	}

	/**
	 * @return array
	 */
	private function getOsInfo() {
		return array(
			'os_name'      => php_uname( 's' ),
			'release_name' => php_uname( 'r' ),
			'version'      => php_uname( 'v' ),
			'machine_type' => php_uname( 'm' ),
			'os_host_name' => php_uname( 'n' ),
		);
	}

	/**
	 * @return array
	 */
	private function getServerInfo() {
		return array(
			'server_software' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : null,
			'server_name'     => isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : null,
			'server_addr'     => isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : null,
			'server_port'     => isset( $_SERVER['SERVER_PORT'] ) ? $_SERVER['SERVER_PORT'] : null,
			'https'           => isset( $_SERVER['HTTPS'] ) ? $_SERVER['HTTPS'] : false,
			'document_root'   => isset( $_SERVER['DOCUMENT_ROOT'] ) ? $_SERVER['DOCUMENT_ROOT'] : null,
			'script_filename' => isset( $_SERVER['SCRIPT_FILENAME'] ) ? $_SERVER['SCRIPT_FILENAME'] : null,
		);
	}

	/**
	 * @return array
	 */
	private function getApacheInfo() {
        if(!function_exists('apache_get_version') || !function_exists('apache_get_modules')) {
            return array();
        }
		return array(
			'version' => apache_get_version(),
			'modules' => apache_get_modules()
		);
	}

	/**
	 * @return array
	 */
	private function getPhpInformation() {
		return array(
			'php_ini'          => php_ini_loaded_file(),
			'parsed_ini_files' => php_ini_scanned_files()
		);
	}

}