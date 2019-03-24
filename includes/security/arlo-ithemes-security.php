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

    protected function raise_error_message() {
        $message = [
            '<p>' . __('Arlo for WordPress has detected that iThemes Security Security plugin is installed, but Arlo for WordPress wasn\'t be able to update the firewall rules.') . '</p>',
            '<p>' . __('For more information, please visit our', 'arlo-for-wordpress') .' ' . sprintf(__('<a target="_blank" href="%s">Help Center</a>.', 'arlo-for-wordpress' ), 'https://support.arlo.co/hc/en-gb/articles/360001023963-Known-conflicts-with-other-WordPress-Plugins') . '</p>'
        ];

        $this->plugin->get_message_handler()->set_message('error', __('Couldn\'t update iThemes Security ' , 'arlo-for-wordpress' ), implode('', $message), true);
    }
}