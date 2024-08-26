<?php

namespace Arlo\Importer;

use Arlo\Logger;

class Categories extends BaseImporter {

	public function __construct($importer, $dbl, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null) {
		parent::__construct($importer, $dbl, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts);

		$this->table_name = $this->dbl->prefix . 'arlo_categories';
	}

	protected function save_entity($item) {
		$slug = sanitize_title($item->CategoryID . ' ' . $item->Name);
		$query = $this->dbl->query( $this->dbl->prepare( 
			"INSERT INTO ". $this->table_name . " 
			(c_arlo_id, c_name, c_slug, c_header, c_footer, c_order, c_parent_id, import_id) 
			VALUES ( %d, %s, %s, %s, %s, %d, %d, %s ) 
			", 
			$item->CategoryID,
			$item->Name,
			$slug,
			!empty($item->Description) && !empty($item->Description->Text) ? $item->Description->Text : null,
			!empty($item->Footer) && !empty($item->Footer->Text) ? $item->Footer->Text : null,
			!empty($item->SequenceIndex) ? $item->SequenceIndex : null,
			!empty($item->ParentCategoryID) ? $item->ParentCategoryID : null,
			$this->import_id
		) );

		if ($query === false) {
			throw new \Exception('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query);
		}
	}
}