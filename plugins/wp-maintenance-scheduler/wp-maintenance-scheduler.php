<?php
/*
Plugin Name: WP Maintenance Scheduler
Description: Automated maintenance system with email reporting
Version: 1.0
Author: Tevin Richard
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
    // Set default options if they don't exist
    if (get_option('maintenance_scheduler_frequency') === false) {
        update_option('maintenance_scheduler_frequency', 'weekly');
    }
    if (get_option('maintenance_scheduler_update_plugins') === false) {
        update_option('maintenance_scheduler_update_plugins', 1);
    }
    if (get_option('maintenance_scheduler_update_themes') === false) {
        update_option('maintenance_scheduler_update_themes', 1);
    }
    if (get_option('maintenance_scheduler_email') === false) {
        update_option('maintenance_scheduler_email', get_option('admin_email'));
    }
    
    // Schedule the maintenance task
    if (!wp_next_scheduled('maintenance_scheduler_hook')) {
        wp_schedule_event(time(), get_option('maintenance_scheduler_frequency', 'weekly'), 'maintenance_scheduler_hook');
    }
}
register_activation_hook(__FILE__, 'initialize_maintenance_scheduler');

// Clean up on deactivation
function cleanup_maintenance_scheduler() {
    wp_clear_scheduled_hook('maintenance_scheduler_hook');
}
register_deactivation_hook(__FILE__, 'cleanup_maintenance_scheduler');

// Clean up all options on uninstall
function maintenance_scheduler_uninstall() {
    delete_option('maintenance_scheduler_frequency');
    delete_option('maintenance_scheduler_email');
    delete_option('maintenance_scheduler_update_plugins');
    delete_option('maintenance_scheduler_update_themes');
    delete_option('last_maintenance_run');
    delete_option('maintenance_metrics');
    wp_clear_scheduled_hook('maintenance_scheduler_hook');
}
register_uninstall_hook(__FILE__, 'maintenance_scheduler_uninstall');

// Add custom schedules
function add_custom_schedules($schedules) {
    if (!isset($schedules['weekly'])) {
        $schedules['weekly'] = array(
            'interval' => 604800, // 7 days in seconds
            'display' => __('Once Weekly')
        );
    }
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