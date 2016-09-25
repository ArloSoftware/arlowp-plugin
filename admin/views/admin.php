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
 * @link      http://arlo.co
 * @copyright 2015 Arlo
 */
?>

<div class="wrap arlo-wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	<div class="<?php echo ARLO_PLUGIN_PREFIX; ?>-sections-wrap">
		<form id="<?php echo ARLO_PLUGIN_PREFIX; ?>-settings" method="post" action="options.php">
			<h2 class="nav-tab-wrapper main-tab">
				<a href="<?=admin_url( 'admin.php?page=arlo-for-wordpress')?>#welcome" class="nav-tab" id="<?=$this->plugin_slug?>-tab-welcome">Welcome</a>
				<a href="<?=admin_url( 'admin.php?page=arlo-for-wordpress')?>#general" class="nav-tab" id="<?=$this->plugin_slug?>-tab-general">General</a>
				<a href="<?=admin_url( 'admin.php?page=arlo-for-wordpress')?>#pages" class="nav-tab" id="<?=$this->plugin_slug?>-tab-pages">Pages</a>
				<a href="<?=admin_url( 'admin.php?page=arlo-for-wordpress')?>#regions" class="nav-tab" id="<?=$this->plugin_slug?>-tab-regions">Regions</a>
				<a href="<?=admin_url( 'admin.php?page=arlo-for-wordpress')?>#customcss" class="nav-tab" id="<?=$this->plugin_slug?>-tab-customcss">Custom CSS</a>
				<a href="<?=admin_url( 'admin.php?page=arlo-for-wordpress')?>#misc" class="nav-tab" id="<?=$this->plugin_slug?>-tab-misc">Misc</a>
			</h2>		
			<?php settings_fields( 'arlo_settings' ); ?>
            <?php $this->do_settings_sections( $this->plugin_slug ); ?>
            <?php submit_button(); ?>
		</form>
	</div>
	<script type="text/javascript">
		var apiHelpText = "<?php _e('API help text', $this->plugin_slug); ?>";
		var slugHelpText = "<?php _e('Slug help text', $this->plugin_slug); ?>";
		var cronHelpText = "<?php _e('Cron help text', $this->plugin_slug); ?>";
		var templateHelpText = "<?php _e('Template help text', $this->plugin_slug); ?>";
	</script>
</div>
