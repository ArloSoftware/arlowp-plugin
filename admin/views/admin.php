<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      https://arlo.co
 * @copyright 2018 Arlo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="wrap arlo-wrap">
	<div class="arlo-page-header">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<?php
		$arlo_ph_settings = get_option('arlo_settings', []);
		if (!empty($arlo_ph_settings['platform_name'])):
			$arlo_ph_import_disabled  = get_option('arlo_import_disabled', '0') == '1';
			$arlo_ph_health_disabled  = (($arlo_ph_settings['import_connection_healthchecks_enabled'] ?? '1') === '1')
				&& get_option('arlo_import_connection_health_disabled', '0') == '1';
			$arlo_ph_user_sync_off    = ($arlo_ph_settings['user_import_enabled'] ?? '1') !== '1';
			$arlo_ph_reconnecting     = !$arlo_ph_import_disabled && !$arlo_ph_health_disabled
				&& !$arlo_ph_user_sync_off
				&& (int) get_option('arlo_platform_access_failure_count', 0) > 0;
			if ($arlo_ph_import_disabled):
				$arlo_ph_badge_class = 'arlo-badge-warning';
				$arlo_ph_badge_label = __('Setup required', 'arlo-training-and-event-management-system');
			elseif ($arlo_ph_health_disabled):
				$arlo_ph_badge_class = 'arlo-badge-error';
				$arlo_ph_badge_label = __('Disconnected', 'arlo-training-and-event-management-system');
			elseif ($arlo_ph_reconnecting):
				$arlo_ph_badge_class = 'arlo-badge-neutral';
				$arlo_ph_badge_label = __('Connecting', 'arlo-training-and-event-management-system');
			else:
				$arlo_ph_badge_class = 'arlo-badge-success';
				$arlo_ph_badge_label = __('Connected', 'arlo-training-and-event-management-system');
			endif;
		?>
		<div class="arlo-platform-indicator">
			<span class="arlo-platform-name"><?php echo esc_html($arlo_ph_settings['platform_name']); ?>.arlo.co</span>
			<span class="arlo-badge <?php echo esc_attr($arlo_ph_badge_class); ?>"><?php echo esc_html( $arlo_ph_badge_label ); ?></span>
		</div>
		<?php endif; ?>
	</div>
	<hr class="wp-header-end">
	<?php settings_errors( 'general' ); ?>
	<?php settings_errors( 'arlo_settings' ); ?>
	<div class="<?php echo esc_attr(ARLO_PLUGIN_PREFIX. '-sections-wrap'); ?> arlo-initializing">
		<form id="<?php echo esc_attr(ARLO_PLUGIN_PREFIX. '-settings'); ?>" method="post" action="options.php">
			<h2 class="nav-tab-wrapper main-tab">
				<a href="<?php echo esc_url(admin_url( 'admin.php?page=arlo-for-wordpress'))?>#theme" class="nav-tab" id="<?php echo esc_attr($this->plugin_slug)?>-tab-theme">Theme</a>
				<a href="<?php echo esc_url(admin_url( 'admin.php?page=arlo-for-wordpress'))?>#general" class="nav-tab" id="<?php echo esc_attr($this->plugin_slug)?>-tab-general">General</a>
				<a href="<?php echo esc_url(admin_url( 'admin.php?page=arlo-for-wordpress'))?>#pages" class="nav-tab" id="<?php echo esc_attr($this->plugin_slug)?>-tab-pages">Pages</a>
				<a href="<?php echo esc_url(admin_url( 'admin.php?page=arlo-for-wordpress'))?>#regions" class="nav-tab" id="<?php echo esc_attr($this->plugin_slug)?>-tab-regions">Regions</a>
				<a href="<?php echo esc_url(admin_url( 'admin.php?page=arlo-for-wordpress'))?>#customcss" class="nav-tab" id="<?php echo esc_attr($this->plugin_slug)?>-tab-customcss">Custom CSS</a>
				<a href="<?php echo esc_url(admin_url( 'admin.php?page=arlo-for-wordpress'))?>#misc" class="nav-tab" id="<?php echo esc_attr($this->plugin_slug)?>-tab-misc">Misc</a>
				<a href="<?php echo esc_url(admin_url( 'admin.php?page=arlo-for-wordpress'))?>#systemrequirements" class="nav-tab" id="<?php echo esc_attr($this->plugin_slug)?>-tab-systemrequirements">System requirements</a>
				<a href="<?php echo esc_url(admin_url( 'admin.php?page=arlo-for-wordpress'))?>#changelog" class="nav-tab" id="<?php echo esc_attr($this->plugin_slug)?>-tab-changelog">Changelog</a>
				<a href="<?php echo esc_url(admin_url( 'admin.php?page=arlo-for-wordpress'))?>#support" class="nav-tab" id="<?php echo esc_attr($this->plugin_slug)?>-tab-support">Support</a>
			</h2>		
			<?php settings_fields( 'arlo_settings' ); ?>
            <?php $this->do_settings_sections( $this->plugin_slug ); ?>
            <?php submit_button(); ?>
		</form>
		<div class="arlo-init-spinner" role="status" aria-live="polite">
			<span class="screen-reader-text">
				<?php echo esc_html__( 'Loading settings…', 'arlo-training-and-event-management-system' ); ?>
			</span>
			<div class="arlo-init-spinner__icon" aria-hidden="true"></div>
		</div>
	</div>
	<script type="text/javascript">
		// Fail-safe: don't leave the settings UI hidden forever if the settings JS fails to initialise.
		document.addEventListener('DOMContentLoaded', function() {
			window.setTimeout(function() {
				var wrap = document.querySelector('.arlo-sections-wrap');
				if (wrap) {
					wrap.classList.remove('arlo-initializing');
				}
			}, 4000);
		});
		var apiHelpText = <?php echo wp_json_encode(__('API help text', 'arlo-training-and-event-management-system' )); ?>;
		var slugHelpText = <?php echo wp_json_encode(__('Slug help text', 'arlo-training-and-event-management-system' )); ?>;
		var cronHelpText = <?php echo wp_json_encode(__('Cron help text', 'arlo-training-and-event-management-system' )); ?>;
		var templateHelpText = <?php echo wp_json_encode(__('Template help text', 'arlo-training-and-event-management-system' )); ?>;
	</script>
</div>
