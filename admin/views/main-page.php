<?php
/**
 * Main admin page view
 *
 * @package WP_REST_API_Key_Authentication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wp-rest-api-key-auth">
    <h1 class="wp-heading-inline"><?php _e('API Key Management', 'wp-rest-api-key-auth'); ?></h1>
    <a href="#" class="page-title-action" id="add-new-api-key"><?php _e('Add New API Key', 'wp-rest-api-key-auth'); ?></a>
    <hr class="wp-header-end">

    <!-- Add New API Key Modal -->
    <div id="api-key-modal" class="api-key-modal" style="display: none;">
        <div class="api-key-modal-content">
            <div class="api-key-modal-header">
                <h2><?php _e('Create New API Key', 'wp-rest-api-key-auth'); ?></h2>
                <span class="api-key-modal-close">&times;</span>
            </div>
            <div class="api-key-modal-body">
                <form id="api-key-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api-key-name"><?php _e('Name', 'wp-rest-api-key-auth'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="api-key-name" name="name" class="regular-text" required>
                                <p class="description"><?php _e('A descriptive name for this API key', 'wp-rest-api-key-auth'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api-key-permissions"><?php _e('Permissions', 'wp-rest-api-key-auth'); ?></label>
                            </th>
                            <td>
                                <?php foreach ($this->get_permission_options() as $value => $label): ?>
                                    <label>
                                        <input type="checkbox" name="permissions[]" value="<?php echo esc_attr($value); ?>" <?php checked($value, 'read'); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label><br>
                                <?php endforeach; ?>
                                <p class="description"><?php _e('Select the permissions for this API key', 'wp-rest-api-key-auth'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api-key-rate-limit"><?php _e('Rate Limit', 'wp-rest-api-key-auth'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="api-key-rate-limit" name="rate_limit" value="1000" min="0" class="small-text">
                                <span><?php _e('requests per hour (0 = unlimited)', 'wp-rest-api-key-auth'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api-key-allowed-ips"><?php _e('Allowed IPs', 'wp-rest-api-key-auth'); ?></label>
                            </th>
                            <td>
                                <textarea id="api-key-allowed-ips" name="allowed_ips" rows="3" class="large-text"></textarea>
                                <p class="description"><?php _e('Comma-separated list of allowed IP addresses or CIDR ranges (leave empty for no restrictions)', 'wp-rest-api-key-auth'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api-key-allowed-domains"><?php _e('Allowed Domains', 'wp-rest-api-key-auth'); ?></label>
                            </th>
                            <td>
                                <textarea id="api-key-allowed-domains" name="allowed_domains" rows="3" class="large-text"></textarea>
                                <p class="description"><?php _e('Comma-separated list of allowed domains (supports wildcards like *.example.com)', 'wp-rest-api-key-auth'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api-key-allowed-endpoints"><?php _e('Allowed Endpoints', 'wp-rest-api-key-auth'); ?></label>
                            </th>
                            <td>
                                <textarea id="api-key-allowed-endpoints" name="allowed_endpoints" rows="3" class="large-text"></textarea>
                                <p class="description"><?php _e('Comma-separated list of allowed endpoints (supports wildcards)', 'wp-rest-api-key-auth'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api-key-blocked-endpoints"><?php _e('Blocked Endpoints', 'wp-rest-api-key-auth'); ?></label>
                            </th>
                            <td>
                                <textarea id="api-key-blocked-endpoints" name="blocked_endpoints" rows="3" class="large-text"></textarea>
                                <p class="description"><?php _e('Comma-separated list of blocked endpoints (supports wildcards)', 'wp-rest-api-key-auth'); ?></p>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
            <div class="api-key-modal-footer">
                <button type="button" class="button button-secondary" id="cancel-api-key"><?php _e('Cancel', 'wp-rest-api-key-auth'); ?></button>
                <button type="button" class="button button-primary" id="generate-api-key"><?php _e('Generate API Key', 'wp-rest-api-key-auth'); ?></button>
            </div>
        </div>
    </div>

    <!-- API Key Display Modal -->
    <div id="api-key-display-modal" class="api-key-modal" style="display: none;">
        <div class="api-key-modal-content">
            <div class="api-key-modal-header">
                <h2><?php _e('Your New API Key', 'wp-rest-api-key-auth'); ?></h2>
                <span class="api-key-modal-close">&times;</span>
            </div>
            <div class="api-key-modal-body">
                <div class="api-key-display">
                    <p class="api-key-warning">
                        <strong><?php _e('Important:', 'wp-rest-api-key-auth'); ?></strong>
                        <?php _e('Please copy this API key now. You will not be able to see it again!', 'wp-rest-api-key-auth'); ?>
                    </p>
                    <div class="api-key-value">
                        <input type="text" id="generated-api-key" readonly class="large-text">
                        <button type="button" class="button" id="copy-api-key"><?php _e('Copy', 'wp-rest-api-key-auth'); ?></button>
                    </div>
                    <div class="api-key-usage">
                        <h4><?php _e('Usage Examples:', 'wp-rest-api-key-auth'); ?></h4>
                        <p><strong><?php _e('Authorization Header:', 'wp-rest-api-key-auth'); ?></strong></p>
                        <code>Authorization: Bearer YOUR_API_KEY</code>
                        <p><strong><?php _e('X-API-Key Header:', 'wp-rest-api-key-auth'); ?></strong></p>
                        <code>X-API-Key: YOUR_API_KEY</code>
                        <p><strong><?php _e('Query Parameter:', 'wp-rest-api-key-auth'); ?></strong></p>
                        <code>?api_key=YOUR_API_KEY</code>
                    </div>
                </div>
            </div>
            <div class="api-key-modal-footer">
                <button type="button" class="button button-primary" id="close-api-key-display"><?php _e('I have copied the key', 'wp-rest-api-key-auth'); ?></button>
            </div>
        </div>
    </div>

    <!-- API Keys Table -->
    <div class="api-keys-table-container">
        <?php if (empty($api_keys)): ?>
            <div class="no-api-keys">
                <h3><?php _e('No API Keys Found', 'wp-rest-api-key-auth'); ?></h3>
                <p><?php _e('You haven\'t created any API keys yet. Click "Add New API Key" to get started.', 'wp-rest-api-key-auth'); ?></p>
            </div>
        <?php else: ?>
            <form method="post" id="api-keys-form">
                <?php wp_nonce_field('wp_rest_api_key_auth_bulk', '_wpnonce'); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value=""><?php _e('Bulk Actions', 'wp-rest-api-key-auth'); ?></option>
                            <option value="delete"><?php _e('Delete', 'wp-rest-api-key-auth'); ?></option>
                            <option value="activate"><?php _e('Activate', 'wp-rest-api-key-auth'); ?></option>
                            <option value="deactivate"><?php _e('Deactivate', 'wp-rest-api-key-auth'); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php _e('Apply', 'wp-rest-api-key-auth'); ?>">
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped api-keys-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all-1">
                            </td>
                            <th scope="col" class="manage-column column-name"><?php _e('Name', 'wp-rest-api-key-auth'); ?></th>
                            <th scope="col" class="manage-column column-status"><?php _e('Status', 'wp-rest-api-key-auth'); ?></th>
                            <th scope="col" class="manage-column column-permissions"><?php _e('Permissions', 'wp-rest-api-key-auth'); ?></th>
                            <th scope="col" class="manage-column column-rate-limit"><?php _e('Rate Limit', 'wp-rest-api-key-auth'); ?></th>
                            <th scope="col" class="manage-column column-last-used"><?php _e('Last Used', 'wp-rest-api-key-auth'); ?></th>
                            <th scope="col" class="manage-column column-requests"><?php _e('Requests', 'wp-rest-api-key-auth'); ?></th>
                            <th scope="col" class="manage-column column-created"><?php _e('Created', 'wp-rest-api-key-auth'); ?></th>
                            <th scope="col" class="manage-column column-actions"><?php _e('Actions', 'wp-rest-api-key-auth'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($api_keys as $key): ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="api_keys[]" value="<?php echo esc_attr($key->id); ?>">
                                </th>
                                <td class="column-name">
                                    <strong><?php echo esc_html($key->name); ?></strong>
                                    <?php if ($is_admin): ?>
                                        <br><small><?php printf(__('User: %s', 'wp-rest-api-key-auth'), get_userdata($key->user_id)->display_name); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-status">
                                    <?php echo $this->get_status_badge($key->status); ?>
                                    <?php if ($key->expires_at && $key->expires_at !== '0000-00-00 00:00:00'): ?>
                                        <br><small><?php printf(__('Expires: %s', 'wp-rest-api-key-auth'), $this->format_date($key->expires_at)); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-permissions">
                                    <?php
                                    $permissions = json_decode($key->permissions, true);
                                    if (is_array($permissions)) {
                                        echo esc_html(implode(', ', array_map('ucfirst', $permissions)));
                                    } else {
                                        echo __('All', 'wp-rest-api-key-auth');
                                    }
                                    ?>
                                </td>
                                <td class="column-rate-limit">
                                    <?php echo $key->rate_limit > 0 ? $this->format_number($key->rate_limit) . '/hr' : __('Unlimited', 'wp-rest-api-key-auth'); ?>
                                </td>
                                <td class="column-last-used">
                                    <?php echo $this->format_date($key->last_used); ?>
                                    <?php if ($key->last_ip): ?>
                                        <br><small><?php echo esc_html($key->last_ip); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-requests">
                                    <?php echo $this->format_number($key->request_count); ?>
                                </td>
                                <td class="column-created">
                                    <?php echo $this->format_date($key->created_at); ?>
                                </td>
                                <td class="column-actions">
                                    <div class="row-actions">
                                        <span class="edit">
                                            <a href="#" class="edit-api-key" data-id="<?php echo esc_attr($key->id); ?>"><?php _e('Edit', 'wp-rest-api-key-auth'); ?></a> |
                                        </span>
                                        <?php if ($key->status === 'active'): ?>
                                            <span class="deactivate">
                                                <a href="#" class="toggle-api-key" data-id="<?php echo esc_attr($key->id); ?>" data-action="deactivate"><?php _e('Deactivate', 'wp-rest-api-key-auth'); ?></a> |
                                            </span>
                                        <?php else: ?>
                                            <span class="activate">
                                                <a href="#" class="toggle-api-key" data-id="<?php echo esc_attr($key->id); ?>" data-action="activate"><?php _e('Activate', 'wp-rest-api-key-auth'); ?></a> |
                                            </span>
                                        <?php endif; ?>
                                        <span class="delete">
                                            <a href="#" class="delete-api-key" data-id="<?php echo esc_attr($key->id); ?>" data-name="<?php echo esc_attr($key->name); ?>"><?php _e('Delete', 'wp-rest-api-key-auth'); ?></a>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
        <?php endif; ?>
    </div>

    <!-- Quick Stats -->
    <div class="api-key-stats">
        <h3><?php _e('Quick Statistics', 'wp-rest-api-key-auth'); ?></h3>
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?php echo count($api_keys); ?></div>
                <div class="stat-label"><?php _e('Total API Keys', 'wp-rest-api-key-auth'); ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo count(array_filter($api_keys, function($key) { return $key->status === 'active'; })); ?></div>
                <div class="stat-label"><?php _e('Active Keys', 'wp-rest-api-key-auth'); ?></div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo array_sum(array_column($api_keys, 'request_count')); ?></div>
                <div class="stat-label"><?php _e('Total Requests', 'wp-rest-api-key-auth'); ?></div>
            </div>
        </div>
    </div>
</div> 