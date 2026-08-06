<?php

namespace ArloTraining\API\Transports;

class Transport
{
	const PLATFORM_DOMAIN = '.arlo.co';

	private $cacheTime = 3600;
	private $requestTimeout = 10;
	private $arloURL = '%s://%s/api/2012-02-01/%s';
	
	public function getRemoteURL($platform_name, $public = true, $force_ssl = true) {
		$public   = ($public) ? 'pub' : 'auth';
		$protocol = (is_bool($force_ssl) && $force_ssl ? 'https' : 'http');

		return sprintf($this->arloURL, $protocol, $platform_name . self::PLATFORM_DOMAIN, $public);
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