<?php

function array_ikey_exists($key,$arr) { 
    if(preg_match("/".$key."/i", join(",", array_keys($arr))))                
        return true; 
    else 
        return false; 
} 

function date_format_to($format, $syntax) {
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
	return preg_replace( $pattern, $to, $format );
}

function strftime_format_to_date_format( $strf_format ) {
	return date_format_to( $strf_format, 'date' );
}

function date_format_to_strftime_format( $date_format ) {
	return date_format_to( $date_format, 'strf' );
}