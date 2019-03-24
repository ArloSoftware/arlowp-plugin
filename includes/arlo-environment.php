<?php

namespace Arlo;

use Arlo\Utilities;
use Arlo\Logger;

class Environment {
    const time_limit = 20; 
    const ARLO_IPS = ['52.36.235.221','52.51.185.255','52.18.103.242','13.54.120.42','13.54.47.103'];

	private $dbl;
	private $plugin;

    protected $memory_limit;

    public $start_time;

    public function __construct($plugin, $dbl) {
        $this->dbl = &$dbl; 	
		$this->plugin = $plugin;
        	

		ini_set('max_execution_time', 3000);

        $disabled_functions = array_map('trim', explode(',', ini_get('disable_functions')));

        if (!in_array('set_time_limit', $disabled_functions)){
    		set_time_limit(3000);
        }

        $this->memory_limit = $this->get_memory_limit();
    }

    public function check_viable_execution_environment() {
        return !($this->time_exceeded() || $this->memory_exceeded());
    }    

    public function check_wordfence() {
        $wf_minimum_version = '7.0.1';
        $wf_config_table = $this->dbl->prefix . 'wfconfig';

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();

        if (array_key_exists('wordfence/wordfence.php', $all_plugins)) {
            $wordfence_version = $all_plugins['wordfence/wordfence.php']['Version'];
            if (version_compare($wordfence_version, $wf_minimum_version) >= 0) {
                $sql = "
                SELECT 
                    val
                FROM
                    " . $wf_config_table . "
                WHERE
                    name = 'whitelisted'
                ";
                
                try {
                    $whitelisted_ips = explode(",", $this->dbl->get_var($sql));
                    foreach(self::ARLO_IPS as $ip) {
                        if (!in_array($ip, $whitelisted_ips)) {
                            $whitelisted_ips[] = $ip;
                        }
                    } 

                    $sql = "
                    UPDATE
                        " . $wf_config_table . "
                    SET
                        val = %s
                    WHERE
                        name = 'whitelisted'
                    ";
                    $query = $this->dbl->query( $this->dbl->prepare($sql, implode(',', $whitelisted_ips)) );

                    if ($query === false) {
                        throw new \Exception('SQL error: ' . $this->dbl->last_error );
                    }
                } catch(\Exception $e) {
                    Logger::log("Couldn't update WordFence's whitelist: " . $e->getMessage());

                    $message = [
                        '<p>' . __('Arlo for WordPress has detected that WordFence Security plugin is installed, but Arlo for WordPress wasn\'t be able to update the firewall rules.') . '</p>',
                        '<p>' . __('For more information, please visit our', 'arlo-for-wordpress') .' ' . sprintf(__('<a target="_blank" href="%s">Help Center</a>.', 'arlo-for-wordpress' ), 'https://support.arlo.co/hc/en-gb/articles/360001023963-Known-conflicts-with-other-WordPress-Plugins') . '</p>'
					];
			
					$this->plugin->get_message_handler()->set_message('error', __('Couldn\'t update WordFence Security ' , 'arlo-for-wordpress' ), implode('', $message), true);
                }
            }
        }
    }

    protected function get_memory_limit() {
        if ( function_exists( 'ini_get' ) ) {
            $memory_limit_setting = ini_get( 'memory_limit' );
            $memory_limit = Utilities::settingToMegabytes($memory_limit_setting);
        } else {
            // Sensible default.
            $memory_limit = '128M';
        }

        if ( ! $memory_limit || -1 === $memory_limit ) {
            // Unlimited, set to 32GB.
            $memory_limit = '32000M';
        }

        return intval( $memory_limit ) * 1024 * 1024;
    }

    protected function time_exceeded() {
        $finish = $this->start_time + self::time_limit;

        return time() >= $finish;
    }

    protected function memory_exceeded() {
        $memory_limit   = $this->memory_limit * 0.9; // 90% of max memory
        $current_memory = memory_get_usage( true );

        return $current_memory >= $memory_limit;
    }
}