<?php
/**
 * Send the report email.
 */
function wpms_send_report() {
	// Get the recipient email address from the settings.
	$recipient_email = get_option( 'wpms_email_address' );

	// Prepare the report content.
	$report_content = 'WP Maintenance Scheduler Report:' . "\n\n";
	$report_content .= 'Plugins updated: ' . ( isset( $GLOBALS['wpms_updated_plugins'] ) ? count( $GLOBALS['wpms_updated_plugins'] ) : 0 ) . "\n";
	$report_content .= 'W3 Total Cache cleared: ' . ( function_exists( 'w3tc_flush_all' ) ? 'Yes' : 'No' ) . "\n";
	$report_content .= 'WP-Optimize optimizations run: ' . ( class_exists( 'WP_Optimize' ) ? 'Yes' : 'No' ) . "\n";

	// Send the email.
	wp_mail( $recipient_email, 'WP Maintenance Scheduler Report', $report_content );
}