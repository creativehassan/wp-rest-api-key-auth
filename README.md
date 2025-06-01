# WP REST API Key Authentication

A comprehensive and secure API key authentication plugin for WordPress REST API with advanced features and robust security measures.

## Features

### 🔐 Advanced Security
- **Cryptographically Secure API Keys**: Generated using `random_bytes()` with configurable length
- **Argon2ID Password Hashing**: Industry-standard hashing for stored API keys
- **HTTPS Enforcement**: Optional requirement for secure connections
- **Rate Limiting**: Configurable request limits per API key
- **IP Address Restrictions**: Allow specific IPs or CIDR ranges
- **Domain Restrictions**: Control which domains can use API keys
- **Endpoint Restrictions**: Allow or block specific REST API endpoints

### 📊 Comprehensive Logging & Analytics
- **Request Logging**: Track all API requests with detailed information
- **Security Event Logging**: Monitor authentication failures and security violations
- **Performance Metrics**: Execution time and memory usage tracking
- **Statistics Dashboard**: Visual analytics with charts and graphs
- **Export Functionality**: Export logs to CSV for analysis
- **Email Notifications**: Alerts for critical security events

### 🎛️ Flexible Management
- **User-Friendly Admin Interface**: Modern, responsive design
- **Bulk Operations**: Manage multiple API keys at once
- **Permission System**: Granular read/write/delete permissions
- **Expiration Dates**: Automatic key expiration
- **Status Management**: Active/inactive/expired states
- **User Association**: Keys linked to WordPress users

### 🔧 Developer-Friendly
- **Multiple Authentication Methods**: 
  - Authorization header (`Bearer` token)
  - Custom header (`X-API-Key`)
  - Query parameter (`?api_key=`)
- **REST API Endpoints**: Manage keys programmatically
- **Hooks & Filters**: Extensive customization options
- **CORS Support**: Configurable cross-origin requests
- **Public Endpoints**: Exclude specific endpoints from authentication

## Installation

1. Download the plugin files
2. Upload to `/wp-content/plugins/wp-rest-api-key-authentication/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to 'API Keys' in the admin menu to get started

## Usage

### Creating an API Key

1. Go to **API Keys** in the WordPress admin menu
2. Click **Add New API Key**
3. Fill in the required information:
   - **Name**: Descriptive name for the key
   - **Permissions**: Select read, write, and/or delete permissions
   - **Rate Limit**: Set requests per hour (0 = unlimited)
   - **IP Restrictions**: Comma-separated list of allowed IPs/CIDR ranges
   - **Domain Restrictions**: Comma-separated list of allowed domains
   - **Endpoint Restrictions**: Control access to specific endpoints
4. Click **Generate API Key**
5. **Important**: Copy the generated key immediately - it won't be shown again!

### Using the API Key

Include the API key in your REST API requests using one of these methods:

#### Authorization Header (Recommended)
```bash
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://yoursite.com/wp-json/wp/v2/posts
```

#### Custom Header
```bash
curl -H "X-API-Key: YOUR_API_KEY" \
     https://yoursite.com/wp-json/wp/v2/posts
```

#### Query Parameter
```bash
curl https://yoursite.com/wp-json/wp/v2/posts?api_key=YOUR_API_KEY
```

### JavaScript Example
```javascript
fetch('https://yoursite.com/wp-json/wp/v2/posts', {
    headers: {
        'Authorization': 'Bearer YOUR_API_KEY',
        'Content-Type': 'application/json'
    }
})
.then(response => response.json())
.then(data => console.log(data));
```

### PHP Example
```php
$response = wp_remote_get('https://yoursite.com/wp-json/wp/v2/posts', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_API_KEY'
    ]
]);

