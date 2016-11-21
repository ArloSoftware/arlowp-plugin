<?php

namespace Arlo\Importer;

use Arlo\Logger;
use Arlo\Utilities;

class ImportRequest extends BaseImporter  {
	const schema_level = 100;

	private $nonce;

	private $callback_action = 'arlo_import_callback';
	
	private $snapshot_type = "Full";
	private $fields = [
		'Regions' => [
			'RegionID', 'Name',
		],
		'TimeZones' => [
			'TimeZoneID', 'Name', 'TzNames'
		],
		'Events' => [
			'EventID', 'EventTemplateID', 'Name', 'Code', 'Summary', 'Description', 'StartDateTime', 'EndDateTime', 'TimeZoneID', 'TimeZone', 'Location', 'IsFull', 'PlacesRemaining', 'AdvertisedOffers', 'SessionsDescription', 'Presenters', 'Notice', 'ViewUri', 'RegistrationInfo', 'Provider', 'TemplateCode', 'Tags', 'Credits',
		],
		'Templates' => [
			'TemplateID', 'Code', 'Name', 'Description', 'AdvertisedPresenters', 'AdvertisedDuration', 'BestAdvertisedOffers', 'ViewUri', 'RegisterInterestUri', 'Categories', 'Tags',
		],	
		'Presenters' => [
			'PresenterID', 'FirstName', 'LastName', 'ViewUri', 'Profile', 'SocialNetworkInfo',
		],
		'OnlineActivities' => [
			'OnlineActivityID', 'TemplateID', 'Name', 'Code', 'DeliveryDescription', 'ViewUri', 'ReferenceTerms', 'Credits', 'RegistrationInfo', 'AdvertisedOffers', 'Tags',
		],
		'Venues' => [
			'VenueID', 'Name', 'GeoData', 'PhysicalAddress', 'FacilityInfo', 'ViewUri',
		],
		'Categories' => [
			'CategoryID', 'ParentCategoryID', 'Name', 'SequenceIndex', 'Description', 'Footer',
		],
		'CategoryItems' => [
			'CategoryID', 'EventTemplateID', 'SequenceIndex',
		],
	];
	private $encription_type = 'A256CBC-HS512';
	private $fragment_max_size_bytes = 524288;

	protected function save_entity($item) {}

	public function run() {
		$this->nonce = Utilities::GUIDv4(true, true);

		$this->importer->set_import_entry($this->nonce);

		$retval = $this->api_client->Snapshots()->request_import($this->generate_post_data());

		if (!empty($retval->RequestID)) {
			$data = [
					'request_id' => $retval->RequestID,
					'response_json' => json_encode($retval),
				];
 
			$this->importer->update_import_entry($data);
		}

		$this->is_finished = true;
	}

	private function generate_post_data($fragment = false) {
		$data_obj = new \stdClass();

		$data_obj->SchemaLevel = self::schema_level;
		$data_obj->SnapshotType = $this->snapshot_type;
		$data_obj->Query = $this->generate_query_object();
		$data_obj->Result = $this->generate_result_object();
		$data_obj->Callback = $this->generate_callback_object();

		return $data_obj;
	}

	private function generate_query_object() {
		$data_obj = new \stdClass();

		foreach ($this->fields as $group => $field) {
			$obj_key = $group . ".Fields";
			$data_obj->$obj_key = implode(",", $field);
		}

		return $data_obj;
	}

	private function generate_result_object($fragment = false) {
		$data_obj = new \stdClass();
		$data_obj->EncryptedResponse = $this->generate_encryptedresponse_object();

		if ($fragment) {
			$data_obj->Disposition = "Fragmented";
			$data_obj->Fragmentation->FragmentMaxSizeBytes = $this->fragment_max_size_bytes;
		}

		return $data_obj;
	}

	private function generate_callback_object($fragment = false) {
		$data_obj = new \stdClass();

		$data_obj->Uri = admin_url('admin-ajax.php') . '?action=' . $this->callback_action;
		$data_obj->Nonce = $this->nonce;
		$data_obj->EncryptedResponse = $this->generate_encryptedresponse_object();

		return $data_obj;
	}
	

	private function generate_encryptedresponse_object() {
		$data_obj = new \stdClass();

		$data_obj->alg = "none";
		$data_obj->enc = $this->encription_type;

		return $data_obj;
	}
}