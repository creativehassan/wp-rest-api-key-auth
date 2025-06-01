<?php
/**
 * Settings page view
 *
 * @package WP_REST_API_Key_Authentication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$key_length = get_option('wp_rest_api_key_auth_key_length', 64);
$key_expiry = get_option('wp_rest_api_key_auth_key_expiry', 365);
$rate_limit = get_option('wp_rest_api_key_auth_rate_limit', 1000);
$log_requests = get_option('wp_rest_api_key_auth_log_requests', true);
$require_https = get_option('wp_rest_api_key_auth_require_https', true);
$cors_origins = get_option('wp_rest_api_key_auth_cors_origins', array());
$public_endpoints = get_option('wp_rest_api_key_auth_public_endpoints', array());
?>

<div class="wrap wp-rest-api-key-auth">
    <h1 class="wp-heading-inline"><?php _e('API Key Authentication Settings', 'wp-rest-api-key-auth'); ?></h1>
    <hr class="wp-header-end">

    <form method="post" action="">
        <?php wp_nonce_field('wp_rest_api_key_auth_settings', '_wpnonce'); ?>
        <input type="hidden" name="save_settings" value="1">

        <!-- Security Settings -->
        <div class="settings-section">
            <h3><?php _e('Security Settings', 'wp-rest-api-key-auth'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="key_length"><?php _e('API Key Length', 'wp-rest-api-key-auth'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="key_length" name="key_length" value="<?php echo esc_attr($key_length); ?>" min="32" max="128" class="small-text">
                        <p class="description"><?php _e('Length of generated API keys in characters (minimum 32 for security)', 'wp-rest-api-key-auth'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="key_expiry"><?php _e('Default Key Expiry', 'wp-rest-api-key-auth'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="key_expiry" name="key_expiry" value="<?php echo esc_attr($key_expiry); ?>" min="0" class="small-text">
                        <span><?php _e('days (0 = never expires)', 'wp-rest-api-key-auth'); ?></span>
                        <p class="description"><?php _e('Default expiration period for new API keys', 'wp-rest-api-key-auth'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="require_https"><?php _e('Require HTTPS', 'wp-rest-api-key-auth'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="require_https" name="require_https" value="1" <?php checked($require_https); ?>>
                            <?php _e('Require HTTPS for all API requests', 'wp-rest-api-key-auth'); ?>
                        </label>
                        <p class="description"><?php _e('Highly recommended for production environments to ensure API keys are transmitted securely', 'wp-rest-api-key-auth'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Rate Limiting -->
        <div class="settings-section">
            <h3><?php _e('Rate Limiting', 'wp-rest-api-key-auth'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="rate_limit"><?php _e('Default Rate Limit', 'wp-rest-api-key-auth'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="rate_limit" name="rate_limit" value="<?php echo esc_attr($rate_limit); ?>" min="0" class="small-text">
                        <span><?php _e('requests per hour (0 = unlimited)', 'wp-rest-api-key-auth'); ?></span>
                        <p class="description"><?php _e('Default rate limit for new API keys. Individual keys can have custom limits.', 'wp-rest-api-key-auth'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Logging Settings -->
        <div class="settings-section">
            <h3><?php _e('Logging & Monitoring', 'wp-rest-api-key-auth'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="log_requests"><?php _e('Log API Requests', 'wp-rest-api-key-auth'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="log_requests" name="log_requests" value="1" <?php checked($log_requests); ?>>
                            <?php _e('Log all API requests for monitoring and analytics', 'wp-rest-api-key-auth'); ?>
                        </label>
                        <p class="description"><?php _e('Logs include endpoint, method, response code, execution time, and memory usage. Sensitive data is automatically sanitized.', 'wp-rest-api-key-auth'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- CORS Settings -->
        <div class="settings-section">
            <h3><?php _e('CORS (Cross-Origin Resource Sharing)', 'wp-rest-api-key-auth'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cors_origins"><?php _e('Allowed Origins', 'wp-rest-api-key-auth'); ?></label>
                    </th>
                    <td>
                        <textarea id="cors_origins" name="cors_origins" rows="5" class="large-text"><?php echo esc_textarea(implode("\n", $cors_origins)); ?></textarea>
                        <p class="description">
                            <?php _e('One origin per line. Use * to allow all origins (not recommended for production).', 'wp-rest-api-key-auth'); ?><br>
                            <?php _e('Examples:', 'wp-rest-api-key-auth'); ?><br>
                            <code>https://example.com</code><br>
                            <code>https://app.example.com</code><br>
                            <code>*</code> <?php _e('(allow all - use with caution)', 'wp-rest-api-key-auth'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Public Endpoints -->
        <div class="settings-section">
            <h3><?php _e('Public Endpoints', 'wp-rest-api-key-auth'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="public_endpoints"><?php _e('Endpoints Without Authentication', 'wp-rest-api-key-auth'); ?></label>
                    </th>
                    <td>
                        <textarea id="public_endpoints" name="public_endpoints" rows="5" class="large-text"><?php echo esc_textarea(implode("\n", $public_endpoints)); ?></textarea>
                        <p class="description">
                            <?php _e('One endpoint per line. These endpoints will not require API key authentication.', 'wp-rest-api-key-auth'); ?><br>
                            <?php _e('Examples:', 'wp-rest-api-key-auth'); ?><br>
                            <code>wp/v2/posts</code> <?php _e('(allow public access to posts)', 'wp-rest-api-key-auth'); ?><br>
                            <code>wp/v2/users</code> <?php _e('(allow public access to users)', 'wp-rest-api-key-auth'); ?><br>
                            <code>custom/v1/public</code> <?php _e('(custom public endpoint)', 'wp-rest-api-key-auth'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- System Information -->
        <div class="settings-section">
            <h3><?php _e('System Information', 'wp-rest-api-key-auth'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Plugin Version', 'wp-rest-api-key-auth'); ?></th>
                    <td><code><?php echo esc_html(WP_REST_API_KEY_AUTH_VERSION); ?></code></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('WordPress Version', 'wp-rest-api-key-auth'); ?></th>
                    <td><code><?php echo esc_html(get_bloginfo('version')); ?></code></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('PHP Version', 'wp-rest-api-key-auth'); ?></th>
                    <td><code><?php echo esc_html(PHP_VERSION); ?></code></td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('HTTPS Status', 'wp-rest-api-key-auth'); ?></th>
                    <td>
                        <?php if (is_ssl()): ?>
                            <span class="status-badge status-active"><?php _e('Enabled', 'wp-rest-api-key-auth'); ?></span>
                        <?php else: ?>
                            <span class="status-badge status-inactive"><?php _e('Disabled', 'wp-rest-api-key-auth'); ?></span>
                            <?php if ($require_https): ?>
                                <p class="description" style="color: #d63638;">
                                    <?php _e('Warning: HTTPS is required but not enabled. API requests will fail.', 'wp-rest-api-key-auth'); ?>
                                </p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Password Hashing', 'wp-rest-api-key-auth'); ?></th>
                    <td>
                        <?php if (defined('PASSWORD_ARGON2ID')): ?>
                            <span class="status-badge status-active">Argon2ID</span>
                            <p class="description"><?php _e('Using secure Argon2ID password hashing', 'wp-rest-api-key-auth'); ?></p>
                        <?php else: ?>
                            <span class="status-badge status-inactive">Fallback</span>
                            <p class="description"><?php _e('Argon2ID not available, using fallback hashing', 'wp-rest-api-key-auth'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Database Tables', 'wp-rest-api-key-auth'); ?></th>
                    <td>
                        <?php
                        global $wpdb;
                        $api_keys_table = $wpdb->prefix . WP_REST_API_KEY_AUTH_TABLE_NAME;
                        $logs_table = $wpdb->prefix . WP_REST_API_KEY_AUTH_TABLE_NAME . '_logs';
                        
                        $api_keys_exists = $wpdb->get_var("SHOW TABLES LIKE '$api_keys_table'") === $api_keys_table;
                        $logs_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'") === $logs_table;
                        ?>
                        
                        <p>
                            <strong><?php _e('API Keys Table:', 'wp-rest-api-key-auth'); ?></strong>
                            <?php if ($api_keys_exists): ?>
                                <span class="status-badge status-active"><?php _e('Exists', 'wp-rest-api-key-auth'); ?></span>
                            <?php else: ?>
                                <span class="status-badge status-inactive"><?php _e('Missing', 'wp-rest-api-key-auth'); ?></span>
                            <?php endif; ?>
                        </p>
                        
                        <p>
                            <strong><?php _e('Logs Table:', 'wp-rest-api-key-auth'); ?></strong>
                            <?php if ($logs_exists): ?>
                                <span class="status-badge status-active"><?php _e('Exists', 'wp-rest-api-key-auth'); ?></span>
                            <?php else: ?>
                                <span class="status-badge status-inactive"><?php _e('Missing', 'wp-rest-api-key-auth'); ?></span>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Maintenance Actions -->
        <div class="settings-section">
            <h3><?php _e('Maintenance', 'wp-rest-api-key-auth'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Database Cleanup', 'wp-rest-api-key-auth'); ?></th>
                    <td>
                        <p class="description">
                            <?php _e('The plugin automatically cleans up expired keys and old logs daily. You can also run cleanup manually:', 'wp-rest-api-key-auth'); ?>
                        </p>
                        <p>
                            <button type="button" class="button button-secondary" id="run-cleanup">
                                <?php _e('Run Cleanup Now', 'wp-rest-api-key-auth'); ?>
                            </button>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Export Settings', 'wp-rest-api-key-auth'); ?></th>
                    <td>
                        <p class="description">
                            <?php _e('Export your plugin settings for backup or migration:', 'wp-rest-api-key-auth'); ?>
                        </p>
                        <p>
                            <button type="button" class="button button-secondary" id="export-settings">
                                <?php _e('Export Settings', 'wp-rest-api-key-auth'); ?>
                            </button>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(__('Save Settings', 'wp-rest-api-key-auth')); ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Run cleanup
    $('#run-cleanup').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e('Running...', 'wp-rest-api-key-auth'); ?>');
        
        $.post(ajaxurl, {
            action: 'wp_rest_api_key_auth_run_cleanup',
            nonce: '<?php echo wp_create_nonce('wp_rest_api_key_auth_nonce'); ?>'
        })
        .done(function(response) {
            if (response.success) {
                alert('<?php _e('Cleanup completed successfully!', 'wp-rest-api-key-auth'); ?>');
            } else {
                alert('<?php _e('Cleanup failed. Please try again.', 'wp-rest-api-key-auth'); ?>');
            }
        })
        .fail(function() {
            alert('<?php _e('Cleanup failed. Please try again.', 'wp-rest-api-key-auth'); ?>');
        })
        .always(function() {
            $button.prop('disabled', false).text('<?php _e('Run Cleanup Now', 'wp-rest-api-key-auth'); ?>');
        });
    });
    
    // Export settings
    $('#export-settings').on('click', function() {
        var settings = {
            key_length: $('#key_length').val(),
            key_expiry: $('#key_expiry').val(),
            rate_limit: $('#rate_limit').val(),
            log_requests: $('#log_requests').is(':checked'),
            require_https: $('#require_https').is(':checked'),
            cors_origins: $('#cors_origins').val().split('\n').filter(function(line) { return line.trim(); }),
            public_endpoints: $('#public_endpoints').val().split('\n').filter(function(line) { return line.trim(); })
        };
        
        var dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(settings, null, 2));
        var downloadAnchorNode = document.createElement('a');
        downloadAnchorNode.setAttribute("href", dataStr);
        downloadAnchorNode.setAttribute("download", "wp-rest-api-key-auth-settings.json");
        document.body.appendChild(downloadAnchorNode);
        downloadAnchorNode.click();
        downloadAnchorNode.remove();
    });
});
</script>

<style>
.settings-section {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.settings-section h3 {
    margin-top: 0;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    color: #333;
}

.form-table th {
    width: 200px;
    font-weight: 600;
}

.form-table .description {
    margin-top: 5px;
    font-style: italic;
    color: #666;
}

.form-table code {
    background: #f1f1f1;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 13px;
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-active {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.status-inactive {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.button.loading {
    position: relative;
    color: transparent !important;
}

.button.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 16px;
    height: 16px;
    margin: -8px 0 0 -8px;
    border: 2px solid #fff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style> 