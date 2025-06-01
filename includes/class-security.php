<?php
/**
 * Security handler for WP REST API Key Authentication
 *
 * @package WP_REST_API_Key_Authentication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security handler class
 */
class WP_REST_API_Key_Security {
    
    /**
     * Rate limit cache
     */
    private $rate_limit_cache = array();
    
    /**
     * Initialize security
     */
    public function init() {
        // Security is initialized
    }
    
    /**
     * Generate secure API key
     */
    public function generate_api_key($length = null) {
        if ($length === null) {
            $length = get_option('wp_rest_api_key_auth_key_length', 64);
        }
        
        // Ensure minimum length for security
        $length = max($length, 32);
        
        // Generate cryptographically secure random bytes
        $bytes = random_bytes($length);
        
        // Convert to base64 and make URL-safe
        $api_key = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        
        // Ensure exact length
        return substr($api_key, 0, $length);
    }
    
    /**
     * Hash API key for storage
     */
    public function hash_api_key($api_key) {
        // Check if Argon2ID is available, fallback to Argon2I or bcrypt
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($api_key, PASSWORD_ARGON2ID, array(
                'memory_cost' => 65536, // 64 MB
                'time_cost' => 4,       // 4 iterations
            ));
        } elseif (defined('PASSWORD_ARGON2I')) {
            return password_hash($api_key, PASSWORD_ARGON2I, array(
                'memory_cost' => 65536, // 64 MB
                'time_cost' => 4,       // 4 iterations
            ));
        } else {
            // Fallback to bcrypt with high cost
            return password_hash($api_key, PASSWORD_BCRYPT, array(
                'cost' => 12,
            ));
        }
    }
    
    /**
     * Verify API key against hash
     */
    public function verify_api_key($api_key, $hash) {
        return password_verify($api_key, $hash);
    }
    
    /**
     * Validate API key format
     */
    public function validate_api_key_format($api_key) {
        // Check if API key is not empty
        if (empty($api_key)) {
            return false;
        }
        
        // Check minimum length
        if (strlen($api_key) < 32) {
            return false;
        }
        
        // Check if it contains only valid characters (base64url)
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $api_key)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if HTTPS is required and being used
     */
    public function check_https_requirement() {
        $require_https = get_option('wp_rest_api_key_auth_require_https', true);
        
        // Allow developers to override HTTPS requirement via filter
        $require_https = apply_filters('wp_rest_api_key_auth_require_https', $require_https);
        
        if ($require_https && !is_ssl()) {
            return new WP_Error(
                'https_required',
                __('HTTPS is required for API key authentication', 'wp-rest-api-key-auth'),
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Validate IP address restrictions
     */
    public function validate_ip_restrictions($api_key_data, $client_ip) {
        if (empty($api_key_data->allowed_ips)) {
            return true;
        }
        
        $allowed_ips = array_map('trim', explode(',', $api_key_data->allowed_ips));
        
        foreach ($allowed_ips as $allowed_ip) {
            if (empty($allowed_ip)) {
                continue;
            }
            
            // Check for CIDR notation
            if (strpos($allowed_ip, '/') !== false) {
                if ($this->ip_in_range($client_ip, $allowed_ip)) {
                    return true;
                }
            } else {
                // Exact IP match
                if ($client_ip === $allowed_ip) {
                    return true;
                }
            }
        }
        
        return new WP_Error(
            'ip_not_allowed',
            __('Your IP address is not allowed to use this API key', 'wp-rest-api-key-auth'),
            array('status' => 403)
        );
    }
    
    /**
     * Validate domain restrictions
     */
    public function validate_domain_restrictions($api_key_data, $request_origin) {
        if (empty($api_key_data->allowed_domains)) {
            return true;
        }
        
        if (empty($request_origin)) {
            return new WP_Error(
                'origin_required',
                __('Origin header is required for this API key', 'wp-rest-api-key-auth'),
                array('status' => 403)
            );
        }
        
        $allowed_domains = array_map('trim', explode(',', $api_key_data->allowed_domains));
        $request_domain = parse_url($request_origin, PHP_URL_HOST);
        
        foreach ($allowed_domains as $allowed_domain) {
            if (empty($allowed_domain)) {
                continue;
            }
            
            // Support wildcard subdomains
            if (strpos($allowed_domain, '*.') === 0) {
                $pattern = str_replace('*.', '', $allowed_domain);
                if ($request_domain === $pattern || substr($request_domain, -strlen('.' . $pattern)) === '.' . $pattern) {
                    return true;
                }
            } else {
                // Exact domain match
                if ($request_domain === $allowed_domain) {
                    return true;
                }
            }
        }
        
        return new WP_Error(
            'domain_not_allowed',
            __('Your domain is not allowed to use this API key', 'wp-rest-api-key-auth'),
            array('status' => 403)
        );
    }
    
    /**
     * Validate endpoint restrictions
     */
    public function validate_endpoint_restrictions($api_key_data, $endpoint) {
        // Check blocked endpoints first
        if (!empty($api_key_data->blocked_endpoints)) {
            $blocked_endpoints = array_map('trim', explode(',', $api_key_data->blocked_endpoints));
            
            foreach ($blocked_endpoints as $blocked_endpoint) {
                if (empty($blocked_endpoint)) {
                    continue;
                }
                
                if ($this->endpoint_matches_pattern($endpoint, $blocked_endpoint)) {
                    return new WP_Error(
                        'endpoint_blocked',
                        __('This endpoint is blocked for your API key', 'wp-rest-api-key-auth'),
                        array('status' => 403)
                    );
                }
            }
        }
        
        // Check allowed endpoints
        if (!empty($api_key_data->allowed_endpoints)) {
            $allowed_endpoints = array_map('trim', explode(',', $api_key_data->allowed_endpoints));
            
            foreach ($allowed_endpoints as $allowed_endpoint) {
                if (empty($allowed_endpoint)) {
                    continue;
                }
                
                if ($this->endpoint_matches_pattern($endpoint, $allowed_endpoint)) {
                    return true;
                }
            }
            
            return new WP_Error(
                'endpoint_not_allowed',
                __('This endpoint is not allowed for your API key', 'wp-rest-api-key-auth'),
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Check rate limiting
     */
    public function check_rate_limit($api_key_data, $db) {
        $rate_limit = intval($api_key_data->rate_limit);
        
        if ($rate_limit <= 0) {
            return true; // No rate limit
        }
        
        // Check cache first
        $cache_key = 'rate_limit_' . $api_key_data->id;
        
        if (isset($this->rate_limit_cache[$cache_key])) {
            $cached_data = $this->rate_limit_cache[$cache_key];
            
            // Check if cache is still valid (1 minute window)
            if (time() - $cached_data['timestamp'] < 60) {
                if ($cached_data['count'] >= $rate_limit) {
                    return new WP_Error(
                        'rate_limit_exceeded',
                        sprintf(
                            __('Rate limit exceeded. Maximum %d requests per hour allowed.', 'wp-rest-api-key-auth'),
                            $rate_limit
                        ),
                        array('status' => 429)
                    );
                }
                
                // Update cache
                $this->rate_limit_cache[$cache_key]['count']++;
                return true;
            }
        }
        
        // Get actual count from database
        $request_count = $db->get_rate_limit_data($api_key_data->id, 60);
        
        if ($request_count >= $rate_limit) {
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    __('Rate limit exceeded. Maximum %d requests per hour allowed.', 'wp-rest-api-key-auth'),
                    $rate_limit
                ),
                array('status' => 429)
            );
        }
        
        // Update cache
        $this->rate_limit_cache[$cache_key] = array(
            'count' => $request_count + 1,
            'timestamp' => time()
        );
        
        return true;
    }
    
    /**
     * Validate API key permissions
     */
    public function validate_permissions($api_key_data, $method) {
        if (empty($api_key_data->permissions)) {
            return true; // No restrictions
        }
        
        $permissions = json_decode($api_key_data->permissions, true);
        
        if (!is_array($permissions)) {
            return true; // Invalid permissions format, allow all
        }
        
        $method_lower = strtolower($method);
        $permission_map = array(
            'get' => 'read',
            'post' => 'write',
            'put' => 'write',
            'patch' => 'write',
            'delete' => 'delete',
        );
        
        $required_permission = isset($permission_map[$method_lower]) ? $permission_map[$method_lower] : 'read';
        
        if (!in_array($required_permission, $permissions)) {
            return new WP_Error(
                'insufficient_permissions',
                sprintf(
                    __('Your API key does not have %s permissions', 'wp-rest-api-key-auth'),
                    $required_permission
                ),
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Get client IP address
     */
    public function get_client_ip() {
        $ip_headers = array(
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
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
     * Check if IP is in CIDR range
     */
    private function ip_in_range($ip, $cidr) {
        list($subnet, $mask) = explode('/', $cidr);
        
        if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($subnet, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - intval($mask));
        
        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }
    
    /**
     * Check if endpoint matches pattern
     */
    private function endpoint_matches_pattern($endpoint, $pattern) {
        // Remove leading slash for consistency
        $endpoint = ltrim($endpoint, '/');
        $pattern = ltrim($pattern, '/');
        
        // Support wildcard patterns
        if (strpos($pattern, '*') !== false) {
            $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
            return preg_match('/^' . $pattern . '$/i', $endpoint);
        }
        
        // Exact match
        return strcasecmp($endpoint, $pattern) === 0;
    }
    
    /**
     * Sanitize API key name
     */
    public function sanitize_api_key_name($name) {
        $name = sanitize_text_field($name);
        $name = trim($name);
        
        if (empty($name)) {
            return false;
        }
        
        if (strlen($name) > 255) {
            $name = substr($name, 0, 255);
        }
        
        return $name;
    }
    
    /**
     * Validate and sanitize IP list
     */
    public function sanitize_ip_list($ip_list) {
        if (empty($ip_list)) {
            return '';
        }
        
        $ips = array_map('trim', explode(',', $ip_list));
        $valid_ips = array();
        
        foreach ($ips as $ip) {
            if (empty($ip)) {
                continue;
            }
            
            // Check for CIDR notation
            if (strpos($ip, '/') !== false) {
                list($subnet, $mask) = explode('/', $ip);
                if (filter_var($subnet, FILTER_VALIDATE_IP) && is_numeric($mask) && $mask >= 0 && $mask <= 32) {
                    $valid_ips[] = $ip;
                }
            } else {
                // Single IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $valid_ips[] = $ip;
                }
            }
        }
        
        return implode(', ', $valid_ips);
    }
    
    /**
     * Validate and sanitize domain list
     */
    public function sanitize_domain_list($domain_list) {
        if (empty($domain_list)) {
            return '';
        }
        
        $domains = array_map('trim', explode(',', $domain_list));
        $valid_domains = array();
        
        foreach ($domains as $domain) {
            if (empty($domain)) {
                continue;
            }
            
            // Remove protocol if present
            $domain = preg_replace('/^https?:\/\//', '', $domain);
            
            // Remove trailing slash
            $domain = rtrim($domain, '/');
            
            // Validate domain format
            if (preg_match('/^(\*\.)?[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
                $valid_domains[] = $domain;
            }
        }
        
        return implode(', ', $valid_domains);
    }
    
    /**
     * Validate and sanitize endpoint list
     */
    public function sanitize_endpoint_list($endpoint_list) {
        if (empty($endpoint_list)) {
            return '';
        }
        
        $endpoints = array_map('trim', explode(',', $endpoint_list));
        $valid_endpoints = array();
        
        foreach ($endpoints as $endpoint) {
            if (empty($endpoint)) {
                continue;
            }
            
            // Remove leading slash
            $endpoint = ltrim($endpoint, '/');
            
            // Basic validation - allow alphanumeric, hyphens, underscores, slashes, and wildcards
            if (preg_match('/^[a-zA-Z0-9\-_\/\*]+$/', $endpoint)) {
                $valid_endpoints[] = $endpoint;
            }
        }
        
        return implode(', ', $valid_endpoints);
    }
} 