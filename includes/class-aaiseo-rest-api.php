<?php
/**
 * REST API Endpoints for Autonomous AI SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAISEO_REST_API {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        register_rest_route('aaiseo/v1', '/analyze', array(
            'methods' => 'POST',
            'callback' => array($this, 'analyze_content'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'content' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'wp_kses_post'
                ),
                'keywords' => array(
                    'required' => false,
                    'type' => 'array',
                    'default' => array()
                ),
                'context' => array(
                    'required' => false,
                    'type' => 'object',
                    'default' => array()
                )
            )
        ));
        
        register_rest_route('aaiseo/v1', '/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_content'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'prompt' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_textarea_field'
                ),
                'type' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'article',
                    'enum' => array('article', 'meta_description', 'title', 'outline')
                )
            )
        ));
        
        register_rest_route('aaiseo/v1', '/optimize', array(
            'methods' => 'POST',
            'callback' => array($this, 'optimize_content'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'post_id' => array(
                    'required' => true,
                    'type' => 'integer',
                    'validate_callback' => array($this, 'validate_post_id')
                ),
                'optimization_type' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => 'full',
                    'enum' => array('full', 'content_only', 'meta_only', 'keywords_only')
                )
            )
        ));
        
        register_rest_route('aaiseo/v1', '/keywords', array(
            'methods' => 'POST',
            'callback' => array($this, 'research_keywords'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'primary_keyword' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'content' => array(
                    'required' => false,
                    'type' => 'string',
                    'default' => ''
                )
            )
        ));
        
        register_rest_route('aaiseo/v1', '/reports', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_reports'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'post_id' => array(
                    'required' => false,
                    'type' => 'integer'
                ),
                'report_type' => array(
                    'required' => false,
                    'type' => 'string'
                ),
                'limit' => array(
                    'required' => false,
                    'type' => 'integer',
                    'default' => 10
                )
            )
        ));
    }
    
    /**
     * Check user permissions
     */
    public function check_permissions() {
        return current_user_can('edit_posts');
    }
    
    /**
     * Validate post ID
     */
    public function validate_post_id($param, $request, $key) {
        return is_numeric($param) && get_post($param) !== null;
    }
    
    /**
     * Analyze content endpoint
     */
    public function analyze_content($request) {
        $content = $request->get_param('content');
        $keywords = $request->get_param('keywords');
        $context = $request->get_param('context');
        
        try {
            $ai_engine = AAISEO_AI_Engine::getInstance();
            $analysis = $ai_engine->analyzeContent($content, $keywords, $context);
            
            if (is_wp_error($analysis)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => $analysis->get_error_message()
                ), 400);
            }
            
            // Parse JSON response if needed
            if (is_string($analysis)) {
                $analysis = json_decode($analysis, true);
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $analysis,
                'timestamp' => current_time('mysql')
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Analysis failed: ', 'autonomous-ai-seo') . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Generate content endpoint
     */
    public function generate_content($request) {
        $prompt = $request->get_param('prompt');
        $type = $request->get_param('type');
        
        try {
            $ai_engine = AAISEO_AI_Engine::getInstance();
            
            switch ($type) {
                case 'article':
                    $result = $ai_engine->generateArticleContent($prompt);
                    break;
                    
                case 'meta_description':
                    // Extract title and keywords from prompt for meta description
                    $result = $ai_engine->generateMetaDescription($prompt, '', array());
                    break;
                    
                case 'outline':
                    // Parse prompt for topic and audience
                    $result = $ai_engine->generateContentOutline($prompt, 'general audience');
                    break;
                    
                default:
                    $result = $ai_engine->generateArticleContent($prompt);
            }
            
            if (is_wp_error($result)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => $result->get_error_message()
                ), 400);
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $result,
                'type' => $type,
                'timestamp' => current_time('mysql')
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Generation failed: ', 'autonomous-ai-seo') . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Optimize content endpoint
     */
    public function optimize_content($request) {
        $post_id = $request->get_param('post_id');
        $optimization_type = $request->get_param('optimization_type');
        
        try {
            $post = get_post($post_id);
            if (!$post) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => __('Post not found', 'autonomous-ai-seo')
                ), 404);
            }
            
            $ai_engine = AAISEO_AI_Engine::getInstance();
            $optimizations = array();
            
            switch ($optimization_type) {
                case 'full':
                    // Analyze content
                    $content_analysis = $ai_engine->analyzeContent($post->post_content);
                    
                    // Generate meta description if missing
                    $meta_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                    if (empty($meta_description)) {
                        $meta_result = $ai_engine->generateMetaDescription(
                            $post->post_title, 
                            $post->post_content
                        );
                        
                        if (!is_wp_error($meta_result) && is_array($meta_result) && isset($meta_result['variations'][0])) {
                            $optimizations['meta_description'] = $meta_result['variations'][0]['description'];
                        }
                    }
                    
                    $optimizations['content_analysis'] = $content_analysis;
                    break;
                    
                case 'content_only':
                    $optimizations['content_analysis'] = $ai_engine->analyzeContent($post->post_content);
                    break;
                    
                case 'meta_only':
                    $meta_result = $ai_engine->generateMetaDescription(
                        $post->post_title, 
                        $post->post_content
                    );
                    
                    if (!is_wp_error($meta_result) && is_array($meta_result) && isset($meta_result['variations'][0])) {
                        $optimizations['meta_description'] = $meta_result['variations'][0]['description'];
                    }
                    break;
                    
                case 'keywords_only':
                    $keyword_result = $ai_engine->analyzeSemanticKeywords(
                        $post->post_content, 
                        $post->post_title
                    );
                    
                    $optimizations['keywords'] = $keyword_result;
                    break;
            }
            
            // Save optimization report
            $this->save_optimization_report($post_id, $optimizations);
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $optimizations,
                'post_id' => $post_id,
                'optimization_type' => $optimization_type,
                'timestamp' => current_time('mysql')
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Optimization failed: ', 'autonomous-ai-seo') . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Research keywords endpoint
     */
    public function research_keywords($request) {
        $primary_keyword = $request->get_param('primary_keyword');
        $content = $request->get_param('content');
        
        try {
            $ai_engine = AAISEO_AI_Engine::getInstance();
            $keywords = $ai_engine->analyzeSemanticKeywords($content, $primary_keyword);
            
            if (is_wp_error($keywords)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => $keywords->get_error_message()
                ), 400);
            }
            
            // Parse JSON response if needed
            if (is_string($keywords)) {
                $keywords = json_decode($keywords, true);
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $keywords,
                'primary_keyword' => $primary_keyword,
                'timestamp' => current_time('mysql')
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Keyword research failed: ', 'autonomous-ai-seo') . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Get reports endpoint
     */
    public function get_reports($request) {
        global $wpdb;
        
        $post_id = $request->get_param('post_id');
        $report_type = $request->get_param('report_type');
        $limit = $request->get_param('limit');
        
        try {
            $reports_table = $wpdb->prefix . 'aaiseo_reports';
            
            // Check if table exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $reports_table
            ));
            
            if (!$table_exists) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => __('Reports table not found. Please reactivate the plugin.', 'autonomous-ai-seo')
                ), 500);
            }
            
            $where_clauses = array();
            $where_values = array();
            
            if ($post_id) {
                $where_clauses[] = 'post_id = %d';
                $where_values[] = $post_id;
            }
            
            if ($report_type) {
                $where_clauses[] = 'report_type = %s';
                $where_values[] = $report_type;
            }
            
            $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
            
            $sql = $wpdb->prepare(
                "SELECT * FROM $reports_table $where_sql ORDER BY created_at DESC LIMIT %d",
                array_merge($where_values, array($limit))
            );
            
            $reports = $wpdb->get_results($sql);
            
            // Check for database errors
            if ($wpdb->last_error) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => __('Database error occurred.', 'autonomous-ai-seo')
                ), 500);
            }
            
            // Parse report data
            foreach ($reports as &$report) {
                $report->report_data = json_decode($report->report_data, true);
            }
            
            return new WP_REST_Response(array(
                'success' => true,
                'data' => $reports,
                'total' => count($reports),
                'timestamp' => current_time('mysql')
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Failed to retrieve reports: ', 'autonomous-ai-seo') . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Save optimization report to database
     */
    private function save_optimization_report($post_id, $optimizations) {
        global $wpdb;
        
        $reports_table = $wpdb->prefix . 'aaiseo_reports';
        
        // Check if table exists before inserting
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $reports_table
        ));
        
        if (!$table_exists) {
            // Try to create the table
            require_once AAISEO_PLUGIN_PATH . 'includes/class-aaiseo-activation.php';
            if (class_exists('AAISEO_Activation')) {
                AAISEO_Activation::activate();
            }
        }
        
        $score = 0;
        if (isset($optimizations['content_analysis']['seo_score'])) {
            $score = intval($optimizations['content_analysis']['seo_score']);
        }
        
        $result = $wpdb->insert(
            $reports_table,
            array(
                'post_id' => $post_id,
                'report_type' => 'optimization',
                'report_data' => json_encode($optimizations),
                'score' => $score,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
        
        // Log any database errors
        if ($wpdb->last_error) {
            error_log('AAISEO Database Error: ' . $wpdb->last_error);
        }
        
        return $result !== false;
    }
}

// Initialize REST API
new AAISEO_REST_API();