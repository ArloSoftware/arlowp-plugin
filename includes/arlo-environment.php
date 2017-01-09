<?php

namespace Arlo;

class Environment {
    protected $memory_limit;

    const time_limit = 20; 
    public $start_time;

    public function __construct() {
		ini_set('max_execution_time', 3000);
		set_time_limit(3000);	

        $this->memory_limit = $this->get_memory_limit();
    }

    public function check_viable_execution_environment() {
        return !($this->time_exceeded() || $this->memory_exceeded());
    }    

    protected function get_memory_limit() {
        if ( function_exists( 'ini_get' ) ) {
            $memory_limit = ini_get( 'memory_limit' );
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