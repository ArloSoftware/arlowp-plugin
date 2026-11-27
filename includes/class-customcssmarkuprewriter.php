<?php
/**
 * Rewrites angle brackets in Custom CSS so WordPress's Additional CSS editor accepts it.
 *
 * @package ArloTraining
 */

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core's block editor refuses Additional CSS that matches /<\/?\w/ ("Do not use <> markup"), although the server stores and prints it.
 * Valid CSS can contain such a bracket, most often in an inline SVG url(), a comment, a content string or a compact range query.
 *
 * Each bracket is rewritten in the way that keeps the CSS meaning the same:
 * - url(): "<" and ">" become %3C and %3E, which browsers read back as the same URL.
 * - Other strings and escaped selectors: "<" becomes the CSS escape \00003C, the same character.
 * - Range comparisons in media and container queries: a space after "<", which the grammar allows.
 * - Comments, which do not affect rendering: "[" and "]", except inside a commented-out url(), which is encoded so it still works if uncommented.
 *
 * Anything else, such as unquoted data types in newer CSS, cannot be rewritten without changing meaning and is reported as residue.
 *
 * This is a lexical reader (comments, strings, url() and everything else), not a CSS parser. It never needs balanced braces, so malformed
 * rules are read the way browsers recover from them. CSS it cannot read to the end (an unclosed comment, string or url()) is returned unchanged
 * and marked unreliable; the compatibility check already blocks those. The scanner never touches the filesystem or WordPress.
 */
class CustomCssMarkupRewriter {

	/**
	 * The editor's rule (validateCSS in the block editor's Additional CSS panel), as a pattern for the whole text.
	 */
	public const EDITOR_PATTERN = '#<\/?\w#';

	/**
	 * Characters the main scan stops at.
	 */
	private const SPECIAL = "/\"'(){}[];@\\<:";

	/**
	 * At-rules whose parenthesised preludes can hold a range comparison.
	 */
	private const RANGE_AT_RULES = [ 'media', 'container', 'custom-media', 'import' ];

	/**
	 * Functions whose arguments are conditions that can hold a range comparison, wherever they appear.
	 */
	private const RANGE_FUNCTIONS = [ 'media', 'style', 'scroll-state' ];

	/**
	 * Bytes of source shown around a change or an unrewritable bracket.
	 */
	private const EXCERPT_BYTES = 60;

	/**
	 * Most unrewritable brackets reported; the total is still counted.
	 */
	private const MAX_RESIDUE = 50;

	/**
	 * Most distinct lines kept for unrewritable brackets that are not described in full.
	 */
	private const MAX_RESIDUE_LINES = 200;

	/**
	 * Most edits held. Each takes memory, so a stylesheet with more than any real one needs is refused, with its first bracket located, before they can exhaust it.
	 */
	private const MAX_EDITS = 50000;

	/**
	 * Deepest nesting of parentheses read, the same bound as the compatibility check. Every character is classified against the open groups, so unbounded nesting would make the scan quadratic.
	 */
	private const MAX_DEPTH = 256;

	/**
	 * Largest text read, the same bound as the compatibility check, which already blocks anything larger.
	 */
	private const MAX_BYTES = 2097152;

	/**
	 * Source CSS.
	 *
	 * @var string
	 */
	private $css;

	/**
	 * Source length in bytes.
	 *
	 * @var int
	 */
	private $length;

	/**
	 * Edits in source order: start, end and replacement text.
	 *
	 * @var array<int, array{0: int, 1: int, 2: string}>
	 */
	private $edits = [];

	/**
	 * Edits of the region being read, in any order until the region ends.
	 *
	 * @var array<int, array{0: int, 1: int, 2: string}>
	 */
	private $pending = [];

	/**
	 * Finished regions: a kind and its edits.
	 *
	 * @var array<int, array{kind: string, edits: array}>
	 */
	private $regions = [];

	/**
	 * Kind of the region being read.
	 *
	 * @var string
	 */
	private $kind = '';

	/**
	 * Source offsets of brackets that cannot be rewritten.
	 *
	 * @var int[]
	 */
	private $residue = [];

	/**
	 * How many unrewritable brackets there are in all, however many offsets are kept.
	 *
	 * @var int
	 */
	private $residue_total = 0;

	/**
	 * Lines that have an unrewritable bracket, as keys, so none is lost when only some are described in full.
	 *
	 * @var array<int, true>
	 */
	private $residue_lines = [];

	/**
	 * Converts the offsets of unrewritable brackets to lines, as the scan finds them in order.
	 *
	 * @var \Closure
	 */
	private $residue_locate;

	/**
	 * How many edits have been asked for, including any refused once the limit was reached.
	 *
	 * @var int
	 */
	private $edit_count = 0;

	/**
	 * Where each comment that has been read starts, keyed by the offset after its end.
	 *
	 * @var array<int, int>
	 */
	private $comment_starts = [];
	/**
	 * Whether the whole text could be read.
	 *
	 * @var bool
	 */
	private $reliable = true;

