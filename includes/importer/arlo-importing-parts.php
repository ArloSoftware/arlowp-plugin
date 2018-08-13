<?php

namespace Arlo\Importer;

use Arlo\Logger;
use Arlo\Utilities;
use Arlo\FileHandler;
use Arlo\Crypto;

class ImportingParts {
	private $dbl;

    private $table_name;


	public function __construct($dbl) {
        $this->dbl = $dbl;
        
        $this->table_name = $this->dbl->prefix . "arlo_import_parts";
    }

	public function add_import_part($part, $iteration, $content, $import_id) {
        $utc_date = gmdate("Y-m-d H:i:s");
        
        if (is_null($iteration)) { // as prepare() do not support null values
            $sql = "INSERT INTO 
                    {$this->table_name}
                    (import_id, part, import_text, created)
                VALUES
                    (%s, %s, %s, %s)
                ";

            $query = $this->dbl->prepare($sql, $import_id, $part, $content, $utc_date);
        } else {
            $sql = "INSERT INTO 
                    {$this->table_name}
                    (import_id, part, iteration, import_text, created)
                VALUES
                    (%s, %s, %d, %s, %s)
                ";

            $query = $this->dbl->prepare($sql, $import_id, $part, $iteration, $content, $utc_date);
        }

        $inserted = $this->dbl->query($query);

        if (!$inserted) return false;

        return $this->dbl->insert_id;
    }
    
    public function get_import_part($part, $iteration, $import_id) {
        $sql = "SELECT
                id,
                import_id,
                part,
                iteration,
                import_text,
                created,
                modified
            FROM
                {$this->table_name}
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
            $query = $this->dbl->prepare($sql, $import_id, $part);
        } else {
            $query = $this->dbl->prepare($sql, $import_id, $part, $iteration);
        }
        $rows = $this->dbl->get_results($query, OBJECT);

        if (is_array($rows) && count($rows) > 0) {
            return $rows[0];
        }

        return null;
    }

}
