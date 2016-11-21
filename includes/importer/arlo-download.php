<?php

namespace Arlo\Importer;

use Arlo\Logger;
use Arlo\Utilities;
use Arlo\Crypto;

class Download extends BaseImporter  {
	const CONNECTION_TIMEOUT = 10;
	const TIMEOUT = 20;

	protected static $dir;		

	public function __construct($importer, $dbl, $message_handler, $data, $iterator = 0, $api_client, $file_handler) {
		self::$dir = trailingslashit(plugin_dir_path( __FILE__ )).'../../import/';

		parent::__construct($importer, $dbl, $message_handler, $data, $iterator, $api_client, $file_handler);
	}

	protected function save_entity($item) {}

	public function run() {

		$import = $this->importer->get_import_entry($this->import_id, null, 1);
		
		$callback_json = json_decode($import->callback_json);
		$response_json = json_decode($import->response_json);

		if (!empty($callback_json->SnapshotUri)) {
			$content = $this->get_remote_data($callback_json->SnapshotUri);
			if (!empty($content) && $content !== false) {

				//need to decode with the given key
				$methods = explode('-', $response_json->Result->EncryptedResponse->enc);
				try {
					$content = Crypto::decrypt($content, $response_json->Result->EncryptedResponse->key->k, $methods[0], $methods[1]);

					$filename = self::$dir . $this->import_id . '.dec.json';
					if ($this->file_handler->write_file($filename, $content)) {
						$this->is_finished = true;
					}
					unset($content);
				} catch(\Exception $e) {
					Logger::log_error($e->getMessage(), $this->importer->import_id);
				}
			}
		}
	}

	private function get_remote_data($url) {
    	$c = curl_init();
    	curl_setopt($c, CURLOPT_URL, $url);
    	curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($c, CURLOPT_SSL_VERIFYHOST,false);
    	curl_setopt($c, CURLOPT_SSL_VERIFYPEER,false);
	    
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
		$error_no = curl_errno($c);
    	$status = curl_getinfo($c);
    	curl_close($c);

		if ($error_no) {
			Logger::log("File download error: " . curl_error($ch));
			return false;
		}

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
				Logger::log('Invalid status code: ' . $status['http_code']);
		}

		return false;
	}
}