	/**
	 * Whether the CSS holds a bracket the editor would reject.
	 *
	 * @param string $css CSS text.
	 * @return bool
	 */
	public static function contains_markup( string $css ): bool {
		return 1 === preg_match( self::EDITOR_PATTERN, $css );
	}

	/**
	 * Rewrites every bracket the editor would reject that can be rewritten without changing meaning.
	 *
	 * Locations are one-based lines (CR, LF and CRLF each end one) and byte columns, as in CustomCssCompatibility.
	 *
	 * @param string $css CSS text.
	 * @return array{css: string, changes: array<int, array{kind: string, line: int, column: int, before: string, after: string}>, residue: array<int, array{line: int, column: int, snippet: string}>, residue_count: int, residue_lines: int[], unreadable: array{line: int, column: int, snippet: string}|null, reliable: bool}
	 *         The new CSS; one change per url(), string, comment, range comparison or escape; the brackets left alone, at most 50; how many there were and the lines they are on;
	 *         and false when the text could not be read to the end or has too many brackets to rewrite, in which case it is returned unchanged and `unreadable` says where its first bracket is.
	 */
	public static function rewrite( string $css ): array {
		$result = [
			'css'           => $css,
			'changes'       => [],
			'residue'       => [],
			'residue_count' => 0,
			'residue_lines' => [],
			'unreadable'    => null,
			'reliable'      => true,
		];
		if ( ! self::contains_markup( $css ) ) {
			return $result;
		}
		if ( strlen( $css ) > self::MAX_BYTES ) {
			// The compatibility check blocks CSS this large, so there is nothing to rewrite for.
			return self::unreadable( $result, $css );
		}

		$rewriter = new self( $css );
		$rewriter->scan();
		if ( ! $rewriter->reliable ) {
			return self::unreadable( $result, $css );
		}

		$output  = $rewriter->apply();
		$offsets = $rewriter->residue;
		$total   = $rewriter->residue_total;
		$lines   = array_keys( $rewriter->residue_lines );
		if ( 0 === $total && self::contains_markup( $output ) ) {
			// Cannot happen while every bracket is handled above; if it ever does, the migration must still not write it.
			preg_match_all( self::EDITOR_PATTERN, $output, $matches, PREG_OFFSET_CAPTURE );
			$locate_leftover = self::locator( $output );
			$offsets         = [];
			$lines           = [];
			foreach ( $matches[0] as $match ) {
				$offsets[] = $match[1];
				$line      = $locate_leftover( $match[1] )[0];
				if ( ! in_array( $line, $lines, true ) && count( $lines ) < self::MAX_RESIDUE_LINES ) {
					$lines[] = $line;
				}
			}
			$total            = count( $offsets );
			$rewriter->css    = $output;
			$rewriter->length = strlen( $output );
		}

		$locate = self::locator( $rewriter->css );
		foreach ( array_slice( $offsets, 0, self::MAX_RESIDUE ) as $offset ) {
			$position            = $locate( $offset );
			$result['residue'][] = [
				'line'    => $position[0],
				'column'  => $position[1],
				'snippet' => self::excerpt( $rewriter->css, $offset - 12, self::EXCERPT_BYTES ),
			];
		}

		$result['css']           = $output;
		$result['changes']       = $rewriter->describe_changes();
		$result['residue_count'] = $total;
		$result['residue_lines'] = $lines;
		return $result;
	}

	/**
	 * The result for CSS that cannot be rewritten at all: unchanged, with where its first bracket is so the administrator can find it.
	 *
	 * @param array  $result The result so far.
	 * @param string $css    CSS text, which holds at least one bracket.
	 * @return array
	 */
	private static function unreadable( array $result, string $css ): array {
		$result['reliable'] = false;
		if ( 1 === preg_match( self::EDITOR_PATTERN, $css, $match, PREG_OFFSET_CAPTURE ) ) {
			$locate               = self::locator( $css );
			$position             = $locate( $match[0][1] );
			$result['unreadable'] = [
				'line'    => $position[0],
				'column'  => $position[1],
				'snippet' => self::excerpt( $css, $match[0][1] - 12, self::EXCERPT_BYTES ),
			];
		}
		return $result;
	}

	/**
	 * Starts a reader over one CSS text.
	 *
	 * @param string $css CSS text.
	 */
	private function __construct( string $css ) {
		$this->css            = $css;
		$this->length         = strlen( $css );
		$this->residue_locate = self::locator( $css );
	}

