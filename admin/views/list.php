<?php
/**
 * Represents the view for the venues list page.
 *
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      http://arlo.co
 * @copyright 2016 Arlo
 */ 
?>

<div class="wrap arlo-wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	<div class="<?php echo ARLO_PLUGIN_PREFIX; ?>-sections-wrap">	
<?
	$list->prepare_items();
	$list->display();	
?>
	</div>
</div>
