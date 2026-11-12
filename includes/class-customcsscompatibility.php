<?php
/**
 * Read-only advice about moving Custom CSS into Additional CSS.
 *
 * @package ArloTraining
 */

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A bounded token scan, not a CSS validator or a guarantee of compatibility.
 */
class CustomCssCompatibility {

	private const MAX_BYTES    = 2097152;
	private const MAX_FINDINGS = 50;
	private const MAX_DEPTH    = 256;

	/**
	 * Source CSS, never normalized or rewritten.
	 *
	 * @var string
	 */
	private $css;

	/**
	 * Current byte offset.
	 *
	 * @var int
	 */
	private $offset = 0;

	/**
	 * One-based source line.
	 *
	 * @var int
	 */
	private $line = 1;

	/**
	 * One-based byte column.
	 *
	 * @var int
	 */
	private $column = 1;

	/**
	 * Advisory result.
	 *
	 * @var array
	 */
	private $result = [
		'status'   => 'clear',
		'blocked'  => false,
		'findings' => [],
	];

	/**
	 * Whether an analysis bound has stopped the scan.
	 *
	 * @var bool
	 */
	private $stopped = false;

	/**
	 * Whether the findings bound has been reached; scanning continues so structural problems are still found.
	 *
	 * @var bool
	 */
	private $capped = false;

	/**
	 * Whether the CSS is going to a block theme's merged global stylesheet.
	 *
	 * @var bool
	 */
	private $block_theme = false;

	/**
	 * Whether the CSS currently comes from the old plugin-folder stylesheet, which resolves relative paths against that file.
	 *
	 * @var bool
	 */
	private $from_file = true;

	/**
	 * Tokens read so far, excluding whitespace and comments.
	 *
	 * @var int
	 */
	private $tokens_seen = 0;

	/**
	 * Analyze constructs that behave differently once the CSS moves, without saving or changing CSS.
	 *
	 * Limits apply only to analysis. Locations use one-based byte columns.
	 *
	 * @param string $css     CSS to examine.
	 * @param array  $context Optional destination facts: 'block_theme' (bool) and 'from_file' (bool).
	 * @return array Status, whether anything blocks migration, and translated, source-located findings.
	 */
	public static function analyze( string $css, array $context = [] ): array {
		$scanner              = new self();
		$scanner->block_theme = ! empty( $context['block_theme'] );
		$scanner->from_file   = ! array_key_exists( 'from_file', $context ) || ! empty( $context['from_file'] );
		if ( strlen( $css ) > self::MAX_BYTES ) {
			$scanner->finding( 'analysis-limit', __( 'This check can only analyze CSS up to 2 MiB, so it cannot confirm this CSS is safe to migrate. Ask your developer to review this larger stylesheet.', 'arlo-training-and-event-management-system' ), true, null, 'blocking' );
			return $scanner->result;
		}

		$scanner->css = $css;
		$scanner->scan();
		if ( 0 === $scanner->tokens_seen && '' !== trim( $css ) ) {
			$scanner->finding( 'comment-only', __( 'This CSS contains only comments. It is migrated as it is, so any rules you have switched off stay available to switch back on.', 'arlo-training-and-event-management-system' ), false, null, 'info' );
		}
		return $scanner->result;
	}

