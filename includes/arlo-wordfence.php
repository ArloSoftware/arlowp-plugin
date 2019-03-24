<?php

namespace Arlo;

use Arlo\Logger;

class WordFence extends SecurityWhitelist {
    const MINIMUM_VERSION = '7.0.1';
    const PLUGIN_FILE = 'wordfence/wordfence.php';

    public function __construct($plugin, $dbl) {
        parent::__construct($plugin, $dbl);
        
        $this->config_table = $this->dbl->prefix . 'wfconfig';
        $this->plugin_file = self::PLUGIN_FILE;
        $this->minimum_version = self::MINIMUM_VERSION;
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
        if (is_array($ips) || ips.count()) {
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

    protected function raise_error_message() {
        $message = [
            '<p>' . __('Arlo for WordPress has detected that WordFence Security plugin is installed, but Arlo for WordPress wasn\'t be able to update the firewall rules.') . '</p>',
            '<p>' . __('For more information, please visit our', 'arlo-for-wordpress') .' ' . sprintf(__('<a target="_blank" href="%s">Help Center</a>.', 'arlo-for-wordpress' ), 'https://support.arlo.co/hc/en-gb/articles/360001023963-Known-conflicts-with-other-WordPress-Plugins') . '</p>'
        ];

        $this->plugin->get_message_handler()->set_message('error', __('Couldn\'t update WordFence Security ' , 'arlo-for-wordpress' ), implode('', $message), true);
    }
}