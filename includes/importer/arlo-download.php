<?php

namespace Arlo\Importer;

use Arlo\Logger;
use Arlo\Utilities;
use Arlo\Crypto;

class Download extends BaseImporter  {
	const CONNECTION_TIMEOUT = 10;
	const TIMEOUT = 20;

	public $import_part;
	public $import_iteration;
	public $uri;
	public $response_json;


	protected function save_entity($item) {}

	public function run() {
		
		if (!empty($this->uri)) {
			$content = $this->get_remote_data($this->uri);
			if (!empty($content) && $content !== false) {

				$key = $this->response_json->Result->EncryptedResponse->key->k;
				$method = $this->response_json->Result->EncryptedResponse->enc;
				$content = Crypto::decrypt_gzip($content, $key, $method);

				$id = $this->importing_parts->add_import_part($this->import_part, $this->import_iteration, $content, $this->import_id);
				if (!empty($id)) {
					$this->is_finished = true;
				}
				unset($content);
			} else {
				throw new \Exception('The downloaded file is empty');
			}
		} else {
			throw new \Exception('The URI couldn\'t be empty');
		}
	}

	private function get_remote_data($url) {
		if (!extension_loaded('curl') ) {
			throw new \Exception('cUrl is not available');
		}

    	$c = curl_init();
    	curl_setopt($c, CURLOPT_URL, $url);
    	curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);

		$settings = get_option('arlo_settings');
		if (!empty($settings['disable_ssl_verification']) && $settings['disable_ssl_verification'] == 1) {
			curl_setopt($c, CURLOPT_SSL_VERIFYHOST,false);
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER,false);
		}
	    
		$follow_allowed = (ini_get('open_basedir') || ini_get('safe_mode')) ? false : true;
		if ($follow_allowed) {
        	curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
    	}

    	curl_setopt($c, CURLOPT_CONNECTTIMEOUT, self::CONNECTION_TIMEOUT);
    	curl_setopt($c, CURLOPT_REFERER, $url);
    	curl_setopt($c, CURLOPT_TIMEOUT, self::TIMEOUT);
    	curl_setopt($c, CURLOPT_AUTOREFERER, true);
    	curl_setopt($c, CURLOPT_ENCODING, 'gzip,deflate');

    	$data = curl_exec($c);
    	$status = curl_getinfo($c);

		if (curl_errno($c)) {
			throw new \Exception("File download error: " . curl_error($c));
			return false;
		}

		curl_close($c);

		switch ($status['http_code']) {
			case 200:
				return $data;
				break;
			case 301:
			case 302:
				if (!$follow_allowed) {
            		if (!empty($status['redirect_url'])) {
                		$redirURL = $status['redirect_url'];
            		} else {
                		preg_match('/href\=\"(.*?)\"/si',$data,$m);
                		if (!empty($m[1])) {
                    		$redirURL=$m[1];
                		}
            		}
            		if(!empty($redirURL)) {
                		return  $this->get_remote_data($redirURL);
            		}
        		}
				break;

			default:
				Logger::log_error('Invalid status code: ' . $status['http_code'] . ' ', $this->import_id);
		}

		return false;
	}
}