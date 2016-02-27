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
	<h2><?php echo esc_html( $list->get_title() ); ?></h2>
	<div class="<?php echo ARLO_PLUGIN_PREFIX; ?>-sections-wrap">	
		<form action="" method="get" >
			<input type="hidden" name="page" value="<?=$_GET['page']?>">
<?
		$list->search_box( __( 'Search' ), 'arlo-search' );
		$list->display();	
?>
		</form>
	</div>
</div>
