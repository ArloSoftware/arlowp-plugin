<?php
namespace ArloAPI\Transports;

// load main Transport class for extending
require_once 'Transport.php';

// now use it
use ArloAPI\Transports\Transport;

class Wordpress extends Transport
{
	/**
	 * core request function
	 *
	 * used as the main communication layer between API and local code
	 *
	 * @param string $platform_name defines the platform we are getting data from
	 * @param string $method defines the method for the request
	 *
	 * @return void
	 */
	public function request($platform_name, $path, $post_data=null, $public=true, $plugin_version = '', $force_ssl = true) {
		$args = func_get_args();
		$cache_key = md5(serialize($args));
		
		if($cached = wp_cache_get($cache_key, 'ArloAPI')) {
			return $cached;
		}
		
		$url = $this->getRemoteURL($platform_name, $public, false, $force_ssl) . $path;

		try {
			$args = array(
				'headers' => array(
					'X-Plugin-Version' => $plugin_version,
					'Content-type' => 'application/json',
					'Accept' => 'application/json',
					'Expect' => '',
				),
				'compress'    => true,
				'decompress'  => false,
                'stream'      => false,
				'timeout'     => $this->getRequestTimeout(),
				'method'	  => (is_null($post_data)) ? 'GET' : 'POST',
			);

			if (!is_null($post_data)) {
				$args['body'] = json_encode($post_data);
			}

			$response = wp_remote_request( $url, $args );

			if(is_wp_error($response)) {
				$message = '';
				if (is_array($response->errors)) {
					foreach ($response->errors as $key => $error) {
						$message .= $key . ' ' . implode(', ', $error) . "; \n";
					}
				} else {
					$message = $response->errors;
				}
				throw new \Exception($message);
			}
			
			if(substr($response['response']['code'], 0, 1) !== "2") {
				$body = json_decode($response['body'], true);			
				throw new \Exception('Error code ' . $response['response']['code'] . ': ' . $response['response']['message'] . (!empty($body['Message']) ?  ' ' . $body['Message'] : ''));
			}
		} catch(\Exception $e) {           
            //pass on the exception to catch framework-side
            throw $e;
		}
		
		$json = json_decode($response['body']);
		
		wp_cache_add( $cache_key, $json, 'ArloAPI', $this->getCacheTime() );
		
		return $json;
	}
}