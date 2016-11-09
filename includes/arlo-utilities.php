<?php

namespace Arlo;

class Utilities {

	public static function array_ikey_exists($key,$arr) { 
		if(preg_match("/".$key."/i", join(",", array_keys($arr))))                
			return true; 
		else 
			return false; 
	} 

	private static function get_now_utc() {
		$logger = new Logger();

		do {
			//this returns, check php doc 
			$now = DateTime::createFromFormat('U', time());
			if (!is_object($now)) {
				$logger->log("Error DateTime::createFromFormat: " . implode(", ", DateTime::getLastErrors()));
			}
		} while (!is_object($now));
		
		return $now;    
    }
}