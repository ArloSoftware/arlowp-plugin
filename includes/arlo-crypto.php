<?php

namespace Arlo;

class Crypto {
	protected static $key = '5gsg5qnjMzzKpkxTRB6mTHdGQSL8TFjuLBf6i8pYj3I=';
	private static $method = 'AES_256_CBC';

	public static function decrypt($encrypted_json) {
    	$key = base64_decode(self::$key);
    	$hashed_key = hash('sha384', $key, true);
    	$key_e = mb_strcut($hashed_key, 0, 32);
    	$key_m = mb_strcut($hashed_key, 32);
		
		if (isset($encrypted_json->a) && $encrypted_json->a == self::$method) {
			$cyphertext = base64_decode($encrypted_json->ct);
			$iv = base64_decode($encrypted_json->iv);

			$hmac = hash_hmac('sha256', $iv . $cyphertext, $key_m, true);

			if ($hmac == base64_decode($encrypted_json->hm)) {
				$decrypted_json = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key_e, $cyphertext, MCRYPT_MODE_CBC, $iv);
				return json_decode(gzdecode($decrypted_json));
			} else {
				throw new \Exception('Invalid HMAC');
			}
		} 
	
		throw new \Exception('The encryption method is not the required one');
		
	}
}