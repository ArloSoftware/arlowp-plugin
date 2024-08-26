<?php

namespace Arlo;

class CacheControl {
    // List of functions that we could potentially execute to clear the entire cache.
    // Most plugins do expose a single function which we can use.
    private static $cache_purge_functions = [
        'wp_cache_clear_cache', // WP Super Cache
        'w3tc_flush_posts' // W3 Total Cache
    ];

    public static function Clear(){
        foreach (self::$cache_purge_functions as $purgeFunction) {
            if (function_exists($purgeFunction)){
                $purgeFunction();
            }
        }
    }
}