	/**
	 * Scan tokens, keeping only delimiter and declaration context.
	 *
	 * @return void
	 */
	private function scan(): void {
		$stack          = [];
		$property       = '';
		$candidate      = null;
		$seen_rule      = false;
		$in_import      = false;
		$import_pending = false;
		$open_prelude   = false;

		while ( ! $this->stopped ) {
			$token = $this->token();
			if ( null === $token ) {
				break;
			}

			$type     = $token['type'];
			$value    = strtolower( $token['value'] );
			$top      = count( $stack ) - 1;
			$boundary = $top < 0 || '}' === $stack[ $top ]['close'];

			// Anything at the top level other than a terminator starts a rule or statement that a later ; or } must finish.
			if ( $top < 0 && ! ( 'symbol' === $type && in_array( $value, [ ';', '}' ], true ) ) ) {
				$open_prelude = true;
			}

			if ( 'symbol' === $type && '/' === $value && '/' === substr( $this->css, $this->offset, 1 ) ) {
				$this->finding( 'line-comment', __( 'Two slashes do not start a comment in CSS, so the rule or declaration after this point is already ignored by browsers. Use /* ... */ for comments.', 'arlo-training-and-event-management-system' ), false, $token );
				$this->advance();
				continue;
			}
			if ( 'at' === $type && 'charset' === $value && $this->tokens_seen > 0 ) {
				$this->finding( 'charset-midfile', __( 'An @charset rule is only honoured at the very start of a stylesheet, so it is already ignored here. You can remove it.', 'arlo-training-and-event-management-system' ), false, $token, 'info' );
			}
			++$this->tokens_seen;

			if ( ! empty( $token['escaped'] ) && 'string' !== $type ) {
				$this->finding( 'escaped-syntax', __( 'This check cannot decode escaped CSS names. Ask your developer to check any image, font or stylesheet URLs in this rule before migrating.', 'arlo-training-and-event-management-system' ), true, $token );
			}

			if ( $import_pending ) {
				$import_pending = false;
				if ( 'string' === $type ) {
					$this->reference( $token['value'], $token, $token['escaped'] );
				} elseif ( 'function' !== $type || 'url' !== $value ) {
					$this->finding( 'unsupported-url-syntax', __( 'Use @import with a quoted stylesheet URL or url(...), placed before ordinary rules. Ask your developer to review other import syntax.', 'arlo-training-and-event-management-system' ), true, $token );
				}
			}

			if ( 'at' === $type && 'import' === $value ) {
				$this->finding( 'import', __( 'Keep @import statements at the top of Custom CSS, before style rules. After migrating, check that the imported stylesheet still loads. If both are already true, you can leave this import unchanged.', 'arlo-training-and-event-management-system' ), false, $token );
				if ( $this->block_theme ) {
					$this->finding( 'block-theme-import', $this->block_theme_import_advice(), false, $token );
				}
				if ( $seen_rule || ! empty( $stack ) ) {
					$this->finding( 'import-order', __( 'Move this @import out of any nested block and place it before ordinary style rules. Browsers can ignore imports in their current position.', 'arlo-training-and-event-management-system' ), false, $token );
				}
				$in_import      = true;
				$import_pending = true;
			} elseif ( 'at' === $type && ! in_array( $value, [ 'charset', 'layer' ], true ) ) {
				$seen_rule = true;
			}

			if ( $top >= 0 && ! empty( $stack[ $top ]['image_set'] ) ) {
				if ( ',' === $value && 'symbol' === $type ) {
					$stack[ $top ]['expect_image'] = true;
				} else {
					if ( 'string' === $type && $stack[ $top ]['expect_image'] ) {
						$this->reference( $token['value'], $token, $token['escaped'] );
					} elseif ( 'name' === $type && $stack[ $top ]['expect_image'] ) {
						$this->finding( 'unsupported-url-syntax', __( 'Check every image-set() candidate and use full image URLs where necessary. Ask your developer to review any syntax this check cannot read.', 'arlo-training-and-event-management-system' ), true, $token );
					}
					$stack[ $top ]['expect_image'] = false;
				}
			}

			if ( 'function' === $type ) {
				if ( 'url' === $value ) {
					// A quoted url() that is not closed stays open on the stack, so it is blocked if nothing ever closes it.
					if ( ! $this->url( $token ) ) {
						$stack[] = [
							'close'        => ')',
							'image_set'    => false,
							'expect_image' => true,
							'token'        => $token,
						];
					}
					continue;
				}

				$resource_context = $this->resource_property( $property );
				foreach ( $stack as $frame ) {
					$resource_context = $resource_context || ! empty( $frame['image_set'] );
				}
				if ( $resource_context && in_array( $value, [ 'var', 'attr', 'env' ], true ) ) {
					$this->finding( 'resource-variable', __( 'Check the value of the variable or function used for this resource. If it contains a relative URL, use the full intended resource URL or verify it on each affected page.', 'arlo-training-and-event-management-system' ), true, $token );
				}
				if ( in_array( $value, [ 'src', 'image', 'url-prefix', 'url-pattern', 'element', '-moz-element' ], true ) ) {
					$this->finding( 'unsupported-url-syntax', __( 'This resource-loading function is not supported by the check. Ask your developer to verify that its resources still load after migrating.', 'arlo-training-and-event-management-system' ), true, $token );
				}
			}

			if ( 'name' === $type && 'url' === $value && ( '' !== $property || $in_import ) ) {
				$this->finding( 'unsupported-url-syntax', __( 'Write resource links as url(...), with no space between url and (. If this syntax is intentional, ask your developer to verify it.', 'arlo-training-and-event-management-system' ), true, $token );
			}

			if ( 'function' === $type || ( 'symbol' === $type && in_array( $value, [ '(', '[', '{' ], true ) ) ) {
				if ( count( $stack ) >= self::MAX_DEPTH ) {
					$this->finding( 'analysis-limit', __( 'This check cannot read CSS nested more than 256 levels deep. Ask your developer to review the nested rules before migrating.', 'arlo-training-and-event-management-system' ), true, $token, 'blocking' );
					$this->stopped = true;
					break;
				}
				$stack[] = [
					'close'        => 'function' === $type ? ')' : [
						'(' => ')',
						'[' => ']',
						'{' => '}',
					][ $value ],
					'image_set'    => 'function' === $type && in_array( $value, [ 'image-set', '-webkit-image-set' ], true ),
					'expect_image' => true,
					'token'        => $token,
				];
				if ( '{' === $value ) {
					$seen_rule = true;
					if ( $in_import ) {
						$this->malformed( $token );
					}
					$property  = '';
					$candidate = null;
				}
				continue;
			}

			if ( 'symbol' === $type && in_array( $value, [ ')', ']', '}' ], true ) ) {
				if ( $top < 0 || $stack[ $top ]['close'] !== $value ) {
					$this->malformed( $token );
				} else {
					array_pop( $stack );
					if ( empty( $stack ) ) {
						$open_prelude = false;
					}
				}
				if ( '}' === $value ) {
					$property  = '';
					$candidate = null;
				}
				continue;
			}

			if ( $boundary && 'symbol' === $type && ';' === $value ) {
				$property  = '';
				$candidate = null;
				$in_import = false;
				if ( $top < 0 ) {
					$open_prelude = false;
				}
			} elseif ( $boundary && 'symbol' === $type && ':' === $value && '' === $property ) {
				$property = (string) $candidate;
			} elseif ( $boundary && '' === $property ) {
				$candidate = 'name' === $type && null === $candidate ? $value : '';
			}
		}

		if ( ! $this->stopped && ( ! empty( $stack ) || $in_import ) ) {
			$this->malformed( empty( $stack ) ? null : $stack[0]['token'], true );
		} elseif ( ! $this->stopped && $open_prelude ) {
			// For example a trailing // comment: once embedded, browsers join it to the first selector that follows and drop that rule.
			$this->finding( 'unfinished-rule', __( 'The CSS ends in the middle of a rule or statement, such as text after the last closing brace. Once migrated, browsers would join it to the CSS that follows it in Additional CSS and ignore that rule. Remove it or finish it before migrating.', 'arlo-training-and-event-management-system' ), true, null, 'blocking' );
		}
	}

