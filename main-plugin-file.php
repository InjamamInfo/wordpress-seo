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
        // Core classes - using the actual file name
        require_once AAISEO_PLUGIN_PATH . 'class-aaiseo-ai-engine-fixed.php';
        
        // Load admin interface
        if (is_admin() && file_exists(AAISEO_PLUGIN_PATH . 'basic-admin-interface.php')) {
            require_once AAISEO_PLUGIN_PATH . 'basic-admin-interface.php';
        }
        
        // Load activation/deactivation handlers
        if (file_exists(AAISEO_PLUGIN_PATH . 'includes/class-aaiseo-activation.php')) {
            require_once AAISEO_PLUGIN_PATH . 'includes/class-aaiseo-activation.php';
        }
        
        if (file_exists(AAISEO_PLUGIN_PATH . 'includes/class-aaiseo-deactivation.php')) {
            require_once AAISEO_PLUGIN_PATH . 'includes/class-aaiseo-deactivation.php';
        }
        
        // Only load additional classes if they exist
        if (is_admin() && file_exists(AAISEO_PLUGIN_PATH . 'admin/class-aaiseo-admin.php')) {
            require_once AAISEO_PLUGIN_PATH . 'admin/class-aaiseo-admin.php';
        }
        
        if (is_admin() && file_exists(AAISEO_PLUGIN_PATH . 'admin/class-aaiseo-settings.php')) {
            require_once AAISEO_PLUGIN_PATH . 'admin/class-aaiseo-settings.php';
        }
        
        if (file_exists(AAISEO_PLUGIN_PATH . 'public/class-aaiseo-public.php')) {
            require_once AAISEO_PLUGIN_PATH . 'public/class-aaiseo-public.php';
        }
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
        // Only enqueue if files exist
        if (file_exists(AAISEO_PLUGIN_PATH . 'assets/css/frontend.css')) {
            wp_enqueue_style(
                'aaiseo-frontend',
                AAISEO_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                AAISEO_PLUGIN_VERSION
            );
        }
        
        if (file_exists(AAISEO_PLUGIN_PATH . 'assets/js/frontend.js')) {
            wp_enqueue_script(
                'aaiseo-frontend',
                AAISEO_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                AAISEO_PLUGIN_VERSION,
                true
            );
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook_suffix) {
        // Only load on plugin pages
        if (strpos($hook_suffix, 'aaiseo') === false) {
            return;
        }
        
        // Only enqueue if files exist
        if (file_exists(AAISEO_PLUGIN_PATH . 'assets/css/admin.css')) {
            wp_enqueue_style(
                'aaiseo-admin',
                AAISEO_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                AAISEO_PLUGIN_VERSION
            );
        }
        
        if (file_exists(AAISEO_PLUGIN_PATH . 'assets/js/admin.js')) {
            wp_enqueue_script(
                'aaiseo-admin',
                AAISEO_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                AAISEO_PLUGIN_VERSION,
                true
            );
        }
        
        // Only localize if the script was enqueued
        if (wp_script_is('aaiseo-admin', 'enqueued')) {
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
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        if (class_exists('AAISEO_Activation')) {
            AAISEO_Activation::activate();
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        if (class_exists('AAISEO_Deactivation')) {
            AAISEO_Deactivation::deactivate();
        }
    }
}

// Initialize the plugin
function aaiseo_init() {
    return Autonomous_AI_SEO::get_instance();
}

// Start the plugin
aaiseo_init();