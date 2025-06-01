<?php
/**
 * Documentation page view
 *
 * @package WP_REST_API_Key_Authentication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$site_url = get_site_url();
$rest_url = get_rest_url();
?>

<div class="wrap wp-rest-api-key-auth">
    <h1 class="wp-heading-inline"><?php _e('API Documentation', 'wp-rest-api-key-auth'); ?></h1>
    <hr class="wp-header-end">

    <div class="documentation-content">
        
        <!-- Quick Start Section -->
        <div class="documentation-section">
            <h2><?php _e('Quick Start Guide', 'wp-rest-api-key-auth'); ?></h2>
            <p><?php _e('Follow these steps to start using API key authentication with your WordPress REST API:', 'wp-rest-api-key-auth'); ?></p>
            
            <ol>
                <li><strong><?php _e('Create an API Key', 'wp-rest-api-key-auth'); ?></strong> - <?php _e('Go to API Keys page and generate a new key', 'wp-rest-api-key-auth'); ?></li>
                <li><strong><?php _e('Include the API Key', 'wp-rest-api-key-auth'); ?></strong> - <?php _e('Add your API key to requests using one of the methods below', 'wp-rest-api-key-auth'); ?></li>
                <li><strong><?php _e('Make API Calls', 'wp-rest-api-key-auth'); ?></strong> - <?php _e('Start making authenticated requests to your WordPress REST API', 'wp-rest-api-key-auth'); ?></li>
            </ol>
        </div>

        <!-- Authentication Methods -->
        <div class="documentation-section">
            <h2><?php _e('Authentication Methods', 'wp-rest-api-key-auth'); ?></h2>
            <p><?php _e('You can authenticate your API requests using any of these three methods:', 'wp-rest-api-key-auth'); ?></p>

            <h3><?php _e('Method 1: Authorization Header (Recommended)', 'wp-rest-api-key-auth'); ?></h3>
            <div class="code-example">
                <pre><code>Authorization: Bearer YOUR_API_KEY_HERE</code></pre>
            </div>

            <h3><?php _e('Method 2: X-API-Key Header', 'wp-rest-api-key-auth'); ?></h3>
            <div class="code-example">
                <pre><code>X-API-Key: YOUR_API_KEY_HERE</code></pre>
            </div>

            <h3><?php _e('Method 3: Query Parameter', 'wp-rest-api-key-auth'); ?></h3>
            <div class="code-example">
                <pre><code><?php echo esc_html($rest_url); ?>wp/v2/posts?api_key=YOUR_API_KEY_HERE</code></pre>
            </div>
            
            <div class="notice notice-info">
                <p><strong><?php _e('Security Note:', 'wp-rest-api-key-auth'); ?></strong> <?php _e('Using headers is more secure than query parameters as they are not logged in server access logs.', 'wp-rest-api-key-auth'); ?></p>
            </div>
        </div>

        <!-- Code Examples -->
        <div class="documentation-section">
            <h2><?php _e('Code Examples', 'wp-rest-api-key-auth'); ?></h2>

            <!-- JavaScript/jQuery Examples -->
            <h3><?php _e('JavaScript / jQuery', 'wp-rest-api-key-auth'); ?></h3>
            
            <h4><?php _e('Fetch API (Modern JavaScript)', 'wp-rest-api-key-auth'); ?></h4>
            <div class="code-example">
                <pre><code>// Get posts
fetch('<?php echo esc_js($rest_url); ?>wp/v2/posts', {
    method: 'GET',
    headers: {
        'Authorization': 'Bearer YOUR_API_KEY_HERE',
        'Content-Type': 'application/json'
    }
})
.then(response => response.json())
.then(data => {
    console.log('Posts:', data);
})
.catch(error => {
    console.error('Error:', error);
});

// Create a new post
fetch('<?php echo esc_js($rest_url); ?>wp/v2/posts', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer YOUR_API_KEY_HERE',
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        title: 'My New Post',
        content: 'This is the post content',
        status: 'publish'
    })
})
.then(response => response.json())
.then(data => {
    console.log('Created post:', data);
});</code></pre>
            </div>

            <h4><?php _e('jQuery AJAX', 'wp-rest-api-key-auth'); ?></h4>
            <div class="code-example">
                <pre><code>// Get posts
$.ajax({
    url: '<?php echo esc_js($rest_url); ?>wp/v2/posts',
    method: 'GET',
    headers: {
        'Authorization': 'Bearer YOUR_API_KEY_HERE'
    },
    success: function(data) {
        console.log('Posts:', data);
    },
    error: function(xhr, status, error) {
        console.error('Error:', error);
    }
});

// Create a new post
$.ajax({
    url: '<?php echo esc_js($rest_url); ?>wp/v2/posts',
    method: 'POST',
    headers: {
        'Authorization': 'Bearer YOUR_API_KEY_HERE',
        'Content-Type': 'application/json'
    },
    data: JSON.stringify({
        title: 'My New Post',
        content: 'This is the post content',
        status: 'publish'
    }),
    success: function(data) {
        console.log('Created post:', data);
    }
});</code></pre>
            </div>

            <!-- PHP Examples -->
            <h3><?php _e('PHP', 'wp-rest-api-key-auth'); ?></h3>
            
            <h4><?php _e('Using cURL', 'wp-rest-api-key-auth'); ?></h4>
            <div class="code-example">
                <pre><code>&lt;?php
// Get posts
$api_key = 'YOUR_API_KEY_HERE';
$url = '<?php echo esc_html($rest_url); ?>wp/v2/posts';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$posts = json_decode($response, true);
curl_close($ch);

print_r($posts);

// Create a new post
$create_url = '<?php echo esc_html($rest_url); ?>wp/v2/posts';
$post_data = [
    'title' => 'My New Post',
    'content' => 'This is the post content',
    'status' => 'publish'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $create_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$created_post = json_decode($response, true);
curl_close($ch);

print_r($created_post);
?&gt;</code></pre>
            </div>

            <h4><?php _e('Using WordPress HTTP API', 'wp-rest-api-key-auth'); ?></h4>
            <div class="code-example">
                <pre><code>&lt;?php
// Get posts
$api_key = 'YOUR_API_KEY_HERE';
$url = '<?php echo esc_html($rest_url); ?>wp/v2/posts';

$response = wp_remote_get($url, [
    'headers' => [
        'Authorization' => 'Bearer ' . $api_key
    ]
]);

if (!is_wp_error($response)) {
    $posts = json_decode(wp_remote_retrieve_body($response), true);
    print_r($posts);
}

// Create a new post
$create_url = '<?php echo esc_html($rest_url); ?>wp/v2/posts';
$post_data = [
    'title' => 'My New Post',
    'content' => 'This is the post content',
    'status' => 'publish'
];

$response = wp_remote_post($create_url, [
    'headers' => [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json'
    ],
    'body' => json_encode($post_data)
]);

if (!is_wp_error($response)) {
    $created_post = json_decode(wp_remote_retrieve_body($response), true);
    print_r($created_post);
}
?&gt;</code></pre>
            </div>

            <!-- Python Examples -->
            <h3><?php _e('Python', 'wp-rest-api-key-auth'); ?></h3>
            
            <h4><?php _e('Using requests library', 'wp-rest-api-key-auth'); ?></h4>
            <div class="code-example">
                <pre><code>import requests
import json

# Configuration
api_key = 'YOUR_API_KEY_HERE'
base_url = '<?php echo esc_html($rest_url); ?>'
headers = {
    'Authorization': f'Bearer {api_key}',
    'Content-Type': 'application/json'
}

# Get posts
response = requests.get(f'{base_url}wp/v2/posts', headers=headers)
if response.status_code == 200:
    posts = response.json()
    print('Posts:', posts)
else:
    print('Error:', response.status_code, response.text)

# Create a new post
post_data = {
    'title': 'My New Post',
    'content': 'This is the post content',
    'status': 'publish'
}

response = requests.post(
    f'{base_url}wp/v2/posts',
    headers=headers,
    data=json.dumps(post_data)
)

if response.status_code == 201:
    created_post = response.json()
    print('Created post:', created_post)
else:
    print('Error:', response.status_code, response.text)</code></pre>
            </div>

            <!-- Node.js Examples -->
            <h3><?php _e('Node.js', 'wp-rest-api-key-auth'); ?></h3>
            
            <h4><?php _e('Using axios', 'wp-rest-api-key-auth'); ?></h4>
            <div class="code-example">
                <pre><code>const axios = require('axios');

// Configuration
const apiKey = 'YOUR_API_KEY_HERE';
const baseURL = '<?php echo esc_js($rest_url); ?>';
const headers = {
    'Authorization': `Bearer ${apiKey}`,
    'Content-Type': 'application/json'
};

// Get posts
async function getPosts() {
    try {
        const response = await axios.get(`${baseURL}wp/v2/posts`, { headers });
        console.log('Posts:', response.data);
    } catch (error) {
        console.error('Error:', error.response?.data || error.message);
    }
}

// Create a new post
async function createPost() {
    const postData = {
        title: 'My New Post',
        content: 'This is the post content',
        status: 'publish'
    };

    try {
        const response = await axios.post(`${baseURL}wp/v2/posts`, postData, { headers });
        console.log('Created post:', response.data);
    } catch (error) {
        console.error('Error:', error.response?.data || error.message);
    }
}

// Execute functions
getPosts();
createPost();</code></pre>
            </div>

            <!-- cURL Examples -->
            <h3><?php _e('cURL (Command Line)', 'wp-rest-api-key-auth'); ?></h3>
            <div class="code-example">
                <pre><code># Get posts
curl -X GET "<?php echo esc_html($rest_url); ?>wp/v2/posts" \
     -H "Authorization: Bearer YOUR_API_KEY_HERE" \
     -H "Content-Type: application/json"

# Create a new post
curl -X POST "<?php echo esc_html($rest_url); ?>wp/v2/posts" \
     -H "Authorization: Bearer YOUR_API_KEY_HERE" \
     -H "Content-Type: application/json" \
     -d '{
       "title": "My New Post",
       "content": "This is the post content",
       "status": "publish"
     }'

# Get a specific post
curl -X GET "<?php echo esc_html($rest_url); ?>wp/v2/posts/123" \
     -H "Authorization: Bearer YOUR_API_KEY_HERE"

# Update a post
curl -X PUT "<?php echo esc_html($rest_url); ?>wp/v2/posts/123" \
     -H "Authorization: Bearer YOUR_API_KEY_HERE" \
     -H "Content-Type: application/json" \
     -d '{
       "title": "Updated Post Title",
       "content": "Updated post content"
     }'

# Delete a post
curl -X DELETE "<?php echo esc_html($rest_url); ?>wp/v2/posts/123" \
     -H "Authorization: Bearer YOUR_API_KEY_HERE"</code></pre>
            </div>
        </div>

        <!-- Common Endpoints -->
        <div class="documentation-section">
            <h2><?php _e('Common WordPress REST API Endpoints', 'wp-rest-api-key-auth'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Endpoint', 'wp-rest-api-key-auth'); ?></th>
                        <th><?php _e('Method', 'wp-rest-api-key-auth'); ?></th>
                        <th><?php _e('Description', 'wp-rest-api-key-auth'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>/wp/v2/posts</code></td>
                        <td>GET</td>
                        <td><?php _e('Get all posts', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/wp/v2/posts</code></td>
                        <td>POST</td>
                        <td><?php _e('Create a new post', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/wp/v2/posts/{id}</code></td>
                        <td>GET</td>
                        <td><?php _e('Get a specific post', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/wp/v2/posts/{id}</code></td>
                        <td>PUT</td>
                        <td><?php _e('Update a post', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/wp/v2/posts/{id}</code></td>
                        <td>DELETE</td>
                        <td><?php _e('Delete a post', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/wp/v2/pages</code></td>
                        <td>GET</td>
                        <td><?php _e('Get all pages', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/wp/v2/users</code></td>
                        <td>GET</td>
                        <td><?php _e('Get all users', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/wp/v2/comments</code></td>
                        <td>GET</td>
                        <td><?php _e('Get all comments', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/wp/v2/media</code></td>
                        <td>GET</td>
                        <td><?php _e('Get all media files', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td><code>/wp/v2/categories</code></td>
                        <td>GET</td>
                        <td><?php _e('Get all categories', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Error Handling -->
        <div class="documentation-section">
            <h2><?php _e('Error Handling', 'wp-rest-api-key-auth'); ?></h2>
            <p><?php _e('The API returns standard HTTP status codes and JSON error responses:', 'wp-rest-api-key-auth'); ?></p>

            <h3><?php _e('Common Error Codes', 'wp-rest-api-key-auth'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Status Code', 'wp-rest-api-key-auth'); ?></th>
                        <th><?php _e('Error Code', 'wp-rest-api-key-auth'); ?></th>
                        <th><?php _e('Description', 'wp-rest-api-key-auth'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>401</td>
                        <td>api_key_missing</td>
                        <td><?php _e('API key is required but not provided', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td>401</td>
                        <td>api_key_invalid</td>
                        <td><?php _e('The provided API key is invalid', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td>401</td>
                        <td>api_key_expired</td>
                        <td><?php _e('The API key has expired or is inactive', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td>403</td>
                        <td>https_required</td>
                        <td><?php _e('HTTPS is required for API authentication', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td>403</td>
                        <td>ip_not_allowed</td>
                        <td><?php _e('Your IP address is not allowed', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td>403</td>
                        <td>domain_not_allowed</td>
                        <td><?php _e('Your domain is not allowed', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td>403</td>
                        <td>insufficient_permissions</td>
                        <td><?php _e('API key lacks required permissions', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                    <tr>
                        <td>429</td>
                        <td>rate_limit_exceeded</td>
                        <td><?php _e('Rate limit exceeded', 'wp-rest-api-key-auth'); ?></td>
                    </tr>
                </tbody>
            </table>

            <h3><?php _e('Error Response Format', 'wp-rest-api-key-auth'); ?></h3>
            <div class="code-example">
                <pre><code>{
    "code": "api_key_invalid",
    "message": "Invalid API key",
    "data": {
        "status": 401
    }
}</code></pre>
            </div>
        </div>

        <!-- Rate Limiting -->
        <div class="documentation-section">
            <h2><?php _e('Rate Limiting', 'wp-rest-api-key-auth'); ?></h2>
            <p><?php _e('API keys have rate limits to prevent abuse. Check the response headers for rate limit information:', 'wp-rest-api-key-auth'); ?></p>

            <h3><?php _e('Rate Limit Headers', 'wp-rest-api-key-auth'); ?></h3>
            <ul>
                <li><code>X-Rate-Limit</code> - <?php _e('Maximum requests allowed per hour', 'wp-rest-api-key-auth'); ?></li>
                <li><code>X-Rate-Limit-Remaining</code> - <?php _e('Remaining requests in current window', 'wp-rest-api-key-auth'); ?></li>
                <li><code>X-API-Key-Name</code> - <?php _e('Name of the API key being used', 'wp-rest-api-key-auth'); ?></li>
            </ul>
        </div>

        <!-- Testing -->
        <div class="documentation-section">
            <h2><?php _e('Testing Your API Key', 'wp-rest-api-key-auth'); ?></h2>
            <p><?php _e('Use this endpoint to test if your API key is working correctly:', 'wp-rest-api-key-auth'); ?></p>

            <div class="code-example">
                <pre><code>GET <?php echo esc_html($rest_url); ?>wp-rest-api-key-auth/v1/test</code></pre>
            </div>

            <p><?php _e('Example response:', 'wp-rest-api-key-auth'); ?></p>
            <div class="code-example">
                <pre><code>{
    "message": "API key is valid and working correctly",
    "timestamp": "2024-01-01T12:00:00+00:00",
    "api_key_name": "My API Key"
}</code></pre>
            </div>
        </div>

        <!-- Best Practices -->
        <div class="documentation-section">
            <h2><?php _e('Security Best Practices', 'wp-rest-api-key-auth'); ?></h2>
            <ul>
                <li><strong><?php _e('Use HTTPS', 'wp-rest-api-key-auth'); ?></strong> - <?php _e('Always use HTTPS in production to encrypt API keys in transit', 'wp-rest-api-key-auth'); ?></li>
                <li><strong><?php _e('Store Keys Securely', 'wp-rest-api-key-auth'); ?></strong> - <?php _e('Never hardcode API keys in client-side code or version control', 'wp-rest-api-key-auth'); ?></li>
                <li><strong><?php _e('Use Environment Variables', 'wp-rest-api-key-auth'); ?></strong> - <?php _e('Store API keys in environment variables or secure configuration files', 'wp-rest-api-key-auth'); ?></li>
                <li><strong><?php _e('Restrict Permissions', 'wp-rest-api-key-auth'); ?></strong> - <?php _e('Only grant the minimum permissions required for your use case', 'wp-rest-api-key-auth'); ?></li>
                <li><strong><?php _e('Use IP Restrictions', 'wp-rest-api-key-auth'); ?></strong> - <?php _e('Limit API key usage to specific IP addresses when possible', 'wp-rest-api-key-auth'); ?></li>
                <li><strong><?php _e('Monitor Usage', 'wp-rest-api-key-auth'); ?></strong> - <?php _e('Regularly check API logs for unusual activity', 'wp-rest-api-key-auth'); ?></li>
                <li><strong><?php _e('Rotate Keys', 'wp-rest-api-key-auth'); ?></strong> - <?php _e('Periodically generate new API keys and deactivate old ones', 'wp-rest-api-key-auth'); ?></li>
            </ul>
        </div>

    </div>
</div>

<style>
.documentation-content {
    max-width: 1200px;
}

.documentation-section {
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 1px solid #ddd;
}

.documentation-section:last-child {
    border-bottom: none;
}

.code-example {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 15px;
    margin: 15px 0;
    overflow-x: auto;
}

.code-example pre {
    margin: 0;
    background: none;
    border: none;
    padding: 0;
    white-space: pre-wrap;
    word-wrap: break-word;
}

.code-example code {
    background: none;
    color: #495057;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 13px;
    line-height: 1.4;
}

.documentation-section h3 {
    color: #2271b1;
    margin-top: 30px;
}

.documentation-section h4 {
    color: #646970;
    margin-top: 25px;
    margin-bottom: 10px;
}

.documentation-section table {
    margin-top: 15px;
}

.documentation-section table code {
    background: #f1f1f1;
    padding: 2px 4px;
    border-radius: 3px;
    font-size: 12px;
}

.notice {
    margin: 20px 0;
}
</style> 