	/**
	 * Reads the text once, recording edits and unrewritable brackets.
	 *
	 * @return void
	 */
	private function scan(): void {
		$css       = $this->css;
		$length    = $this->length;
		$frames    = [];
		$colons    = [];
		$at        = '';
		$in_custom = false;
		// Open {} and [] blocks inside a custom property's value or a style() condition, where a semicolon or brace does not end the declaration or the prelude.
		$blocks = 0;
		// Where the current declaration or rule prelude starts, and whether its first colon has been looked at.
		$decl_start = 0;
		$decl_seen  = false;
		$i          = 0;

		while ( $i < $length ) {
			$i += strcspn( $css, self::SPECIAL, $i );
			if ( $i >= $length ) {
				break;
			}
			$char = $css[ $i ];
			// The text of a custom property's value, or of a style() condition, is the data itself: it is compared as written and can be read back.
			$in_style  = in_array( 'style', $frames, true );
			$text_data = $in_custom || $in_style;

			if ( '/' === $char ) {
				$i = '*' === substr( $css, $i + 1, 1 ) ? $this->comment( $i ) : $i + 1;
			} elseif ( '"' === $char || "'" === $char ) {
				$i = $this->plain_string( $i, $text_data );
			} elseif ( '(' === $char ) {
				if ( count( $frames ) >= self::MAX_DEPTH ) {
					$this->reliable = false;
					return;
				}
				$name = $this->function_name( $i );
				if ( 'url' === $name ) {
					// The name a @namespace rule is given is a literal, not an address that is fetched. An address in a custom property is still encoded, which
					// keeps icons held in variables working, but one compared by a style() condition is text.
					list( $i, $open ) = $this->url( $i, 'namespace' === $at, $in_style );
					if ( $open ) {
						$frames[] = 'url';
						$colons[] = false;
					}
				} else {
					$frames[] = $name;
					$colons[] = false;
					++$i;
				}
			} elseif ( ')' === $char ) {
				array_pop( $frames );
				array_pop( $colons );
				++$i;
			} elseif ( ':' === $char ) {
				// After a colon, inside a group, the text is a value, not a comparison; after "--name:" outside one, it is a custom property's value.
				if ( [] !== $colons ) {
					$colons[ count( $colons ) - 1 ] = true;
				} elseif ( ! $decl_seen ) {
					// Only the first colon of a declaration can follow its name.
					$decl_seen = true;
					$in_custom = $this->is_custom_property( $decl_start, $i );
				}
				++$i;
			} elseif ( '{' === $char || '[' === $char ) {
				if ( $text_data ) {
					++$blocks;
				} elseif ( '{' === $char ) {
					$blocks     = 0;
					$frames     = [];
					$colons     = [];
					$at         = '';
					$decl_start = $i + 1;
					$decl_seen  = false;
				}
				++$i;
			} elseif ( '}' === $char || ']' === $char ) {
				if ( $blocks > 0 ) {
					--$blocks;
				} elseif ( '}' === $char ) {
					$frames     = [];
					$colons     = [];
					$at         = '';
					$in_custom  = false;
					$decl_start = $i + 1;
					$decl_seen  = false;
				}
				++$i;
			} elseif ( ';' === $char ) {
				if ( $blocks > 0 ) {
					// A semicolon inside a block of a custom property's value is part of the value.
					++$i;
				} elseif ( [] === $frames ) {
					// A semicolon inside parentheses, as in if(), ends a branch: what follows starts with a condition again, not a value.
					$at         = '';
					$in_custom  = false;
					$decl_start = $i + 1;
					$decl_seen  = false;
					++$i;
				} else {
					$colons[ count( $colons ) - 1 ] = false;
					++$i;
				}
			} elseif ( '@' === $char ) {
				list( $at, $consumed ) = $this->identifier( $i + 1 );
				$i                    += 1 + $consumed;
			} elseif ( '\\' === $char ) {
				if ( '<' === substr( $css, $i + 1, 1 ) && $this->markup_at( $i + 1 ) ) {
					if ( $text_data ) {
						$this->note_residue( $i );
					} else {
						$this->begin( 'escape' );
						$this->edit( $i, $i + 2, '\\00003C' );
						$this->finish();
					}
				}
				$i += 2;
			} else {
				// A "<".
				if ( $this->markup_at( $i ) ) {
					if ( '/' !== $css[ $i + 1 ] && ! $in_custom && $this->in_range( $at, $frames, $colons ) && $this->after_operand( $i ) ) {
						$this->begin( 'range' );
						$this->edit( $i, $i + 1, '< ' );
						$this->finish();
					} else {
						$this->note_residue( $i );
					}
				}
				++$i;
			}

			if ( ! $this->reliable ) {
				return;
			}
		}
	}

	/**
	 * Reads a comment, rewriting its brackets, and returns the offset after it.
	 *
	 * @param int $start Offset of the opening slash.
	 * @return int
	 */
	private function comment( int $start ): int {
		$end = strpos( $this->css, '*/', $start + 2 );
		if ( false === $end ) {
			$this->reliable = false;
			return $this->length;
		}
		$from = $start + 2;
		if ( 1 === preg_match( self::EDITOR_PATTERN, substr( $this->css, $from, $end - $from ) ) ) {
			$this->rewrite_comment( $from, $end );
		}
		// Remembered so a comment between an operand and a "<" can be stepped over.
		$this->comment_starts[ $end + 2 ] = $start;
		return $end + 2;
	}

