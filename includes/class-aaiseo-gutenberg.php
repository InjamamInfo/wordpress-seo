<?php
/**
 * Gutenberg Block Integration for Autonomous AI SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAISEO_Gutenberg {
    
    public function __construct() {
        add_action('init', array($this, 'register_blocks'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_assets'));
        add_action('wp_ajax_aaiseo_analyze_content', array($this, 'ajax_analyze_content'));
        add_action('wp_ajax_aaiseo_generate_suggestions', array($this, 'ajax_generate_suggestions'));
        add_action('wp_ajax_aaiseo_research_keywords', array($this, 'ajax_research_keywords'));
    }
    
    /**
     * Register Gutenberg blocks
     */
    public function register_blocks() {
        // Register SEO Analysis Block
        register_block_type('aaiseo/seo-analysis', array(
            'attributes' => array(
                'content' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'keywords' => array(
                    'type' => 'string',
                    'default' => ''
                )
            ),
            'render_callback' => array($this, 'render_seo_analysis_block'),
            'editor_script' => 'aaiseo-blocks'
        ));
        
        // Register Content Suggestions Block
        register_block_type('aaiseo/content-suggestions', array(
            'attributes' => array(
                'topic' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'targetAudience' => array(
                    'type' => 'string',
                    'default' => ''
                )
            ),
            'render_callback' => array($this, 'render_suggestions_block'),
            'editor_script' => 'aaiseo-blocks'
        ));
        
        // Register Keyword Research Block
        register_block_type('aaiseo/keyword-research', array(
            'attributes' => array(
                'primaryKeyword' => array(
                    'type' => 'string',
                    'default' => ''
                )
            ),
            'render_callback' => array($this, 'render_keyword_block'),
            'editor_script' => 'aaiseo-blocks'
        ));
    }
    
    /**
     * Enqueue block editor assets
     */
    public function enqueue_block_assets() {
        wp_enqueue_script(
            'aaiseo-blocks',
            AAISEO_PLUGIN_URL . 'assets/js/blocks.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            AAISEO_PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_style(
            'aaiseo-blocks',
            AAISEO_PLUGIN_URL . 'assets/css/blocks.css',
            array('wp-edit-blocks'),
            AAISEO_PLUGIN_VERSION
        );
        
        // Localize script for AJAX
        wp_localize_script('aaiseo-blocks', 'aaiseoBlocks', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aaiseo_blocks_nonce'),
            'strings' => array(
                'analyzing' => __('Analyzing content...', 'autonomous-ai-seo'),
                'error' => __('Analysis failed. Please try again.', 'autonomous-ai-seo'),
                'noContent' => __('Please add some content to analyze.', 'autonomous-ai-seo')
            )
        ));
    }
    
    /**
     * Render SEO Analysis Block
     */
    public function render_seo_analysis_block($attributes) {
        // This block is editor-only, no frontend rendering
        return '';
    }
    
    /**
     * Render Content Suggestions Block
     */
    public function render_suggestions_block($attributes) {
        // This block is editor-only, no frontend rendering
        return '';
    }
    
    /**
     * Render Keyword Research Block
     */
    public function render_keyword_block($attributes) {
        // This block is editor-only, no frontend rendering
        return '';
    }
    
    /**
     * AJAX handler for content analysis
     */
    public function ajax_analyze_content() {
        check_ajax_referer('aaiseo_blocks_nonce', 'nonce');
        
        $content = wp_unslash($_POST['content']);
        $keywords = isset($_POST['keywords']) ? explode(',', sanitize_text_field($_POST['keywords'])) : array();
        
        if (empty($content)) {
            wp_send_json_error(__('No content provided for analysis.', 'autonomous-ai-seo'));
        }
        
        $ai_engine = AAISEO_AI_Engine::getInstance();
        $analysis = $ai_engine->analyzeContent($content, $keywords);
        
        if (is_wp_error($analysis)) {
            wp_send_json_error($analysis->get_error_message());
        }
        
        // Parse JSON if it's a string
        if (is_string($analysis)) {
            $analysis = json_decode($analysis, true);
        }
        
        wp_send_json_success($analysis);
    }
    
    /**
     * AJAX handler for content suggestions
     */
    public function ajax_generate_suggestions() {
        check_ajax_referer('aaiseo_blocks_nonce', 'nonce');
        
        $topic = sanitize_text_field($_POST['topic']);
        $target_audience = sanitize_text_field($_POST['target_audience']);
        
        if (empty($topic)) {
            wp_send_json_error(__('Please provide a topic.', 'autonomous-ai-seo'));
        }
        
        $ai_engine = AAISEO_AI_Engine::getInstance();
        $outline = $ai_engine->generateContentOutline($topic, $target_audience);
        
        if (is_wp_error($outline)) {
            wp_send_json_error($outline->get_error_message());
        }
        
        wp_send_json_success($outline);
    }
    
    /**
     * AJAX handler for keyword research
     */
    public function ajax_research_keywords() {
        check_ajax_referer('aaiseo_blocks_nonce', 'nonce');
        
        $primary_keyword = sanitize_text_field($_POST['primary_keyword']);
        $content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
        
        if (empty($primary_keyword)) {
            wp_send_json_error(__('Please provide a primary keyword.', 'autonomous-ai-seo'));
        }
        
        $ai_engine = AAISEO_AI_Engine::getInstance();
        $keywords = $ai_engine->analyzeSemanticKeywords($content, $primary_keyword);
        
        if (is_wp_error($keywords)) {
            wp_send_json_error($keywords->get_error_message());
        }
        
        // Parse JSON if it's a string
        if (is_string($keywords)) {
            $keywords = json_decode($keywords, true);
        }
        
        wp_send_json_success($keywords);
    }
}

// Initialize Gutenberg integration
new AAISEO_Gutenberg();