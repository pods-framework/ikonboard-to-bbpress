<?php
/**
 * Class MigrateUsers
 */
class MigrateUsers {

	public static function migrate () {
		/** @global wpdb $wpdb */
		global $wpdb;

		$members = $wpdb->get_results( "SELECT * FROM bp_member_profiles LIMIT 1000" );
		foreach ( $members as $this_member ) {

			// wp_users insert
			$name = addslashes( $this_member->MEMBER_NAME );
			$email = addslashes( $this_member->MEMBER_EMAIL );
			$sql = "
					INSERT INTO {$wpdb->users}
						(user_login, user_nicename, display_name, user_email)
					VALUES
						('$name', '$name', '$name', '$email');
				";
			$wpdb->query( $sql );

			// Get the new user ID
			$user_id = $wpdb->get_var( 'SELECT LAST_INSERT_ID();' );

			// wp_usermeta insert
			$member_id = addslashes( $this_member->MEMBER_ID );
			$sql = "
				INSERT INTO {$wpdb->usermeta}
					(user_id, meta_key, meta_value)
				VALUES
					($user_id, 'MEMBER_ID', '$member_id')
			";
			$wpdb->query( $sql );
		}

	}
}

class MigrateTopics {

	public static function migrate () {
		/** @global wpdb $wpdb */
		global $wpdb;

		$topics = $wpdb->get_results( "
			SELECT
				*
			FROM
				bp_forum_topics AS t
				LEFT JOIN bp_forum_posts AS p
					ON p.TOPIC_ID = t.TOPIC_ID
					AND p.POST_DATE = (
						SELECT
							MIN(POST_DATE)
						FROM
							bp_forum_posts AS ptemp
						WHERE
							ptemp.TOPIC_ID = t.TOPIC_ID
					)
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

class MigrateReplies {

	public static function migrate () {
		/** @global wpdb $wpdb */
		global $wpdb;

		$replies = $wpdb->get_results( "
			SELECT
				*
			FROM
				bp_forum_topics AS t
				LEFT JOIN bp_forum_posts AS p
					ON p.TOPIC_ID = t.TOPIC_ID
					AND p.POST_DATE != (
						SELECT
							MIN(POST_DATE)
						FROM
							bp_forum_posts AS ptemp
						WHERE
							ptemp.TOPIC_ID = t.TOPIC_ID
					)
		" );

		/*
		foreach($replies as $this_reply) {

			// insert reply post

			// insert reply meta
		}
		*/

	}
}