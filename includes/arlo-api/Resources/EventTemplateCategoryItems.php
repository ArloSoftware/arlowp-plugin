<?php

namespace ArloAPI\Resources;

// load main Transport class for extending
require_once 'Resource.php';

// now use it
use ArloAPI\Resources\Resource;

class EventTemplateCategoryItems extends Resource
{
	protected $apiPath = '/resources/eventtemplatecategoryitems/';
	
	public function search($fields = array(), $count = 20, $category_ids = array(), $skip = 0) {
		$data = array(
			'fields=' . implode(',', $fields),
			'categoryIDs='. implode(',', $category_ids),
			'top=' . $count,
			'format=json',
			'skip=' . $skip,
			'includeTotalCount=true'
		);
				
		$results = $this->request(implode('&', $data));
		
		return $results;
	}

	public function getTemplateCategoriesItems($id) {
		$this->__set('api_path', $this->apiPath . $id);
		$results = $this->request();
		
		return $results;
	}

	public function getAllTemplateCategoriesItems($fields=array(), $category_ids = array()) {
		$maxCount = 200;
		$categoryCount = 32;
		$items = [];
				
		$category_iteration = ceil(count($category_ids) / $categoryCount);
				
		for ($c = 0; $c < $category_iteration; $c++) {

			$categories = array_slice($category_ids, $c * $categoryCount , $categoryCount);
			
			$result = $this->search($fields, $maxCount, $categories);
						
			$items = array_merge($items, $result->Items);
			
			if($result->TotalCount > $maxCount) {
				$iterate = ceil($result->TotalCount/$maxCount)-1;// we've already gone once - minus 1
				
				for($i=1;$i<=$iterate;$i++) {
					$items = array_merge($items, $this->search($fields, $maxCount, $categories, $i*$maxCount)->Items);
				}
			}	
		}
	
		return $items;
	}
}