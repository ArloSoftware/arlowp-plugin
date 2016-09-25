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

<div class="wrap arlo-wrap arlo-list">
	<h2><?php echo esc_html( $list->get_title() ); ?></h2>
	<?php if ($list::TABLENAME !== 'arlo_log') { ?>
	<a href="<?=$list->get_new_link()?>" target="_blank" class="button button-primary">New <?=strtolower($list->singular)?></a>
	&nbsp;&nbsp;&nbsp;
	<a href="<?=$list->get_list_link()?>" target="_blank" class="arlo-middle">Manage <?=strtolower($list->plural)?> in Arlo</a>
	<?php } ?>
	<div class="<?php echo ARLO_PLUGIN_PREFIX; ?>-sections-wrap <?php echo $list::TABLENAME ?>">	
		<form action="" method="get" >
			<input type="hidden" name="page" value="<?=$_GET['page']?>">
<?php
		$list->search_box( __( 'Search' ), 'arlo-search' );
		$list->display();	
?>
		</form>
	</div>
</div>
