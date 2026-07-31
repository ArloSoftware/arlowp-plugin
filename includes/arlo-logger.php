<?php

namespace ArloTraining;

class Logger {
    const clean_up_days = 14;

    public static function log_error($message, $import_id = null, $timestamp = null, $successful = false) {
        self::save_log($message, $import_id, $timestamp, $successful, true);
    }

    public static function log($message, $import_id = null, $timestamp = null, $successful = false) {
        self::save_log($message, $import_id, $timestamp, $successful, false);
    }

    private static function save_log($message, $import_id = null, $timestamp = null, $successful = false, $raise_exception = false) {
		global $wpdb;

		if (!is_string($timestamp) || strtotime($timestamp) === false) {
			$now = Utilities::get_now_utc();
			$timestamp = $now->format("Y-m-d H:i:s");
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching --  Direct database query is required for custom table. Cache is cleared after the insert operation
		$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->prefix}arlo_log 
				(message, import_id, created, successful) 
				VALUES ( %s, %d, %s, %d ) 
				", $message, $import_id, $timestamp, $successful)); 

		CacheControl::cache_delete(CacheControl::GROUP_LOG);
        self::clean_up_log();

        if ($raise_exception) {
            self::raise_exception($message);
        }
    }  

    private static function raise_exception($message) {
        throw new \Exception($message); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Message is stored in the Arlo log table and any admin rendering escapes it via esc_html(); it is not output unescaped.
    }

    private static function clean_up_log() {
        global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required, using fixed table name and constant interval. Cache is cleared by next line.
		$wpdb->query("DELETE FROM {$wpdb->prefix}arlo_log WHERE CREATED < NOW() - INTERVAL " . self::clean_up_days . " DAY ORDER BY ID ASC");

		CacheControl::cache_delete(CacheControl::GROUP_LOG);
    }

    public static function get_log($successful = null, $limit = null) {
        global $wpdb;

        $successful = (!empty($successful) && is_bool($successful) ? $successful : null);
        $limit = (!empty($limit) && is_numeric($limit) ? $limit : null);

		$parameters = [];
        $where = "";

        if (!is_null($successful)) {
            $where = "WHERE successful = %d";
            $parameters[] = ($successful ? 1 : 0);
        }

        $sql = "
		SELECT
			message,
			created,
			successful
		FROM
			{$wpdb->prefix}arlo_log 
			$where
		ORDER BY
			id DESC
        ". (!is_null($limit) ? "LIMIT %d"  : "") ."
		";
		if(!is_null($limit)) {
			$parameters[] = $limit;
		}
	
		if (!empty($parameters)) {
			$sql = Utilities::prepare_sql($sql, $parameters);
		}
        
		$items = CacheControl::fetch_results($sql, ARRAY_A, CacheControl::GROUP_LOG);

		return $items; 
    }

	/**
	 * Stream the import log as a CSV file download.
	 *
	 * Sends appropriate HTTP headers and outputs the full log as a UTF-8 CSV
	 * file, then terminates the request. Each field is sanitized against formula
	 * injection before being written. Returns without output if the CSV could
	 * not be generated.
	 *
	 * @return void
	 */
	public static function render_log_csv_attachment() {
		try {
			$csv = self::build_log_csv_string(0);
		} catch ( \RuntimeException $e ) {
			return;
		}

		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename="arlo_sync_log.csv"');
		header('Content-Length: ' . strlen($csv));

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Diagnostic log CSV download. Each field is sanitized against formula injection before fputcsv() at write time; not HTML output.
		exit;
	}

	/**
	 * Build the import log as a CSV-formatted string.
	 *
	 * Queries the log table, writes rows to a temporary stream (php://temp,
	 * backed by memory up to 2 MB then spilled to a temp file) via fputcsv(),
	 * and returns the result as a string. Each field is sanitized
	 * against formula injection before being written. Throws if the temporary
	 * stream cannot be opened, the query fails, or the stream cannot be read.
	 *
	 * @param int $limit Maximum number of rows to include, ordered by id DESC.
	 *                   Pass 0 for no limit.
	 * @return string CSV-formatted log contents.
	 * @throws \RuntimeException If the temporary stream cannot be opened, the query fails, or the stream cannot be read.
	 */
	public static function build_log_csv_string($limit = 1000) {
		global $wpdb;
		$limit = max( 0, intval( $limit ) );
		        
		$fp = fopen('php://temp/maxmemory:2097125', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- php://temp is an in-memory stream; WP_Filesystem does not support in-memory streams.
		if ( $fp === false ) {
			self::log('Couldn\'t create log CSV');
			throw new \RuntimeException('Couldn\'t create log CSV');
		}
        
		$parameters = [];
		$has_limit = $limit !== 0;
		if ( $has_limit ) {
			$parameters[] = $limit;
		}
        $sql = "
            SELECT 
                id,
                message,
                created, 
                successful
            FROM
                {$wpdb->prefix}arlo_log
            ORDER BY
            	id DESC
            " . 
            ($has_limit ? 'LIMIT %d' : '');
			
        $sql = Utilities::prepare_sql($sql, $parameters);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL preparation is handled above. A direct query is required here so SQL failures are not masked or cached during CSV generation.
		$entries = $wpdb->get_results($sql, ARRAY_N);
		if ( ! empty( $wpdb->last_error ) ) {
			self::log($wpdb->last_error);
			throw new \RuntimeException('Could not query log entries for CSV export.');
		}
	
		if (is_array($entries) && count($entries)) {
			fputcsv($fp, array('id', 'message', 'created_date', 'is_success'));

			foreach ($entries as $entry) {
				$entry = array_map( [ self::class, 'sanitize_csv_field_value' ], $entry );
				fputcsv( $fp, $entry );
			}
		}

		rewind($fp);
		$csv = stream_get_contents($fp);
		if ( false === $csv ) {
			fclose($fp); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes a php://temp in-memory stream; WP_Filesystem does not support in-memory streams.
			self::log('Could not read log CSV stream.');
			throw new \RuntimeException('Could not read log CSV stream.');
		}
		fclose($fp); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes a php://temp in-memory stream; WP_Filesystem does not support in-memory streams.
		
		return $csv;
	
	}  

	/**
	 * Sanitize a single CSV field value against formula injection.
	 *
	 * Spreadsheet applications (Excel, Google Sheets) interpret cell values that
	 * begin with '=', '+', '-', or '@' as formulas, and '\t' / '\r' as potential
	 * triggers in some parsers. A leading single-quote forces the cell to be
	 * treated as literal text rather than a formula — it acts as a text-prefix
	 * marker recognised by those applications and is not displayed in the cell.
	 *
	 * @param mixed $field The field value to sanitize.
	 * @return mixed The original value, or the value prefixed with "'" if it begins
	 *               with a formula-injection character.
	 */
	private static function sanitize_csv_field_value( $field ) {
		if ( is_string( $field ) && strlen( $field ) > 0 && in_array( $field[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
			return "'" . $field;
		}
		return $field;
	}
}