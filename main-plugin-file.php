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
        
        // Initialize cron jobs
        $this->init_cron_jobs();
        
        // Hook weekly report generation
        add_action('aaiseo_generate_reports', array($this, 'generate_weekly_reports_cron'));
    }
    
    /**
     * Initialize cron job handlers
     */
    private function init_cron_jobs() {
        add_action('aaiseo_cleanup_cache', array($this, 'cleanup_cache_cron'));
        add_action('aaiseo_reset_api_usage', array($this, 'reset_api_usage_cron'));
    }
    
    /**
     * Cron job to cleanup expired cache entries
     */
    public function cleanup_cache_cron() {
        global $wpdb;
        
        try {
            // Clean up expired transients
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_timeout_aaiseo_%' 
                 AND option_value < UNIX_TIMESTAMP()"
            );
            
            // Clean up corresponding transient data
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE '_transient_aaiseo_%' 
                 AND option_name NOT IN (
                     SELECT REPLACE(option_name, '_timeout', '') 
                     FROM {$wpdb->options} 
                     WHERE option_name LIKE '_transient_timeout_aaiseo_%'
                 )"
            );
            
            // Clean up custom cache table if it exists
            $cache_table = $wpdb->prefix . 'aaiseo_cache';
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $cache_table
            ));
            
            if ($table_exists) {
                $wpdb->query(
                    "DELETE FROM $cache_table WHERE expires_at < NOW()"
                );
            }
            
            // Log successful cleanup
            if (get_option('aaiseo_settings')['enable_logging'] ?? false) {
                error_log('AAISEO: Cache cleanup completed successfully');
            }
            
        } catch (Exception $e) {
            error_log('AAISEO Cache Cleanup Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Cron job to reset API usage counters
     */
    public function reset_api_usage_cron() {
        try {
            // Reset hourly API usage counters
            delete_transient('aaiseo_api_usage_openai');
            delete_transient('aaiseo_api_usage_gemini');
            delete_transient('aaiseo_api_usage_grok');
            delete_transient('aaiseo_api_usage_deepseek');
            
            // Reset daily counters (if tracking daily limits)
            $current_date = date('Y-m-d');
            delete_option('aaiseo_daily_usage_' . $current_date);
            
            // Clean up old daily usage records (keep last 30 days)
            $cutoff_date = date('Y-m-d', strtotime('-30 days'));
            global $wpdb;
            
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} 
                     WHERE option_name LIKE 'aaiseo_daily_usage_%%' 
                     AND option_name < %s",
                    'aaiseo_daily_usage_' . $cutoff_date
                )
            );
            
            // Log successful reset
            if (get_option('aaiseo_settings')['enable_logging'] ?? false) {
                error_log('AAISEO: API usage counters reset successfully');
            }
            
        } catch (Exception $e) {
            error_log('AAISEO API Usage Reset Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get current API usage for a provider
     */
    public function get_api_usage($provider) {
        $usage = get_transient('aaiseo_api_usage_' . $provider);
        return intval($usage);
    }
    
    /**
     * Increment API usage counter for a provider
     */
    public function increment_api_usage($provider) {
        $current_usage = $this->get_api_usage($provider);
        $new_usage = $current_usage + 1;
        
        // Set transient to expire at the top of the next hour
        $next_hour = strtotime('+1 hour', strtotime(date('Y-m-d H:00:00')));
        $expire_time = $next_hour - time();
        
        set_transient('aaiseo_api_usage_' . $provider, $new_usage, $expire_time);
        
        return $new_usage;
    }
    
    /**
     * Check if API usage limit is exceeded
     */
    public function is_usage_limit_exceeded($provider) {
        $settings = get_option('aaiseo_settings', array());
        $max_requests = isset($settings['max_requests_per_hour']) ? intval($settings['max_requests_per_hour']) : 100;
        
        $current_usage = $this->get_api_usage($provider);
        
        return $current_usage >= $max_requests;
    }
    
    /**
     * Generate weekly reports (cron job)
     */
    public function generate_weekly_reports_cron() {
        try {
            global $wpdb;
            
            // Get posts that have been analyzed in the last week
            $reports_table = $wpdb->prefix . 'aaiseo_reports';
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $reports_table
            ));
            
            if (!$table_exists) {
                error_log('AAISEO Weekly Reports: Reports table not found');
                return;
            }
            
            $one_week_ago = date('Y-m-d H:i:s', strtotime('-1 week'));
            
            // Get performance data for the last week
            $recent_reports = $wpdb->get_results($wpdb->prepare(
                "SELECT post_id, AVG(score) as avg_score, COUNT(*) as analysis_count, MAX(created_at) as last_analysis
                 FROM $reports_table 
                 WHERE created_at >= %s 
                 GROUP BY post_id 
                 ORDER BY avg_score DESC 
                 LIMIT 50",
                $one_week_ago
            ));
            
            if (empty($recent_reports)) {
                error_log('AAISEO Weekly Reports: No recent reports found');
                return;
            }
            
            // Calculate statistics
            $total_posts = count($recent_reports);
            $avg_seo_score = array_sum(array_column($recent_reports, 'avg_score')) / $total_posts;
            $top_performing_posts = array_slice($recent_reports, 0, 5);
            $needs_improvement = array_filter($recent_reports, function($report) {
                return $report->avg_score < 60;
            });
            
            // Prepare report data
            $report_data = array(
                'period' => array(
                    'start' => $one_week_ago,
                    'end' => current_time('mysql')
                ),
                'summary' => array(
                    'total_posts_analyzed' => $total_posts,
                    'average_seo_score' => round($avg_seo_score, 1),
                    'posts_needing_improvement' => count($needs_improvement),
                    'improvement_percentage' => $total_posts > 0 ? round((count($needs_improvement) / $total_posts) * 100, 1) : 0
                ),
                'top_performing' => $top_performing_posts,
                'needs_improvement' => array_slice($needs_improvement, 0, 10),
                'recommendations' => $this->generate_weekly_recommendations($recent_reports)
            );
            
            // Save the weekly report
            $wpdb->insert(
                $reports_table,
                array(
                    'post_id' => 0, // 0 indicates a global report
                    'report_type' => 'weekly_summary',
                    'report_data' => json_encode($report_data),
                    'score' => round($avg_seo_score),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%d', '%s')
            );
            
            // Optionally email report to admin
            $settings = get_option('aaiseo_settings', array());
            if (isset($settings['email_weekly_reports']) && $settings['email_weekly_reports']) {
                $this->email_weekly_report($report_data);
            }
            
            // Log successful generation
            if ($settings['enable_logging'] ?? false) {
                error_log('AAISEO: Weekly report generated successfully for ' . $total_posts . ' posts');
            }
            
        } catch (Exception $e) {
            error_log('AAISEO Weekly Reports Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate recommendations for weekly report
     */
    private function generate_weekly_recommendations($reports) {
        $recommendations = array();
        
        $low_score_count = count(array_filter($reports, function($r) { return $r->avg_score < 60; }));
        $total_count = count($reports);
        
        if ($low_score_count > ($total_count * 0.5)) {
            $recommendations[] = 'More than half of your content needs SEO improvement. Focus on optimizing low-scoring posts first.';
        }
        
        $recent_analysis = array_filter($reports, function($r) {
            return strtotime($r->last_analysis) > strtotime('-3 days');
        });
        
        if (count($recent_analysis) < ($total_count * 0.3)) {
            $recommendations[] = 'Many posts haven\'t been analyzed recently. Consider running fresh analyses to get updated recommendations.';
        }
        
        $high_score_count = count(array_filter($reports, function($r) { return $r->avg_score >= 80; }));
        
        if ($high_score_count > ($total_count * 0.7)) {
            $recommendations[] = 'Excellent work! Most of your content is well-optimized. Continue maintaining this high standard.';
        }
        
        $recommendations[] = 'Review the posts marked as "needs improvement" and implement the suggested optimizations.';
        $recommendations[] = 'Consider adding more target keywords to posts with low keyword density scores.';
        
        return array_slice($recommendations, 0, 5);
    }
    
    /**
     * Email weekly report to admin
     */
    private function email_weekly_report($report_data) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf('[%s] Weekly SEO Report - %s', $site_name, date('M j, Y'));
        
        $message = sprintf(
            "Weekly SEO Report for %s\n\n" .
            "Period: %s to %s\n\n" .
            "Summary:\n" .
            "- Posts Analyzed: %d\n" .
            "- Average SEO Score: %s\n" .
            "- Posts Needing Improvement: %d (%s%%)\n\n" .
            "Top Recommendations:\n%s\n\n" .
            "View detailed reports in your WordPress admin dashboard under AI SEO.\n\n" .
            "This is an automated email from the Autonomous AI SEO plugin.",
            $site_name,
            date('M j, Y', strtotime($report_data['period']['start'])),
            date('M j, Y', strtotime($report_data['period']['end'])),
            $report_data['summary']['total_posts_analyzed'],
            $report_data['summary']['average_seo_score'],
            $report_data['summary']['posts_needing_improvement'],
            $report_data['summary']['improvement_percentage'],
            implode("\n", array_map(function($rec) { return "- {$rec}"; }, $report_data['recommendations']))
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Core classes - using the actual file name
        require_once AAISEO_PLUGIN_PATH . 'class-aaiseo-ai-engine-fixed.php';
        
        // Load new enhanced features
        if (file_exists(AAISEO_PLUGIN_PATH . 'includes/class-aaiseo-gutenberg.php')) {
            require_once AAISEO_PLUGIN_PATH . 'includes/class-aaiseo-gutenberg.php';
        }
        
        if (file_exists(AAISEO_PLUGIN_PATH . 'includes/class-aaiseo-rest-api.php')) {
            require_once AAISEO_PLUGIN_PATH . 'includes/class-aaiseo-rest-api.php';
        }
        
        if (file_exists(AAISEO_PLUGIN_PATH . 'includes/class-aaiseo-real-time.php')) {
            require_once AAISEO_PLUGIN_PATH . 'includes/class-aaiseo-real-time.php';
        }
        
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
        // Load on plugin pages and post edit pages
        $load_on_pages = array('post.php', 'post-new.php');
        $is_plugin_page = strpos($hook_suffix, 'aaiseo') !== false;
        $is_edit_page = in_array($hook_suffix, $load_on_pages);
        
        if (!$is_plugin_page && !$is_edit_page) {
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
                    'plugin_url' => AAISEO_PLUGIN_URL,
                    'strings' => array(
                        'error' => __('An error occurred. Please try again.', 'autonomous-ai-seo'),
                        'success' => __('Operation completed successfully.', 'autonomous-ai-seo'),
                        'analyzing' => __('Analyzing...', 'autonomous-ai-seo'),
                        'loading' => __('Loading...', 'autonomous-ai-seo')
                    )
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