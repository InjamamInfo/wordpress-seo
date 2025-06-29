<?php
/**
 * Basic Admin Interface for Autonomous AI SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AAISEO_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_aaiseo_test_api', array($this, 'test_api_connection'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Autonomous AI SEO', 'autonomous-ai-seo'),
            __('AI SEO', 'autonomous-ai-seo'),
            'manage_options',
            'aaiseo-dashboard',
            array($this, 'admin_page'),
            'dashicons-search',
            30
        );
        
        add_submenu_page(
            'aaiseo-dashboard',
            __('Settings', 'autonomous-ai-seo'),
            __('Settings', 'autonomous-ai-seo'),
            'manage_options',
            'aaiseo-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('aaiseo_settings', 'aaiseo_settings', array($this, 'sanitize_settings'));
        
        add_settings_section(
            'aaiseo_api_section',
            __('AI Provider Settings', 'autonomous-ai-seo'),
            array($this, 'api_section_callback'),
            'aaiseo_settings'
        );
        
        add_settings_field(
            'preferred_ai_provider',
            __('Preferred AI Provider', 'autonomous-ai-seo'),
            array($this, 'provider_field_callback'),
            'aaiseo_settings',
            'aaiseo_api_section'
        );
        
        add_settings_field(
            'openai_api_key',
            __('OpenAI API Key', 'autonomous-ai-seo'),
            array($this, 'openai_field_callback'),
            'aaiseo_settings',
            'aaiseo_api_section'
        );
        
        add_settings_field(
            'gemini_api_key',
            __('Google Gemini API Key', 'autonomous-ai-seo'),
            array($this, 'gemini_field_callback'),
            'aaiseo_settings',
            'aaiseo_api_section'
        );
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['preferred_ai_provider'])) {
            $allowed_providers = array('internal', 'openai', 'gemini', 'grok', 'deepseek');
            $provider = sanitize_text_field($input['preferred_ai_provider']);
            $sanitized['preferred_ai_provider'] = in_array($provider, $allowed_providers) ? $provider : 'internal';
        }
        
        if (isset($input['openai_api_key'])) {
            $api_key = sanitize_text_field($input['openai_api_key']);
            // Basic validation for OpenAI API key format
            if (empty($api_key) || preg_match('/^sk-[a-zA-Z0-9]{48}$/', $api_key) || preg_match('/^sk-proj-[a-zA-Z0-9_-]{48,}$/', $api_key)) {
                $sanitized['openai_api_key'] = $api_key;
            } else {
                add_settings_error('aaiseo_settings', 'invalid_openai_key', __('Invalid OpenAI API key format.', 'autonomous-ai-seo'));
                $sanitized['openai_api_key'] = '';
            }
        }
        
        if (isset($input['gemini_api_key'])) {
            $api_key = sanitize_text_field($input['gemini_api_key']);
            // Basic validation for Gemini API key format
            if (empty($api_key) || preg_match('/^[a-zA-Z0-9_-]{39}$/', $api_key)) {
                $sanitized['gemini_api_key'] = $api_key;
            } else {
                add_settings_error('aaiseo_settings', 'invalid_gemini_key', __('Invalid Gemini API key format.', 'autonomous-ai-seo'));
                $sanitized['gemini_api_key'] = '';
            }
        }
        
        if (isset($input['grok_api_key'])) {
            $sanitized['grok_api_key'] = sanitize_text_field($input['grok_api_key']);
        }
        
        if (isset($input['deepseek_api_key'])) {
            $sanitized['deepseek_api_key'] = sanitize_text_field($input['deepseek_api_key']);
        }
        
        // Preserve existing settings not in this form
        $existing_options = get_option('aaiseo_settings', array());
        $sanitized = array_merge($existing_options, $sanitized);
        
        return $sanitized;
    }
    
    /**
     * Admin dashboard page
     */
    public function admin_page() {
        $ai_engine = AAISEO_AI_Engine::getInstance();
        $provider_status = $ai_engine->getAPIProviderStatus();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="aaiseo-dashboard">
                <h2><?php _e('AI Provider Status', 'autonomous-ai-seo'); ?></h2>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Provider', 'autonomous-ai-seo'); ?></th>
                            <th><?php _e('Status', 'autonomous-ai-seo'); ?></th>
                            <th><?php _e('Active', 'autonomous-ai-seo'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($provider_status['providers'] as $key => $provider): ?>
                        <tr>
                            <td><?php echo esc_html($provider['name']); ?></td>
                            <td>
                                <span class="status-<?php echo $provider['available'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $provider['available'] ? __('Configured', 'autonomous-ai-seo') : __('Not Configured', 'autonomous-ai-seo'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($provider['is_active']): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-minus" style="color: #ccc;"></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <p>
                    <strong><?php _e('Active Provider:', 'autonomous-ai-seo'); ?></strong> 
                    <?php echo esc_html($provider_status['providers'][$provider_status['active_provider']]['name']); ?>
                </p>
                
                <p>
                    <a href="<?php echo admin_url('admin.php?page=aaiseo-settings'); ?>" class="button button-primary">
                        <?php _e('Configure Settings', 'autonomous-ai-seo'); ?>
                    </a>
                </p>
            </div>
        </div>
        
        <style>
        .status-active { color: green; font-weight: bold; }
        .status-inactive { color: #ccc; }
        .aaiseo-dashboard { margin-top: 20px; }
        </style>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('aaiseo_settings');
                do_settings_sections('aaiseo_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * API section callback
     */
    public function api_section_callback() {
        echo '<p>' . __('Configure your AI provider API keys. The plugin will automatically use the preferred provider if available, or fall back to internal algorithms.', 'autonomous-ai-seo') . '</p>';
    }
    
    /**
     * Provider selection field
     */
    public function provider_field_callback() {
        $options = get_option('aaiseo_settings', array());
        $preferred = isset($options['preferred_ai_provider']) ? $options['preferred_ai_provider'] : 'internal';
        ?>
        <select name="aaiseo_settings[preferred_ai_provider]">
            <option value="internal" <?php selected($preferred, 'internal'); ?>><?php _e('Internal AI (Always Available)', 'autonomous-ai-seo'); ?></option>
            <option value="openai" <?php selected($preferred, 'openai'); ?>><?php _e('OpenAI (GPT)', 'autonomous-ai-seo'); ?></option>
            <option value="gemini" <?php selected($preferred, 'gemini'); ?>><?php _e('Google Gemini', 'autonomous-ai-seo'); ?></option>
            <option value="grok" <?php selected($preferred, 'grok'); ?>><?php _e('Grok (Coming Soon)', 'autonomous-ai-seo'); ?></option>
            <option value="deepseek" <?php selected($preferred, 'deepseek'); ?>><?php _e('DeepSeek (Coming Soon)', 'autonomous-ai-seo'); ?></option>
        </select>
        <p class="description"><?php _e('Choose your preferred AI provider. If not available, the plugin will automatically fall back to other providers or internal algorithms.', 'autonomous-ai-seo'); ?></p>
        <?php
    }
    
    /**
     * OpenAI API key field
     */
    public function openai_field_callback() {
        $options = get_option('aaiseo_settings', array());
        $value = isset($options['openai_api_key']) ? $options['openai_api_key'] : '';
        ?>
        <input type="password" name="aaiseo_settings[openai_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php _e('Get your API key from OpenAI dashboard.', 'autonomous-ai-seo'); ?></p>
        <?php
    }
    
    /**
     * Gemini API key field
     */
    public function gemini_field_callback() {
        $options = get_option('aaiseo_settings', array());
        $value = isset($options['gemini_api_key']) ? $options['gemini_api_key'] : '';
        ?>
        <input type="password" name="aaiseo_settings[gemini_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text" />
        <p class="description"><?php _e('Get your API key from Google AI Studio.', 'autonomous-ai-seo'); ?></p>
        <?php
    }
    
    /**
     * Test API connection
     */
    public function test_api_connection() {
        check_ajax_referer('aaiseo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions.', 'autonomous-ai-seo'));
        }
        
        $provider = sanitize_text_field($_POST['provider']);
        
        if (!in_array($provider, array('openai', 'gemini', 'grok', 'deepseek'))) {
            wp_send_json_error(__('Invalid provider.', 'autonomous-ai-seo'));
        }
        
        $ai_engine = AAISEO_AI_Engine::getInstance();
        
        // Simple test prompt
        $result = $ai_engine->makeAIRequest('Test connection', 'You are a helpful assistant. Respond with "Connection successful" if you receive this message.', $provider);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            wp_send_json_success(__('Connection successful!', 'autonomous-ai-seo'));
        }
    }
}

// Initialize admin interface
if (is_admin()) {
    new AAISEO_Admin();
}