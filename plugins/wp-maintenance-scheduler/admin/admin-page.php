<?php
if (!defined('ABSPATH')) {
    exit;
}

// Register settings
function maintenance_scheduler_register_settings() {
    register_setting('maintenance_scheduler_options', 'maintenance_scheduler_frequency');
    register_setting('maintenance_scheduler_options', 'maintenance_scheduler_email');
    register_setting('maintenance_scheduler_options', 'maintenance_scheduler_update_plugins');
    register_setting('maintenance_scheduler_options', 'maintenance_scheduler_update_themes');
}
add_action('admin_init', 'maintenance_scheduler_register_settings');

// Add admin menu item
function maintenance_scheduler_menu() {
    add_management_page(
        'Maintenance Scheduler',
        'Maintenance Scheduler',
        'manage_options',
        'maintenance-scheduler',
        'maintenance_scheduler_page'
    );
}
add_action('admin_menu', 'maintenance_scheduler_menu');

// Add admin styles
function maintenance_scheduler_admin_styles() {
    if (isset($_GET['page']) && $_GET['page'] === 'maintenance-scheduler') {
        ?>
        <style>
            .maintenance-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
            }
            .maintenance-metrics {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-top: 15px;
            }
            .metric-item {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 4px;
                border-left: 4px solid #007cba;
            }
            .maintenance-status {
                margin-top: 20px;
            }
            .maintenance-log {
                max-height: 300px;
                overflow-y: auto;
                background: #f8f9fa;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .success-message { color: #28a745; }
            .error-message { color: #dc3545; }
            .warning-message { color: #ffc107; }
            .schedule-info {
                background: #e9ecef;
                padding: 10px 15px;
                border-radius: 4px;
                margin: 10px 0;
            }
        </style>
        <?php
    }
}
add_action('admin_head', 'maintenance_scheduler_admin_styles');

function maintenance_scheduler_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save settings
    if (isset($_POST['save_settings']) && check_admin_referer('maintenance_scheduler_settings')) {
        update_option('maintenance_scheduler_frequency', sanitize_text_field($_POST['frequency']));
        update_option('maintenance_scheduler_email', sanitize_email($_POST['email']));
        update_option('maintenance_scheduler_update_plugins', isset($_POST['update_plugins']) ? 1 : 0);
        update_option('maintenance_scheduler_update_themes', isset($_POST['update_themes']) ? 1 : 0);
        
        // Reschedule the maintenance task
        wp_clear_scheduled_hook('maintenance_scheduler_hook');
        wp_schedule_event(time(), get_option('maintenance_scheduler_frequency', 'weekly'), 'maintenance_scheduler_hook');
        
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
    }

    // Handle manual maintenance run
    if (isset($_POST['run_maintenance']) && check_admin_referer('run_maintenance_nonce')) {
        try {
            $maintenance = new SiteMaintenance();
            $result = $maintenance->run_maintenance();
            
            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>Maintenance completed successfully. Check your email for the detailed report.</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Maintenance encountered some issues. Please check the error log.</p></div>';
            }
        } catch (Exception $e) {
            echo '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';
        }
    }
    ?>

    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <!-- Settings Section -->
        <div class="maintenance-card">
            <h2>Maintenance Settings</h2>
            <form method="post">
                <?php wp_nonce_field('maintenance_scheduler_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="frequency">Schedule Frequency</label></th>
                        <td>
                            <select name="frequency" id="frequency">
                                <option value="hourly" <?php selected(get_option('maintenance_scheduler_frequency'), 'hourly'); ?>>Hourly</option>
                                <option value="twicedaily" <?php selected(get_option('maintenance_scheduler_frequency'), 'twicedaily'); ?>>Twice Daily</option>
                                <option value="daily" <?php selected(get_option('maintenance_scheduler_frequency'), 'daily'); ?>>Daily</option>
                                <option value="weekly" <?php selected(get_option('maintenance_scheduler_frequency'), 'weekly'); ?>>Weekly</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="email">Notification Email</label></th>
                        <td>
                            <input type="email" name="email" id="email" value="<?php echo esc_attr(get_option('maintenance_scheduler_email', get_option('admin_email'))); ?>" class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Update Settings</th>
                        <td>
                            <label>
                                <input type="checkbox" name="update_plugins" <?php checked(get_option('maintenance_scheduler_update_plugins'), 1); ?>>
                                Automatically update plugins
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" name="update_themes" <?php checked(get_option('maintenance_scheduler_update_themes'), 1); ?>>
                                Automatically update themes
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="save_settings" class="button button-primary" value="Save Settings">
                </p>
            </form>
        </div>

        <!-- Status Section -->
        <div class="maintenance-card">
            <h2>System Status</h2>
            <div class="maintenance-metrics">
                <div class="metric-item">
                    <h3>Last Maintenance Run</h3>
                    <p><?php echo get_option('last_maintenance_run') ? date('Y-m-d H:i:s', get_option('last_maintenance_run')) : 'Never'; ?></p>
                </div>
                <div class="metric-item">
                    <h3>Next Scheduled Run</h3>
                    <p><?php echo wp_next_scheduled('maintenance_scheduler_hook') ? date('Y-m-d H:i:s', wp_next_scheduled('maintenance_scheduler_hook')) : 'Not scheduled'; ?></p>
                </div>
            </div>
        </div>

        <!-- Manual Run Section -->
        <div class="maintenance-card">
            <h2>Manual Maintenance</h2>
            <p>Click the button below to run maintenance tasks manually. This might take a few minutes.</p>
            <form method="post">
                <?php wp_nonce_field('run_maintenance_nonce'); ?>
                <input type="submit" name="run_maintenance" class="button button-primary" value="Run Maintenance Now">
            </form>
        </div>

        <!-- Metrics Section -->
        <?php
        $metrics = get_option('maintenance_metrics');
        if ($metrics): ?>
            <div class="maintenance-card">
                <h2>Latest System Metrics</h2>
                <div class="maintenance-metrics">
                    <div class="metric-item">
                        <h3>Database Size</h3>
                        <p><?php echo size_format($metrics['db_size']); ?></p>
                    </div>
                    <div class="metric-item">
                        <h3>Response Time</h3>
                        <p><?php echo round($metrics['response_time'], 2); ?> seconds</p>
                    </div>
                    <div class="metric-item">
                        <h3>Memory Usage</h3>
                        <p><?php echo size_format($metrics['memory_usage']); ?></p>
                    </div>
                    <div class="metric-item">
                        <h3>PHP Version</h3>
                        <p><?php echo esc_html($metrics['php_version']); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Logs Section -->
        <div class="maintenance-card">
            <h2>Recent Error Logs</h2>
            <div class="maintenance-log">
                <?php
                $log_file = WP_CONTENT_DIR . '/debug.log';
                if (file_exists($log_file)) {
                    $logs = file_get_contents($log_file);
                    if ($logs) {
                        $logs = array_filter(
                            explode("\n", $logs),
                            function($line) {
                                return strpos($line, date('Y-m-d')) !== false;
                            }
                        );
                        if (!empty($logs)) {
                            echo '<pre>' . esc_html(implode("\n", array_slice($logs, -20))) . '</pre>';
                        } else {
                            echo '<p>No recent errors found.</p>';
                        }
                    } else {
                        echo '<p>Error log is empty.</p>';
                    }
                } else {
                    echo '<p>No error log file found.</p>';
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}

// Add dashboard widget
function add_maintenance_dashboard_widget() {
    wp_add_dashboard_widget(
        'maintenance_dashboard_widget',
        'Maintenance Status',
        'display_maintenance_status'
    );
}
add_action('wp_dashboard_setup', 'add_maintenance_dashboard_widget');

function display_maintenance_status() {
    $last_run = get_option('last_maintenance_run');
    $next_run = wp_next_scheduled('maintenance_scheduler_hook');
    $metrics = get_option('maintenance_metrics');
    
    echo '<div class="maintenance-status">';
    echo '<p><strong>Last Run:</strong> ' . ($last_run ? date('Y-m-d H:i:s', $last_run) : 'Never') . '</p>';
    echo '<p><strong>Next Scheduled:</strong> ' . ($next_run ? date('Y-m-d H:i:s', $next_run) : 'Not scheduled') . '</p>';
    
    if ($metrics) {
        echo '<h4>Latest Metrics:</h4>';
        echo '<ul>';
        echo '<li><strong>Database Size:</strong> ' . size_format($metrics['db_size']) . '</li>';
        echo '<li><strong>Response Time:</strong> ' . round($metrics['response_time'], 2) . ' seconds</li>';
        echo '<li><strong>Memory Usage:</strong> ' . size_format($metrics['memory_usage']) . '</li>';
        echo '</ul>';
    }
    echo '</div>';
}