<?php
/**
 * Logs page view
 *
 * @package WP_REST_API_Key_Authentication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'api';
?>

<div class="wrap wp-rest-api-key-auth">
    <h1 class="wp-heading-inline"><?php _e('API Logs', 'wp-rest-api-key-auth'); ?></h1>
    <hr class="wp-header-end">

    <!-- Tabs -->
    <div class="logs-tabs">
        <h2 class="nav-tab-wrapper">
            <a href="?page=wp-rest-api-key-auth-logs&tab=api" class="nav-tab <?php echo $active_tab === 'api' ? 'nav-tab-active' : ''; ?>">
                <?php _e('API Requests', 'wp-rest-api-key-auth'); ?>
            </a>
            <a href="?page=wp-rest-api-key-auth-logs&tab=security" class="nav-tab <?php echo $active_tab === 'security' ? 'nav-tab-active' : ''; ?>">
                <?php _e('Security Events', 'wp-rest-api-key-auth'); ?>
            </a>
            <a href="?page=wp-rest-api-key-auth-logs&tab=system" class="nav-tab <?php echo $active_tab === 'system' ? 'nav-tab-active' : ''; ?>">
                <?php _e('System Events', 'wp-rest-api-key-auth'); ?>
            </a>
        </h2>
    </div>

    <!-- Export Actions -->
    <div class="tablenav top">
        <div class="alignleft actions">
            <button type="button" class="button export-logs" data-type="<?php echo esc_attr($active_tab); ?>" data-days="30">
                <?php _e('Export Last 30 Days', 'wp-rest-api-key-auth'); ?>
            </button>
            <button type="button" class="button export-logs" data-type="<?php echo esc_attr($active_tab); ?>" data-days="7">
                <?php _e('Export Last 7 Days', 'wp-rest-api-key-auth'); ?>
            </button>
        </div>
        <div class="alignright actions">
            <form method="post" style="display: inline;">
                <?php wp_nonce_field('wp_rest_api_key_auth_clear_logs', '_wpnonce'); ?>
                <input type="hidden" name="clear_logs" value="<?php echo esc_attr($active_tab); ?>">
                <button type="submit" class="button button-secondary" onclick="return confirm('<?php _e('Are you sure you want to clear these logs?', 'wp-rest-api-key-auth'); ?>')">
                    <?php _e('Clear Logs', 'wp-rest-api-key-auth'); ?>
                </button>
            </form>
        </div>
    </div>

    <div class="logs-container">
        <?php if ($active_tab === 'api'): ?>
            <!-- API Request Logs -->
            <div class="api-logs-tab">
                <?php if (empty($logs)): ?>
                    <div class="no-logs">
                        <h3><?php _e('No API Request Logs Found', 'wp-rest-api-key-auth'); ?></h3>
                        <p><?php _e('API request logs will appear here once API keys are used to make requests.', 'wp-rest-api-key-auth'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped logs-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php _e('Date/Time', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('API Key', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('Endpoint', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('Method', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('Response', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('IP Address', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('Execution Time', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('Memory Usage', 'wp-rest-api-key-auth'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($this->format_date($log->created_at)); ?></td>
                                    <td>
                                        <strong><?php echo esc_html($log->api_key_name ?: __('Unknown', 'wp-rest-api-key-auth')); ?></strong>
                                    </td>
                                    <td>
                                        <code><?php echo esc_html($log->endpoint); ?></code>
                                    </td>
                                    <td>
                                        <span class="method-badge method-<?php echo strtolower($log->method); ?>">
                                            <?php echo esc_html($log->method); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="response-code response-<?php echo substr($log->response_code, 0, 1); ?>xx">
                                            <?php echo esc_html($log->response_code); ?>
                                        </span>
                                        <?php if ($log->response_message): ?>
                                            <br><small><?php echo esc_html($log->response_message); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($log->ip_address); ?></td>
                                    <td><?php echo esc_html(round($log->execution_time, 4)); ?>s</td>
                                    <td><?php echo esc_html($this->format_bytes($log->memory_usage)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php elseif ($active_tab === 'security'): ?>
            <!-- Security Event Logs -->
            <div class="security-logs-tab">
                <?php if (empty($security_logs)): ?>
                    <div class="no-logs">
                        <h3><?php _e('No Security Events Found', 'wp-rest-api-key-auth'); ?></h3>
                        <p><?php _e('Security events will appear here when authentication failures or security violations occur.', 'wp-rest-api-key-auth'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped logs-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php _e('Date/Time', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('Event Type', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('Level', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('Message', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('IP Address', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('User Agent', 'wp-rest-api-key-auth'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($security_logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($this->format_date($log['timestamp'])); ?></td>
                                    <td>
                                        <code><?php echo esc_html($log['event_type']); ?></code>
                                    </td>
                                    <td>
                                        <span class="log-level <?php echo esc_attr($log['level']); ?>">
                                            <?php echo esc_html(ucfirst($log['level'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($log['message']); ?></td>
                                    <td><?php echo esc_html($log['ip_address']); ?></td>
                                    <td>
                                        <small><?php echo esc_html(substr($log['user_agent'], 0, 50)); ?><?php echo strlen($log['user_agent']) > 50 ? '...' : ''; ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- System Event Logs -->
            <div class="system-logs-tab">
                <?php 
                $system_logs = $this->logger->get_system_logs(50);
                ?>
                <?php if (empty($system_logs)): ?>
                    <div class="no-logs">
                        <h3><?php _e('No System Events Found', 'wp-rest-api-key-auth'); ?></h3>
                        <p><?php _e('System events will appear here when plugin operations occur.', 'wp-rest-api-key-auth'); ?></p>
                    </div>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped logs-table">
                        <thead>
                            <tr>
                                <th scope="col"><?php _e('Date/Time', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('Event Type', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('Level', 'wp-rest-api-key-auth'); ?></th>
                                <th scope="col"><?php _e('Message', 'wp-rest-api-key-auth'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($system_logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html($this->format_date($log['timestamp'])); ?></td>
                                    <td>
                                        <code><?php echo esc_html($log['event_type']); ?></code>
                                    </td>
                                    <td>
                                        <span class="log-level <?php echo esc_attr($log['level']); ?>">
                                            <?php echo esc_html(ucfirst($log['level'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($log['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.no-logs {
    text-align: center;
    padding: 60px 20px;
    background: #f8f9fa;
    border-radius: 4px;
    margin-top: 20px;
}

.method-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.method-get { background: #d1ecf1; color: #0c5460; }
.method-post { background: #d4edda; color: #155724; }
.method-put { background: #fff3cd; color: #856404; }
.method-delete { background: #f8d7da; color: #721c24; }

.response-code {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.response-2xx { background: #d4edda; color: #155724; }
.response-3xx { background: #d1ecf1; color: #0c5460; }
.response-4xx { background: #fff3cd; color: #856404; }
.response-5xx { background: #f8d7da; color: #721c24; }
</style> 