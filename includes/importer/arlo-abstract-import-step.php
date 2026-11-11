<?php

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Base class for import steps that process a decoded item payload.
 *
 * Concrete import steps receive their step-specific data array from the owning
 * Importer, iterate it through run(), and persist each item via import_entity().
 * This base class also provides the resumable iteration checkpoint used when a
 * request pauses mid-step, along with shared prepared-query helpers for the
 * importer layer.
 */
abstract class AbstractImportStep {
	/**
	 * Longest region ID the pre-5.2.0 region columns could hold.
	 */
	const LEGACY_REGION_ID_MAX_LENGTH = 5;

	/**
	 * The kses filters core attaches for post titles and content, as hook, callback and priority.
	 *
	 * These are the filters the importers' post writes pass through. Comment and excerpt filtering are deliberately not included.
	 *
	 * @var array<int, array{0: string, 1: string, 2: int}>
	 */
	private const KSES_POST_TEXT_FILTERS = array(
		array( 'title_save_pre', 'wp_filter_kses', 10 ),
		array( 'content_save_pre', 'wp_filter_global_styles_post', 9 ),
		array( 'content_save_pre', 'wp_filter_post_kses', 10 ),
	);

	/**
	 * Indicates that a nested iteration completed and the outer checkpoint may
	 * advance past the stored iteration on the next retry.
	 *
	 * @var bool
	 */
	public $iteration_finished = false;

	/**
	 * Indicates that this import step has finished all of its work.
	 *
	 * @var bool
	 */
	public $is_finished = false;

	/**
	 * Zero-based checkpoint index for resumable step processing.
	 *
	 * @var int
	 */
	public $iteration = 0;

	/**
	 * Scheduler task id for the owning import job.
	 *
	 * Populated by the top-level importer so subtasks that need to update task
	 * state can write back to the correct scheduler row.
	 *
	 * @var int|null
	 */
	public $task_id;

	protected $id;
	protected $importer;
	protected $message_handler;
	protected $api_client;
	protected $scheduler;
	protected $importing_parts;

	protected $import_id;

	/**
	 * Whether the session froze legacy handling, limiting stored region IDs to five characters.
	 *
	 * @var bool
	 */
	protected $legacy_region_id_handling = false;

	/**
	 * Item payload for the current import step.
	 *
	 * Each concrete import step receives the array for its own decoded import-task
	 * segment (for example timezones, events, templates, or online activities)
	 * and iterates those items in run(). Tasks with no item payload may receive
	 * null instead.
	 *
	 * @var array<int, mixed>|null
	 */
	protected $data;

	/**
	 * @param mixed                  $importer        Owning importer coordinator.
	 * @param mixed                  $message_handler Import message handler.
	 * @param array<int, mixed>|null $data            Decoded item array for this import step.
	 * @param int                    $iteration       Current item offset for resumable runs.
	 * @param mixed                  $api_client      API client used by import steps that call the platform.
	 * @param mixed                  $scheduler       Scheduler used by import steps that persist task state.
	 * @param mixed                  $importing_parts Import-parts helper used by import steps that read fragment state.
	 */
	public function __construct( $importer, $message_handler, $data, $iteration = 0, $api_client = null, $scheduler = null, $importing_parts = null ) {
		$this->importer        = $importer;
		$this->message_handler = $message_handler;
		$this->api_client      = $api_client;
		$this->scheduler       = $scheduler;
		$this->importing_parts = $importing_parts;

		$this->import_id = $importer->import_id;
		$this->data      = $data;
		$this->iteration = $iteration;
	}

	/**
	 * Applies the session's frozen region ID handling to this step.
	 *
	 * @param bool $enabled Whether stored region IDs are limited to five characters.
	 * @return void
	 */
	public function set_legacy_region_id_handling( bool $enabled ): void {
		$this->legacy_region_id_handling = $enabled;
	}

	/**
	 * Returns a region ID in the form this session stores and compares it.
	 *
	 * Region IDs are ASCII, so a byte limit matches the old column. Apply it to every region value used for
	 * writes and for the replay lookups that must find those writes.
	 *
	 * @param mixed $region Region ID from an import payload.
	 * @return mixed The region unchanged, or its first five characters in a legacy session.
	 */
	protected function normalise_region( $region ) {
		if ( $this->legacy_region_id_handling && is_string( $region ) ) {
			return substr( $region, 0, self::LEGACY_REGION_ID_MAX_LENGTH );
		}

		return $region;
	}

