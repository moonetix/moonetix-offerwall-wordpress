<?php
/**
 * Plugin settings: one option array with the connection keys, the reward destination and the display options.
 *
 * @package Moonetix_Offerwall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and sanitizes the plugin settings.
 */
class Moonetix_Offerwall_Settings {

	const OPTION = 'moonetix_offerwall_settings';

	/**
	 * Default values of every setting.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'app_id'          => '',
			'link_salt'       => '',
			'postback_key'    => '',
			'destination'     => 'builtin',
			'mycred_type'     => 'mycred_default',
			'gamipress_type'  => '',
			'balance_label'   => __( 'Points', 'moonetix-offerwall' ),
			'height'          => 900,
			'guest_message'   => __( 'Log in to see the offers and earn rewards.', 'moonetix-offerwall' ),
			'delete_data'     => 0,
			'floating'        => 0,
			'floating_text'   => __( 'Earn', 'moonetix-offerwall' ),
			'floating_side'   => 'right',
			'accent'          => '#0E7A48',
			'toast'           => 1,
			'credit_link'     => 0,
			'ref_code'        => '',
			'wallet_rate'     => 1,
			'access'          => 'all',
			'roles'           => array(),
			'denied_message'  => __( 'The offers are available to members only.', 'moonetix-offerwall' ),
			'floating_device' => 'all',
			'floating_hide'   => array(),
			'floating_shop'   => 1,
			'popup_links'     => 0,
		);
	}

	/**
	 * All settings, with defaults for missing keys.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		$all   = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		foreach ( self::CONSTANTS as $key => $constant ) {
			if ( self::locked( $key ) ) {
				$all[ $key ] = (string) constant( $constant );
			}
		}
		return $all;
	}

	/**
	 * Keys that can be set in wp-config.php instead of the database.
	 */
	const CONSTANTS = array(
		'link_salt'    => 'MOONETIX_OFFERWALL_LINK_SALT',
		'postback_key' => 'MOONETIX_OFFERWALL_POSTBACK_KEY',
	);

	/**
	 * Whether a key comes from a wp-config.php constant.
	 *
	 * @param string $key link_salt or postback_key.
	 * @return bool
	 */
	public static function locked( $key ) {
		return isset( self::CONSTANTS[ $key ] ) && defined( self::CONSTANTS[ $key ] ) && '' !== (string) constant( self::CONSTANTS[ $key ] );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Whether the App ID is set.
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return '' !== self::get( 'app_id' );
	}

	/**
	 * Reward destinations that can be used on this site.
	 *
	 * @return array Key => label.
	 */
	public static function destinations() {
		$out = array( 'builtin' => __( 'Built-in balance (this plugin)', 'moonetix-offerwall' ) );
		if ( function_exists( 'mycred_add' ) ) {
			$out['mycred'] = 'myCRED';
		}
		if ( function_exists( 'gamipress_award_points_to_user' ) ) {
			$out['gamipress'] = 'GamiPress';
		}
		if ( self::wallet_active() ) {
			$out['terawallet'] = __( 'TeraWallet (WooCommerce wallet)', 'moonetix-offerwall' );
		}
		return $out;
	}

