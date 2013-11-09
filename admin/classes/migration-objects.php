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
 * Class MigratePostCreator
 */
abstract class MigratePostCreator {

	/**
	 * @param array $post_data
	 *
	 * @return null|string New post ID
	 */
	public static function create_post ( $post_data ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		$user_id = get_current_user_id();
		$date = date( 'Y-m-d H:i:s' );

		$defaults = array(
			'post_author'       => $user_id,
			'post_date'         => "'$date'",
			'post_date_gmt'     => "'$date'",
			'post_status'       => "'publish'",
			'comment_status'    => "'closed'",
			'ping_status'       => "'closed'",
			'post_modified'     => "'$date'",
			'post_modified_gmt' => "'$date'",
			'post_parent'       => 0,
		);

		$post_data = array_merge( $defaults, $post_data );
		$keys = implode( ', ', array_keys( $post_data ) );
		$values = implode( ', ', $post_data );

		$wpdb->query( "INSERT INTO {$wpdb->posts} ($keys) VALUES ($values)" );
		return $wpdb->get_var( 'SELECT LAST_INSERT_ID();' );
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

		$members = $wpdb->get_results( "
			SELECT *
			FROM {$config->member_profiles}
			LIMIT 1000"
		);
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
 */
class MigrateForums extends MigratePostCreator {

	/**
	 * @param MigrateConfig $config
	 */
	public static function migrate ( $config ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		// Ikonboard categories become top-level forums (no parent)
		$categories = $wpdb->get_results( "SELECT * FROM {$config->categories}" );
		foreach ( $categories as $this_cat ) {
			$post_title = addslashes( $this_cat->CAT_NAME );
			$post_name = sanitize_title( $post_title );
			$menu_order = addslashes( $this_cat->CAT_POS );

			$post_data = array(
				'post_title' => "'$post_title'",
				'post_name'  => "'$post_name'",
				'menu_order' => $menu_order,
				'post_type'  => "'forum'"
			);
			$post_id = self::create_post( $post_data );

			// Save Ikonboard category ID as meta
			$ikon_cat_id = addslashes( $this_cat->CAT_ID );
			$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES ($post_id, 'IKON_CAT_ID', '$ikon_cat_id')" );
		}

		// Ikonboard Forums
		$forums = $wpdb->get_results( "SELECT * FROM {$config->forum_info}" );
		foreach ( $forums as $this_forum ) {
			$post_title = addslashes( $this_forum->FORUM_NAME );
			$post_name = sanitize_title( $post_title );
			$post_content = addslashes( $this_forum->FORUM_DESC );
			$menu_order = (int) addslashes( $this_forum->FORUM_POSITION );

			// Lookup the forum parent via the Ikonboard category id we stash in meta
			$post_parent = (int) $wpdb->get_var( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='IKON_CAT_ID' AND meta_value = '{$this_forum->CATEGORY}'" );

			$post_data = array(
				'post_content' => "'$post_content'",
				'post_title'   => "'$post_title'",
				'post_name'    => "'$post_name'",
				'post_parent'  => $post_parent,
				'menu_order'   => $menu_order,
				'post_type'    => "'forum'"
			);
			$post_id = self::create_post( $post_data );

			// Ikonboard forum meta
			$ikon_forum_id = addslashes( $this_forum->FORUM_ID );
			$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES ($post_id, 'IKON_FORUM_ID', '$ikon_forum_id')" );
		}

		// Set all the guids (faster to set all the guids at once than one at a time in the "big loop")
		$site_url = get_site_url();
		$params = '/?post_type=forum&#038;p=';
		$wpdb->query( "UPDATE {$wpdb->posts} SET guid = CONCAT('$site_url', '$params', ID) WHERE guid = '' AND post_type = 'forum'" );
	}
}

/**
 * Class MigrateTopics
 */
class MigrateTopics extends MigratePostCreator {

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
			LIMIT 50
		" );
		foreach ( $topics as $this_topic ) {
			$post_title = addslashes( $this_topic->TOPIC_TITLE );
			$post_name = sanitize_title( $post_title );
			$post_content = addslashes( $this_topic->POST );
			$post_author = (int) $wpdb->get_var( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='IKON_MEMBER_ID' AND meta_value = '{$this_topic->AUTHOR}'" );

			// insert topic post
			$post_data = array(
				'post_author'  => $post_author,
				'post_content' => "'$post_content'",
				'post_title'   => "'$post_title'",
				'post_name'    => "'$post_name'",
				'post_type'    => "'topic'"
			);
			$post_id = self::create_post( $post_data );

			// topic meta
			$ikon_topic_id = addslashes( $this_topic->TOPIC_ID );
			$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES ($post_id, 'IKON_TOPIC_ID', '$ikon_topic_id')" );
		}

		// Set all the guids (faster to set all the guids at once than one at a time in the "big loop")
		$site_url = get_site_url();
		$params = '/?post_type=topic&#038;p=';
		$wpdb->query( "UPDATE {$wpdb->posts} SET guid = CONCAT('$site_url', '$params', ID) WHERE guid = '' AND post_type = 'topic'" );
	}
}

/**
 * Class MigrateReplies
 */
class MigrateReplies extends MigratePostCreator {

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

		foreach ( $replies as $this_reply ) {

			// ToDo: insert reply post

			// ToDo: insert reply meta
		}
	}
}