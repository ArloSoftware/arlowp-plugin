<?php

namespace Arlo\Importer;

use Arlo\Logger;
use Arlo\Utilities;
use Arlo\Crypto;

class Download extends BaseImporter  {
	const CONNECTION_TIMEOUT = 10;
	const TIMEOUT = 20;

	protected static $dir;
	public $filename;
	public $uri;

	public $response_json;

	public function __construct($importer, $dbl, $message_handler, $data, $iteration = 0, $api_client = null, $file_handler = null, $scheduler = null) {
		parent::__construct($importer, $dbl, $message_handler, $data, $iteration, $api_client, $file_handler, $scheduler);

		self::$dir = trailingslashit(plugin_dir_path( __FILE__ )).'../../import/';
	}

	protected function save_entity($item) {}

	public function run() {
		
		if (!empty($this->uri)) {
			$content = $this->get_remote_data($this->uri);
			if (!empty($content) && $content !== false) {

				//need to decode with the given key
				$methods = explode('-', $this->response_json->Result->EncryptedResponse->enc);
				$content = Crypto::decrypt($content, $this->response_json->Result->EncryptedResponse->key->k, $methods[0], $methods[1], 1);

				$filename = self::$dir . $this->filename . '.dec.json';
				if ($this->file_handler->write_file($filename, $content)) {
					$this->is_finished = true;
				} else {
					Logger::log_error('Missing write permission on \'import\' directory', $this->import_id);
					throw new \Exception('Missing write permission on \'import\' directory');
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