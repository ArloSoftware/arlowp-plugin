<?php

namespace Arlo;

class Crypto {
	public static $available_crypto_methods = [
		'A256CBC' => 'AES_256_CBC'
	];

	public static $available_hasher_methods = [
		'HS512' => 'sha512'
	];

	const ENCRYPTION_KEY_LENGTH = 32;
	const MAC_KEY_LENGTH  = 32;
	const MAC_LENGTH = 32; 
	const IV_LENGTH = 16;

	public static function decrypt($encrypted, $key, $crypto_method, $hash_method) {
    	$key = base64_decode($key);
    	$hashed_key = hash(self::$available_hasher_methods[$hash_method], $key, true);

    	$key_m = substr($hashed_key, 0, self::MAC_KEY_LENGTH);
		$key_e = substr($hashed_key, self::MAC_KEY_LENGTH, self::ENCRYPTION_KEY_LENGTH);
    	
		if (array_key_exists($crypto_method, self::$available_crypto_methods)) {
			//first bytes are IV
			$iv = substr($encrypted, 0, self::IV_LENGTH);
			$cyphertext = substr($encrypted, self::IV_LENGTH, strlen($encrypted) - self::MAC_LENGTH - self::IV_LENGTH);

			if (array_key_exists($hash_method, self::$available_hasher_methods)) {
				$hmac = substr(hash_hmac(self::$available_hasher_methods[$hash_method], $iv . $cyphertext, $key_m, true), 0, self::MAC_LENGTH);

				//last bytes are HMAC
				if (substr($encrypted, strlen($encrypted) - self::MAC_LENGTH) == $hmac) {
					$decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key_e, $cyphertext, MCRYPT_MODE_CBC, $iv);
					return gzdecode($decrypted);
				} else {
					throw new \Exception('Invalid HMAC');
				}
			} 
			throw new \Exception('The hash method is not the required one');
		} 
		throw new \Exception('The encryption method is not the required one');
	}
}