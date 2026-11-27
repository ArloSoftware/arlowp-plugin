<?php
/**
 * Context-aware substitution for shortcode wrapper fragments.
 */

namespace ArloTraining\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Closure;
use WP_HTML_Decoder;
use WP_HTML_Tag_Processor;

/**
 * Resolves the attribute slots in a shortcode's wrap argument.
 *
 * Only the wrap argument is read for %s placeholders, never the full template, and %% is a literal
 * percent sign. The wrapper still passes through wp_kses_post(), so markup KSES removes is gone.
 *
 * Body slots (plain text or element content, as in wrap="Course: %s" or wrap="<div>%s</div>") are left
 * for ResolvedShortcodeWrap::fill_body(), which inserts the normal rendered output, including markup.
 *
 * Attribute slots are filled here, and only for the shortcodes allowed to supply that kind of value:
 * - href: the slot must be the whole value, filled with a URL shortcode's HTTP(S) or relative URL,
 *   validated, then escaped with esc_url(). An image URL shortcode can also fill a whole src.
 * - style: the slot must be inside a url(), filled by an image URL shortcode.
 * - class: the slot must be a separate token. Tag shortcodes supply tokens only in layout="class";
 *   code shortcodes are reduced to letters, digits, hyphens and underscores. The value is escaped
 *   with esc_attr().
 *
 * Finalized attribute values are kept out of any later shortcode expansion by encoding their
 * square brackets.
 *
 * Failures never insert a value verbatim. An unsupported slot or an invalid value drops that
 * attribute, except that a class slot is dropped on its own and the static class tokens are kept.
 * A wrapper whose placement is ambiguous, such as a slot in a tag name or comment, or whose slot
 * count KSES changed, is skipped and the shortcode renders without it. A wrapper with attribute
 * slots and no body slot, such as an opening <a href="%s">, is returned without a body.
 *
 * Nothing is logged unless a handler is set with set_logging_handler(); messages are fixed text
 * with no template or imported content.
 */
final class ShortcodeWrapSanitizer {

	private const SHORTCODE_EVENT_TEMPLATE_PERMALINK      = 'arlo_event_template_permalink';
	private const SHORTCODE_EVENT_TEMPLATE_LINK           = 'arlo_event_template_link';
	private const SHORTCODE_EVENT_TEMPLATE_LIST_IMAGE_SRC = 'arlo_event_template_list_image_src';
	private const SHORTCODE_EVENT_TEMPLATE_TAGS           = 'arlo_event_template_tags';
	private const SHORTCODE_EVENT_TAGS                    = 'arlo_event_tags';
	private const SHORTCODE_PRESENTER_LINK                = 'arlo_presenter_link';
	private const SHORTCODE_PRESENTER_PERMALINK           = 'arlo_presenter_permalink';
	private const SHORTCODE_PRESENTER_SOCIAL_LINK         = 'arlo_presenter_social_link';
	private const SHORTCODE_VENUE_DIRECTION               = 'arlo_venue_direction';
	private const SHORTCODE_VENUE_EVENTS_LINK             = 'arlo_venue_events_link';
	private const SHORTCODE_VENUE_LINK                    = 'arlo_venue_link';
	private const SHORTCODE_VENUE_PERMALINK               = 'arlo_venue_permalink';

	/**
	 * Shortcodes whose whole output is a URL, usable as a whole href. A shortcode that can also return
	 * markup (the social link with link text) is safe here because markup fails URL validation.
	 */
	private const SHORTCODES_ALLOWED_IN_WRAP_HREF = [
		self::SHORTCODE_EVENT_TEMPLATE_PERMALINK,
		self::SHORTCODE_EVENT_TEMPLATE_LINK,
		self::SHORTCODE_EVENT_TEMPLATE_LIST_IMAGE_SRC,
		self::SHORTCODE_PRESENTER_LINK,
		self::SHORTCODE_PRESENTER_PERMALINK,
		self::SHORTCODE_PRESENTER_SOCIAL_LINK,
		self::SHORTCODE_VENUE_DIRECTION,
		self::SHORTCODE_VENUE_EVENTS_LINK,
		self::SHORTCODE_VENUE_LINK,
		self::SHORTCODE_VENUE_PERMALINK,
	];

	/**
	 * Image URL shortcodes usable as a whole src, or inside a url() in a style attribute.
	 */
	private const SHORTCODES_ALLOWED_IN_WRAP_IMAGE_URL = [
		self::SHORTCODE_EVENT_TEMPLATE_LIST_IMAGE_SRC,
	];

