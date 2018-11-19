<?php

namespace Arlo;

use Arlo\Utilities;

class Logger {
    const clean_up_days = 14;

    public static function log_error($message, $import_id = null, $timestamp = null, $successful = false) {
        self::save_log($message, $import_id, $timestamp, $successful, true);
    }

    public static function log($message, $import_id = null, $timestamp = null, $successful = false) {
        self::save_log($message, $import_id, $timestamp, $successful, false);
    }

    private static function save_log($message, $import_id = null, $timestamp = null, $successful = false, $raise_exception = false) {
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
				VALUES ( %s, %d, %s, %d ) 
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

    public static function get_log($successful = null, $limit = null) {
        global $wpdb;

        $successful = (!empty($successful) && is_bool($successful) ? $successful : null);
        $limit = (!empty($limit) && is_numeric($limit) ? $limit : null);

        $sql = "
		SELECT
			message,
			created,
			successful
		FROM
			{$wpdb->prefix}arlo_log
			". (!is_null($successful) ? "WHERE successful = " . ($successful ? "1" : "0")  : "") ."
		ORDER BY
			id DESC
        ". (!is_null($limit) ? "LIMIT " . $limit  : "") ."
		";
		
		return $wpdb->get_results($sql, ARRAY_A); 
    }

	public static function create_log_csv($limit = 1000) {
		global $wpdb;
		$limit = intval($limit);
		
        $table_name = "{$wpdb->prefix}arlo_import_lock";
        
		$fp = fopen('php://temp/maxmemory:2097125', 'w');
		if($fp === FALSE) {
			self::log('Couldn\'t create log CSV', $import_id);
		    return false;
		}
        
        $sql = '
            SELECT 
                id,
                message,
                created, 
                successful
            FROM
                ' . $wpdb->prefix . 'arlo_log
            ORDER BY
            	id DESC
            ' . 
            ($limit !== 0 ? 'LIMIT ' . $limit : '');
            
	               
        $entries = $wpdb->get_results($sql, 'ARRAY_N');
	
		if (is_array($entries) && count($entries)) {
			fputcsv($fp, array('Id', 'Log', 'CreatedDateTime', 'Successful'));
			
			foreach ($entries as $entry) {
				fputcsv($fp, $entry);
	        } 
		}
		
		rewind($fp);
		$csv = stream_get_contents($fp);
		fclose($fp);		
		
		return $csv;
	
	}  

	public static function download_log() {        
		$csv = self::create_log_csv(0);
	
        header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=arlo_sync_log.csv');
		
		echo $csv;
		exit;
	}
        
}