	/**
	 * Rewrites the brackets in a comment: encoded inside a commented-out url(), square brackets elsewhere.
	 *
	 * @param int $from Offset of the first character inside the comment.
	 * @param int $to   Offset after the last character inside the comment.
	 * @return void
	 */
	private function rewrite_comment( int $from, int $to ): void {
		$inner   = substr( $this->css, $from, $to - $from );
		$size    = strlen( $inner );
		$covered = [];
		$this->begin( 'comment' );

		// Matches are read one at a time, so a very long comment never holds every match in memory.
		$cursor = 0;
		while ( 1 === preg_match( '~(?<![A-Za-z0-9_-])url\(~i', $inner, $match, PREG_OFFSET_CAPTURE, $cursor ) ) {
			$body = $match[0][1] + 4;
			while ( $body < $size && $this->is_space( $inner[ $body ] ) ) {
				++$body;
			}
			$quote = $inner[ $body ] ?? '';
			if ( '"' === $quote || "'" === $quote ) {
				++$body;
			} else {
				$quote = '';
			}
			$stop = $body;
			while ( $stop < $size && ( '' === $quote ? ')' : $quote ) !== $inner[ $stop ] ) {
				$stop += '\\' === $inner[ $stop ] ? 2 : 1;
			}
			$stop   = min( $stop, $size );
			$cursor = $stop + 1;
			if ( 1 === preg_match( self::EDITOR_PATTERN, substr( $inner, $body, $stop - $body ) ) ) {
				$this->encode( $from + $body, $from + $stop );
				$covered[] = [ $from + $body, $from + $stop ];
			}
		}

		// The covered ranges are in order, so one moving index answers "is this inside a commented-out address" for every bracket.
		$count  = count( $covered );
		$next   = 0;
		$cursor = 0;
		while ( 1 === preg_match( '~<(?=/?\w)~', $inner, $bracket, PREG_OFFSET_CAPTURE, $cursor ) ) {
			$cursor = $bracket[0][1] + 1;
			$open   = $from + $bracket[0][1];
			while ( $next < $count && $covered[ $next ][1] <= $open ) {
				++$next;
			}
			if ( $next < $count && $covered[ $next ][0] <= $open ) {
				continue;
			}
			$this->edit( $open, $open + 1, '[' );
			// The ">" that closes the same tag, if the text reaches one before the next "<" or the next encoded address.
			$limit = $next < $count ? $covered[ $next ][0] : PHP_INT_MAX;
			for ( $k = $bracket[0][1] + 1; $k < $size && $from + $k < $limit && '<' !== $inner[ $k ]; $k++ ) {
				if ( '>' === $inner[ $k ] ) {
					$this->edit( $from + $k, $from + $k + 1, ']' );
					break;
				}
			}
		}
		$this->finish();
	}

	/**
	 * Reads a string that is not a url() argument, rewriting its brackets, and returns the offset after it.
	 *
	 * @param int $start Offset of the opening quote.
	 * @return int
	 */
	private function plain_string( int $start, bool $text_data = false ): int {
		$end = $this->string_end( $start );
		if ( null === $end ) {
			$this->reliable = false;
			return $this->length;
		}
		if ( $text_data ) {
			// Compared as written, so an escape would change what is compared.
			$this->block_brackets( $start + 1, $end );
		} else {
			$this->begin( 'string' );
			$this->escape_brackets( $start + 1, $end );
			$this->finish();
		}
		return $end < $this->length && $this->css[ $end ] === $this->css[ $start ] ? $end + 1 : $end;
	}

	/**
	 * Reports each bracket the editor would reject between two offsets as one that cannot be rewritten.
	 *
	 * @param int $from Offset of the first character of the text.
	 * @param int $to   Offset after the last character of the text.
	 * @return void
	 */
	private function block_brackets( int $from, int $to ): void {
		$i = $from;
		while ( $i < $to ) {
			$i += strcspn( $this->css, "\\<", $i, $to - $i );
			if ( $i >= $to ) {
				return;
			}
			if ( '\\' === $this->css[ $i ] ) {
				if ( '<' === substr( $this->css, $i + 1, 1 ) && $i + 1 < $to && $this->markup_at( $i + 1 ) ) {
					$this->note_residue( $i );
				}
				$i += 2;
				continue;
			}
			if ( $this->markup_at( $i ) ) {
				$this->note_residue( $i );
			}
			++$i;
		}
	}

	/**
	 * Rewrites each bracket the editor would reject between two offsets as the CSS escape for the same character.
	 *
	 * Used for quoted strings, and for the name given to a @namespace rule, which is a literal rather than an address.
	 *
	 * @param int $from Offset of the first character of the text.
	 * @param int $to   Offset after the last character of the text.
	 * @return void
	 */
	private function escape_brackets( int $from, int $to ): void {
		$i = $from;
		while ( $i < $to ) {
			$i += strcspn( $this->css, "\\<", $i, $to - $i );
			if ( $i >= $to ) {
				break;
			}
			if ( '\\' === $this->css[ $i ] ) {
				if ( '<' === substr( $this->css, $i + 1, 1 ) && $i + 1 < $to && $this->markup_at( $i + 1 ) ) {
					$this->edit( $i, $i + 2, '\\00003C' );
				}
				$i += 2;
				continue;
			}
			if ( $this->markup_at( $i ) ) {
				$this->edit( $i, $i + 1, '\\00003C' );
			}
			++$i;
		}
	}