	/**
	 * Whether TeraWallet, the WooCommerce wallet plugin, is active and usable.
	 *
	 * @return bool
	 */
	public static function wallet_active() {
		if ( ! function_exists( 'woo_wallet' ) ) {
			return false;
		}
		$plugin = woo_wallet();
		return is_object( $plugin ) && isset( $plugin->wallet ) && is_object( $plugin->wallet ) && method_exists( $plugin->wallet, 'credit' ) && method_exists( $plugin->wallet, 'debit' );
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function shop_active() {
		return function_exists( 'is_cart' ) && function_exists( 'is_checkout' );
	}

	/**
	 * The user roles of this site.
	 *
	 * @return array Role key => translated name.
	 */
	public static function role_names() {
		$names = function_exists( 'wp_roles' ) ? (array) wp_roles()->get_names() : array();
		$out   = array();
		foreach ( $names as $key => $name ) {
			$out[ (string) $key ] = function_exists( 'translate_user_role' ) ? translate_user_role( $name ) : (string) $name;
		}
		return $out;
	}

	/**
	 * Sanitize callback of register_setting().
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$old = self::all();
		$in  = is_array( $input ) ? $input : array();
		$out = $old;

		$app_id = isset( $in['app_id'] ) ? trim( sanitize_text_field( wp_unslash( $in['app_id'] ) ) ) : $old['app_id'];
		// A pasted offerwall link works too: the App ID is the first part of its path.
		if ( preg_match( '#^(?:https?://)?wall\.moonetix\.com/([A-Za-z0-9]{8,64})(?:[/?\#].*)?$#i', $app_id, $m ) ) {
			$app_id = $m[1];
		}
		$app_id = strtolower( $app_id );
		if ( '' === $app_id || preg_match( '/^[a-z0-9]{8,64}$/D', $app_id ) ) {
			$out['app_id'] = $app_id;
		} else {
			add_settings_error( self::OPTION, 'app_id', __( 'The App ID can only contain letters and numbers. Copy it from your Moonetix dashboard.', 'moonetix-offerwall' ) );
		}

		$saved = get_option( self::OPTION, array() );
		foreach ( array( 'link_salt', 'postback_key' ) as $secret ) {
			$out[ $secret ] = is_array( $saved ) && isset( $saved[ $secret ] ) ? (string) $saved[ $secret ] : '';
			if ( self::locked( $secret ) ) {
				continue;
			}
			if ( ! empty( $in[ $secret . '_clear' ] ) ) {
				$out[ $secret ] = '';
				continue;
			}
			// Keys are only checked, never changed: printable ASCII without spaces, or the value is refused.
			$value = isset( $in[ $secret ] ) && is_string( $in[ $secret ] ) ? trim( wp_unslash( $in[ $secret ] ) ) : '';
			if ( '' === $value ) {
				continue;
			}
			if ( preg_match( '/^[\x21-\x7E]{8,128}$/D', $value ) ) {
				$out[ $secret ] = $value;
			} else {
				add_settings_error( self::OPTION, $secret, __( 'A key you entered has spaces or unusual characters. Copy it again from your Moonetix dashboard.', 'moonetix-offerwall' ) );
			}
		}

		$destination        = isset( $in['destination'] ) ? sanitize_key( $in['destination'] ) : $old['destination'];
		$out['destination'] = in_array( $destination, array( 'builtin', 'mycred', 'gamipress', 'terawallet' ), true ) ? $destination : 'builtin';

		if ( isset( $in['mycred_type'] ) ) {
			$out['mycred_type'] = sanitize_key( $in['mycred_type'] );
		}
		if ( isset( $in['gamipress_type'] ) ) {
			$out['gamipress_type'] = sanitize_key( $in['gamipress_type'] );
		}
		if ( isset( $in['balance_label'] ) ) {
			$label                = sanitize_text_field( wp_unslash( $in['balance_label'] ) );
			$out['balance_label'] = '' !== $label ? mb_substr( $label, 0, 40 ) : self::defaults()['balance_label'];
		}
		if ( isset( $in['height'] ) ) {
			$out['height'] = min( 3000, max( 400, absint( $in['height'] ) ) );
		}
		if ( isset( $in['guest_message'] ) ) {
			$out['guest_message'] = mb_substr( sanitize_text_field( wp_unslash( $in['guest_message'] ) ), 0, 300 );
		}
		$out['delete_data'] = empty( $in['delete_data'] ) ? 0 : 1;
		$out['floating']    = empty( $in['floating'] ) ? 0 : 1;
		$out['toast']       = empty( $in['toast'] ) ? 0 : 1;
		$out['credit_link'] = empty( $in['credit_link'] ) ? 0 : 1;
		if ( isset( $in['ref_code'] ) ) {
			$code = trim( sanitize_text_field( wp_unslash( $in['ref_code'] ) ) );
			// The Referrals page shows the whole link, so a pasted link is accepted and its code kept.
			if ( preg_match( '#^(?:https?://)?(?:www\.)?moonetix\.com/r/([A-Za-z0-9_]{3,20})/?$#i', $code, $m ) ) {
				$code = $m[1];
			}
			if ( '' === $code || preg_match( '/^[A-Za-z0-9_]{3,20}$/D', $code ) ) {
				$out['ref_code'] = $code;
			} else {
				add_settings_error( self::OPTION, 'ref_code', __( 'A referral code has 3 to 20 letters, numbers, or underscores. Copy it from the Referrals page of your Moonetix dashboard.', 'moonetix-offerwall' ) );
			}
		}
		if ( isset( $in['floating_text'] ) ) {
			$text                 = sanitize_text_field( wp_unslash( $in['floating_text'] ) );
			$out['floating_text'] = '' !== $text ? mb_substr( $text, 0, 24 ) : self::defaults()['floating_text'];
		}
		if ( isset( $in['floating_side'] ) ) {
			$out['floating_side'] = 'left' === $in['floating_side'] ? 'left' : 'right';
		}
		if ( isset( $in['accent'] ) ) {
			$color         = sanitize_hex_color( wp_unslash( $in['accent'] ) );
			$out['accent'] = $color ? strtoupper( $color ) : self::defaults()['accent'];
		}

		if ( isset( $in['wallet_rate'] ) ) {
			$rate = str_replace( array( ' ', ',' ), array( '', '.' ), (string) wp_unslash( $in['wallet_rate'] ) );
			$rate = is_numeric( $rate ) ? round( (float) $rate, 6 ) : 0.0;
			if ( $rate > 0 && $rate <= 1000000000 ) {
				$out['wallet_rate'] = $rate;
			} else {
				add_settings_error( self::OPTION, 'wallet_rate', __( 'Enter how many points equal 1 unit of your store currency, a number above 0. For example 1000.', 'moonetix-offerwall' ) );
			}
		}

		if ( isset( $in['access'] ) ) {
			$known = array_keys( self::role_names() );
			$roles = array();
			if ( isset( $in['roles'] ) && is_array( $in['roles'] ) ) {
				foreach ( wp_unslash( $in['roles'] ) as $role ) {
					$role = (string) $role;
					if ( $known ? in_array( $role, $known, true ) : '' !== sanitize_key( $role ) ) {
						$roles[] = $known ? $role : sanitize_key( $role );
					}
				}
			}
			$out['roles']  = array_values( array_unique( $roles ) );
			$out['access'] = 'roles' === $in['access'] ? 'roles' : 'all';
			if ( 'roles' === $out['access'] && ! $out['roles'] ) {
				$out['access'] = 'all';
				add_settings_error( self::OPTION, 'roles', __( 'Choose at least one role, or let all logged-in users earn. For now, all logged-in users can earn.', 'moonetix-offerwall' ) );
			}
		}
		if ( isset( $in['denied_message'] ) ) {
			$text                  = sanitize_text_field( wp_unslash( $in['denied_message'] ) );
			$out['denied_message'] = '' !== $text ? mb_substr( $text, 0, 300 ) : self::defaults()['denied_message'];
		}

		if ( isset( $in['floating_device'] ) ) {
			$out['floating_device'] = in_array( $in['floating_device'], array( 'all', 'mobile', 'desktop' ), true ) ? $in['floating_device'] : 'all';
		}
		if ( isset( $in['floating_hide_sent'] ) ) {
			$pages = array();
			if ( isset( $in['floating_hide'] ) && is_array( $in['floating_hide'] ) ) {
				foreach ( $in['floating_hide'] as $page_id ) {
					$page_id = is_numeric( $page_id ) ? (int) $page_id : 0;
					if ( $page_id > 0 ) {
						$pages[] = $page_id;
					}
				}
			}
			$out['floating_hide'] = array_slice( array_values( array_unique( $pages ) ), 0, 500 );
		}
		if ( isset( $in['floating_shop_sent'] ) ) {
			$out['floating_shop'] = empty( $in['floating_shop'] ) ? 0 : 1;
		}
		$out['popup_links'] = empty( $in['popup_links'] ) ? 0 : 1;

		return $out;
	}
}
