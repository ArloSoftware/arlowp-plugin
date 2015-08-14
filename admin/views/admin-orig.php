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

<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	<div class="<?php echo PLUGIN_PREFIX; ?>-sections-wrap">
		<form id="<?php echo PLUGIN_PREFIX; ?>-settings" method="post" action="options.php">
			<!-- API Section -->
			<div class="<?php echo PLUGIN_PREFIX; ?>-section api">
				<button class="<?php echo PLUGIN_PREFIX; ?>-tooltip" data-tooltip="<?php _e('API help text', $this->plugin_slug); ?>">?</button>
				<h3 class="title"><?php _e('API Endpoint', $this->plugin_slug ); ?></h3>
				<div class="<?php echo PLUGIN_PREFIX; ?>-field-wrap">
					<div class="<?php echo PLUGIN_PREFIX; ?>-field">
						<input class="<?php echo PLUGIN_PREFIX; ?>-validate" type="url" value="" placeholder="<?php echo PLUGIN_NAME; ?> API endpoint URL" pattern="(^|\s)((https?:\/\/)?[\w-]+(\.[\w-]+)+\.?(:\d+)?(\/\S*)?)" />
						<small><?php _e('Please enter a valid URL',$this->plugin_slug); ?></small>
					</div>
				</div>
			</div>
			<!-- Slugs Section -->
			<div class="<?php echo PLUGIN_PREFIX; ?>-section slugs">
				<button class="<?php echo PLUGIN_PREFIX; ?>-tooltip" data-tooltip="<?php _e('Slugs help text', $this->plugin_slug); ?>">?</button>
				<h3 class="title"><?php _e('Slugs / Permalinks', $this->plugin_slug ); ?></h3>
				<!-- Events slug -->
				<div class="<?php echo PLUGIN_PREFIX; ?>-field-wrap cf">
					<div class="<?php echo PLUGIN_PREFIX; ?>-label">
						<label for="<?php echo PLUGIN_PREFIX; ?>EventsSlug"><?php _e('Events', $this->plugin_slug);?></label>
					</div>
					<div class="<?php echo PLUGIN_PREFIX; ?>-field">
						<input id="<?php echo PLUGIN_PREFIX; ?>EventsSlug" class="<?php echo PLUGIN_PREFIX; ?>-validate" name="<?php echo PLUGIN_PREFIX; ?>EventsSlug" type="text" value="" placeholder="Events Slug" required pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$" title="<?php _e('Can only contain numbers, lowercase letters and hyphens (-)',$this->plugin_slug); ?>" />
						<small><?php _e('Can only contain numbers, lowercase letters and hyphens (-)',$this->plugin_slug); ?></small>
					</div>
				</div>
				<!-- Upcoming Events slug -->
				<div class="<?php echo PLUGIN_PREFIX; ?>-field-wrap cf">
					<div class="<?php echo PLUGIN_PREFIX; ?>-label">
						<label for="<?php echo PLUGIN_PREFIX; ?>UpcomingEventsSlug"><?php _e('Upcoming Events', $this->plugin_slug);?></label>
					</div>
					<div class="<?php echo PLUGIN_PREFIX; ?>-field">
						<input id="<?php echo PLUGIN_PREFIX; ?>UpcomingEventsSlug" class="<?php echo PLUGIN_PREFIX; ?>-validate" name="<?php echo PLUGIN_PREFIX; ?>UpcomingEventSlug" type="text" value="" placeholder="Upcoming Events Slug" required pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$" title="<?php _e('Can only contain numbers, lowercase letters and hyphens (-)',$this->plugin_slug); ?>" />
						<small><?php _e('Can only contain numbers, lowercase letters and hyphens (-)',$this->plugin_slug); ?></small>
					</div>
				</div>
				<!-- Presenters slug -->
				<div class="<?php echo PLUGIN_PREFIX; ?>-field-wrap cf">
					<div class="<?php echo PLUGIN_PREFIX; ?>-label">
						<label for="<?php echo PLUGIN_PREFIX; ?>PresentersSlug"><?php _e('Presenters', $this->plugin_slug);?></label>
					</div>
					<div class="<?php echo PLUGIN_PREFIX; ?>-field">
						<input id="<?php echo PLUGIN_PREFIX; ?>PresentersSlug" class="<?php echo PLUGIN_PREFIX; ?>-validate" name="<?php echo PLUGIN_PREFIX; ?>PresentersSlug" type="text" value="" placeholder="Presenters Slug" required pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$" title="<?php _e('Can only contain numbers, lowercase letters and hyphens (-)',$this->plugin_slug); ?>" />
						<small><?php _e('Can only contain numbers, lowercase letters and hyphens (-)',$this->plugin_slug); ?></small>
					</div>
				</div>
				<!-- Locations slug -->
				<div class="<?php echo PLUGIN_PREFIX; ?>-field-wrap cf">
					<div class="<?php echo PLUGIN_PREFIX; ?>-label">
						<label for="<?php echo PLUGIN_PREFIX; ?>LocationsSlug"><?php _e('Locations', $this->plugin_slug);?></label>
					</div>
					<div class="<?php echo PLUGIN_PREFIX; ?>-field">
						<input id="<?php echo PLUGIN_PREFIX; ?>LocationsSlug" class="<?php echo PLUGIN_PREFIX; ?>-validate" name="<?php echo PLUGIN_PREFIX; ?>LocationsSlug" type="text" value="" placeholder="Locations Slug" required pattern="^[a-z0-9]+(?:-[a-z0-9]+)*$" title="<?php _e('Can only contain numbers, lowercase letters and hyphens (-)',$this->plugin_slug); ?>" />
						<small><?php _e('Can only contain numbers, lowercase letters and hyphens (-)',$this->plugin_slug); ?></small>
					</div>
				</div>
			</div>
			<!-- Cron Section -->
			<div class="<?php echo PLUGIN_PREFIX; ?>-section cron">
				<button class="<?php echo PLUGIN_PREFIX; ?>-tooltip" data-tooltip="<?php _e('Cron help text', $this->plugin_slug); ?>">?</button>
				<h3 class="title"><?php _e('Setup Cron', $this->plugin_slug ); ?></h3>
				<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
				<div class="<?php echo PLUGIN_PREFIX; ?>-field-wrap cf">
					<div class="<?php echo PLUGIN_PREFIX; ?>-check">
						<input id="<?php echo PLUGIN_PREFIX; ?>OwnCron" name="<?php echo PLUGIN_PREFIX; ?>OwnCron" type="checkbox" />
					</div>
					<div class="<?php echo PLUGIN_PREFIX; ?>-label">
						<label for="<?php echo PLUGIN_PREFIX; ?>OwnCron"><?php _e('I have setup my own cron jobs', $this->plugin_slug);?></label>
					</div>
				</div>
			</div>
			<!-- Templates Section -->
			<div class="<?php echo PLUGIN_PREFIX; ?>-section templates">
				<button class="<?php echo PLUGIN_PREFIX; ?>-tooltip" data-tooltip="<?php _e('Templates help text', $this->plugin_slug); ?>">?</button>
				<h3 class="title"><?php _e('Templates', $this->plugin_slug ); ?></h3><div class="<?php echo PLUGIN_PREFIX; ?>-field-wrap">
					<div class="<?php echo PLUGIN_PREFIX; ?>-select">
						<button class="<?php echo PLUGIN_PREFIX; ?>-preview button action"><?php _e('Preview', $this->plugin_slug); ?></button>
						<select id="<?php echo PLUGIN_PREFIX.'-template-select'; ?>" name="<?php echo PLUGIN_PREFIX.'TemplateSelect'; ?>">
							<option value="<?php echo PLUGIN_PREFIX; ?>_events_template_editor"><?php _e('Events Template', $this->plugin_slug); ?></option>
							<option value="<?php echo PLUGIN_PREFIX; ?>_presenters_template_editor"><?php _e('Presenters Template', $this->plugin_slug); ?></option>
							<option value="<?php echo PLUGIN_PREFIX; ?>_locations_template_editor"><?php _e('Locations Template', $this->plugin_slug); ?></option>
						</select>
					</div>
				</div>
				<div class="<?php echo PLUGIN_PREFIX; ?>-template-editor-wrap">
					<div class="<?php echo PLUGIN_PREFIX; ?>-editor <?php echo PLUGIN_PREFIX; ?>_events_template_editor">
						<?php wp_editor('Events', PLUGIN_PREFIX.'_events_template_editor',array('textarea_rows'=>'20')); ?>
					</div>
					<div class="<?php echo PLUGIN_PREFIX; ?>-editor <?php echo PLUGIN_PREFIX; ?>_presenters_template_editor">
						<?php wp_editor('Presenters', PLUGIN_PREFIX.'_presenters_template_editor',array('textarea_rows'=>'20')); ?>
					</div>
					<div class="<?php echo PLUGIN_PREFIX; ?>-editor <?php echo PLUGIN_PREFIX; ?>_locations_template_editor">
						<?php wp_editor('Locations', PLUGIN_PREFIX.'_locations_template_editor',array('textarea_rows'=>'20')); ?>
					</div>
				</div>
				<div class="<?php echo PLUGIN_PREFIX; ?>-field-wrap cf">
					<div class="<?php echo PLUGIN_PREFIX; ?>-submit">
						<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php _e('Save Changes', $this->plugin_slug); ?>" />
					</div>
				</div>
			</div>
		</form>
	</div>

	<!-- @TODO: Provide markup for your options page here. -->

</div>
