<?php

namespace Arlo\Security;

use Arlo\Logger;

class IThemesSecurity extends SecurityWhitelist {
    const MINIMUM_VERSION = '7.0.1';
    const PLUGIN_FILE = 'better-wp-security/better-wp-security.php';

    private $option_name = 'itsec-storage';

    public function __construct($plugin, $dbl) {
        parent::__construct($plugin, $dbl);
        
        $this->plugin_file = self::PLUGIN_FILE;
        $this->minimum_version = self::MINIMUM_VERSION;
        $this->plugin_name = 'iThemes Security';
    }

    protected function get_whitelisted_ips() {
        if (is_array($this->whitelisted_ips)) {
            return $this->whitelisted_ips;
        }

        $this->whitelisted_ips = [];

        $settings = get_option($this->option_name, array());
        if (isset($settings['global']) && isset($settings['global']['lockout_white_list'])) {
            $this->whitelisted_ips = $settings['global']['lockout_white_list'];
        }

        return $this->whitelisted_ips;
    }

    protected function update_whitelist($ips = []) {
        if (is_array($ips) || $ips.count()) {
            $settings = get_option('itsec-storage', array());
            $settings['global']['lockout_white_list'] = $ips;

            update_option($this->option_name, $settings);
        }
    }
}