<?php
/**
 * Real-time Content Optimization for Autonomous AI SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAISEO_Real_Time {
    
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_real_time_scripts'));
        add_action('wp_ajax_aaiseo_real_time_analyze', array($this, 'ajax_real_time_analyze'));
        add_action('wp_ajax_aaiseo_generate_suggestions', array($this, 'ajax_generate_suggestions'));
        add_filter('the_editor', array($this, 'add_seo_panel_to_editor'));
        add_action('add_meta_boxes', array($this, 'add_seo_meta_box'));
        add_action('save_post', array($this, 'save_meta_box_data'));
    }
    
    /**
     * Enqueue real-time optimization scripts
     */
    public function enqueue_real_time_scripts($hook) {
        global $post_type;
        
        // Only load on post edit screens
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        wp_enqueue_script(
            'aaiseo-real-time',
            AAISEO_PLUGIN_URL . 'assets/js/real-time.js',
            array('jquery', 'wp-util'),
            AAISEO_PLUGIN_VERSION,
            true
        );
        
        wp_enqueue_style(
            'aaiseo-real-time',
            AAISEO_PLUGIN_URL . 'assets/css/real-time.css',
            array(),
            AAISEO_PLUGIN_VERSION
        );
        
        wp_localize_script('aaiseo-real-time', 'aaiseoRealTime', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('aaiseo_real_time_nonce'),
            'postId' => isset($_GET['post']) ? intval($_GET['post']) : 0,
            'strings' => array(
                'analyzing' => __('Analyzing...', 'autonomous-ai-seo'),
                'excellent' => __('Excellent', 'autonomous-ai-seo'),
                'good' => __('Good', 'autonomous-ai-seo'),
                'needsWork' => __('Needs Work', 'autonomous-ai-seo'),
                'poor' => __('Poor', 'autonomous-ai-seo')
            )
        ));
    }
    
    /**
     * Add SEO meta box to post edit screen
     */
    public function add_seo_meta_box() {
        $post_types = get_post_types(array('public' => true), 'names');
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'aaiseo-real-time-analysis',
                __('AI SEO Analysis', 'autonomous-ai-seo'),
                array($this, 'render_seo_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render SEO meta box
     */
    public function render_seo_meta_box($post) {
        wp_nonce_field('aaiseo_meta_box', 'aaiseo_meta_box_nonce');
        
        // Get existing analysis if available
        $existing_analysis = get_post_meta($post->ID, '_aaiseo_analysis', true);
        $target_keywords = get_post_meta($post->ID, '_aaiseo_target_keywords', true);
        ?>
        <div id="aaiseo-real-time-panel">
            <div class="aaiseo-settings-section" style="margin-bottom: 15px;">
                <label for="aaiseo_target_keywords"><?php _e('Target Keywords:', 'autonomous-ai-seo'); ?></label>
                <input type="text" id="aaiseo_target_keywords" name="aaiseo_target_keywords" value="<?php echo esc_attr($target_keywords); ?>" placeholder="<?php _e('Enter keywords separated by commas', 'autonomous-ai-seo'); ?>" style="width: 100%; margin-top: 5px;" />
                <p class="description"><?php _e('These keywords will be used for SEO analysis and optimization suggestions.', 'autonomous-ai-seo'); ?></p>
            </div>
            
            <div class="aaiseo-score-container">
                <div class="aaiseo-score-circle" data-score="0">
                    <span class="aaiseo-score-number">0</span>
                </div>
                <div class="aaiseo-score-label">
                    <strong><?php _e('SEO Score', 'autonomous-ai-seo'); ?></strong>
                    <span class="aaiseo-score-status"><?php _e('Not analyzed', 'autonomous-ai-seo'); ?></span>
                </div>
            </div>
            
            <div class="aaiseo-quick-metrics">
                <div class="aaiseo-metric">
                    <span class="aaiseo-metric-label"><?php _e('Words', 'autonomous-ai-seo'); ?></span>
                    <span class="aaiseo-metric-value" id="aaiseo-word-count">0</span>
                </div>
                <div class="aaiseo-metric">
                    <span class="aaiseo-metric-label"><?php _e('Readability', 'autonomous-ai-seo'); ?></span>
                    <span class="aaiseo-metric-value" id="aaiseo-readability">-</span>
                </div>
                <div class="aaiseo-metric">
                    <span class="aaiseo-metric-label"><?php _e('Keywords', 'autonomous-ai-seo'); ?></span>
                    <span class="aaiseo-metric-value" id="aaiseo-keyword-density">-</span>
                </div>
            </div>
            
            <div class="aaiseo-recommendations" id="aaiseo-recommendations">
                <h4><?php _e('Recommendations', 'autonomous-ai-seo'); ?></h4>
                <div class="aaiseo-recommendations-content">
                    <p class="aaiseo-no-analysis"><?php _e('Start typing to see real-time SEO analysis...', 'autonomous-ai-seo'); ?></p>
                </div>
            </div>
            
            <div class="aaiseo-actions">
                <button type="button" class="button" id="aaiseo-manual-analyze">
                    <?php _e('Analyze Now', 'autonomous-ai-seo'); ?>
                </button>
                <button type="button" class="button" id="aaiseo-generate-suggestions">
                    <?php _e('Get Suggestions', 'autonomous-ai-seo'); ?>
                </button>
            </div>
            
            <div class="aaiseo-loading" id="aaiseo-loading" style="display: none;">
                <span class="spinner is-active"></span>
                <span><?php _e('Analyzing content...', 'autonomous-ai-seo'); ?></span>
            </div>
        </div>
        
        <?php if ($existing_analysis): ?>
        <script>
        jQuery(document).ready(function() {
            var analysis = <?php echo json_encode($existing_analysis); ?>;
            window.aaiseoRealTime.displayAnalysis(analysis);
        });
        </script>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Add SEO panel to classic editor
     */
    public function add_seo_panel_to_editor($editor) {
        global $post;
        
        if (!$post || !current_user_can('edit_post', $post->ID)) {
            return $editor;
        }
        
        $seo_panel = '
        <div id="aaiseo-editor-panel" style="margin-top: 10px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
            <h4>' . __('AI SEO Analysis', 'autonomous-ai-seo') . '</h4>
            <div id="aaiseo-editor-analysis">
                <p>' . __('Content analysis will appear here as you type...', 'autonomous-ai-seo') . '</p>
            </div>
        </div>';
        
        return $editor . $seo_panel;
    }
    
    /**
     * AJAX handler for real-time analysis
     */
    public function ajax_real_time_analyze() {
        check_ajax_referer('aaiseo_real_time_nonce', 'nonce');
        
        $content = wp_unslash($_POST['content']);
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (empty($content)) {
            wp_send_json_error(__('No content provided for analysis.', 'autonomous-ai-seo'));
        }
        
        try {
            // Get target keywords from post meta or settings
            $target_keywords = array();
            if ($post_id) {
                $keywords_meta = get_post_meta($post_id, '_aaiseo_target_keywords', true);
                if ($keywords_meta) {
                    $target_keywords = explode(',', $keywords_meta);
                }
            }
            
            // Add title as potential keyword if available
            if (!empty($title)) {
                $target_keywords[] = $title;
            }
            
            $ai_engine = AAISEO_AI_Engine::getInstance();
            
            // Perform quick analysis for real-time feedback
            $analysis = $this->perform_quick_analysis($content, $target_keywords, $title);
            
            // Save analysis to post meta if post_id is provided
            if ($post_id) {
                update_post_meta($post_id, '_aaiseo_analysis', $analysis);
                update_post_meta($post_id, '_aaiseo_last_analyzed', current_time('mysql'));
            }
            
            wp_send_json_success($analysis);
            
        } catch (Exception $e) {
            wp_send_json_error(__('Analysis failed: ', 'autonomous-ai-seo') . $e->getMessage());
        }
    }
    
    /**
     * Perform quick analysis optimized for real-time feedback
     */
    private function perform_quick_analysis($content, $target_keywords = array(), $title = '') {
        // Basic content metrics
        $word_count = str_word_count(strip_tags($content));
        $char_count = strlen(strip_tags($content));
        $sentences = preg_split('/[.!?]+/', strip_tags($content), -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        
        // Calculate readability score
        $readability = $this->calculate_readability($content, $word_count, $sentence_count);
        
        // Calculate SEO score
        $seo_score = $this->calculate_quick_seo_score($content, $target_keywords, $word_count, $title);
        
        // Generate quick recommendations
        $recommendations = $this->generate_quick_recommendations($content, $target_keywords, $word_count, $seo_score);
        
        // Keyword analysis
        $keyword_analysis = $this->analyze_keyword_usage($content, $target_keywords);
        
        return array(
            'seo_score' => $seo_score,
            'word_count' => $word_count,
            'character_count' => $char_count,
            'sentence_count' => $sentence_count,
            'readability_score' => $readability,
            'keyword_analysis' => $keyword_analysis,
            'recommendations' => $recommendations,
            'timestamp' => current_time('mysql'),
            'analysis_type' => 'real_time'
        );
    }
    
    /**
     * Calculate readability score using Flesch Reading Ease formula
     */
    private function calculate_readability($content, $word_count, $sentence_count) {
        if ($word_count == 0 || $sentence_count == 0) {
            return 0;
        }
        
        $text = strip_tags($content);
        $syllables = $this->count_syllables($text);
        
        $avg_sentence_length = $word_count / $sentence_count;
        $avg_syllables_per_word = $syllables / $word_count;
        
        // Flesch Reading Ease Score
        $score = 206.835 - (1.015 * $avg_sentence_length) - (84.6 * $avg_syllables_per_word);
        
        return max(0, min(100, round($score)));
    }
    
    /**
     * Count syllables in text (simplified algorithm)
     */
    private function count_syllables($text) {
        $text = strtolower($text);
        $words = str_word_count($text, 1);
        $syllable_count = 0;
        
        foreach ($words as $word) {
            $syllables = preg_match_all('/[aeiouy]/', $word);
            // Adjust for silent e
            if (preg_match('/e$/', $word)) {
                $syllables--;
            }
            // Minimum of 1 syllable per word
            $syllable_count += max(1, $syllables);
        }
        
        return $syllable_count;
    }
    
    /**
     * Calculate quick SEO score
     */
    private function calculate_quick_seo_score($content, $target_keywords, $word_count, $title) {
        $score = 0;
        
        // Content length score (0-25 points)
        if ($word_count >= 1500) {
            $score += 25;
        } elseif ($word_count >= 1000) {
            $score += 20;
        } elseif ($word_count >= 700) {
            $score += 15;
        } elseif ($word_count >= 300) {
            $score += 10;
        }
        
        // Keyword usage score (0-25 points)
        if (!empty($target_keywords)) {
            $keyword_score = 0;
            $content_lower = strtolower($content);
            
            foreach ($target_keywords as $keyword) {
                $keyword = trim(strtolower($keyword));
                if (strpos($content_lower, $keyword) !== false) {
                    $keyword_score += 5;
                }
            }
            
            $score += min(25, $keyword_score);
        }
        
        // Structure score (0-25 points)
        $structure_score = 0;
        if (preg_match('/<h[1-6][^>]*>/', $content)) {
            $structure_score += 10; // Has headings
        }
        if (preg_match('/<(ul|ol)[^>]*>/', $content)) {
            $structure_score += 5; // Has lists
        }
        if (preg_match('/<img[^>]*>/', $content)) {
            $structure_score += 5; // Has images
        }
        if (preg_match('/<a[^>]*>/', $content)) {
            $structure_score += 5; // Has links
        }
        
        $score += $structure_score;
        
        // Title optimization (0-25 points)
        if (!empty($title)) {
            $title_score = 0;
            $title_length = strlen($title);
            
            if ($title_length >= 30 && $title_length <= 60) {
                $title_score += 10; // Good title length
            }
            
            if (!empty($target_keywords)) {
                $title_lower = strtolower($title);
                foreach ($target_keywords as $keyword) {
                    $keyword = trim(strtolower($keyword));
                    if (strpos($title_lower, $keyword) !== false) {
                        $title_score += 15;
                        break; // Only count once
                    }
                }
            }
            
            $score += $title_score;
        }
        
        return min(100, $score);
    }
    
    /**
     * Analyze keyword usage
     */
    private function analyze_keyword_usage($content, $target_keywords) {
        $analysis = array();
        $content_lower = strtolower(strip_tags($content));
        $word_count = str_word_count($content_lower);
        
        foreach ($target_keywords as $keyword) {
            $keyword = trim(strtolower($keyword));
            if (empty($keyword)) continue;
            
            $occurrences = substr_count($content_lower, $keyword);
            $density = $word_count > 0 ? ($occurrences / $word_count) * 100 : 0;
            
            $analysis[$keyword] = array(
                'occurrences' => $occurrences,
                'density' => round($density, 2),
                'optimal' => $density >= 0.5 && $density <= 3.0
            );
        }
        
        return $analysis;
    }
    
    /**
     * Generate quick recommendations
     */
    private function generate_quick_recommendations($content, $target_keywords, $word_count, $seo_score) {
        $recommendations = array();
        
        // Content length recommendations
        if ($word_count < 300) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => __('Content is too short. Aim for at least 300 words for better SEO.', 'autonomous-ai-seo')
            );
        } elseif ($word_count < 700) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => __('Consider expanding your content to 700+ words for better search rankings.', 'autonomous-ai-seo')
            );
        }
        
        // Keyword recommendations
        if (!empty($target_keywords)) {
            $content_lower = strtolower($content);
            $missing_keywords = array();
            
            foreach ($target_keywords as $keyword) {
                $keyword = trim(strtolower($keyword));
                if (strpos($content_lower, $keyword) === false) {
                    $missing_keywords[] = $keyword;
                }
            }
            
            if (!empty($missing_keywords)) {
                $recommendations[] = array(
                    'type' => 'warning',
                    'message' => sprintf(
                        __('Missing keywords in content: %s', 'autonomous-ai-seo'),
                        implode(', ', $missing_keywords)
                    )
                );
            }
        }
        
        // Structure recommendations
        if (!preg_match('/<h[2-6][^>]*>/', $content)) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => __('Add subheadings (H2, H3) to improve content structure and readability.', 'autonomous-ai-seo')
            );
        }
        
        if (!preg_match('/<(ul|ol)[^>]*>/', $content)) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => __('Consider adding bullet points or numbered lists to break up text.', 'autonomous-ai-seo')
            );
        }
        
        // Overall score recommendations
        if ($seo_score < 50) {
            $recommendations[] = array(
                'type' => 'warning',
                'message' => __('Your SEO score needs improvement. Focus on content length, keyword usage, and structure.', 'autonomous-ai-seo')
            );
        } elseif ($seo_score < 75) {
            $recommendations[] = array(
                'type' => 'info',
                'message' => __('Good progress! Fine-tune your keyword placement and content structure for better results.', 'autonomous-ai-seo')
            );
        } else {
            $recommendations[] = array(
                'type' => 'success',
                'message' => __('Excellent SEO optimization! Your content is well-structured and keyword-optimized.', 'autonomous-ai-seo')
            );
        }
        
        return $recommendations;
    }
    
    /**
     * AJAX handler for generating suggestions (referenced in JS but missing)
     */
    public function ajax_generate_suggestions() {
        check_ajax_referer('aaiseo_real_time_nonce', 'nonce');
        
        $topic = isset($_POST['topic']) ? sanitize_text_field($_POST['topic']) : '';
        $target_audience = isset($_POST['target_audience']) ? sanitize_text_field($_POST['target_audience']) : 'general audience';
        
        if (empty($topic)) {
            wp_send_json_error(__('Please provide a topic.', 'autonomous-ai-seo'));
        }
        
        try {
            $ai_engine = AAISEO_AI_Engine::getInstance();
            $suggestions = $ai_engine->generateContentOutline($topic, $target_audience);
            
            if (is_wp_error($suggestions)) {
                wp_send_json_error($suggestions->get_error_message());
            }
            
            // Parse JSON if it's a string
            if (is_string($suggestions)) {
                $suggestions = json_decode($suggestions, true);
            }
            
            wp_send_json_success($suggestions);
            
        } catch (Exception $e) {
            wp_send_json_error(__('Failed to generate suggestions: ', 'autonomous-ai-seo') . $e->getMessage());
        }
    }
    
    /**
     * Save meta box data when post is saved
     */
    public function save_meta_box_data($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check nonce
        if (!isset($_POST['aaiseo_meta_box_nonce']) || !wp_verify_nonce($_POST['aaiseo_meta_box_nonce'], 'aaiseo_meta_box')) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save target keywords if provided
        if (isset($_POST['aaiseo_target_keywords'])) {
            update_post_meta($post_id, '_aaiseo_target_keywords', sanitize_text_field($_POST['aaiseo_target_keywords']));
        }
    }
}

// Initialize real-time optimization
new AAISEO_Real_Time();