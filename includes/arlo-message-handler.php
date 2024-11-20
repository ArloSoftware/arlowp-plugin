<?php

namespace Arlo;

class MessageHandler {
	
	private $dbl;
	private $table = '';
	
	public function __construct($dbl) {		
		$this->dbl = &$dbl;
		$this->table = $this->dbl->prefix . 'arlo_messages';
	}

	public function get_message_by_type_count($type = null, $count_dismissed = false) {		
		$count_dismissed = (isset($count_dismissed) && $count_dismissed ? true : false );
		$type = (!empty($type) ? $type : null);
		$parameters = [];
		$where = ['1'];
		
		if (!$count_dismissed) {
			$where[] = ' dismissed IS NULL ';
		}
		
		if (!is_null($type)) {
			$where[] = " type = '%s'";
			$parameters[] = $type;
		}
	
		$sql = '
		SELECT 
			COUNT(1) AS num
		FROM
			' . $this->table .'
		WHERE 
			' . (implode(' AND ', $where)) . '
		';
		$query = $this->dbl->prepare($sql, $parameters);

		$result = $this->dbl->get_results($query); 
				
		return $result[0]->num;
	}
	
	
	public function set_message($type = '', $title = '', $message = '', $global = false) {
		if (empty($type)) return false;
		$utc_date = gmdate("Y-m-d H:i:s"); 
	
		$sql = '
		INSERT INTO
			' . $this->table . ' (type, title, message, global, created)
		VALUES
			(%s, %s, %s, %d, %s)
		';
		
		$query = $this->dbl->query($this->dbl->prepare($sql, $type, $title, $message, $global, $utc_date));
		
		if ($query) {
			return $this->dbl->insert_id;
		} else {
			return false;
		}
	}	
	
	public function dismiss_by_type($type = null) {
		$type = (!empty($type) ? $type : null);
		if (is_null($type)) return;

		$user = wp_get_current_user();	
		
		$utc_date = gmdate("Y-m-d H:i:s"); 
		
		$sql = '
		UPDATE
			' . $this->table . ' 
		SET
			dismissed = %s,
			dismissed_by = %d
		WHERE 
			type = %s
		AND
			dismissed IS NULL
		';
		
		$query = $this->dbl->query($this->dbl->prepare($sql, $utc_date, $user->ID, $type));		
	}
	
	public function dismiss_message($id) {
		$id = intval($id);
		if ($id == 0) return false;
		
		$user = wp_get_current_user();		
		
		$utc_date = gmdate("Y-m-d H:i:s"); 
	
		$sql = '
		UPDATE
			' . $this->table . ' 
		SET
			dismissed = %s,
			dismissed_by = %d
		WHERE
			id = %d
		AND
			dismissed IS NULL
		';
		
		$query = $this->dbl->query($this->dbl->prepare($sql, $utc_date, $user->ID, $id));
		
		return $query !== false;
	}	

	public function delete_messages($type) {			
		$sql = '
			DELETE FROM 
				' . $this->table . ' 
			WHERE type = %s
		';

		$query = $this->dbl->query($this->dbl->prepare($sql, $type));
	}	


	public function get_messages($type = null, $global = false) {
		$global = (isset($global) && is_bool($global) ? $global : null );
		$type = (!empty($type) ? $type : null);
		$parameters = [];
		$where = [' dismissed IS NULL '];
		
		if (is_bool($global)) {
			$where[] = ' global = ' . ($global ? 1 : 0);
		}
		
		if (!is_null($type)) {
			$where[] = " type = '%s'";
			$parameters[] =  $type;
		}		
		
		$sql = '
		SELECT 
			id,
			type,
			title,
			message,
			global
		FROM
			' . $this->table . '	
		WHERE 
			' . (implode(' AND ', $where)) . '
		';

		$query = $this->dbl->prepare($sql, $parameters);

		$items = $this->dbl->get_results($query);
		array_map(function($item) {
			$item->is_dismissable = true;
		}, $items); 
		
		return $items;
	}
	
	
}
