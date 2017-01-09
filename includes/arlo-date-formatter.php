<?php

namespace Arlo;

class DateFormatter {

	private static function date_format_to($format, $syntax) {
		$strf_syntax = [
			'%O', '%d', '%a', '%e', '%A', '%u', '%w', '%j',
			'%V',
			'%B', '%m', '%b', '%-m',
			'%G', '%Y', '%y',
			'%P', '%p', '%l', '%I', '%H', '%M', '%S',
			'%z', '%Z',
			'%s'
		];

		$date_syntax = [
			'S', 'd', 'D', 'j', 'l', 'N', 'w', 'z',
			'W',
			'F', 'm', 'M', 'n',
			'o', 'Y', 'y',
			'a', 'A', 'g', 'h', 'H', 'i', 's',
			'O', 'T',
			'U'
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

		if ($syntax == 'strf') {
			if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
				$new_format = preg_replace('#(?<!%)((?:%%)*)%e#', '\1%#d', $new_format);
				$new_format = preg_replace('#(?<!%)((?:%%)*)%l#', '\1%I', $new_format);
			} else if (strtoupper(substr(PHP_OS, 0, 3)) == 'DAR') {
				$new_format = preg_replace('#(?<!%)((?:%%)*)%P#', '\1%p', $new_format);
			}
		}

		return $new_format;
	}

	public static function strftime_format_to_date_format( $strf_format ) {
		return self::date_format_to( $strf_format, 'date' );
	}

	public static function date_format_to_strftime_format( $date_format ) {
		return self::date_format_to( $date_format, 'strf' );
	}
}