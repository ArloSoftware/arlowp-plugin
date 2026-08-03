<?php

namespace ArloTraining;

class CacheControl {
    const DEFAULT_CACHE_TIME    = 1200;                      // 20 minutes (1200 seconds) — import-data safety-net TTL
    const CACHE_TIME_MUTABLE    = 60;                        // 60 seconds — safety-net TTL for event-driven groups that should recover quickly from a missed cache flush
    const GROUP_VERSIONS        = 'ArloCacheVersions';      //For cache versioning
    const GROUP_PRIMARY_OBJECTS = 'ArloPrimaryObjects';     //For data synchronized from Arlo platform
    const GROUP_LOG             = 'ArloLogs';               //For log generated in this plugin  
    const GROUP_API             = 'ArloAPI';                //For API responses
    const GROUP_MESSAGES        = 'ArloMessages';           //For messages

    // Best-effort page-cache purge hooks exposed by some caching plugins.
    // These do not manage the WP object cache groups handled elsewhere in this class.
    private static $page_cache_purge_functions = [
        'wp_cache_clear_cache', // WP Super Cache
        'w3tc_flush_posts' // W3 Total Cache
    ];

    private static function supportsGroupFlush() {
        return function_exists('wp_cache_supports') && wp_cache_supports('flush_group');
    }

    /**
     * Store a value in the cache for the requested group.
     *
     * When no explicit TTL is supplied, the group's default TTL is used.
     *
     * @param string   $key    Cache key.
     * @param mixed    $data   Value to cache.
     * @param string   $group  Cache group.
     * @param int|null $expire Cache expiration in seconds.
     * @return void
     */
    public static function cache_set($key, $data, $group = self::GROUP_PRIMARY_OBJECTS, $expire = null) {
        if (is_null($expire)) {
            $expire = self::get_group_cache_time($group);
        }

        // If cache supports group flush, store raw data without versioning
        if (self::supportsGroupFlush()) {
            wp_cache_set($key, $data, $group, $expire);
            return;
        }
        // Store data with version information
        $cache_data = [
            'data' => $data,
            'version' => self::get_group_version($group),
            'timestamp' => time()
        ];
        
        wp_cache_set($key, $cache_data, $group, $expire);
    }

    /**
     * Read a value from the cache for the requested group.
     *
     * For caches using versioned fallback invalidation, this also verifies the
     * stored group version before returning a hit.
     *
     * @param string $key   Cache key.
     * @param string $group Cache group.
     * @return mixed|false Cached value, or false on cache miss.
     */
    public static function cache_get($key, $group = self::GROUP_PRIMARY_OBJECTS) {
        // If cache supports group flush, read raw value
        if (self::supportsGroupFlush()) {
            return wp_cache_get($key, $group);
        }
        
        $cache_data = wp_cache_get($key, $group);
        
        if ($cache_data === false) {
            return false;
        }
        
        // Check if version matches
        $current_version = self::get_group_version($group);
        if (!isset($cache_data['version']) || $cache_data['version'] !== $current_version) {
            // Version mismatch, return cache miss
            wp_cache_delete($key, $group);
            return false;
        }
        
        return $cache_data['data'];
    }
    
    /**
     * Invalidate all cached entries for a cache group.
     *
     * Uses native group flushing when the active object-cache backend supports
     * it; otherwise falls back to version-based invalidation.
     *
     * @param string $group Cache group.
     * @return void
     */
    public static function cache_delete($group) {
        // Check if the object cache implementation supports group flushing
        if (self::supportsGroupFlush()) {
            // Use native group flushing if available (more efficient)
            wp_cache_flush_group($group);
        } else {
            // Fallback: Only increment version number, all old version caches will automatically expire
            self::increment_group_version($group);
        }
    }
    
    /**
     * Get the version number for a cache group
     */
    public static function get_group_version($group) {
        $version = wp_cache_get($group, self::GROUP_VERSIONS);
        
        if ($version === false) {
            $version = 1;
            wp_cache_set($group, $version, self::GROUP_VERSIONS, 0); // Never expire
        }
        
        return $version;
    }
    
    /**
     * Increment the version number for a cache group
     */
    public static function increment_group_version($group) {
        $current_version = self::get_group_version($group);
        $new_version = $current_version + 1;
        
        wp_cache_set($group, $new_version, self::GROUP_VERSIONS, 0); // Never expire
        
        return $new_version;
    }

    /**
     * Get value from cache or compute it via callback if missing
     * 
     * @param string   $key      Cache key
     * @param callable $callback Function to generate data if cache miss
     * @param string   $group    Cache group
     * @param int|null $expire   Cache expiration in seconds
     * @return mixed   Cached or computed value
     */
    public static function get_cached_object($key, $callback, $group = self::GROUP_PRIMARY_OBJECTS, $expire = null) {
        $result = self::cache_get($key, $group);
        
        if ($result !== false) {
            return $result;
        }

        $result = call_user_func($callback);
        
        // Don't cache if result is false (standard WP behavior)
        if ($result !== false) {
            self::cache_set($key, $result, $group, $expire);
        }
        
        return $result;
    }

    /**
     * Fetch multiple results with caching
     *
     * @param string $query SQL query
     * @param string $output_type Output type (OBJECT, ARRAY_A, ARRAY_N)
     * @param string $group Cache group
     * @param int|null $expire Cache expiration in seconds
     * @param string|null $cache_key Optional custom cache key
     * @return array|object|null Database query results
     */
    public static function fetch_results($query, $output_type = OBJECT, $group = self::GROUP_PRIMARY_OBJECTS, $expire = null, $cache_key = null) {
        global $wpdb;
        if (empty($cache_key)) {
            $cache_key = md5($query . 'results' . $output_type);
        }
        
        return self::get_cached_object($cache_key, function() use ($wpdb, $query, $output_type) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL preparation is the responsibility of the caller. Caching is handled by this class wrapper. Direct query is required for custom table
            return $wpdb->get_results($query, $output_type);
        }, $group, $expire);
    }

    /**
     * Fetch single row with caching
     *
     * @param string $query SQL query
     * @param string $output_type Output type (OBJECT, ARRAY_A, ARRAY_N)
     * @param string $group Cache group
     * @param int|null $expire Cache expiration in seconds
     * @param string|null $cache_key Optional custom cache key
     * @return array|object|null Database query result
     */
    public static function fetch_row($query, $output_type = OBJECT, $group = self::GROUP_PRIMARY_OBJECTS, $expire = null, $cache_key = null) {
        global $wpdb;
        if (empty($cache_key)) {
            $cache_key = md5($query . 'row' . $output_type);
        }
        
        return self::get_cached_object($cache_key, function() use ($wpdb, $query, $output_type) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL preparation is the responsibility of the caller. Caching is handled by this class wrapper. Direct query is required for custom table
            return $wpdb->get_row($query, $output_type);
        }, $group, $expire);
    }

    /**
     * Trigger best-effort third-party page-cache purges after content changes.
     *
     * This does not clear CacheControl's object-cache groups. Group invalidation
     * is handled by cache_delete() and, when available, wp_cache_flush_group().
     *
     * @return void
     */
    public static function Clear(){
        foreach (self::$page_cache_purge_functions as $purgeFunction) {
            if (function_exists($purgeFunction)){
                $purgeFunction();
            }
        }
    }

    private static function get_group_cache_time($group) {
        switch ($group) {
            case self::GROUP_LOG:
            case self::GROUP_MESSAGES:
                return self::CACHE_TIME_MUTABLE;
            default:
                return self::DEFAULT_CACHE_TIME;
        }
    }
}