	/**
	 * Import one decoded API entity payload for this step.
	 *
	 * @param mixed $entity_payload Single decoded item from this step's payload.
	 * @return void
	 */
	abstract protected function import_entity( $entity_payload );

	/**
	 * Returns any extra step state that should be merged into the stored
	 * import checkpoint.
	 *
	 * Base import steps do not contribute any additional state, so this
	 * default implementation returns null. Subclasses with nested progress state
	 * can override it.
	 *
	 * @return mixed|null
	 */
	public function get_state() {
		return null;
	}

	/**
	 * Returns whether this step has work for the supplied or current payload.
	 *
	 * @param mixed|null $data Optional payload to probe instead of this step's current data.
	 * @return bool True when the payload should be processed.
	 */
	public function should_process( $data = null ): bool {
		$payload = func_num_args() ? $data : $this->data;

		return ! empty( $payload );
	}

	/**
	 * Processes each item in this importer's current payload until the payload
	 * is exhausted or the execution-environment guard requests a checkpoint.
	 *
	 * The payload is the concrete step array passed into the constructor from
	 * the decoded import manifest. Each element is handed to import_entity(). When
	 * the importer pauses because the current request is no longer viable,
	 * iteration is left at the most recently completed item so the next request
	 * can resume from that checkpoint.
	 *
	 * @return void
	 */
	public function run() {
		if ( ! empty( $this->data ) && is_array( $this->data ) ) {
			$count = count( $this->data );

			for ( $i = $this->iteration; $i < $count; $i++ ) {
				if ( isset( $this->data[ $i ] ) ) {
					$this->import_entity( $this->data[ $i ] );
					if ( ! $this->importer->check_viable_execution_environment() ) {
						$this->iteration = $i;
						break;
					}
				}
			}

			if ( $i >= $count ) {
				$this->is_finished = true;
			}
		}
	}

	/**
	 * Run an importer-owned prepared write query and throw if the database reports failure.
	 *
	 * @param string $sql        SQL query containing placeholders.
	 * @param array  $parameters Values to prepare into the SQL query.
	 * @return int|bool Query result from wpdb after false results have been converted to exceptions.
	 * @throws \Exception If the database reports a query failure.
	 */
	protected function execute_prepared_query( $sql, array $parameters = [] ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is a caller-supplied importer-owned template with placeholders; it is prepared here with $parameters. Direct query on plugin tables is required; import writes are not cached.
		$query = $wpdb->query( $wpdb->prepare( $sql, $parameters ) );

		if ( $query === false ) {
			throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}

		return $query;
	}

	/**
	 * Run an importer-owned prepared column lookup and throw if the database reports failure.
	 *
	 * @param string $sql        SQL query containing placeholders.
	 * @param array  $parameters Values to prepare into the SQL query.
	 * @return array Column values returned by wpdb, or an empty array for a non-array result.
	 * @throws \Exception If the database reports a query failure.
	 */
	protected function get_prepared_column_values( $sql, array $parameters = [] ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is a caller-supplied importer-owned template with placeholders; it is prepared here with $parameters. Direct read of plugin tables is required; results are consumed within the import run.
		$results = $wpdb->get_col( $wpdb->prepare( $sql, $parameters ) );

		if ( ! empty( $wpdb->last_error ) ) {
			throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception is caught by the calling context and written to the error log; not rendered in any browser context.
		}

		return is_array( $results ) ? $results : [];
	}

