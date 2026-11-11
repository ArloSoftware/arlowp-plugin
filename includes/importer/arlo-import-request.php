<?php

namespace ArloTraining\Importer;

use ArloTraining\Logger;
use ArloTraining\API\Transports\Transport;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Starts a snapshot import request against the Arlo platform.
 *
 * This task builds the snapshot field list, generates the callback metadata and
 * nonce, requests a new snapshot from the platform, and stores the response so
 * later tasks can download and process the returned fragments.
 */
class ImportRequest extends AbstractImportStep {
	const FRAGMENT_MAX_BYTE_SIZE     = 10485760;
	const FRAGMENT_DEFAULT_BYTE_SIZE = 524288;

	private $nonce;

	private $callback_action = 'arlo_import_callback';

	private $snapshot_type        = "Full";
	private const SNAPSHOT_FIELDS = [
		'Regions'          => [
			'RegionID',
			'Name',
		],
		'TimeZones'        => [
			'TimeZoneID',
			'Name',
			'WindowsTzID',
			'UtcOffset',
		],
		'Events'           => [
			'EventID',
			'EventTemplateID',
			'Name',
			'Code',
			'Description',
			'Summary',
			'StartDateTime',
			'EndDateTime',
			'StartTimeZoneAbbr',
			'EndTimeZoneAbbr',
			'TimeZoneID',
			'Location',
			'IsFull',
			'PlacesRemaining',
			'AdvertisedOffers',
			'SessionsDescription',
			'Presenters',
			'Notice',
			'ViewUri',
			'RegistrationInfo',
			'Provider',
			'TemplateCode',
			'Tags',
			'Credits',
			'Sessions.EventID',
			'Sessions.Name',
			'Sessions.Code',
			'Sessions.Summary',
			'Sessions.StartDateTime',
			'Sessions.EndDateTime',
			'Sessions.StartTimeZoneAbbr',
			'Sessions.EndTimeZoneAbbr',
			'Sessions.TimeZoneID',
			'Sessions.Location',
			'Sessions.IsFull',
			'Sessions.PlacesRemaining',
			'Sessions.AdvertisedOffers',
			'Sessions.Presenters',
			'Sessions.Tags',
		],
		'Templates'        => [
			'TemplateID',
			'Code',
			'Name',
			'Description',
			'AdvertisedPresenters',
			'AdvertisedDuration',
			'BestAdvertisedOffers',
			'ViewUri',
			'RegisterInterestUri',
			'RegisterPrivateInterestUri',
			'Categories',
			'Tags',
			'Credits',
			'Media',
		],
		'Presenters'       => [
			'PresenterID',
			'FirstName',
			'LastName',
			'ViewUri',
			'Profile',
			'SocialNetworkInfo',
		],
		'OnlineActivities' => [
			'OnlineActivityID',
			'TemplateID',
			'Name',
			'Code',
			'DeliveryDescription',
			'ViewUri',
			'ReferenceTerms',
			'Credits',
			'RegistrationInfo',
			'AdvertisedOffers',
			'Tags',
		],
		'Venues'           => [
			'VenueID',
			'Name',
			'GeoData',
			'PhysicalAddress',
			'FacilityInfo',
			'ViewUri',
			'LocationName',
		],
		'Categories'       => [
			'CategoryID',
			'ParentCategoryID',
			'Name',
			'SequenceIndex',
			'Description',
			'Footer',
		],
		'CategoryItems'    => [
			'CategoryID',
			'EventTemplateID',
			'SequenceIndex',
		],
	];
	private $encryption_type      = 'A256CBC-HS512';

	private $fragmentation = true;
	public $fragment_size;

	protected function import_entity( $entity_payload ) {}

	/**
	 * Returns the fixed field projection used for every full snapshot request.
	 *
	 * @return array<string,array<int,string>> Snapshot entity fields by group.
	 */
	public static function get_snapshot_field_configuration(): array {
		return self::SNAPSHOT_FIELDS;
	}

