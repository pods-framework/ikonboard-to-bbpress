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

	public $temp_topics;

	public $temp_replies;

	public $pods_wizard;

	public $page = 1;

	public $limit = 0;

	public $offset = 0;

	/**
	 * @param string|null $prefix
	 * @param array $table_names
	 * @param null $pods_wizard
	 */
	public function __construct ( $prefix = null, $table_names = array(), $pods_wizard = null ) {
		$defaults = array(
			'member_profiles' => 'member_profiles',
			'categories'      => 'categories',
			'forum_info'      => 'forum_info',
			'forum_topics'    => 'forum_topics',
			'forum_posts'     => 'forum_posts',
			'temp_topics'     => 'temp_topics',
			'temp_replies'    => 'temp_replies'
		);
		$table_names = array_merge( $defaults, $table_names );

		foreach ( $table_names as $member_name => $name ) {
			if ( !empty( $prefix ) ) {
				$name = $prefix . $name;
			}

			$this->{$member_name} = $name;
		}

		if ( is_object( $pods_wizard ) ) {
			$this->pods_wizard =& $pods_wizard;
		}

		if ( 0 < $this->limit ) {
			if ( empty( $this->offset ) && 1 < $this->page ) {
				$this->offset = ( $this->limit * ( $this->page - 1 ) );
			}
		}
	}
}

/**
 * Class MigragrateTempTables
 */
class MigrateTempTables {

