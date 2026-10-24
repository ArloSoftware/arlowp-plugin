<?php

namespace ArloTraining;

class TimeZoneManager {
	
	private $plugin;

	/** @var array<string,string|false> Resolved identifier per Windows time zone ID; false when none is usable. */
	private static $resolved_identifiers = [];

	public $timezones;
	public $indexed_timezones;

	public function __construct($plugin) {
		$this->plugin = $plugin;
	}

	/**
	 * Resolves an Arlo Windows time zone ID to a PHP time zone the host accepts.
	 *
	 * @param mixed $windows_tz_id Windows time zone ID from the imported data.
	 * @return \DateTimeZone|null Null when the ID is unmapped or no candidate is accepted.
	 */
	public static function resolve_php_timezone( $windows_tz_id ) {
		if ( ! is_string( $windows_tz_id ) || '' === $windows_tz_id ) {
			return null;
		}

		if ( ! array_key_exists( $windows_tz_id, self::$resolved_identifiers ) ) {
			$map      = Arrays::$arlo_timezone_system_names_to_php_tz_identifiers;
			$timezone = isset( $map[ $windows_tz_id ] ) ? self::first_valid_timezone( $map[ $windows_tz_id ] ) : null;

			self::$resolved_identifiers[ $windows_tz_id ] = null === $timezone ? false : $timezone->getName();

			return $timezone;
		}

		$identifier = self::$resolved_identifiers[ $windows_tz_id ];

		return false === $identifier ? null : self::first_valid_timezone( $identifier );
	}

	/**
	 * Returns the first candidate identifier this PHP build accepts.
	 *
	 * @param string|string[] $candidates One identifier or an ordered list.
	 * @return \DateTimeZone|null
	 */
	public static function first_valid_timezone( $candidates ) {
		foreach ( (array) $candidates as $candidate ) {
			if ( ! is_string( $candidate ) || '' === $candidate ) {
				continue;
			}

			try {
				return new \DateTimeZone( $candidate );
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		return null;
	}

	/**
	 * Fallback only: builds a fixed-offset zone (no region, DST rules or abbreviation) from Arlo's stored UTC offset,
	 * such as "+13:00" or "+1300". It does not look up a named zone for that offset.
	 *
	 * @param mixed $offset
	 * @return \DateTimeZone|null Null when the offset is malformed, outside -12:00 to +14:00, or rejected by PHP.
	 */
	public static function offset_timezone( $offset ) {
		if ( ! is_string( $offset ) || ! preg_match( '/^([+-])(\d{2}):?(\d{2})$/', $offset, $parts ) ) {
			return null;
		}

		$minutes = (int) $parts[2] * 60 + (int) $parts[3];
		if ( (int) $parts[3] > 59 || $minutes > ( '+' === $parts[1] ? 14 : 12 ) * 60 ) {
			return null;
		}

		return self::first_valid_timezone( $parts[1] . $parts[2] . ':' . $parts[3] );
	}

	/**
	 * Normal path: returns the mapped zone when its rules agree with the offset Arlo stored for the event.
	 * Fallback: when it is missing, or disagrees (stale host tz data or a wrong mapping), returns a fixed-offset
	 * zone for the stored offset so the event still renders at the time Arlo recorded.
	 *
	 * @param \DateTimeZone|null $timezone Resolved zone for the event, if any.
	 * @param \DateTimeInterface $instant  Event time built from its stored local time and offset.
	 * @return \DateTimeZone
	 */
	public static function zone_for_instant( $timezone, \DateTimeInterface $instant ) {
		if ( $timezone instanceof \DateTimeZone && $timezone->getOffset( $instant ) === $instant->getOffset() ) {
			return $timezone;
		}

		return new \DateTimeZone( $instant->format( 'P' ) );
	}

	/**
	 * Returns the zone abbreviation, or "(UTC-05:00)" when PHP has only a numeric offset such as "GMT-0500" or "+03".
	 *
	 * @param \DateTimeInterface $date Date already set to its display zone.
	 * @return string
	 */
	public static function get_abbreviation( \DateTimeInterface $date ) {
		$abbreviation = $date->format( 'T' );

		if ( preg_match( '/^(?:GMT|UTC)?[+-]\d{1,2}(?::?\d{2})?$/', $abbreviation ) ) {
			return '(UTC' . $date->format( 'P' ) . ')';
		}

		return $abbreviation;
	}

	public function get_timezones() {
		if (!$this->timezones) {
			$this->timezones = $this->query_timezones();
		}

		return $this->timezones;
	}

	public function get_indexed_timezones($timezone_id = 0) {
		if (!$this->indexed_timezones) {
			$this->timezones = $this->get_timezones();

			$this->indexed_timezones = [];
			foreach ($this->timezones as $timezone) {
				$this->indexed_timezones[$timezone['id']] = $timezone;
			}
		}

		if (intval($timezone_id) > 0) {
			return !empty($this->indexed_timezones[$timezone_id]) ? $this->indexed_timezones[$timezone_id] : null;
		} 

		return $this->indexed_timezones;
	}	

	private function query_timezones($timezone_id = 0) {
		global $wpdb;
		$import_id = $this->plugin->get_importer()->get_current_import_id();
		$timezone_id = intval($timezone_id);

		return CacheControl::fetch_results($wpdb->prepare("
		SELECT
			id,
			name,
			windows_tz_id
		FROM
			{$wpdb->prefix}arlo_timezones
		WHERE
			import_id = %d
		ORDER BY utc_offset, name
		", $import_id), ARRAY_A);
	}

}