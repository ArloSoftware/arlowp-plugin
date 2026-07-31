<?php

namespace ArloTraining;

use ArloTraining\Utilities;

class Environment {
    protected $memory_limit;

    const time_limit = 20; 
    public $start_time;

    public function __construct() {
        $this->memory_limit = $this->get_memory_limit();
    }
    //set time limit temporarily, set it back then.
    public function arlo_set_time_limit($time) {
        $original_time_limit = ini_get('max_execution_time');
        $disabled_functions = array_map('trim', explode(',', ini_get('disable_functions')));
        if ( ! in_array( 'ini_set', $disabled_functions, true ) ) {
            // phpcs:ignore WordPress.PHP.IniSet.max_execution_time_Disallowed, Squiz.PHP.DiscouragedFunctions.Discouraged -- Best-effort attempt to extend execution time on hosts where changing max_execution_time is allowed; set_time_limit() below resets the timer when available.
            ini_set( 'max_execution_time', $time );
        }
        if ( ! in_array( 'set_time_limit', $disabled_functions, true ) ) {
            set_time_limit($time); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Required for long-running import processes. Even if it might not be 100% successful, it's worth a try.
        }
        return $original_time_limit;
    }

    public function check_viable_execution_environment() {
        return !($this->time_exceeded() || $this->memory_exceeded());
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