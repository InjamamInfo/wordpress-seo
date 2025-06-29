<?php
/**
 * Plugin Activation Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAISEO_Activation {
    
    /**
     * Run activation tasks
     */
    public static function activate() {
        // Create database tables
        self::create_tables();
        
        // Set default options
        self::set_default_options();
        
        // Create necessary directories
        self::create_directories();
        
        // Schedule cron events
        self::schedule_events();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create tables with proper error handling
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // SEO Reports table
        $reports_table = $wpdb->prefix . 'aaiseo_reports';
        $reports_sql = "CREATE TABLE $reports_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            report_type varchar(50) NOT NULL,
            report_data longtext NOT NULL,
            score int(3) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY report_type (report_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta($reports_sql);
        
        // Cache table
        $cache_table = $wpdb->prefix . 'aaiseo_cache';
        $cache_sql = "CREATE TABLE $cache_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            cache_key varchar(255) NOT NULL,
            cache_value longtext NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY cache_key (cache_key),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        dbDelta($cache_sql);
        
        // Update database version
        update_option('aaiseo_db_version', AAISEO_PLUGIN_VERSION);
    }
    
    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $default_options = array(
            'plugin_version' => AAISEO_PLUGIN_VERSION,
            'preferred_ai_provider' => 'internal',
            'openai_api_key' => '',
            'grok_api_key' => '',
            'gemini_api_key' => '',
            'deepseek_api_key' => '',
            'auto_optimize' => false,
            'cache_duration' => 3600,
            'max_requests_per_hour' => 100,
            'enable_logging' => false,
            'real_time_analysis' => true,
            'analysis_delay' => 2000
        );
        
        // Only set if option doesn't exist
        if (!get_option('aaiseo_settings')) {
            add_option('aaiseo_settings', $default_options);
        } else {
            // Update existing options with new defaults
            $existing_options = get_option('aaiseo_settings', array());
            $updated_options = array_merge($default_options, $existing_options);
            update_option('aaiseo_settings', $updated_options);
        }
    }
    
    /**
     * Create necessary directories
     */
    private static function create_directories() {
        $upload_dir = wp_upload_dir();
        $aaiseo_dir = $upload_dir['basedir'] . '/aaiseo';
        
        if (!file_exists($aaiseo_dir)) {
            wp_mkdir_p($aaiseo_dir);
            
            // Create .htaccess to protect directory
            $htaccess_content = "Order Deny,Allow\nDeny from all";
            file_put_contents($aaiseo_dir . '/.htaccess', $htaccess_content);
        }
    }
    
    /**
     * Schedule cron events
     */
    private static function schedule_events() {
        // Schedule cache cleanup (daily)
        if (!wp_next_scheduled('aaiseo_cleanup_cache')) {
            wp_schedule_event(time(), 'daily', 'aaiseo_cleanup_cache');
        }
        
        // Schedule API usage reset (hourly)
        if (!wp_next_scheduled('aaiseo_reset_api_usage')) {
            wp_schedule_event(time(), 'hourly', 'aaiseo_reset_api_usage');
        }
        
        // Schedule weekly optimization report generation
        if (!wp_next_scheduled('aaiseo_generate_reports')) {
            wp_schedule_event(time(), 'weekly', 'aaiseo_generate_reports');
        }
    }
}