	/**
	 * Tag shortcodes that output class tokens when layout="class".
	 */
	private const SHORTCODES_ALLOWED_IN_WRAP_CLASS_LAYOUT = [
		self::SHORTCODE_EVENT_TEMPLATE_TAGS,
		self::SHORTCODE_EVENT_TAGS,
	];

	/**
	 * Plain-text code shortcodes whose output is reduced to class-safe tokens for a class slot.
	 */
	private const SHORTCODES_ALLOWED_IN_WRAP_CLASS_TEXT = [
		'arlo_event_code',
		'arlo_event_template_code',
		'arlo_oa_code',
	];

	/**
	 * Optional diagnostic callback for wrapper fallbacks.
	 *
	 * @var Closure|null
	 */
	private static ?Closure $logging_handler = null;

	/**
	 * Resolve a wrapper's attribute slots before any shortcode output is inserted into its body.
	 *
	 * Each attribute slot is filled with a validated, escaped value or its attribute is removed.
	 * Only ordinary text slots remain, so filling them later preserves rendered HTML without
	 * re-filtering it. A wrapper whose placement cannot be classified is skipped.
	 *
	 * @param string $wrap_template Author-supplied wrap fragment.
	 * @param string $filtered_shortcode_output Shortcode output after filters; may contain markup. Non-scalars are passed as an empty string.
	 * @param string $shortcode_name Registered shortcode name.
	 * @param bool   $uses_class_layout Whether the tag shortcode selected its class layout.
	 * @return ResolvedShortcodeWrap|null Null means render without a wrapper.
	 */
	public static function resolve_attribute_slots( string $wrap_template, string $filtered_shortcode_output, string $shortcode_name, bool $uses_class_layout ): ?ResolvedShortcodeWrap {
		if ( '%s' === $wrap_template ) {
			return null;
		}

		$slot_marker        = 'arlo-wrap-' . wp_generate_uuid4();
		$unfiltered_wrapper = Shortcodes::apply_placeholder( $wrap_template, $slot_marker );
		$html               = Shortcodes::apply_placeholder( wp_kses_post( $wrap_template ), $slot_marker );
		$body               = 0;
		$edits              = [];
		$omitted            = substr_count( $unfiltered_wrapper, $slot_marker ) > substr_count( $html, $slot_marker );

		// Check both versions for possible markup, even if KSES stripped it from one.
		if ( false !== strpos( $unfiltered_wrapper, '<' ) || false !== strpos( $html, '<' ) ) {
			// If KSES changed the wrapper, record the original body slot count for comparison.
			$unfiltered_body_slots = $unfiltered_wrapper === $html
				? null
				: self::scan_wrap_slots( new WP_HTML_Tag_Processor( $unfiltered_wrapper ), $unfiltered_wrapper, $slot_marker );

			// Count the remaining body slots and validate attribute slots in their own context.
			$processor = new WP_HTML_Tag_Processor( $html );
			$body      = self::scan_wrap_slots(
				$processor,
				$html,
				$slot_marker,
				static function ( string $attribute_name, string $attribute_value_with_slots ) use ( $processor, $slot_marker, $filtered_shortcode_output, $shortcode_name, $uses_class_layout, &$edits, &$omitted ): bool {
					$escaped = self::escape_wrap_attribute( $attribute_name, $attribute_value_with_slots, $slot_marker, $filtered_shortcode_output, $shortcode_name, $uses_class_layout, $omitted );
					if ( '' === $escaped ) {
						$omitted = true;
						return $processor->remove_attribute( $attribute_name );
					}

					// Use a temporary value to avoid escaping the final value twice.
					$key = '/' . $slot_marker . '-' . count( $edits ) . '-value';
					// Encode brackets so later shortcode passes cannot expand the attribute value.
					$edits[ $key ] = strtr(
						$escaped,
						[
							'[' => '&#x5b;',
							']' => '&#x5d;',
						]
					);
					return $processor->set_attribute( $attribute_name, $key );
				}
			);

			// Skip the wrapper if a slot is unclassified or KSES changed the body slot count.
			if ( null === $body || ( $unfiltered_wrapper !== $html && $unfiltered_body_slots !== $body ) ) {
				self::report_fallback( 'Shortcode wrap skipped: ambiguous placeholder placement.' );
				return null;
			}

			// Replace the temporary values after the HTML processor has written the quoted attributes.
			$html = strtr( $processor->get_updated_html(), $edits );
		} else {
			// With no possible markup, every slot is a body slot.
			$body = substr_count( $html, $slot_marker );
		}

		if ( $omitted ) {
			self::report_fallback( 'Shortcode wrap attribute omitted: unsupported placement or invalid value.' );
		}

		return new ResolvedShortcodeWrap(
			$html,
			$slot_marker,
			0 === $body && false !== strpos( $unfiltered_wrapper, $slot_marker )
		);
	}

