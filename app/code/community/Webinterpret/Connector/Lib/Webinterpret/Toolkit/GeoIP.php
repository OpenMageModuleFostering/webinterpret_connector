<?php

namespace WebinterpretConnector\Webinterpret\Toolkit;

use GeoIp2\Database\Reader;

/**
 * Geolocation and IP helpers
 */
class GeoIP
{
    private $geoip2Reader;

    public function __construct(Reader $reader)
    {
        $this->geoip2Reader = $reader;
    }

	/**
	 * Gets the client IP from headers. Proxies are taken into consideration, private & reserved ranges are filtered out.
	 *
	 * WARNING: the IP headers can be spoofed, so don't rely on it
	 *
	 * @return bool|string False on failure, client IP address on success
	 */
	public function getClientIp() {
		foreach (
			array(
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
				'REMOTE_ADDR'
			) as $key
		) {
			if (array_key_exists($key, $_SERVER) === true) {
				foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
					if (filter_var(
                            $ip,
                            FILTER_VALIDATE_IP,
                            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                        ) !== false) {
						return $ip;
					}
				}
			}
		}

		return false;
	}

    /**
     * Returns a two-character ISO 3166-1 alpha country code for the country associated with the location (based on client IP)
     * or false if no country code was found
     *
     * @return bool|string False on failure, ISO 3166-1 country code on success
     */
    public function getClientCountryCode() {
        $clientIp = $this->getClientIp();

        if ($clientIp) {
            return $this->getCountryCodeForIp($clientIp);
        }

        return false;
    }

    /**
     * Returns a two-character ISO 3166-1 alpha country code for the country associated with the provided ip
     *
     * @param string $ip
     *
     * @return string
     */
    public function getCountryCodeForIp($ip) {
        $country = $this->getGeoip2Reader()->country($ip);

        return $country->country->isoCode;
    }

    /**
     * Return reader for the GeoIP2 database format (will be lazily instantiated if it hasn't been before)
     *
     * @return Reader
     */
    public function getGeoip2Reader() {
        return $this->geoip2Reader;
    }
}
