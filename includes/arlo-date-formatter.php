<?php

namespace ArloTraining;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class DateFormatter {

	/**
	 * The widest minimum field width apply_strftime_compat_format() honours. A wider width is left as written,
	 * so a typo such as `%99999999d` cannot exhaust memory.
	 */
	private const STRFTIME_COMPAT_MAX_WIDTH = 64;

	private static function date_format_to( $format, $syntax ) {
		$strf_syntax = [
			'%O',
			'%d',
			'%a',
			'%e',
			'%A',
			'%u',
			'%w',
			'%j',
			'%V',
			'%B',
			'%m',
			'%b',
			'%-m',
			'%G',
			'%Y',
			'%y',
			'%P',
			'%p',
			'%l',
			'%I',
			'%H',
			'%M',
			'%S',
			'%z',
			'%Z',
			'%s',
		];

		$date_syntax = [
			'S',
			'd',
			'D',
			'j',
			'l',
			'N',
			'w',
			'z',
			'W',
			'F',
			'm',
			'M',
			'n',
			'o',
			'Y',
			'y',
			'a',
			'A',
			'g',
			'h',
			'H',
			'i',
			's',
			'O',
			'T',
			'U',
		];
		switch ( $syntax ) {
			case 'date':
				$from = $strf_syntax;
				$to   = $date_syntax;
				break;
			case 'strf':
				$from = $date_syntax;
				$to   = $strf_syntax;
				break;
			default:
				return false;
		}

		$pattern = array_map(
			function ( $s ) {
				return '/(?<!\\\\|\%)' . $s . '/';
			},
			$from
		);

		$new_format = preg_replace( $pattern, $to, $format );

		// The output is rendered by apply_strftime_compat_format(), which is the same on every platform, so no
		// per-platform spellings of %e, %l or %P are needed here.
		return $new_format;
	}

	public static function strftime_format_to_date_format( $strf_format ) {
		return self::date_format_to( $strf_format, 'date' );
	}

	public static function date_format_to_strftime_format( $date_format ) {
		return self::date_format_to( $date_format, 'strf' );
	}

	/**
	 * Renders a strftime() style format with DateTime, following glibc's strftime() in the C locale.
	 *
	 * The `format` attribute of the date shortcodes is documented as PHP's strftime format. strftime()
	 * is deprecated, so this reproduces it: every conversion in the PHP manual, the `-` (no padding),
	 * `_` (pad with spaces), `0` (pad with zeros) and `^` (upper case) flags, and a minimum width.
	 * A sequence it does not support, including `%#d`, the `%E` and `%O` modifiers and a width above 64, is
	 * left as written, as is a `%` that does not start a sequence. It never throws on a malformed format.
	 *
	 * Differences from glibc:
	 * - Names are always English.
	 * - `%e`, `%k` and `%l` are not padded unless `_` or a width is given (`%_e`), because the plugin's PHP
	 *   date() converter relies on them for `j` and `g`.
	 * - `%G` and `%g` are a compatibility shim and deliberately wrong: they give the calendar year, not the ISO
	 *   week-numbering year that strftime() defines. The Starter themes used `%G` to mean the year from 2017 until
	 *   they were changed to `%Y`, so saved copies of the older templates still contain it. Use `%Y` and `%y` in
	 *   new templates.
	 * - `%Z` and `%z` use `$text_overrides` when given, so the event's own time zone abbreviation and offset are
	 *   used with every flag and width. Without them they are the date's own `T` and `O` values, and `%z` is
	 *   `+1300`. The plugin passes the offset with a colon (`+13:00`), which differs from glibc.
	 * - `%z` with a flag or width is padded as plain text, whereas glibc pads it in an unusual way.
	 *
	 * @param string               $format         Format containing `%x` sequences.
	 * @param \DateTimeInterface   $date           Date to render, already in the wanted time zone.
	 * @param array<string,string> $text_overrides Text to use for a conversion letter instead of the date's own, keyed by
	 *                                             letter. Only `Z` and `z` are looked up.
	 * @return string
	 */
	public static function apply_strftime_compat_format( string $format, \DateTimeInterface $date, array $text_overrides = [] ): string {
		return (string) preg_replace_callback(
			'/%([_\-0^]*+)(\d*+)([a-zA-Z%])/',
			static function ( $matches ) use ( $date, $text_overrides ) {
				if ( '' !== $matches[2] && ( strlen( $matches[2] ) > 2 || (int) $matches[2] > self::STRFTIME_COMPAT_MAX_WIDTH ) ) {
					return $matches[0];
				}

				$field = self::strftime_compat_field( $matches[3], $date, $text_overrides );
				if ( null === $field ) {
					return $matches[0];
				}

				list( $text, $is_number, $default_width, $default_pad ) = $field;

				// glibc applies the padding flags in order, so the last of `_`, `-` and `0` wins. Scan from the end
				// without copying the flags, so a very long run of them costs no memory.
				$pad_flag = '';
				for ( $i = strlen( $matches[1] ) - 1; $i >= 0; $i-- ) {
					if ( '_' === $matches[1][ $i ] || '-' === $matches[1][ $i ] || '0' === $matches[1][ $i ] ) {
						$pad_flag = $matches[1][ $i ];
						break;
					}
				}

				if ( $is_number ) {
					$width = '' === $matches[2] ? 0 : (int) $matches[2];

					if ( '-' === $pad_flag ) {
						return str_pad( $text, $width, ' ', STR_PAD_LEFT );
					}

					$pad = $default_pad;
					if ( '_' === $pad_flag ) {
						$pad = ' ';
					} elseif ( '0' === $pad_flag ) {
						$pad = '0';
					}

					if ( '' === $pad ) {
						if ( 0 === $width ) {
							return $text;
						}

						$pad = ' ';
					}

					return str_pad( $text, max( $width, $default_width ), $pad, STR_PAD_LEFT );
				}

				if ( 'P' !== $matches[3] && false !== strpos( $matches[1], '^' ) ) {
					$text = strtoupper( $text );
				}

				return str_pad( $text, (int) $matches[2], '0' === $pad_flag ? '0' : ' ', STR_PAD_LEFT );
			},
			$format
		);
	}

