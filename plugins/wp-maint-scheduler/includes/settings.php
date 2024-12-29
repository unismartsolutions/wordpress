<?php
// Add the admin menu page.
add_action( 'admin_menu', 'wpms_add_admin_menu' );

/**
 * Add the admin menu page.
 */
function wpms_add_admin_menu() {
	add_options_page(
		'WP Maintenance Scheduler',
		'WP Maintenance Scheduler',
		'manage_options',
		'wp-maintenance-scheduler',
		'wpms_settings_page'
	);
}

/**
 * Render the settings page.
 */
function wpms_settings_page() {
	?>
	<div class="wrap">
		<h1>WP Maintenance Scheduler Settings</h1>

		<form method="post" action="options.php">
			<?php settings_fields( 'wpms_settings_group' ); ?>
			<?php do_settings_sections( 'wp-maintenance-scheduler' ); ?>

			<table class="form-table">
				<tr valign="top">
					<th scope="row">Schedule Kickoff Time</th>
					<td>
						<input type="time" name="wpms_kickoff_time" value="<?php echo esc_attr( get_option( 'wpms_kickoff_time' ) ); ?>" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Frequency</th>
					<td>
						<select name="wpms_frequency">
							<option value="daily" <?php selected( get_option( 'wpms_frequency' ), 'daily' ); ?>>Daily</option>
							<option value="weekly" <?php selected( get_option( 'wpms_frequency' ), 'weekly' ); ?>>Weekly</option>
							<option value="monthly" <?php selected( get_option( 'wpms_frequency' ), 'monthly' ); ?>>Monthly</option>
						</select>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">Email Address</th>
					<td>
						<input type="email" name="wpms_email_address" value="<?php echo esc_attr( get_option( 'wpms_email_address' ) ); ?>" />
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

// Register settings.
add_action( 'admin_init', 'wpms_register_settings' );

/**
 * Register settings.
 */
function wpms_register_settings() {
	register_setting( 'wpms_settings_group', 'wpms_kickoff_time' );
	register_setting( 'wpms_settings_group', 'wpms_frequency' );
	register_setting( 'wpms_settings_group', 'wpms_email_address' );
}