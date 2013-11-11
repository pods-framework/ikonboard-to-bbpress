<?php
time_elapsed(); // Start timer
?>
<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php echo esc_html( get_admin_page_title() ); ?>: Migrate</h2>
	<?php
		// Setup config for our environment (table names and prefix)
		$config = new MigrateConfig( 'bp_' );

		//MigrateUsers::migrate( $config );
		//time_elapsed('Migrate users');

		//MigrateForums::migrate( $config );
		//time_elapsed('Migrate forums');

		//MigrateTopics::migrate( $config );
		//time_elapsed('Migrate topics');

		//MigrateReplies::migrate( $config );
		//time_elapsed('Migrate replies');
	?>
</div>