	/**
	 * Rewrites the brackets in the text of a url(), if it has any.
	 *
	 * @param int  $from    Offset of the first character of the address.
	 * @param int  $to      Offset after the last character of the address.
	 * @param bool $literal Whether the text is a literal, like a @namespace name, so only a CSS escape keeps its value; otherwise it is an address, which is percent-encoded.
	 * @return void
	 */
	private function rewrite_address( int $from, int $to, bool $literal, bool $text_data = false ): void {
		if ( 1 !== preg_match( self::EDITOR_PATTERN, substr( $this->css, $from, $to - $from ) ) ) {
			return;
		}
		if ( $text_data ) {
			$this->block_brackets( $from, $to );
			return;
		}
		$this->begin( $literal ? 'string' : 'url' );
		if ( $literal ) {
			$this->escape_brackets( $from, $to );
		} else {
			$this->encode( $from, $to );
		}
		$this->finish();
	}

	/**
	 * Keeps the offset of a bracket that cannot be rewritten, and its line, within limits that do not grow with the number of brackets.
	 *
	 * @param int $offset Offset of the "<".
	 * @return void
	 */
	private function note_residue( int $offset ): void {
		++$this->residue_total;
		if ( count( $this->residue ) < self::MAX_RESIDUE ) {
			$this->residue[] = $offset;
		}
		if ( count( $this->residue_lines ) < self::MAX_RESIDUE_LINES ) {
			$locate = $this->residue_locate;
			$line   = $locate( $offset )[0];

			$this->residue_lines[ $line ] = true;
		}
	}

	/**
	 * Finds where a quoted string ends: at its closing quote, or, as browsers do, at an unescaped line break.
	 *
	 * @param int $start Offset of the opening quote.
	 * @return int|null Offset of the closing quote or of the line break; null when the text ends first.
	 */
	private function string_end( int $start ): ?int {
		$quote  = $this->css[ $start ];
		$length = $this->length;
		$i      = $start + 1;
		while ( $i < $length ) {
			$i += strcspn( $this->css, "\\\n\r\f" . $quote, $i );
			if ( $i >= $length ) {
				break;
			}
			$char = $this->css[ $i ];
			if ( $quote === $char || "\n" === $char || "\r" === $char || "\f" === $char ) {
				return $i;
			}
			// A backslash takes the next character, and a CRLF after it, as a line continuation.
			$i += "\r\n" === substr( $this->css, $i + 1, 2 ) ? 3 : 2;
		}
		return null;
	}

	/**
	 * Reads a url() whose opening bracket is at $open, encoding any brackets in its address.
	 *
	 * @param int  $open      Offset of the opening bracket.
	 * @param bool $literal   Whether the text is a literal, like a @namespace name, escaped instead of percent-encoded.
	 * @param bool $text_data Whether the address is compared as written, as in a style() condition, so brackets in it are reported instead of encoded.
	 * @return array{0: int, 1: bool} The offset to carry on from, and whether the function is still open there.
	 */
	private function url( int $open, bool $literal = false, bool $text_data = false ): array {
		$length = $this->length;
		$i      = $open + 1;
		while ( $i < $length && $this->is_space( $this->css[ $i ] ) ) {
			++$i;
		}
		if ( $i >= $length ) {
			$this->reliable = false;
			return [ $length, false ];
		}

		if ( '"' === $this->css[ $i ] || "'" === $this->css[ $i ] ) {
			$end = $this->string_end( $i );
			if ( null === $end ) {
				$this->reliable = false;
				return [ $length, false ];
			}
			$this->rewrite_address( $i + 1, $end, $literal, $text_data );
			if ( $this->css[ $end ] !== $this->css[ $i ] ) {
				return [ $end, true ];
			}
			$next = $end + 1;
			while ( $next < $length && $this->is_space( $this->css[ $next ] ) ) {
				++$next;
			}
			return ')' === ( $this->css[ $next ] ?? '' ) ? [ $next + 1, false ] : [ $next, true ];
		}

		// An unquoted address runs to the closing bracket, skipping escapes; browsers read a malformed one the same way.
		$stop = $i;
		while ( $stop < $length && ')' !== $this->css[ $stop ] ) {
			$stop += '\\' === $this->css[ $stop ] ? 2 : 1;
		}
		if ( $stop >= $length ) {
			$this->reliable = false;
			return [ $length, false ];
		}
		$this->rewrite_address( $i, $stop, $literal, $text_data );
		return [ $stop + 1, false ];
	}

	/**
	 * Encodes every "<" and ">" between two offsets as %3C and %3E, an escaped "<" included.
	 *
	 * @param int $from Offset of the first character of the address.
	 * @param int $to   Offset after the last character of the address.
	 * @return void
	 */
	private function encode( int $from, int $to ): void {
		$i = $from;
		while ( $i < $to ) {
			$i += strcspn( $this->css, "\\<>", $i, $to - $i );
			if ( $i >= $to ) {
				return;
			}
			$char = $this->css[ $i ];
			if ( '\\' === $char ) {
				if ( '<' === substr( $this->css, $i + 1, 1 ) && $i + 1 < $to ) {
					$this->edit( $i, $i + 2, '%3C' );
				}
				$i += 2;
				continue;
			}
			$this->edit( $i, $i + 1, '<' === $char ? '%3C' : '%3E' );
			++$i;
		}
	}

