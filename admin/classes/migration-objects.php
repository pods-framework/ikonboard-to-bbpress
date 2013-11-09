<?php

/**
 * Class MigrateConfig
 */
class MigrateConfig {

	public $member_profiles;

	public $categories;

	public $forum_info;

	public $forum_topics;

	public $forum_posts;

	/**
	 * @param string|null $prefix
	 * @param array $table_names
	 */
	public function __construct ( $prefix = null, $table_names = array() ) {
		$defaults = array(
			'member_profiles' => 'member_profiles',
			'categories'      => 'categories',
			'forum_info'      => 'forum_info',
			'forum_topics'    => 'forum_topics',
			'forum_posts'     => 'forum_posts'
		);
		$table_names = array_merge( $defaults, $table_names );

		foreach ( $table_names as $member_name => $name ) {
			if ( !empty( $prefix ) ) {
				$name = $prefix . $name;
			}
			$this->$member_name = $name;
		}
	}
}

/**
 * Class MigrateUsers
 */
class MigrateUsers {

	/**
	 * @param MigrateConfig $config
	 */
	public static function migrate ( $config ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		$members = $wpdb->get_results( "SELECT * FROM {$config->member_profiles} LIMIT 1000" );
		foreach ( $members as $this_member ) {

			// wp_users insert
			$name = addslashes( $this_member->MEMBER_NAME );
			$email = addslashes( $this_member->MEMBER_EMAIL );
			$wpdb->query( "
				INSERT INTO {$wpdb->users}
					(user_login, user_nicename, display_name, user_email)
				VALUES
					('$name', '$name', '$name', '$email');
			" );

			// Get the new user ID
			$user_id = $wpdb->get_var( 'SELECT LAST_INSERT_ID();' );

			// wp_usermeta insert
			$member_id = addslashes( $this_member->MEMBER_ID );
			$wpdb->query( "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) VALUES ($user_id, 'IKON_MEMBER_ID', '$member_id')" );
		}
	}
}

/**
 * Class MigrateForums
 *
 * Straight insert: 23.58 seconds
 * wp_insert_post: 50.98 seconds
 *
 */
class MigrateForums {

	/**
	 * @param MigrateConfig $config
	 */
	public static function migrate ( $config ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		$user_id = get_current_user_id();
		$date = date( 'Y-m-d H:i:s' );

		// Ikonboard categories become top-level forums (no parent)
		$categories = $wpdb->get_results( "SELECT * FROM {$config->categories}" );
		foreach ( $categories as $this_cat ) {
			$post_title = stripslashes( $this_cat->CAT_NAME );
			$post_name = sanitize_title( $post_title );
			$menu_order = stripslashes( $this_cat->CAT_POS );
			$category_id = stripslashes( $this_cat->CAT_ID );

			$post_data = array(
				'post_author'       => $user_id,
				'post_date'         => "'$date'",
				'post_date_gmt'     => "'$date'",
				'post_content'      => "''",
				'post_title'        => "'$post_title'",
				'post_status'       => "'publish'",
				'comment_status'    => "'closed'",
				'ping_status'       => "'closed'",
				'post_name'         => "'$post_name'",
				'post_modified'     => "'$date'",
				'post_modified_gmt' => "'$date'",
				'post_parent'       => 0,
				'menu_order'        => $menu_order,
				'post_type'         => "'forum'"
			);
			$keys = implode( ', ', array_keys( $post_data ) );
			$values = implode( ', ', $post_data );

			$wpdb->query( "INSERT INTO {$wpdb->posts} ($keys) VALUES ($values)" );

			// Get the new post id
			$post_id = $wpdb->get_var( 'SELECT LAST_INSERT_ID();' );

			// Insert meta for the category
			$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES ($post_id, 'IKON_CAT_ID', '$category_id')" );
		}

		// Ikonboard Forums
		$forums = $wpdb->get_results( "SELECT * FROM {$config->forum_info}" );
		foreach ( $forums as $this_forum ) {
			$post_title = stripslashes( $this_forum->FORUM_NAME );
			$post_name = sanitize_title( $post_title );
			$post_content = stripslashes( $this_forum->FORUM_DESC );
			$menu_order = (int)stripslashes( $this_forum->FORUM_POSITION );
			$post_parent = (int) $wpdb->get_var( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='IKON_CAT_ID' AND meta_value = '{$this_forum->CATEGORY}'" );

			$post_data = array(
				'post_author'       => $user_id,
				'post_date'         => "'$date'",
				'post_date_gmt'     => "'$date'",
				'post_content'      => "'$post_content'",
				'post_title'        => "'$post_title'",
				'post_status'       => "'publish'",
				'comment_status'    => "'closed'",
				'ping_status'       => "'closed'",
				'post_name'         => "'$post_name'",
				'post_modified'     => "'$date'",
				'post_modified_gmt' => "'$date'",
				'post_parent'       => $post_parent,
				'menu_order'        => $menu_order,
				'post_type'         => "'forum'"
			);
			$keys = implode( ', ', array_keys( $post_data ) );
			$values = implode( ', ', $post_data );

			$wpdb->query( "INSERT INTO {$wpdb->posts} ($keys) VALUES ($values)" );

			// Get the new post id
			$post_id = $wpdb->get_var( 'SELECT LAST_INSERT_ID();' );
		}

		// Set all the guids (faster to set all the guids at once than one at a time in the "big loop")
		$site_url = get_site_url();
		$params = '/?post_type=forum&#038;p=';
		$wpdb->query( "UPDATE {$wpdb->posts} SET guid = CONCAT('$site_url', '$params', ID) WHERE guid = '' AND post_type = 'forum'" );

		// ToDo: forum meta
	}
}

/**
 * Class MigrateTopics
 */
class MigrateTopics {

	/**
	 * @param MigrateConfig $config
	 */
	public static function migrate ( $config ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		$topics = $wpdb->get_results( "
			SELECT
				*
			FROM
				{$config->forum_topics} AS t
				LEFT JOIN {$config->forum_posts} AS p
					ON p.TOPIC_ID = t.TOPIC_ID
					AND p.POST_DATE = ( SELECT MIN(POST_DATE) FROM {$config->forum_posts} AS ptemp WHERE ptemp.TOPIC_ID = t.TOPIC_ID )
		" );
		/*
		foreach($topics as $this_topic) {
			echo $this_topic->TOPIC_TITLE . ' ' . $this_topic->POST . '<hr />';

			// insert topic post

			// insert topic meta
		}
		*/
	}
}

/**
 * Class MigrateReplies
 */
class MigrateReplies {

	/**
	 * @param MigrateConfig $config
	 */
	public static function migrate ( $config ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		$replies = $wpdb->get_results( "
			SELECT
				*
			FROM
				{$config->forum_topics} AS t
				LEFT JOIN {$config->forum_posts} AS p
					ON p.TOPIC_ID = t.TOPIC_ID
					AND p.POST_DATE != ( SELECT MIN(POST_DATE) FROM $config->forum_posts} AS ptemp WHERE ptemp.TOPIC_ID = t.TOPIC_ID )
		" );

		/*
		foreach($replies as $this_reply) {

			// insert reply post

			// insert reply meta
		}
		*/

	}
}