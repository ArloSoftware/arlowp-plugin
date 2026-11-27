<?php
/**
 * A shortcode wrapper whose attribute slots have been resolved.
 */

namespace ArloTraining\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The wrap argument after ShortcodeWrapSanitizer has filled or removed its attribute slots.
 * Only body slots, which take the rendered shortcode output, are left to fill.
 *
 * @internal
 */
final class ResolvedShortcodeWrap {

	private string $html;

	private string $body_slot_marker;

	private bool $attributes_only;

	/**
	 * @param string $html Wrapper markup with attribute slots resolved and body slots holding the marker.
	 * @param string $body_slot_marker Unique text standing in for each body slot.
	 * @param bool   $attributes_only Whether the author's wrapper had attribute slots and no body slot.
	 */
	public function __construct( string $html, string $body_slot_marker, bool $attributes_only ) {
		$this->html             = $html;
		$this->body_slot_marker = $body_slot_marker;
		$this->attributes_only  = $attributes_only;
	}

	/**
	 * An attribute-only wrapper, such as an opening tag, consumes the shortcode output through its
	 * attributes; the body, labels and string stripping do not apply.
	 *
	 * @return bool
	 */
	public function is_attributes_only(): bool {
		return $this->attributes_only;
	}

	/**
	 * Insert rendered shortcode output into every body slot. The output is inserted as is.
	 *
	 * @param string $body Rendered output, which may contain markup.
	 * @return string Wrapper markup, still to be expanded for nested shortcodes.
	 */
	public function fill_body( string $body ): string {
		return str_replace( $this->body_slot_marker, $body, $this->html );
	}
}