	/**
	 * Advice for an @import that a block theme's merged global stylesheet would ignore.
	 *
	 * Called as the @import keyword has just been read. Only this import's own URL is classified.
	 *
	 * @return string Translated advice.
	 */
	private function block_theme_import_advice(): string {
		$url = $this->import_url();

		if ( null !== $url && 1 === preg_match( '#fonts\.(?:googleapis|bunny|gstatic|adobe)\.|typekit\.net|[?&]family=#i', $url ) ) {
			return __( 'This theme\'s Additional CSS is merged into its global stylesheet, where browsers ignore @import, so these fonts would stop loading. Add the fonts in WordPress instead (Appearance > Editor > Styles > Typography > Fonts), then remove this @import and keep the rules that use the fonts.', 'arlo-training-and-event-management-system' );
		}

		return __( 'This theme\'s Additional CSS is merged into its global stylesheet, where browsers ignore @import. Load this stylesheet or font through the theme instead.', 'arlo-training-and-event-management-system' );
	}

	/**
	 * Reads the URL of the @import that has just been read, without moving the cursor.
	 *
	 * Understands comments, quoted strings and url(), so a semicolon inside a quoted URL does not end it and the next
	 * import is never read. Anything it cannot read plainly, including escapes, is treated as unknown.
	 *
	 * @return string|null The URL, or null when it is missing, escaped, malformed or longer than the look-ahead bound.
	 */
	private function import_url(): ?string {
		$end = min( strlen( $this->css ), $this->offset + 2048 );
		$i   = $this->offset;

		while ( $i < $end ) {
			if ( $this->space( $this->css[ $i ] ) ) {
				++$i;
			} elseif ( '/*' === substr( $this->css, $i, 2 ) ) {
				$close = strpos( $this->css, '*/', $i + 2 );
				if ( false === $close || $close >= $end ) {
					return null;
				}
				$i = $close + 2;
			} else {
				break;
			}
		}

		$in_url = 0 === strncasecmp( substr( $this->css, $i, 4 ), 'url(', 4 );
		if ( $in_url ) {
			$i += 4;
			while ( $i < $end && $this->space( $this->css[ $i ] ) ) {
				++$i;
			}
		}

		$quote = $i < $end ? $this->css[ $i ] : '';
		$stop  = '"' === $quote || "'" === $quote ? $quote : ( $in_url ? ')' : '' );
		if ( '' === $stop ) {
			return null;
		}

		$start = '' !== $quote && $stop === $quote ? $i + 1 : $i;
		for ( $j = $start; $j < $end; ++$j ) {
			$char = $this->css[ $j ];
			if ( $char === $stop ) {
				return substr( $this->css, $start, $j - $start );
			}
			// Escapes, line breaks and, in an unquoted url(), quotes or spaces mean the URL is not a plain one.
			if ( '\\' === $char || "\r" === $char || "\n" === $char || ( ')' === $stop && ( false !== strpos( "\"'(", $char ) || $this->space( $char ) ) ) ) {
				return null;
			}
		}

		return null;
	}

