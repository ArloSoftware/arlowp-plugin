<?php

namespace ArloTraining;

use ArloTraining\Entities\Categories as CategoriesEntity;

class Utilities {

	public static function prepare_sql($sql, $parameters = array()) {
		global $wpdb;

		if (empty($parameters)) {
			return $sql;
		}

		return $wpdb->prepare($sql, $parameters); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.
	}

	/**
	 * Case-insensitive array key existence check.
	 *
	 * @param string $key The key to search for.
	 * @param array  $arr The array whose keys are checked.
	 * @return bool True if a key matching $key (case-insensitive) exists in $arr.
	 */
	public static function array_ikey_exists($key, $arr) {
		foreach ($arr as $k => $_value) {
			if (strcasecmp((string) $k, (string) $key) === 0) {
				return true;
			}
		}
		return false;
	}

	public static function get_now_utc() {
		$logger = new Logger();

		do {
			//this returns, check php doc 
			$now = \DateTime::createFromFormat('U', time());
			if (!is_object($now)) {
				$logger->log("Error DateTime::createFromFormat: " . implode(", ", DateTime::getLastErrors()));
			}
		} while (!is_object($now));
		
		return $now;    
    }

    public static function get_filter_keys_string_array($name, $atts = []) {
        $url_parameter = self::clean_string_url_parameter('arlo-'.$name);
        $global_att = self::get_shortcode_att_string_array($name, $atts);
        $by_page = self::get_filter_setting_string_array($name);
        return self::get_only_prioritised_filter_array($url_parameter, $global_att, $by_page);
    }

    public static function get_filter_keys_int_array($name, $atts = []) {
        $url_parameter = self::clean_int_url_parameter('arlo-'.$name);
        $global_att = self::get_shortcode_att_int_array($name, $atts);
        $by_page = self::get_filter_setting_int_array($name);
        return self::get_only_prioritised_filter_array($url_parameter, $global_att, $by_page);
    }

    private static function get_only_prioritised_filter_array($url_parameter, $shortcode_att, $bypage_filter) {
        //1. from url parameter a.k.a. user specified
        if (!empty($url_parameter) || $url_parameter == "0") {
            return [$url_parameter];
        }
        //2. specified on the global shortcode itself
        if (count($shortcode_att)) {
            return $shortcode_att;
        }
        //3. filtered by page
        if (count($bypage_filter)) {
            return $bypage_filter;
        }
        return [];
    }

    private static function get_shortcode_att_string_array($att_name, $atts) {
        if (!isset($atts[$att_name])) {
            return [];
        }
        if (!is_array($atts[$att_name])) {
            return [$atts[$att_name]];
        }
        return $atts[$att_name];
    }

    private static function get_shortcode_att_int_array($att_name, $atts) {
        if (!isset($atts[$att_name])) {
            return [];
        }
        if (!is_array($atts[$att_name])) {
            return [ self::to_int_or_null($atts[$att_name]) ];
        }
        return array_map('self::to_int_or_null', $atts[$att_name]);
    }

    private static function get_filter_setting_string_array($att_name) {
        if (!isset($GLOBALS['arlo_filter_base'][$att_name])) {
            return [];
        }
        return $GLOBALS['arlo_filter_base'][$att_name];
    }

    private static function get_filter_setting_int_array($att_name) {
        if (!isset($GLOBALS['arlo_filter_base'][$att_name])) {
            return [];
        }
        return array_map('self::to_int_or_null', $GLOBALS['arlo_filter_base'][$att_name]);
    }

    private static function to_int_or_null($to_be_converted) {
        return (is_numeric($to_be_converted) ? intval($to_be_converted) : null);
    }

