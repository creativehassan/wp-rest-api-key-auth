<?php
/**
 * API handler for WP REST API Key Authentication
 *
 * @package WP_REST_API_Key_Authentication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API handler class
 */
class WP_REST_API_Key_API {
    
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
     * Current API key data
     */
    private $current_api_key = null;
    
    /**
     * Constructor
     */
    public function __construct($db, $security, $logger) {
        $this->db = $db;
        $this->security = $security;
        $this->logger = $logger;
    }
    
    /**
     * Initialize API
     */
    public function init() {
        // Add REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        
        // Add authentication info to REST API index
        add_filter('rest_index', array($this, 'add_auth_info_to_index'));
        
        // Add custom response headers
        add_filter('rest_post_dispatch', array($this, 'add_response_headers'), 10, 3);
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        // API key management endpoints
        register_rest_route('wp-rest-api-key-auth/v1', '/keys', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_api_keys'),
                'permission_callback' => array($this, 'check_manage_api_keys_permission'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_api_key'),
                'permission_callback' => array($this, 'check_manage_api_keys_permission'),
                'args' => array(
                    'name' => array(
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'permissions' => array(
                        'type' => 'array',
                        'default' => array('read'),
                    ),
                    'rate_limit' => array(
                        'type' => 'integer',
                        'default' => 1000,
                    ),
                    'allowed_ips' => array(
                        'type' => 'string',
                        'default' => '',
                    ),
                    'allowed_domains' => array(
                        'type' => 'string',
                        'default' => '',
                    ),
                    'allowed_endpoints' => array(
                        'type' => 'string',
                        'default' => '',
                    ),
                    'blocked_endpoints' => array(
                        'type' => 'string',
                        'default' => '',
                    ),
                ),
            ),
        ));
        
        register_rest_route('wp-rest-api-key-auth/v1', '/keys/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_api_key'),
                'permission_callback' => array($this, 'check_manage_api_keys_permission'),
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_api_key'),
                'permission_callback' => array($this, 'check_manage_api_keys_permission'),
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_api_key'),
                'permission_callback' => array($this, 'check_manage_api_keys_permission'),
            ),
        ));
        
        // Statistics endpoints
        register_rest_route('wp-rest-api-key-auth/v1', '/stats', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_statistics'),
            'permission_callback' => array($this, 'check_view_logs_permission'),
        ));
        
        // Logs endpoints
        register_rest_route('wp-rest-api-key-auth/v1', '/logs', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_logs'),
            'permission_callback' => array($this, 'check_view_logs_permission'),
        ));
        
        // Test endpoint for API key validation
        register_rest_route('wp-rest-api-key-auth/v1', '/test', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'test_api_key'),
            'permission_callback' => '__return_true',
        ));
    }
    
    /**
     * Authenticate REST API request
     */
    public function authenticate_request($result) {
        // If authentication already occurred, return the result
        if (!empty($result)) {
            return $result;
        }
        
        // Skip authentication for certain endpoints
        if ($this->should_skip_authentication()) {
            return $result;
        }
        
        // Check HTTPS requirement
        $https_check = $this->security->check_https_requirement();
        if (is_wp_error($https_check)) {
            $this->logger->log_security_event(
                'https_required',
                'HTTPS required but request made over HTTP',
                WP_REST_API_Key_Logger::LOG_LEVEL_WARNING
            );
            return $https_check;
        }
        
        // Get API key from request
        $api_key = $this->extract_api_key_from_request();
        
        if (empty($api_key)) {
            $this->logger->log_security_event(
                'missing_api_key',
                'API key missing from request',
                WP_REST_API_Key_Logger::LOG_LEVEL_WARNING
            );
            
            return new WP_Error(
                'api_key_missing',
                __('API key is required for authentication', 'wp-rest-api-key-auth'),
                array('status' => 401)
            );
        }
        
        // Validate API key format
        if (!$this->security->validate_api_key_format($api_key)) {
            $this->logger->log_security_event(
                'invalid_api_key_format',
                'Invalid API key format provided',
                WP_REST_API_Key_Logger::LOG_LEVEL_WARNING
            );
            
            return new WP_Error(
                'api_key_invalid_format',
                __('Invalid API key format', 'wp-rest-api-key-auth'),
                array('status' => 401)
            );
        }
        
        // Get API key data from database
        $api_key_data = $this->db->get_api_key_by_key($api_key);
        
        if (!$api_key_data) {
            $this->logger->log_security_event(
                'invalid_api_key',
                'Invalid API key used: ' . substr($api_key, 0, 8) . '...',
                WP_REST_API_Key_Logger::LOG_LEVEL_WARNING
            );
            
            return new WP_Error(
                'api_key_invalid',
                __('Invalid API key', 'wp-rest-api-key-auth'),
                array('status' => 401)
            );
        }
        
        // Check if API key is expired
        if ($api_key_data->status !== 'active') {
            $this->logger->log_security_event(
                'expired_api_key',
                'Expired or inactive API key used: ' . $api_key_data->name,
                WP_REST_API_Key_Logger::LOG_LEVEL_WARNING
            );
            
            return new WP_Error(
                'api_key_expired',
                __('API key is expired or inactive', 'wp-rest-api-key-auth'),
                array('status' => 401)
            );
        }
        
        // Get client IP and request info
        $client_ip = $this->security->get_client_ip();
        $request_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        // Extract endpoint from request URI
        $endpoint = $this->extract_endpoint_from_uri($request_uri);
        
        // Validate IP restrictions
        $ip_check = $this->security->validate_ip_restrictions($api_key_data, $client_ip);
        if (is_wp_error($ip_check)) {
            $this->logger->log_security_event(
                'ip_restriction_violation',
                'IP restriction violation for API key: ' . $api_key_data->name . ' from IP: ' . $client_ip,
                WP_REST_API_Key_Logger::LOG_LEVEL_WARNING
            );
            return $ip_check;
        }
        
        // Validate domain restrictions
        $domain_check = $this->security->validate_domain_restrictions($api_key_data, $request_origin);
        if (is_wp_error($domain_check)) {
            $this->logger->log_security_event(
                'domain_restriction_violation',
                'Domain restriction violation for API key: ' . $api_key_data->name . ' from origin: ' . $request_origin,
                WP_REST_API_Key_Logger::LOG_LEVEL_WARNING
            );
            return $domain_check;
        }
        
        // Validate endpoint restrictions
        $endpoint_check = $this->security->validate_endpoint_restrictions($api_key_data, $endpoint);
        if (is_wp_error($endpoint_check)) {
            $this->logger->log_security_event(
                'endpoint_restriction_violation',
                'Endpoint restriction violation for API key: ' . $api_key_data->name . ' accessing: ' . $endpoint,
                WP_REST_API_Key_Logger::LOG_LEVEL_WARNING
            );
            return $endpoint_check;
        }
        
        // Validate permissions
        $permission_check = $this->security->validate_permissions($api_key_data, $request_method);
        if (is_wp_error($permission_check)) {
            $this->logger->log_security_event(
                'permission_violation',
                'Permission violation for API key: ' . $api_key_data->name . ' method: ' . $request_method,
                WP_REST_API_Key_Logger::LOG_LEVEL_WARNING
            );
            return $permission_check;
        }
        
        // Check rate limiting
        $rate_limit_check = $this->security->check_rate_limit($api_key_data, $this->db);
        if (is_wp_error($rate_limit_check)) {
            $this->logger->log_security_event(
                'rate_limit_exceeded',
                'Rate limit exceeded for API key: ' . $api_key_data->name,
                WP_REST_API_Key_Logger::LOG_LEVEL_WARNING
            );
            return $rate_limit_check;
        }
        
        // Store current API key data for later use
        $this->current_api_key = $api_key_data;
        
        // Update API key usage
        $this->db->update_api_key_usage($api_key_data->id, $client_ip);
        
        // Log successful authentication
        $this->logger->log_api_request(
            $api_key_data->id,
            $endpoint,
            $request_method,
            200,
            'Authentication successful'
        );
        
        // Authentication succeeded
        return true;
    }
    
    /**
     * Extract API key from request
     */
    private function extract_api_key_from_request() {
        // Check Authorization header (Bearer token)
        $headers = getallheaders();
        if (!empty($headers['Authorization'])) {
            if (strpos($headers['Authorization'], 'Bearer ') === 0) {
                return substr($headers['Authorization'], 7);
            }
        }
        
        // Check X-API-Key header
        if (!empty($headers['X-API-Key'])) {
            return $headers['X-API-Key'];
        }
        
        // Check query parameter
        if (!empty($_GET['api_key'])) {
            return $_GET['api_key'];
        }
        
        return null;
    }
    
    /**
     * Extract endpoint from request URI
     */
    private function extract_endpoint_from_uri($request_uri) {
        // Remove query string
        $uri = strtok($request_uri, '?');
        
        // Remove WordPress base path
        $wp_base = parse_url(home_url(), PHP_URL_PATH);
        if ($wp_base && strpos($uri, $wp_base) === 0) {
            $uri = substr($uri, strlen($wp_base));
        }
        
        // Remove leading slash
        $uri = ltrim($uri, '/');
        
        // Extract REST API endpoint
        if (strpos($uri, 'wp-json/') === 0) {
            $uri = substr($uri, 8); // Remove 'wp-json/'
        }
        
        return $uri;
    }
    
    /**
     * Check if authentication should be skipped
     */
    private function should_skip_authentication() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        
        // Skip for WordPress core endpoints that don't require authentication
        $skip_patterns = array(
            '#/wp-json/$#',
            '#/wp-json$#',
            '#/wp-json/wp/v2/types#',
            '#/wp-json/oembed/#',
        );
        
        foreach ($skip_patterns as $pattern) {
            if (preg_match($pattern, $request_uri)) {
                return true;
            }
        }
        
        // Check if this is a public endpoint
        $public_endpoints = get_option('wp_rest_api_key_auth_public_endpoints', array());
        
        if (!empty($public_endpoints)) {
            $endpoint = $this->extract_endpoint_from_uri($request_uri);
            
            foreach ($public_endpoints as $public_endpoint) {
                if (strpos($endpoint, trim($public_endpoint)) === 0) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Add authentication info to REST API index
     */
    public function add_auth_info_to_index($response) {
        // Get the response data
        $data = $response->get_data();
        
        // Add authentication info
        $data['authentication'] = array(
            'wp-rest-api-key-auth' => array(
                'description' => __('API Key Authentication for WordPress REST API', 'wp-rest-api-key-auth'),
                'version' => WP_REST_API_KEY_AUTH_VERSION,
                'methods' => array(
                    'header' => 'Authorization: Bearer YOUR_API_KEY',
                    'header_alt' => 'X-API-Key: YOUR_API_KEY',
                    'query' => '?api_key=YOUR_API_KEY',
                ),
            ),
        );
        
        // Set the modified data back to the response
        $response->set_data($data);
        
        return $response;
    }
    
    /**
     * Add custom response headers
     */
    public function add_response_headers($response, $server, $request) {
        if ($this->current_api_key) {
            $response->header('X-API-Key-Name', $this->current_api_key->name);
            $response->header('X-Rate-Limit', $this->current_api_key->rate_limit);
            
            // Add rate limit remaining
            $used_requests = $this->db->get_rate_limit_data($this->current_api_key->id, 60);
            $remaining = max(0, $this->current_api_key->rate_limit - $used_requests);
            $response->header('X-Rate-Limit-Remaining', $remaining);
        }
        
        return $response;
    }
    
    /**
     * REST API: Get API keys
     */
    public function get_api_keys($request) {
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $search = $request->get_param('search') ?: '';
        
        $offset = ($page - 1) * $per_page;
        
        if (current_user_can('manage_options')) {
            $api_keys = $this->db->get_all_api_keys($per_page, $offset, $search);
        } else {
            $api_keys = $this->db->get_user_api_keys(get_current_user_id(), $per_page, $offset);
        }
        
        // Remove sensitive data
        foreach ($api_keys as &$key) {
            unset($key->api_key, $key->api_key_hash);
        }
        
        return rest_ensure_response($api_keys);
    }
    
    /**
     * REST API: Get single API key
     */
    public function get_api_key($request) {
        $id = $request->get_param('id');
        $api_key = $this->db->get_api_key($id);
        
        if (!$api_key) {
            return new WP_Error('api_key_not_found', __('API key not found', 'wp-rest-api-key-auth'), array('status' => 404));
        }
        
        // Check permissions
        if (!current_user_can('manage_options') && $api_key->user_id != get_current_user_id()) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to view this API key', 'wp-rest-api-key-auth'), array('status' => 403));
        }
        
        // Remove sensitive data
        unset($api_key->api_key, $api_key->api_key_hash);
        
        return rest_ensure_response($api_key);
    }
    
    /**
     * REST API: Create API key
     */
    public function create_api_key($request) {
        $name = $this->security->sanitize_api_key_name($request->get_param('name'));
        
        if (!$name) {
            return new WP_Error('invalid_name', __('Invalid API key name', 'wp-rest-api-key-auth'), array('status' => 400));
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
            'permissions' => json_encode($request->get_param('permissions')),
            'rate_limit' => intval($request->get_param('rate_limit')),
            'allowed_ips' => $this->security->sanitize_ip_list($request->get_param('allowed_ips')),
            'allowed_domains' => $this->security->sanitize_domain_list($request->get_param('allowed_domains')),
            'allowed_endpoints' => $this->security->sanitize_endpoint_list($request->get_param('allowed_endpoints')),
            'blocked_endpoints' => $this->security->sanitize_endpoint_list($request->get_param('blocked_endpoints')),
        );
        
        $result = $this->db->create_api_key($data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $this->logger->log_system_event(
            'api_key_created',
            'API key created: ' . $name,
            WP_REST_API_Key_Logger::LOG_LEVEL_INFO
        );
        
        return rest_ensure_response(array(
            'id' => $result,
            'api_key' => $api_key,
            'message' => __('API key created successfully. Please save this key as it will not be shown again.', 'wp-rest-api-key-auth'),
        ));
    }
    
    /**
     * REST API: Update API key
     */
    public function update_api_key($request) {
        $id = $request->get_param('id');
        $api_key = $this->db->get_api_key($id);
        
        if (!$api_key) {
            return new WP_Error('api_key_not_found', __('API key not found', 'wp-rest-api-key-auth'), array('status' => 404));
        }
        
        // Check permissions
        if (!current_user_can('manage_options') && $api_key->user_id != get_current_user_id()) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to update this API key', 'wp-rest-api-key-auth'), array('status' => 403));
        }
        
        $data = array();
        
        if ($request->has_param('name')) {
            $name = $this->security->sanitize_api_key_name($request->get_param('name'));
            if (!$name) {
                return new WP_Error('invalid_name', __('Invalid API key name', 'wp-rest-api-key-auth'), array('status' => 400));
            }
            $data['name'] = $name;
        }
        
        if ($request->has_param('status')) {
            $data['status'] = in_array($request->get_param('status'), array('active', 'inactive')) ? $request->get_param('status') : 'active';
        }
        
        if ($request->has_param('permissions')) {
            $data['permissions'] = json_encode($request->get_param('permissions'));
        }
        
        if ($request->has_param('rate_limit')) {
            $data['rate_limit'] = intval($request->get_param('rate_limit'));
        }
        
        if ($request->has_param('allowed_ips')) {
            $data['allowed_ips'] = $this->security->sanitize_ip_list($request->get_param('allowed_ips'));
        }
        
        if ($request->has_param('allowed_domains')) {
            $data['allowed_domains'] = $this->security->sanitize_domain_list($request->get_param('allowed_domains'));
        }
        
        if ($request->has_param('allowed_endpoints')) {
            $data['allowed_endpoints'] = $this->security->sanitize_endpoint_list($request->get_param('allowed_endpoints'));
        }
        
        if ($request->has_param('blocked_endpoints')) {
            $data['blocked_endpoints'] = $this->security->sanitize_endpoint_list($request->get_param('blocked_endpoints'));
        }
        
        $result = $this->db->update_api_key($id, $data);
        
        if (!$result) {
            return new WP_Error('update_failed', __('Failed to update API key', 'wp-rest-api-key-auth'), array('status' => 500));
        }
        
        $this->logger->log_system_event(
            'api_key_updated',
            'API key updated: ' . $api_key->name,
            WP_REST_API_Key_Logger::LOG_LEVEL_INFO
        );
        
        return rest_ensure_response(array('message' => __('API key updated successfully', 'wp-rest-api-key-auth')));
    }
    
    /**
     * REST API: Delete API key
     */
    public function delete_api_key($request) {
        $id = $request->get_param('id');
        $api_key = $this->db->get_api_key($id);
        
        if (!$api_key) {
            return new WP_Error('api_key_not_found', __('API key not found', 'wp-rest-api-key-auth'), array('status' => 404));
        }
        
        // Check permissions
        if (!current_user_can('manage_options') && $api_key->user_id != get_current_user_id()) {
            return new WP_Error('insufficient_permissions', __('You do not have permission to delete this API key', 'wp-rest-api-key-auth'), array('status' => 403));
        }
        
        $result = $this->db->delete_api_key($id);
        
        if (!$result) {
            return new WP_Error('delete_failed', __('Failed to delete API key', 'wp-rest-api-key-auth'), array('status' => 500));
        }
        
        $this->logger->log_system_event(
            'api_key_deleted',
            'API key deleted: ' . $api_key->name,
            WP_REST_API_Key_Logger::LOG_LEVEL_INFO
        );
        
        return rest_ensure_response(array('message' => __('API key deleted successfully', 'wp-rest-api-key-auth')));
    }
    
    /**
     * REST API: Get statistics
     */
    public function get_statistics($request) {
        $days = $request->get_param('days') ?: 30;
        
        $stats = $this->logger->get_api_usage_stats($days);
        $top_endpoints = $this->logger->get_top_endpoints(10, $days);
        $error_rates = $this->logger->get_error_rate_stats(7);
        
        return rest_ensure_response(array(
            'usage_stats' => $stats,
            'top_endpoints' => $top_endpoints,
            'error_rates' => $error_rates,
        ));
    }
    
    /**
     * REST API: Get logs
     */
    public function get_logs($request) {
        $api_key_id = $request->get_param('api_key_id');
        $limit = $request->get_param('limit') ?: 50;
        $offset = $request->get_param('offset') ?: 0;
        
        $logs = $this->db->get_logs($api_key_id, $limit, $offset);
        
        return rest_ensure_response($logs);
    }
    
    /**
     * REST API: Test API key
     */
    public function test_api_key($request) {
        return rest_ensure_response(array(
            'message' => __('API key is valid and working correctly', 'wp-rest-api-key-auth'),
            'timestamp' => current_time('c'),
            'api_key_name' => $this->current_api_key ? $this->current_api_key->name : null,
        ));
    }
    
    /**
     * Check manage API keys permission
     */
    public function check_manage_api_keys_permission($request) {
        return current_user_can('manage_api_keys');
    }
    
    /**
     * Check view logs permission
     */
    public function check_view_logs_permission($request) {
        return current_user_can('view_api_logs');
    }
} 