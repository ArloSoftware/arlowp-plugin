<?php

namespace Arlo\Importer;

use Arlo\Logger;

class Categories extends BaseImporter {

	public function __construct($plugin, $importer, $data, $iterator = 0) {
		parent::__construct($plugin, $importer, $data, $iterator);

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
			@$item->Description->Text,
			@$item->Footer->Text,
			@$item->SequenceIndex,
			@$item->ParentCategoryID,
			$this->import_id
		) );

		if ($query === false) {
			Logger::log_error('SQL error: ' . $this->dbl->last_error . ' ' .$this->dbl->last_query, $this->import_id);
		}
	}
}