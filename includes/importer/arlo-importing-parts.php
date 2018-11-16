<?php

namespace Arlo\Importer;

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
                    (import_id, part, created)
                VALUES
                    (%s, %s, %s)
                ";

            $query = $this->dbl->prepare($sql, $import_id, $part, $utc_date);
        } else {
            $sql = "INSERT INTO 
                    {$this->table_name}
                    (import_id, part, iteration, created)
                VALUES
                    (%s, %s, %d, %s)
                ";

            $query = $this->dbl->prepare($sql, $import_id, $part, $iteration, $utc_date);
        }

        $inserted = $this->dbl->query($query);
        $insert_id = $this->dbl->insert_id;

        if (empty($inserted) || empty($insert_id)) return false;


        // insert by chunks

        $size = 512 * 1024;
        $offset = 0;
        $chunk = substr($content, $offset, $size);

        while (!empty($chunk)) {
            usleep(10000); // 10 ms.

            $sql = "UPDATE 
                    {$this->table_name}
                SET
                    import_text = CONCAT_WS('', import_text, %s)
                WHERE
                    id = %d
                ";

            $query = $this->dbl->prepare($sql, $chunk, $insert_id);

            $updated = $this->dbl->query($query);

            $offset += $size;
            $chunk = substr($content, $offset, $size);
        }

        return $insert_id;
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
    
    public function delete_all_import_parts() {
        $sql = "DELETE FROM
                {$this->table_name}
            ";

        $this->dbl->query($sql);
    }

}
