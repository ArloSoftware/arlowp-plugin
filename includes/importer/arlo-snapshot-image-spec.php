<?php // phpcs:disable WordPress.Files.FileName.InvalidClassFileName -- This established importer file follows the arlo-* loader convention.

namespace ArloTraining\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Validated fragment manifest declared by a decoded snapshot image.
 */
class SnapshotImageSpec {
	/** @var array<int,array<string,string>> */
	private $fragment_elements;

	/**
	 * @param array<int,array<string,string>> $fragment_elements Validated fragment elements keyed by image index.
	 */
	private function __construct( array $fragment_elements ) {
		$this->fragment_elements = $fragment_elements;
	}

	/**
	 * Parses a snapshot image and validates its declared fragment locations.
	 *
	 * @param string $snapshot_image_content Snapshot image JSON content.
	 * @return self Validated snapshot image fragment manifest.
	 * @throws \ArloTraining\SnapshotProcessException When the image does not contain valid fragment elements.
	 */
	public static function from_content( string $snapshot_image_content ): self {
		$image             = json_decode( $snapshot_image_content, true );
		$full_fragments    = is_array( $image ) ? ( $image['FullImageFragments'] ?? null ) : null;
		$fragment_elements = is_array( $full_fragments ) ? ( $full_fragments['Elements'] ?? null ) : null;
		if ( ! is_array( $fragment_elements ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot image does not contain valid fragment elements.' );
		}

		foreach ( $fragment_elements as $index => $fragment_element ) {
			if ( ! is_int( $index ) || ! is_array( $fragment_element ) || ! isset( $fragment_element['Uri'] ) || ! is_string( $fragment_element['Uri'] ) || '' === trim( $fragment_element['Uri'] ) ) {
				throw new \ArloTraining\SnapshotProcessException( 'Snapshot image does not contain valid fragment elements.' );
			}
			$fragment_elements[ $index ]['Uri'] = trim( $fragment_element['Uri'] );
		}

		return new self( $fragment_elements );
	}

	/**
	 * Returns all validated fragment elements, keyed by their image index.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_fragment_elements(): array {
		return $this->fragment_elements;
	}

	/**
	 * Returns the URI declared for one snapshot-image fragment index.
	 *
	 * @param int $fragment_element_index Snapshot-image fragment index.
	 * @return string Fragment URI.
	 * @throws \ArloTraining\SnapshotProcessException When the index is not declared by the snapshot image.
	 */
	public function get_fragment_uri( int $fragment_element_index ): string {
		if ( ! isset( $this->fragment_elements[ $fragment_element_index ]['Uri'] ) ) {
			throw new \ArloTraining\SnapshotProcessException( 'Snapshot image does not declare the requested fragment.' );
		}

		return $this->fragment_elements[ $fragment_element_index ]['Uri'];
	}
}
// phpcs:enable WordPress.Files.FileName.InvalidClassFileName