	public static function filter_string_polyfill( int $input, string $input_name ): string {
		$value = filter_input( $input, $input_name );
		if ( is_null( $value ) ) {
			// filter_input() returns null on some FastCGI/PHP-FPM stacks even when
			// the parameter is present. Fall back to the superglobal directly.
			//
			// Only INPUT_GET, INPUT_POST, and INPUT_COOKIE are slashed by WordPress
			// via wp_magic_quotes(), so wp_unslash() is only correct for those types.
			// For any other $input type (INPUT_SERVER, INPUT_ENV, etc.), the
			// superglobal fallback is not supported — return empty string safely.
			if ( $input === INPUT_POST ) {
				$superglobal = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verification is the caller's responsibility; this helper only sanitises the raw value.
			} elseif ( $input === INPUT_GET ) {
				$superglobal = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is the caller's responsibility; this helper only sanitises the raw value.
			} elseif ( $input === INPUT_COOKIE ) {
				$superglobal = $_COOKIE;
			} else {
				return '';
			}
			$value = $superglobal[ $input_name ] ?? '';
			// Reject non-scalar values (e.g. ?param[]=x) to avoid "Array to string
			// conversion" warnings and prevent array literals reaching nonce checks.
			if ( is_array( $value ) || is_object( $value ) ) {
				return '';
			}
			return sanitize_text_field( wp_unslash( (string) $value ) );
		}
		// filter_input() reads pre-slash SAPI data — wp_unslash() is not appropriate here.
		// Reject non-scalar values for the same reason as the fallback path above.
		if ( is_array( $value ) || is_object( $value ) ) {
			return '';
		}
		return sanitize_text_field( (string) $value );
	}
	
