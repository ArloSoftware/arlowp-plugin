<?php

namespace ArloTraining\Importer;

use ArloTraining\API\Transports\Transport;

class ImportRequest extends BaseImporter  {
	const schema_level = 100;
	const FRAGMENT_MAX_BYTE_SIZE = 10485760;
	const FRAGMENT_DEFAULT_BYTE_SIZE = 524288;	

	private $nonce;

	private $callback_action = 'arlo_import_callback';
	
	private $snapshot_type = "Full";
	private $fields = [
		'Regions' => [
			'RegionID', 'Name',
		],
		'TimeZones' => [
			'TimeZoneID', 'Name', 'WindowsTzID', 'UtcOffset'
		],
		'Events' => [
			'EventID', 'EventTemplateID', 'Name', 'Code', 'Description', 'Summary', 'StartDateTime', 'EndDateTime', 'StartTimeZoneAbbr', 'EndTimeZoneAbbr', 'TimeZoneID', 'Location', 'IsFull', 'PlacesRemaining', 'AdvertisedOffers', 'SessionsDescription', 'Presenters', 'Notice', 'ViewUri', 'RegistrationInfo', 'Provider', 'TemplateCode', 'Tags', 'Credits', 'Sessions.EventID', 'Sessions.Name', 'Sessions.Code', 'Sessions.Summary', 'Sessions.StartDateTime', 'Sessions.EndDateTime', 'Sessions.StartTimeZoneAbbr', 'Sessions.EndTimeZoneAbbr', 'Sessions.TimeZoneID', 'Sessions.Location', 'Sessions.IsFull', 'Sessions.PlacesRemaining','Sessions.AdvertisedOffers', 'Sessions.Presenters', 'Sessions.Tags'
		],
		'Templates' => [
			'TemplateID', 'Code', 'Name', 'Description', 'AdvertisedPresenters', 'AdvertisedDuration', 'BestAdvertisedOffers', 'ViewUri', 'RegisterInterestUri', 'RegisterPrivateInterestUri', 'Categories', 'Tags', 'Credits', 'Media',
		],	
		'Presenters' => [
			'PresenterID', 'FirstName', 'LastName', 'ViewUri', 'Profile', 'SocialNetworkInfo',
		],
		'OnlineActivities' => [
			'OnlineActivityID', 'TemplateID', 'Name', 'Code', 'DeliveryDescription', 'ViewUri', 'ReferenceTerms', 'Credits', 'RegistrationInfo', 'AdvertisedOffers', 'Tags',
		],
		'Venues' => [
			'VenueID', 'Name', 'GeoData', 'PhysicalAddress', 'FacilityInfo', 'ViewUri', 'LocationName',
		],
		'Categories' => [
			'CategoryID', 'ParentCategoryID', 'Name', 'SequenceIndex', 'Description', 'Footer',
		],
		'CategoryItems' => [
			'CategoryID', 'EventTemplateID', 'SequenceIndex',
		],
	];
	private $encription_type = 'A256CBC-HS512';

	private $fragmentation = true;
	public $fragment_size;

	protected function save_entity($item) {}

	public function run() {
		if (!$this->api_client) {
			throw new \ArloTraining\SchedulerException(
				'Platform name is not configured. Please set it under Arlo Settings → General.'
			);
		}

		do_action('arlo_import_starting');

		$this->nonce = SnapshotHandler::generate_nonce();
		$platform_hostname = $this->get_platform_hostname();

		$this->check_fragment_byte_size();
		$snapshot_request_post_data = $this->generate_post_data($this->fragmentation);

		$import_id = $this->importer->set_import_entry($this->nonce);

		try {
			$snapshot_request_response = $this->api_client->Snapshots()->request_import($snapshot_request_post_data);

			if (!is_object($snapshot_request_response) || empty($snapshot_request_response->RequestID)) {
				throw new \ArloTraining\PlatformAccessHttpException('Snapshot request returned no RequestID');
			}

			$snapshot_request_response->ArloMetadata = (object) [
				'PlatformHostname' => $platform_hostname,
			];
			$data = [
					'request_id' => $snapshot_request_response->RequestID,
					'response_json' => json_encode($snapshot_request_response),
				];

			$this->importer->update_import_entry($data);
		} catch (\Throwable $e) {
			try {
				$this->importer->delete_import_entry($import_id);
			} catch (\Throwable $delete_exception) {
				// Best-effort teardown only: preserve the original request/update failure
				// so platform-access errors still propagate with their real type/message
				// even when deleting the transient import row also fails.
			}
			throw $e;
		}

		$this->is_finished = true;
	}

