<?php
/**
 * Represents the view for the venues list page.
 *
 *
 * @package   Arlo_For_Wordpress_Admin
 * @author    Arlo <info@arlo.co>
 * @license   GPL-2.0+
 * @link      https://arlo.co
 * @copyright 2018 Arlo
 */ 
//[x]: checked escaped html
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
} 
?>

<div class="wrap arlo-wrap arlo-list">
	<h2><?php echo esc_html( $list->get_title() ); ?></h2>
	<?php if ($list::TABLENAME !== 'arlo_log') { ?>
	<a href="<?php echo esc_url($list->get_new_link())?>" target="_blank" class="button button-primary">New <?php echo esc_html(strtolower($list->singular))?></a>
	&nbsp;&nbsp;&nbsp;
	<a href="<?php echo esc_url($list->get_list_link())?>" target="_blank" class="arlo-middle">Manage <?php echo esc_html(strtolower($list->plural))?> in Arlo</a>
	<?php } ?>
	<div class="<?php echo esc_attr(ARLO_PLUGIN_PREFIX. '-sections-wrap '. $list::TABLENAME);?>">	
		<form action="" method="get" >
			<?php $arlo_page_get = \ArloTraining\Utilities::filter_string_polyfill(INPUT_GET, 'page'); ?>
			<input type="hidden" name="page" value="<?php echo esc_attr($arlo_page_get)?>">
<?php
		$list->search_box( esc_html__( 'Search', 'arlo-training-and-event-management-system' ), 'arlo-search' );
		$list->display();	
?>
		</form>
	</div>
</div>