	public function run() {
		if ( ! $this->api_client ) {
			throw new \ArloTraining\SchedulerException(
				'Platform name is not configured. Please set it under Arlo Settings → General.'
			);
		}

		do_action( 'arlo_import_starting' );

		$this->nonce       = SnapshotHandler::generate_nonce();
		$platform_hostname = $this->get_platform_hostname();

		$this->check_fragment_byte_size();
		$import_id = $this->importer->set_import_entry( $this->nonce );
		Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_TRACE, 'Created snapshot import entry [import_id=' . $import_id . ']', $this->import_id );

		try {
			// Read the legacy region setting once; the session keeps this value even if the setting changes mid-import.
			// A legacy session is always a full import, as the delta manifest path does not apply the five-character limit.
			$legacy_region_id_handling = \Arlo_For_Wordpress::is_legacy_region_id_handling_enabled();
			// Persist one readiness-admitted mode; later steps trust it rather than polling metadata during the import.
			$importer_mode = $this->importer->admit_importer_mode( $legacy_region_id_handling ? \Arlo_For_Wordpress::IMPORTER_MODE_FULL : \Arlo_For_Wordpress::get_importer_mode() );
			$schema_level  = $this->get_schema_level( $importer_mode );
			$this->persist_session_admission( $importer_mode, $schema_level, $legacy_region_id_handling );
			if ( $legacy_region_id_handling ) {
				// Regions sharing their first five characters overwrite each other in this mode, so support needs to see it without raising the diagnostic level.
				Logger::log( 'Legacy (4.x) region ID handling is on: region IDs are stored cut to 5 characters and this import runs in full mode.', $this->import_id );
			}

			try {
				$snapshot_request_response = $this->request_snapshot_import( $platform_hostname, $schema_level );
			} catch ( \ArloTraining\PlatformAccessHttpException $exception ) {
				if ( ! $this->is_delta_schema_level_rejection( $exception, $importer_mode, $schema_level ) ) {
					throw $exception;
				}

				// Platform deployments may explicitly reject schema 200 when its feature is not enabled; persist a full-schema session before one compatibility retry.
				$importer_mode = \Arlo_For_Wordpress::IMPORTER_MODE_FULL;
				$schema_level  = SnapshotManifest::SCHEMA_LEVEL_DEFAULT;
				$this->persist_session_admission( $importer_mode, $schema_level, $legacy_region_id_handling );
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Platform rejected the delta snapshot schema; retrying with the full snapshot schema [platform=' . $platform_hostname . '; rejected_schema_level=' . SnapshotManifest::SCHEMA_LEVEL_DELTA . '; fallback_schema_level=' . $schema_level . ']', $this->import_id );
				$snapshot_request_response = $this->request_snapshot_import( $platform_hostname, $schema_level );
			}

			if ( ! is_object( $snapshot_request_response ) || empty( $snapshot_request_response->RequestID ) ) {
				throw new \ArloTraining\PlatformAccessHttpException( 'Snapshot request returned an invalid response or no RequestID' );
			}

			$snapshot_request_response->ArloMetadata = (object) [
				'PlatformHostname' => $platform_hostname,
			];
			$data                                    = [
				'request_id'    => $snapshot_request_response->RequestID,
				'response_json' => wp_json_encode( $snapshot_request_response ),
			];

			$this->importer->update_import_entry( $data );
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Arlo accepted the import snapshot request [request_id=' . $snapshot_request_response->RequestID . '; platform=' . $platform_hostname . ']', $this->import_id );
		} catch ( \Throwable $e ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Import snapshot request failed [exception=' . get_class( $e ) . '; message=' . $e->getMessage() . ']', $this->import_id );
			try {
				$this->importer->delete_import_entry( $import_id );
			} catch ( \Throwable $delete_exception ) {
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
		if ( ! is_scalar( $platform_name ) ) {
			throw new \ArloTraining\SchedulerException( 'Platform name is invalid. Please check it under Arlo Settings → General.' );
		}

		$platform_name = strtolower( trim( (string) $platform_name ) );
		if ( ! preg_match( '/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/i', $platform_name ) || strlen( $platform_name ) > 63 ) {
			throw new \ArloTraining\SchedulerException( 'Platform name is invalid. Please check it under Arlo Settings → General.' );
		}

		return $platform_name . Transport::PLATFORM_DOMAIN;
	}

	private function check_fragment_byte_size() {
		if ( empty( $this->fragment_size ) || ! is_numeric( $this->fragment_size ) ) {
			$this->fragment_size = self::FRAGMENT_DEFAULT_BYTE_SIZE;
		} elseif ( $this->fragment_size > self::FRAGMENT_MAX_BYTE_SIZE ) {
			$this->fragment_size = self::FRAGMENT_MAX_BYTE_SIZE;
		}
	}

	private function generate_post_data( $fragmentation = false, int $schema_level = SnapshotManifest::SCHEMA_LEVEL_DEFAULT ) {
		$data_obj = new \stdClass();

		$data_obj->SchemaLevel  = $schema_level;
		$data_obj->SnapshotType = $this->snapshot_type;
		$data_obj->Query        = $this->generate_query_object();
		$data_obj->Result       = $this->generate_result_object( $fragmentation );
		$data_obj->Callback     = $this->generate_callback_object();

		return $data_obj;
	}

	/**
	 * @param string $importer_mode Readiness-admitted mode for this import session.
	 * @return int Snapshot schema level requested from the platform.
	 */
	private function get_schema_level( string $importer_mode ): int {
		return \Arlo_For_Wordpress::IMPORTER_MODE_FULL_DELTA === $importer_mode
			? SnapshotManifest::SCHEMA_LEVEL_DELTA
			: SnapshotManifest::SCHEMA_LEVEL_DEFAULT;
	}

	/**
	 * Persists the mode, schema level and region ID handling that subsequent import steps must use.
	 *
	 * The legacy flag is always written, so a retried request cannot keep a stale value from an earlier attempt.
	 *
	 * @param string $importer_mode Admitted importer mode.
	 * @param int    $schema_level Snapshot schema level.
	 * @param bool   $legacy_region_id_handling Whether this session limits stored region IDs to five characters.
	 * @return void
	 */
	private function persist_session_admission( string $importer_mode, int $schema_level, bool $legacy_region_id_handling ): void {
		if ( ! $this->importer->update_task_data_if_mutex_owned(
			$this->task_id,
			[
				'importer_mode'             => $importer_mode,
				'schema_level'              => $schema_level,
				'legacy_region_id_handling' => $legacy_region_id_handling,
			]
		) ) {
			throw new \Exception( 'Import setup could not persist the session admission decision.' );
		}
	}

	/**
	 * Requests a snapshot using the specified admitted schema level.
	 *
	 * @param string $platform_hostname Validated platform hostname.
	 * @param int    $schema_level Snapshot schema level.
	 * @return mixed Platform snapshot request response.
	 */
	private function request_snapshot_import( string $platform_hostname, int $schema_level ) {
		$snapshot_request_post_data = $this->generate_post_data( $this->fragmentation, $schema_level );
		Logger::log_diagnostic(
			Logger::DIAGNOSTIC_VERBOSITY_DEBUG,
			function () use ( $platform_hostname, $schema_level ) {
				return 'Requesting import snapshot from Arlo [platform=' . $platform_hostname . '; schema_level=' . $schema_level . '; fragmentation=' . ( $this->fragmentation ? 'enabled' : 'disabled' ) . '; fragment_size=' . $this->fragment_size . ' bytes]';
			},
			$this->import_id
		);

		return $this->api_client->Snapshots()->request_import( $snapshot_request_post_data );
	}

	/**
	 * Returns whether the platform explicitly rejected only the delta schema level.
	 * This intentionally does not treat other platform failures as compatibility signals.
	 *
	 * @param \ArloTraining\PlatformAccessHttpException $exception Platform request failure.
	 * @param string                                     $importer_mode Admitted importer mode.
	 * @param int                                        $schema_level Requested schema level.
	 * @return bool Whether a one-time full-schema retry is compatible.
	 */
	private function is_delta_schema_level_rejection( \ArloTraining\PlatformAccessHttpException $exception, string $importer_mode, int $schema_level ): bool {
		$schema_level_pattern = preg_quote( (string) SnapshotManifest::SCHEMA_LEVEL_DELTA, '#' );

		return \Arlo_For_Wordpress::IMPORTER_MODE_FULL_DELTA === $importer_mode
			&& SnapshotManifest::SCHEMA_LEVEL_DELTA === $schema_level
			&& 1 === preg_match( '#\breturned\s+400\b.*[\'\"]?SchemaLevel[\'\"]?\s+value\s+of\s+' . $schema_level_pattern . '\s+is\s+not\s+valid\b#i', $exception->getMessage() );
	}

	private function generate_query_object() {
		$data_obj = new \stdClass();

		foreach ( self::SNAPSHOT_FIELDS as $group => $field ) {
			$obj_key            = $group . ".Fields";
			$data_obj->$obj_key = implode( ",", $field );
		}

		return $data_obj;
	}

	private function generate_result_object( $fragmentation = false ) {
		$data_obj                    = new \stdClass();
		$data_obj->EncryptedResponse = $this->generate_encryptedresponse_object();

		if ( $fragmentation ) {
			$data_obj->Disposition                         = "Fragmented";
			$data_obj->Fragmentation                       = new \stdClass();
			$data_obj->Fragmentation->FragmentMaxSizeBytes = $this->fragment_size;
		}

		return $data_obj;
	}

	private function generate_callback_object() {
		$data_obj = new \stdClass();

		$data_obj->Uri               = admin_url( 'admin-ajax.php' ) . '?action=' . $this->callback_action;
		$data_obj->Nonce             = $this->nonce;
		$data_obj->EncryptedResponse = $this->generate_encryptedresponse_object();

		$settings = get_option( 'arlo_settings' );
		if ( ! empty( $settings['import_callback_host'] ) ) {
			$import_callback_host = $settings['import_callback_host'];
			if ( strpos( $import_callback_host, '://' ) === false ) {
				$import_callback_host = 'https://' . $import_callback_host;
			}
			$scheme = strtolower( (string) wp_parse_url( $import_callback_host, PHP_URL_SCHEME ) );
			if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				throw new \ArloTraining\SchedulerException( 'Import callback host is invalid: unsupported scheme "' . esc_html( $scheme ) . '". Please correct the Import callback host setting.' );
			}
			$site_url     = wp_parse_url( get_option( 'siteurl' ) );
			$callback_url = wp_parse_url( $import_callback_host );

			if ( empty( $site_url['host'] ) || empty( $callback_url['host'] ) ) {
				throw new \ArloTraining\SchedulerException( 'Import callback host could not be parsed. Please check the Import callback host setting.' );
			}

			$search_url  = $site_url["host"] . ( ! empty( $site_url['port'] ) ? ':' . $site_url['port'] : '' );
			$replace_url = $callback_url["host"] . ( ! empty( $callback_url['port'] ) ? ':' . $callback_url['port'] : '' );

			if ( ! empty( $search_url ) && ! empty( $replace_url ) ) {
				$data_obj->Uri = str_replace( $search_url, $replace_url, $data_obj->Uri );
			}
		}

		return $data_obj;
	}


	private function generate_encryptedresponse_object() {
		$data_obj = new \stdClass();

		$data_obj->alg = "dir";
		$data_obj->enc = $this->encryption_type;

		return $data_obj;
	}

	/**
	 * Builds the incremental-baseline signature for one snapshot platform.
	 *
	 * @param string $platform_hostname Platform hostname captured with the snapshot request.
	 * @return string Raw SHA-256 signature.
	 */
	public static function compute_baseline_configuration_signature_for_platform( string $platform_hostname ): string {
		$regions = get_option( 'arlo_regions', [] );
		$payload = [
			'platform_hostname' => strtolower( rtrim( trim( $platform_hostname ), '.' ) ),
			'region_ids'        => is_array( $regions ) ? array_keys( $regions ) : [],
			'field_map'         => self::get_snapshot_field_configuration(),
		];
		sort( $payload['region_ids'], SORT_STRING );
		$encoded_payload = wp_json_encode( $payload );
		if ( false === $encoded_payload ) {
			throw new \RuntimeException( 'Could not encode incremental baseline configuration.' );
		}

		return hash( 'sha256', $encoded_payload, true );
	}
}
