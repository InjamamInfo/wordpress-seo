<?php
/**
 * Enhanced AI Engine for Autonomous AI SEO
 * Compatible with PHP 7.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAISEO_AI_Engine {
    
    private static $instance = null;
    private $settings;
    private $preferred_provider = 'openai';
    private $api_base_url = 'https://api.openai.com/v1/';
    private $cache_duration = 3600; // 1 hour cache
    private $api_keys = array();
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $settings = get_option('aaiseo_settings', array());
        $this->settings = $settings;
        $this->preferred_provider = !empty($settings['preferred_ai_provider']) ? $settings['preferred_ai_provider'] : 'openai';
        
        // Initialize API keys
        $this->api_keys = array(
            'openai' => !empty($settings['openai_api_key']) ? $settings['openai_api_key'] : '',
            'grok' => !empty($settings['grok_api_key']) ? $settings['grok_api_key'] : '',
            'gemini' => !empty($settings['gemini_api_key']) ? $settings['gemini_api_key'] : '',
            'deepseek' => !empty($settings['deepseek_api_key']) ? $settings['deepseek_api_key'] : ''
        );
    }
    
    /**
     * Get API provider status
     */
    public function getAPIProviderStatus() {
        $providers = array(
            'openai' => array(
                'name' => 'OpenAI (GPT)',
                'available' => !empty($this->api_keys['openai']),
                'is_active' => $this->preferred_provider === 'openai'
            ),
            'grok' => array(
                'name' => 'Grok',
                'available' => !empty($this->api_keys['grok']),
                'is_active' => $this->preferred_provider === 'grok'
            ),
            'gemini' => array(
                'name' => 'Google Gemini',
                'available' => !empty($this->api_keys['gemini']),
                'is_active' => $this->preferred_provider === 'gemini'
            ),
            'deepseek' => array(
                'name' => 'DeepSeek',
                'available' => !empty($this->api_keys['deepseek']),
                'is_active' => $this->preferred_provider === 'deepseek'
            ),
            'internal' => array(
                'name' => 'Internal AI (Fallback)',
                'available' => true,
                'is_active' => $this->preferred_provider === 'internal'
            )
        );
        
        // Determine active provider - use preferred if available, otherwise fallback
        $active_provider = $this->preferred_provider;
        
        if ($active_provider !== 'internal' && (!isset($providers[$active_provider]) || !$providers[$active_provider]['available'])) {
            // Fallback to available provider
            foreach (array('openai', 'gemini', 'grok', 'deepseek') as $provider) {
                if ($providers[$provider]['available']) {
                    $active_provider = $provider;
                    break;
                }
            }
            
            // If no API keys available, use internal
            if (!$providers[$active_provider]['available']) {
                $active_provider = 'internal';
            }
        }
        
        return array(
            'providers' => $providers,
            'active_provider' => $active_provider
        );
    }
    
    /**
     * Select the appropriate AI provider based on settings and available API keys
     */
    private function selectAIProvider($provider = null) {
        // If no provider specified, use the preferred provider
        if (!$provider) {
            $provider = $this->preferred_provider;
        }
        
        // Check if the selected provider is available
        switch ($provider) {
            case 'grok':
                if (!empty($this->api_keys['grok'])) {
                    return 'grok';
                }
                break;
            case 'gemini':
                if (!empty($this->api_keys['gemini'])) {
                    return 'gemini';
                }
                break;
            case 'deepseek':
                if (!empty($this->api_keys['deepseek'])) {
                    return 'deepseek';
                }
                break;
            case 'openai':
                if (!empty($this->api_keys['openai'])) {
                    return 'openai';
                }
                break;
            case 'internal':
                return 'internal';
        }
        
        // If the selected provider is not available, fall back to any available provider
        if (!empty($this->api_keys['openai'])) return 'openai';
        if (!empty($this->api_keys['grok'])) return 'grok';
        if (!empty($this->api_keys['gemini'])) return 'gemini';
        if (!empty($this->api_keys['deepseek'])) return 'deepseek';
        
        // If no external provider is available, fall back to internal methods
        return 'internal';
    }
    
    /**
     * Make a request to the appropriate AI provider
     */
    private function makeAIRequest($prompt, $system_prompt = '', $provider = null) {
        $selected_provider = $this->selectAIProvider($provider);
        
        switch ($selected_provider) {
            case 'openai':
                $messages = array();
                if (!empty($system_prompt)) {
                    $messages[] = array(
                        'role' => 'system',
                        'content' => $system_prompt
                    );
                }
                $messages[] = array(
                    'role' => 'user',
                    'content' => $prompt
                );
                
                $response = $this->makeOpenAIRequest('chat/completions', array(
                    'model' => 'gpt-3.5-turbo',
                    'messages' => $messages,
                    'max_tokens' => 3000,
                    'temperature' => 0.3
                ));
                
                if (is_wp_error($response)) {
                    return $response;
                }
                
                $result = json_decode($response, true);
                
                if (isset($result['choices'][0]['message']['content'])) {
                    return $result['choices'][0]['message']['content'];
                }
                
                return new WP_Error('invalid_response', __('Invalid OpenAI response', 'autonomous-ai-seo'));
                
            case 'grok':
                return $this->makeGrokRequest($prompt, $system_prompt);
                
            case 'gemini':
                return $this->makeGeminiRequest($prompt);
                
            case 'deepseek':
                return $this->makeDeepSeekRequest($prompt, $system_prompt);
                
            case 'internal':
                return $this->generateInternalResponse($prompt);
                
            default:
                return new WP_Error('invalid_provider', __('Invalid AI provider', 'autonomous-ai-seo'));
        }
    }
    
    /**
     * Make a request to the Grok API
     */
    private function makeGrokRequest($prompt, $system_prompt = '', $retry_count = 0) {
        if (empty($this->api_keys['grok'])) {
            return new WP_Error('no_api_key', __('Grok API key not configured', 'autonomous-ai-seo'));
        }
        
        // Construct the API URL (placeholder)
        $url = 'https://api.grok.x/v1/chat/completions';
        
        // Prepare the messages array
        $messages = array();
        if (!empty($system_prompt)) {
            $messages[] = array(
                'role' => 'system',
                'content' => $system_prompt
            );
        }
        $messages[] = array(
            'role' => 'user',
            'content' => $prompt
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_keys['grok']
            ),
            'body' => json_encode(array(
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 3000
            )),
            'timeout' => 60
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            // Retry up to 3 times for network errors
            if ($retry_count < 3) {
                sleep(1); // Wait 1 second before retry
                return $this->makeGrokRequest($prompt, $system_prompt, $retry_count + 1);
            }
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'API request failed';
            return new WP_Error('api_error', $error_message);
        }
        
        // Parse the response and extract the content
        $result = json_decode($response_body, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
        
        return new WP_Error('invalid_response', __('Invalid Grok API response', 'autonomous-ai-seo'));
    }
    
    /**
     * Make a request to the Google Gemini API
     */
    private function makeGeminiRequest($prompt, $retry_count = 0) {
        if (empty($this->api_keys['gemini'])) {
            return new WP_Error('no_api_key', __('Gemini API key not configured', 'autonomous-ai-seo'));
        }
        
        // Construct the API URL
        $url = 'https://generativelanguage.googleapis.com/v1/models/gemini-pro:generateContent?key=' . $this->api_keys['gemini'];
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array(
                                'text' => $prompt
                            )
                        )
                    )
                ),
                'generationConfig' => array(
                    'temperature' => 0.7,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 8192,
                )
            )),
            'timeout' => 60
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            // Retry up to 3 times for network errors
            if ($retry_count < 3) {
                sleep(1); // Wait 1 second before retry
                return $this->makeGeminiRequest($prompt, $retry_count + 1);
            }
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'API request failed';
            return new WP_Error('api_error', $error_message);
        }
        
        // Parse the response and extract the content
        $result = json_decode($response_body, true);
        
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }
        
        return new WP_Error('invalid_response', __('Invalid Gemini API response', 'autonomous-ai-seo'));
    }
    
    /**
     * Make a request to the DeepSeek API
     */
    private function makeDeepSeekRequest($prompt, $system_prompt = '', $retry_count = 0) {
        if (empty($this->api_keys['deepseek'])) {
            return new WP_Error('no_api_key', __('DeepSeek API key not configured', 'autonomous-ai-seo'));
        }
        
        // Construct the API URL (placeholder)
        $url = 'https://api.deepseek.com/v1/chat/completions';
        
        // Prepare the messages array
        $messages = array();
        if (!empty($system_prompt)) {
            $messages[] = array(
                'role' => 'system',
                'content' => $system_prompt
            );
        }
        $messages[] = array(
            'role' => 'user',
            'content' => $prompt
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_keys['deepseek']
            ),
            'body' => json_encode(array(
                'messages' => $messages,
                'model' => 'deepseek-chat',
                'temperature' => 0.7,
                'max_tokens' => 3000
            )),
            'timeout' => 60
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            // Retry up to 3 times for network errors
            if ($retry_count < 3) {
                sleep(1); // Wait 1 second before retry
                return $this->makeDeepSeekRequest($prompt, $system_prompt, $retry_count + 1);
            }
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'API request failed';
            return new WP_Error('api_error', $error_message);
        }
        
        // Parse the response and extract the content
        $result = json_decode($response_body, true);
        
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
        
        return new WP_Error('invalid_response', __('Invalid DeepSeek API response', 'autonomous-ai-seo'));
    }
    
    /**
     * Generate a response without external AI using internal algorithms
     */
    private function generateInternalResponse($prompt) {
        // Parse the prompt to determine the type of analysis needed
        if (strpos($prompt, 'content') !== false && strpos($prompt, 'analyze') !== false) {
            return $this->generateInternalContentAnalysis($prompt);
        } elseif (strpos($prompt, 'technical') !== false) {
            return $this->generateInternalTechnicalRecommendations($prompt);
        } elseif (strpos($prompt, 'keyword') !== false) {
            return $this->generateInternalKeywordSuggestions($prompt);
        } else {
            // Default response
            return json_encode(array(
                'message' => 'Generated using internal algorithms. For more advanced analysis, please configure an external AI provider.',
                'recommendations' => array(
                    'Add more detailed content to improve depth',
                    'Include relevant keywords naturally throughout the text',
                    'Ensure proper heading structure (H2, H3) for better organization'
                )
            ));
        }
    }
    
    /**
     * Generate content analysis using internal algorithms without external AI
     */
    private function generateInternalContentAnalysis($prompt) {
        // Extract content from the prompt
        preg_match('/Content:(.*?)(?:\n\n|$)/s', $prompt, $matches);
        $content = isset($matches[1]) ? trim($matches[1]) : '';
        
        if (empty($content)) {
            return json_encode(array(
                'error' => 'No content found for analysis'
            ));
        }
        
        // Basic content metrics
        $word_count = str_word_count(strip_tags($content));
        $sentences = preg_split('/[.!?]+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        $sentence_count = count($sentences);
        $avg_sentence_length = $sentence_count > 0 ? $word_count / $sentence_count : 0;
        
        // Basic keyword analysis
        $words = str_word_count(strtolower(strip_tags($content)), 1);
        $word_freq = array_count_values($words);
        arsort($word_freq);
        $top_keywords = array_slice($word_freq, 0, 10, true);
        
        // Calculate scores
        $seo_score = $this->calculateBasicSeoScore($word_count, $avg_sentence_length, $content);
        $readability_score = $this->calculateReadabilityScore($content);
        
        // Generate suggestions
        $suggestions = $this->generateBasicSuggestions($word_count, $avg_sentence_length, $content);
        
        // Return results in expected format
        return json_encode(array(
            'seo_score' => $seo_score,
            'content_quality' => array(
                'readability_score' => $readability_score,
                'word_count' => $word_count,
                'avg_sentence_length' => round($avg_sentence_length, 1),
                'content_depth' => $word_count > 1000 ? 85 : ($word_count > 500 ? 65 : 40)
            ),
            'top_keywords' => array_keys($top_keywords),
            'improvement_suggestions' => $suggestions
        ));
    }
    
    /**
     * Calculate a basic SEO score based on content metrics
     */
    private function calculateBasicSeoScore($word_count, $avg_sentence_length, $content) {
        $score = 50; // Base score
        
        // Word count factor (up to 30 points)
        if ($word_count < 300) {
            $score -= 10; // Too short
        } elseif ($word_count >= 300 && $word_count < 600) {
            $score += 10;
        } elseif ($word_count >= 600 && $word_count < 1000) {
            $score += 15;
        } elseif ($word_count >= 1000 && $word_count < 1500) {
            $score += 20;
        } elseif ($word_count >= 1500) {
            $score += 25;
        }
        
        // Sentence length factor (up to 10 points)
        if ($avg_sentence_length > 30) {
            $score -= 10; // Sentences too long
        } elseif ($avg_sentence_length > 25) {
            $score -= 5; // Sentences a bit long
        } elseif ($avg_sentence_length >= 15 && $avg_sentence_length <= 20) {
            $score += 10; // Ideal range
        }
        
        // Structure factor (up to 20 points)
        if (strpos($content, '<h2') !== false || strpos($content, '<h3') !== false) {
            $score += 10; // Has subheadings
        }
        if (strpos($content, '<ul') !== false || strpos($content, '<ol') !== false) {
            $score += 5; // Has lists
        }
        if (strpos($content, '<img') !== false) {
            $score += 5; // Has images
        }
        
        return min(100, max(0, $score));
    }
    
    /**
     * Calculate readability score
     */
    private function calculateReadabilityScore($content) {
        // Simplified readability calculation
        $text = strip_tags($content);
        $words = str_word_count($text);
        $sentences = count(preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY));
        
        if ($sentences == 0) return 0;
        
        $avg_words_per_sentence = $words / $sentences;
        
        // Very simple scoring
        if ($avg_words_per_sentence > 30) {
            return 30; // Very difficult
        } elseif ($avg_words_per_sentence > 25) {
            return 45; // Difficult
        } elseif ($avg_words_per_sentence > 20) {
            return 60; // Moderately difficult
        } elseif ($avg_words_per_sentence > 15) {
            return 75; // Standard
        } elseif ($avg_words_per_sentence > 10) {
            return 85; // Fairly easy
        } else {
            return 95; // Very easy
        }
    }
    
    /**
     * Generate basic improvement suggestions
     */
    private function generateBasicSuggestions($word_count, $avg_sentence_length, $content) {
        $suggestions = array();
        
        // Word count suggestions
        if ($word_count < 300) {
            $suggestions[] = "Increase content length to at least 300 words for better SEO performance.";
        }
        
        // Sentence length suggestions
        if ($avg_sentence_length > 25) {
            $suggestions[] = "Break down longer sentences to improve readability. Aim for an average of 15-20 words per sentence.";
        }
        
        // Structure suggestions
        if (strpos($content, '<h2') === false && strpos($content, '<h3') === false) {
            $suggestions[] = "Add subheadings (H2, H3) to structure your content and improve readability.";
        }
        
        if (strpos($content, '<ul') === false && strpos($content, '<ol') === false) {
            $suggestions[] = "Include bullet points or numbered lists to break up text and highlight important information.";
        }
        
        if (strpos($content, '<img') === false) {
            $suggestions[] = "Add relevant images with descriptive alt text to enhance engagement and accessibility.";
        }
        
        // Add some standard suggestions
        $suggestions[] = "Ensure your primary keyword appears in the title, first paragraph, and at least one subheading.";
        $suggestions[] = "Include a clear call-to-action (CTA) at the end of your content.";
        $suggestions[] = "Add internal links to other relevant content on your website.";
        
        return array_slice($suggestions, 0, 5); // Return top 5 suggestions
    }
    
    /**
     * Generate internal technical recommendations
     */
    private function generateInternalTechnicalRecommendations($prompt) {
        // This would analyze technical audit data and generate recommendations
        // For now, return some standard recommendations
        return json_encode(array(
            'critical_fixes' => array(
                array(
                    'issue' => 'Missing meta descriptions',
                    'solution' => 'Add unique meta descriptions to all pages',
                    'impact' => 'Improved click-through rates from search results'
                ),
                array(
                    'issue' => 'Slow page load speed',
                    'solution' => 'Optimize images and enable browser caching',
                    'impact' => 'Better user experience and improved Core Web Vitals'
                )
            ),
            'htaccess_rules' => array(
                'Enable browser caching for static resources',
                'Enable GZIP compression'
            ),
            'schema_markup' => array(
                'recommended_types' => array('Article', 'FAQPage', 'Organization')
            )
        ));
    }
    
    /**
     * Generate internal keyword suggestions
     */
    private function generateInternalKeywordSuggestions($prompt) {
        // Extract primary keyword if present
        preg_match('/keyword[s]?:?\s*([^\n]+)/i', $prompt, $matches);
        $primary_keyword = isset($matches[1]) ? trim($matches[1]) : '';
        
        if (empty($primary_keyword)) {
            $primary_keyword = 'SEO';
        }
        
        // Generate variations
        $variations = array(
            $primary_keyword . ' tips',
            $primary_keyword . ' guide',
            'best ' . $primary_keyword,
            'how to ' . $primary_keyword,
            $primary_keyword . ' examples',
            $primary_keyword . ' strategies',
            $primary_keyword . ' tools',
            'improve ' . $primary_keyword,
            $primary_keyword . ' benefits',
            $primary_keyword . ' vs traditional methods'
        );
        
        return json_encode(array(
            'primary_keyword' => $primary_keyword,
            'semantic_keywords' => array_slice($variations, 0, 5),
            'long_tail_variations' => array_slice($variations, 5)
        ));
    }
    
    /**
     * Enhanced content analysis with sentiment and plagiarism checking
     */
    public function analyzeContent($content, $target_keywords = array(), $context = array(), $provider = null) {
        // Get active provider
        $provider_status = $this->getAPIProviderStatus();
        $selected_provider = $provider ?: $provider_status['active_provider'];
        
        // If using internal, use simplified analysis
        if ($selected_provider === 'internal') {
            return $this->performInternalAnalysis($content, $target_keywords, $context);
        }
        
        // Check cache first regardless of provider
        $cache_key = 'aaiseo_content_analysis_' . md5($content . serialize($target_keywords) . $selected_provider);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $prompt = $this->buildEnhancedContentAnalysisPrompt($content, $target_keywords, $context);
        $system_prompt = 'You are an expert SEO analyst with expertise in content optimization, sentiment analysis, and plagiarism detection. Provide comprehensive analysis in JSON format.';
        
        $response = $this->makeAIRequest($prompt, $system_prompt, $selected_provider);
        
        if (is_wp_error($response)) {
            // Fall back to internal analysis
            return $this->performInternalAnalysis($content, $target_keywords, $context);
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
                return $this->performInternalAnalysis($content, $target_keywords, $context);
            }
        }
        
        // Cache the result
        set_transient($cache_key, $parsed, $this->cache_duration);
        
        return $parsed;
    }
    
    /**
     * Perform internal content analysis (fallback when no API keys are available)
     */
    private function performInternalAnalysis($content, $target_keywords = array(), $context = array()) {
        $word_count = str_word_count(strip_tags($content));
        $text_length = strlen(strip_tags($content));
        $avg_word_length = $word_count > 0 ? $text_length / $word_count : 0;
        
        // Calculate readability score (simplified Flesch-Kincaid)
        $sentences = preg_split('/[.!?]+/', $content);
        $sentence_count = count(array_filter($sentences));
        
        $readability_score = 0;
        if ($sentence_count > 0 && $word_count > 0) {
            $words_per_sentence = $word_count / $sentence_count;
            // Simple readability formula
            $readability_score = 206.835 - (1.015 * $words_per_sentence) - (84.6 * $avg_word_length / 5);
            $readability_score = min(100, max(0, $readability_score));
        }
        
        // Check keyword density
        $keyword_density = array();
        foreach ($target_keywords as $keyword) {
            $keyword = strtolower($keyword);
            $count = substr_count(strtolower($content), $keyword);
            $density = $word_count > 0 ? ($count / $word_count) * 100 : 0;
            $keyword_density[$keyword] = round($density, 2) . '%';
        }
        
        // Basic SEO score calculation
        $seo_score = 0;
        
        // Add points for content length
        if ($word_count >= 1500) {
            $seo_score += 25;
        } elseif ($word_count >= 1000) {
            $seo_score += 20;
        } elseif ($word_count >= 700) {
            $seo_score += 15;
        } elseif ($word_count >= 300) {
            $seo_score += 10;
        }
        
        // Add points for keyword presence
        $keyword_points = 0;
        foreach ($target_keywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $keyword_points += 5;
            }
        }
        $seo_score += min(25, $keyword_points);
        
        // Add points for readability
        if ($readability_score >= 60) {
            $seo_score += 25;
        } elseif ($readability_score >= 50) {
            $seo_score += 20;
        } elseif ($readability_score >= 40) {
            $seo_score += 15;
        }
        
        // Add points for headings
        $has_h2 = preg_match('/<h2[^>]*>.*?<\/h2>/is', $content);
        $has_h3 = preg_match('/<h3[^>]*>.*?<\/h3>/is', $content);
        
        if ($has_h2) $seo_score += 15;
        if ($has_h3) $seo_score += 10;
        
        // Generate basic recommendations
        $recommendations = array();
        
        if ($word_count < 300) {
            $recommendations[] = 'Increase content length to at least 300 words for better SEO performance';
        }
        
        if (count($target_keywords) > 0) {
            $keywords_found = 0;
            foreach ($target_keywords as $keyword) {
                if (stripos($content, $keyword) !== false) {
                    $keywords_found++;
                }
            }
            
            if ($keywords_found < count($target_keywords)) {
                $recommendations[] = 'Include all target keywords in your content';
            }
        }
        
        if (!$has_h2) {
            $recommendations[] = 'Add H2 headings to structure your content';
        }
        
        if (!$has_h3 && $word_count > 500) {
            $recommendations[] = 'Consider adding H3 subheadings for longer content';
        }
        
        return array(
            'seo_score' => $seo_score,
            'readability_score' => round($readability_score),
            'word_count' => $word_count,
            'keyword_density' => $keyword_density,
            'recommendations' => $recommendations,
            'analysis_note' => 'Analysis performed using internal engine (API-free)'
        );
    }
    
    /**
     * Build enhanced content analysis prompt
     */
    private function buildEnhancedContentAnalysisPrompt($content, $target_keywords, $context) {
        $keywords_text = !empty($target_keywords) ? 'Target keywords: ' . implode(', ', $target_keywords) . "\n" : '';
        $context_text = !empty($context) ? 'Context: ' . json_encode($context) . "\n" : '';
        
        return "Analyze this content comprehensively and provide results in JSON format:

{$keywords_text}{$context_text}
Content:
{$content}

Please provide analysis in the following JSON structure:
{
  \"seo_score\": 0-100,
  \"sentiment_analysis\": {
    \"tone\": \"positive/neutral/negative\",
    \"emotion\": \"primary emotion detected\",
    \"confidence\": 0-1,
    \"recommendations\": \"suggestions for tone improvement\"
  },
  \"content_quality\": {
    \"readability_score\": 0-100,
    \"keyword_density\": \"percentage for each keyword\",
    \"semantic_relevance\": 0-100,
    \"content_depth\": 0-100
  },
  \"plagiarism_indicators\": {
    \"uniqueness_score\": 0-100,
    \"potential_issues\": \"areas that might need attention\"
  },
  \"technical_seo\": {
    \"header_structure\": \"analysis of H1-H6 usage\",
    \"meta_opportunities\": \"suggestions for meta tags\",
    \"internal_linking\": \"opportunities for internal links\"
  },
  \"improvement_suggestions\": [
    \"specific actionable recommendations\"
  ],
  \"content_gaps\": [
    \"topics or keywords missing\"
  ]
}";
    }
    
    /**
     * Make OpenAI API request with retry logic
     */
    private function makeOpenAIRequest($endpoint, $data, $retry_count = 0) {
        if (empty($this->api_keys['openai'])) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured', 'autonomous-ai-seo'));
        }
        
        $url = $this->api_base_url . $endpoint;
        
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_keys['openai']
            ),
            'body' => json_encode($data),
            'timeout' => 60
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            // Retry up to 3 times for network errors
            if ($retry_count < 3) {
                sleep(1); // Wait 1 second before retry
                return $this->makeOpenAIRequest($endpoint, $data, $retry_count + 1);
            }
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 429 && $retry_count < 3) {
            // Rate limit - wait and retry
            sleep(2);
            return $this->makeOpenAIRequest($endpoint, $data, $retry_count + 1);
        }
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) ? $error_data['error']['message'] : 'API request failed';
            return new WP_Error('api_error', $error_message);
        }
        
        return $response_body;
    }
    
    /**
     * Generate article content
     */
    public function generateArticleContent($prompt) {
        // Get active provider
        $provider_status = $this->getAPIProviderStatus();
        $provider = $provider_status['active_provider'];
        
        // If using internal, use simplified generation
        if ($provider === 'internal') {
            return $this->generateInternalContent($prompt);
        }
        
        $response = $this->makeAIRequest($prompt, 'You are an expert SEO content writer who creates engaging, informative, and well-structured articles. You will output content in clean HTML format that can be used in WordPress.', $provider);
        
        if (is_wp_error($response)) {
            return $this->generateInternalContent($prompt);
        }
        
        return $response;
    }
    
    /**
     * Generate internal content (fallback when no API keys are available)
     */
    private function generateInternalContent($prompt) {
        // Extract key information from the prompt
        preg_match('/Article Title: (.*?)(\n|$)/', $prompt, $title_matches);
        $title = !empty($title_matches[1]) ? trim($title_matches[1]) : 'Untitled Article';
        
        preg_match('/Target Keywords: (.*?)(\n|$)/', $prompt, $keyword_matches);
        $keywords = !empty($keyword_matches[1]) ? explode(',', trim($keyword_matches[1])) : array();
        
        // Generate a simple article structure
        $content = "<!-- wp:heading -->\n<h2>Introduction to " . esc_html($title) . "</h2>\n<!-- /wp:heading -->\n\n";
        $content .= "<!-- wp:paragraph -->\n<p>Welcome to this comprehensive guide about " . esc_html($title) . ". In this article, we'll explore the key aspects of this topic and provide valuable insights to help you understand it better.</p>\n<!-- /wp:paragraph -->\n\n";
        
        if (!empty($keywords)) {
            $content .= "<!-- wp:paragraph -->\n<p>When it comes to " . esc_html(implode(' and ', $keywords)) . ", there are several important factors to consider. Let's dive into the details.</p>\n<!-- /wp:paragraph -->\n\n";
        }
        
        $content .= "<!-- wp:heading -->\n<h2>Key Points to Understand</h2>\n<!-- /wp:heading -->\n\n";
        $content .= "<!-- wp:paragraph -->\n<p>The most important aspects of this topic include understanding the fundamentals, applying best practices, and staying updated with the latest trends.</p>\n<!-- /wp:paragraph -->\n\n";
        
        $content .= "<!-- wp:heading {\"level\":3} -->\n<h3>1. Understanding the Basics</h3>\n<!-- /wp:heading -->\n\n";
        $content .= "<!-- wp:paragraph -->\n<p>To fully grasp this subject, it's essential to start with the foundational concepts. This provides a solid base for more advanced knowledge.</p>\n<!-- /wp:paragraph -->\n\n";
        
        $content .= "<!-- wp:heading {\"level\":3} -->\n<h3>2. Best Practices for Success</h3>\n<!-- /wp:heading -->\n\n";
        $content .= "<!-- wp:paragraph -->\n<p>Following industry best practices can significantly improve your results. These time-tested approaches have proven effective in various scenarios.</p>\n<!-- /wp:paragraph -->\n\n";
        
        $content .= "<!-- wp:heading {\"level\":3} -->\n<h3>3. Latest Trends and Developments</h3>\n<!-- /wp:heading -->\n\n";
        $content .= "<!-- wp:paragraph -->\n<p>Staying current with emerging trends is crucial in today's fast-changing environment. Being aware of recent developments gives you a competitive advantage.</p>\n<!-- /wp:paragraph -->\n\n";
        
        $content .= "<!-- wp:heading -->\n<h2>Conclusion</h2>\n<!-- /wp:heading -->\n\n";
        $content .= "<!-- wp:paragraph -->\n<p>In conclusion, " . esc_html($title) . " is a fascinating subject with many important applications. By understanding the core concepts and following best practices, you can achieve excellent results.</p>\n<!-- /wp:paragraph -->\n\n";
        
        return $content;
    }
    
    /**
     * Generate recommendations based on prompt
     */
    public function generateRecommendations($prompt) {
        // Get active provider
        $provider_status = $this->getAPIProviderStatus();
        $provider = $provider_status['active_provider'];
        
        // If using internal, return generic recommendations
        if ($provider === 'internal') {
            return array(
                'recommendations' => array(
                    array(
                        'title' => 'Improve Content Structure',
                        'description' => 'Break content into smaller, more digestible sections with clear headings and subheadings.'
                    ),
                    array(
                        'title' => 'Enhance Call-to-Action',
                        'description' => 'Make your CTAs more prominent and use action-oriented language to encourage clicks.'
                    ),
                    array(
                        'title' => 'Optimize for Mobile Users',
                        'description' => 'Ensure all elements are properly sized for mobile screens and touch interactions.'
                    ),
                    array(
                        'title' => 'Improve Page Load Speed',
                        'description' => 'Optimize images and minimize unnecessary scripts to improve page load times.'
                    )
                )
            );
        }
        
        $response = $this->makeAIRequest($prompt, 'You are an expert in user experience and conversion rate optimization. Provide specific, actionable recommendations to improve engagement and conversion rates.', $provider);
        
        if (is_wp_error($response)) {
            return array('recommendations' => array());
        }
        
        // Try to parse JSON response
        $parsed = json_decode($response, true);
        if ($parsed && isset($parsed['recommendations'])) {
            return $parsed;
        }
        
        // Fallback to text parsing
        preg_match_all('/\d+\.\s+(.*?)(?=\d+\.|$)/s', $response, $matches);
        if (!empty($matches[1])) {
            $recommendations = array();
            foreach ($matches[1] as $rec) {
                $parts = explode(':', $rec, 2);
                if (count($parts) === 2) {
                    $recommendations[] = array(
                        'title' => trim($parts[0]),
                        'description' => trim($parts[1])
                    );
                } else {
                    $recommendations[] = array(
                        'title' => 'Recommendation',
                        'description' => trim($rec)
                    );
                }
            }
            return array('recommendations' => $recommendations);
        }
        
        return array('recommendations' => array());
    }
    
    /**
     * Generate content outline
     */
    public function generateContentOutline($topic, $target_audience = '', $provider = null) {
        // Get active provider
        $provider_status = $this->getAPIProviderStatus();
        $selected_provider = $provider ?: $provider_status['active_provider'];
        
        // If using internal, generate basic outline
        if ($selected_provider === 'internal') {
            return $this->generateInternalOutline($topic, $target_audience);
        }
        
        // Check cache first
        $cache_key = 'aaiseo_outline_' . md5($topic . $target_audience . $selected_provider);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $prompt = "Create a comprehensive content outline for the topic: '{$topic}'";
        if (!empty($target_audience)) {
            $prompt .= " targeting audience: {$target_audience}";
        }
        
        $prompt .= "

Please provide the response in JSON format:
{
  \"title_suggestions\": [
    \"suggested title 1\",
    \"suggested title 2\",
    \"suggested title 3\"
  ],
  \"outline\": [
    {
      \"heading\": \"Main Section 1\",
      \"subheadings\": [\"Subsection 1.1\", \"Subsection 1.2\"]
    },
    {
      \"heading\": \"Main Section 2\",
      \"subheadings\": [\"Subsection 2.1\", \"Subsection 2.2\"]
    }
  ],
  \"meta_description\": \"Suggested meta description\"
}";
        
        $system_prompt = 'You are an expert content strategist who creates detailed, SEO-optimized content outlines. Always respond in valid JSON format.';
        
        $response = $this->makeAIRequest($prompt, $system_prompt, $selected_provider);
        
        if (is_wp_error($response)) {
            return $this->generateInternalOutline($topic, $target_audience);
        }
        
        // Try to parse JSON response
        $parsed = json_decode($response, true);
        
        if (!$parsed) {
            // Try to extract JSON from response
            preg_match('/```json\s*(.*?)\s*```/s', $response, $matches);
            if (!empty($matches[1])) {
                $parsed = json_decode($matches[1], true);
            }
            
            if (!$parsed) {
                return $this->generateInternalOutline($topic, $target_audience);
            }
        }
        
        // Cache the result
        set_transient($cache_key, $parsed, $this->cache_duration);
        
        return $parsed;
    }
    
    /**
     * Generate internal content outline (fallback)
     */
    private function generateInternalOutline($topic, $target_audience = '') {
        $audience_text = !empty($target_audience) ? " for {$target_audience}" : '';
        
        return array(
            'title_suggestions' => array(
                "Complete Guide to {$topic}",
                "Everything You Need to Know About {$topic}",
                "The Ultimate {$topic} Guide{$audience_text}",
                "Mastering {$topic}: A Comprehensive Overview",
                "{$topic} Explained: Tips and Best Practices"
            ),
            'outline' => array(
                array(
                    'heading' => "Introduction to {$topic}",
                    'subheadings' => array(
                        "What is {$topic}?",
                        "Why {$topic} matters",
                        "Key benefits and applications"
                    )
                ),
                array(
                    'heading' => "Getting Started with {$topic}",
                    'subheadings' => array(
                        "Basic concepts and terminology",
                        "Essential tools and resources",
                        "Common challenges and solutions"
                    )
                ),
                array(
                    'heading' => "Best Practices for {$topic}",
                    'subheadings' => array(
                        "Industry standards and guidelines",
                        "Expert tips and recommendations",
                        "Common mistakes to avoid"
                    )
                ),
                array(
                    'heading' => "Advanced {$topic} Techniques",
                    'subheadings' => array(
                        "Advanced strategies and methods",
                        "Case studies and examples",
                        "Future trends and developments"
                    )
                ),
                array(
                    'heading' => "Conclusion",
                    'subheadings' => array(
                        "Key takeaways",
                        "Next steps and recommendations",
                        "Additional resources"
                    )
                )
            ),
            'meta_description' => "Comprehensive guide to {$topic}{$audience_text}. Learn best practices, expert tips, and advanced techniques to master {$topic} effectively."
        );
    }
    
    /**
     * Analyze semantic keywords
     */
    public function analyzeSemanticKeywords($content, $primary_keyword, $provider = null) {
        // Get active provider
        $provider_status = $this->getAPIProviderStatus();
        $selected_provider = $provider ?: $provider_status['active_provider'];
        
        // If using internal, generate basic keywords
        if ($selected_provider === 'internal') {
            return $this->generateInternalKeywordSuggestions("Primary keyword: {$primary_keyword}\nContent: {$content}");
        }
        
        // Check cache first
        $cache_key = 'aaiseo_keywords_' . md5($content . $primary_keyword . $selected_provider);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $prompt = "Analyze the following content and generate semantic keywords and variations for the primary keyword: '{$primary_keyword}'

Content: {$content}

Please provide the response in JSON format:
{
  \"primary_keyword\": \"{$primary_keyword}\",
  \"semantic_keywords\": [
    \"related keyword 1\",
    \"related keyword 2\",
    \"related keyword 3\"
  ],
  \"long_tail_variations\": [
    \"long tail keyword 1\",
    \"long tail keyword 2\",
    \"long tail keyword 3\"
  ],
  \"lsi_keywords\": [
    \"LSI keyword 1\",
    \"LSI keyword 2\",
    \"LSI keyword 3\"
  ]
}";
        
        $system_prompt = 'You are an expert SEO keyword researcher who identifies semantic keywords, long-tail variations, and LSI keywords. Always respond in valid JSON format.';
        
        $response = $this->makeAIRequest($prompt, $system_prompt, $selected_provider);
        
        if (is_wp_error($response)) {
            return $this->generateInternalKeywordSuggestions("Primary keyword: {$primary_keyword}\nContent: {$content}");
        }
        
        // Try to parse JSON response
        $parsed = json_decode($response, true);
        
        if (!$parsed) {
            // Try to extract JSON from response
            preg_match('/```json\s*(.*?)\s*```/s', $response, $matches);
            if (!empty($matches[1])) {
                $parsed = json_decode($matches[1], true);
            }
            
            if (!$parsed) {
                return $this->generateInternalKeywordSuggestions("Primary keyword: {$primary_keyword}\nContent: {$content}");
            }
        }
        
        // Cache the result
        set_transient($cache_key, $parsed, $this->cache_duration);
        
        return $parsed;
    }
    
    /**
     * Generate meta description
     */
    public function generateMetaDescription($title, $content, $keywords = array(), $provider = null) {
        // Get active provider
        $provider_status = $this->getAPIProviderStatus();
        $selected_provider = $provider ?: $provider_status['active_provider'];
        
        // If using internal, generate basic meta description
        if ($selected_provider === 'internal') {
            return $this->generateInternalMetaDescription($title, $content, $keywords);
        }
        
        // Check cache first
        $cache_key = 'aaiseo_meta_' . md5($title . $content . serialize($keywords) . $selected_provider);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }
        
        $keywords_text = !empty($keywords) ? "Target keywords: " . implode(', ', $keywords) : '';
        
        $prompt = "Generate multiple compelling meta descriptions for the following content:

