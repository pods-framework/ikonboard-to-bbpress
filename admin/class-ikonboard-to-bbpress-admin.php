<?php
/**
 * @package   ikonboard-to-bbpress
 * @author    Scott Kingsley Clark <lol@scottkclark.com>, Phil Lewis
 * @license   GPL-2.0+
 * @copyright 2013 Scott Kingsley Clark, Phil Lewis
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * administrative side of the WordPress site.
 *
 * If you're interested in introducing public-facing
 * functionality, then refer to `bp-bbpress.php`
 *
 */
class IkonboardToBBPress_Admin {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * PodsAPI instance for Wizard.
	 *
	 * @since    1.0.0
	 *
	 * @var      PodsAPI
	 */
	protected $api = null;

	/**
	 * Tables for Wizard.
	 *
	 * @since    1.0.0
	 *
	 * @var      array
	 */
	protected $tables = array();

	/**
	 * Tables like for Wizard.
	 *
	 * @since    1.0.0
	 *
	 * @var      array
	 */
	protected $tables_like = array(
		'member_profiles',
		'categories',
		'forum_info',
		'forum_topics',
		'forum_posts'
	);

	/**
	 * Table prefix for Ikonboard.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $table_prefix = 'bp_';

	/**
	 * Table prefix for Ikonboard.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $base_table_prefix = 'bp_';

	/**
	 *
	 * @since    1.0.0
	 *
	 * @var      array
	 */
	protected $progress = array(
		'meta' => array()
	);

	/**
	 *
	 * @since    1.0.0
	 *
	 * @var      bool Whether to update items or insert only
	 */
	protected $update = true;

	/**
	 *
	 * @since    1.0.0
	 *
	 * @var      int How many items to migrate at a time (fallback)
	 */
	protected $limit = 20;

	/**
	 *
	 * @since    1.0.0
	 *
	 * @var      int How many users to migrate at a time
	 */
	protected $limit_users = 500;

	/**
	 *
	 * @since    1.0.0
	 *
	 * @var      int How many forum parents to migrate at a time
	 */
	protected $limit_forum_parents = 500;

	/**
	 *
	 * @since    1.0.0
	 *
	 * @var      int How many forums to migrate at a time
	 */
	protected $limit_forums = 500;

	/**
	 *
	 * @since    1.0.0
	 *
	 * @var      int How many topics to migrate at a time
	 */
	protected $limit_topics = 500;