	/**
	 * Read a token, excluding whitespace and comments.
	 *
	 * @return array|null Token, or null at the end of the source.
	 */
	private function token(): ?array {
		$length = strlen( $this->css );
		while ( $this->offset < $length && ! $this->stopped ) {
			$char = $this->css[ $this->offset ];
			if ( $this->space( $char ) ) {
				$this->advance();
				continue;
			}
			$token = [
				'type'    => 'symbol',
				'value'   => $char,
				'line'    => $this->line,
				'column'  => $this->column,
				'escaped' => false,
			];
			if ( '/*' === substr( $this->css, $this->offset, 2 ) ) {
				$end = strpos( $this->css, '*/', $this->offset + 2 );
				if ( false === $end ) {
					$this->malformed( $token, true );
					$this->advance( $length - $this->offset );
					return null;
				}
				$this->advance( $end + 2 - $this->offset );
				continue;
			}
			if ( '"' === $char || "'" === $char ) {
				return $this->string_token();
			}
			if ( '@' === $char ) {
				$this->advance();
				$token['type'] = 'at';
			} elseif ( $this->name_character( $char ) || '\\' === $char ) {
				$token['type'] = 'name';
			} else {
				$this->advance();
				return $token;
			}

			$start = $this->offset;
			while ( $this->offset < $length ) {
				$char = $this->css[ $this->offset ];
				if ( '\\' === $char ) {
					$token['escaped'] = true;
					$this->advance( min( 2, $length - $this->offset ) );
				} elseif ( $this->name_character( $char ) ) {
					$this->advance();
				} else {
					break;
				}
			}
			$token['value'] = substr( $this->css, $start, $this->offset - $start );
			if ( 'name' === $token['type'] && '(' === substr( $this->css, $this->offset, 1 ) ) {
				$token['type'] = 'function';
				$this->advance();
			}
			return $token;
		}
		return null;
	}

	/**
	 * Read a quoted string without interpreting its contents as CSS tokens.
	 *
	 * @return array String token.
	 */
	private function string_token(): array {
		$quote = $this->css[ $this->offset ];
		$token = [
			'type'    => 'string',
			'value'   => '',
			'line'    => $this->line,
			'column'  => $this->column,
			'escaped' => false,
		];
		$this->advance();
		$start  = $this->offset;
		$length = strlen( $this->css );
		while ( $this->offset < $length ) {
			$char = $this->css[ $this->offset ];
			if ( $quote === $char ) {
				$token['value'] = substr( $this->css, $start, $this->offset - $start );
				$this->advance();
				return $token;
			}
			if ( '\\' === $char ) {
				$token['escaped'] = true;
				$this->advance( min( 2, $length - $this->offset ) );
				if ( "\r" === substr( $this->css, $this->offset - 1, 1 ) && "\n" === substr( $this->css, $this->offset, 1 ) ) {
					$this->advance();
				}
			} else {
				if ( "\n" === $char || "\r" === $char || "\f" === $char ) {
					$this->malformed( $token );
				}
				$this->advance();
			}
		}
		$this->malformed( $token, true );
		return $token;
	}

