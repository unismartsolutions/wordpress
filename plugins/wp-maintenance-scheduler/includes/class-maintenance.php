<?php
if (!defined('ABSPATH')) {
   exit;
}

class SiteMaintenance {
   private $report_data = array();

   public function run_maintenance() {
       $this->report_data['start_time'] = current_time('mysql');
       
       try {
           // Run tasks and collect results
           $this->report_data['updates'] = $this->update_wordpress();
           $this->report_data['cache'] = $this->clear_cache();
           $this->report_data['errors'] = $this->check_error_logs();
           $this->report_data['performance'] = $this->monitor_performance();
           
           // Send detailed report
           $this->send_detailed_report();
           
           // Save last run time and metrics
           update_option('last_maintenance_run', time());
           update_option('maintenance_metrics', $this->report_data['performance']);
           
           return true;
       } catch (Exception $e) {
           error_log('Maintenance error: ' . $e->getMessage());
           return false;
       }
   }

   private function update_wordpress() {
       require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader.php');
       require_once(ABSPATH . 'wp-admin/includes/misc.php');
       require_once(ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php');
       require_once(ABSPATH . 'wp-admin/includes/plugin-api.php');
       require_once(ABSPATH . 'wp-admin/includes/update.php');
       
       $updates = array(
           'plugins' => array(),
           'themes' => array()
       );
       
       try {
           // Only update if enabled in settings
           if (get_option('maintenance_scheduler_update_plugins', 1)) {
               // Force WordPress to check for plugin updates
               wp_update_plugins();
               $plugins_requiring_update = get_plugin_updates();
               
               if (!empty($plugins_requiring_update)) {
                   $plugin_upgrader = new Plugin_Upgrader(new WP_Upgrader_Skin());
                   
                   foreach ($plugins_requiring_update as $plugin_file => $plugin_data) {
                       $result = $plugin_upgrader->upgrade($plugin_file);
                       $updates['plugins'][] = array(
                           'name' => $plugin_data->Name,
                           'result' => $result !== false,
                           'old_version' => $plugin_data->Version,
                           'new_version' => $plugin_data->update->new_version,
                           'error' => $result === false ? $plugin_upgrader->skin->get_upgrade_messages() : null
                       );
                   }
               }
           }
           
           // Only update if enabled in settings
           if (get_option('maintenance_scheduler_update_themes', 1)) {
               // Force WordPress to check for theme updates
               wp_update_themes();
               $themes_requiring_update = get_theme_updates();
               
               if (!empty($themes_requiring_update)) {
                   $theme_upgrader = new Theme_Upgrader(new WP_Upgrader_Skin());
                   
                   foreach ($themes_requiring_update as $theme_file => $theme_data) {
                       $result = $theme_upgrader->upgrade($theme_file);
                       $updates['themes'][] = array(
                           'name' => $theme_data->Name,
                           'result' => $result !== false,
                           'old_version' => $theme_data->Version,
                           'new_version' => $theme_data->update->new_version,
                           'error' => $result === false ? $theme_upgrader->skin->get_upgrade_messages() : null
                       );
                   }
               }
           }
       } catch (Exception $e) {
           error_log('Update error: ' . $e->getMessage());
       }
       
       return $updates;
   }

   private function clear_cache() {
       $cache_cleared = false;
       
       try {
           // Clear W3 Total Cache
           if (function_exists('w3tc_flush_all')) {
               w3tc_flush_all();
               $cache_cleared = true;
           }
           
           // Clear WP Rocket
           if (function_exists('rocket_clean_domain')) {
               rocket_clean_domain();
               $cache_cleared = true;
           }
           
           // Clear WP Super Cache
           if (function_exists('wp_cache_clear_cache')) {
               wp_cache_clear_cache();
               $cache_cleared = true;
           }
           
           // Clear WordPress object cache
           wp_cache_flush();
           
           // Clear transients
           $this->clear_expired_transients();
           
           $cache_cleared = true;
       } catch (Exception $e) {
           error_log('Cache clearing error: ' . $e->getMessage());
       }
       
       return $cache_cleared;
   }

   private function clear_expired_transients() {
       global $wpdb;

       $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_%' AND option_value < " . time());
       $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_site_transient_%' AND option_value < " . time());
   }

   private function check_error_logs() {
       $log_file = WP_CONTENT_DIR . '/debug.log';
       $errors = array();
       
       try {
           if (file_exists($log_file)) {
               $log_content = file_get_contents($log_file);
               if ($log_content !== false) {
                   // Get last 24 hours of logs
                   $today_logs = array_filter(
                       explode("\n", $log_content),
                       function($line) {
                           return strpos($line, date('Y-m-d')) !== false;
                       }
                   );
                   $errors = array_merge($errors, array_slice($today_logs, -50)); // Limit to last 50 errors
               }
           }
       } catch (Exception $e) {
           error_log('Error log check failed: ' . $e->getMessage());
       }
       
       return $errors;
   }

   private function monitor_performance() {
       global $wpdb;
       $metrics = array();
       
       try {
           // Database size
           $db_size = $wpdb->get_row("SELECT SUM(data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
           
           // Response time
           $response_time = $this->check_response_time();
           
           // Memory usage
           $memory_usage = memory_get_peak_usage(true);
           
           $metrics = array(
               'db_size' => $db_size->size,
               'response_time' => $response_time,
               'memory_usage' => $memory_usage,
               'php_version' => phpversion(),
               'wordpress_version' => get_bloginfo('version'),
               'total_plugins' => count(get_plugins()),
               'active_plugins' => count(get_option('active_plugins')),
               'server_info' => $_SERVER['SERVER_SOFTWARE']
           );

           // Check disk space if possible
           if (function_exists('disk_free_space')) {
               $metrics['disk_free_space'] = disk_free_space(ABSPATH);
           }

           // Get database tables status
           $tables_status = $wpdb->get_results("SHOW TABLE STATUS");
           $metrics['db_tables'] = count($tables_status);
           
           // Count posts and pages
           $metrics['total_posts'] = wp_count_posts()->publish;
           $metrics['total_pages'] = wp_count_posts('page')->publish;

       } catch (Exception $e) {
           error_log('Performance monitoring error: ' . $e->getMessage());
       }
       
       return $metrics;
   }

   private function check_response_time() {
       $start = microtime(true);
       $response = wp_remote_get(home_url());
       $end = microtime(true);
       return $end - $start;
   }

   private function send_detailed_report() {
       $to = get_option('maintenance_scheduler_email', get_option('admin_email'));
       $subject = 'Maintenance Report - ' . get_bloginfo('name') . ' - ' . date('Y-m-d');
       
       $message = $this->generate_html_report();
       
       $headers = array(
           'Content-Type: text/html; charset=UTF-8',
           'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
       );
       
       return wp_mail($to, $subject, $message, $headers);
   }

   private function generate_html_report() {
       $html = $this->get_report_header();
       $html .= $this->get_report_updates_section();
       $html .= $this->get_report_cache_section();
       $html .= $this->get_report_errors_section();
       $html .= $this->get_report_performance_section();
       $html .= $this->get_report_footer();
       
       return $html;
   }

   private function get_report_header() {
       return '
       <html>
       <head>
           <style>
               body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
               .container { max-width: 800px; margin: 0 auto; }
               .section { margin: 20px 0; background: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
               .success { color: #28a745; }
               .error { color: #dc3545; }
               .warning { color: #ffc107; }
               table { border-collapse: collapse; width: 100%; margin: 15px 0; }
               th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
               th { background-color: #f8f9fa; }
               h2, h3 { color: #007bff; margin-top: 20px; }
               .metric-card { background: #f8f9fa; padding: 15px; margin: 10px 0; border-radius: 4px; }
           </style>
       </head>
       <body>
           <div class="container">
               <h2>WordPress Maintenance Report</h2>
               <p>Report generated on: ' . $this->report_data['start_time'] . '</p>';
   }

   private function get_report_updates_section() {
       $html = '
           <div class="section">
               <h3>Updates Performed</h3>';
       
       if (get_option('maintenance_scheduler_update_plugins', 1)) {
           $html .= '
               <h4>Plugins:</h4>';
           if (empty($this->report_data['updates']['plugins'])) {
               $html .= '<p>No plugin updates were necessary.</p>';
           } else {
               $html .= '
               <table>
                   <tr>
                       <th>Plugin</th>
                       <th>Status</th>
                       <th>Version</th>
                   </tr>';
               
               foreach($this->report_data['updates']['plugins'] as $plugin) {
                   $status_class = $plugin['result'] ? 'success' : 'error';
                   $status_text = $plugin['result'] ? 'Updated successfully' : 'Update failed';
                   $version_text = $plugin['result'] ? "{$plugin['old_version']} → {$plugin['new_version']}" : $plugin['old_version'];
                   
                   $html .= "
                   <tr>
                       <td>{$plugin['name']}</td>
                       <td class='{$status_class}'>{$status_text}</td>
                       <td>{$version_text}</td>
                   </tr>";
                   
                   if (!$plugin['result'] && $plugin['error']) {
                       $html .= "
                       <tr>
                           <td colspan='3' class='error'>" . implode('<br>', $plugin['error']) . "</td>
                       </tr>";
                   }
               }
               
               $html .= '</table>';
           }
       } else {
           $html .= '<p>Plugin updates are disabled in settings.</p>';
       }
       
       if (get_option('maintenance_scheduler_update_themes', 1)) {
           $html .= '
               <h4>Themes:</h4>';
           if (empty($this->report_data['updates']['themes'])) {
               $html .= '<p>No theme updates were necessary.</p>';
           } else {
               $html .= '
               <table>
                   <tr>
                       <th>Theme</th>
                       <th>Status</th>
                       <th>Version</th>
                   </tr>';
               
               foreach($this->report_data['updates']['themes'] as $theme) {
                   $status_class = $theme['result'] ? 'success' : 'error';
                   $status_text = $theme['result'] ? 'Updated successfully' : 'Update failed';
                   $version_text = $theme['result'] ? "{$theme['old_version']} → {$theme['new_version']}" : $theme['old_version'];
                   
                   $html .= "
                   <tr>
                       <td>{$theme['name']}</td>
                       <td class='{$status_class}'>{$status_text}</td>
                       <td>{$version_text}</td>
                   </tr>";
                   
                   if (!$theme['result'] && $theme['error']) {
                       $html .= "
                       <tr>
                           <td colspan='3' class='error'>" . implode('<br>', $theme['error']) . "</td>
                       </tr>";
                   }
               }
               
               $html .= '</table>';
           }
       } else {
           $html .= '<p>Theme updates are disabled in settings.</p>';
       }
       
       $html .= '</div>';
       return $html;
   }

   private function get_report_cache_section() {
       return '
           <div class="section">
               <h3>Cache Status</h3>
               <div class="metric-card">
                   <p>Cache cleared: <span class="' . ($this->report_data['cache'] ? 'success' : 'error') . '">' 
                   . ($this->report_data['cache'] ? 'Yes' : 'No') . '</span></p>
               </div>
           </div>';
   }

   private function get_report_errors_section() {
       $html = '
           <div class="section">
               <h3>Error Logs</h3>';
       
       if(empty($this->report_data['errors'])) {
           $html .= '<div class="metric-card success"><p>No errors found in the last 24 hours.</p></div>';
       } else {
           $html .= '
               <table>
                   <tr>
                       <th>Error</th>
                   </tr>';
           foreach($this->report_data['errors'] as $error) {
               $html .= "<tr><td class='error'>" . esc_html($error) . "</td></tr>";
           }
           $html .= '</table>';
       }
       
       $html .= '</div>';
       return $html;
   }

   private function get_report_performance_section() {
    $metrics = $this->report_data['performance'];
    $html = '
        <div class="section">
            <h3>Performance Metrics</h3>
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Value</th>
                </tr>
                <tr>
                    <td>Database Size</td>
                    <td>' . size_format($metrics['db_size']) . '</td>
                </tr>
                <tr>
                    <td>Response Time</td>
                    <td>' . round($metrics['response_time'], 2) . ' seconds</td>
                </tr>
                <tr>
                    <td>Memory Usage</td>
                    <td>' . size_format($metrics['memory_usage']) . '</td>
                </tr>
                <tr>
                    <td>PHP Version</td>
                    <td>' . esc_html($metrics['php_version']) . '</td>
                </tr>
                <tr>
                    <td>WordPress Version</td>
                    <td>' . esc_html($metrics['wordpress_version']) . '</td>
                </tr>
                <tr>
                    <td>Total Plugins</td>
                    <td>' . esc_html($metrics['total_plugins']) . '</td>
                </tr>
                <tr>
                    <td>Active Plugins</td>
                    <td>' . esc_html($metrics['active_plugins']) . '</td>
                </tr>
                <tr>
                    <td>Total Posts</td>
                    <td>' . esc_html($metrics['total_posts']) . '</td>
                </tr>
                <tr>
                    <td>Total Pages</td>
                    <td>' . esc_html($metrics['total_pages']) . '</td>
                </tr>
                <tr>
                    <td>Database Tables</td>
                    <td>' . esc_html($metrics['db_tables']) . '</td>
                </tr>
                <tr>
                    <td>Server Info</td>
                    <td>' . esc_html($metrics['server_info']) . '</td>
                </tr>';

    if (isset($metrics['disk_free_space'])) {
        $html .= '
                <tr>
                    <td>Free Disk Space</td>
                    <td>' . size_format($metrics['disk_free_space']) . '</td>
                </tr>';
    }

    $html .= '
            </table>
        </div>';

    return $html;
}

private function get_report_footer() {
    return '
            <div class="section">
                <p>This is an automated maintenance report from ' . get_bloginfo('name') . '.</p>
                <p>Maintenance schedule: ' . get_option('maintenance_scheduler_frequency', 'weekly') . '</p>
            </div>
        </div>
    </body>
    </html>';
}
}