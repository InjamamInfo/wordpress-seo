<?php
/**
 * Plugin Name: Autonomous AI SEO
 * Plugin URI: https://your-domain.com/autonomous-ai-seo
 * Description: An advanced SEO plugin powered by multiple AI providers for autonomous content optimization.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://your-domain.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: autonomous-ai-seo
 * Domain Path: /languages
 * 
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.0
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AAISEO_PLUGIN_VERSION', '1.0.0');
define('AAISEO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AAISEO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('AAISEO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check PHP version compatibility
if (version_compare(PHP_VERSION, '7.0', '<')) {
    add_action('admin_notices', 'aaiseo_php_version_notice');
    return;
}

/**
 * Display PHP version notice
 */
function aaiseo_php_version_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <strong>Autonomous AI SEO</strong> requires PHP version 7.0 or higher. 
            You are running PHP <?php echo PHP_VERSION; ?>. 
            Please upgrade your PHP version or contact your hosting provider.
        </p>
    </div>
    <?php
}

// Main plugin class
class Autonomous_AI_SEO {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize components
        $this->init_admin();
        $this->init_frontend();
        
        // Hook into WordPress
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes
        require_once AAISEO_PLUGIN_PATH . 'includes/class-aaiseo-ai-engine.php';
        
        // Admin classes
        if (is_admin()) {
            require_once AAISEO_PLUGIN_PATH . 'admin/class-aaiseo-admin.php';
            require_once AAISEO_PLUGIN_PATH . 'admin/class-aaiseo-settings.php';
        }
        
        // Frontend classes
        require_once AAISEO_PLUGIN_PATH . 'public/class-aaiseo-public.php';
    }
    
    /**
     * Initialize admin functionality
     */
    private function init_admin() {
        if (is_admin()) {
            // Initialize admin classes here
        }
    }
    
    /**
     * Initialize frontend functionality
     */
    private function init_frontend() {
        if (!is_admin()) {
            // Initialize frontend classes here
        }
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'autonomous-ai-seo',
            false,
            dirname(AAISEO_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_frontend_scripts() {
        // Enqueue frontend CSS
        wp_enqueue_style(
            'aaiseo-frontend',
            AAISEO_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            AAISEO_PLUGIN_VERSION
        );
        
        // Enqueue frontend JS
        wp_enqueue_script(
            'aaiseo-frontend',
            AAISEO_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            AAISEO_PLUGIN_VERSION,
            true
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook_suffix) {
        // Only load on plugin pages
        if (strpos($hook_suffix, 'aaiseo') === false) {
            return;
        }
        
        // Enqueue admin CSS
        wp_enqueue_style(
            'aaiseo-admin',
            AAISEO_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            AAISEO_PLUGIN_VERSION
        );
        
        // Enqueue admin JS
        wp_enqueue_script(
            'aaiseo-admin',
            AAISEO_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            AAISEO_PLUGIN_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script(
            'aaiseo-admin',
            'aaiseo_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aaiseo_nonce'),
                'plugin_url' => AAISEO_PLUGIN_URL
            )
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables if needed
        $this->create_database_tables();
        
        // Set default options
        $default_options = array(
            'plugin_version' => AAISEO_PLUGIN_VERSION,
            'preferred_ai_provider' => 'internal',
            'openai_api_key' => '',
            'grok_api_key' => '',
            'gemini_api_key' => '',
            'deepseek_api_key' => '',
            'auto_optimize' => false,
            'cache_duration' => 3600
        );
        
        add_option('aaiseo_settings', $default_options);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear caches
        $this->clear_plugin_caches();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // SEO Reports table
        $table_name = $wpdb->prefix . 'aaiseo_reports';
        
        $sql = "CREATE TABLE $table_name (
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
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
    }
    
    /**
     * Clear plugin caches
     */
    private function clear_plugin_caches() {
        // Clear WordPress transients
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_aaiseo_%' 
             OR option_name LIKE '_transient_timeout_aaiseo_%'"
        );
        
        // Clear custom cache table
        $cache_table = $wpdb->prefix . 'aaiseo_cache';
        $wpdb->query("TRUNCATE TABLE $cache_table");
    }
}

// Initialize the plugin
function aaiseo_init() {
    return Autonomous_AI_SEO::get_instance();
}

// Start the plugin
aaiseo_init();