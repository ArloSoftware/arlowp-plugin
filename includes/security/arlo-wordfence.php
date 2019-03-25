<?php

namespace Arlo\Security;

use Arlo\Logger;

class WordFence extends SecurityWhitelist {
    const MINIMUM_VERSION = '7.0.1';
    const PLUGIN_FILE = 'wordfence/wordfence.php';

    public function __construct($plugin, $dbl) {
        parent::__construct($plugin, $dbl);
        
        $this->config_table = $this->dbl->prefix . 'wfconfig';
        $this->plugin_file = self::PLUGIN_FILE;
        $this->minimum_version = self::MINIMUM_VERSION;
        $this->plugin_name = 'WordFence Security';
    }

    protected function get_whitelisted_ips() {
        if (is_array($this->whitelisted_ips)) {
            return $this->whitelisted_ips;
        }

        $sql = "
        SELECT 
            val
        FROM
            " . $this->config_table . "
        WHERE
            name = 'whitelisted'
        ";

        $this->whitelisted_ips = explode(",", $this->dbl->get_var($sql));

        return $this->whitelisted_ips;
    }

    protected function update_whitelist($ips = []) {
        if (is_array($ips) || $ips.count()) {
            $sql = "
            UPDATE
                " . $this->config_table . "
            SET
                val = %s
            WHERE
                name = 'whitelisted'
            ";
            $query = $this->dbl->query( $this->dbl->prepare($sql, implode(',', $ips)));
    
            if ($query === false) {
                throw new \Exception('SQL error: ' . $this->dbl->last_error );
            }    
        }
    }
}