	/**
	 * @param MigrateConfig $config
	 *
	 * @return null|string
	 */
	public static function migrate ( $config ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		debug_out( 'Importing all posts to a temp table...' );
		$wpdb->query( "
			CREATE TABLE IF NOT EXISTS `{$config->temp_replies}` (
				`FORUM_ID` bigint(10) DEFAULT NULL,
				`TOPIC_ID` bigint(10) unsigned NOT NULL DEFAULT '0',
				`TOPIC_TITLE` varchar(70) NOT NULL DEFAULT '',
				`POST_ID` bigint(10) unsigned DEFAULT '0',
				`AUTHOR` varchar(32) DEFAULT NULL,
				`IP_ADDR` varchar(16) DEFAULT NULL,
				`POST_DATE` int(10) NOT NULL DEFAULT '0',
				`POST` text
			) ENGINE=InnoDB DEFAULT CHARSET=latin1;
		" );

		$wpdb->query( "
			INSERT INTO `{$config->temp_replies}`
				(`FORUM_ID`, `TOPIC_ID`, `TOPIC_TITLE`, `POST_ID`, `AUTHOR`, `IP_ADDR`, `POST_DATE`, `POST`)
			SELECT
				`t`.`FORUM_ID`, `t`.`TOPIC_ID`, `TOPIC_TITLE`, `POST_ID`, `AUTHOR`, `IP_ADDR`, `POST_DATE`, `POST`
			FROM
				`{$config->forum_topics}` AS `t` LEFT JOIN `{$config->forum_posts}` AS `p` ON `p`.`TOPIC_ID` = `t`.`TOPIC_ID`
		" );
		time_elapsed( 'All posts copied' );

		debug_out( "Indexing posts..." );
		$wpdb->query( "
			ALTER TABLE `{$config->temp_replies}`
			ADD INDEX `topic_id`  (`TOPIC_ID` ASC),
			ADD INDEX `post_id`   (`POST_ID` ASC),
			ADD INDEX `post_date` (`POST_DATE` ASC)
		" );
		time_elapsed( 'Indexes created' );

		debug_out( 'Importing all topics to a temp table...' );
		$wpdb->query( "
			CREATE TABLE IF NOT EXISTS `{$config->temp_topics}` (
				`FORUM_ID` bigint(10) DEFAULT NULL,
				`TOPIC_ID` bigint(10) unsigned NOT NULL DEFAULT '0',
				`TOPIC_TITLE` varchar(70) NOT NULL DEFAULT '',
				`POST_ID` bigint(10) unsigned DEFAULT '0',
				`AUTHOR` varchar(32) DEFAULT NULL,
				`IP_ADDR` varchar(16) DEFAULT NULL,
				`POST_DATE` int(10) NOT NULL DEFAULT '0',
				`POST` text
			) ENGINE=InnoDB DEFAULT CHARSET=latin1;
		" );
		$wpdb->query( "
			INSERT INTO `{$config->temp_topics}`
				(`FORUM_ID`, `TOPIC_ID`, `TOPIC_TITLE`, `POST_ID`, `AUTHOR`, `IP_ADDR`, `POST_DATE`, `POST`)
			SELECT
			   `FORUM_ID`, `TOPIC_ID`, `TOPIC_TITLE`, `POST_ID`, `AUTHOR`, `IP_ADDR`, `POST_DATE`, `POST`
			FROM
				`{$config->temp_replies}`
			GROUP BY `TOPIC_ID`
			HAVING MIN(`POST_DATE`)
		" );
		time_elapsed( 'All topics copied' );

		debug_out( 'Indexing topics...' );
		$wpdb->query( "
			ALTER TABLE `{$config->temp_topics}`
			ADD INDEX `topic_id`  (`TOPIC_ID` ASC),
			ADD INDEX `post_id`   (`POST_ID` ASC),
			ADD INDEX `post_date` (`POST_DATE` ASC)
		" );
		time_elapsed( 'Indexes created' );

		debug_out( 'Removing topics from reply temp table...' );
		$wpdb->query( "
			DELETE FROM `{$config->temp_replies}`
			USING `{$config->temp_replies}`, `{$config->temp_topics}`
			WHERE `{$config->temp_replies}`.`POST_ID` = `{$config->temp_topics}`.`POST_ID`
		" );
		time_elapsed( 'Topics removed from reply table' );

		debug_out( 'Temp table setup complete.' );
	}
}

/**
 * Class MigrateUsers
 */
class MigrateUsers {

	/**
	 * @param MigrateConfig $config
	 *
	 * @return null|string
	 */
	public static function migrate ( $config ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		// Get all member records from Ikonboard
		debug_out( 'Querying all members...' );

		$limit = '';

		if ( 0 < $config->page && 0 < $config->limit ) {
			$limit = "LIMIT {$config->offset}, {$config->limit}";
		}

		$members = $wpdb->get_results( "
			SELECT
				`p`.`MEMBER_ID`, `p`.`MEMBER_NAME`, `p`.`MEMBER_EMAIL`, `p`.`MEMBER_JOINED`
			FROM
				`{$config->member_profiles}` AS `p`
			LEFT JOIN
				`{$wpdb->usermeta}` AS `um` ON `um`.`meta_key` = CONCAT( 'IKON_MEMBER_ID_', `p`.`MEMBER_ID` )
			WHERE
				`um`.`meta_value` IS NULL
			{$limit}
		" );

		$records_selected = count( $members );

		if ( empty( $records_selected ) ) {
			return 0;
		}

		debug_out( "Query returned $records_selected rows" );

		// Build a series of values strings for the insert
		$values = '';

		foreach ( $members as $this_member ) {
			$name = addslashes( $this_member->MEMBER_NAME );
			$email = addslashes( $this_member->MEMBER_EMAIL );
			$user_registered = date( 'Y-m-d H:i:s', (int) $this_member->MEMBER_JOINED );

			$password = '';

			if ( 32 == strlen( $this_member->MEMBER_PASSWORD ) ) {
				$password = $this_member->MEMBER_PASSWORD;
			}

			// Just doing it this way because it's easier to maintain the key/value list.
			// The keys are only used once, after the last iteration of the loop
			$user_data = array(
				'user_login'      => "'$name'",
				'user_pass'       => "'$password'",
				'user_nicename'   => "'" . sanitize_title( $name ) . "'", // sanitize name for URL use
				'display_name'    => "'$name'",
				'user_email'      => "'$email'",
				'user_registered' => "'$user_registered'"
			);

			$values .= '(' . implode( ', ', $user_data ) . '),';
		}

		// Pull off the last trailing comma and run the full insert
		$values = trim( $values, ',' );
		$keys = implode( ', ', array_keys( $user_data ) );
		$wpdb->query( "INSERT INTO `{$wpdb->users}` ($keys) VALUES $values" );

		// Statement order is significant here: ROW_COUNT() applies ONLY to the last sql statement, even a SELECT
		// LAST_INSERT_ID() applies to the most recently executed INSERT (returns the FIRST auto-generated ID if bulk)
		$added_rows = $wpdb->get_var( 'SELECT ROW_COUNT();' );
		$first_new_id = $wpdb->get_var( 'SELECT LAST_INSERT_ID();' );

		debug_out( "Added $added_rows members, adding meta..." );

		// Do it again for meta
		$values = '';
		$user_id = $first_new_id;
		foreach ( $members as $this_member ) {
			$member_id = addslashes( $this_member->MEMBER_ID );
			$values .= "($user_id, 'IKON_MEMBER_ID_$member_id', '$member_id'),";
			$user_id++;
		}
		$values = trim( $values, ',' );
		$wpdb->query( "INSERT INTO `{$wpdb->usermeta}` (user_id, meta_key, meta_value) VALUES $values" );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return $records_selected;
		}

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
		$categories = $wpdb->get_results( "
			SELECT
				`CAT_ID`, `CAT_NAME`, `CAT_POS`
			FROM
				`{$config->categories}`
		" );
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
		$forums = $wpdb->get_results( "
			SELECT
				`FORUM_ID`, `FORUM_NAME`, `FORUM_DESC`, `FORUM_POSITION`, `CATEGORY`
			FROM
				`{$config->forum_info}`
		" );
		foreach ( $forums as $this_forum ) {
			$post_title = addslashes( $this_forum->FORUM_NAME );
			$post_name = sanitize_title( $post_title );
			$post_content = addslashes( $this_forum->FORUM_DESC );
			$menu_order = (int) addslashes( $this_forum->FORUM_POSITION );

			// Lookup the forum parent via the Ikonboard category id we stash in meta
			$post_parent = (int) $wpdb->get_var( "SELECT `post_id` FROM `{$wpdb->postmeta}` WHERE `meta_key`='IKON_CAT_ID_{$this_forum->CATEGORY}'" );

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
			$wpdb->query( "INSERT INTO `{$wpdb->postmeta}` (`post_id`, `meta_key`, `meta_value`) VALUES ($post_id, 'IKON_FORUM_ID_$ikon_forum_id', '$ikon_forum_id')" );
		}

		// Set all the guids
		$site_url = get_site_url();
		$params = '/?post_type=forum&#038;p=';
		$wpdb->query( "UPDATE `{$wpdb->posts}` SET `guid` = CONCAT('$site_url', '$params', `ID`) WHERE `guid` = '' AND `post_type` = 'forum'" );
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

		$wpdb->query( "INSERT INTO `{$wpdb->posts}` ($keys) VALUES ($values)" );
		return $wpdb->get_var( 'SELECT LAST_INSERT_ID();' );
	}
}

/**
 * Class MigrateBatched
 */
abstract class MigrateBatched {

	const ROWS_TO_BUFFER = 50000;

	/**
	 * @var string
	 */
	protected static $target_table = '';

	/**
	 * @var string
	 */
	protected static $post_type = '';

	/**
	 * @var int
	 */
	protected static $current_start_row = 0;

	/**
	 * @var int|null
	 */
	protected static $first_new_id = null;

	/**
	 * @var int
	 */
	protected static $row_count = 0;

	/**
	 * @param array $records
	 */
	protected static function process_batch ( $records ) {
	}

	/**
	 * @param MigrateConfig $config
	 */
	public static function migrate ( $config ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		$table = self::$target_table;

		do {
			$start = self::$current_start_row;
			$rows = self::ROWS_TO_BUFFER;

			debug_out( sprintf( "Selecting rows %d to %d...", $start + 1, $start + $rows ) );
			$results = $wpdb->get_results( "SELECT * FROM `{$table}` LIMIT $start, $rows" );

			self::$row_count += count( $results );
			self::$current_start_row += self::ROWS_TO_BUFFER;
			static::process_batch( $results );
		}
		while ( count( $results ) == self::ROWS_TO_BUFFER );

		// Ensure unique page_name
		debug_out( 'Updating post slugs...' );
		$post_type = self::$post_type;
		$wpdb->query( "UPDATE `{$wpdb->posts}` SET `post_name` = CONCAT(`post_name`, '-', `ID`) WHERE `post_type` = '$post_type'" );

		// Set all the guids for the new posts
		debug_out( 'Updating all topic guids...' );
		$site_url = get_site_url();
		$params = "/?post_type=$post_type&#038;p=";
		$wpdb->query( "UPDATE `{$wpdb->posts}` SET `guid` = CONCAT('$site_url', '$params', `ID`) WHERE `guid` = '' AND `post_type` = '$post_type'" );
	}

	/**
	 * @param string $keys
	 * @param string $values
	 */
	protected static function insert_posts ( $keys, $values ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		debug_out( 'Inserting into wp_posts' );
		$values = rtrim( $values, ',' );
		$wpdb->query( "INSERT INTO {$wpdb->posts} ($keys) VALUES $values" );
		self::$first_new_id = $wpdb->get_var( 'SELECT LAST_INSERT_ID();' );
	}

	/**
	 * @param string $keys
	 * @param string $values
	 */
	protected static function insert_postmeta ( $keys, $values ) {
		/** @global wpdb $wpdb */
		global $wpdb;

		debug_out( 'Inserting into wp_postmeta' );
		$values = rtrim( $values, ',' );
		$wpdb->query( "INSERT INTO {$wpdb->postmeta} ($keys) VALUES $values" );
	}
}

/**
 * Class MigrateTopics
 */
class MigrateTopics extends MigrateBatched {

	/**
	 * @param MigrateConfig $config
	 */
	public static function migrate ( $config ) {
		self::$target_table = $config->temp_topics;
		self::$post_type = 'topic';
		parent::migrate( $config );
	}

	/**
	 * @param array $topics
	 */
	protected static function process_batch ( $topics ) {
		/** @global wpdb $wpdb */
		global $wpdb;

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

			if ( 0 == $row % 10000 ) {
				debug_out( "Buffering topic post $row" );
			}
		}
		self::insert_posts( $keys, $values );

		// topic meta
		$values = '';
		$post_id = self::$first_new_id;
		$row = 0;
		debug_out( 'Processing topic meta...' );
		debug_out( "(First new topic ID = $post_id)" );
		foreach ( $topics as $this_topic ) {
			$ikon_topic_id = (string) addslashes( $this_topic->TOPIC_ID );
			$author_ip = (string) addslashes( $this_topic->IP_ADDR );
			$bbp_forum_id = $wpdb->get_var( "SELECT post_parent FROM $wpdb->posts WHERE ID = $post_id" );

			$values .= "($post_id, 'IKON_TOPIC_ID_$ikon_topic_id', '$ikon_topic_id'),";
			$values .= "($post_id, '_bbp_forum_id', '$bbp_forum_id'),";
			$values .= "($post_id, '_bbp_topic_id', '$post_id'),";
			$values .= "($post_id, '_bbp_author_ip', '$author_ip'),";
			$row++;

			if ( 0 == $row % 10000 ) {
				debug_out( "Buffering topic meta $row" );
			}

			$post_id++;
		}
		self::insert_postmeta( 'post_id, meta_key, meta_value', $values );
	}
}

/**
 * Class MigrateReplies
 */
class MigrateReplies extends MigrateBatched {

	/**
	 * @param MigrateConfig $config
	 */
	public static function migrate ( $config ) {
		self::$target_table = $config->temp_replies;
		self::$post_type = 'reply';
		parent::migrate( $config );
	}

	/**
	 * @param array $replies
	 */
	protected static function process_batch ( $replies ) {
		/** @global wpdb $wpdb */
		global $wpdb;

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

			if ( 0 == $row % 10000 ) {
				debug_out( "Buffering reply post $row" );
			}
		}
		// Insert the remainder in the buffer
		self::insert_posts( $keys, $values );

		// reply meta
		$values = '';
		$post_id = self::$first_new_id;
		$row = 0;
		debug_out( 'Processing reply meta...' );
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

			if ( 0 == $row % 10000 ) {
				debug_out( "Buffering reply meta $row" );
			}

			$post_id++;
		}
		self::insert_postmeta( 'post_id, meta_key, meta_value', $values );
	}
}