	/**
	 * Reads the name of the function whose opening bracket is at $open, with its escapes resolved.
	 *
	 * @param int $open Offset of the opening bracket.
	 * @return string The lower-case name, so "fn\31 url" is "fn1url", "\75 rl" is "url" and "\\url" is not.
	 */
	private function function_name( int $open ): string {
		$start = $open;
		for ( ;; ) {
			while ( $start > 0 && $this->is_name_byte( ord( $this->css[ $start - 1 ] ) ) ) {
				--$start;
			}
			if ( $start > 0 && '\\' === $this->css[ $start - 1 ] ) {
				// A backslash right before the name starts an escape that belongs to it, as in "\75 rl".
				while ( $start > 0 && '\\' === $this->css[ $start - 1 ] ) {
					--$start;
				}
			} elseif ( $start >= 2 && '\\' === $this->css[ $start - 2 ] && $this->escapes_next( $start - 2 ) ) {
				// A character escaped with a backslash, as in "\<url(", is part of the name, which is then not "url".
				$start -= 2;
			} elseif ( $start >= 2 && $this->is_space( $this->css[ $start - 1 ] ) && $this->hex_escape_before( $start - 1 ) >= 0 ) {
				// The space that ends a hex escape belongs to the escape, so what follows it is still the same name.
				$start = $this->hex_escape_before( $start - 1 );
			} else {
				break;
			}
		}
		$name = $this->identifier( $start )[0];
		// "#url(" is a hash token and "@url(" an at-keyword, not the url function, so the prefix is part of the name.
		if ( $start > 0 && ( '#' === $this->css[ $start - 1 ] || '@' === $this->css[ $start - 1 ] ) ) {
			$name = $this->css[ $start - 1 ] . $name;
		}
		return $name;
	}

	/**
	 * Whether the backslash at an offset begins an escape, as opposed to being escaped itself by the backslashes before it.
	 *
	 * @param int $offset Offset of a backslash.
	 * @return bool
	 */
	private function escapes_next( int $offset ): bool {
		$run = 0;
		for ( $k = $offset; $k >= 0 && '\\' === $this->css[ $k ]; $k-- ) {
			++$run;
		}
		return 1 === $run % 2;
	}

	/**
	 * Finds the backslash of a hex escape that is ended by the whitespace at an offset: one to six hex digits after a backslash, which also takes one whitespace.
	 *
	 * @param int $space Offset of the whitespace character.
	 * @return int Offset of the backslash, or -1 when the whitespace does not end an escape.
	 */
	private function hex_escape_before( int $space ): int {
		$k = $space - 1;
		// A line break written as CRLF is one whitespace.
		if ( "\n" === $this->css[ $space ] && $k >= 0 && "\r" === $this->css[ $k ] ) {
			--$k;
		}
		$digits = 0;
		while ( $k >= 0 && $digits < 7 && ctype_xdigit( $this->css[ $k ] ) ) {
			--$k;
			++$digits;
		}
		if ( $digits >= 1 && $digits <= 6 && $k >= 0 && '\\' === $this->css[ $k ] && $this->escapes_next( $k ) ) {
			return $k;
		}
		return -1;
	}

	/**
	 * Whether a byte can be part of a name: a letter, digit, hyphen or underscore, or part of a multi-byte character.
	 *
	 * @param int $byte Byte value.
	 * @return bool
	 */
	private function is_name_byte( int $byte ): bool {
		return ( $byte >= 48 && $byte <= 57 ) || ( $byte >= 65 && $byte <= 90 ) || ( $byte >= 97 && $byte <= 122 ) || 45 === $byte || 95 === $byte || $byte >= 128;
	}

	/**
	 * Reads a name, including any escapes in it, so @name\73 pace is read as @namespace and --t\61 g as --tag.
	 *
	 * @param int $from Offset of the first character of the name.
	 * @return array{0: string, 1: int} The lower-case name, with each escape resolved to its character, and the number of bytes it takes.
	 */
	private function identifier( int $from ): array {
		$length = $this->length;
		$i      = $from;
		$name   = '';
		while ( $i < $length ) {
			$char = $this->css[ $i ];
			$byte = ord( $char );
			if ( '\\' === $char ) {
				$next = $this->css[ $i + 1 ] ?? '';
				if ( '' === $next || "\n" === $next || "\r" === $next || "\f" === $next ) {
					break;
				}
				$digits = strspn( $this->css, '0123456789abcdefABCDEF', $i + 1, 6 );
				if ( $digits > 0 ) {
					$code = (int) hexdec( substr( $this->css, $i + 1, $digits ) );
					$i   += 1 + $digits;
					// One whitespace after the digits belongs to the escape.
					if ( $i < $length && $this->is_space( $this->css[ $i ] ) ) {
						$i += "\r\n" === substr( $this->css, $i, 2 ) ? 2 : 1;
					}
					$name .= $code > 0 && $code < 128 ? chr( $code ) : "\xEF\xBF\xBD";
				} else {
					$name .= $next;
					$i    += 2;
				}
			} elseif ( ( $byte >= 48 && $byte <= 57 ) || ( $byte >= 65 && $byte <= 90 ) || ( $byte >= 97 && $byte <= 122 ) || 45 === $byte || 95 === $byte || $byte >= 128 ) {
				$name .= $char;
				++$i;
			} else {
				break;
			}
		}
		return [ strtolower( $name ), $i - $from ];
	}