	/**
	 * Set an optional diagnostic callback for the current request. Nothing is logged by default.
	 *
	 * @param callable|null $handler Callback receiving one fixed diagnostic message, or null to disable it.
	 * @return void
	 */
	public static function set_logging_handler( ?callable $handler ): void {
		self::$logging_handler = null === $handler ? null : Closure::fromCallable( $handler );
	}

	/**
	 * Count text slots and visit attribute slots. A count mismatch covers syntax WordPress does
	 * not expose as either: names, comments, raw text, incomplete tags and hidden duplicates.
	 *
	 * A noscript or plaintext element is read differently by a browser than by this tokenizer, so a slot
	 * inside or after the first one is ambiguous and the placement is rejected. A slot before it is
	 * classified as usual, so the wrapper survives.
	 *
	 * @param WP_HTML_Tag_Processor $processor HTML tokenizer for the wrapper.
	 * @param string                $html Original bytes for this tokenizer.
	 * @param string                $slot_marker Marker replacing a real %s.
	 * @param callable|null         $edit Optional attribute editor.
	 * @return int|null Number of ordinary text slots, or null when placement is ambiguous.
	 */
	private static function scan_wrap_slots( WP_HTML_Tag_Processor $processor, string $html, string $slot_marker, ?callable $edit = null ): ?int {
		$expected = substr_count( $html, $slot_marker );
		if ( 0 === $expected ) {
			return 0;
		}

		$body       = 0;
		$attributes = 0;
		$ambiguous  = false;
		while ( $processor->next_token() ) {
			if ( '#text' === $processor->get_token_type() ) {
				$text_slots = substr_count( $processor->get_modifiable_text(), $slot_marker );
				if ( $ambiguous && $text_slots > 0 ) {
					return null;
				}
				$body += $text_slots;
				continue;
			}

			if ( '#tag' !== $processor->get_token_type() || $processor->is_tag_closer() ) {
				continue;
			}
			if ( in_array( $processor->get_tag(), [ 'NOSCRIPT', 'PLAINTEXT' ], true ) ) {
				$ambiguous = true;
				continue;
			}

			foreach ( $processor->get_attribute_names_with_prefix( '' ) ?? [] as $attribute_name ) {
				$attribute_value_with_slots = $processor->get_attribute( $attribute_name );
				if ( ! is_string( $attribute_value_with_slots ) || false === strpos( $attribute_value_with_slots, $slot_marker ) ) {
					continue;
				}
				if ( $ambiguous ) {
					return null;
				}
				$attributes += substr_count( $attribute_value_with_slots, $slot_marker );
				if ( $edit && ! $edit( $attribute_name, $attribute_value_with_slots ) ) {
					return null;
				}
			}
		}

		return ! $processor->paused_at_incomplete_token() && $expected === $body + $attributes ? $body : null;
	}

	/**
	 * Resolve only the attribute contracts used by the bundled templates and documented examples.
	 *
	 * @param string $attribute_name Attribute name.
	 * @param string $attribute_value_with_slots Decoded attribute value with slot markers.
	 * @param string $slot_marker Marker replacing a real %s.
	 * @param string $filtered_shortcode_output Shortcode output before attribute validation.
	 * @param string $shortcode_name Registered shortcode name.
	 * @param bool   $uses_class_layout Whether the tag shortcode selected its class layout.
	 * @param bool   $omitted Set when any part of the attribute was left out.
	 * @return string Escaped attribute value, or an empty string to remove it.
	 */
	private static function escape_wrap_attribute( string $attribute_name, string $attribute_value_with_slots, string $slot_marker, string $filtered_shortcode_output, string $shortcode_name, bool $uses_class_layout, bool &$omitted ): string {
		if ( $attribute_value_with_slots === $slot_marker ) {
			if ( 'href' === $attribute_name && in_array( $shortcode_name, self::SHORTCODES_ALLOWED_IN_WRAP_HREF, true ) ) {
				return self::escape_wrap_url( $filtered_shortcode_output );
			}
			if ( 'src' === $attribute_name && in_array( $shortcode_name, self::SHORTCODES_ALLOWED_IN_WRAP_IMAGE_URL, true ) ) {
				return self::escape_wrap_url( $filtered_shortcode_output );
			}
		}

		if ( 'style' === $attribute_name && in_array( $shortcode_name, self::SHORTCODES_ALLOWED_IN_WRAP_IMAGE_URL, true ) ) {
			return self::escape_wrap_inline_style_url( $attribute_value_with_slots, $slot_marker, $filtered_shortcode_output );
		}

		if ( 'class' === $attribute_name ) {
			return self::escape_wrap_class( $attribute_value_with_slots, $slot_marker, $filtered_shortcode_output, $shortcode_name, $uses_class_layout, $omitted );
		}

		return '';
	}

