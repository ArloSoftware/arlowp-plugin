<?php

namespace Arlo;

require_once 'arlo-singleton.php';

use Arlo\Singleton;

class MessageHandler extends Singleton {
	
	private $wpdb;
	private $table = '';
	
	public function __construct($plugin) {
		global $wpdb;
		
		$this->wpdb = &$wpdb; 		
		$this->table = $this->wpdb->prefix . 'arlo_messages';
		$this->plugin = $plugin;
	}

	
	public function get_message_by_type_count($message_type = null, $count_dismissed = false) {		
		$count_dismissed = (isset($count_dismissed) && $count_dismissed ? true : false );
		$message_type = (!empty($message_type) ? $message_type : null);
		
		$where = ['1'];
		
		if (!$count_dismissed) {
			$where[] = ' dismissed IS NULL ';
		}
		
		if (!is_null($message_type)) {
			$where[] = " message_type = '" . esc_sql($message_type) ."'";
		}
	
		$sql = '
		SELECT 
			COUNT(1) AS num
		FROM
			' . $this->table .'
		WHERE 
			' . (implode(' AND ', $where)) . '
		';
		
		$result = $this->wpdb->get_results($sql); 
				
		return $result[0]->num;
	}
	
	
	public function set_message($message_type = '', $title = '', $message = '', $global = false) {
		if (empty($message_type)) return false;
		$utc_date = gmdate("Y-m-d H:i:s"); 
	
		$sql = '
		INSERT INTO
			' . $this->table . ' (message_type, title, message, global, created)
		VALUES
			(%s, %s, %s, %d, %s)
		';
		
		$query = $this->wpdb->query($this->wpdb->prepare($sql, $message_type, $title, $message, $global, $utc_date));
		
		if ($query) {
			return $this->wpdb->insert_id;
		} else {
			return false;
		}
	}	
	
	public function dismiss_message($id) {
		$id = intval($id);
		if ($id == 0) return false;
		
		$utc_date = gmdate("Y-m-d H:i:s"); 
	
		$sql = '
		UPDATE
			' . $this->table . ' 
		SET
			dismissed = %s
		';
		
		$query = $this->wpdb->query($this->wpdb->prepare($sql, $utc_date));
		
		return $query !== false;
	}	
	
	public function get_messages($message_type = null, $global = false) {
		$global = (isset($global) && is_bool($global) ? $global : null );
		$message_type = (!empty($message_type) ? $message_type : null);
		
		$where = [' dismissed IS NULL '];
		
		if (is_bool($global)) {
			$where[] = ' global = ' . ($global ? 1 : 0);
		}
		
		if (!is_null($message_type)) {
			$where[] = " message_type = '" . esc_sql($message_type) ."'";
		}		
		
		$sql = '
		SELECT 
			id,
			message_type,
			title,
			message
		FROM
			' . $this->table . '	
		WHERE 
			' . (implode(' AND ', $where)) . '
		';
		
		return $this->wpdb->get_results($sql);
	}
	
	
}

?>