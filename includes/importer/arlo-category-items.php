<?php

namespace Arlo\Importer;

class CategoryItems extends BaseEntity {

	public function __construct($plugin, $importer, $data, $iterator = 0) {
		parent::__construct($plugin, $importer, $data, $iterator);

		$this->table_name = $this->wpdb->prefix . 'arlo_eventtemplates_categories';
	}

	protected function save_entity($item) {
		$sql = "
		UPDATE
			" . $this->table_name . "
		SET
			et_order = %d
		WHERE
			et_arlo_id = %d
		AND
			c_arlo_id = %d
		";

		$query = $this->wpdb->query( $this->wpdb->prepare($sql, !empty($item->SequenceIndex) ? $item->SequenceIndex : 0, $item->EventTemplateID, $item->CategoryID) );
		
		if ($query === false) {
			Logger::log('SQL error: ' . $this->wpdb->last_error . ' ' .$this->wpdb->last_query, $this->import_id, null, false , true);
		}
	}
}