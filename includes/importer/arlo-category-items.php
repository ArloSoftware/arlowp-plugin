<?php

namespace Arlo\Importer;

use Arlo\Logger;

class CategoryItems extends BaseImporter {

	public function __construct($importer, $dbl, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
		parent::__construct($importer, $dbl, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts);

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
			throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query);
		}
	}
}