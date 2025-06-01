<?php
/**
 * Admin handler for WP REST API Key Authentication
 *
 * @package WP_REST_API_Key_Authentication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin handler class
 */
class WP_REST_API_Key_Admin {
    
    /**
     * Database handler
     */
    private $db;
    
    /**
     * Security handler
     */
    private $security;
    
    /**
     * Logger handler
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct($db, $security, $logger) {
        $this->db = $db;
        $this->security = $security;
        $this->logger = $logger;
    }
    
    /**
     * Initialize admin
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        add_action('wp_ajax_wp_rest_api_key_auth_generate_key', array($this, 'ajax_generate_key'));
        add_action('wp_ajax_wp_rest_api_key_auth_delete_key', array($this, 'ajax_delete_key'));
        add_action('wp_ajax_wp_rest_api_key_auth_update_key', array($this, 'ajax_update_key'));
        add_action('wp_ajax_wp_rest_api_key_auth_export_logs', array($this, 'ajax_export_logs'));
        add_action('wp_ajax_wp_rest_api_key_auth_run_cleanup', array($this, 'ajax_run_cleanup'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . WP_REST_API_KEY_AUTH_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
        
        // Add admin notices
        add_action('admin_notices', array($this, 'show_admin_notices'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __('API Key Authentication', 'wp-rest-api-key-auth'),
            __('API Keys', 'wp-rest-api-key-auth'),
            'manage_api_keys',
            'wp-rest-api-key-auth',
            array($this, 'render_main_page'),
            'dashicons-admin-network',
            30
        );
        
        // Submenu pages
        add_submenu_page(
            'wp-rest-api-key-auth',
            __('Manage API Keys', 'wp-rest-api-key-auth'),
            __('Manage Keys', 'wp-rest-api-key-auth'),
            'manage_api_keys',
            'wp-rest-api-key-auth',
            array($this, 'render_main_page')
        );
        
        add_submenu_page(
            'wp-rest-api-key-auth',
            __('API Logs', 'wp-rest-api-key-auth'),
            __('Logs', 'wp-rest-api-key-auth'),
            'view_api_logs',
            'wp-rest-api-key-auth-logs',
            array($this, 'render_logs_page')
        );
        
        add_submenu_page(
            'wp-rest-api-key-auth',
            __('Statistics', 'wp-rest-api-key-auth'),
            __('Statistics', 'wp-rest-api-key-auth'),
            'view_api_logs',
            'wp-rest-api-key-auth-stats',
            array($this, 'render_stats_page')
        );
        
        add_submenu_page(
            'wp-rest-api-key-auth',
            __('Settings', 'wp-rest-api-key-auth'),
            __('Settings', 'wp-rest-api-key-auth'),
            'manage_options',
            'wp-rest-api-key-auth-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'wp-rest-api-key-auth',
            __('API Documentation', 'wp-rest-api-key-auth'),
            __('Documentation', 'wp-rest-api-key-auth'),
            'read',
            'wp-rest-api-key-auth-docs',
            array($this, 'render_documentation_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'wp-rest-api-key-auth') === false) {
            return;
        }
        
        wp_enqueue_style(
            'wp-rest-api-key-auth-admin',
            WP_REST_API_KEY_AUTH_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WP_REST_API_KEY_AUTH_VERSION
        );
        
        wp_enqueue_script(
            'wp-rest-api-key-auth-admin',
            WP_REST_API_KEY_AUTH_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            WP_REST_API_KEY_AUTH_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('wp-rest-api-key-auth-admin', 'wpRestApiKeyAuth', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_rest_api_key_auth_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this API key?', 'wp-rest-api-key-auth'),
                'copySuccess' => __('API key copied to clipboard!', 'wp-rest-api-key-auth'),
                'copyError' => __('Failed to copy API key. Please copy manually.', 'wp-rest-api-key-auth'),
                'generating' => __('Generating...', 'wp-rest-api-key-auth'),
                'deleting' => __('Deleting...', 'wp-rest-api-key-auth'),
                'updating' => __('Updating...', 'wp-rest-api-key-auth'),
                'error' => __('An error occurred. Please try again.', 'wp-rest-api-key-auth'),
            ),
        ));
        
        // Chart.js for statistics
        if ($hook === 'api-keys_page_wp-rest-api-key-auth-stats') {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                array(),
                '3.9.1',
                true
            );
        }
    }
    
    /**
     * Handle admin actions
     */
    public function handle_admin_actions() {
        if (!current_user_can('manage_api_keys')) {
            return;
        }
        
        // Handle settings save
        if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'wp_rest_api_key_auth_settings')) {
            $this->save_settings();
            wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
            exit;
        }
        
        // Handle bulk actions
        if (isset($_POST['bulk_action']) && isset($_POST['api_keys']) && wp_verify_nonce($_POST['_wpnonce'], 'wp_rest_api_key_auth_bulk')) {
            $this->handle_bulk_actions();
        }
    }
    
    /**
     * Render main page
     */
    public function render_main_page() {
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        
        // Get API keys
        if ($is_admin) {
            $api_keys = $this->db->get_all_api_keys(50, 0);
        } else {
            $api_keys = $this->db->get_user_api_keys($current_user_id, 50, 0);
        }
        
        include WP_REST_API_KEY_AUTH_PLUGIN_DIR . 'admin/views/main-page.php';
    }
    
    /**
     * Render logs page
     */
    public function render_logs_page() {
        $logs = $this->db->get_logs(null, 100, 0);
        $security_logs = $this->logger->get_security_logs(50);
        
        include WP_REST_API_KEY_AUTH_PLUGIN_DIR . 'admin/views/logs-page.php';
    }
    
    /**
     * Render statistics page
     */
    public function render_stats_page() {
        $stats = $this->logger->get_api_usage_stats(30);
        $top_endpoints = $this->logger->get_top_endpoints(10, 30);
        $error_rates = $this->logger->get_error_rate_stats(7);
        
        include WP_REST_API_KEY_AUTH_PLUGIN_DIR . 'admin/views/stats-page.php';
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        include WP_REST_API_KEY_AUTH_PLUGIN_DIR . 'admin/views/settings-page.php';
    }
    
    /**
     * Render documentation page
     */
    public function render_documentation_page() {
        include WP_REST_API_KEY_AUTH_PLUGIN_DIR . 'admin/views/documentation-page.php';
    }
    
    /**
     * AJAX: Generate API key
     */
    public function ajax_generate_key() {
        check_ajax_referer('wp_rest_api_key_auth_nonce', 'nonce');
        
        if (!current_user_can('manage_api_keys')) {
            wp_die(__('Insufficient permissions', 'wp-rest-api-key-auth'));
        }
        
        $name = $this->security->sanitize_api_key_name($_POST['name']);
        if (!$name) {
            wp_send_json_error(__('Invalid API key name', 'wp-rest-api-key-auth'));
        }
        
        // Generate API key
        $api_key = $this->security->generate_api_key();
        $api_key_hash = $this->security->hash_api_key($api_key);
        
        // Prepare data
        $data = array(
            'name' => $name,
            'api_key' => $api_key,
            'api_key_hash' => $api_key_hash,
            'user_id' => get_current_user_id(),
            'permissions' => json_encode(isset($_POST['permissions']) ? $_POST['permissions'] : array('read')),
            'rate_limit' => intval($_POST['rate_limit'] ?? 1000),
            'allowed_ips' => $this->security->sanitize_ip_list($_POST['allowed_ips'] ?? ''),
            'allowed_domains' => $this->security->sanitize_domain_list($_POST['allowed_domains'] ?? ''),
            'allowed_endpoints' => $this->security->sanitize_endpoint_list($_POST['allowed_endpoints'] ?? ''),
            'blocked_endpoints' => $this->security->sanitize_endpoint_list($_POST['blocked_endpoints'] ?? ''),
        );
        
        $result = $this->db->create_api_key($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        $this->logger->log_system_event(
            'api_key_created',
            'API key created: ' . $name,
            WP_REST_API_Key_Logger::LOG_LEVEL_INFO
        );
        
        wp_send_json_success(array(
            'id' => $result,
            'api_key' => $api_key,
            'message' => __('API key created successfully!', 'wp-rest-api-key-auth'),
        ));
    }
    
    /**
     * AJAX: Delete API key
     */
    public function ajax_delete_key() {
        check_ajax_referer('wp_rest_api_key_auth_nonce', 'nonce');
        
        if (!current_user_can('manage_api_keys')) {
            wp_die(__('Insufficient permissions', 'wp-rest-api-key-auth'));
        }
        
        $id = intval($_POST['id']);
        $api_key = $this->db->get_api_key($id);
        
        if (!$api_key) {
            wp_send_json_error(__('API key not found', 'wp-rest-api-key-auth'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options') && $api_key->user_id != get_current_user_id()) {
            wp_send_json_error(__('You do not have permission to delete this API key', 'wp-rest-api-key-auth'));
        }
        
        $result = $this->db->delete_api_key($id);
        
        if (!$result) {
            wp_send_json_error(__('Failed to delete API key', 'wp-rest-api-key-auth'));
        }
        
        $this->logger->log_system_event(
            'api_key_deleted',
            'API key deleted: ' . $api_key->name,
            WP_REST_API_Key_Logger::LOG_LEVEL_INFO
        );
        
        wp_send_json_success(__('API key deleted successfully!', 'wp-rest-api-key-auth'));
    }
    
    /**
     * AJAX: Update API key
     */
    public function ajax_update_key() {
        check_ajax_referer('wp_rest_api_key_auth_nonce', 'nonce');
        
        if (!current_user_can('manage_api_keys')) {
            wp_die(__('Insufficient permissions', 'wp-rest-api-key-auth'));
        }
        
        $id = intval($_POST['id']);
        $api_key = $this->db->get_api_key($id);
        
        if (!$api_key) {
            wp_send_json_error(__('API key not found', 'wp-rest-api-key-auth'));
        }
        
        // Check permissions
        if (!current_user_can('manage_options') && $api_key->user_id != get_current_user_id()) {
            wp_send_json_error(__('You do not have permission to update this API key', 'wp-rest-api-key-auth'));
        }
        
        $data = array();
        
        if (isset($_POST['name'])) {
            $name = $this->security->sanitize_api_key_name($_POST['name']);
            if (!$name) {
                wp_send_json_error(__('Invalid API key name', 'wp-rest-api-key-auth'));
            }
            $data['name'] = $name;
        }
        
        if (isset($_POST['status'])) {
            $data['status'] = in_array($_POST['status'], array('active', 'inactive')) ? $_POST['status'] : 'active';
        }
        
        if (isset($_POST['permissions'])) {
            $data['permissions'] = json_encode($_POST['permissions']);
        }
        
        if (isset($_POST['rate_limit'])) {
            $data['rate_limit'] = intval($_POST['rate_limit']);
        }
        
        if (isset($_POST['allowed_ips'])) {
            $data['allowed_ips'] = $this->security->sanitize_ip_list($_POST['allowed_ips']);
        }
        
        if (isset($_POST['allowed_domains'])) {
            $data['allowed_domains'] = $this->security->sanitize_domain_list($_POST['allowed_domains']);
        }
        
        if (isset($_POST['allowed_endpoints'])) {
            $data['allowed_endpoints'] = $this->security->sanitize_endpoint_list($_POST['allowed_endpoints']);
        }
        
        if (isset($_POST['blocked_endpoints'])) {
            $data['blocked_endpoints'] = $this->security->sanitize_endpoint_list($_POST['blocked_endpoints']);
        }
        
        $result = $this->db->update_api_key($id, $data);
        
        if (!$result) {
            wp_send_json_error(__('Failed to update API key', 'wp-rest-api-key-auth'));
        }
        
        $this->logger->log_system_event(
            'api_key_updated',
            'API key updated: ' . $api_key->name,
            WP_REST_API_Key_Logger::LOG_LEVEL_INFO
        );
        
        wp_send_json_success(__('API key updated successfully!', 'wp-rest-api-key-auth'));
    }
    
    /**
     * AJAX: Export logs
     */
    public function ajax_export_logs() {
        check_ajax_referer('wp_rest_api_key_auth_nonce', 'nonce');
        
        if (!current_user_can('view_api_logs')) {
            wp_die(__('Insufficient permissions', 'wp-rest-api-key-auth'));
        }
        
        $type = sanitize_text_field($_POST['type'] ?? 'api');
        $days = intval($_POST['days'] ?? 30);
        
        $this->logger->export_logs_to_csv($type, $days);
    }
    
    /**
     * AJAX: Run cleanup
     */
    public function ajax_run_cleanup() {
        check_ajax_referer('wp_rest_api_key_auth_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'wp-rest-api-key-auth'));
        }
        
        try {
            // Run database cleanup
            $this->db->cleanup_expired_keys();
            
            $this->logger->log_system_event(
                'manual_cleanup',
                'Manual database cleanup executed',
                WP_REST_API_Key_Logger::LOG_LEVEL_INFO
            );
            
            wp_send_json_success(__('Cleanup completed successfully!', 'wp-rest-api-key-auth'));
        } catch (Exception $e) {
            $this->logger->log_system_event(
                'cleanup_error',
                'Manual cleanup failed: ' . $e->getMessage(),
                WP_REST_API_Key_Logger::LOG_LEVEL_ERROR
            );
            
            wp_send_json_error(__('Cleanup failed. Please try again.', 'wp-rest-api-key-auth'));
        }
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        $settings = array(
            'wp_rest_api_key_auth_key_length' => intval($_POST['key_length'] ?? 64),
            'wp_rest_api_key_auth_key_expiry' => intval($_POST['key_expiry'] ?? 365),
            'wp_rest_api_key_auth_rate_limit' => intval($_POST['rate_limit'] ?? 1000),
            'wp_rest_api_key_auth_log_requests' => isset($_POST['log_requests']),
            'wp_rest_api_key_auth_require_https' => isset($_POST['require_https']),
        );
        
        // Handle CORS origins
        $cors_origins = sanitize_textarea_field($_POST['cors_origins'] ?? '');
        $cors_origins_array = array_filter(array_map('trim', explode("\n", $cors_origins)));
        $settings['wp_rest_api_key_auth_cors_origins'] = $cors_origins_array;
        
        // Handle public endpoints
        $public_endpoints = sanitize_textarea_field($_POST['public_endpoints'] ?? '');
        $public_endpoints_array = array_filter(array_map('trim', explode("\n", $public_endpoints)));
        $settings['wp_rest_api_key_auth_public_endpoints'] = $public_endpoints_array;
        
        foreach ($settings as $option => $value) {
            update_option($option, $value);
        }
        
        $this->logger->log_system_event(
            'settings_updated',
            'Plugin settings updated',
            WP_REST_API_Key_Logger::LOG_LEVEL_INFO
        );
    }
    
    /**
     * Handle bulk actions
     */
    private function handle_bulk_actions() {
        $action = sanitize_text_field($_POST['bulk_action']);
        $api_key_ids = array_map('intval', $_POST['api_keys']);
        
        switch ($action) {
            case 'delete':
                foreach ($api_key_ids as $id) {
                    $api_key = $this->db->get_api_key($id);
                    if ($api_key && (current_user_can('manage_options') || $api_key->user_id == get_current_user_id())) {
                        $this->db->delete_api_key($id);
                    }
                }
                break;
                
            case 'activate':
                foreach ($api_key_ids as $id) {
                    $api_key = $this->db->get_api_key($id);
                    if ($api_key && (current_user_can('manage_options') || $api_key->user_id == get_current_user_id())) {
                        $this->db->update_api_key($id, array('status' => 'active'));
                    }
                }
                break;
                
            case 'deactivate':
                foreach ($api_key_ids as $id) {
                    $api_key = $this->db->get_api_key($id);
                    if ($api_key && (current_user_can('manage_options') || $api_key->user_id == get_current_user_id())) {
                        $this->db->update_api_key($id, array('status' => 'inactive'));
                    }
                }
                break;
        }
        
        wp_redirect(wp_get_referer());
        exit;
    }
    
    /**
     * Add plugin action links
     */
    public function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wp-rest-api-key-auth-settings'),
            __('Settings', 'wp-rest-api-key-auth')
        );
        
        array_unshift($links, $settings_link);
        
        return $links;
    }
    
    /**
     * Show admin notices
     */
    public function show_admin_notices() {
        // Check if HTTPS is required but not being used
        if (get_option('wp_rest_api_key_auth_require_https', true) && !is_ssl()) {
            echo '<div class="notice notice-warning"><p>';
            printf(
                __('WP REST API Key Authentication: HTTPS is required but your site is not using HTTPS. Please <a href="%s">configure HTTPS</a> or disable the HTTPS requirement in <a href="%s">settings</a>.', 'wp-rest-api-key-auth'),
                'https://wordpress.org/support/article/https-for-wordpress/',
                admin_url('admin.php?page=wp-rest-api-key-auth-settings')
            );
            echo '</p></div>';
        }
        
        // Show settings updated notice
        if (isset($_GET['settings-updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            _e('Settings saved successfully!', 'wp-rest-api-key-auth');
            echo '</p></div>';
        }
    }
    
    /**
     * Get permission options
     */
    public function get_permission_options() {
        return array(
            'read' => __('Read', 'wp-rest-api-key-auth'),
            'write' => __('Write', 'wp-rest-api-key-auth'),
            'delete' => __('Delete', 'wp-rest-api-key-auth'),
        );
    }
    
    /**
     * Get status options
     */
    public function get_status_options() {
        return array(
            'active' => __('Active', 'wp-rest-api-key-auth'),
            'inactive' => __('Inactive', 'wp-rest-api-key-auth'),
            'expired' => __('Expired', 'wp-rest-api-key-auth'),
        );
    }
    
    /**
     * Format date for display
     */
    public function format_date($date) {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return __('Never', 'wp-rest-api-key-auth');
        }
        
        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($date));
    }
    
    /**
     * Format number for display
     */
    public function format_number($number) {
        return number_format_i18n($number);
    }
    
    /**
     * Get status badge HTML
     */
    public function get_status_badge($status) {
        $class = 'status-badge';
        $text = ucfirst($status);
        
        switch ($status) {
            case 'active':
                $class .= ' status-active';
                break;
            case 'inactive':
                $class .= ' status-inactive';
                break;
            case 'expired':
                $class .= ' status-expired';
                break;
        }
        
        return sprintf('<span class="%s">%s</span>', $class, $text);
    }
    
    /**
     * Format bytes to human readable format
     */
    public function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
} 