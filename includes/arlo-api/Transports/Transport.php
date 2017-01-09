<?php

namespace ArloAPI\Transports;

class Transport
{
	private $cacheTime = 3600;
	private $requestTimeout = 10;
	private $arloURL = '%s://%s.arlo.co/%sapi/2012-02-01/%s';
	private $arloURL2 = '%s://%s/api/2012-02-01/%s';
	private $useNewUrlStructure = false;
	
	public function getRemoteURL($platform_name, $public = true, $force_new_url_structure = false, $force_ssl = true) {
		$subdomain = (defined('ARLO_TEST_API') && ARLO_TEST_API) ? 'api-test' : 'api';
		$public = ($public) ? 'pub' : 'auth';
		$protocol = (is_bool($force_ssl) && $force_ssl ? 'https' : 'http');
		
		if (strpos($platform_name, '.') !== false) {
			return sprintf($this->arloURL2, $protocol, $platform_name, $public);
		} else if ($this->useNewUrlStructure || $force_new_url_structure) {
			return sprintf($this->arloURL, $protocol, $platform_name, "" ,$public);
		} else {
			return sprintf($this->arloURL, $protocol, $subdomain, $platform_name . '/', $public);
		}
	}
	
	public function setCacheTime($time) {
		$this->cacheTime = $time;
	}
	
	public function getCacheTime() {
		return $this->cacheTime;
	}
	
	public function setRequestTimeout($seconds) {
		$this->requestTimeout = $seconds;
	}
	
	public function getRequestTimeout() {
		return $this->requestTimeout;
	}
	
	public function setUseNewUrlStructure($use_new_url_structure = false) {
		$this->useNewUrlStructure = $use_new_url_structure;
	}
}