    /**
     * Returns the src attribute value of the first <img> tag in $html, or null
     * if no matching tag is found. Handles both single- and double-quoted attributes.
     *
     * @param mixed $html Candidate HTML fragment to search.
     * @return string|null
     */
    public static function try_parse_first_image_src( $html ) {
        if ( ! is_string( $html ) || $html === '' ) {
            return null;
        }

        if ( preg_match( '/<img\b[^>]*?\ssrc=["\']([^"\']+)["\'][^>]*>/i', $html, $matches ) ) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Returns $value only if it is a plugin-relative asset path.
     *
     * Intended for shortcode attributes that are concatenated with a plugin-root
     * URL constant. Rejects absolute URLs, scheme-relative URLs, raw or
     * percent-encoded leading slashes, backslashes, dot segments, queries, and
     * fragments so an admin-authored attribute cannot escape the expected plugin
     * asset path shape.
     *
     * @param mixed $value The candidate path string.
     * @param mixed $fallback Returned when $value is not a valid plugin-relative path.
     * @return mixed
     */
    public static function sanitize_relative_url( $value, $fallback = '' ) {
        if ( ! is_string( $value ) || $value === '' ) {
            return $fallback;
        }

        $parts = wp_parse_url( $value );
        if ( false === $parts ) {
            return $fallback;
        }

        if ( isset( $parts['scheme'] ) || isset( $parts['host'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
            return $fallback;
        }

        $path = isset( $parts['path'] ) ? (string) $parts['path'] : $value;
        $decoded_path = rawurldecode( $path );

        if ( 0 === strpos( $path, '/' ) || 0 === strpos( $decoded_path, '/' ) ) {
            return $fallback;
        }

        if ( false !== strpos( $path, '\\' ) || false !== strpos( $decoded_path, '\\' ) ) {
            return $fallback;
        }

        if ( preg_match( '#(^|/)\.\.?(?:/|$)#', $path ) || preg_match( '#(^|/)\.\.?(?:/|$)#', $decoded_path ) ) {
            return $fallback;
        }

        return $value;
    }

    public static function clean_string_url_parameter($parameter_name) {
        $parameter_value = self::filter_string_polyfill(INPUT_GET, $parameter_name);
        if ( $parameter_value !== '' ) {
            return $parameter_value;
        }
        $query_var = get_query_var($parameter_name);
        // get_query_var() can return an array if the var was injected as ?param[]=x.
        // urldecode() throws a TypeError on PHP 8+ when passed an array.
        if ( is_array( $query_var ) || is_object( $query_var ) ) {
            return '';
        }
        return sanitize_text_field( wp_unslash( urldecode( (string) $query_var ) ) );
    }

    public static function clean_int_url_parameter($parameter_name) {
        $parameter_value =  self::filter_string_polyfill(INPUT_GET, $parameter_name);
        if (!empty($parameter_value)) {
            return intval($parameter_value);
        } else {
            $value = get_query_var($parameter_name);
            if (is_numeric($value)) {
                return intval($value);
            }
        }

        return null;
    }

    public static function get_att_string($name, $atts = []) {
        $string_parameter = self::clean_string_url_parameter('arlo-'.$name);
        return !empty($string_parameter) || $string_parameter == "0" ? $string_parameter : ( is_array($atts) && array_key_exists($name, $atts) ? $atts[$name] : '' );
    }

    public static function get_att_int($name, $atts = []) {
        $int_parameter = self::clean_int_url_parameter('arlo-'.$name);
        return !empty($int_parameter) || $int_parameter == "0" ? $int_parameter : ( is_array($atts) && array_key_exists($name, $atts) ? intval($atts[$name]) : '' );
    }

    public static function process_att($new_atts_array, $callback, $att_name = '', $atts = [], $value = null) {
        if (!empty($callback) && is_callable($callback))
            $value = call_user_func($callback, $att_name, $atts);
       
		if (!is_null($value) && (!empty($value) || is_numeric($value))) {
			$new_atts_array[$att_name] = $value;
        }
        
		return $new_atts_array;
	}
    
    public static function remove_url_protocol($url) {
        $url = wp_parse_url($url);
        unset($url['scheme']);
        return '//'.implode($url);
    }

	public static function GUIDv4 ($trim = true, $remove_hyphens = false) {
        

        // Windows
        if (function_exists('com_create_guid') === true) {
            if ($trim === true)
                $guid = trim(com_create_guid(), '{}');
            else
                $guid = com_create_guid();
        }

        // OSX/Linux
        if (function_exists('openssl_random_pseudo_bytes') === true) {
            $data = openssl_random_pseudo_bytes(16);
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);    // set version to 0100
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);    // set bits 6-7 to 10
            $guid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }

        if ($remove_hyphens) {
            return str_replace('-', '', $guid); 
        }

        return $guid;
    }
    
    public static function get_random_int() {
        $guid = explode("-", self::GUIDv4());
        
        return substr((string)hexdec($guid[0]), 0, 8);
    }

    public static function glob_recursive($pattern, $flags = 0) {
        $files = glob($pattern, $flags);
        
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
            $files = array_merge($files, self::glob_recursive($dir.'/'.basename($pattern), $flags));
        }
        
        return $files;
    }

    public static function get_absolute_url($rel) {
        if (wp_parse_url($rel, PHP_URL_SCHEME) != '' || empty($rel)) {
            return ($rel);
        }

        return (get_home_url() . $rel);
    }

    public static function convert_string_to_int_array($string) {
        if (is_string($string)) {
            $string = explode(',', $string);
        }

        if (!empty($string)) {
            return array_filter(
                array_map(function($int) {
                    return intval($int);
                }, $string), 
                function($int) {
                    return $int >= 0;
                });
        }

        return [];
    }

    public static function convert_string_to_string_array($string) {
        if (is_string($string)) {
            $string = explode(',', $string);
        }

        if (!empty($string)) {
            return array_filter(
                array_map(function($s) {
                    return trim($s);
                }, $string), 
                function($s) {
                    return !empty($s);
                });
        }

        return [];
    }