	/**
	 * Fill a class attribute. A slot that cannot be filled is dropped and the author's static
	 * tokens are kept, so selectors that depend on them keep working.
	 *
	 * @param string $attribute_value_with_slots Decoded class attribute value with slot markers.
	 * @param string $slot_marker Marker replacing a real %s.
	 * @param string $filtered_shortcode_output Shortcode output before validation.
	 * @param string $shortcode_name Registered shortcode name.
	 * @param bool   $uses_class_layout Whether the tag shortcode selected its class layout.
	 * @param bool   $omitted Set when a token was left out.
	 * @return string Escaped class list, or an empty string to remove the attribute.
	 */
	private static function escape_wrap_class( string $attribute_value_with_slots, string $slot_marker, string $filtered_shortcode_output, string $shortcode_name, bool $uses_class_layout, bool &$omitted ): string {
		$slot_tokens = self::resolve_wrap_class_tokens( $filtered_shortcode_output, $shortcode_name, $uses_class_layout );
		$classes     = [];
		foreach ( preg_split( '/[ \t\r\n\f]+/', trim( $attribute_value_with_slots, " \t\r\n\f" ) ) as $token ) {
			if ( $slot_marker === $token && null !== $slot_tokens ) {
				array_push( $classes, ...$slot_tokens );
			} elseif ( false !== strpos( $token, $slot_marker ) ) {
				$omitted = true;
			} elseif ( '' !== $token ) {
				$classes[] = $token;
			}
		}

		return esc_attr( str_replace( '&', '&amp;', implode( ' ', $classes ) ) );
	}

	/**
	 * Fill url() values in a style attribute. Every slot must sit inside a url(); the URL is
	 * validated like an href and its parentheses and apostrophes are percent-encoded for CSS.
	 *
	 * @param string $attribute_value_with_slots Decoded style attribute value with slot markers.
	 * @param string $slot_marker Marker replacing a real %s.
	 * @param string $filtered_shortcode_output Shortcode output before URL validation.
	 * @return string Escaped style value, or an empty string to remove the attribute.
	 */
	private static function escape_wrap_inline_style_url( string $attribute_value_with_slots, string $slot_marker, string $filtered_shortcode_output ): string {
		$escaped_url = self::escape_wrap_url( $filtered_shortcode_output );
		if ( '' === $escaped_url ) {
			return '';
		}

		$url   = strtr(
			WP_HTML_Decoder::decode_attribute( $escaped_url ),
			[
				'(' => '%28',
				')' => '%29',
				"'" => '%27',
			]
		);
		$count = 0;
		$style = preg_replace_callback(
			'/url\(\s*(["\']?)' . preg_quote( $slot_marker, '/' ) . '\1\s*\)/i',
			static function () use ( $url ): string {
				return 'url(' . $url . ')';
			},
			$attribute_value_with_slots,
			-1,
			$count
		);
		if ( null === $style || substr_count( $attribute_value_with_slots, $slot_marker ) !== $count ) {
			return '';
		}

		return esc_attr( str_replace( '&', '&amp;', $style ) );
	}

