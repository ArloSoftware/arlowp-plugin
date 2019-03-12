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
					return intval($current_value) >= intval($expected_value);
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

	public static function get_diagnostic_info() {
		global $table_prefix;
		global $wpdb;
		$info = [];

		$info['site_url'] = site_url();
		$info['home_url'] = home_url();
		$info['dbname'] = $wpdb->dbname;
		$info['table_prefix'] = $table_prefix;
		$info['wp_version'] = get_bloginfo( 'version', 'display' );
		$info['db_version'] = $wpdb->db_version();
		$info['db_mysqli'] = empty( $wpdb->use_mysqli ) ? 'no' : 'yes';
		$info['wp_memory_limit'] = defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : 'Not defined';
		$info['wp_debug'] = defined( 'WP_DEBUG' ) ? WP_DEBUG : 'False';
		$info['wp_max_upload_size'] = size_format( wp_max_upload_size() );
		$info['wp_cron'] = ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? 'Disabled' : 'Enabled';

		$info['multisite'] = is_multisite() ? 'Yes' : 'No';
		if ( is_multisite() ) {
			$info['multisite_subdomain'] = is_subdomain_install() ? 'subdomain' : 'subdirectory';
			$info['multisite_blogcount'] = get_blog_count();
		}

		$info['webserver'] = ! empty( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : '';

		$info['php_version'] = function_exists( 'phpversion' ) ? phpversion() : '?';
		$info['memory_limit'] = function_exists( 'ini_get' ) ? ini_get( 'memory_limit' ) : 'Cannot find';
		$info['memory_usage'] = size_format(memory_get_usage( true ));
		$info['max_execution_time'] = function_exists( 'ini_get' ) ? ini_get( 'max_execution_time' ) : 'Cannot find';

		// TODO This should have checks that metadata exists
		$theme_info = wp_get_theme();
		$info['theme_name'] = $theme_info->get('Name');
		$info['theme_version'] = $theme_info->get('Version');
		$info['theme_folder'] = $theme_info->get_stylesheet();

		if ( is_child_theme() ) {
			$parent_info = $theme_info->parent();
			$info['theme_parent_name'] = $parent_info->get('Name');
			$info['theme_parent_version'] = $parent_info->get('Version');
			$info['theme_parent_folder'] = $parent_info->get_stylesheet();
		}
		
		// TODO dropins
		$dropins = get_dropins();

		return $info;
	}
}
