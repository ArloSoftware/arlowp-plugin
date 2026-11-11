<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * One validated complete projection key in a snapshot manifest.
 */
class SnapshotManifestEntry {
	/**
	 * Schema-level projection kind that owns the physical key shape.
	 *
	 * @var string
	 */
	public $kind;

	/**
	 * Canonical ordered physical-key components as validated values.
	 *
	 * @var array<int,string>
	 */
	public $key;

	/**
	 * Canonical source region code, or the empty region sentinel; event rows retain it as varchar(16).
	 *
	 * @var string
	 */
	public $region;

	/**
	 * Server-supplied structural state tag, stored as 32 raw bytes.
	 *
	 * @var string
	 */
	public $structural_tag;

	/**
	 * Server-supplied availability state tag, stored as 32 raw bytes when applicable.
	 *
	 * @var string|null
	 */
	public $availability_tag;

	/**
	 * Authenticated image-inventory index for a selectable source fragment, if any.
	 *
	 * @var int|null
	 */
	public $fragment_element_index;

	/**
	 * @param string            $kind Kind.
	 * @param array<int,string> $key Canonical opaque key bytes.
	 * @param string            $region Canonical source region code, or an empty sentinel.
	 * @param string            $structural_tag Raw 32-byte structural tag.
	 * @param string|null       $availability_tag Raw 32-byte availability tag, if applicable.
	 * @param int|null          $fragment_element_index Declared source fragment index, if selectable.
	 */
	public function __construct( string $kind, array $key, string $region, string $structural_tag, ?string $availability_tag, ?int $fragment_element_index ) {
		$this->kind                   = $kind;
		$this->key                    = $key;
		$this->region                 = $region;
		$this->structural_tag         = $structural_tag;
		$this->availability_tag       = $availability_tag;
		$this->fragment_element_index = $fragment_element_index;
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName
