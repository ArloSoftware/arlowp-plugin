<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

use ArloTraining\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Validates optional snapshot manifest intent, then retrieves and stores decoded snapshot parts.
 */
class Download extends AbstractImportStep {
	public $import_part;
	public $import_iteration;
	public $uri;
	public $response_json;

	/** @var SnapshotPartProvider */
	private $part_provider;

	/** @var SnapshotManifestProvider|null */
	private $manifest_provider;

	/** @var SnapshotManifestRequest|null */
	private $manifest_request;

	/** @var IncrementalBaselineRepository|null */
	private $baseline_repository;

	/** @var array<string,mixed>|null */
	private $validated_delta_context;

	/**
	 * @param mixed                 $importer Owning importer coordinator.
	 * @param mixed                 $message_handler Import message handler.
	 * @param array<int,mixed>|null $data Decoded item data.
	 * @param int                   $iteration Current item offset.
	 * @param mixed                 $api_client API client.
	 * @param mixed                 $scheduler Scheduler.
	 * @param mixed                 $importing_parts Import-parts helper.
	 */
	public function __construct( $importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null ) {
		parent::__construct( $importer, $message_handler, $data, $iteration, $api_client, $scheduler, $importing_parts );
		$this->part_provider = new RemoteHttpSnapshotPartProvider();
	}

	protected function import_entity( $entity_payload ) {}

