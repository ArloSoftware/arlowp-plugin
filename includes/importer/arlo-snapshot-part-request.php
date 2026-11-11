<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Request value object for one concrete downloadable part of a platform snapshot.
 *
 * The part can be the root JSON document that describes the snapshot image or
 * one JSON document for an element fragment named by that root document.
 */
class SnapshotPartRequest {
	const PART_TYPE_SNAPSHOT = 'snapshot';
	const PART_TYPE_FRAGMENT = 'fragment';
	/**
	 * URI for the requested root snapshot JSON document or element fragment JSON document.
	 *
	 * @var string
	 */
	public $uri;

	/** @var SnapshotContext */
	public $context;

	/**
	 * Part type: `snapshot` for the root snapshot JSON document or `fragment` for an
	 * element fragment JSON document.
	 *
	 * @var string
	 */
	public $part_type;

	/**
	 * Zero-based element-fragment index, or null for the root snapshot JSON document.
	 *
	 * @var int|null
	 */
	public $fragment_index;

	/**
	 * @param SnapshotContext $context Reusable transport and decryption metadata for this snapshot.
	 * @param string          $uri URI for the requested root snapshot or element fragment JSON document.
	 * @param string   $part_type Part type: `PART_TYPE_SNAPSHOT` or `PART_TYPE_FRAGMENT`.
	 * @param int|null $fragment_index Element-fragment index, or null for the root snapshot.
	 */
	public function __construct( SnapshotContext $context, string $uri, string $part_type, ?int $fragment_index ) {
		$this->context        = $context;
		$this->uri            = $uri;
		$this->part_type      = $part_type;
		$this->fragment_index = $fragment_index;
	}
}
