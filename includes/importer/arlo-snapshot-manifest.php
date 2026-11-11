<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Complete validated server target intent, never evidence of local applied state.
 * Tags are preserved as raw bytes.
 */
class SnapshotManifest {
	const SCHEMA_LEVEL_DEFAULT = 100;
	const SCHEMA_LEVEL_DELTA   = 200;

	/**
	 * Schema level that defines this manifest's supported projection contract.
	 *
	 * @var int
	 */
	public $schema_level;

	/**
	 * Complete server-supplied target census tag, stored as 32 raw bytes.
	 *
	 * @var string
	 */
	public $census_tag;

	/**
	 * Complete validated target projection inventory.
	 *
	 * @var array<int,SnapshotManifestEntry>
	 */
	public $entries;

	/**
	 * @param int                              $schema_level Schema level.
	 * @param string                           $census_tag Raw 32-byte CensusTag.
	 * @param array<int,SnapshotManifestEntry> $entries Complete projection entries.
	 */
	public function __construct( int $schema_level, string $census_tag, array $entries ) {
		$this->schema_level = $schema_level;
		$this->census_tag   = $census_tag;
		$this->entries      = $entries;
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
