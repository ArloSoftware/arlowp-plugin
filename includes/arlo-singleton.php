<?php

namespace Arlo;

class Singleton {
	public static $instance;
	
	public function __construct() {
	}
	
    public static function init() {
    	return static::instance();
    }

	public static function instance() {
		if(!static::$instance) {
			$class = get_called_class();
			static::$instance = new $class();
		}
		
		return static::$instance;
	}
	
	public static function __callStatic($name, $arguments) {
    	static::instance();
    }
}