	/**
	 * Consume url() as one resource, leaving data payloads opaque.
	 *
	 * @param array $token URL function token.
	 * @return bool False when a quoted URL is not followed by its closing bracket, so the function is still open.
	 */
	private function url( array $token ): bool {
		$length = strlen( $this->css );
		while ( $this->offset < $length && $this->space( $this->css[ $this->offset ] ) ) {
			$this->advance();
		}
		$char = substr( $this->css, $this->offset, 1 );
		if ( '"' === $char || "'" === $char ) {
			$string = $this->string_token();
			$close  = $this->token();
			$closed = null !== $close && 'symbol' === $close['type'] && ')' === $close['value'];
			if ( ! $closed ) {
				$this->malformed( $token );
			}
			$this->reference( $string['value'], $token, $string['escaped'] );
			return $closed;
		}

		$start   = $this->offset;
		$depth   = 1;
		$escaped = false;
		$invalid = false;
		$data    = 0 === strncasecmp( substr( $this->css, $start, 5 ), 'data:', 5 );
		while ( $this->offset < $length ) {
			$char = $this->css[ $this->offset ];
			if ( '\\' === $char ) {
				$escaped = true;
				$this->advance( min( 2, $length - $this->offset ) );
				continue;
			}
			if ( '(' === $char ) {
				++$depth;
				$invalid = true;
			} elseif ( ')' === $char ) {
				--$depth;
				if ( 0 === $depth ) {
					$reference = trim( substr( $this->css, $start, $this->offset - $start ) );
					$this->advance();
					if ( ! $data && ( $invalid || strpbrk( $reference, " \t\r\n\f\"'" ) !== false ) ) {
						$this->finding( 'unsupported-url-syntax', __( 'Check the quotes and brackets in this URL. Quote URLs containing spaces, then run the check again. Ask your developer to review any other syntax the check cannot read.', 'arlo-training-and-event-management-system' ), true, $token );
					} elseif ( $data && ( $invalid || strpbrk( $reference, " \t\r\n\f\"'" ) !== false ) ) {
						$this->finding( 'bad-url', __( 'This unquoted data: URL contains quotes, spaces or brackets, so browsers already ignore the declaration. Wrap the URL in quotes (and encode any quotes inside it) to make it work.', 'arlo-training-and-event-management-system' ), false, $token );
					} else {
						$this->reference( $reference, $token, $escaped );
					}
					return true;
				}
			}
			$this->advance();
		}
		// Already reported as unclosed (blocking) here, so the caller does not track it again.
		$this->malformed( $token, true );
		return true;
	}

	/**
	 * Classify a resource without resolving or fetching it.
	 *
	 * @param string $reference Raw resource reference.
	 * @param array  $token     Source location.
	 * @param bool   $escaped   Whether the reference contains CSS escapes.
	 * @return void
	 */
	private function reference( string $reference, array $token, bool $escaped ): void {
		if ( 0 === strncasecmp( $reference, 'data:', 5 ) ) {
			return;
		}
		if ( $escaped ) {
			$this->finding( 'escaped-syntax', __( 'This check cannot decode this escaped URL. Confirm which resource it refers to and use its full URL if necessary.', 'arlo-training-and-event-management-system' ), true, $token );
		} elseif ( '' === $reference || '#' === $reference[0] || '?' === $reference[0] ) {
			$this->finding( 'base-sensitive-url', __( 'Check that this resource or SVG reference still works on each affected page after migrating. Keep it if it works; otherwise ask your developer to update the reference for use in the page instead of the old stylesheet.', 'arlo-training-and-event-management-system' ), false, $token );
		} elseif ( '/' === $reference[0] ) {
			$this->finding( 'base-sensitive-url', __( 'Check this resource on every domain used by the site, including mapped domains. Use the full intended URL if a different host or protocol would load the wrong resource; otherwise leave it unchanged.', 'arlo-training-and-event-management-system' ), false, $token );
		} elseif ( 1 !== preg_match( '/^[a-z][a-z0-9+.-]*:/i', $reference ) ) {
			// Legacy mode only: a relative path resolved from the old plugin-folder stylesheet; inline CSS already resolves from the page.
			if ( ! $this->from_file ) {
				return;
			}
			$this->finding( 'relative-url', __( 'After migration, this relative path is resolved from the page instead of the plugin-folder stylesheet. Replace it with the full URL of the intended image, font or stylesheet, or confirm it still loads correctly on every affected page. If it already works, no change is needed.', 'arlo-training-and-event-management-system' ), false, $token );
		}
	}

