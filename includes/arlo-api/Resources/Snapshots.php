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
		
		$results = $this->request(null, $post_data, true, false);
		
		return $results;
	}
	

	public function request_test_callback($platform_name, $post_data) {
		$this->apiPath .= 'requests/';
		$this->__set('api_path', $this->apiPath);

		$return_object = new \stdClass();
		$post_data_string = json_encode($post_data);

		$url = $this->get_remote_url($platform_name, true, true);

		if (extension_loaded('curl')) {
			$ch = curl_init($url);

			curl_setopt($ch, CURLOPT_TIMEOUT, 5);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data_string);

			curl_setopt($ch, CURLOPT_HTTPHEADER, array(                                                                          
				'Content-Type: application/json',                                                                                
				'Content-Length: ' . strlen($post_data_string))                                                                       
			); 

			$settings = get_option('arlo_settings');
			if (!empty($settings['disable_ssl_verification']) && $settings['disable_ssl_verification'] == 1) {
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
			}
			
			$response = curl_exec($ch);

			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$headers = substr($response, 0, $header_size);
			$body = substr($response, $header_size);
			try {
				$json_body = json_decode($body);
				$error = $json_body->Message;
			} catch (\Exception $e) {}
			
			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
			$curl_error = curl_error($ch);
		} else {
			$httpcode = 0;
			$curl_error = 'cUrl is not available';
		}

		$return_object->success = substr($httpcode, 0, 1) == 2;
		if ($httpcode < 200) {
			$return_object->error = $curl_error;
		} else {
				if ($return_object->success) {
					if (!($json_body->TestResult && $json_body->TestResult->Success)) {
							$return_object->success = false;
							$return_object->error = __('Error from the WordPress server ', 'arlo-for-wordpress' );
							if ($json_body->TestResult && !empty($json_body->TestResult->Error)) {
								$return_object->error .= ": " . $json_body->TestResult->Error;
							}
					}
 			} else if (substr($httpcode, 0, 1) == 4) {
				if (!empty($error)) {
					$return_object->error = __('Error: ', 'arlo-for-wordpress' ) . $json_body->Message;
				} else {
					$return_object->error = __('The provided platform does not exist.', 'arlo-for-wordpress' );
				}
			} else if (substr($httpcode, 0, 1) == 5) {
				if (!empty($error)) {
					$return_object->error = __('Error: ', 'arlo-for-wordpress' ) . $json_body->Message;
				} else {
					$return_object->error = __('Server error, please try again later', 'arlo-for-wordpress' );
				}
			}
		}

		return $return_object;
	}

	private function get_remote_url($platform_name = "", $public = true, $force_ssl = true) {
		$path = $this->__get('api_path');
		$transport = $this->__get('transport');

		if (empty($platform_name)) 
    		$platform_name = $this->__get('platform_name');

        return $transport->getRemoteURL($platform_name, $public, false, $force_ssl) . $path;
    }
}