<?php

namespace ArloAPI\Resources;

// load main Transport class for extending
require_once 'Resource.php';

// now use it
use ArloAPI\Resources\Resource;

class EventTemplateSearch extends Resource
{
	protected $apiPath = '/resources/eventtemplatesearch';

	public function search($fields = array(), $count = 20, $skip = 0, $region) {
		$data = array(
			'fields=' . implode(',', $fields),
			'top=' . $count,
			'includeTotalCount=true',
			'skip=' . $skip,
			'format=json'
		);
		
		if (!empty($region)) {
			$data[] = 'region=' . $region;
		}
		
		$results = $this->request(implode('&', $data));
		
		return $results;
	}
	
	/* Helper functions */
	
	/**
	 * getAllEventTemplates
	 *
	 * A wrapper for search() providing an easy way to return all events
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function getAllEventTemplates($fields = array(), $regions = array()) {
		$maxCount = 10;
		
		if (!(is_array($regions) && count($regions))) {
			$regions = [''];
		}		
		
		foreach($regions as $region) {
			$result = $this->search($fields, $maxCount, 0, $region);
				
			$items[$region] = $result->Items;
			
			// get items over and above the max 200 imposed by the API
			// Dirty... but is a limitation of the public API
			if($result->TotalCount > $maxCount) {
				$iterate = ceil($result->TotalCount/$maxCount)-1;// we've already gone once - minus 1
				
				for($i=1;$i<=$iterate;$i++) {
					$items[$region] = array_merge($items[$region], $this->search($fields, $maxCount, $i*$maxCount, $region)->Items);
				}
			}
			
		}
		
		return $items;
	}
}