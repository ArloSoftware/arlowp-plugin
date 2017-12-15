<?php

namespace Arlo;

use Arlo\Entities\Categories as CategoriesEntity;

class Utilities {

	public static function array_ikey_exists($key,$arr) { 
		if(preg_match("/".$key."/i", join(",", array_keys($arr))))                
			return true; 
		else 
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

    public static function clean_string_url_parameter($parameter_name) {
        return !empty($_GET[$parameter_name]) ? wp_unslash($_GET[$parameter_name]) : wp_unslash(urldecode(get_query_var($parameter_name)));
    }

    public static function clean_int_url_parameter($parameter_name) {
        if (isset($_GET[$parameter_name])) {
            return intval($_GET[$parameter_name]);
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
        return self::get_att_generic($name, $atts, $string_parameter);
    }

    public static function get_att_int($name, $atts = []) {
        $int_parameter = self::clean_int_url_parameter('arlo-'.$name);
        $prioritised = self::get_att_generic($name, $atts, $int_parameter);
        return intval($prioritised);
    }

    private static function get_att_generic($name, $atts, $url_parameter) {
        $on_global = (isset($atts[$name]) ? $atts[$name] : null);
        $by_page = (isset($GLOBALS['arlo_filter_base'][$name][0]) ? $GLOBALS['arlo_filter_base'][$name][0] : null);
        return self::prioritise_att($url_parameter, $on_global, $by_page);
    }

    private static function prioritise_att($url_att, $shortcode_att, $bypage_att) {
        //1. from url parameter a.k.a. user specified
        if (!empty($url_att) || $url_att == "0") {
            return $url_att;
        }
        //2. specified on the global shortcode itself
        if (!is_null($shortcode_att)) {
            return $shortcode_att;
        }
        //3. filtered by page
        if (!is_null($bypage_att)) {
            return $bypage_att;
        }
        return '';
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
        $url = parse_url($url);
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
        if (parse_url($rel, PHP_URL_SCHEME) != '' || empty($rel)) {
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
        $parameter = \Arlo\Utilities::clean_string_url_parameter('arlo-' . $filter_name);
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
}