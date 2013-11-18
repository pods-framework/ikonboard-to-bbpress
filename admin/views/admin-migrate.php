<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php echo esc_html( get_admin_page_title() ); ?>: Migrate</h2>
	<?php

	time_elapsed(); // Start timer
	$config = new MigrateConfig( 'bp_' ); // Setup config for our environment (table names and prefix)

	/** @global wpdb $wpdb */
	global $wpdb;

	MigrateTempTables::migrate( $config );

	MigrateUsers::migrate( $config );
	time_elapsed( 'Migrate users completed' );

	MigrateForumCategories::migrate( $config );
	time_elapsed( 'Migrate forum categories completed' );

	MigrateForums::migrate( $config );
	time_elapsed( 'Migrate forums completed' );

	MigrateTopics::migrate( $config );
	time_elapsed( 'Migrate topics' );

	MigrateReplies::migrate( $config );
	time_elapsed( 'Migrate replies' );
	?>
</div>