Title: {$title}
{$keywords_text}

Content summary: " . substr(strip_tags($content), 0, 500) . "...

Requirements:
- 150-160 characters each
- Include primary keywords naturally
- Compelling and click-worthy
- Accurately describe the content

Please provide the response in JSON format:
{
  \"variations\": [
    {
      \"description\": \"Meta description variation 1\",
      \"character_count\": 155
    },
    {
      \"description\": \"Meta description variation 2\",
      \"character_count\": 158
    },
    {
      \"description\": \"Meta description variation 3\",
      \"character_count\": 152
    }
  ]
}";
        
        $system_prompt = 'You are an expert copywriter who creates compelling meta descriptions that improve click-through rates. Always respond in valid JSON format.';
        
        $response = $this->makeAIRequest($prompt, $system_prompt, $selected_provider);
        
        if (is_wp_error($response)) {
            return $this->generateInternalMetaDescription($title, $content, $keywords);
        }
        
        // Try to parse JSON response
        $parsed = json_decode($response, true);
        
        if (!$parsed) {
            // Try to extract JSON from response
            preg_match('/```json\s*(.*?)\s*```/s', $response, $matches);
            if (!empty($matches[1])) {
                $parsed = json_decode($matches[1], true);
            }
            
            if (!$parsed) {
                return $this->generateInternalMetaDescription($title, $content, $keywords);
            }
        }
        
        // Cache the result
        set_transient($cache_key, $parsed, $this->cache_duration);
        
        return $parsed;
    }
    
    /**
     * Generate internal meta description (fallback)
     */
    private function generateInternalMetaDescription($title, $content, $keywords = array()) {
        $content_summary = strip_tags($content);
        $sentences = preg_split('/[.!?]+/', $content_summary, -1, PREG_SPLIT_NO_EMPTY);
        
        // Get first meaningful sentence or create one from title
        $base_description = '';
        if (!empty($sentences[0]) && strlen(trim($sentences[0])) > 20) {
            $base_description = trim($sentences[0]);
        } else {
            $base_description = "Learn about {$title} with our comprehensive guide";
        }
        
        // Add keywords if provided
        $keyword_text = '';
        if (!empty($keywords)) {
            $keyword_text = ". Covers " . implode(', ', array_slice($keywords, 0, 3));
        }
        
        $descriptions = array();
        
        // Variation 1: Direct approach
        $desc1 = $base_description . $keyword_text . ". Expert insights and practical tips.";
        $descriptions[] = array(
            'description' => substr($desc1, 0, 160),
            'character_count' => strlen(substr($desc1, 0, 160))
        );
        
        // Variation 2: Question-based
        $desc2 = "Looking for information about {$title}? Discover everything you need to know{$keyword_text} in this detailed guide.";
        $descriptions[] = array(
            'description' => substr($desc2, 0, 160),
            'character_count' => strlen(substr($desc2, 0, 160))
        );
        
        // Variation 3: Benefit-focused
        $desc3 = "Complete guide to {$title}. Get expert tips, best practices{$keyword_text} to achieve better results.";
        $descriptions[] = array(
            'description' => substr($desc3, 0, 160),
            'character_count' => strlen(substr($desc3, 0, 160))
        );
        
        return array(
            'variations' => $descriptions
        );
    }
}