$data = json_decode(wp_remote_retrieve_body($response), true);
```

## Configuration

### Plugin Settings

Navigate to **API Keys > Settings** to configure:

- **Key Length**: Length of generated API keys (minimum 32 characters)
- **Key Expiry**: Default expiration period in days (0 = no expiration)
- **Rate Limit**: Default rate limit for new keys
- **HTTPS Requirement**: Force HTTPS for API requests
- **Request Logging**: Enable/disable request logging
- **CORS Origins**: Allowed origins for cross-origin requests
- **Public Endpoints**: Endpoints that don't require authentication

### Security Best Practices

1. **Use HTTPS**: Always enable HTTPS requirement in production
2. **Limit Permissions**: Only grant necessary permissions to each key
3. **Set Rate Limits**: Prevent abuse with appropriate rate limiting
4. **Use IP Restrictions**: Limit access to known IP addresses when possible
5. **Regular Audits**: Review logs and active keys regularly
6. **Key Rotation**: Regenerate keys periodically
7. **Monitor Logs**: Watch for suspicious activity

## REST API Endpoints

The plugin provides its own REST API endpoints for programmatic management:

### Get API Keys
```
GET /wp-json/wp-rest-api-key-auth/v1/keys
```

### Create API Key
```
POST /wp-json/wp-rest-api-key-auth/v1/keys
```

### Update API Key
```
PUT /wp-json/wp-rest-api-key-auth/v1/keys/{id}
```

### Delete API Key
```
DELETE /wp-json/wp-rest-api-key-auth/v1/keys/{id}
```

### Get Statistics
```
GET /wp-json/wp-rest-api-key-auth/v1/stats
```

### Get Logs
```
GET /wp-json/wp-rest-api-key-auth/v1/logs
```

### Test API Key
```
GET /wp-json/wp-rest-api-key-auth/v1/test
```

## Hooks & Filters

### Actions
- `wp_rest_api_key_auth_key_created` - Fired when a new API key is created
- `wp_rest_api_key_auth_key_deleted` - Fired when an API key is deleted
- `wp_rest_api_key_auth_request_authenticated` - Fired on successful authentication
- `wp_rest_api_key_auth_authentication_failed` - Fired on authentication failure

### Filters
- `wp_rest_api_key_auth_validate_key` - Modify key validation logic
- `wp_rest_api_key_auth_rate_limit` - Customize rate limiting
- `wp_rest_api_key_auth_allowed_endpoints` - Modify allowed endpoints
- `wp_rest_api_key_auth_log_data` - Customize logged data

## Database Schema

### API Keys Table (`wp_rest_api_keys`)
- `id` - Primary key
- `name` - Human-readable name
- `api_key` - The actual key (for lookup)
- `api_key_hash` - Hashed version for security
- `user_id` - Associated WordPress user
- `permissions` - JSON array of permissions
- `rate_limit` - Requests per hour limit
- `allowed_ips` - Comma-separated IP restrictions
- `allowed_domains` - Comma-separated domain restrictions
- `allowed_endpoints` - Comma-separated endpoint restrictions
- `blocked_endpoints` - Comma-separated blocked endpoints
- `last_used` - Last usage timestamp
- `last_ip` - Last used IP address
- `request_count` - Total request count
- `status` - active/inactive/expired
- `expires_at` - Expiration timestamp
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

### Logs Table (`wp_rest_api_keys_logs`)
- `id` - Primary key
- `api_key_id` - Foreign key to API keys table
- `endpoint` - Requested endpoint
- `method` - HTTP method
- `ip_address` - Client IP address
- `user_agent` - Client user agent
- `request_data` - Sanitized request data
- `response_code` - HTTP response code
- `response_message` - Response message
- `execution_time` - Request execution time
- `memory_usage` - Memory usage
- `created_at` - Request timestamp

## Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Changelog

### Version 2.0.0
- Complete rewrite with modern architecture
- Advanced security features
- Comprehensive logging and analytics
- Improved admin interface
- REST API endpoints for management
- Enhanced documentation

## Support

For support, feature requests, or bug reports, please contact:
- **Author**: Hassan Ali | Coresol Studio
- **Website**: https://coresolstudio.com
- **Email**: support@coresolstudio.com

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by Hassan Ali at Coresol Studio with a focus on security, performance, and user experience. 