    public static function set_base_filter($template_name, $filter_name = '', $filter_settings = [], $atts = [], &$stored_atts = [], $callback = '', $callback_parameters = [], $is_hidden = false ) {
        $parameter = \ArloTraining\Utilities::clean_string_url_parameter('arlo-' . $filter_name);
        $filter_setting_section = ($is_hidden ? 'hiddenfilters' : 'showonlyfilters');
        $filter_setting_name = $filter_name;
        $filter_name = ($is_hidden ? $filter_name . 'hidden' : $filter_setting_name);

        if (is_array($atts) && count($atts) && !empty($atts[$filter_name])) {
            
            $value = $atts[$filter_name];
            
            $value = self::call_user_func_with_callback($value, $callback, $callback_parameters);

            $GLOBALS['arlo_filter_base'][$filter_name] = $value;              

            if (!isset($stored_atts[$filter_name])) {
                $stored_atts[$filter_name] = $GLOBALS['arlo_filter_base'][$filter_name];
            }

        } else if (isset($filter_settings[$filter_setting_section]) && isset($filter_settings[$filter_setting_section][$template_name]) && isset($filter_settings[$filter_setting_section][$template_name][$filter_setting_name])) {
            //this is always an array, coming from the admin UI
            $value = array_values($filter_settings[$filter_setting_section][$template_name][$filter_setting_name]);

            $value = self::call_user_func_with_callback($value, $callback, $callback_parameters);

            $GLOBALS['arlo_filter_base'][$filter_name] = $value;
            
            if (empty($parameter) || $is_hidden) {
                $stored_atts[$filter_name] = $GLOBALS['arlo_filter_base'][$filter_name];
            }
        }
    }

    public static function call_user_func_with_callback($value, $callback = '', $callback_parameters = []) {
        $parameters = [$value];

        if (is_array($callback_parameters)) {
            $parameters = array_merge($parameters, $callback_parameters);
        } else if (!empty($callback_parameters)) {
            $parameters[] = $callback_parameters;
        }

        if (!empty($callback) && is_callable($callback)) {
            $value = call_user_func_array($callback, $parameters);
        }

        return $value;
    }

    public static function settingToMegabytes($setting) {
        if (strpos($setting, 'G')) {
            return (intval($setting) * 1024) . 'M';
        }
        return $setting;
    }

