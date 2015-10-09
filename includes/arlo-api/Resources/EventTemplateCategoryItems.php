<?php

namespace ArloAPI\Resources;

// load main Transport class for extending
require_once 'Resource.php';

// now use it
use ArloAPI\Resources\Resource;

class EventTemplateCategoryItems extends Resource
{
	protected $apiPath = '/resources/eventtemplatecategoryitems/';
	
	public function search($fields = array(), $count = 20) {
		$data = array(
			'fields=' . implode(',', $fields),
			'top=' . $count,
			'format=json'
		);
		
		$results = $this->request(implode('&', $data));
		
		return $results;
	}

	public function getTemplateCategoriesItems($id) {
		$this->__set('api_path', $this->apiPath . $id);
		$results = $this->request();
		
		return $results;
	}

	public function getAllTemplateCategoriesItems($fields=array()) {
		return $this->search($fields, 1000)->Items;
	}
}