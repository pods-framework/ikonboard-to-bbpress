<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * Backpacker Ikonboard -> bbPress migration
 *
 * @package   bp-bbpress-migration
 * @author    Scott Kingsley Clark <lol@scottkclark.com>, Phil Lewis
 * @license   GPL-2.0+
 * @copyright 2013 Scott Kingsley Clark, Phil Lewis
 */
?>

<div class="wrap">

	<?php screen_icon(); ?>
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<h3><?php _e( 'Do it', 'bp-bbpress-migration' ); ?></h3>

	<a href="<?php echo add_query_arg( array( 'action' => 'migrate' ) ); ?>" class="button button-primary" style="padding: 0 20px; text-align: center;"><?php _e( 'Migrate', 'bp-bbpress-migration' ); ?></a>

</div>