	/**
	 * Escape a whole href supplied as a raw URL or a value already escaped for display by WordPress.
	 *
	 * @param string $filtered_shortcode_output Shortcode output before URL validation.
	 * @return string Escaped URL, or an empty string to remove the attribute.
	 */
	private static function escape_wrap_url( string $filtered_shortcode_output ): string {
		// Undo only WordPress's display escaping for ampersands and apostrophes, not arbitrary entities in URL data.
		$url = preg_replace(
			[ '/&(?:amp|#0*38|#[xX]0*26);/', '/&(?:apos|#0*39|#[xX]0*27);/' ],
			[ '&', "'" ],
			trim( $filtered_shortcode_output )
		);
		if ( null === $url ) {
			return '';
		}

		$url_kind = self::parse_url_kind( $url );
		if ( '' === $url_kind ) {
			return '';
		}

		// Protect ampersands and keep esc_url() from rewriting literal semicolon/slash sequences.
		$url = str_replace( ';//', ';&#47;/', str_replace( '&', '&amp;', $url ) );
		// A temporary root slash keeps esc_url() from inferring a scheme from relative paths, queries, or fragments.
		$needs_root_slash = 'relative' === $url_kind && '/' !== $url[0];
		$escaped_url      = esc_url( $needs_root_slash ? '/' . $url : $url, [ 'https', 'http' ] );
		if ( $needs_root_slash ) {
			$escaped_url = 0 === strpos( $escaped_url, '/' ) ? substr( $escaped_url, 1 ) : '';
		}

		// Check the browser-facing value after normalization and confirm it is safe in a quoted attribute.
		if ( false !== strpbrk( $escaped_url, '<>"' ) || $url_kind !== self::parse_url_kind( WP_HTML_Decoder::decode_attribute( $escaped_url ) ) ) {
			return '';
		}

		return $escaped_url;
	}

	/**
	 * Resolve the class tokens this shortcode's output may supply to a class slot.
	 *
	 * @param string $filtered_shortcode_output Shortcode output before validation.
	 * @param string $shortcode_name Registered shortcode name.
	 * @param bool   $uses_class_layout Whether the tag shortcode selected its class layout.
	 * @return string[]|null Class tokens, which may be empty when nothing usable remains, or null when
	 *                       the shortcode or value is not allowed to fill a class slot.
	 */
	private static function resolve_wrap_class_tokens( string $filtered_shortcode_output, string $shortcode_name, bool $uses_class_layout ): ?array {
		$value = trim( $filtered_shortcode_output, " \t\r\n\f" );

		if ( in_array( $shortcode_name, self::SHORTCODES_ALLOWED_IN_WRAP_CLASS_LAYOUT, true ) ) {
			// sanitize_title(), used by the tag handlers, also produces percent-encoded UTF-8 slugs.
			if ( ! $uses_class_layout || ! preg_match( '/^(?:[a-z0-9_-]|%[a-f0-9]{2})+(?:[ \t\r\n\f]+(?:[a-z0-9_-]|%[a-f0-9]{2})+)*$/iD', $value ) ) {
				return null;
			}
			return preg_split( '/[ \t\r\n\f]+/', $value );
		}

		if ( in_array( $shortcode_name, self::SHORTCODES_ALLOWED_IN_WRAP_CLASS_TEXT, true ) ) {
			// These handlers escape their text for HTML, so markup here means a filter changed the contract.
			if ( false !== strpbrk( $value, '<>' ) ) {
				return null;
			}
			$tokens = [];
			foreach ( preg_split( '/\s+/', wp_specialchars_decode( $value, ENT_QUOTES ), -1, PREG_SPLIT_NO_EMPTY ) as $part ) {
				$token = sanitize_html_class( $part );
				if ( '' !== $token ) {
					$tokens[] = $token;
				}
			}
			return $tokens;
		}

		return null;
	}

	/**
	 * Classify a literal URL before escaping or after HTML attribute decoding.
	 *
	 * @param string $url URL as the browser would read it from the attribute.
	 * @return string URL kind, or an empty string if unsupported.
	 */
	private static function parse_url_kind( string $url ): string {
		if ( '' === $url || preg_match( '/[<>"\\\\\x00-\x20\x7f]/', $url ) ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( false === $parts ) {
			return '';
		}
		if ( isset( $parts['scheme'] ) ) {
			return ! empty( $parts['host'] ) && preg_match( '~^https?://~i', $url ) ? 'absolute' : '';
		}
		if ( 0 === strpos( $url, '//' ) ) {
			return ! empty( $parts['host'] ) ? 'network' : '';
		}

		// A relative path may not contain a colon in its first segment; queries and fragments may.
		return false !== strpos( substr( $url, 0, strcspn( $url, '/?#' ) ), ':' ) ? '' : 'relative';
	}

	/**
	 * @param string $message Fixed diagnostic message with no template or shortcode content.
	 * @return void
	 */
	private static function report_fallback( string $message ): void {
		if ( null !== self::$logging_handler ) {
			( self::$logging_handler )( $message );
		}
	}
}
