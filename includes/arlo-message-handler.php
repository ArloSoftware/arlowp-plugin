<?php

namespace ArloTraining;

use ArloTraining\CacheControl;

class MessageHandler {
	
	public function __construct() {		
	}

	public function get_message_by_type_count($type = null, $count_dismissed = false) {		
		global $wpdb;
		$count_dismissed = (isset($count_dismissed) && $count_dismissed ? true : false );
		$type = (!empty($type) ? $type : null);
		$parameters = [];
		$where = ['1'];
		
		if (!$count_dismissed) {
			$where[] = ' dismissed IS NULL ';
		}
		
		if (!is_null($type)) {
			$where[] = " type = %s";
			$parameters[] = $type;
		}
	
		$sql = "
		SELECT 
			COUNT(1) AS num
		FROM
			{$wpdb->prefix}arlo_messages
		WHERE 
			" . (implode(' AND ', $where)) . "
		";
		$result = CacheControl::fetch_results(Utilities::prepare_sql($sql, $parameters), OBJECT, CacheControl::GROUP_MESSAGES); 
				
		return $result[0]->num;
	}
	
	public function set_message($type = '', $title = '', $message = '', $global = false) {
		global $wpdb;
		if (empty($type)) return false;
		$utc_date = gmdate("Y-m-d H:i:s"); 
	
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache is reset after this insert operation. Direct database query is required for custom table.
		$query = $wpdb->query($wpdb->prepare(
			"INSERT INTO
				{$wpdb->prefix}arlo_messages (type, title, message, global, created)
			VALUES
				(%s, %s, %s, %d, %s)", 
			$type, $title, $message, $global, $utc_date));
		
		CacheControl::cache_delete(CacheControl::GROUP_MESSAGES);

		if ($query) {
			return $wpdb->insert_id;
		} else {
			return false;
		}
	}	
	
	public function dismiss_by_type($type = null) {
		global $wpdb;
		$type = (!empty($type) ? $type : null);
		if (is_null($type)) return;

		$user = wp_get_current_user();	
		
		$utc_date = gmdate("Y-m-d H:i:s"); 
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache is reset after this update operation. Direct database query is required for custom table.
		$query = $wpdb->query($wpdb->prepare("
		UPDATE
			{$wpdb->prefix}arlo_messages 
		SET
			dismissed = %s,
			dismissed_by = %d
		WHERE 
			type = %s
		AND
			dismissed IS NULL
		", $utc_date, $user->ID, $type));
		CacheControl::cache_delete(CacheControl::GROUP_MESSAGES);		
	}
	
	public function dismiss_by_type_and_title( $type = null, $title = null ) {
		global $wpdb;
		if ( empty( $type ) || empty( $title ) ) return;

		$user     = wp_get_current_user();
		$utc_date = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache is reset after this update operation. Direct database query is required for custom table.
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}arlo_messages
			 SET dismissed = %s, dismissed_by = %d
			 WHERE type = %s AND title = %s AND dismissed IS NULL",
			$utc_date, $user->ID, $type, $title
		) );
		CacheControl::cache_delete( CacheControl::GROUP_MESSAGES );
	}

	public function dismiss_message($id) {
		global $wpdb;
		$id = intval($id);
		if ($id == 0) return false;
		
		$user = wp_get_current_user();		
		
		$utc_date = gmdate("Y-m-d H:i:s"); 
	
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache is reset after this update operation. Direct database query is required for custom table.
		$query = $wpdb->query($wpdb->prepare("
		UPDATE
			{$wpdb->prefix}arlo_messages 
		SET
			dismissed = %s,
			dismissed_by = %d
		WHERE
			id = %d
		AND
			dismissed IS NULL
		", $utc_date, $user->ID, $id));
		CacheControl::cache_delete(CacheControl::GROUP_MESSAGES);
		
		return $query !== false;
	}	

	public function delete_messages($type) {			
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cache is reset after this insert operation. Direct database query is required for custom table.
		$query = $wpdb->query($wpdb->prepare("
			DELETE FROM 
				{$wpdb->prefix}arlo_messages 
			WHERE type = %s
		", $type));
		CacheControl::cache_delete(CacheControl::GROUP_MESSAGES);
	}	


	public function get_messages($type = null, $global = false) {
		global $wpdb;
		$global = (isset($global) && is_bool($global) ? $global : null );
		$type = (!empty($type) ? $type : null);
		$parameters = [];
		$where = [' dismissed IS NULL '];
		
		if (is_bool($global)) {
			$where[] = ' global = ' . ($global ? 1 : 0);
		}
		
		if (!is_null($type)) {
			$where[] = " type = %s";
			$parameters[] =  $type;
		}		
		
		$sql = "
		SELECT 
			id,
			type,
			title,
			message,
			global
		FROM
			{$wpdb->prefix}arlo_messages	
		WHERE 
			" . (implode(' AND ', $where)) . "
		";

		$items = CacheControl::fetch_results(Utilities::prepare_sql($sql, $parameters), OBJECT, CacheControl::GROUP_MESSAGES);
		array_map(function($item) {
			$item->is_dismissable = true;
		}, $items); 
		
		return $items;
	}
	
	
}