	private function get_platform_hostname() {
		$platform_name = $this->api_client->platform_name;
		if (!is_scalar($platform_name)) {
			throw new \ArloTraining\SchedulerException('Platform name is invalid. Please check it under Arlo Settings → General.');
		}

		$platform_name = strtolower(trim((string) $platform_name));
		if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/i', $platform_name) || strlen($platform_name) > 63) {
			throw new \ArloTraining\SchedulerException('Platform name is invalid. Please check it under Arlo Settings → General.');
		}

		return $platform_name . Transport::PLATFORM_DOMAIN;
	}

	private function check_fragment_byte_size() {
		if (empty($this->fragment_size) || !is_numeric($this->fragment_size) ) {
			$this->fragment_size = self::FRAGMENT_DEFAULT_BYTE_SIZE;
		} else if ($this->fragment_size > self::FRAGMENT_MAX_BYTE_SIZE) {
			$this->fragment_size = self::FRAGMENT_MAX_BYTE_SIZE;
		}
	}

	private function generate_post_data($fragmentation = false) {
		$data_obj = new \stdClass();

		$data_obj->SchemaLevel = self::schema_level;
		$data_obj->SnapshotType = $this->snapshot_type;
		$data_obj->Query = $this->generate_query_object();
		$data_obj->Result = $this->generate_result_object($fragmentation);
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

	private function generate_result_object($fragmentation = false) {
		$data_obj = new \stdClass();
		$data_obj->EncryptedResponse = $this->generate_encryptedresponse_object();

		if ($fragmentation) {
			$data_obj->Disposition = "Fragmented";
			$data_obj->Fragmentation = new \stdClass(); 
			$data_obj->Fragmentation->FragmentMaxSizeBytes = $this->fragment_size;
		}

		return $data_obj;
	}

	private function generate_callback_object() {
		$data_obj = new \stdClass();

		$data_obj->Uri = admin_url('admin-ajax.php') . '?action=' . $this->callback_action;
		$data_obj->Nonce = $this->nonce;
		$data_obj->EncryptedResponse = $this->generate_encryptedresponse_object();

		$settings = get_option('arlo_settings');
		if (!empty($settings['import_callback_host'])) {
			$import_callback_host = $settings['import_callback_host'];
			if (strpos($import_callback_host, '://') === false) {
				$import_callback_host = 'https://' . $import_callback_host;
			}
			$scheme = strtolower((string) wp_parse_url($import_callback_host, PHP_URL_SCHEME));
			if (!in_array($scheme, array('http', 'https'), true)) {
				throw new \ArloTraining\SchedulerException('Import callback host is invalid: unsupported scheme "' . esc_html($scheme) . '". Please correct the Import callback host setting.');
			}
			$site_url = wp_parse_url(get_option( 'siteurl' ));
			$callback_url = wp_parse_url($import_callback_host);

			if (empty($site_url['host']) || empty($callback_url['host'])) {
				throw new \ArloTraining\SchedulerException('Import callback host could not be parsed. Please check the Import callback host setting.');
			}

			$search_url = $site_url["host"] . (!empty($site_url['port']) ? ':' . $site_url['port'] : '');
			$replace_url = $callback_url["host"] . (!empty($callback_url['port']) ? ':' . $callback_url['port'] : '');

			if (!empty($search_url) && !empty($replace_url)) {
				$data_obj->Uri = str_replace($search_url, $replace_url, $data_obj->Uri);
			}
		}

		return $data_obj;
	}
	

	private function generate_encryptedresponse_object() {
		$data_obj = new \stdClass();

		$data_obj->alg = "dir";
		$data_obj->enc = $this->encription_type;

		return $data_obj;
	}
}