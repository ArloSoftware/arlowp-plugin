<?php

namespace ArloAPI\Resources;

// load main Transport class for extending
require_once 'Resource.php';

// now use it
use ArloAPI\Resources\Resource;

class Timezones extends Resource
{
	protected $apiPath = '/resources/timezones/';
	
	public function search($count = 256) {
		$data = array(
			'top=' . $count,
			'format=json',
			'fields=TimeZoneID,Name,TzNames'
		);
		
		$results = $this->request(implode('&', $data));
		
		return $results;
	}

	public function getAllTimezones($fields=array()) {
		return $this->search()->Items;
	}
}