<?php
// Schedule the cron event based on user settings.
add_filter( 'cron_schedules', 'wpms_add_cron_schedule' );

/**
 * Add custom cron schedule.
 */
function wpms_add_cron_schedule( $schedules ) {
	$schedules['wpms_schedule'] = array(
		'interval' => wpms_get_schedule_interval(),
		'display'  => 'WP Maintenance Schedule',
	);

	return $schedules;
}

/**
 * Get the schedule interval in seconds.
 */
function wpms_get_schedule_interval() {
	$frequency = get_option( 'wpms_frequency', 'daily' );

	switch ( $frequency ) {
		case 'weekly':
			return WEEK_IN_SECONDS;
		case 'monthly':
			return MONTH_IN_SECONDS;
		default:
			return DAY_IN_SECONDS;
	}
}

// Schedule the event at the specified kickoff time.
add_action( 'wpms_cron_hook', 'wpms_schedule_event_at_kickoff_time' );

/**
 * Schedule the event at the kickoff time.
 */
function wpms_schedule_event_at_kickoff_time() {
	$kickoff_time = get_option( 'wpms_kickoff_time', '00:00' );
	$timestamp     = strtotime( $kickoff_time );

	if ( $timestamp !== false ) {
		wp_schedule_event( $timestamp, 'wpms_schedule', 'wpms_cron_hook' );
	}
}