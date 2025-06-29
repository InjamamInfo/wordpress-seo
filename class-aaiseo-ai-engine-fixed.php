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
     * Generate content outline with title suggestions and meta description
     */
    public function generateContentOutline($topic, $target_audience = '') {
        // Get active provider
        $provider_status = $this->getAPIProviderStatus();
        $provider = $provider_status['active_provider'];
        
        // If using internal, use simplified generation
        if ($provider === 'internal') {
            return $this->generateInternalContentOutline($topic, $target_audience);
        }
        
        // Check cache first
        $cache_key = 'aaiseo_content_outline_' . md5($topic . $target_audience . $provider);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $prompt = $this->buildContentOutlinePrompt($topic, $target_audience);
        $system_prompt = 'You are an expert content strategist and SEO copywriter. Generate comprehensive content outlines with SEO-optimized titles and meta descriptions. Provide results in JSON format.';
        
        $response = $this->makeAIRequest($prompt, $system_prompt, $provider);
        
        if (is_wp_error($response)) {
            // Fall back to internal generation
            return $this->generateInternalContentOutline($topic, $target_audience);
        }
        
        // Try to parse the response
        $parsed = json_decode($response, true);
        
        if (!$parsed) {
            // Try to extract JSON from the response
            preg_match('/```json\s*(.*?)\s*```/s', $response, $matches);
            if (!empty($matches[1])) {
                $parsed = json_decode($matches[1], true);
            }
            
            if (!$parsed) {
                // Fall back to internal generation
                return $this->generateInternalContentOutline($topic, $target_audience);
            }
        }
        
        // Cache the result
        set_transient($cache_key, $parsed, $this->cache_duration);
        
        return $parsed;
    }
    
    /**
     * Build content outline prompt
     */
    private function buildContentOutlinePrompt($topic, $target_audience) {
        $audience_text = !empty($target_audience) ? " for {$target_audience}" : '';
        
        return "Create a comprehensive content outline for the topic: \"{$topic}\"{$audience_text}

Please provide the response in this exact JSON format:
{
  \"title_suggestions\": [
    \"SEO-optimized title 1\",
    \"SEO-optimized title 2\",
    \"SEO-optimized title 3\",
    \"SEO-optimized title 4\",
    \"SEO-optimized title 5\"
  ],
  \"outline\": [
    {
      \"heading\": \"Introduction to {$topic}\",
      \"subheadings\": [
        \"What is {$topic}?\",
        \"Why {$topic} matters\",
        \"Benefits of understanding {$topic}\"
      ]
    },
    {
      \"heading\": \"Key Concepts and Fundamentals\",
      \"subheadings\": [
        \"Core principles\",
        \"Common misconceptions\",
        \"Best practices\"
      ]
    },
    {
      \"heading\": \"Practical Applications\",
      \"subheadings\": [
        \"Real-world examples\",
        \"Step-by-step guide\",
        \"Tools and resources\"
      ]
    },
    {
      \"heading\": \"Advanced Strategies\",
      \"subheadings\": [
        \"Expert techniques\",
        \"Common pitfalls to avoid\",
        \"Future trends\"
      ]
    },
    {
      \"heading\": \"Conclusion\",
      \"subheadings\": [
        \"Key takeaways\",
        \"Next steps\",
        \"Additional resources\"
      ]
    }
  ],
  \"meta_description\": \"Comprehensive guide to {$topic}. Learn key concepts, practical applications, and expert strategies. Perfect for {$target_audience} looking to master this topic.\"
}";
    }
    
    /**
     * Generate internal content outline (fallback)
     */
    private function generateInternalContentOutline($topic, $target_audience = '') {
        $audience_text = !empty($target_audience) ? " for {$target_audience}" : '';
        
        $title_suggestions = array(
            "Complete Guide to {$topic}: Everything You Need to Know",
            "Mastering {$topic}: A Comprehensive Tutorial",
            "The Ultimate {$topic} Guide{$audience_text}",
            "{$topic} Explained: Tips, Tricks, and Best Practices",
            "How to Get Started with {$topic}: A Beginner's Guide"
        );
        
        $outline = array(
            array(
                'heading' => "Introduction to {$topic}",
                'subheadings' => array(
                    "What is {$topic}?",
                    "Why {$topic} matters",
                    "Who should learn about {$topic}?"
                )
            ),
            array(
                'heading' => "Getting Started with {$topic}",
                'subheadings' => array(
                    "Basic concepts and terminology",
                    "Essential tools and resources",
                    "Common misconceptions"
                )
            ),
            array(
                'heading' => "Practical Applications of {$topic}",
                'subheadings' => array(
                    "Real-world examples",
                    "Step-by-step implementation",
                    "Case studies and success stories"
                )
            ),
            array(
                'heading' => "Advanced {$topic} Strategies",
                'subheadings' => array(
                    "Expert tips and techniques",
                    "Troubleshooting common issues",
                    "Optimization and best practices"
                )
            ),
            array(
                'heading' => "Conclusion and Next Steps",
                'subheadings' => array(
                    "Key takeaways",
                    "Recommended next steps",
                    "Additional learning resources"
                )
            )
        );
        
        $meta_description = "Comprehensive guide to {$topic}. Learn essential concepts, practical applications, and expert strategies{$audience_text}. Start your journey today!";
        
        return array(
            'title_suggestions' => $title_suggestions,
            'outline' => $outline,
            'meta_description' => $meta_description,
            'generated_by' => 'internal'
        );
    }
    
    /**
     * Generate meta description variations
     */
    public function generateMetaDescription($title, $content, $keywords = array()) {
        // Get active provider
        $provider_status = $this->getAPIProviderStatus();
        $provider = $provider_status['active_provider'];
        
        // If using internal, use simplified generation
        if ($provider === 'internal') {
            return $this->generateInternalMetaDescription($title, $content, $keywords);
        }
        
        // Check cache first
        $cache_key = 'aaiseo_meta_desc_' . md5($title . substr($content, 0, 500) . serialize($keywords) . $provider);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $prompt = $this->buildMetaDescriptionPrompt($title, $content, $keywords);
        $system_prompt = 'You are an expert SEO copywriter specializing in creating compelling meta descriptions that drive clicks and improve search rankings. Provide results in JSON format.';
        
        $response = $this->makeAIRequest($prompt, $system_prompt, $provider);
        
        if (is_wp_error($response)) {
            // Fall back to internal generation
            return $this->generateInternalMetaDescription($title, $content, $keywords);
        }
        
        // Try to parse the response
        $parsed = json_decode($response, true);
        
        if (!$parsed) {
            // Try to extract JSON from the response
            preg_match('/```json\s*(.*?)\s*```/s', $response, $matches);
            if (!empty($matches[1])) {
                $parsed = json_decode($matches[1], true);
            }
            
            if (!$parsed) {
                // Fall back to internal generation
                return $this->generateInternalMetaDescription($title, $content, $keywords);
            }
        }
        
        // Cache the result
        set_transient($cache_key, $parsed, $this->cache_duration);
        
        return $parsed;
    }
    
    /**
     * Build meta description prompt
     */
    private function buildMetaDescriptionPrompt($title, $content, $keywords) {
        $content_excerpt = wp_trim_words(strip_tags($content), 100);
        $keywords_text = !empty($keywords) ? 'Target keywords: ' . implode(', ', $keywords) . "\n" : '';
        
        return "Create compelling meta descriptions for this content:

Title: {$title}
{$keywords_text}
Content excerpt: {$content_excerpt}

Requirements:
- Length: 150-160 characters
- Include target keywords naturally
- Write compelling copy that encourages clicks
- Accurately describe the content
- Use active voice when possible

Please provide 5 variations in this JSON format:
{
  \"variations\": [
    {
      \"description\": \"Meta description text here\",
      \"length\": 155,
      \"keywords_included\": [\"keyword1\", \"keyword2\"]
    }
  ]
}";
    }
    
    /**
     * Generate internal meta description (fallback)
     */
    private function generateInternalMetaDescription($title, $content, $keywords = array()) {
        $content_words = wp_trim_words(strip_tags($content), 20);
        $keyword_phrase = !empty($keywords) ? implode(' and ', array_slice($keywords, 0, 2)) : '';
        
        $variations = array();
        
        // Variation 1: Direct approach
        $desc1 = "Learn about {$title}.";
        if (!empty($keyword_phrase)) {
            $desc1 .= " Discover {$keyword_phrase} and more.";
        }
        $desc1 .= " " . wp_trim_words($content_words, 12);
        $variations[] = array(
            'description' => substr($desc1, 0, 160),
            'length' => strlen(substr($desc1, 0, 160)),
            'keywords_included' => array_slice($keywords, 0, 2)
        );
        
        // Variation 2: Question approach
        $desc2 = "Looking for information about {$title}?";
        if (!empty($keyword_phrase)) {
            $desc2 .= " Get insights on {$keyword_phrase} and practical tips.";
        } else {
            $desc2 .= " Get comprehensive insights and practical tips.";
        }
        $variations[] = array(
            'description' => substr($desc2, 0, 160),
            'length' => strlen(substr($desc2, 0, 160)),
            'keywords_included' => array_slice($keywords, 0, 2)
        );
        
        // Variation 3: Benefit-focused
        $desc3 = "Master {$title} with our comprehensive guide.";
        if (!empty($keyword_phrase)) {
            $desc3 .= " Learn {$keyword_phrase} techniques that work.";
        } else {
            $desc3 .= " Learn proven techniques and best practices.";
        }
        $variations[] = array(
            'description' => substr($desc3, 0, 160),
            'length' => strlen(substr($desc3, 0, 160)),
            'keywords_included' => array_slice($keywords, 0, 2)
        );
        
        // Variation 4: Action-oriented
        $desc4 = "Discover everything about {$title}.";
        if (!empty($keyword_phrase)) {
            $desc4 .= " Expert advice on {$keyword_phrase} and more.";
        } else {
            $desc4 .= " Expert advice and actionable strategies.";
        }
        $variations[] = array(
            'description' => substr($desc4, 0, 160),
            'length' => strlen(substr($desc4, 0, 160)),
            'keywords_included' => array_slice($keywords, 0, 2)
        );
        
        // Variation 5: Simple and direct
        $desc5 = "Complete guide to {$title}. ";
        if (!empty($keyword_phrase)) {
            $desc5 .= "Includes {$keyword_phrase} tips and strategies. ";
        }
        $desc5 .= "Start learning today!";
        $variations[] = array(
            'description' => substr($desc5, 0, 160),
            'length' => strlen(substr($desc5, 0, 160)),
            'keywords_included' => array_slice($keywords, 0, 2)
        );
        
        return array(
            'variations' => $variations,
            'generated_by' => 'internal'
        );
    }
    
    /**
     * Analyze semantic keywords and LSI keywords
     */
    public function analyzeSemanticKeywords($content, $primary_keyword) {
        // Get active provider
        $provider_status = $this->getAPIProviderStatus();
        $provider = $provider_status['active_provider'];
        
        // If using internal, use simplified analysis
        if ($provider === 'internal') {
            return $this->generateInternalKeywordSuggestions($primary_keyword);
        }
        
        // Check cache first
        $cache_key = 'aaiseo_semantic_keywords_' . md5($content . $primary_keyword . $provider);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $prompt = $this->buildSemanticKeywordsPrompt($content, $primary_keyword);
        $system_prompt = 'You are an expert SEO keyword researcher with deep knowledge of semantic search and LSI keywords. Provide comprehensive keyword analysis in JSON format.';
        
        $response = $this->makeAIRequest($prompt, $system_prompt, $provider);
        
        if (is_wp_error($response)) {
            // Fall back to internal analysis
            return $this->generateInternalKeywordSuggestions($primary_keyword);
        }
        
        // Try to parse the response
        $parsed = json_decode($response, true);
        
        if (!$parsed) {
            // Try to extract JSON from the response
            preg_match('/```json\s*(.*?)\s*```/s', $response, $matches);
            if (!empty($matches[1])) {
                $parsed = json_decode($matches[1], true);
            }
            
            if (!$parsed) {
                // Fall back to internal analysis
                return $this->generateInternalKeywordSuggestions($primary_keyword);
            }
        }
        
        // Cache the result
        set_transient($cache_key, $parsed, $this->cache_duration);
        
        return $parsed;
    }
    
    /**
     * Build semantic keywords prompt
     */
    private function buildSemanticKeywordsPrompt($content, $primary_keyword) {
        $content_excerpt = wp_trim_words(strip_tags($content), 200);
        
        return "Analyze the following content and primary keyword to generate comprehensive keyword suggestions:

Primary Keyword: \"{$primary_keyword}\"
Content: {$content_excerpt}

Please provide keyword analysis in this JSON format:
{
  \"primary_keyword\": \"{$primary_keyword}\",
  \"semantic_keywords\": [
    \"Related semantic variations of the primary keyword\",
    \"Contextually related terms\",
    \"Synonyms and variations\"
  ],
  \"long_tail_variations\": [
    \"Longer phrases containing the primary keyword\",
    \"Question-based long-tail keywords\",
    \"How-to and informational long-tail keywords\"
  ],
  \"lsi_keywords\": [
    \"Latent Semantic Indexing keywords\",
    \"Topic-related terms that support the primary keyword\",
    \"Contextually relevant supporting keywords\"
  ],
  \"keyword_difficulty\": {
    \"primary\": \"estimated difficulty level (easy/medium/hard)\",
    \"explanation\": \"Brief explanation of why this difficulty level\"
  },
  \"search_intent\": {
    \"type\": \"informational/commercial/navigational/transactional\",
    \"explanation\": \"What users are looking for with this keyword\"
  }
}";
    }
    
    /**
     * Generate internal keyword suggestions
     */
    private function generateInternalKeywordSuggestions($prompt) {
        // Extract primary keyword from prompt
        $primary_keyword = trim($prompt);
        
        // Generate basic variations
        $variations = array(
            $primary_keyword . ' guide',
            $primary_keyword . ' tutorial',
            $primary_keyword . ' tips',
            $primary_keyword . ' examples',
            $primary_keyword . ' best practices',
            'how to ' . $primary_keyword,
            'what is ' . $primary_keyword,
            $primary_keyword . ' for beginners',
            $primary_keyword . ' explained',
            $primary_keyword . ' strategies'
        );
        
        return json_encode(array(
            'primary_keyword' => $primary_keyword,
            'semantic_keywords' => array_slice($variations, 0, 5),
            'long_tail_variations' => array_slice($variations, 5),
            'lsi_keywords' => array(
                $primary_keyword . ' guide',
                $primary_keyword . ' tutorial',
                $primary_keyword . ' tips',
                $primary_keyword . ' best practices',
                $primary_keyword . ' examples'
            ),
            'keyword_difficulty' => array(
                'primary' => 'medium',
                'explanation' => 'Difficulty estimated based on keyword length and specificity'
            ),
            'search_intent' => array(
                'type' => 'informational',
                'explanation' => 'Users are likely seeking to learn about this topic'
            ),
            'generated_by' => 'internal'
        ));
    }
    
    /**
     * Generate SEO recommendations
     */
    public function generateSEORecommendations($content, $title = '', $meta_description = '') {
        $recommendations = array();
        
        // Analyze content length
        $word_count = str_word_count(strip_tags($content));
        if ($word_count < 300) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => 'Content is too short. Aim for at least 300 words for better SEO.',
                'priority' => 'high'
            );
        } elseif ($word_count > 2000) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => 'Consider breaking this long content into multiple pages or sections.',
                'priority' => 'medium'
            );
        }
        
        // Check title length
        if (!empty($title)) {
            $title_length = strlen($title);
            if ($title_length < 30) {
                $recommendations[] = array(
                    'type' => 'warning',
                    'message' => 'Title is too short. Aim for 30-60 characters.',
                    'priority' => 'high'
                );
            } elseif ($title_length > 60) {
                $recommendations[] = array(
                    'type' => 'warning',
                    'message' => 'Title is too long. Keep it under 60 characters.',
                    'priority' => 'high'
                );
            }
        }
        
        // Check meta description length
        if (!empty($meta_description)) {
            $meta_length = strlen($meta_description);
            if ($meta_length < 120) {
                $recommendations[] = array(
                    'type' => 'warning',
                    'message' => 'Meta description is too short. Aim for 120-160 characters.',
                    'priority' => 'medium'
                );
            } elseif ($meta_length > 160) {
                $recommendations[] = array(
                    'type' => 'warning',
                    'message' => 'Meta description is too long. Keep it under 160 characters.',
                    'priority' => 'medium'
                );
            }
        }
        
        // Check for headings
        $heading_count = substr_count($content, '<h');
        if ($heading_count < 2) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => 'Add more headings (H2, H3) to improve content structure.',
                'priority' => 'medium'
            );
        }
        
        // Check for images
        $image_count = substr_count($content, '<img');
        if ($image_count === 0) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => 'Consider adding relevant images to enhance user experience.',
                'priority' => 'low'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Make AI request (public method to allow testing)
     */
    public function makeAIRequest($prompt, $system_prompt = '', $provider = null) {
        return $this->makeAIRequest($prompt, $system_prompt, $provider);
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