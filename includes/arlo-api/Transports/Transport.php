<?php

namespace ArloAPI\Transports;

class Transport
{
	private $cacheTime = 3600;
	private $requestTimeout = 10;
	private $arloURL = 'https://%s.arlo.co/%s/api/2012-02-01/%s';
	
	public function getRemoteURL($platform_name, $public=true) {
		$subdomain = (defined('ARLO_TEST_API') && ARLO_TEST_API) ? 'api-test' : 'api';
		$public = ($public) ? 'pub' : 'auth';
		
		return sprintf($this->arloURL, $subdomain, $platform_name, $public);
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
}