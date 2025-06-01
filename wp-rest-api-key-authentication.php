<?php
/**
 * Plugin Name: WP REST API Key Authentication
 * Plugin URI: https://coresolstudio.com
 * Description: Advanced and secure API key-based authentication for WordPress REST API with comprehensive features and robust security measures.
 * Version: 2.0.0
 * Author: Hassan Ali | Coresol Studio
 * Author URI: https://coresolstudio.com
 * Text Domain: wp-rest-api-key-auth
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_REST_API_KEY_AUTH_VERSION', '2.0.0');
define('WP_REST_API_KEY_AUTH_PLUGIN_FILE', __FILE__);
define('WP_REST_API_KEY_AUTH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_REST_API_KEY_AUTH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_REST_API_KEY_AUTH_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WP_REST_API_KEY_AUTH_TABLE_NAME', 'wp_rest_api_keys');

/**
 * Main plugin class
 */
class WP_REST_API_Key_Authentication {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Database handler
     */
    private $db;
    
    /**
     * Security handler
     */
    private $security;
    
    /**
     * Admin handler
     */
    private $admin;
    
    /**
     * API handler
     */
    private $api;
    
    /**
     * Logger handler
     */
    private $logger;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once WP_REST_API_KEY_AUTH_PLUGIN_DIR . 'includes/class-database.php';
        require_once WP_REST_API_KEY_AUTH_PLUGIN_DIR . 'includes/class-security.php';
        require_once WP_REST_API_KEY_AUTH_PLUGIN_DIR . 'includes/class-logger.php';
        require_once WP_REST_API_KEY_AUTH_PLUGIN_DIR . 'includes/class-api.php';
        require_once WP_REST_API_KEY_AUTH_PLUGIN_DIR . 'admin/class-admin.php';
        
        $this->db = new WP_REST_API_Key_Database();
        $this->security = new WP_REST_API_Key_Security();
        $this->logger = new WP_REST_API_Key_Logger();
        $this->api = new WP_REST_API_Key_API($this->db, $this->security, $this->logger);
        $this->admin = new WP_REST_API_Key_Admin($this->db, $this->security, $this->logger);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array('WP_REST_API_Key_Authentication', 'uninstall'));
        
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // REST API authentication
        add_filter('rest_authentication_errors', array($this->api, 'authenticate_request'), 10, 1);
        
        // Add custom headers for CORS if needed
        add_action('rest_api_init', array($this, 'add_cors_headers'));
        
        // Clean up expired keys daily
        add_action('wp_rest_api_key_auth_cleanup', array($this->db, 'cleanup_expired_keys'));
        if (!wp_next_scheduled('wp_rest_api_key_auth_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_rest_api_key_auth_cleanup');
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Initialize components
        $this->db->init();
        $this->security->init();
        $this->logger->init();
        $this->api->init();
        $this->admin->init();
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'wp-rest-api-key-auth',
            false,
            dirname(WP_REST_API_KEY_AUTH_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Add CORS headers if needed
     */
    public function add_cors_headers() {
        $allowed_origins = get_option('wp_rest_api_key_auth_cors_origins', array());
        
        if (!empty($allowed_origins)) {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
            
            if (in_array($origin, $allowed_origins) || in_array('*', $allowed_origins)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
                header('Access-Control-Allow-Credentials: true');
            }
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->load_dependencies();
        $this->db->create_tables();
        
        // Set default options
        $default_options = array(
            'wp_rest_api_key_auth_key_length' => 64,
            'wp_rest_api_key_auth_key_expiry' => 365, // days
            'wp_rest_api_key_auth_rate_limit' => 1000, // requests per hour
            'wp_rest_api_key_auth_log_requests' => true,
            'wp_rest_api_key_auth_require_https' => $this->is_production_environment(),
            'wp_rest_api_key_auth_cors_origins' => array(),
            'wp_rest_api_key_auth_allowed_endpoints' => array(),
            'wp_rest_api_key_auth_blocked_endpoints' => array(),
        );
        
        foreach ($default_options as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
        
        // Create capabilities
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap('manage_api_keys');
            $role->add_cap('view_api_logs');
        }
        
        // Schedule cleanup
        if (!wp_next_scheduled('wp_rest_api_key_auth_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wp_rest_api_key_auth_cleanup');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('wp_rest_api_key_auth_cleanup');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin uninstall
     */
    public static function uninstall() {
        global $wpdb;
        
        // Remove database tables
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}" . WP_REST_API_KEY_AUTH_TABLE_NAME);
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}" . WP_REST_API_KEY_AUTH_TABLE_NAME . "_logs");
        
        // Remove options
        $options = array(
            'wp_rest_api_key_auth_key_length',
            'wp_rest_api_key_auth_key_expiry',
            'wp_rest_api_key_auth_rate_limit',
            'wp_rest_api_key_auth_log_requests',
            'wp_rest_api_key_auth_require_https',
            'wp_rest_api_key_auth_cors_origins',
            'wp_rest_api_key_auth_allowed_endpoints',
            'wp_rest_api_key_auth_blocked_endpoints',
        );
        
        foreach ($options as $option) {
            delete_option($option);
        }
        
        // Remove capabilities
        $role = get_role('administrator');
        if ($role) {
            $role->remove_cap('manage_api_keys');
            $role->remove_cap('view_api_logs');
        }
        
        // Clear scheduled events
        wp_clear_scheduled_hook('wp_rest_api_key_auth_cleanup');
    }
    
    /**
     * Get database handler
     */
    public function get_db() {
        return $this->db;
    }
    
    /**
     * Get security handler
     */
    public function get_security() {
        return $this->security;
    }
    
    /**
     * Get logger handler
     */
    public function get_logger() {
        return $this->logger;
    }
    
    /**
     * Get API handler
     */
    public function get_api() {
        return $this->api;
    }
    
    /**
     * Get admin handler
     */
    public function get_admin() {
        return $this->admin;
    }

    /**
     * Check if the current environment is a production environment
     */
    private function is_production_environment() {
        // Check for common development environment indicators
        $site_url = get_site_url();
        $server_name = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
        
        // Common development environment patterns
        $dev_patterns = array(
            'localhost',
            '127.0.0.1',
            '.local',
            '.dev',
            '.test',
            'staging',
            'dev.',
            'test.',
        );
        
        // Check if any development patterns match
        foreach ($dev_patterns as $pattern) {
            if (strpos($site_url, $pattern) !== false || strpos($server_name, $pattern) !== false) {
                return false; // This is a development environment
            }
        }
        
        // Check for WordPress debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return false; // Development mode
        }
        
        // Check for Local by Flywheel
        if (defined('WP_LOCAL_DEV')) {
            return false; // Local development
        }
        
        // Default to production (require HTTPS)
        return true;
    }
}

/**
 * Initialize the plugin
 */
function wp_rest_api_key_auth() {
    return WP_REST_API_Key_Authentication::get_instance();
}

// Start the plugin
wp_rest_api_key_auth(); 