<?php
/**
 * Transaction log and reward crediting. Every transaction ID is handled once, and a reversal only takes back
 * a reward that was credited before.
 *
 * @package Moonetix_Offerwall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- the plugin's own transaction table and an atomic balance update; these must never be served from a cache.

/**
 * Keeps the transaction log and moves points to the chosen destination.
 */
class Moonetix_Offerwall_Ledger {

	const BALANCE_META = 'moonetix_offerwall_balance';

	/**
	 * User meta key of the built-in balance. Per site, so the sites of a multisite network keep separate balances.
	 *
	 * @return string
	 */
	public static function balance_key() {
		global $wpdb;
		return $wpdb->get_blog_prefix() . self::BALANCE_META;
	}

	/**
	 * User meta key of the waiting reward notices, per site.
	 *
	 * @return string
	 */
	public static function toast_key() {
		global $wpdb;
		return $wpdb->get_blog_prefix() . 'moonetix_offerwall_toasts';
	}

	/**
	 * Name of the transaction table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'moonetix_offerwall_log';
	}

	/**
	 * Creates or updates the transaction table.
	 */
	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			transaction_id varchar(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
			external_user_id varchar(191) NOT NULL DEFAULT '',
			wp_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			reward decimal(20,5) NOT NULL DEFAULT 0,
			moved decimal(20,5) NOT NULL DEFAULT 0,
			status tinyint(3) unsigned NOT NULL DEFAULT 0,
			result varchar(20) NOT NULL DEFAULT '',
			destination varchar(20) NOT NULL DEFAULT '',
			offer_name varchar(191) NOT NULL DEFAULT '',
			offer_id varchar(32) NOT NULL DEFAULT '',
			payout decimal(20,5) NOT NULL DEFAULT 0,
			country char(2) NOT NULL DEFAULT '',
			device varchar(10) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY transaction_id (transaction_id),
			KEY wp_user_id (wp_user_id)
		) ENGINE=InnoDB {$charset};";
		dbDelta( $sql );
		update_option( 'moonetix_offerwall_db_version', MOONETIX_OFFERWALL_DB_VERSION, false );
	}

	/**
	 * Runs install() after an update that changed the table.
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'moonetix_offerwall_db_version' ) !== MOONETIX_OFFERWALL_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Handles one verified postback.
	 *
	 * @param string $transaction_id   Moonetix transaction ID.
	 * @param string $external_user_id User ID as sent by Moonetix.
	 * @param int    $wp_user_id       WordPress user, 0 when unknown.
	 * @param float  $reward           Reward in the site currency, never negative.
	 * @param int    $status           1 credited, 2 reversed.
	 * @param string $offer_name       Offer name for the log.
	 * @param array  $extra            Unsigned details for the log: offer_id, payout, country, device.
	 * @return array{result:string,http:int}
	 */
	public static function handle( $transaction_id, $external_user_id, $wp_user_id, $reward, $status, $offer_name, $extra = array() ) {
		global $wpdb;
		$table       = self::table();
		$now         = current_time( 'mysql', true );
		$destination = (string) Moonetix_Offerwall_Settings::get( 'destination' );
		$extra       = array_merge(
			array(
				'offer_id' => '',
				'payout'   => 0,
				'country'  => '',
				'device'   => '',
			),
			(array) $extra
		);

		$wpdb->query( 'START TRANSACTION' );
		$row = null;
		if ( 2 === $status ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE transaction_id = %s FOR UPDATE", $table, $transaction_id ), ARRAY_A );
		}

		if ( 1 === $status ) {
			// The unique transaction ID is claimed first, so two copies of one postback arriving at the same moment
			// cannot both credit: the second insert waits for the first and then inserts nothing.
			$extra['moved'] = $wp_user_id > 0 ? self::planned( $destination, $reward ) : 0;
			$claimed        = self::insert( $transaction_id, $external_user_id, $wp_user_id, $reward, $status, $wp_user_id > 0 ? 'credited' : 'unknown_user', $wp_user_id > 0 ? $destination : '', $offer_name, $now, $extra );
			if ( null === $claimed ) {
				$wpdb->query( 'ROLLBACK' );
				self::note_failure( 'database' );
				return array(
					'result' => 'error',
					'http'   => 503,
				);
			}
			if ( ! $claimed ) {
				$wpdb->query( 'COMMIT' );
				return array(
					'result' => 'duplicate',
					'http'   => 200,
				);
			}
			if ( $wp_user_id <= 0 ) {
				$wpdb->query( 'COMMIT' );
				return array(
					'result' => 'unknown_user',
					'http'   => 200,
				);
			}
			$moved = self::move( $destination, $wp_user_id, $reward, $transaction_id, $offer_name, false );
			if ( false === $moved ) {
				$wpdb->query( 'ROLLBACK' );
				self::note_failure( $destination );
				return array(
					'result' => 'destination_unavailable',
					'http'   => 503,
				);
			}
			if ( abs( $moved - (float) $extra['moved'] ) > 0.000001 ) {
				$wpdb->query( $wpdb->prepare( 'UPDATE %i SET moved = %f WHERE transaction_id = %s', $table, $moved, $transaction_id ) );
			}
			$wpdb->query( 'COMMIT' );
			self::queue_toast( $wp_user_id, $moved, $offer_name, $destination );
			return array(
				'result' => 'credited',
				'http'   => 200,
			);
		}

		if ( ! $row ) {
			self::insert( $transaction_id, $external_user_id, $wp_user_id, $reward, $status, 'ignored', '', $offer_name, $now, $extra );
			$wpdb->query( 'COMMIT' );
			return array(
				'result' => 'ignored',
				'http'   => 200,
			);
		}
		if ( 'credited' !== $row['result'] ) {
			$wpdb->query( 'COMMIT' );
			return array(
				'result' => 'duplicate',
				'http'   => 200,
			);
		}
		$taken = (float) $row['reward'];
		$exact = 'terawallet' === $row['destination'] ? (float) $row['moved'] : null;
		// A row whose user was erased has nobody to take the reward back from; it is only marked as reversed.
		if ( (int) $row['wp_user_id'] > 0 && false === self::move( (string) $row['destination'], (int) $row['wp_user_id'], $taken, $transaction_id, (string) $row['offer_name'], true, $exact ) ) {
			$wpdb->query( 'ROLLBACK' );
			self::note_failure( (string) $row['destination'] );
			return array(
				'result' => 'destination_unavailable',
				'http'   => 503,
			);
		}
		$wpdb->update(
			$table,
			array(
				'status'     => 2,
				'result'     => 'reversed',
				'updated_at' => $now,
			),
			array( 'id' => (int) $row['id'] ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
		$wpdb->query( 'COMMIT' );
		return array(
			'result' => 'reversed',
			'http'   => 200,
		);
	}

	/**
	 * Adds a row to the transaction log.
	 *
	 * @param string $transaction_id   Transaction ID.
	 * @param string $external_user_id User ID as sent.
	 * @param int    $wp_user_id       WordPress user.
	 * @param float  $reward           Reward.
	 * @param int    $status           Status.
	 * @param string $result           What the plugin did.
	 * @param string $destination      Where the points went.
	 * @param string $offer_name       Offer name.
	 * @param string $now              Time (UTC).
	 * @param array  $extra            offer_id, payout, country, device, and moved: the amount for the destination.
	 * @return bool|null True when added, false when the transaction ID is already in the log, null on a database error.
	 */
	private static function insert( $transaction_id, $external_user_id, $wp_user_id, $reward, $status, $result, $destination, $offer_name, $now, $extra ) {
		global $wpdb;
		$table = self::table();
		$done  = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (transaction_id, external_user_id, wp_user_id, reward, moved, status, result, destination, offer_name, offer_id, payout, country, device, created_at, updated_at) VALUES (%s, %s, %d, %f, %f, %d, %s, %s, %s, %s, %f, %s, %s, %s, %s)", $table, $transaction_id, $external_user_id, $wp_user_id, $reward, isset( $extra['moved'] ) ? (float) $extra['moved'] : 0.0, $status, $result, $destination, $offer_name, (string) $extra['offer_id'], (float) $extra['payout'], (string) $extra['country'], (string) $extra['device'], $now, $now ) );
		if ( false === $done ) {
			return null;
		}
		return 1 === (int) $done;
	}

	/**
	 * The amount a reward becomes in a destination: money for TeraWallet, whole points for GamiPress.
	 *
	 * @param string $destination Destination.
	 * @param float  $reward      Reward in points.
	 * @return float
	 */
	private static function planned( $destination, $reward ) {
		if ( 'terawallet' === $destination ) {
			return self::to_wallet( $reward );
		}
		if ( 'gamipress' === $destination ) {
			return (float) round( $reward );
		}
		return (float) $reward;
	}

	/**
	 * Remembers that a reward could not be credited, for the notice on the settings page. The postback is answered
	 * with an error, so Moonetix sends it again later.
	 *
	 * @param string $destination The points plugin that was not active.
	 */
	private static function note_failure( $destination ) {
		update_option(
			'moonetix_offerwall_last_failure',
			array(
				'time'        => time(),
				'destination' => $destination,
			),
			false
		);
	}

	/**
	 * Adds or takes back points.
	 *
	 * @param string $destination    builtin, mycred or gamipress.
	 * @param int    $wp_user_id     User.
	 * @param float  $amount         Positive amount.
	 * @param string $transaction_id Transaction ID, used as reference.
	 * @param string $offer_name     Offer name for the points log of the other plugins.
	 * @param bool   $reverse        Take back instead of add.
	 * @param float  $exact          TeraWallet only: the wallet amount to take back, as credited before.
	 * @return float|false The amount moved in the unit of the destination, false when it could not be moved.
	 */
	private static function move( $destination, $wp_user_id, $amount, $transaction_id, $offer_name, $reverse, $exact = null ) {
		$signed = $reverse ? -1 * $amount : $amount;
		/* translators: %s: offer name. */
		$entry = $reverse ? sprintf( __( 'Offerwall reward reversed: %s', 'moonetix-offerwall' ), $offer_name ) : sprintf( __( 'Offerwall reward: %s', 'moonetix-offerwall' ), $offer_name );

		if ( 'mycred' === $destination ) {
			if ( ! function_exists( 'mycred_add' ) || ! function_exists( 'mycred_subtract' ) ) {
				return false;
			}
			$type = (string) Moonetix_Offerwall_Settings::get( 'mycred_type' );
			$data = array(
				'ref_type'       => 'moonetix_offerwall',
				'transaction_id' => $transaction_id,
			);
			if ( $reverse ) {
				mycred_subtract( 'moonetix_offerwall_reversal', $wp_user_id, $amount, $entry, 0, $data, $type );
			} else {
				mycred_add( 'moonetix_offerwall', $wp_user_id, $amount, $entry, 0, $data, $type );
			}
			return (float) $amount;
		}

		if ( 'gamipress' === $destination ) {
			if ( ! function_exists( 'gamipress_award_points_to_user' ) || ! function_exists( 'gamipress_deduct_points_to_user' ) ) {
				return false;
			}
			$type   = (string) Moonetix_Offerwall_Settings::get( 'gamipress_type' );
			$points = (int) round( $amount );
			$args   = array(
				'reason'   => $entry,
				'log_type' => 'points_' . ( $reverse ? 'deduct' : 'award' ),
			);
			if ( $points > 0 ) {
				if ( $reverse ) {
					gamipress_deduct_points_to_user( $wp_user_id, $points, $type, $args );
				} else {
					gamipress_award_points_to_user( $wp_user_id, $points, $type, $args );
				}
			}
			return (float) $points;
		}

		if ( 'terawallet' === $destination ) {
			if ( ! Moonetix_Offerwall_Settings::wallet_active() ) {
				return false;
			}
			$money = null !== $exact ? round( (float) $exact, 5 ) : self::to_wallet( $amount );
			if ( $money <= 0 ) {
				return 0.0;
			}
			$wallet = woo_wallet()->wallet;
			$args   = array( 'for' => 'moonetix_offerwall' );
			if ( $reverse ) {
				// A reversal takes the reward back even when the user already spent it, so the wallet may go below zero.
				add_filter( 'woo_wallet_disallow_negative_transaction', '__return_false', 999 );
				try {
					$done = $wallet->debit( $wp_user_id, $money, $entry, $args );
				} finally {
					remove_filter( 'woo_wallet_disallow_negative_transaction', '__return_false', 999 );
				}
			} else {
				$done = $wallet->credit( $wp_user_id, $money, $entry, $args );
			}
			return $done ? $money : false;
		}

		global $wpdb;
		add_user_meta( $wp_user_id, self::balance_key(), '0', true );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->usermeta} SET meta_value = ROUND(CAST(meta_value AS DECIMAL(20,5)) + %f, 5) WHERE user_id = %d AND meta_key = %s",
				$signed,
				$wp_user_id,
				self::balance_key()
			)
		);
		wp_cache_delete( $wp_user_id, 'user_meta' );
		do_action( 'moonetix_offerwall_balance_changed', $wp_user_id, $signed, $transaction_id );
		return (float) $amount;
	}

	/**
	 * Converts points to the store currency of TeraWallet with the rate from the settings, rounded to the
	 * decimals of the store prices.
	 *
	 * @param float $points Reward in points.
	 * @return float
	 */
	public static function to_wallet( $points ) {
		$rate     = (float) Moonetix_Offerwall_Settings::get( 'wallet_rate' );
		$rate     = $rate > 0 ? $rate : 1.0;
		$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
		return round( (float) $points / $rate, max( 0, min( 5, $decimals ) ) );
	}

	/**
	 * Balance of a user in the chosen destination.
	 *
	 * @param int $wp_user_id User.
	 * @return float
	 */
	public static function balance( $wp_user_id ) {
		$destination = (string) Moonetix_Offerwall_Settings::get( 'destination' );
		if ( 'mycred' === $destination && function_exists( 'mycred_get_users_balance' ) ) {
			return (float) mycred_get_users_balance( $wp_user_id, (string) Moonetix_Offerwall_Settings::get( 'mycred_type' ) );
		}
		if ( 'gamipress' === $destination && function_exists( 'gamipress_get_user_points' ) ) {
			return (float) gamipress_get_user_points( $wp_user_id, (string) Moonetix_Offerwall_Settings::get( 'gamipress_type' ) );
		}
		if ( 'terawallet' === $destination && Moonetix_Offerwall_Settings::wallet_active() ) {
			return (float) woo_wallet()->wallet->get_wallet_balance( $wp_user_id, 'edit' );
		}
		return (float) get_user_meta( $wp_user_id, self::balance_key(), true );
	}

	/**
	 * Latest log rows, for the settings page or one user's history.
	 *
	 * @param int  $limit       Number of rows.
	 * @param int  $wp_user_id  Only this user, 0 for everyone.
	 * @param bool $all_results With a user: every row, not only credits and reversals.
	 * @return array
	 */
	public static function recent( $limit, $wp_user_id = 0, $all_results = false ) {
		global $wpdb;
		$table = self::table();
		if ( $wp_user_id > 0 && $all_results ) {
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE wp_user_id = %d ORDER BY id DESC LIMIT %d", $table, $wp_user_id, $limit ), ARRAY_A );
		}
		if ( $wp_user_id > 0 ) {
			return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE wp_user_id = %d AND result IN ('credited', 'reversed', 'adjusted') ORDER BY id DESC LIMIT %d", $table, $wp_user_id, $limit ), ARRAY_A );
		}
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i ORDER BY id DESC LIMIT %d", $table, $limit ), ARRAY_A );
	}

	/**
	 * One page of the log for the admin list: everyone, one user, or a search.
	 *
	 * @param string $q      Search text, empty for none.
	 * @param int    $user   Only this WordPress user, 0 for everyone.
	 * @param int    $offset Rows to skip.
	 * @param int    $limit  Rows to return.
	 * @return array{0:array,1:int} Rows and the total number of matching rows.
	 */
	public static function page( $q, $user, $offset, $limit ) {
		global $wpdb;
		$table = self::table();
		if ( $user > 0 ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE wp_user_id = %d', $table, $user ) );
			$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE wp_user_id = %d ORDER BY id DESC LIMIT %d OFFSET %d', $table, $user, $limit, $offset ), ARRAY_A );
		} elseif ( '' !== $q ) {
			$found = get_user_by( 'login', $q );
			if ( ! $found && is_email( $q ) ) {
				$found = get_user_by( 'email', $q );
			}
			if ( ! $found && preg_match( '/^[1-9][0-9]{0,18}$/D', $q ) ) {
				$found = get_user_by( 'id', (int) $q );
			}
			$uid   = $found ? (int) $found->ID : -1;
			$like  = '%' . $wpdb->esc_like( $q ) . '%';
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE wp_user_id = %d OR external_user_id = %s OR transaction_id LIKE %s OR offer_name LIKE %s', $table, $uid, $q, $like, $like ) );
			$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE wp_user_id = %d OR external_user_id = %s OR transaction_id LIKE %s OR offer_name LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d', $table, $uid, $q, $like, $like, $limit, $offset ), ARRAY_A );
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
			$rows  = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', $table, $limit, $offset ), ARRAY_A );
		}
		return array( $rows, $total );
	}

	/**
	 * Credits and reversals of the last days, for the dashboard widget.
	 *
	 * @param int $days Number of days.
	 * @return array{credited:int,reward:float,wallet:float,reversed:int}
	 */
	public static function stats( $days ) {
		global $wpdb;
		$since = gmdate( 'Y-m-d H:i:s', time() - (int) $days * DAY_IN_SECONDS );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT SUM(result = 'credited') AS credited, SUM(CASE WHEN result = 'credited' THEN reward ELSE 0 END) AS reward, SUM(CASE WHEN result = 'credited' AND destination = 'terawallet' THEN moved ELSE 0 END) AS wallet, SUM(result = 'reversed') AS reversed FROM %i WHERE created_at >= %s", self::table(), $since ), ARRAY_A );
		return array(
			'credited' => (int) ( $row['credited'] ?? 0 ),
			'reward'   => (float) ( $row['reward'] ?? 0 ),
			'wallet'   => (float) ( $row['wallet'] ?? 0 ),
			'reversed' => (int) ( $row['reversed'] ?? 0 ),
		);
	}

	/**
	 * Manual change of the built-in balance by an administrator, logged like a postback.
	 *
	 * @param int    $wp_user_id User.
	 * @param float  $amount     Positive to add, negative to take away.
	 * @param string $note       Reason, shown to the user in [moonetix_history].
	 * @param int    $by         Administrator who made the change.
	 * @return bool
	 */
	public static function adjust( $wp_user_id, $amount, $note, $by ) {
		global $wpdb;
		$amount = round( (float) $amount, 5 );
		if ( $wp_user_id <= 0 || 0.0 === $amount ) {
			return false;
		}
		$now = current_time( 'mysql', true );
		$tx  = 'manual_' . gmdate( 'YmdHis' ) . '_' . wp_generate_password( 8, false, false );
		$wpdb->query( 'START TRANSACTION' );
		$ok = self::insert(
			$tx,
			'admin:' . (int) $by,
			$wp_user_id,
			$amount,
			0,
			'adjusted',
			'builtin',
			mb_substr( $note, 0, 191 ),
			$now,
			array(
				'offer_id' => '',
				'payout'   => 0,
				'country'  => '',
				'device'   => '',
			)
		);
		if ( ! $ok || false === self::move( 'builtin', $wp_user_id, abs( $amount ), $tx, $note, $amount < 0 ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}
		$wpdb->query( 'COMMIT' );
		return true;
	}

	/**
	 * Remembers a credited reward for the small notice the user sees on the next page.
	 *
	 * @param int    $wp_user_id  User.
	 * @param float  $reward      Amount credited, in the unit of the destination.
	 * @param string $offer_name  Offer name.
	 * @param string $destination Where the reward went, for the unit shown in the notice.
	 */
	private static function queue_toast( $wp_user_id, $reward, $offer_name, $destination = 'builtin' ) {
		if ( ! Moonetix_Offerwall_Settings::get( 'toast' ) || (float) $reward <= 0 ) {
			return;
		}
		$list   = get_user_meta( $wp_user_id, self::toast_key(), true );
		$list   = is_array( $list ) ? $list : array();
		$list[] = array(
			'reward' => (float) $reward,
			'dest'   => (string) $destination,
			'offer'  => mb_substr( $offer_name, 0, 80 ),
			'time'   => time(),
		);
		update_user_meta( $wp_user_id, self::toast_key(), array_slice( $list, -5 ) );
	}

	/**
	 * Takes the waiting reward notices of a user, newer than a day.
	 *
	 * @param int $wp_user_id User.
	 * @return array
	 */
	public static function take_toasts( $wp_user_id ) {
		$list = get_user_meta( $wp_user_id, self::toast_key(), true );
		if ( ! is_array( $list ) || ! $list ) {
			return array();
		}
		delete_user_meta( $wp_user_id, self::toast_key() );
		return array_values(
			array_filter(
				$list,
				static function ( $t ) {
					return is_array( $t ) && isset( $t['time'] ) && (int) $t['time'] > time() - DAY_IN_SECONDS;
				}
			)
		);
	}

	/**
	 * Personal data exporter for Tools, Export Personal Data.
	 *
	 * @param string $email User email.
	 * @param int    $page  Page, 100 rows each.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		global $wpdb;
		$user = get_user_by( 'email', $email );
		$data = array();
		$done = true;
		if ( $user ) {
			$table = self::table();
			$off   = max( 0, ( (int) $page - 1 ) * 100 );
			$rows = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE wp_user_id = %d ORDER BY id LIMIT 100 OFFSET %d", $table, (int) $user->ID, $off ), ARRAY_A );
			foreach ( $rows as $row ) {
				$data[] = array(
					'group_id'    => 'moonetix-offerwall',
					'group_label' => __( 'Offerwall rewards', 'moonetix-offerwall' ),
					'item_id'     => 'moonetix-offerwall-' . $row['id'],
					'data'        => array(
						array(
							'name'  => __( 'Date (UTC)', 'moonetix-offerwall' ),
							'value' => $row['created_at'],
						),
						array(
							'name'  => __( 'Offer', 'moonetix-offerwall' ),
							'value' => $row['offer_name'],
						),
						array(
							'name'  => __( 'Reward', 'moonetix-offerwall' ),
							'value' => $row['reward'],
						),
						array(
							'name'  => __( 'Result', 'moonetix-offerwall' ),
							'value' => $row['result'],
						),
						array(
							'name'  => __( 'Country', 'moonetix-offerwall' ),
							'value' => $row['country'],
						),
						array(
							'name'  => __( 'Device', 'moonetix-offerwall' ),
							'value' => $row['device'],
						),
						array(
							'name'  => __( 'Transaction ID', 'moonetix-offerwall' ),
							'value' => $row['transaction_id'],
						),
					),
				);
			}
			$done = count( $rows ) < 100;
			if ( 1 === (int) $page && '' !== get_user_meta( (int) $user->ID, self::balance_key(), true ) ) {
				$data[] = array(
					'group_id'    => 'moonetix-offerwall',
					'group_label' => __( 'Offerwall rewards', 'moonetix-offerwall' ),
					'item_id'     => 'moonetix-offerwall-balance',
					'data'        => array(
						array(
							'name'  => __( 'Balance', 'moonetix-offerwall' ),
							'value' => get_user_meta( (int) $user->ID, self::balance_key(), true ),
						),
					),
				);
			}
		}
		return array(
			'data' => $data,
			'done' => $done,
		);
	}

	/**
	 * Personal data eraser for Tools, Erase Personal Data. The rows stay, without the person, so a postback that
	 * arrives again is still recognized and not credited twice.
	 *
	 * @param string $email User email.
	 * @return array
	 */
	public static function erase( $email ) {
		global $wpdb;
		$user    = get_user_by( 'email', $email );
		$removed = false;
		if ( $user ) {
			$n = $wpdb->update(
				self::table(),
				array(
					'wp_user_id'       => 0,
					'external_user_id' => 'erased',
					'country'          => '',
					'device'           => '',
				),
				array( 'wp_user_id' => (int) $user->ID ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			delete_user_meta( (int) $user->ID, self::balance_key() );
			delete_user_meta( (int) $user->ID, self::toast_key() );
			$removed = $n > 0;
		}
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Adds the exporter to Tools, Export Personal Data.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['moonetix-offerwall'] = array(
			'exporter_friendly_name' => __( 'Moonetix Offerwall rewards', 'moonetix-offerwall' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Adds the eraser to Tools, Erase Personal Data.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['moonetix-offerwall'] = array(
			'eraser_friendly_name' => __( 'Moonetix Offerwall rewards', 'moonetix-offerwall' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}
}