	/**
	 * Whether the "<" at an offset follows something that can be compared, which is what makes it a comparison operator.
	 *
	 * @param int $offset Offset of the "<".
	 * @return bool
	 */
	private function after_operand( int $offset ): bool {
		$k = $offset - 1;
		// Whitespace and comments are not tokens, so step over both to the one before.
		while ( $k >= 0 ) {
			if ( $this->is_space( $this->css[ $k ] ) ) {
				--$k;
			} elseif ( '/' === $this->css[ $k ] && isset( $this->comment_starts[ $k + 1 ] ) ) {
				$k = $this->comment_starts[ $k + 1 ] - 1;
			} else {
				break;
			}
		}
		if ( $k < 0 ) {
			return false;
		}
		$byte = ord( $this->css[ $k ] );
		// A name, a number, a unit or percentage, a decimal point, or the end of a function such as var(--x).
		return ( $byte >= 48 && $byte <= 57 ) || ( $byte >= 65 && $byte <= 90 ) || ( $byte >= 97 && $byte <= 122 ) || 45 === $byte || 95 === $byte || 37 === $byte || 46 === $byte || 41 === $byte || $byte >= 128;
	}

	/**
	 * Whether the declaration that starts at an offset has a custom property's name, such as --tag, before the colon at a later offset, so what comes after is its value.
	 * The name is read forward, as a browser reads it: comments and whitespace may surround it, and escapes in it are resolved.
	 *
	 * @param int $start Offset where the declaration starts.
	 * @param int $colon Offset of its first colon.
	 * @return bool
	 */
	private function is_custom_property( int $start, int $colon ): bool {
		$i                       = $this->skip_trivia( $start, $colon );
		list( $name, $consumed ) = $this->identifier( $i );
		return 0 === strpos( $name, '--' ) && $this->skip_trivia( $i + $consumed, $colon ) === $colon;
	}

	/**
	 * Steps over whitespace and comments.
	 *
	 * @param int $from  Offset to start at.
	 * @param int $limit Offset to stop at.
	 * @return int Offset of the first character that is neither, or $limit.
	 */
	private function skip_trivia( int $from, int $limit ): int {
		while ( $from < $limit ) {
			if ( $this->is_space( $this->css[ $from ] ) ) {
				++$from;
			} elseif ( '/' === $this->css[ $from ] && '*' === ( $this->css[ $from + 1 ] ?? '' ) ) {
				$end = strpos( $this->css, '*/', $from + 2 );
				if ( false === $end ) {
					return $limit;
				}
				$from = $end + 2;
			} else {
				break;
			}
		}
		return min( $from, $limit );
	}

	/**
	 * Whether a range comparison can be written here: in the parenthesised prelude of a media or container query, or in a condition function.
	 *
	 * @param string   $at     Name of the at-rule whose prelude is being read, if any.
	 * @param string[] $frames Names of the functions and groups that are open.
	 * @param bool[]   $colons For each open group, whether a colon has been read in it since its last semicolon, so the text after it is a value; a value in a group around this one makes this one a value too.
	 * @return bool
	 */
	private function in_range( string $at, array $frames, array $colons ): bool {
		// After a colon, in this group or one around it, the text is a value, as in style(--tag: fn(<div>)), so a space after the "<" would change the value that is tested.
		if ( in_array( true, $colons, true ) ) {
			return false;
		}
		if ( [] !== $frames && in_array( $at, self::RANGE_AT_RULES, true ) ) {
			return true;
		}
		// media(), style() and scroll-state() are conditions only inside if(); anywhere else a function with that name is just a function, and what is in it is a value.
		return in_array( 'if', $frames, true ) && [] !== array_intersect( $frames, self::RANGE_FUNCTIONS );
	}

	/**
	 * Whether the editor's rule matches at an offset.
	 *
	 * @param int $offset Offset of a "<".
	 * @return bool
	 */
	private function markup_at( int $offset ): bool {
		return 1 === preg_match( '#<\/?\w#A', $this->css, $unused, 0, $offset );
	}

	/**
	 * Whether a byte is CSS whitespace.
	 *
	 * @param string $char One byte.
	 * @return bool
	 */
	private function is_space( string $char ): bool {
		return false !== strpos( " \t\r\n\f", $char );
	}

