<?php
/**
 * Plugin Deactivation Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAISEO_Deactivation {
    
    /**
     * Run deactivation tasks
     */
    public static function deactivate() {
        // Clear scheduled events
        self::clear_scheduled_events();
        
        // Clear caches
        self::clear_caches();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Clear scheduled cron events
     */
    private static function clear_scheduled_events() {
        wp_clear_scheduled_hook('aaiseo_cleanup_cache');
        wp_clear_scheduled_hook('aaiseo_reset_api_usage');
    }
    
    /**
     * Clear plugin caches
     */
    private static function clear_caches() {
        global $wpdb;
        
        // Clear WordPress transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_aaiseo_%' 
             OR option_name LIKE '_transient_timeout_aaiseo_%'"
        );
        
        // Clear custom cache table if it exists
        $cache_table = $wpdb->prefix . 'aaiseo_cache';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $cache_table
        ));
        
        if ($table_exists) {
            $wpdb->query("TRUNCATE TABLE $cache_table");
        }
    }
}