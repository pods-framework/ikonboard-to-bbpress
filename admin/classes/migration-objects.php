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

	public $pods_wizard;

	/**
	 * @param string|null $prefix
	 * @param array $table_names
	 */
	public function __construct ( $prefix = null, $table_names = array(), $pods_wizard = null ) {
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

		if ( is_object( $pods_wizard ) ) {
			$this->pods_wizard =& $pods_wizard;
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

		debug_out( 'Querying all members...' );
		// Get all member records from Ikonboard
		$members = $wpdb->get_results( "
			SELECT *
			FROM {$config->member_profiles}
		" );
		$records_selected = count( $members );
		debug_out( "Query returned $records_selected rows" );

		// Build a series of values strings for the insert
		$values = '';
		foreach ( $members as $this_member ) {
			$name = addslashes( $this_member->MEMBER_NAME );
			$email = addslashes( $this_member->MEMBER_EMAIL );
			$user_registered = date( 'Y-m-d H:i:s', (int) $this_member->MEMBER_JOINED );

			// Just doing it this way because it's easier to maintain the key/value list.
			// The keys are only used once, after the last iteration of the loop
			$user_data = array(
				'user_login'      => "'$name'",
				'user_nicename'   => "'$name'",
				'display_name'    => "'$name'",
				'user_email'      => "'$email'",
				'user_registered' => "'$user_registered'"
			);
			$values .= '(' . implode( ', ', $user_data ) . '),';
		}

		// Pull off the last trailing comma and run the full insert
		$values = trim( $values, ',' );
		$keys = implode( ', ', array_keys( $user_data ) );
		$wpdb->query( "INSERT INTO {$wpdb->users} ($keys) VALUES $values" );

		// Statement order is significant here: ROW_COUNT() applies ONLY to the last sql statement, even a SELECT
		// LAST_INSERT_ID() applies to the most recently executed INSERT (returns the FIRST auto-generated ID if bulk)
		$added_rows = $wpdb->get_var( 'SELECT ROW_COUNT();' );
		$first_new_id = $wpdb->get_var( 'SELECT LAST_INSERT_ID();' );

		debug_out( "Members: $records_selected : $added_rows" );

		if ( $added_rows != $records_selected ) {
			// ToDo: decide what to do here if the insert didn't add all rows.  We can't do the loop to add
			// meta correctly if we didn't insert all records.
		}

		// Do it again for meta
		$values = '';
		$user_id = $first_new_id;
		foreach ( $members as $this_member ) {
			$member_id = addslashes( $this_member->MEMBER_ID );
			$values .= "($user_id, 'IKON_MEMBER_ID_$member_id', '$member_id'),";
			$user_id++;
		}
		$values = trim( $values, ',' );
		$wpdb->query( "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) VALUES $values" );

		return $added_rows;
	}
}

/**
 * Class MigrateForums
 */
class MigrateForums {

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
			$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES ($post_id, 'IKON_CAT_ID_$ikon_cat_id', '$ikon_cat_id')" );
		}

		// Ikonboard Forums
		$forums = $wpdb->get_results( "SELECT * FROM {$config->forum_info}" );
		foreach ( $forums as $this_forum ) {
			$post_title = addslashes( $this_forum->FORUM_NAME );
			$post_name = sanitize_title( $post_title );
			$post_content = addslashes( $this_forum->FORUM_DESC );
			$menu_order = (int) addslashes( $this_forum->FORUM_POSITION );

			// Lookup the forum parent via the Ikonboard category id we stash in meta
			$post_parent = (int) $wpdb->get_var( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='IKON_CAT_ID_{$this_forum->CATEGORY}'" );

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
			$ikon_forum_id = (string) addslashes( $this_forum->FORUM_ID );
			$wpdb->query( "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES ($post_id, 'IKON_FORUM_ID_$ikon_forum_id', '$ikon_forum_id')" );
		}

		// Set all the guids (faster to set all the guids at once than one at a time in the "big loop")
		$site_url = get_site_url();
		$params = '/?post_type=forum&#038;p=';
		$wpdb->query( "UPDATE {$wpdb->posts} SET guid = CONCAT('$site_url', '$params', ID) WHERE guid = '' AND post_type = 'forum'" );
	}

	/**
	 * @param array $post_data
	 *
	 * @return null|string New post ID
	 */
	private static function create_post ( $post_data ) {
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
 * Class MigrateTopics
 */
class MigrateTopics {

	const ROWS_TO_BUFFER = 30000;

	private static $first_new_id = null;

	/**
	 * @param MigrateConfig $config
	 */
	public static function migrate ( $config ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		debug_out( "Querying all topics..." );

		// Topics are separate in Ikonboard.  Join the topic with the oldest post in the topic to get the bbPress topic
		$topics = $wpdb->get_results( "
			SELECT
				*
			FROM
				{$config->forum_topics} AS t
				LEFT JOIN {$config->forum_posts} AS p
					ON p.TOPIC_ID = t.TOPIC_ID
					AND p.POST_DATE = ( SELECT MIN(POST_DATE) FROM {$config->forum_posts} AS ptemp WHERE ptemp.TOPIC_ID = t.TOPIC_ID )
		" );
		$records_selected = count( $topics );

		debug_out( "Query returned $records_selected rows.<br />" );
		debug_out( "Processing topic posts..." );

		$keys = '';
		$values = '';
		$row = 0;
		foreach ( $topics as $this_topic ) {
			$date = date( 'Y-m-d H:i:s', (int) $this_topic->POST_DATE );
			$post_title = (string) addslashes( $this_topic->TOPIC_TITLE );
			$post_name = (string) sanitize_title( $post_title ); // Must be unique, we'll tack on post id later, when it's known
			$post_content = (string) addslashes( $this_topic->POST );
			$post_author = (int) $wpdb->get_var( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='IKON_MEMBER_ID_{$this_topic->AUTHOR}'" );
			$post_parent = (int) $wpdb->get_var( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='IKON_FORUM_ID_{$this_topic->FORUM_ID}'" );

			// Just doing it this way because it's easier to maintain the key/value list. The keys are only copied once.
			$post_data = array(
				'post_author'       => $post_author,
				'post_date'         => "'$date'",
				'post_date_gmt'     => "'$date'",
				'post_content'      => "'$post_content'",
				'post_title'        => "'$post_title'",
				'post_name'         => "'$post_name'",
				'post_status'       => "'publish'",
				'comment_status'    => "'closed'",
				'ping_status'       => "'closed'",
				'post_modified'     => "'$date'",
				'post_modified_gmt' => "'$date'",
				'post_parent'       => $post_parent,
				'post_type'         => "'topic'"
			);
			if ( '' == $keys ) {
				$keys = implode( ', ', array_keys( $post_data ) );
			}
			$values .= '(' . implode( ', ', $post_data ) . '),';
			$row++;

			if ( 0 == $row % 1000 ) {
				debug_out( "Buffering topic post $row" );
			}

			if ( 0 == $row % self::ROWS_TO_BUFFER ) {
				// Write everything out to this point and clear the values string buffer
				self::insert_posts( $keys, $values );
				$values = '';
			}
		}
		// Insert the remainder in the buffer
		self::insert_posts( $keys, $values );

		debug_out( 'Processing topic meta...' );

		// topic meta
		$values = '';
		$post_id = self::$first_new_id;
		$row = 0;
		debug_out( "First new topic ID = $post_id" );
		foreach ( $topics as $this_topic ) {
			$ikon_topic_id = (string) addslashes( $this_topic->TOPIC_ID );
			$author_ip = (string) addslashes( $this_topic->IP_ADDR );
			$bbp_forum_id = $wpdb->get_var( "SELECT post_parent FROM $wpdb->posts WHERE ID = $post_id" );

			$values .= "($post_id, 'IKON_TOPIC_ID_$ikon_topic_id', '$ikon_topic_id'),";
			$values .= "($post_id, '_bbp_forum_id', '$bbp_forum_id'),";
			$values .= "($post_id, '_bbp_topic_id', '$post_id'),";
			$values .= "($post_id, '_bbp_author_ip', '$author_ip'),";
			$row++;

			if ( 0 == $row % 1000 ) {
				debug_out( "Buffering topic meta $row" );
			}

			if ( 0 == $row % self::ROWS_TO_BUFFER ) {
				// Write everything out to this point and clear the values string buffer
				self::insert_postmeta( 'post_id, meta_key, meta_value', $values );
				$values = '';
			}

			$post_id++;
		}
		// Insert the remainder in the buffer
		self::insert_postmeta( 'post_id, meta_key, meta_value', $values );

		// Ensure unique page_name
		debug_out( 'Updating all topic post slugs...' );
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_name = CONCAT(post_name, '-', ID) WHERE post_type = 'topic'" );
		debug_out( 'Post slugs updated.' );

		// Set all the guids for the new posts
		debug_out( 'Updating all topic guids...' );
		$site_url = get_site_url();
		$params = '/?post_type=topic&#038;p=';
		$wpdb->query( "UPDATE {$wpdb->posts} SET guid = CONCAT('$site_url', '$params', ID) WHERE guid = '' AND post_type = 'topic'" );
		debug_out( 'Guids updated.' );
	}

	/**
	 * @param string $keys
	 * @param string $values
	 */
	private static function insert_posts ( $keys, $values ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		debug_out( 'Inserting into wp_posts' );
		$values = rtrim( $values, ',' );
		$wpdb->query( "INSERT INTO {$wpdb->posts} ($keys) VALUES $values" );
		debug_out( 'Insert done' );

		if ( null === self::$first_new_id ) {
			self::$first_new_id = $wpdb->get_var( 'SELECT LAST_INSERT_ID();' );
		}
	}

	/**
	 * @param string $keys
	 * @param string $values
	 */
	private static function insert_postmeta ( $keys, $values ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		$values = rtrim( $values, ',' );
		debug_out( 'Inserting into wp_postmeta' );
		$wpdb->query( "INSERT INTO {$wpdb->postmeta} ($keys) VALUES $values" );
		debug_out( 'Insert done' );
	}
}

/**
 * Class MigrateReplies
 */
class MigrateReplies {

	const ROWS_TO_BUFFER = 30000;

	private static $first_new_id = null;

	/**
	 * @param MigrateConfig $config
	 */
	public static function migrate ( $config ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		$date = date( 'Y-m-d H:i:s' );

		debug_out( "Querying all replies..." );

		// Ignore the oldest post in Ikonboard, that one was used for the topic in bbPress
		$replies = $wpdb->get_results( "
			SELECT
				*
			FROM
				{$config->forum_topics} AS t
				LEFT JOIN {$config->forum_posts} AS p
					ON p.TOPIC_ID = t.TOPIC_ID
					AND p.POST_DATE != ( SELECT MIN(POST_DATE) FROM {$config->forum_posts} AS ptemp WHERE ptemp.TOPIC_ID = t.TOPIC_ID )
			LIMIT 50000
		" ); // ToDo: Don't leave the limit on there
		$records_selected = count( $replies );

		debug_out( "Query returned $records_selected rows.<br />" );
		debug_out( "Processing reply posts..." );

		$keys = '';
		$values = '';
		$row = 0;
		foreach ( $replies as $this_reply ) {
			$date = date( 'Y-m-d H:i:s', (int) $this_reply->POST_DATE );
			$post_title = (string) addslashes( $this_reply->TOPIC_TITLE );
			$post_name = (string) sanitize_title( $post_title ); // Must be unique, we'll tack on post id later, when it's known
			$post_content = (string) addslashes( $this_reply->POST );
			$post_author = (int) $wpdb->get_var( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key='IKON_MEMBER_ID_{$this_reply->AUTHOR}'" );
			$post_parent = (int) $wpdb->get_var( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='IKON_TOPIC_ID_{$this_reply->TOPIC_ID}'" );

			// Just doing it this way because it's easier to maintain the key/value list. The keys are only copied once.
			$post_data = array(
				'post_author'       => $post_author,
				'post_date'         => "'$date'",
				'post_date_gmt'     => "'$date'",
				'post_content'      => "'$post_content'",
				'post_title'        => "'$post_title'",
				'post_name'         => "'$post_name'",
				'post_status'       => "'publish'",
				'comment_status'    => "'closed'",
				'ping_status'       => "'closed'",
				'post_modified'     => "'$date'",
				'post_modified_gmt' => "'$date'",
				'post_parent'       => $post_parent,
				'post_type'         => "'reply'"
			);
			if ( '' == $keys ) {
				$keys = implode( ', ', array_keys( $post_data ) );
			}
			$values .= '(' . implode( ', ', $post_data ) . '),';
			$row++;

			if ( 0 == $row % 1000 ) {
				debug_out( "Buffering reply post $row" );
			}

			if ( 0 == $row % self::ROWS_TO_BUFFER ) {
				// Write everything out to this point and clear the values string buffer
				self::insert_posts( $keys, $values );
				$values = '';
			}
		}
		// Insert the remainder in the buffer
		self::insert_posts( $keys, $values );

		debug_out( 'Processing reply meta...' );

		// reply meta
		$values = '';
		$post_id = self::$first_new_id;
		$row = 0;
		debug_out( "First new reply ID = $post_id" );
		foreach ( $replies as $this_reply ) {
			$ikon_post_id = (string) addslashes( $this_reply->POST_ID );
			$author_ip = (string) addslashes( $this_reply->IP_ADDR );
			$bbp_topic_id = (int) $wpdb->get_var( "SELECT post_parent FROM $wpdb->posts WHERE ID = $post_id" );
			$bbp_forum_id = (int) $wpdb->get_var( "SELECT post_parent FROM $wpdb->posts WHERE ID = $bbp_topic_id" );

			$values .= "($post_id, 'IKON_POST_ID_$ikon_post_id', '$ikon_post_id'),";
			$values .= "($post_id, '_bbp_forum_id', '$bbp_forum_id'),";
			$values .= "($post_id, '_bbp_topic_id', '$bbp_topic_id'),";
			$values .= "($post_id, '_bbp_author_ip', '$author_ip'),";
			$row++;

			if ( 0 == $row % 1000 ) {
				debug_out( "Buffering reply meta $row" );
			}

			if ( 0 == $row % self::ROWS_TO_BUFFER ) {
				// Write everything out to this point and clear the values string buffer
				self::insert_postmeta( 'post_id, meta_key, meta_value', $values );
				$values = '';
			}

			$post_id++;
		}
		// Insert the remainder in the buffer
		self::insert_postmeta( 'post_id, meta_key, meta_value', $values );

		// Ensure unique page_name
		debug_out( 'Updating all reply post slugs...' );
		$wpdb->query( "UPDATE {$wpdb->posts} SET post_name = CONCAT(post_name, '-', ID) WHERE post_type = 'reply'" );
		debug_out( 'Post slugs updated.' );

		// Set all the guids for the new posts
		debug_out( 'Updating all reply guids...' );
		$site_url = get_site_url();
		$params = '/?post_type=reply&#038;p=';
		$wpdb->query( "UPDATE {$wpdb->posts} SET guid = CONCAT('$site_url', '$params', ID) WHERE guid = '' AND post_type = 'reply'" );
		debug_out( 'Guids updated.' );
	}

	/**
	 * @param string $keys
	 * @param string $values
	 */
	private static function insert_posts ( $keys, $values ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		debug_out( 'Inserting into wp_posts' );
		$values = rtrim( $values, ',' );
		$wpdb->query( "INSERT INTO {$wpdb->posts} ($keys) VALUES $values" );
		debug_out( 'Insert done' );

		if ( null === self::$first_new_id ) {
			self::$first_new_id = $wpdb->get_var( 'SELECT LAST_INSERT_ID();' );
		}
	}

	/**
	 * @param string $keys
	 * @param string $values
	 */
	private static function insert_postmeta ( $keys, $values ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		$values = rtrim( $values, ',' );
		debug_out( 'Inserting into wp_postmeta' );
		$wpdb->query( "INSERT INTO {$wpdb->postmeta} ($keys) VALUES $values" );
		debug_out( 'Insert done' );
	}

}