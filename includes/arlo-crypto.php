<?php

namespace ArloTraining;

use \ArloTraining\Logger;

class Crypto {
	public static $available_hasher_methods = [
		'HS512' => 'sha512',
	];

	public static $available_cipher_methods = [
		'A256CBC' => 'RIJNDAEL_128',
	];

	public static $available_cipher_modes = [
		'A256CBC' => 'MODE_CBC',
	];

	public static $jwe_part_keys = [
		'header' => 0,
		'encrypted_key' => 1,
		'iv' => 2,
		'cipher_text' => 3,
		'auth_tag' => 4,
	];

	const ENCRYPTION_KEY_LENGTH = 32;
	const MAC_KEY_LENGTH  = 32;
	const MAC_LENGTH = 32; 
	const IV_LENGTH = 16;


	public static function decrypt_gzip($fullencrypted, $key, $method, $max_length = 0) {
		$iv = substr($fullencrypted, 0, self::IV_LENGTH);
		$encrypted = substr($fullencrypted, self::IV_LENGTH, strlen($fullencrypted) - self::IV_LENGTH);

		$methods = explode('-', $method);

		$zipped = self::decrypt($iv, $encrypted, $key, $methods[0], $methods[1]);

		$unzipped = $max_length > 0 ? gzdecode($zipped, $max_length) : gzdecode($zipped);
		if ($unzipped === false) {
			return '';
		}
		if ($max_length > 0 && strlen($unzipped) >= $max_length) {
			return $unzipped;
		}

		return trim($unzipped);
	}

