<?php

namespace Arlo;

class Logger {
    const clean_up_days = 14;

    public static function log($message, $import_id = null, $timestamp = null, $successful = false, $raise_exception = false) {
		global $wpdb;

        $table_name = $wpdb->prefix . "arlo_log";

        if (strtotime($timestamp) === false) {
			$now = \Arlo\Utilities::get_now_utc();
        	$timestamp = $now->format("Y-m-d H:i:s");
		}

		$wpdb->query(
			$wpdb->prepare( 
				"INSERT INTO " . $table_name . " 
				(message, import_id, created, successful) 
				VALUES ( %s, %s, %s, %d ) 
				", 
			    $message,
				$import_id,
				$timestamp,
				$successful
			)
		);

        self::clean_up_log();

        if ($raise_exception) {
            self::raise_exception($message);
        }
    }

    private static function raise_exception($message) {
        throw new \Exception($message);
    }

    private static function clean_up_log() {
        global $wpdb;

        $table_name = $wpdb->prefix . "arlo_log";

        $wpdb->query("DELETE FROM $table_name WHERE CREATED < NOW() - INTERVAL " . self::clean_up_days . " DAY ORDER BY ID ASC LIMIT 10");
    }
}