	/**
	 *
	 * @since    1.0.0
	 *
	 * @var      int How many replies to migrate at a time
	 */
	protected $limit_replies = 500;

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		/*
		 * Call $plugin_slug from public plugin class.
		 */
		$plugin = IkonboardToBBPress::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();

		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ), 30 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 30 );

		// Add the options page and menu item.
		add_action( 'admin_init', array( $this, 'process_plugin_admin_post' ) );
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_slug . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		// Add AJAX handler
		add_action( 'wp_ajax_' . str_replace( '-', '_', $this->plugin_slug ), array( $this, 'ajax' ) );

	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;

	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		if ( !isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_style( $this->plugin_slug . '-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), IkonboardToBBPress::VERSION );

			wp_enqueue_style( 'pods-admin' );
			wp_enqueue_style( 'pods-wizard' );
		}

	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		if ( !isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), IkonboardToBBPress::VERSION );

			wp_enqueue_script( 'pods' );
			wp_enqueue_script( 'pods-migrate' );
		}

	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		/*
		 * Menu
		 */
		$this->plugin_screen_hook_suffix = add_management_page( __( 'Ikonboard to bbPress', $this->plugin_slug ), __( 'Ikonboard to bbPress', $this->plugin_slug ), 'manage_options', $this->plugin_slug, array( $this, 'display_plugin_admin_page' ) );

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {

		if ( isset( $_GET[ 'action' ] ) && 'migrate' == $_GET[ 'action' ] ) {
			require_once 'classes/migration-objects.php';
			include_once 'views/admin-migrate.php';
		}
		elseif ( isset( $_GET[ 'action' ] ) && 'migrate-basic' == $_GET[ 'action' ] ) {
			include_once 'views/admin.php';
		}
		else {
			$this->wizard();
		}

	}

	/**
	 *
	 */
	public function process_plugin_admin_post() {

		// Bail if needed
		if ( !isset( $_GET[ 'page' ] ) || $this->plugin_slug != $_GET[ 'page' ] || !isset( $_GET[ 'action' ] ) ) {
			return;
		}

		$this->api = pods_api();

		// The import
		if ( 'migrate' == $_GET[ 'action' ] ) {
			ini_set( 'memory_limit', '3075M' );
			set_time_limit( 0 );

			// These allow for unbuffered, real-time echos to the browser
			ob_implicit_flush( true );
			ini_set( 'zlib.output_compression', 0 );
			header( 'Content-Encoding: none;' );
		}

	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		return array_merge( array( 'settings' => '<a href="' . admin_url( 'tools.php?page=' . $this->plugin_slug ) . '">' . __( 'Settings', $this->plugin_slug ) . '</a>' ), $links );

	}

	/**
	 * Show the Wizard
	 *
	 * @since    1.0.0
	 */
	public function wizard() {

		global $wpdb;

		$wizard_migration = $this->plugin_slug;

		$wizard_ajax_action = str_replace( '-', '_', $this->plugin_slug );

		$wizard_cleanup = true;

		$wizard_i18n = array(
			'title' => __( 'Migrate Ikonboard to WordPress and bbPress', 'pods' ),
			'step1_description' => __( 'This wizard will guide you through migrating your Ikonboard forums over to WordPress and bbPress.', 'pods' ),
			'step2_description' => __( 'We will prepare all of your members, forums, topics, and replies for migration. If any issues are found they will be displayed below for your review. Be sure to backup your database before continuing onto the next step for Migration.', 'pods' ),
			'step3_description' => __( 'During this process your members, forums, topics, and replies will be migrated from Ikonboard to WordPress and bbPress. We will not delete any of your old data, the tables will remain until you choose to remove them up after a successful migration.', 'pods' )
		);

		$wizard_rows = array(
			'users' => __( 'Users (Ikonboard Members)', 'pods' ),
			'forum' => array(
				'forum_parents' => __( 'Parent Forums (Ikonboard Categories)', 'pods' ),
				'forums' => __( 'Forums', 'pods' )
			),
			'topics' => __( 'Topics', 'pods' ),
			'replies' => __( 'Replies (Ikonboard Posts)', 'pods' )
		);

		$wizard_migrate_rows = $wizard_rows;

		include_once 'views/wizard.php';

	}

	/**
	 *
	 */
	public function get_tables() {

		/**
		 * @var $wpdb WPDB
		 */
		global $wpdb;

		$wpdb->select( 'backpacker_ikonboard' );

		foreach ( $this->tables_like as $table_like ) {

			$tables = $wpdb->get_results( "SHOW TABLES LIKE '" . $this->base_table_prefix . $table_like . "%'", ARRAY_N );

			if ( !empty( $tables ) ) {
				foreach ( $tables as $table ) {
					$this->tables[] = $table[ 0 ];
				}
			}
		}

		$wpdb->select( DB_NAME );

	}

	/**
	 *
	 */
	public function get_progress() {

		$methods = get_class_methods( $this );

		foreach ( $methods as $method ) {
			if ( 0 === strpos( $method, 'migrate_' ) ) {
				$this->progress[ str_replace( 'migrate_', '', $method ) ] = false;
			}
		}

		$progress = (array) get_option( 'pods_framework_' . $this->plugin_slug . '_migrate', array() );

		if ( !empty( $progress ) ) {
			$this->progress = array_merge( $this->progress, $progress );
		}

	}

	/**
	 * @param $params
	 *
	 * @return mixed|void
	 */
	public function ajax() {

		$params = (object) pods_unsanitize( $_POST );

		require_once 'classes/migration-objects.php';

		$this->api = pods_api();

		$this->get_tables();

		// Stop notifications / etc in certain plugins
		define( 'WP_IMPORTING', true );

		if ( !wp_verify_nonce( $params->_wpnonce, 'pods-migrate' ) ) {
			return pods_error( __( 'Invalid request.', 'pods' ) );
		}
		elseif ( !isset( $params->step ) ) {
			return pods_error( __( 'Invalid migration process.', 'pods' ) );
		}
		elseif ( !isset( $params->type ) ) {
			return pods_error( __( 'Invalid migration method.', 'pods' ) );
		}
		elseif ( !method_exists( $this, $params->step . '_' . $params->type ) ) {
			return pods_error( __( 'Migration method not found.', 'pods' ) );
		}

		$count = call_user_func( array( $this, $params->step . '_' . $params->type ), $params );

		if ( is_int( $count ) && 1000 <= $count ) {
			$count = number_format( $count );
		}

		echo $count;

		die();

	}

	/**
	 * @param $method
	 * @param $v
	 * @param null $x
	 */
	public function update_progress_meta( $meta, $method, $v, $x = null ) {

		$method = str_replace( 'migrate_', '', $method );

		if ( !isset( $this->progress[ 'meta' ][ $method ] ) || !is_array( $this->progress[ 'meta' ][ $method ] ) ) {
			$this->progress[ 'meta' ][ $method ] = array();
		}

		if ( null !== $x ) {
			if ( !isset( $this->progress[ 'meta' ][ $method ][ $x ] ) || !is_array( $this->progress[ 'meta' ][ $method ][ $x ] ) ) {
				$this->progress[ 'meta' ][ $method ][ $x ] = array();
			}

			$this->progress[ 'meta' ][ $method ][ $x ][ $meta ] = $v;
		}
		else {
			$this->progress[ 'meta' ][ $method ][ $meta ] = $v;
		}

		update_option( 'pods_framework_' . $this->plugin_slug . '_migrate', $this->progress );

	}

	/**
	 * @param $method
	 * @param null $x
	 *
	 * @return bool
	 */
	public function get_progress_meta( $meta, $method, $x = null ) {

		$method = str_replace( 'migrate_', '', $method );

		if ( !isset( $this->progress[ 'meta' ][ $method ] ) || !is_array( $this->progress[ 'meta' ][ $method ] ) ) {
			return false;
		}

		if ( null !== $x ) {
			if ( !isset( $this->progress[ 'meta' ][ $method ][ $x ] ) || !is_array( $this->progress[ 'meta' ][ $method ][ $x ] ) ) {
				return false;
			}

			return $this->progress[ 'meta' ][ $method ][ $x ][ $meta ];
		}

		return $this->progress[ 'meta' ][ $method ][ $meta ];

	}

	/**
	 * @param $method
	 * @param $v
	 * @param null $x
	 */
	public function update_progress( $method, $v, $x = null ) {

		if ( true === $v ) {
			$this->update_progress_meta( 'left', $method, 0, $x );
			$this->update_progress_meta( 'end', $method, time(), $x );
		}

		$method = str_replace( 'migrate_', '', $method );

		if ( null !== $x ) {
			if ( !isset( $this->progress[ $method ] ) || !is_array( $this->progress[ $method ] ) ) {
				$this->progress[ $method ] = array();
			}

			$this->progress[ $method ][ $x ] = $v;
		}
		else {
			$this->progress[ $method ] = $v;
		}

		update_option( 'pods_framework_' . $this->plugin_slug . '_migrate', $this->progress );

	}

	/**
	 * @param $method
	 * @param null $x
	 *
	 * @return bool
	 */
	public function check_progress( $method, $x = null ) {

		$method = str_replace( 'migrate_', '', $method );

		if ( isset( $this->progress[ $method ] ) ) {
			if ( null === $x ) {
				return $this->progress[ $method ];
			}
			elseif ( is_array( $this->progress[ $method ] ) && isset( $this->progress[ $method ][ $x ] ) ) {
				return $this->progress[ $method ][ $x ];
			}
		}

		return false;

	}

	/**
	 * @return int
	 */
	public function prepare_users() {

		/**
		 * @var $wpdb WPDB
		 */
		global $wpdb;

		if ( !in_array( $this->base_table_prefix . 'member_profiles', $this->tables ) ) {
			return pods_error( __( 'Table(s) not found, it cannot be migrated', 'pods' ) );
		}

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_prefix}member_profiles" );

		return $count;

	}

	/**
	 * @param $params
	 *
	 * @return int
	 */
	public function prepare_forum( $params ) {

		/**
		 * @var $wpdb WPDB
		 */
		global $wpdb;

		if ( !isset( $params->object ) ) {
			return pods_error( __( 'Invalid Object.', 'pods' ) );
		}
		elseif ( !in_array( $this->base_table_prefix . 'categories', $this->tables ) || !in_array( $this->base_table_prefix . 'forum_info', $this->tables ) ) {
			return pods_error( __( 'Table(s) not found, it cannot be migrated', 'pods' ) );
		}

		$tables = array(
			'forum_parents' => $this->table_prefix . 'categories',
			'forums' => $this->table_prefix . 'forum_info'
		);

		if ( !isset( $tables[ $params->object ] ) ) {
			return pods_error( __( 'Invalid Object.', 'pods' ) );
		}

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables[$params->object]}" );

		return $count;

	}

	/**
	 * @return int
	 */
	public function prepare_topics() {

		/**
		 * @var $wpdb WPDB
		 */
		global $wpdb;

		if ( !in_array( $this->base_table_prefix . 'forum_topics', $this->tables ) ) {
			return pods_error( __( 'Table(s) not found, it cannot be migrated', 'pods' ) );
		}

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_prefix}forum_topics" );

		return $count;

	}

	/**
	 * @return int
	 */
	public function prepare_replies() {

		/**
		 * @var $wpdb WPDB
		 */
		global $wpdb;

		if ( !in_array( $this->base_table_prefix . 'forum_posts', $this->tables ) ) {
			return pods_error( __( 'Table(s) not found, it cannot be migrated', 'pods' ) );
		}

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_prefix}forum_posts" );

		return $count;

	}

	/**
	 * @param $params
	 *
	 * @return string
	 */
	public function migrate_users( $params ) {

		if ( !in_array( $this->base_table_prefix . 'member_profiles', $this->tables ) ) {
			return pods_error( __( 'Table(s) not found, it cannot be migrated', 'pods' ) );
		}

		$progress = $this->check_progress( __FUNCTION__, $params->object );

		if ( true === $progress ) {
			return '1';
		}

		$last_id = (int) $progress;

		$config = new MigrateConfig( $this->table_prefix, array(), $this );

		$total_found = $this->prepare_users( $params );

		//$migration_limit = $this->limit_users;

		if ( empty( $last_id ) ) {
			$this->update_progress_meta( 'start', __FUNCTION__, time(), $params->object );
			$this->update_progress_meta( 'total', __FUNCTION__, $total_found, $params->object );
		}

		$this->update_progress_meta( 'left', __FUNCTION__, $total_found, $params->object );

		$added_rows = MigrateUsers::migrate( $config );

		// All done!
		//if ( $added_rows < $migration_limit || 0 == $added_rows ) {
			$this->update_progress( __FUNCTION__, true, $params->object );

			return '1';
		//}

		// To be continued...
		//return '-2';

	}

	/**
	 * @param $params
	 *
	 * @return string
	 */
	public function migrate_forum( $params ) {

		if ( !isset( $params->object ) ) {
			return pods_error( __( 'Invalid Object.', 'pods' ) );
		}
		elseif ( !in_array( $this->base_table_prefix . 'categories', $this->tables ) || !in_array( $this->base_table_prefix . 'forum_info', $this->tables ) ) {
			return pods_error( __( 'Table(s) not found, it cannot be migrated', 'pods' ) );
		}

		$tables = array(
			'forum_parents' => $this->table_prefix . 'categories',
			'forums' => $this->table_prefix . 'forum_info'
		);

		$progress = $this->check_progress( __FUNCTION__, $params->object );

		if ( !isset( $tables[ $params->object ] ) ) {
			return pods_error( __( 'Invalid Object.', 'pods' ) );
		}
		elseif ( true === $progress ) {
			return '1';
		}

		$last_id = (int) $progress;

		$config = new MigrateConfig( $this->table_prefix, array(), $this );

		$total_found = $this->prepare_forum( $params );

		//$migration_limit = $this->limit_forums;

		if ( empty( $last_id ) ) {
			$this->update_progress_meta( 'start', __FUNCTION__, time(), $params->object );
			$this->update_progress_meta( 'total', __FUNCTION__, $total_found, $params->object );
		}

		$this->update_progress_meta( 'left', __FUNCTION__, $total_found, $params->object );

		$added_rows = MigrateForums::migrate( $config );

		// All done!
		//if ( $added_rows < $migration_limit || 0 == $added_rows ) {
			$this->update_progress( __FUNCTION__, true, $params->object );

			return '1';
		//}

		// To be continued...
		//return '-2';

	}

	/**
	 * @param $params
	 *
	 * @return string
	 */
	public function migrate_topics( $params ) {

		if ( !in_array( $this->base_table_prefix . 'forum_topics', $this->tables ) || !in_array( $this->base_table_prefix . 'forum_posts', $this->tables ) ) {
			return pods_error( __( 'Table(s) not found, it cannot be migrated', 'pods' ) );
		}

		$progress = $this->check_progress( __FUNCTION__, $params->object );

		if ( true === $progress ) {
			return '1';
		}

		$last_id = (int) $progress;

		$config = new MigrateConfig( $this->table_prefix, array(), $this );

		$total_found = $this->prepare_topics( $params );

		//$migration_limit = $this->limit_topics;

		if ( empty( $last_id ) ) {
			$this->update_progress_meta( 'start', __FUNCTION__, time(), $params->object );
			$this->update_progress_meta( 'total', __FUNCTION__, $total_found, $params->object );
		}

		$this->update_progress_meta( 'left', __FUNCTION__, $total_found, $params->object );

		$added_rows = MigrateTopics::migrate( $config );

		// All done!
		//if ( $added_rows < $migration_limit || 0 == $added_rows ) {
			$this->update_progress( __FUNCTION__, true, $params->object );

			return '1';
		//}

		// To be continued...
		//return '-2';

	}

	/**
	 * @param $params
	 *
	 * @return string
	 */
	public function migrate_replies( $params ) {

		if ( !in_array( $this->base_table_prefix . 'forum_topics', $this->tables ) || !in_array( $this->base_table_prefix . 'forum_posts', $this->tables ) ) {
			return pods_error( __( 'Table(s) not found, it cannot be migrated', 'pods' ) );
		}

		$progress = $this->check_progress( __FUNCTION__, $params->object );

		if ( true === $progress ) {
			return '1';
		}

		$last_id = (int) $progress;

		$config = new MigrateConfig( $this->table_prefix, array(), $this );

		$total_found = $this->prepare_replies( $params );

		//$migration_limit = $this->limit_users;

		if ( empty( $last_id ) ) {
			$this->update_progress_meta( 'start', __FUNCTION__, time(), $params->object );
			$this->update_progress_meta( 'total', __FUNCTION__, $total_found, $params->object );
		}

		$this->update_progress_meta( 'left', __FUNCTION__, $total_found, $params->object );

		$added_rows = MigrateReplies::migrate( $config );

		// All done!
		//if ( $added_rows < $migration_limit || 0 == $added_rows ) {
			$this->update_progress( __FUNCTION__, true, $params->object );

			return '1';
		//}

		// To be continued...
		//return '-2';

	}

	/**
	 * @return string
	 */
	public function migrate_cleanup() {

		update_option( 'pods_framework_' . $this->plugin_slug . '_migrated', 1 );

		$this->api->cache_flush_pods();

		return '1';

	}

	/**
	 * Get the new ID from an older object ID
	 *
	 * @param string $object Object Type (user / post)
	 * @param int $old_id Old Drupal ID
	 * @param string $old_type Old File Type (for attachments)
	 * @param string $type Type (for post type)
	 * @param string $exclude_type Type to Exclude (for post type)
	 *
	 * @return int
	 */
	public function get_new_id( $object, $old_id, $old_type = null, $type = null, $exclude_type = null ) {

		global $wpdb;

		$new_id = 0;

		if ( !in_array( $object, array( 'term', 'term_name', 'post_title', 'post_name' ) ) ) {
			$old_id = (int) $old_id;

			if ( empty( $old_id ) ) {
				return $new_id;
			}
		}

		if ( !empty( $old_id ) ) {
			if ( 'user' == $object ) {
				$new_id = (int) $wpdb->get_var( "SELECT `user_id` FROM `{$wpdb->usermeta}` WHERE `meta_key` = 'old_id' AND `meta_value` = {$old_id}" );
			}
			elseif ( 'post' == $object ) {
				if ( empty( $old_type ) ) {
					if ( !empty( $type ) ) {
						$new_id = (int) $wpdb->get_var( $wpdb->prepare( "
                                SELECT `pm`.`post_id`
                                FROM `{$wpdb->posts}` AS `p`
                                LEFT JOIN `{$wpdb->postmeta}` AS `pm` ON `pm`.`post_id` = `p`.`ID` AND `pm`.`meta_key` = 'old_id'
                                WHERE
                                    `p`.`post_type` = %s
                                    AND `pm`.`meta_value` = %s
                            ", array(
									$type,
									$old_id
							   ) ) );
					}
					elseif ( !empty( $exclude_type ) ) {
						$new_id = (int) $wpdb->get_var( $wpdb->prepare( "
                                SELECT `pm`.`post_id`
                                FROM `{$wpdb->posts}` AS `p`
                                LEFT JOIN `{$wpdb->postmeta}` AS `pm` ON `pm`.`post_id` = `p`.`ID` AND `pm`.`meta_key` = 'old_id'
                                WHERE
                                    `p`.`post_type` NOT IN ( %s, 'inherit' )
                                    AND `pm`.`meta_value` = %s
                            ", array(
									$exclude_type,
									$old_id
							   ) ) );
					}
					else {
						$new_id = (int) $wpdb->get_var( "SELECT `post_id` FROM `{$wpdb->postmeta}` WHERE `meta_key` = 'old_id' AND `meta_value` = {$old_id}" );
					}
				}
				else {
					$new_id = (int) $wpdb->get_var( $wpdb->prepare( "
                            SELECT `pm`.`post_id`
                            FROM `{$wpdb->postmeta}` AS `pm`
                            LEFT JOIN `{$wpdb->postmeta}` AS `pm2` ON `pm2`.`post_id` = `pm`.`post_id` AND `pm2`.`meta_key` = 'old_type'
                            WHERE
                                `pm`.`meta_key` = 'old_id'
                                AND `pm`.`meta_value` = %s
                                AND `pm2`.`meta_value` = %s
                        ", array(
								$old_id,
								$old_type
						   ) ) );
				}
			}
			elseif ( 'post_title' == $object ) {
				if ( empty( $type ) ) {
					$new_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT `ID` FROM `{$wpdb->posts}` WHERE `post_title` = %s", $old_id ) );
				}
				else {
					$new_id = (int) $wpdb->get_var( $wpdb->prepare( "
                            SELECT `ID`
                            FROM `{$wpdb->posts}`
                            WHERE
                                `post_title` = %s
                                AND `post_type` = %s
                        ", array(
								$old_id,
								$type
						   ) ) );
				}
			}
			elseif ( 'post_name' == $object ) {
				if ( empty( $type ) ) {
					$new_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT `ID` FROM `{$wpdb->posts}` WHERE `post_name` = %s", $old_id ) );
				}
				else {
					$new_id = (int) $wpdb->get_var( $wpdb->prepare( "
                            SELECT `ID`
                            FROM `{$wpdb->posts}`
                            WHERE
                                `post_name` = %s
                                AND `post_type` = %s
                        ", array(
								$old_id,
								$type
						   ) ) );
				}
			}
			elseif ( 'term' == $object ) {
				$new_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT `term_id` FROM `{$wpdb->terms}` WHERE `slug` = %s", $old_id ) );
			}
			elseif ( 'term_name' == $object ) {
				$new_id = $wpdb->get_var( $wpdb->prepare( "SELECT `name` FROM `{$wpdb->terms}` WHERE `slug` = %s", $old_id ) );
			}
			elseif ( 'term_taxonomy' == $object ) {
				$new_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT `term_taxonomy_id` FROM `{$wpdb->term_taxonomy}` WHERE `term_id` = %d AND `taxonomy` = %s", array(
																																										 $old_id,
																																										 $old_type
																																									) ) );
			}
			elseif ( 'term_relationship' == $object ) {
				$new_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT `object_id` FROM `{$wpdb->term_relationships}` WHERE `term_taxonomy_id` = %d AND `object_id` = %d", array(
																																												 $old_id,
																																												 $old_type
																																											) ) );
			}
			elseif ( 'comment' == $object ) {
				$new_id = (int) $wpdb->get_var( "SELECT `comment_id` FROM `{$wpdb->commentmeta}` WHERE `meta_key` = 'old_id' AND `meta_value` = {$old_id}" );
			}
		}

		return $new_id;

	}

	/**
	 * An array form of str_repeat, repeat a value into an array (used for $wpdb format arrays)
	 *
	 * @param $value
	 * @param $count
	 *
	 * @return array
	 */
	public function array_repeat( $value, $count ) {

		$array = array();

		for ( $x = 0; $x < $count; $x++ ) {
			$array[] = $value;
		}

		return $array;

	}

	/**
	 * Clean Drupal content for wpautop
	 *
	 * @param string $content Drupal Content
	 *
	 * @return string Content ready for wpautop
	 */
	public function clean_content( $content ) {

		$content = preg_replace( '/[ \t]+/', ' ', $content ); // replace repeating whitespace (spaces / tabs) with a single space
		$content = str_replace( array( "\r\n", " \n", "\n " ), "\n", $content ); // replace \r\n and trim \n characters
		$content = preg_replace( '/\n{1}([^\s])/', ' $1', $content ); // replace single \n uses with a space
		$content = preg_replace( '/\n{3,}/', "\n\n", $content ); // replace any uses of \n more than two with just two

		// @todo Handle URL replacements from old to new

		return $content;

	}

	/**
	 *
	 */
	public function restart() {

		/**
		 * @var $wpdb WPDB
		 */
		global $wpdb;

		delete_option( 'pods_framework_' . $this->plugin_slug . '_migrate' );
		delete_option( 'pods_framework_' . $this->plugin_slug . '_migrated' );

	}

	/**
	 *
	 */
	public function cleanup() {

		/**
		 * @var $wpdb WPDB
		 */
		global $wpdb;

		/*delete_option( 'pods_framework_' . $this->plugin_slug . '_migrate' );
		delete_option( 'pods_framework_' . $this->plugin_slug . '_migrated' );*/
	}

	/**
	 * NOTE:     Actions are points in the execution of a page or process
	 *           lifecycle that WordPress fires.
	 *
	 *           Actions:    http://codex.wordpress.org/Plugin_API#Actions
	 *           Reference:  http://codex.wordpress.org/Plugin_API/Action_Reference
	 *
	 * @since    1.0.0
	 */
	public function action_method_name() {
		// TODO: Define your action hook callback here
	}

	/**
	 * NOTE:     Filters are points of execution in which WordPress modifies data
	 *           before saving it or sending it to the browser.
	 *
	 *           Filters: http://codex.wordpress.org/Plugin_API#Filters
	 *           Reference:  http://codex.wordpress.org/Plugin_API/Filter_Reference
	 *
	 * @since    1.0.0
	 */
	public function filter_method_name() {
		// TODO: Define your filter hook callback here
	}

}