	public static function decrypt($iv, $encrypted, $key, $crypto_method, $hash_method) {
		if (!array_key_exists($hash_method, self::$available_hasher_methods)) {
			throw new \Exception("Hash method '" . $hash_method . "' is not supported"); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		$keys = self::derive_secondary_keys($key, $hash_method);

		$ciphertext = substr($encrypted, 0, strlen($encrypted) - self::MAC_LENGTH);
		$hmac = substr($encrypted, strlen($encrypted) - self::MAC_LENGTH);
		$hasher = self::$available_hasher_methods[ $hash_method ];

		if (!self::hmac_validation($iv, $ciphertext, $keys['mac'], $hasher, $hmac)) {
			throw new \Exception("Hmac validation failed");
		}

		//use mCrypt as fallback for retrocompatibility
		$decrypted = '';
		if (extension_loaded('openssl')) {
			$decrypted = self::decrypt_with_openssl($iv, $ciphertext, $keys['enc'], $crypto_method);
		} else {
			$decrypted = self::decrypt_with_mcrypt($iv, $ciphertext, $keys['enc'], $crypto_method);
		}
		return $decrypted;
	}


	public static function derive_secondary_keys($key, $hash_method) {
		$key = base64_decode($key);
    	$hashed_key = hash(self::$available_hasher_methods[$hash_method], $key, true);
		$last_error = error_get_last();
		if (empty($hashed_key) && isset($last_error['message'])) {
			throw new \Exception($last_error['message']); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		return [
			'mac' => substr($hashed_key, 0, self::MAC_KEY_LENGTH),
			'enc' => substr($hashed_key, self::MAC_KEY_LENGTH, self::ENCRYPTION_KEY_LENGTH)
		];
	}

	public static function hmac_validation($iv, $ciphertext, $key, $method, $hmac) {
		$computed = hash_hmac($method, $iv . $ciphertext, $key, true);

		$a = substr($hmac, 0, self::MAC_LENGTH);
		$b = substr($computed, 0, self::MAC_LENGTH);

		return hash_equals($a, $b);
	}


	private static function decrypt_with_mcrypt($iv, $ciphertext, $key, $method) {
		$algo = self::$available_cipher_methods[ $method ];
		$mode = self::$available_cipher_modes[ $method ];

		if ('RIJNDAEL_128' == $algo && 'MODE_CBC' == $mode) {
			$decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $ciphertext, MCRYPT_MODE_CBC, $iv);
			
			$last_error = error_get_last();
			if (empty($decrypted) && isset($last_error['message'])) {
				throw new \Exception('mCrypt cannot decrypt data: ' . $last_error['message']); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}
		} else {
			throw new \Exception("The cryptographic method chosen for mCrypt (" . $method . ") is not supported by the plugin"); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
		return $decrypted;
	}

	private static function decrypt_with_openssl($iv, $ciphertext, $key, $method) {
		$algo = self::$available_cipher_methods[ $method ];
		$mode = self::$available_cipher_modes[ $method ];

		if ('RIJNDAEL_128' == $algo && 'MODE_CBC' == $mode) {
			$decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
			if (empty($decrypted)) {
				throw new \Exception('OpenSSL cannot decrypt data for an unknown reason'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}
		} else {
			throw new \Exception("The cryptographic method chosen for OpenSSL (" . $method . ") is not supported by the plugin"); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
		return $decrypted;
	}


	public static function jwe_decrypt($jwe, $key) {
		$jwe_parts = explode('.', $jwe);

		if (count($jwe_parts) != 5) {
			throw new \Exception(sprintf('JWE contains only %d components when 5 were expected', count($jwe_parts))); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		$jwe = [];
		foreach(self::$jwe_part_keys as $part_key => $part_index) {
			$jwe[$part_key] = base64_decode($jwe_parts[$part_index]);
		}

		self::assert_valid_jwe_parts($jwe);

		$jwe_header = json_decode($jwe['header']);
		$jwe_header_enc = explode('-', $jwe_header->enc);

		$decrypted = self::decrypt($jwe['iv'], $jwe['cipher_text'], $key, $jwe_header_enc[0], $jwe_header_enc[1]);
		return trim($decrypted);
	}

	private static function assert_valid_jwe_parts($jwe = []) {
		self::jwe_validate_header($jwe['header']);

		// With alg=dir (direct encryption), the shared key is used as-is — no key wrapping occurs.
		// The JWE spec requires the Encrypted Key part to be empty in this case.
		// A non-empty value means the payload uses key wrapping, which this implementation does not support.
		if (!empty($jwe['encrypted_key'])) {
			throw new \Exception('JWE encryption key value contains a value, but must be blank in this implementation'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}
	}

	private static function jwe_validate_header($jwe_header = '') {
		$jwe_header = json_decode($jwe_header);
		
		if (!(!empty($jwe_header->alg) && $jwe_header->alg == 'dir')) {
			throw new \Exception(sprintf('JWE header "alg" value of "%s" is not supported', (!empty($jwe_header->alg) ? $jwe_header->alg : 'empty'))); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		if (!empty($jwe_header->enc)) {
			$enc = explode('-', $jwe_header->enc);

			$cipher_methods = self::$available_cipher_methods;
			$cipher_modes = self::$available_cipher_modes;
			$hasher_methods = self::$available_hasher_methods;
			
			if (count($enc) != 2) {
				throw new \Exception(sprintf('JWE header "enc" value of "%s" is not supported', $jwe_header->enc)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}
			if (!array_key_exists($enc[0], $cipher_methods)) {
				throw new \Exception(sprintf('JWE header "enc" value of "%s" is not supported (cipher method)', $jwe_header->enc)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}
			if (!array_key_exists($enc[0], $cipher_modes)) {
				throw new \Exception(sprintf('JWE header "enc" value of "%s" is not supported (cipher mode)', $jwe_header->enc)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}
			if (!array_key_exists($enc[1], $hasher_methods)) {
				throw new \Exception(sprintf('JWE header "enc" value of "%s" is not supported (hasher method)', $jwe_header->enc)); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
			}
		} else {
			throw new \Exception('Empty value for JWE header "enc" is not supported ');
		}	
	}
}