	/**
	 * Starts a region: the edits that make up one change.
	 *
	 * @param string $kind url, string, escape, range or comment.
	 * @return void
	 */
	private function begin( string $kind ): void {
		$this->kind    = $kind;
		$this->pending = [];
	}

	/**
	 * Records an edit in the current region.
	 *
	 * @param int    $start Offset of the first replaced byte.
	 * @param int    $end   Offset after the last replaced byte.
	 * @param string $text  Replacement.
	 * @return void
	 */
	private function edit( int $start, int $end, string $text ): void {
		if ( ++$this->edit_count > self::MAX_EDITS ) {
			// More than any real stylesheet needs, and each edit takes memory: stop reading, and the text is reported as one that cannot be rewritten.
			$this->reliable = false;
			return;
		}
		$this->pending[] = [ $start, $end, $text ];
	}

	/**
	 * Ends the current region, keeping its edits in source order.
	 *
	 * @return void
	 */
	private function finish(): void {
		if ( [] === $this->pending ) {
			return;
		}
		usort(
			$this->pending,
			static function ( array $a, array $b ): int {
				return $a[0] <=> $b[0];
			}
		);
		foreach ( $this->pending as $edit ) {
			$this->edits[] = $edit;
		}
		$this->regions[] = [
			'kind'  => $this->kind,
			'edits' => $this->pending,
		];
		$this->pending   = [];
	}

	/**
	 * Builds the new text.
	 *
	 * @return string
	 */
	private function apply(): string {
		return self::splice( $this->css, 0, $this->edits );
	}

	/**
	 * Applies edits that fall inside a window of the source.
	 *
	 * @param string $css   Source text.
	 * @param int    $base  Offset of the first byte of $css within the source.
	 * @param array  $edits Edits in source order, in source offsets.
	 * @return string
	 */
	private static function splice( string $css, int $base, array $edits ): string {
		$output = '';
		$cursor = 0;
		foreach ( $edits as $edit ) {
			$start = $edit[0] - $base;
			$end   = $edit[1] - $base;
			if ( $start < $cursor || $end > strlen( $css ) ) {
				continue;
			}
			$output .= substr( $css, $cursor, $start - $cursor ) . $edit[2];
			$cursor  = $end;
		}
		return $output . substr( $css, $cursor );
	}

	/**
	 * Summarises each region for the administrator.
	 *
	 * @return array<int, array{kind: string, line: int, column: int, before: string, after: string}>
	 */
	private function describe_changes(): array {
		$locate  = self::locator( $this->css );
		$changes = [];
		foreach ( $this->regions as $region ) {
			$first    = $region['edits'][0][0];
			$from     = max( 0, $first - 12 );
			$window   = substr( $this->css, $from, self::EXCERPT_BYTES );
			$position = $locate( $first );

			$changes[] = [
				'kind'   => $region['kind'],
				'line'   => $position[0],
				'column' => $position[1],
				'before' => self::excerpt( $window, 0, 80 ),
				'after'  => self::excerpt( self::splice( $window, $from, $region['edits'] ), 0, 80 ),
			];
		}
		return $changes;
	}

	/**
	 * Returns a function that converts offsets to one-based line and byte column, counting CR, LF and CRLF as one line end each.
	 *
	 * It is fastest for offsets in ascending order and starts again if asked for an earlier one.
	 *
	 * @param string $text Text the offsets refer to.
	 * @return \Closure
	 */
	private static function locator( string $text ): \Closure {
		$at     = 0;
		$line   = 1;
		$column = 1;
		return static function ( int $offset ) use ( $text, &$at, &$line, &$column ): array {
			if ( $offset < $at ) {
				$at     = 0;
				$line   = 1;
				$column = 1;
			}
			for ( ; $at < $offset; ++$at ) {
				$char = $text[ $at ];
				if ( "\r" === $char || ( "\n" === $char && ( 0 === $at || "\r" !== $text[ $at - 1 ] ) ) ) {
					++$line;
					$column = 1;
				} elseif ( "\n" !== $char ) {
					++$column;
				}
			}
			return [ $line, $column ];
		};
	}

	/**
	 * A short, single-line, valid UTF-8 piece of text that is safe to put in a JSON response.
	 *
	 * @param string $text   Source text, possibly not valid UTF-8.
	 * @param int    $from   Offset to start at.
	 * @param int    $length Most bytes to take.
	 * @return string
	 */
	private static function excerpt( string $text, int $from, int $length ): string {
		$piece = (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', substr( $text, max( 0, $from ), $length ) );
		if ( 1 === preg_match( '//u', $piece ) ) {
			return $piece;
		}
		// A cut can land inside a multi-byte character; trim the broken ends before giving up on the text.
		$trimmed  = (string) preg_replace( '/^[\x80-\xBF]+/', '', $piece );
		$attempts = 0;
		while ( $attempts < 3 && 1 !== preg_match( '//u', $trimmed ) ) {
			$trimmed = substr( $trimmed, 0, -1 );
			++$attempts;
		}
		return 1 === preg_match( '//u', $trimmed ) ? $trimmed : (string) preg_replace( '/[^\x20-\x7E]/', '?', $piece );
	}
}
