<?php
/**
 * Statistics page view
 *
 * @package WP_REST_API_Key_Authentication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Calculate summary statistics
$total_requests = 0;
$total_errors = 0;
$avg_execution_time = 0;
$avg_memory_usage = 0;

if (!empty($stats)) {
    foreach ($stats as $stat) {
        $total_requests += $stat->total_requests;
        $avg_execution_time += $stat->avg_execution_time;
        $avg_memory_usage += $stat->avg_memory_usage;
    }
    $avg_execution_time = count($stats) > 0 ? $avg_execution_time / count($stats) : 0;
    $avg_memory_usage = count($stats) > 0 ? $avg_memory_usage / count($stats) : 0;
}

if (!empty($error_rates)) {
    foreach ($error_rates as $rate) {
        $total_errors += $rate->error_requests;
    }
}
?>

<div class="wrap wp-rest-api-key-auth">
    <h1 class="wp-heading-inline"><?php _e('API Statistics', 'wp-rest-api-key-auth'); ?></h1>
    <hr class="wp-header-end">

    <!-- Summary Cards -->
    <div class="stats-summary">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $this->format_number($total_requests); ?></div>
                    <div class="stat-label"><?php _e('Total Requests (30 days)', 'wp-rest-api-key-auth'); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $this->format_number($total_errors); ?></div>
                    <div class="stat-label"><?php _e('Total Errors (7 days)', 'wp-rest-api-key-auth'); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⚡</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo round($avg_execution_time, 3); ?>s</div>
                    <div class="stat-label"><?php _e('Avg Response Time', 'wp-rest-api-key-auth'); ?></div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💾</div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo $this->format_bytes($avg_memory_usage); ?></div>
                    <div class="stat-label"><?php _e('Avg Memory Usage', 'wp-rest-api-key-auth'); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-section">
        <div class="chart-row">
            <!-- Daily Requests Chart -->
            <div class="chart-container">
                <h3><?php _e('Daily API Requests (Last 30 Days)', 'wp-rest-api-key-auth'); ?></h3>
                <canvas id="dailyRequestsChart" width="400" height="200"></canvas>
            </div>
        </div>

        <div class="chart-row">
            <!-- Error Rate Chart -->
            <div class="chart-container half-width">
                <h3><?php _e('Error Rate (Last 7 Days)', 'wp-rest-api-key-auth'); ?></h3>
                <canvas id="errorRateChart" width="400" height="200"></canvas>
            </div>

            <!-- Top Endpoints Chart -->
            <div class="chart-container half-width">
                <h3><?php _e('Top Endpoints (Last 30 Days)', 'wp-rest-api-key-auth'); ?></h3>
                <canvas id="topEndpointsChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <!-- Top Endpoints Table -->
    <div class="top-endpoints-section">
        <h3><?php _e('Top API Endpoints', 'wp-rest-api-key-auth'); ?></h3>
        <?php if (empty($top_endpoints)): ?>
            <div class="no-data">
                <p><?php _e('No endpoint data available yet.', 'wp-rest-api-key-auth'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php _e('Endpoint', 'wp-rest-api-key-auth'); ?></th>
                        <th scope="col"><?php _e('Requests', 'wp-rest-api-key-auth'); ?></th>
                        <th scope="col"><?php _e('Avg Response Time', 'wp-rest-api-key-auth'); ?></th>
                        <th scope="col"><?php _e('Avg Memory Usage', 'wp-rest-api-key-auth'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_endpoints as $endpoint): ?>
                        <tr>
                            <td><code><?php echo esc_html($endpoint->endpoint); ?></code></td>
                            <td><?php echo $this->format_number($endpoint->request_count); ?></td>
                            <td><?php echo round($endpoint->avg_execution_time, 4); ?>s</td>
                            <td><?php echo $this->format_bytes($endpoint->avg_memory_usage); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Performance Insights -->
    <div class="performance-insights">
        <h3><?php _e('Performance Insights', 'wp-rest-api-key-auth'); ?></h3>
        <div class="insights-grid">
            <?php if ($avg_execution_time > 1): ?>
                <div class="insight-card warning">
                    <div class="insight-icon">⚠️</div>
                    <div class="insight-content">
                        <h4><?php _e('Slow Response Times', 'wp-rest-api-key-auth'); ?></h4>
                        <p><?php _e('Average response time is above 1 second. Consider optimizing your API endpoints.', 'wp-rest-api-key-auth'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($total_errors > 0 && $total_requests > 0): ?>
                <?php $error_percentage = ($total_errors / $total_requests) * 100; ?>
                <?php if ($error_percentage > 5): ?>
                    <div class="insight-card error">
                        <div class="insight-icon">🚨</div>
                        <div class="insight-content">
                            <h4><?php _e('High Error Rate', 'wp-rest-api-key-auth'); ?></h4>
                            <p><?php printf(__('Error rate is %.1f%%. Review your API logs for issues.', 'wp-rest-api-key-auth'), $error_percentage); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($avg_memory_usage > 50 * 1024 * 1024): // 50MB ?>
                <div class="insight-card warning">
                    <div class="insight-icon">💾</div>
                    <div class="insight-content">
                        <h4><?php _e('High Memory Usage', 'wp-rest-api-key-auth'); ?></h4>
                        <p><?php _e('Average memory usage is high. Consider optimizing your API responses.', 'wp-rest-api-key-auth'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($stats) || $total_requests == 0): ?>
                <div class="insight-card info">
                    <div class="insight-icon">ℹ️</div>
                    <div class="insight-content">
                        <h4><?php _e('No Data Available', 'wp-rest-api-key-auth'); ?></h4>
                        <p><?php _e('Start using your API keys to see performance insights here.', 'wp-rest-api-key-auth'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Daily Requests Chart
    const dailyCtx = document.getElementById('dailyRequestsChart').getContext('2d');
    const dailyData = {
        labels: [
            <?php 
            if (!empty($stats)) {
                foreach (array_reverse($stats) as $stat) {
                    echo "'" . esc_js(date('M j', strtotime($stat->date))) . "',";
                }
            }
            ?>
        ],
        datasets: [{
            label: '<?php _e('Requests', 'wp-rest-api-key-auth'); ?>',
            data: [
                <?php 
                if (!empty($stats)) {
                    foreach (array_reverse($stats) as $stat) {
                        echo intval($stat->daily_requests) . ',';
                    }
                }
                ?>
            ],
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    };

    new Chart(dailyCtx, {
        type: 'line',
        data: dailyData,
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Error Rate Chart
    const errorCtx = document.getElementById('errorRateChart').getContext('2d');
    const errorData = {
        labels: [
            <?php 
            if (!empty($error_rates)) {
                foreach (array_reverse($error_rates) as $rate) {
                    echo "'" . esc_js(date('M j', strtotime($rate->date))) . "',";
                }
            }
            ?>
        ],
        datasets: [{
            label: '<?php _e('Error Rate %', 'wp-rest-api-key-auth'); ?>',
            data: [
                <?php 
                if (!empty($error_rates)) {
                    foreach (array_reverse($error_rates) as $rate) {
                        echo floatval($rate->error_rate) . ',';
                    }
                }
                ?>
            ],
            borderColor: 'rgb(255, 99, 132)',
            backgroundColor: 'rgba(255, 99, 132, 0.2)',
            tension: 0.1
        }]
    };

    new Chart(errorCtx, {
        type: 'line',
        data: errorData,
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });

    // Top Endpoints Chart
    const endpointsCtx = document.getElementById('topEndpointsChart').getContext('2d');
    const endpointsData = {
        labels: [
            <?php 
            if (!empty($top_endpoints)) {
                foreach (array_slice($top_endpoints, 0, 5) as $endpoint) {
                    $label = strlen($endpoint->endpoint) > 20 ? substr($endpoint->endpoint, 0, 20) . '...' : $endpoint->endpoint;
                    echo "'" . esc_js($label) . "',";
                }
            }
            ?>
        ],
        datasets: [{
            data: [
                <?php 
                if (!empty($top_endpoints)) {
                    foreach (array_slice($top_endpoints, 0, 5) as $endpoint) {
                        echo intval($endpoint->request_count) . ',';
                    }
                }
                ?>
            ],
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 205, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)'
            ]
        }]
    };

    new Chart(endpointsCtx, {
        type: 'doughnut',
        data: endpointsData,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>

<style>
.stats-summary {
    margin: 20px 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stat-icon {
    font-size: 32px;
    margin-right: 15px;
}

.stat-content .stat-number {
    font-size: 28px;
    font-weight: bold;
    color: #0073aa;
    margin-bottom: 5px;
}

.stat-content .stat-label {
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.charts-section {
    margin: 30px 0;
}

.chart-row {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
}

.chart-container {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    flex: 1;
}

.chart-container.half-width {
    flex: 0 0 calc(50% - 10px);
}

.chart-container h3 {
    margin-top: 0;
    margin-bottom: 20px;
    color: #333;
}

.top-endpoints-section,
.performance-insights {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin: 30px 0;
}

.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.insight-card {
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: flex-start;
}

.insight-card.warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
}

.insight-card.error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
}

.insight-card.info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
}

.insight-icon {
    font-size: 24px;
    margin-right: 15px;
    margin-top: 5px;
}

.insight-content h4 {
    margin: 0 0 10px 0;
    color: #333;
}

.insight-content p {
    margin: 0;
    color: #666;
    line-height: 1.5;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #666;
}

@media (max-width: 768px) {
    .chart-row {
        flex-direction: column;
    }
    
    .chart-container.half-width {
        flex: 1;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style> 