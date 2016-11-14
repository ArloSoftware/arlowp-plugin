<?php

namespace Arlo\Importer;

use Arlo\Logger;

class CategoryItems extends BaseEntity {

	public function __construct($plugin, $importer, $data, $iterator = 0) {
		parent::__construct($plugin, $importer, $data, $iterator);

		$this->table_name = $this->dbl->prefix . 'arlo_eventtemplates_categories';
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

		$query = $this->dbl->query( $this->dbl->prepare($sql, !empty($item->SequenceIndex) ? $item->SequenceIndex : 0, $item->EventTemplateID, $item->CategoryID) );
		
		if ($query === false) {
			Logger::log_error('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query, $this->import_id);
		}
	}
}