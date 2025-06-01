<?php
/**
 * Logger handler for WP REST API Key Authentication
 *
 * @package WP_REST_API_Key_Authentication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger handler class
 */
class WP_REST_API_Key_Logger {
    
    /**
     * Log levels
     */
    const LOG_LEVEL_DEBUG = 'debug';
    const LOG_LEVEL_INFO = 'info';
    const LOG_LEVEL_WARNING = 'warning';
    const LOG_LEVEL_ERROR = 'error';
    const LOG_LEVEL_CRITICAL = 'critical';
    
    /**
     * Request start time
     */
    private $request_start_time;
    
    /**
     * Request start memory
     */
    private $request_start_memory;
    
    /**
     * Initialize logger
     */
    public function init() {
        $this->request_start_time = microtime(true);
        $this->request_start_memory = memory_get_usage();
    }
    
    /**
     * Log API request
     */
    public function log_api_request($api_key_id, $endpoint, $method, $response_code = 200, $response_message = '', $additional_data = array()) {
        if (!get_option('wp_rest_api_key_auth_log_requests', true)) {
            return;
        }
        
        $execution_time = microtime(true) - $this->request_start_time;
        $memory_usage = memory_get_usage() - $this->request_start_memory;
        
        $log_data = array(
            'api_key_id' => $api_key_id,
            'endpoint' => $endpoint,
            'method' => strtoupper($method),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'request_data' => $this->sanitize_request_data(),
            'response_code' => $response_code,
            'response_message' => $response_message,
            'execution_time' => round($execution_time, 4),
            'memory_usage' => $memory_usage,
        );
        
        // Add additional data if provided
        if (!empty($additional_data)) {
            $log_data = array_merge($log_data, $additional_data);
        }
        
        // Get database instance
        $plugin = wp_rest_api_key_auth();
        $db = $plugin->get_db();
        
        $db->log_request($log_data);
        
        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $this->write_to_debug_log(
                sprintf(
                    'API Request: %s %s - Response: %d - Time: %fs - Memory: %s - IP: %s',
                    $method,
                    $endpoint,
                    $response_code,
                    $execution_time,
                    $this->format_bytes($memory_usage),
                    $log_data['ip_address']
                ),
                self::LOG_LEVEL_INFO
            );
        }
    }
    
    /**
     * Log security event
     */
    public function log_security_event($event_type, $message, $level = self::LOG_LEVEL_WARNING, $additional_data = array()) {
        $log_data = array(
            'event_type' => $event_type,
            'message' => $message,
            'level' => $level,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'timestamp' => current_time('mysql'),
        );
        
        // Add additional data
        if (!empty($additional_data)) {
            $log_data = array_merge($log_data, $additional_data);
        }
        
        // Store in WordPress options for security events (limited to last 100 events)
        $security_logs = get_option('wp_rest_api_key_auth_security_logs', array());
        
        // Add new log entry
        array_unshift($security_logs, $log_data);
        
        // Keep only last 100 entries
        $security_logs = array_slice($security_logs, 0, 100);
        
        update_option('wp_rest_api_key_auth_security_logs', $security_logs);
        
        // Also log to WordPress debug log
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $this->write_to_debug_log(
                sprintf('Security Event [%s]: %s - IP: %s', $event_type, $message, $log_data['ip_address']),
                $level
            );
        }
        
        // Send email notification for critical security events
        if ($level === self::LOG_LEVEL_CRITICAL) {
            $this->send_security_notification($event_type, $message, $log_data);
        }
    }
    
    /**
     * Log system event
     */
    public function log_system_event($event_type, $message, $level = self::LOG_LEVEL_INFO, $additional_data = array()) {
        // Log to WordPress debug log
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $this->write_to_debug_log(
                sprintf('System Event [%s]: %s', $event_type, $message),
                $level
            );
        }
        
        // Store critical system events
        if ($level === self::LOG_LEVEL_CRITICAL || $level === self::LOG_LEVEL_ERROR) {
            $system_logs = get_option('wp_rest_api_key_auth_system_logs', array());
            
            $log_data = array(
                'event_type' => $event_type,
                'message' => $message,
                'level' => $level,
                'timestamp' => current_time('mysql'),
            );
            
            if (!empty($additional_data)) {
                $log_data = array_merge($log_data, $additional_data);
            }
            
            array_unshift($system_logs, $log_data);
            $system_logs = array_slice($system_logs, 0, 50);
            
            update_option('wp_rest_api_key_auth_system_logs', $system_logs);
        }
    }
    
    /**
     * Get security logs
     */
    public function get_security_logs($limit = 50) {
        $logs = get_option('wp_rest_api_key_auth_security_logs', array());
        return array_slice($logs, 0, $limit);
    }
    
    /**
     * Get system logs
     */
    public function get_system_logs($limit = 50) {
        $logs = get_option('wp_rest_api_key_auth_system_logs', array());
        return array_slice($logs, 0, $limit);
    }
    
    /**
     * Clear logs
     */
    public function clear_logs($type = 'all') {
        switch ($type) {
            case 'security':
                delete_option('wp_rest_api_key_auth_security_logs');
                break;
            case 'system':
                delete_option('wp_rest_api_key_auth_system_logs');
                break;
            case 'api':
                // Clear API logs from database
                $plugin = wp_rest_api_key_auth();
                $db = $plugin->get_db();
                global $wpdb;
                $logs_table = $wpdb->prefix . WP_REST_API_KEY_AUTH_TABLE_NAME . '_logs';
                $wpdb->query("TRUNCATE TABLE {$logs_table}");
                break;
            case 'all':
                delete_option('wp_rest_api_key_auth_security_logs');
                delete_option('wp_rest_api_key_auth_system_logs');
                $plugin = wp_rest_api_key_auth();
                $db = $plugin->get_db();
                global $wpdb;
                $logs_table = $wpdb->prefix . WP_REST_API_KEY_AUTH_TABLE_NAME . '_logs';
                $wpdb->query("TRUNCATE TABLE {$logs_table}");
                break;
        }
        
        $this->log_system_event('logs_cleared', "Logs cleared: {$type}", self::LOG_LEVEL_INFO);
    }
    
    /**
     * Get API usage statistics
     */
    public function get_api_usage_stats($days = 30) {
        $plugin = wp_rest_api_key_auth();
        $db = $plugin->get_db();
        
        return $db->get_statistics(null, $days);
    }
    
    /**
     * Get top endpoints
     */
    public function get_top_endpoints($limit = 10, $days = 30) {
        global $wpdb;
        $logs_table = $wpdb->prefix . WP_REST_API_KEY_AUTH_TABLE_NAME . '_logs';
        
        $sql = $wpdb->prepare(
            "SELECT endpoint, COUNT(*) as request_count, 
                    AVG(execution_time) as avg_execution_time,
                    AVG(memory_usage) as avg_memory_usage
             FROM {$logs_table} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY endpoint 
             ORDER BY request_count DESC 
             LIMIT %d",
            $days,
            $limit
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get error rate statistics
     */
    public function get_error_rate_stats($days = 7) {
        global $wpdb;
        $logs_table = $wpdb->prefix . WP_REST_API_KEY_AUTH_TABLE_NAME . '_logs';
        
        $sql = $wpdb->prepare(
            "SELECT 
                DATE(created_at) as date,
                COUNT(*) as total_requests,
                SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) as error_requests,
                ROUND((SUM(CASE WHEN response_code >= 400 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as error_rate
             FROM {$logs_table} 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
             GROUP BY DATE(created_at)
             ORDER BY date DESC",
            $days
        );
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    
    /**
     * Sanitize request data for logging
     */
    private function sanitize_request_data() {
        $request_data = array();
        
        // Get request method
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '';
        
        // Get query parameters
        if (!empty($_GET)) {
            $request_data['query'] = $this->sanitize_sensitive_data($_GET);
        }
        
        // Get POST data for non-GET requests
        if ($method !== 'GET' && !empty($_POST)) {
            $request_data['post'] = $this->sanitize_sensitive_data($_POST);
        }
        
        // Get JSON body for API requests
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            $json_data = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request_data['json'] = $this->sanitize_sensitive_data($json_data);
            }
        }
        
        return json_encode($request_data);
    }
    
    /**
     * Sanitize sensitive data from request
     */
    private function sanitize_sensitive_data($data) {
        $sensitive_keys = array(
            'password',
            'pass',
            'passwd',
            'pwd',
            'secret',
            'token',
            'api_key',
            'auth',
            'authorization',
            'credit_card',
            'cc_number',
            'ssn',
            'social_security',
        );
        
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $key_lower = strtolower($key);
                
                // Check if key contains sensitive information
                foreach ($sensitive_keys as $sensitive_key) {
                    if (strpos($key_lower, $sensitive_key) !== false) {
                        $data[$key] = '[REDACTED]';
                        break;
                    }
                }
                
                // Recursively sanitize nested arrays
                if (is_array($value)) {
                    $data[$key] = $this->sanitize_sensitive_data($value);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Write to WordPress debug log
     */
    private function write_to_debug_log($message, $level = self::LOG_LEVEL_INFO) {
        $log_message = sprintf(
            '[%s] [%s] WP REST API Key Auth: %s',
            current_time('Y-m-d H:i:s'),
            strtoupper($level),
            $message
        );
        
        error_log($log_message);
    }
    
    /**
     * Send security notification email
     */
    private function send_security_notification($event_type, $message, $log_data) {
        $admin_email = get_option('admin_email');
        
        if (empty($admin_email)) {
            return;
        }
        
        $subject = sprintf(
            '[%s] Critical Security Alert - WP REST API Key Authentication',
            get_bloginfo('name')
        );
        
        $email_message = sprintf(
            "A critical security event has been detected on your WordPress site.\n\n" .
            "Event Type: %s\n" .
            "Message: %s\n" .
            "IP Address: %s\n" .
            "User Agent: %s\n" .
            "Timestamp: %s\n\n" .
            "Please review your API key authentication logs and take appropriate action if necessary.\n\n" .
            "Site: %s\n" .
            "Admin URL: %s",
            $event_type,
            $message,
            $log_data['ip_address'],
            $log_data['user_agent'],
            $log_data['timestamp'],
            get_bloginfo('url'),
            admin_url('admin.php?page=wp-rest-api-key-auth-logs')
        );
        
        wp_mail($admin_email, $subject, $email_message);
    }
    
    /**
     * Format bytes to human readable format
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Export logs to CSV
     */
    public function export_logs_to_csv($type = 'api', $days = 30) {
        $filename = sprintf('wp-rest-api-key-auth-%s-logs-%s.csv', $type, date('Y-m-d'));
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        switch ($type) {
            case 'api':
                $this->export_api_logs_csv($output, $days);
                break;
            case 'security':
                $this->export_security_logs_csv($output);
                break;
            case 'system':
                $this->export_system_logs_csv($output);
                break;
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export API logs to CSV
     */
    private function export_api_logs_csv($output, $days) {
        // CSV headers
        fputcsv($output, array(
            'Date/Time',
            'API Key Name',
            'Endpoint',
            'Method',
            'Response Code',
            'IP Address',
            'Execution Time (s)',
            'Memory Usage',
            'User Agent'
        ));
        
        $plugin = wp_rest_api_key_auth();
        $db = $plugin->get_db();
        
        $logs = $db->get_logs(null, 1000, 0); // Get up to 1000 logs
        
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log->created_at,
                $log->api_key_name,
                $log->endpoint,
                $log->method,
                $log->response_code,
                $log->ip_address,
                $log->execution_time,
                $this->format_bytes($log->memory_usage),
                $log->user_agent
            ));
        }
    }
    
    /**
     * Export security logs to CSV
     */
    private function export_security_logs_csv($output) {
        fputcsv($output, array(
            'Date/Time',
            'Event Type',
            'Level',
            'Message',
            'IP Address',
            'User Agent'
        ));
        
        $logs = $this->get_security_logs(1000);
        
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log['timestamp'],
                $log['event_type'],
                $log['level'],
                $log['message'],
                $log['ip_address'],
                $log['user_agent']
            ));
        }
    }
    
    /**
     * Export system logs to CSV
     */
    private function export_system_logs_csv($output) {
        fputcsv($output, array(
            'Date/Time',
            'Event Type',
            'Level',
            'Message'
        ));
        
        $logs = $this->get_system_logs(1000);
        
        foreach ($logs as $log) {
            fputcsv($output, array(
                $log['timestamp'],
                $log['event_type'],
                $log['level'],
                $log['message']
            ));
        }
    }
} 