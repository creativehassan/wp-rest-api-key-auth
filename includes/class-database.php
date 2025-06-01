<?php
/**
 * Database handler for WP REST API Key Authentication
 *
 * @package WP_REST_API_Key_Authentication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database handler class
 */
class WP_REST_API_Key_Database {
    
    /**
     * API keys table name
     */
    private $table_name;
    
    /**
     * Logs table name
     */
    private $logs_table_name;
    
    /**
     * WordPress database object
     */
    private $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . WP_REST_API_KEY_AUTH_TABLE_NAME;
        $this->logs_table_name = $wpdb->prefix . WP_REST_API_KEY_AUTH_TABLE_NAME . '_logs';
    }
    
    /**
     * Initialize database
     */
    public function init() {
        // Database is initialized during plugin activation
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // API keys table
        $sql_keys = "CREATE TABLE {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            api_key varchar(255) NOT NULL,
            api_key_hash varchar(255) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            permissions text,
            rate_limit int(11) DEFAULT 1000,
            allowed_ips text,
            allowed_domains text,
            allowed_endpoints text,
            blocked_endpoints text,
            last_used datetime DEFAULT NULL,
            last_ip varchar(45) DEFAULT NULL,
            request_count bigint(20) DEFAULT 0,
            status enum('active','inactive','expired') DEFAULT 'active',
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY api_key (api_key),
            KEY user_id (user_id),
            KEY status (status),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        // Logs table
        $sql_logs = "CREATE TABLE {$this->logs_table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            api_key_id bigint(20) unsigned NOT NULL,
            endpoint varchar(255) NOT NULL,
            method varchar(10) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            request_data text,
            response_code int(11),
            response_message text,
            execution_time float,
            memory_usage bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY api_key_id (api_key_id),
            KEY endpoint (endpoint),
            KEY ip_address (ip_address),
            KEY created_at (created_at),
            FOREIGN KEY (api_key_id) REFERENCES {$this->table_name}(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_keys);
        dbDelta($sql_logs);
    }
    
    /**
     * Create new API key
     */
    public function create_api_key($data) {
        $defaults = array(
            'name' => '',
            'api_key' => '',
            'api_key_hash' => '',
            'user_id' => get_current_user_id(),
            'permissions' => json_encode(array('read')),
            'rate_limit' => get_option('wp_rest_api_key_auth_rate_limit', 1000),
            'allowed_ips' => '',
            'allowed_domains' => '',
            'allowed_endpoints' => '',
            'blocked_endpoints' => '',
            'status' => 'active',
            'expires_at' => null,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Set expiry date if configured
        $expiry_days = get_option('wp_rest_api_key_auth_key_expiry', 365);
        if ($expiry_days > 0) {
            $data['expires_at'] = date('Y-m-d H:i:s', strtotime("+{$expiry_days} days"));
        }
        
        $result = $this->wpdb->insert(
            $this->table_name,
            $data,
            array(
                '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s'
            )
        );
        
        if ($result === false) {
            return new WP_Error('db_error', __('Failed to create API key', 'wp-rest-api-key-auth'));
        }
        
        return $this->wpdb->insert_id;
    }
    
    /**
     * Get API key by ID
     */
    public function get_api_key($id) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        );
        
        return $this->wpdb->get_row($sql);
    }
    
    /**
     * Get API key by key value
     */
    public function get_api_key_by_key($api_key) {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE api_key = %s AND status = 'active'",
            $api_key
        );
        
        return $this->wpdb->get_row($sql);
    }
    
    /**
     * Get all API keys for a user
     */
    public function get_user_api_keys($user_id = null, $limit = 20, $offset = 0) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        );
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get all API keys (admin only)
     */
    public function get_all_api_keys($limit = 20, $offset = 0, $search = '') {
        $where = '';
        $params = array();
        
        if (!empty($search)) {
            $where = " WHERE name LIKE %s OR last_ip LIKE %s";
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        
        $sql = "SELECT * FROM {$this->table_name}{$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }
    
    /**
     * Update API key
     */
    public function update_api_key($id, $data) {
        $data['updated_at'] = current_time('mysql');
        
        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            array('id' => $id),
            null,
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Update API key usage
     */
    public function update_api_key_usage($id, $ip_address = null) {
        $data = array(
            'last_used' => current_time('mysql'),
            'request_count' => new stdClass() // Will be handled as raw SQL
        );
        
        if ($ip_address) {
            $data['last_ip'] = $ip_address;
        }
        
        // Use raw SQL for incrementing request_count
        $sql = $this->wpdb->prepare(
            "UPDATE {$this->table_name} SET 
                last_used = %s, 
                request_count = request_count + 1" . 
                ($ip_address ? ", last_ip = %s" : "") . 
            " WHERE id = %d",
            current_time('mysql'),
            $ip_address ? $ip_address : null,
            $id
        );
        
        // Remove null parameter if no IP address
        if (!$ip_address) {
            $sql = $this->wpdb->prepare(
                "UPDATE {$this->table_name} SET 
                    last_used = %s, 
                    request_count = request_count + 1
                WHERE id = %d",
                current_time('mysql'),
                $id
            );
        }
        
        return $this->wpdb->query($sql) !== false;
    }
    
    /**
     * Delete API key
     */
    public function delete_api_key($id) {
        $result = $this->wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Log API request
     */
    public function log_request($data) {
        if (!get_option('wp_rest_api_key_auth_log_requests', true)) {
            return true;
        }
        
        $defaults = array(
            'api_key_id' => 0,
            'endpoint' => '',
            'method' => '',
            'ip_address' => '',
            'user_agent' => '',
            'request_data' => '',
            'response_code' => 200,
            'response_message' => '',
            'execution_time' => 0,
            'memory_usage' => 0,
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $this->wpdb->insert(
            $this->logs_table_name,
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%f', '%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Get API logs
     */
    public function get_logs($api_key_id = null, $limit = 50, $offset = 0) {
        $where = '';
        $params = array();
        
        if ($api_key_id) {
            $where = " WHERE l.api_key_id = %d";
            $params[] = $api_key_id;
        }
        
        $sql = "SELECT l.*, k.name as api_key_name 
                FROM {$this->logs_table_name} l 
                LEFT JOIN {$this->table_name} k ON l.api_key_id = k.id
                {$where} 
                ORDER BY l.created_at DESC 
                LIMIT %d OFFSET %d";
        
        $params[] = $limit;
        $params[] = $offset;
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }
    
    /**
     * Get API statistics
     */
    public function get_statistics($api_key_id = null, $days = 30) {
        $where = '';
        $params = array();
        
        if ($api_key_id) {
            $where = " AND api_key_id = %d";
            $params[] = $api_key_id;
        }
        
        $date_from = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $params[] = $date_from;
        
        $sql = "SELECT 
                    COUNT(*) as total_requests,
                    COUNT(DISTINCT api_key_id) as unique_keys,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    AVG(execution_time) as avg_execution_time,
                    AVG(memory_usage) as avg_memory_usage,
                    DATE(created_at) as date,
                    COUNT(*) as daily_requests
                FROM {$this->logs_table_name} 
                WHERE created_at >= %s {$where}
                GROUP BY DATE(created_at)
                ORDER BY date DESC";
        
        return $this->wpdb->get_results($this->wpdb->prepare($sql, $params));
    }
    
    /**
     * Clean up expired keys
     */
    public function cleanup_expired_keys() {
        $sql = "UPDATE {$this->table_name} 
                SET status = 'expired' 
                WHERE expires_at IS NOT NULL 
                AND expires_at < NOW() 
                AND status = 'active'";
        
        $this->wpdb->query($sql);
        
        // Clean up old logs (keep last 90 days)
        $sql = "DELETE FROM {$this->logs_table_name} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        
        $this->wpdb->query($sql);
    }
    
    /**
     * Get rate limit data for API key
     */
    public function get_rate_limit_data($api_key_id, $window_minutes = 60) {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) as request_count 
             FROM {$this->logs_table_name} 
             WHERE api_key_id = %d 
             AND created_at >= DATE_SUB(NOW(), INTERVAL %d MINUTE)",
            $api_key_id,
            $window_minutes
        );
        
        $result = $this->wpdb->get_var($sql);
        return intval($result);
    }
    
    /**
     * Check if API key exists
     */
    public function api_key_exists($api_key) {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE api_key = %s",
            $api_key
        );
        
        return $this->wpdb->get_var($sql) > 0;
    }
} 