<?php
/**
 * Plugin Name: WP Maintenance Scheduler
 * Plugin URI:  https://www.example.com/wp-maintenance-scheduler
 * Description: Automates essential WordPress maintenance tasks.
 * Version:     1.1
 * Author:      Tevin Richard
 * Author URI:  https://www.example.com/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-maintenance-scheduler
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants.
define( 'WPMS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include necessary files.
require_once WPMS_PLUGIN_PATH . 'includes/settings.php';
require_once WPMS_PLUGIN_PATH . 'includes/scheduler.php';
require_once WPMS_PLUGIN_PATH . 'includes/reporter.php';

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, 'wpms_activate' );
register_deactivation_hook( __FILE__, 'wpms_deactivate' );

/**
 * Plugin activation callback.
 */
function wpms_activate() {
	// Schedule the cron event.
	wpms_schedule_event();
}

/**
 * Plugin deactivation callback.
 */
function wpms_deactivate() {
	// Clear the scheduled cron event.
	wp_schedule_event( time() - DAY_IN_SECONDS, 'daily', 'wpms_cron_hook' );
}

/**
 * Schedule the cron event.
 */
function wpms_schedule_event() {
	if ( ! wp_next_scheduled( 'wpms_cron_hook' ) ) {
		$schedule_time = wp_next_scheduled( 'wpms_cron_hook', array(), 'wpms_schedule' );
		wp_schedule_event( $schedule_time, 'wpms_schedule', 'wpms_cron_hook' );
	}
}

add_action( 'wpms_cron_hook', 'wpms_run_maintenance' );

/**
 * Run the maintenance tasks.
 */
function wpms_run_maintenance() {
	// 1. Update outdated plugins.
	wpms_update_plugins();

	// 2. Clear W3 Total Cache.
	wpms_clear_w3tc_cache();

	// 3. Run WP-Optimize optimizations.
	wpms_run_wpoptimize();

	// 4. Send the report.
	wpms_send_report();
}

/**
 * Update outdated plugins.
 */
function wpms_update_plugins() {
	// Include the plugin updater.
	include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	// Get outdated plugins.
	$outdated_plugins = get_site_transient( 'update_plugins' );

	if ( isset( $outdated_plugins->response ) && is_array( $outdated_plugins->response ) ) {
		// Loop through outdated plugins and update them.
		foreach ( $outdated_plugins->response as $plugin_file => $plugin_data ) {
			$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
			$upgrader->upgrade( $plugin_file );
		}
	}
}

/**
 * Clear W3 Total Cache.
 */
function wpms_clear_w3tc_cache() {
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
	}
}

/**
 * Run WP-Optimize optimizations.
 */
function wpms_run_wpoptimize() {
	if ( class_exists( 'WP_Optimize' ) ) {
		WP_Optimize()->get_page_cache()->purge();
		WP_Optimize()->get_db_cleaner()->clean_all();
	}
}

/**
 * Send the report.
 */
function wpms_send_report() {
	// Get the recipient email address from the settings.
	$recipient_email = get_option( 'wpms_email_address' );

	// Prepare the report content.
	$report_content = 'WP Maintenance Scheduler Report:' . "\n\n";
	$report_content .= 'Plugins updated: ' . ( isset( $outdated_plugins->response ) ? count( $outdated_plugins->response ) : 0 ) . "\n";
	$report_content .= 'W3 Total Cache cleared: ' . ( function_exists( 'w3tc_flush_all' ) ? 'Yes' : 'No' ) . "\n";
	$report_content .= 'WP-Optimize optimizations run: ' . ( class_exists( 'WP_Optimize' ) ? 'Yes' : 'No' ) . "\n";

	// Send the email.
	wp_mail( $recipient_email, 'WP Maintenance Scheduler Report', $report_content );
}