	/**
	 * Delete rows by integer IDs from a hardcoded importer table and column.
	 *
	 * @param string $table_name  Full table name. Must be an importer-owned hardcoded table name.
	 * @param string $column_name Integer ID column name. Must be an importer-owned hardcoded column name.
	 * @param array  $ids         Integer row IDs to delete.
	 * @return int|bool Query result from wpdb, or 0 when no IDs are supplied.
	 * @throws \Exception If the database reports a query failure.
	 */
	protected function delete_rows_by_ids( $table_name, $column_name, array $ids ) {
		if ( empty( $ids ) ) {
			return 0;
		}

		$ids          = array_map( 'intval', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		return $this->execute_prepared_query( "DELETE FROM {$table_name} WHERE {$column_name} IN ($placeholders)", $ids );
	}

	/**
	 * Inserts a post with wp_insert_post(), sanitised as it would be for a user without unfiltered_html.
	 *
	 * The columns are slashed first because wp_insert_post() expects slashed data and would otherwise strip backslashes.
	 *
	 * @param array $post_config_array Post columns for wp_insert_post().
	 * @param bool  $wp_error          Whether to return a WP_Error on failure.
	 * @return int|\WP_Error|false Post ID, or the failure wp_insert_post() reports.
	 */
	protected function insert_post_sanitised( array $post_config_array, $wp_error = false ) {
		return $this->with_kses_filters(
			function () use ( $post_config_array, $wp_error ) {
				return wp_insert_post( wp_slash( $post_config_array ), $wp_error );
			}
		);
	}

	/**
	 * Sanitises post_title and post_content as wp_insert_post() would, for a row written without it.
	 *
	 * Other columns, including post_name, are returned unchanged. Backslashes are preserved.
	 *
	 * @param array $post_config_array Post columns about to be written.
	 * @param int   $post_id           ID of the existing post.
	 * @return array The same columns with post_title and post_content sanitised.
	 */
	protected function sanitise_post_text( array $post_config_array, $post_id ): array {
		return $this->with_kses_filters(
			function () use ( $post_config_array, $post_id ) {
				foreach ( array( 'post_title', 'post_content' ) as $field ) {
					if ( array_key_exists( $field, $post_config_array ) ) {
						// Not sanitize_post(): it adds ID and filter keys that are not wp_posts columns. The slash round trip is core's contract for these filters.
						$post_config_array[ $field ] = wp_unslash( sanitize_post_field( $field, wp_slash( (string) $post_config_array[ $field ] ), (int) $post_id, 'db' ) );
					}
				}

				return $post_config_array;
			}
		);
	}

	/**
	 * Runs a callback with the kses filters for post titles and content attached, whatever the current user's unfiltered_html capability.
	 *
	 * Only filters that are missing are attached, and only those are removed afterwards, so any other filter state is left as it was,
	 * including filters that other code registered or removed independently.
	 *
	 * @param callable $callback Work that saves post text through core's *_save_pre filters.
	 * @return mixed The callback's return value.
	 */
	protected function with_kses_filters( callable $callback ) {
		$added = array();
		foreach ( self::KSES_POST_TEXT_FILTERS as $registration ) {
			if ( false === has_filter( $registration[0], $registration[1] ) ) {
				add_filter( $registration[0], $registration[1], $registration[2] );
				$added[] = $registration;
			}
		}

		try {
			return $callback();
		} finally {
			foreach ( $added as $registration ) {
				remove_filter( $registration[0], $registration[1], $registration[2] );
			}
		}
	}

	protected function save_advertised_offer( $advertised_offer, $region = '', $template_id = null, $event_id = null, $oa_id = null ) {
		global $wpdb;
		if ( ! empty( $advertised_offer ) && is_array( $advertised_offer ) ) {
			$template_id = ( intval( $template_id ) > 0 ? $template_id : null );
			$event_id    = ( intval( $event_id ) > 0 ? $event_id : null );
			$oa_id       = ( intval( $oa_id ) > 0 ? $oa_id : null );

			//$offers = array_reverse($advertised_offer);
			foreach ( $advertised_offer as $key => $offer ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. Do not need cache for insert operation in data import process.
				$query = $wpdb->query(
					$wpdb->prepare(
						"INSERT INTO {$wpdb->prefix}arlo_offers
					(o_arlo_id, et_id, e_id, oa_id, o_label, o_isdiscountoffer, o_currencycode, o_offeramounttaxexclusive, o_offeramounttaxinclusive, o_formattedamounttaxexclusive, o_formattedamounttaxinclusive, o_taxrateshortcode, o_taxratename, o_taxratepercentage, o_message, o_order, o_replaces, o_region, import_id)
					VALUES ( %d, %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s, %d )
					",
						$offer->OfferID + 1,
						$template_id,
						$event_id,
						$oa_id,
						! empty( $offer->Label ) ? $offer->Label : null,
						! empty( $offer->IsDiscountOffer ), // bool
						! empty( $offer->OfferAmount ) && ! empty( $offer->OfferAmount->CurrencyCode ) ? $offer->OfferAmount->CurrencyCode : null,
						! empty( $offer->OfferAmount ) && ! empty( $offer->OfferAmount->AmountTaxExclusive ) ? $offer->OfferAmount->AmountTaxExclusive : null,
						! empty( $offer->OfferAmount ) && ! empty( $offer->OfferAmount->AmountTaxInclusive ) ? $offer->OfferAmount->AmountTaxInclusive : null,
						! empty( $offer->OfferAmount ) && ! empty( $offer->OfferAmount->FormattedAmountTaxExclusive ) ? $offer->OfferAmount->FormattedAmountTaxExclusive : null,
						! empty( $offer->OfferAmount ) && ! empty( $offer->OfferAmount->FormattedAmountTaxInclusive ) ? $offer->OfferAmount->FormattedAmountTaxInclusive : null,
						! empty( $offer->OfferAmount ) && ! empty( $offer->OfferAmount->TaxRate ) && ! empty( $offer->OfferAmount->TaxRate->ShortName ) ? $offer->OfferAmount->TaxRate->ShortName : null,
						! empty( $offer->OfferAmount ) && ! empty( $offer->OfferAmount->TaxRate ) && ! empty( $offer->OfferAmount->TaxRate->Name ) ? $offer->OfferAmount->TaxRate->Name : null,
						! empty( $offer->OfferAmount ) && ! empty( $offer->OfferAmount->TaxRate ) && ! empty( $offer->OfferAmount->TaxRate->RatePercent ) ? $offer->OfferAmount->TaxRate->RatePercent : null,
						! empty( $offer->Message ) ? $offer->Message : null,
						$key + 1,
						( isset( $offer->ReplacesOfferID ) ) ? $offer->ReplacesOfferID + 1 : null,
						( ! empty( $region ) ? $this->normalise_region( $region ) : '' ),
						(int) $this->import_id
					)
				);

				if ( $query === false ) {
					throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
				}
			}
		}
	}

	protected function save_tags( $tags, $id, $type = '' ) {
		global $wpdb;
		switch ( $type ) {
			case "template":
				$field      = "et_id";
				$table_name = $wpdb->prefix . "arlo_eventtemplates_tags";
				break;
			case "event":
				$field      = "e_id";
				$table_name = $wpdb->prefix . "arlo_events_tags";
				break;
			case "oa":
				$field      = "oa_id";
				$table_name = $wpdb->prefix . "arlo_onlineactivities_tags";
				break;
			default:
				throw new \Exception( 'Tag type failed: ' . $type ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
		}

		if ( isset( $tags ) && is_array( $tags ) ) {
			$exisiting_tags = [];
			$sql            = "
			SELECT
				id,
				tag
			FROM
				" . $wpdb->prefix . "arlo_tags
			WHERE
				tag IN (" . implode(
					',',
					array_map(
						function () {
							return "%s";},
						$tags
					)
				) . ")
			AND
				import_id = %d
			";

			$parameter   = array_merge( [], $tags );
			$parameter[] = $this->import_id;

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The SQL statement is dynamically constructed and parameters are prepared here. Direct database query is required for custom table. Do not need cache for import process.
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $parameter ), ARRAY_A );
			foreach ( $rows as $row ) {
				$exisiting_tags[ $row['tag'] ] = $row['id'];
			}
			unset( $rows );

			foreach ( $tags as $tag ) {
				if ( empty( $exisiting_tags[ $tag ] ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. Do not need cache for import process.
					$query = $wpdb->query(
						$wpdb->prepare(
							"INSERT INTO {$wpdb->prefix}arlo_tags
						(tag, import_id)
						VALUES ( %s, %d )
						",
							$tag,
							$this->import_id
						)
					);

					if ( $query === false ) {
						throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
					} else {
						$exisiting_tags[ $tag ] = $wpdb->insert_id;
					}
				}

				if ( ! empty( $exisiting_tags[ $tag ] ) ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct database query is required. Table name and field are selected from a fixed switch whitelist, not user input. Do not need cache for import process.
					$query = $wpdb->query(
						$wpdb->prepare(
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table name and field are selected from a fixed switch whitelist, not user input; bound parameters are supplied via $wpdb->prepare().
							"INSERT INTO {$table_name} (" . $field . ", tag_id, import_id) VALUES ( %d, %d, %d ) ",
							$id,
							$exisiting_tags[ $tag ],
							$this->import_id
						)
					);

					if ( $query === false ) {
						throw new \Exception( 'SQL error: ' . $wpdb->last_error ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
					}
				} else {
					throw new \Exception( 'Couldn\'t find tag: ' . $tag ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message is caught by the import pipeline, written to the Arlo log table via Logger, and escaped with esc_html() at admin render time.
				}
			}
		}
	}
}
