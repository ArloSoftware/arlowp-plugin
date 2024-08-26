<?php

namespace ArloAPI\Resources;

// load main Transport class for extending
require_once 'Resource.php';

// now use it
use ArloAPI\Resources\Resource;

class WPLogError extends Resource
{
	protected $apiPath = '/resources/wp/logerror';
	
	public function send($data = null) {
				
		$results = $this->request(null, $data);
		
		return $results;
	}

	public function sendLog($message = '', $last_import_date = null, $log = '') {
		global $wp_version;
		
		$this->__set('api_path', $this->apiPath . $id);
		
		$data = [
			'WordPressUrl' => $_SERVER['HTTP_HOST'],
			'PluginVersion' => $this->__get('plugin_version'),
    		'WordPressVersion' => $wp_version,
			'LastSuccessfulSyncTimestamp' => !(is_null($last_import_date) || empty($last_import_date)) ? strtotime($last_import_date) : 'Never',
			'Message' => $message,
			'ErrorLog' => $log
		];
		
		$results = $this->send($data);
		
		return $results;
	}
}