	/**
	 * Whether a property can consume a URL through a dynamic value.
	 *
	 * @param string $property Lowercase property name.
	 * @return bool
	 */
	private function resource_property( string $property ): bool {
		return in_array(
			$property,
			[
				'background',
				'background-image',
				'src',
				'mask',
				'mask-image',
				'-webkit-mask',
				'-webkit-mask-image',
				'filter',
				'-webkit-filter',
				'backdrop-filter',
				'-webkit-backdrop-filter',
				'cursor',
				'content',
				'list-style',
				'list-style-image',
				'border-image',
				'border-image-source',
				'-webkit-border-image',
				'shape-outside',
				'clip-path',
				'offset-path',
				'fill',
				'stroke',
				'marker',
				'marker-start',
				'marker-mid',
				'marker-end',
			],
			true
		);
	}

	/**
	 * Record uncertainty about malformed CSS.
	 *
	 * @param array|null $token    Source token, or current location.
	 * @param bool       $unclosed Whether a construct is left open to the end of the CSS, which would swallow whatever follows it once embedded.
	 * @return void
	 */
	private function malformed( ?array $token = null, bool $unclosed = false ): void {
		if ( $unclosed ) {
			$this->finding( 'unclosed-construct', __( 'A comment, quote, bracket or brace is never closed. Browsers would treat everything after it, including other CSS on the page, as part of it. Close it before migrating.', 'arlo-training-and-event-management-system' ), true, $token, 'blocking' );
			return;
		}
		$this->finding( 'malformed-css', __( 'Close any unfinished quotes, brackets or comments near this position, then run the check again. If the syntax is intentional, ask your developer to review it; this check cannot confirm readiness for every CSS feature.', 'arlo-training-and-event-management-system' ), true, $token );
	}

	/**
	 * Add a finding while preserving uncertainty and the findings bound.
	 *
	 * Blocking findings are never dropped by the bound, because they decide whether migration can go ahead.
	 *
	 * @param string     $code    Machine-readable finding code.
	 * @param string     $message Translated explanation.
	 * @param bool       $unknown Whether analysis is inconclusive.
	 * @param array|null $token   Source token, or current location.
	 * @param string     $level   'warning', 'info' (does not change the status) or 'blocking' (prevents migration).
	 * @return void
	 */
	private function finding( string $code, string $message, bool $unknown = false, ?array $token = null, string $level = 'warning' ): void {
		if ( ( $this->stopped || $this->capped ) && 'blocking' !== $level ) {
			return;
		}
		if ( 'blocking' !== $level && count( $this->result['findings'] ) >= self::MAX_FINDINGS ) {
			array_pop( $this->result['findings'] );
			$code         = 'analysis-limit';
			$message      = __( 'The check stopped after 50 findings. Review these items, then run the check again to look for further issues.', 'arlo-training-and-event-management-system' );
			$unknown      = true;
			$level        = 'warning';
			$this->capped = true;
		}
		$this->result['findings'][] = [
			'code'    => $code,
			'line'    => $token['line'] ?? $this->line,
			'column'  => $token['column'] ?? $this->column,
			'level'   => $level,
			'message' => $message,
		];
		if ( 'blocking' === $level ) {
			$this->result['blocked'] = true;
		}
		if ( 'info' === $level ) {
			return;
		}
		if ( $unknown ) {
			$this->result['status'] = 'unknown';
		} elseif ( 'clear' === $this->result['status'] ) {
			$this->result['status'] = 'warning';
		}
	}

	/**
	 * Advance the cursor, counting CRLF as a single newline.
	 *
	 * @param int $count Bytes to consume.
	 * @return void
	 */
	private function advance( int $count = 1 ): void {
		for ( $i = 0; $i < $count; ++$i ) {
			$char = $this->css[ $this->offset ];
			if ( "\r" === $char || ( "\n" === $char && ( 0 === $this->offset || "\r" !== $this->css[ $this->offset - 1 ] ) ) ) {
				++$this->line;
				$this->column = 1;
			} elseif ( "\n" !== $char ) {
				++$this->column;
			}
			++$this->offset;
		}
	}

	/**
	 * Whether a byte is CSS whitespace.
	 *
	 * @param string $char Source byte.
	 * @return bool
	 */
	private function space( string $char ): bool {
		return false !== strpos( " \t\r\n\f", $char );
	}

	/**
	 * Whether a byte can occur in an identifier.
	 *
	 * @param string $char Source byte.
	 * @return bool
	 */
	private function name_character( string $char ): bool {
		$byte = ord( $char );
		return ( $byte >= 48 && $byte <= 57 ) || ( $byte >= 65 && $byte <= 90 ) || ( $byte >= 97 && $byte <= 122 ) || $byte >= 128 || '-' === $char || '_' === $char;
	}
}
