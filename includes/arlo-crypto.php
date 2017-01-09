<?php

namespace Arlo;

use \Arlo\Logger;

class Crypto {
	public static $available_crypto_methods = [
		'A256CBC' => MCRYPT_RIJNDAEL_128,
	];

	public static $available_hasher_methods = [
		'HS512' => 'sha512',
	];

	public static $jwe_part_keys = [
		'header' => 0,
		'enryption_key' => 1,
		'iv' => 2,
		'cipher_text' => 3,
		'auth_tag' => 4,
	];

	const ENCRYPTION_KEY_LENGTH = 32;
	const MAC_KEY_LENGTH  = 32;
	const MAC_LENGTH = 32; 
	const IV_LENGTH = 16;

	public static function decrypt($encrypted, $key, $crypto_method, $hash_method, $gzip = false) {
		if (!extension_loaded('mcrypt') ) {
			throw new \Exception('mCrypt is not available');
		}

		list($key_m, $key_e) = self::derive_secondary_keys($key, $hash_method);

		if (array_key_exists($crypto_method, self::$available_crypto_methods)) {
			//first bytes are IV
			$iv = substr($encrypted, 0, self::IV_LENGTH);
			$cyphertext = substr($encrypted, self::IV_LENGTH, strlen($encrypted) - self::MAC_LENGTH - self::IV_LENGTH);

			if (array_key_exists($hash_method, self::$available_hasher_methods)) {
				$hmac = substr(hash_hmac(self::$available_hasher_methods[$hash_method], $iv . $cyphertext, $key_m, true), 0, self::MAC_LENGTH);

				//last bytes are HMAC
				if (substr($encrypted, strlen($encrypted) - self::MAC_LENGTH) == $hmac) {
					$decrypted = mcrypt_decrypt(self::$available_crypto_methods[$crypto_method], $key_e, $cyphertext, MCRYPT_MODE_CBC, $iv);
					if (!empty($php_errormsg)) {
						throw new \Exception($php_errormsg);
					}

					return trim(($gzip ? gzdecode($decrypted) : $decrypted));
				} else {
					throw new \Exception('Invalid HMAC');
				}
			} 
			throw new \Exception('The hash method is not the required one');
		} 
		throw new \Exception('The encryption method is not the required one');
	}

	public static function derive_secondary_keys($key, $hash_method) {
		$key = base64_decode($key);
    	$hashed_key = hash(self::$available_hasher_methods[$hash_method], $key, true);
		if (!empty($php_errormsg)) {
			throw new \Exception($php_errormsg);
		}

		return [
			substr($hashed_key, 0, self::MAC_KEY_LENGTH),
			substr($hashed_key, self::MAC_KEY_LENGTH, self::ENCRYPTION_KEY_LENGTH)
		];
	}

	public static function jwe_decrypt($jwe = '', $key) {
		$jwe_parts = explode('.', $jwe);

		if (count($jwe_parts) != 5) {
			throw new \Exception(sprintf('JWE contains only %d components when 5 were expected', count($jwe_parts)));
		}

		$jwe = [];
		foreach(self::$jwe_part_keys as $part_key => $part_index) {
			$jwe[$part_key] = base64_decode($jwe_parts[$part_index]);
		}

		$jwe['header'] = utf8_encode($jwe['header']);
		
		self::jwe_valider_parts($jwe);

		$jwe_header = json_decode($jwe['header']);
		$jwe_header_enc = explode('-', $jwe_header->enc);

		return self::decrypt($jwe['iv'] . $jwe['cipher_text'], $key, $jwe_header_enc[0], $jwe_header_enc[1]);
	}

	private static function jwe_valider_parts($jwe = []) {
		self::jwe_validate_header($jwe['header']);

		//encryption key has to be empty
		if (!empty($jwe['enryption_key'])) {
			throw new \Exception('JWE encryption key value contains a value, but must be blank in this implementation');
		}
	}

	private static function jwe_validate_header($jwe_header = '') {
		$jwe_header = json_decode($jwe_header);
		
		if (!(!empty($jwe_header->alg) && $jwe_header->alg == 'dir')) {
			throw new \Exception(sprintf('JWE header "alg" value of "%s" is not supported', (!empty($jwe_header->alg) ? $jwe_header->alg : 'empty') ));
		}

		if (!empty($jwe_header->enc)) {
			$enc = explode('-', $jwe_header->enc);
			
			if (!(count($enc) == 2 && array_key_exists($enc[0], self::$available_crypto_methods) && array_key_exists($enc[1], self::$available_hasher_methods))) {
				throw new \Exception(sprintf('JWE header "enc" value of "%s" is not supported', $jwe_header->enc));
			}
		} else {
			throw new \Exception('Empty value for JWE header "enc" is not supported ');
		}	
	}
}