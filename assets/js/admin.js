/* Autonomous AI SEO Admin JavaScript */

jQuery(document).ready(function($) {
    
    // Test API connection
    $('.aaiseo-test-api').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var provider = $button.data('provider');
        var $status = $button.siblings('.api-status');
        
        $button.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: aaiseo_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'aaiseo_test_api',
                provider: provider,
                nonce: aaiseo_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;">✓ Connected</span>');
                } else {
                    $status.html('<span style="color: red;">✗ Failed: ' + response.data + '</span>');
                }
            },
            error: function() {
                $status.html('<span style="color: red;">✗ Connection Error</span>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Connection');
                
                // Clear status after 5 seconds
                setTimeout(function() {
                    $status.html('');
                }, 5000);
            }
        });
    });
    
    // Show/hide API key fields based on provider selection
    $('#preferred_ai_provider').on('change', function() {
        var selectedProvider = $(this).val();
        
        $('.api-key-field').hide();
        
        if (selectedProvider !== 'internal') {
            $('#' + selectedProvider + '_api_key_field').show();
        }
    }).trigger('change');
    
    // Toggle password visibility
    $('.toggle-password').on('click', function() {
        var $input = $(this).siblings('input');
        var type = $input.attr('type') === 'password' ? 'text' : 'password';
        
        $input.attr('type', type);
        $(this).text(type === 'password' ? 'Show' : 'Hide');
    });
    
});