	/**
	 * Returns the text of one strftime conversion.
	 *
	 * @param string             $conversion Conversion letter, or `%`.
	 * @param \DateTimeInterface $date       Date to render.
	 * @param array<string,string> $text_overrides See apply_strftime_compat_format().
	 * @return array{0: string, 1: bool, 2: int, 3: string}|null Text, whether it is a number, default width and default padding (empty for none unless asked). Null when unsupported.
	 */
	private static function strftime_compat_field( string $conversion, \DateTimeInterface $date, array $text_overrides ): ?array {
		$day_of_year = (int) $date->format( 'z' );
		$weekday     = (int) $date->format( 'w' );

		switch ( $conversion ) {
			case 'd':
				return [ $date->format( 'j' ), true, 2, '0' ];
			case 'e':
				return [ $date->format( 'j' ), true, 2, '' ];
			case 'H':
				return [ $date->format( 'G' ), true, 2, '0' ];
			case 'k':
				return [ $date->format( 'G' ), true, 2, '' ];
			case 'I':
				return [ $date->format( 'g' ), true, 2, '0' ];
			case 'l':
				return [ $date->format( 'g' ), true, 2, '' ];
			case 'm':
				return [ $date->format( 'n' ), true, 2, '0' ];
			case 'M':
				return [ (string) (int) $date->format( 'i' ), true, 2, '0' ];
			case 'S':
				return [ (string) (int) $date->format( 's' ), true, 2, '0' ];
			case 'j':
				return [ (string) ( $day_of_year + 1 ), true, 3, '0' ];
			case 'u':
				return [ $date->format( 'N' ), true, 1, '0' ];
			case 'w':
				return [ (string) $weekday, true, 1, '0' ];
			case 'U':
				return [ (string) intdiv( $day_of_year + 7 - $weekday, 7 ), true, 2, '0' ];
			case 'W':
				return [ (string) intdiv( $day_of_year + 7 - ( $weekday + 6 ) % 7, 7 ), true, 2, '0' ];
			case 'V':
				return [ (string) (int) $date->format( 'W' ), true, 2, '0' ];
			// Compatibility shim: %G and %g are deliberately the calendar year, not the ISO week-numbering year
			// that strftime() defines. The Starter themes used %G as the year from 2017 until they were changed to %Y,
			// and 5.0.10 to 5.2.0 rendered it that way. Following strftime() here would show the wrong year around
			// New Year (1 January 2027 as 2026, 30 December 2019 as 2020) in every saved copy of those templates.
			case 'G':
				return [ (string) (int) $date->format( 'Y' ), true, 0, '0' ];
			case 'g':
				return [ (string) ( (int) $date->format( 'Y' ) % 100 ), true, 2, '0' ];
			case 'y':
				return [ (string) ( (int) $date->format( 'Y' ) % 100 ), true, 2, '0' ];
			case 'C':
				return [ (string) intdiv( (int) $date->format( 'Y' ), 100 ), true, 2, '0' ];
			case 'Y':
				return [ (string) (int) $date->format( 'Y' ), true, 0, '0' ];
			case 's':
				return [ $date->format( 'U' ), true, 0, ' ' ];
			case 'a':
				return [ $date->format( 'D' ), false, 0, ' ' ];
			case 'A':
				return [ $date->format( 'l' ), false, 0, ' ' ];
			case 'b':
			case 'h':
				return [ $date->format( 'M' ), false, 0, ' ' ];
			case 'B':
				return [ $date->format( 'F' ), false, 0, ' ' ];
			case 'p':
				return [ $date->format( 'A' ), false, 0, ' ' ];
			case 'P':
				return [ $date->format( 'a' ), false, 0, ' ' ];
			case 'Z':
				return [ $text_overrides['Z'] ?? $date->format( 'T' ), false, 0, ' ' ];
			case 'z':
				return [ $text_overrides['z'] ?? $date->format( 'O' ), false, 0, ' ' ];
			case 'c':
				return [ self::apply_strftime_compat_format( '%a %b %_e %H:%M:%S %Y', $date ), false, 0, ' ' ];
			case 'D':
			case 'x':
				return [ self::apply_strftime_compat_format( '%m/%d/%y', $date ), false, 0, ' ' ];
			case 'F':
				return [ self::apply_strftime_compat_format( '%Y-%m-%d', $date ), false, 0, ' ' ];
			case 'r':
				return [ self::apply_strftime_compat_format( '%I:%M:%S %p', $date ), false, 0, ' ' ];
			case 'R':
				return [ self::apply_strftime_compat_format( '%H:%M', $date ), false, 0, ' ' ];
			case 'T':
			case 'X':
				return [ self::apply_strftime_compat_format( '%H:%M:%S', $date ), false, 0, ' ' ];
			case 'n':
				return [ "\n", false, 0, ' ' ];
			case 't':
				return [ "\t", false, 0, ' ' ];
			case '%':
				return [ '%', false, 0, ' ' ];
		}

		return null;
	}
}
