<?php

namespace ArloAPI\Resources;

// load main Transport class for extending
require_once 'Resource.php';

// now use it
use ArloAPI\Resources\Resource;

class Snapshots extends Resource
{
	protected $apiPath = '/resources/views/4ca89ebdc4e54490b6f4f46c347d0d9c/snapshots/';
	
	public function request_import($post_data) {
		$this->apiPath .= 'requests/';
		$this->__set('api_path', $this->apiPath);
		
		$results = $this->request(null, $post_data);
		
		return $results;
	}
}