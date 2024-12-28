<?php
/*
Plugin Name: WP Maintenance Scheduler
Description: Automated maintenance system with email reporting
Version: 1.0
Author: Your Name
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/class-maintenance.php';
require_once plugin_dir_path(__FILE__) . 'admin/admin-page.php';

// Initialize the plugin
function initialize_maintenance_scheduler() {
    // Schedule maintenance tasks
    if (!wp_next_scheduled('maintenance_scheduler_hook')) {
        wp_schedule_event(time(), 'weekly', 'maintenance_scheduler_hook');
    }
}
register_activation_hook(__FILE__, 'initialize_maintenance_scheduler');

// Clean up on deactivation
function cleanup_maintenance_scheduler() {
    wp_clear_scheduled_hook('maintenance_scheduler_hook');
}
register_deactivation_hook(__FILE__, 'cleanup_maintenance_scheduler');

// Add custom schedules if needed
function add_custom_schedules($schedules) {
    $schedules['weekly'] = array(
        'interval' => 604800, // 7 days in seconds
        'display' => __('Once Weekly')
    );
    return $schedules;
}
add_filter('cron_schedules', 'add_custom_schedules');

// Hook for scheduled maintenance
add_action('maintenance_scheduler_hook', function() {
    $maintenance = new SiteMaintenance();
    $maintenance->run_maintenance();
});

// Add plugin settings link
function add_plugin_settings_link($links) {
    $settings_link = '<a href="tools.php?page=maintenance-scheduler">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_plugin_settings_link');