    /**
     * Normalise and validate a platform name submitted via the settings form.
     *
     * Strips any accidental protocol prefix and validates the DNS label format.
     * When the normalised value differs from $current, probes the Arlo API to
     * confirm the platform exists.
     *
     * Does not read options or queue admin notices — those are the caller's concern.
     *
     * @param string $raw     Raw value from the form field.
     * @param string $current Previously-stored platform name (used to skip the
     *                        API probe when the value has not changed).
     * @return string Normalised platform name.
     * @throws PlatformNameException With the failure code as the message:
     *         invalid_format|timeout|ssl_error|redirect|not_found|server_error|
     *         unexpected_http|invalid_content_type|request_failed.
     */
    public static function sanitize_and_validate_platform_name( $raw, $current = '' ) {
        // Normalise: strip accidental protocol prefix, trim whitespace and trailing slashes,
        // then strip a pasted '.arlo.co' suffix so users can paste the full platform URL
        // (e.g. https://myplatform.arlo.co/) and still store the bare label.
        $value = rtrim(
            preg_replace( '#^https?://#i', '', trim( sanitize_text_field( wp_unslash( $raw ) ) ) ),
            '/'
        );
        $value = preg_replace( '#\.arlo\.co$#i', '', $value );

        if ( empty( $value ) ) {
            return $value;
        }

        // Must be a valid DNS label: letters, numbers and hyphens, no leading/trailing hyphen,
        // and no longer than 63 characters (RFC 1035 §2.3.4).
        if ( ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/i', $value ) || strlen( $value ) > 63 ) {
            throw new PlatformNameException( 'invalid_format' );
        }

        // Normalise $current using the same rules so legacy stored values (e.g. with a
        // protocol prefix or trailing slash) still match and don't trigger a redundant probe.
        $current_normalised = rtrim(
            preg_replace( '#^https?://#i', '', trim( (string) $current ) ),
            '/'
        );

        // Skip the API probe when the value has not changed.
        if ( $value === $current_normalised ) {
            return $value;
        }

        $probe = self::validate_platform_name( $value );
        if ( ! $probe['valid'] ) {
            throw new PlatformNameException( $probe['code'] ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the settings save handler; message is an internal validation code, not rendered in any browser context.
        }

        return $value;
    }

    /**
     * Probe the Arlo public API to confirm a platform name is reachable.
     *
     * @param string $platform_name The normalised platform name to validate (bare label, no domain suffix).
     * @return array {
     *     @type bool        $valid       True if the platform responded correctly.
     *     @type string      $code        One of: ok|timeout|ssl_error|not_found|redirect|server_error|
     *                                   unexpected_http|invalid_content_type|request_failed.
     *     @type int|null    $http_status HTTP status code, or null on connection error.
     * }
     */
    public static function validate_platform_name( $platform_name ) {
        $url        = 'https://' . $platform_name . '.arlo.co/api/2012-02-01/pub/resources/eventsearch?top=0';
        $start_time = microtime( true );
        $response   = wp_remote_get( $url, [ 'timeout' => 5, 'sslverify' => true, 'redirection' => 0 ] );
        $duration   = (int) round( ( microtime( true ) - $start_time ) * 1000 );

        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            if ( stripos( $error_message, 'SSL' ) !== false ) {
                $code = 'ssl_error';
            } elseif ( stripos( $error_message, 'timed out' ) !== false ) {
                $code = 'timeout';
            } else {
                $code = 'request_failed';
            }
            Logger::log( sprintf( 'Platform name validation failed. URL: %s | Error: %s | Duration: %dms', $url, $error_message, $duration ) );
            return [ 'valid' => false, 'code' => $code, 'http_status' => null ];
        }

        $http_status  = (int) wp_remote_retrieve_response_code( $response );
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        $content_type = is_array( $content_type ) ? implode( ', ', $content_type ) : (string) $content_type;
        $content_type_log = trim( $content_type ) !== '' ? $content_type : '(None)';

        if ( $http_status >= 200 && $http_status <= 299 ) {
            if ( stripos( $content_type, 'application/json' ) === false ) {
                Logger::log( sprintf( 'Platform name validation failed. URL: %s | HTTP status: %d | Content-Type: %s | Duration: %dms', $url, $http_status, $content_type_log, $duration ) );
                return [ 'valid' => false, 'code' => 'invalid_content_type', 'http_status' => $http_status ];
            }
            return [ 'valid' => true, 'code' => 'ok', 'http_status' => $http_status ];
        }

        if ( $http_status >= 300 && $http_status <= 399 ) {
            Logger::log( sprintf( 'Platform name validation failed. URL: %s | HTTP status: %d | Content-Type: %s | Duration: %dms', $url, $http_status, $content_type_log, $duration ) );
            return [ 'valid' => false, 'code' => 'redirect', 'http_status' => $http_status ];
        }

        if ( $http_status === 404 ) {
            Logger::log( sprintf( 'Platform name validation failed. URL: %s | HTTP status: %d | Content-Type: %s | Duration: %dms', $url, $http_status, $content_type_log, $duration ) );
            return [ 'valid' => false, 'code' => 'not_found', 'http_status' => $http_status ];
        }

        if ( $http_status >= 500 && $http_status <= 599 ) {
            Logger::log( sprintf( 'Platform name validation failed. URL: %s | HTTP status: %d | Content-Type: %s | Duration: %dms', $url, $http_status, $content_type_log, $duration ) );
            return [ 'valid' => false, 'code' => 'server_error', 'http_status' => $http_status ];
        }

        // Other 4xx or unrecognised status.
        Logger::log( sprintf( 'Platform name validation failed. URL: %s | HTTP status: %d | Content-Type: %s | Duration: %dms', $url, $http_status, $content_type_log, $duration ) );
        return [ 'valid' => false, 'code' => 'unexpected_http', 'http_status' => $http_status ];
    }

}