	public function run() {
		if ( ! is_scalar( $this->uri ) || '' === trim( (string) $this->uri ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'The URI couldn\'t be empty' );
		}
		// Attempt to fetch and validate the advertised manifest.
		$manifest = $this->try_get_advertised_manifest();
		if ( null === $manifest && null !== $this->manifest_provider && null !== $this->manifest_request ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot manifest rejected; continuing ordinary full-shadow import', $this->import_id );
		} elseif ( $manifest instanceof SnapshotManifest ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot manifest validated [entries=' . count( $manifest->entries ) . ']', $this->import_id );
		}

		$uri = (string) $this->uri;

		$is_snapshot      = 'image' === $this->import_part;
		$fragment_index   = $is_snapshot ? null : (int) $this->import_iteration - 1;
		$snapshot_context = new SnapshotContext( $this->get_platform_hostname(), $this->get_encryption_key(), $this->get_encryption_method(), (int) $this->import_id );
		$content          = $this->part_provider->get_snapshot_part_content(
			$snapshot_context->create_part_request( $uri, $is_snapshot ? SnapshotPartRequest::PART_TYPE_SNAPSHOT : SnapshotPartRequest::PART_TYPE_FRAGMENT, $fragment_index )
		);
		if ( strlen( $content ) > ImportRequest::FRAGMENT_MAX_BYTE_SIZE ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot file decryption exceeds maximum allowed size' );
		}
		if ( '' === $content ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot file decryption produced an empty result' );
		}

		// Keep validated delta inputs in this request only; the outer importer chooses its durable route after image storage.
		if ( $is_snapshot && $manifest instanceof SnapshotManifest ) {
			try {
				$configuration_signature = ImportRequest::compute_baseline_configuration_signature_for_platform( $this->manifest_request->platform_hostname );
			} catch ( \Throwable $exception ) {
				$configuration_signature = null;
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot manifest could not build a compatible baseline signature; continuing ordinary full-shadow import [stage=configuration_signature; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
			}

			if ( ! is_string( $configuration_signature ) ) {
				Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot manifest could not prove a delta route; continuing ordinary full-shadow import', $this->import_id );
			} else {
				// Retain validated delta inputs until the outer importer persists
				// their selected delta or full-shadow route.
				$this->validated_delta_context = [
					'manifest'                => $manifest,
					'manifest_request'        => $this->manifest_request,
					'image_content'           => $content,
					'configuration_signature' => $configuration_signature,
				];
			}
		}

		$id = $this->importing_parts->add_import_part( $this->import_part, $this->import_iteration, $content, $this->import_id );
		if ( ! empty( $id ) ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot data stored for processing [part=' . $this->import_part . ( is_null( $this->import_iteration ) ? '' : '; iteration=' . $this->import_iteration ) . ']', $this->import_id );
			$this->is_finished = true;
		}
		unset( $content );
	}

	/**
	 * @return string Platform hostname from the snapshot response.
	 */
	private function get_platform_hostname(): string {
		if ( ! is_object( $this->response_json ) || ! isset( $this->response_json->ArloMetadata->PlatformHostname ) || ! is_scalar( $this->response_json->ArloMetadata->PlatformHostname ) || '' === trim( (string) $this->response_json->ArloMetadata->PlatformHostname ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot platform hostname is missing from import response' );
		}

		return (string) $this->response_json->ArloMetadata->PlatformHostname;
	}

	/**
	 * @return string Snapshot encryption key.
	 */
	private function get_encryption_key(): string {
		if ( ! is_object( $this->response_json ) || ! isset( $this->response_json->Result->EncryptedResponse->key->k ) || ! is_scalar( $this->response_json->Result->EncryptedResponse->key->k ) || '' === trim( (string) $this->response_json->Result->EncryptedResponse->key->k ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot response encryption key is missing' );
		}

		return (string) $this->response_json->Result->EncryptedResponse->key->k;
	}

	/**
	 * @return string Snapshot encryption method.
	 */
	private function get_encryption_method(): string {
		if ( ! is_object( $this->response_json ) || ! isset( $this->response_json->Result->EncryptedResponse->enc ) || ! is_scalar( $this->response_json->Result->EncryptedResponse->enc ) || '' === trim( (string) $this->response_json->Result->EncryptedResponse->enc ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot response encryption method is missing' );
		}

		return (string) $this->response_json->Result->EncryptedResponse->enc;
	}

	/**
	 * Sets the provider used by this Download instance.
	 *
	 * @param SnapshotPartProvider $provider Snapshot-part provider.
	 * @return void
	 */
	public function set_snapshot_part_provider( SnapshotPartProvider $provider ): void {
		$this->part_provider = $provider;
	}

	/**
	 * Sets the code-owned manifest admission inputs for this top-level Download.
	 *
	 * @param SnapshotManifestProvider $provider Manifest provider.
	 * @param SnapshotManifestRequest  $request Admitted package advertisement.
	 * @return void
	 */
	public function set_snapshot_manifest_admission( SnapshotManifestProvider $provider, SnapshotManifestRequest $request ): void {
		$this->manifest_provider = $provider;
		$this->manifest_request  = $request;
	}

	/**
	 * Sets the persistence boundary used for compatible full-shadow candidates.
	 *
	 * @param IncrementalBaselineRepository $repository Baseline repository.
	 * @return void
	 */
	public function set_incremental_baseline_repository( IncrementalBaselineRepository $repository ): void {
		$this->baseline_repository = $repository;
	}

	/**
	 * Returns and clears the request-local validated delta context.
	 *
	 * @return array<string,mixed>|null Validated delta context, or null when no routing proof is available.
	 */
	public function consume_validated_delta_context(): ?array {
		$context                       = $this->validated_delta_context;
		$this->validated_delta_context = null;

		return $context;
	}

	/**
	 * Stages the admitted snapshot as an ordinary full-shadow baseline candidate.
	 *
	 * @param array<string,mixed> $context One-request admitted delta inputs.
	 * @return bool True when the candidate is durably staged; false when it is not,
	 *              including invalid context or an unconfirmed staging result.
	 */
	public function stage_full_shadow_candidate( array $context ): bool {
		if ( ! isset( $context['manifest'], $context['manifest_request'], $context['image_content'], $context['configuration_signature'] ) || ! $context['manifest'] instanceof SnapshotManifest || ! $context['manifest_request'] instanceof SnapshotManifestRequest || ! is_string( $context['image_content'] ) || ! is_string( $context['configuration_signature'] ) ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Full-snapshot candidate was not staged [stage=full_shadow_candidate; reason=invalid_context; result=false]', $this->import_id );
			return false;
		}

		try {
			$staged = $this->get_baseline_repository()->stage_full_shadow_candidate( (int) $this->import_id, $context['manifest_request'], $context['manifest'], $context['image_content'], $context['configuration_signature'] );
		} catch ( \Throwable $exception ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Full-snapshot candidate could not be staged [stage=full_shadow_candidate; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . '; result=threw]', $this->import_id );
			return false;
		}

		if ( ! $staged ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Full-snapshot candidate was not staged [stage=full_shadow_candidate; result=false]', $this->import_id );
		}

		return $staged;
	}

	/**
	 * Fetches and validates an advertised manifest.
	 *
	 * @return SnapshotManifest|null Validated server intent, or null when ordinary full shadow must continue without a candidate.
	 */
	private function try_get_advertised_manifest(): ?SnapshotManifest {
		if ( null === $this->manifest_provider || null === $this->manifest_request ) {
			return null;
		}

		try {
			$this->assert_manifest_request_binding_integrity();
		} catch ( \Throwable $exception ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot manifest validation failed; continuing ordinary full-shadow import [stage=manifest_binding; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
			return null;
		}

		try {
			$bytes = $this->manifest_provider->get_snapshot_manifest_content( $this->manifest_request );
		} catch ( \ArloTraining\SnapshotManifestMemoryException $exception ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot manifest could not be processed within the PHP memory budget; continuing ordinary full-shadow import [stage=manifest_memory_preflight; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . '; memory_limit_bytes=' . $exception->memory_limit_bytes . '; manifest_bytes=' . $exception->manifest_bytes . '; estimated_memory_required_bytes=' . $exception->estimated_memory_required_bytes . ']', $this->import_id );
			return null;
		} catch ( \Throwable $exception ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot manifest validation failed; continuing ordinary full-shadow import [stage=manifest_fetch; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
			return null;
		}

		try {
			return ( new SnapshotManifestParser() )->parse( $bytes );
		} catch ( \Throwable $exception ) {
			Logger::log_diagnostic( Logger::DIAGNOSTIC_VERBOSITY_DEBUG, 'Snapshot manifest validation failed; continuing ordinary full-shadow import [stage=manifest_parse; exception=' . get_class( $exception ) . '; message=' . $exception->getMessage() . ']', $this->import_id );
			return null;
		} finally {
			unset( $bytes );
		}
	}

	/**
	 * @return IncrementalBaselineRepository Candidate persistence boundary.
	 */
	private function get_baseline_repository(): IncrementalBaselineRepository {
		if ( null === $this->baseline_repository ) {
			$this->baseline_repository = new IncrementalBaselineRepository();
		}

		return $this->baseline_repository;
	}

	/**
	 * Asserts that the manifest advertisement is bound to the snapshot artifact.
	 *
	 * @return void
	 */
	private function assert_manifest_request_binding_integrity(): void {
		if ( (int) $this->manifest_request->import_id !== (int) $this->import_id || ! is_scalar( $this->uri ) || ! hash_equals( $this->manifest_request->snapshot_uri, (string) $this->uri ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot manifest is not bound to the admitted download artifact' );
		}
	}
}
