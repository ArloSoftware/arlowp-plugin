<?php

namespace ArloTraining\Importer;

class ImportingParts {

	public function __construct() {
    }

	public function add_import_part($part, $iteration, $content, $import_id) {
        global $wpdb;
        $utc_date = gmdate("Y-m-d H:i:s");

        if (is_null($iteration)) { // as prepare() do not support null values
            $query = $wpdb->prepare(
                "INSERT INTO 
                    {$wpdb->prefix}arlo_import_parts
                    (import_id, part, created)
                VALUES
                    (%s, %s, %s)
                ", 
                $import_id, 
                $part, 
                $utc_date
            );
        } else {
            $query = $wpdb->prepare(
                "INSERT INTO 
                    {$wpdb->prefix}arlo_import_parts
                    (import_id, part, iteration, created)
                VALUES
                    (%s, %s, %d, %s)
                ", 
                $import_id, 
                $part, 
                $iteration, 
                $utc_date
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Direct database query is required. Do not need cache for insert operation in data import process. $query is prepared above
        $inserted = $wpdb->query($query);
        $insert_id = $wpdb->insert_id;

        if (empty($inserted) || empty($insert_id)) return false;


        // insert by chunks

        $size = 512 * 1024;
        $offset = 0;
        $chunk = substr($content, $offset, $size);

        while (!empty($chunk)) {
            usleep(10000); // 10 ms.

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required. Do not need cache for update operation in data import process .
            $updated = $wpdb->query($wpdb->prepare("UPDATE 
                    {$wpdb->prefix}arlo_import_parts
                SET
                    import_text = CONCAT_WS('', import_text, %s)
                WHERE
                    id = %d
                ", $chunk, $insert_id));

            $offset += $size;
            $chunk = substr($content, $offset, $size);
        }

        return $insert_id;
    }
    
    public function get_import_part($part, $iteration, $import_id) {
        global $wpdb;
        $sql = "SELECT
                id,
                import_id,
                part,
                iteration,
                import_text,
                created,
                modified
            FROM
                {$wpdb->prefix}arlo_import_parts
            WHERE
                import_id = %s
            AND
                part = %s
            AND
                " . (is_null($iteration) ? 
                    " iteration IS NULL " :
                    " iteration = %d "
                ) . "
            ";

        if (is_null($iteration)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.
            $query = $wpdb->prepare($sql, $import_id, $part);
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- The SQL statement is dynamically constructed and parameters are prepared here.
            $query = $wpdb->prepare($sql, $import_id, $part, $iteration);
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Direct database query is required and prepared above. Do not need cache in data import process.
        $rows = $wpdb->get_results($query, OBJECT);

        if (is_array($rows) && count($rows) > 0) {
            return $rows[0];
        }

        return null;
    }
    
    public function delete_all_import_parts() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct database query is required for custom table. Do not need cache for import process
        $wpdb->query("DELETE FROM {$wpdb->prefix}arlo_import_parts");
    }

}
