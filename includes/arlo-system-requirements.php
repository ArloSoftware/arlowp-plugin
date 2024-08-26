<?php

namespace Arlo;

use Arlo\Utilities;

class SystemRequirements {
	public static function get_system_requirements() {
		return [
			[
				'name' => 'PHP version',
				'expected_value' => '5.5',
				'current_value' => function () {
					if (!defined('PHP_MAJOR_VERSION') || !defined('PHP_MINOR_VERSION') || !defined('PHP_RELEASE_VERSION')) {
						return 'Unknown';
					}
					return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
				},
				'check' => function($current_value, $expected_value) {
					if (!strncmp('Unknown', $current_value, 7))	return null;
					return version_compare($current_value, $expected_value) >= 0;
				}
			],
			[
				'name' => 'WordPress version',
				'expected_value' => '4.7',
				'current_value' => function () {
					if (!isset($GLOBALS['wp_version'])) {
						return 'Unknown';
					}
					return $GLOBALS['wp_version'];
				},
				'check' => function($current_value, $expected_value) {
					if (!strncmp('Unknown', $current_value, 7))	return null;
					return version_compare($current_value, $expected_value) >= 0;
				}
			],
			[
				'name' => 'Memory limit',
				'expected_value' => '64M',
				'current_value' => function () {
					$memory_limit_setting = ini_get('memory_limit');
					return Utilities::settingToMegabytes($memory_limit_setting);
				},
				'check' => function($current_value, $expected_value) {
					return intval($current_value) >= intval($expected_value) || intval($current_value) === -1;
				}
			],
			[
				'name' => 'Max execution time',
				'expected_value' => '30',
				'current_value' => function () {
					return ini_get('max_execution_time');
				},
				'check' => function($current_value, $expected_value) {
					return intval($current_value) >= intval($expected_value) || $current_value == 0;
				}
			],
			[
				'name' => 'cUrl enabled',
				'expected_value' => 'Yes',
				'current_value' => function () {
					return extension_loaded('curl') ? 'Yes': 'No';
				},
				'check' => function($current_value, $expected_value) {
					return $current_value == 'Yes';
				}
			],
			[
				'name' => 'OpenSSL or mCrypt enabled',
				'expected_value' => 'OpenSSL',
				'current_value' => function () {
					if (extension_loaded('openssl')) {
						return 'OpenSSL';
					} else if (extension_loaded('mcrypt')) {
						return 'mCrypt';
					}
					return 'None';
				},
				'check' => function($current_value, $expected_value) {
					return in_array($current_value, ['OpenSSL', 'mCrypt']);
				}
			],
			[
				'name' => 'RIJNDAEL 128 available',
				'expected_value' => 'Yes',
				'current_value' => function () {
					if (extension_loaded('openssl')) {
						return (in_array('AES-256-CBC', openssl_get_cipher_methods())
						     || in_array('aes-256-cbc', openssl_get_cipher_methods()) ? 'Yes' : 'No');
					} else if (extension_loaded('mcrypt')) {
						return (in_array(MCRYPT_RIJNDAEL_128, mcrypt_list_algorithms()) ? 'Yes' : 'No');
					}
					return 'N/A';
				},
				'check' => function($current_value, $expected_value) {
					return $current_value == 'Yes';
				}
			],
			[
				'name' => 'CBC mode available',
				'expected_value' => 'Yes',
				'current_value' => function () {
					if (extension_loaded('openssl')) {
						return (in_array('AES-256-CBC', openssl_get_cipher_methods())
						     || in_array('aes-256-cbc', openssl_get_cipher_methods()) ? 'Yes' : 'No');
					} else if (extension_loaded('mcrypt')) {
						return (in_array(MCRYPT_MODE_CBC, mcrypt_list_modes()) ? 'Yes' : 'No');
					}
					return 'N/A';
				},
				'check' => function($current_value, $expected_value) {
					return $current_value == 'Yes';
				}
			],
			[
				'name' => 'SHA512 available',
				'expected_value' => 'Yes',
				'current_value' => function () {
					return in_array('sha512', hash_algos()) ? 'Yes' : 'No';
				},
				'check' => function($current_value, $expected_value) {
					return $current_value == 'Yes';
				}
			]									
		];
	} 

	public static function overall_check() {
		$good = true;
		foreach (self::get_system_requirements() as $requirement) {
			$current_value = $requirement['current_value']();
			
			if ($requirement['check']($current_value, $requirement['expected_value']) === false) {
				$good = false;
				break;
			}
		}

		return $good;
	}
}
