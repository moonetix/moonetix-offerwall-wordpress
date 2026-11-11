<?php
/**
 * REST endpoint that receives the Moonetix postbacks.
 *
 * Moonetix calls it with a GET request for every reward (status 1) and every reversal (status 2). The hash is
 * HMAC-SHA256 of "transaction_id:user_id:reward:status", keyed with the postback secret key of the offerwall.
 *
 * @package Moonetix_Offerwall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Postback route and its checks.
 */
class Moonetix_Offerwall_Postback {

	const NAMESPACE_V1 = 'moonetix-offerwall/v1';

	/**
	 * Registers the route.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/postback',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle' ),
				'permission_callback' => array( __CLASS__, 'verify' ),
				'args'                => array(
					'transaction_id' => array(
						'type'     => 'string',
						'required' => true,
					),
					'user_id'        => array(
						'type'     => 'string',
						'required' => true,
					),
					'reward'         => array(
						'type'     => 'string',
						'required' => true,
					),
					'status'         => array(
						'type'     => 'string',
						'required' => true,
					),
					'hash'           => array(
						'type'     => 'string',
						'required' => true,
					),
					'offer_name'     => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'offer_id'       => array(
						'type'     => 'string',
						'required' => false,
					),
					'payout'         => array(
						'type'     => 'string',
						'required' => false,
					),
					'country'        => array(
						'type'     => 'string',
						'required' => false,
					),
					'device'         => array(
						'type'     => 'string',
						'required' => false,
					),
				),
			)
		);
	}

	/**
	 * The postback URL to paste into the Moonetix dashboard.
	 *
	 * @return string
	 */
	public static function url_template() {
		return add_query_arg(
			array(
				'user_id'        => '{user_id}',
				'transaction_id' => '{transaction_id}',
				'reward'         => '{reward}',
				'status'         => '{status}',
				'hash'           => '{hash}',
				'offer_name'     => '{offer_name}',
				'offer_id'       => '{offer_id}',
				'payout'         => '{payout}',
				'country'        => '{country}',
				'device'         => '{device}',
			),
			rest_url( self::NAMESPACE_V1 . '/postback' )
		);
	}

	/**
	 * The signed values, checked for shape. Null when one is missing or malformed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|null
	 */
	private static function values( $request ) {
		$v = array();
		foreach ( array( 'transaction_id', 'user_id', 'reward', 'status', 'hash' ) as $key ) {
			$raw = $request->get_param( $key );
			if ( ! is_string( $raw ) ) {
				return null;
			}
			$v[ $key ] = $raw;
		}
		$v['hash'] = strtolower( $v['hash'] );
		$ok = preg_match( '/^[A-Za-z0-9_.:\-]{1,191}$/D', $v['transaction_id'] )
			&& preg_match( '/^[\x21-\x7E]{1,191}$/D', $v['user_id'] )
			&& preg_match( '/^\d{1,12}(\.\d{1,5})?$/D', $v['reward'] )
			&& in_array( $v['status'], array( '1', '2' ), true )
			&& preg_match( '/^[a-f0-9]{64}$/D', $v['hash'] );
		return $ok ? $v : null;
	}

	/**
	 * Permission check: only a correctly signed postback gets through.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function verify( $request ) {
		$key = (string) Moonetix_Offerwall_Settings::get( 'postback_key' );
		if ( '' === $key ) {
			return new WP_Error( 'moonetix_offerwall_not_configured', __( 'The postback secret key is not set in the plugin settings.', 'moonetix-offerwall' ), array( 'status' => 503 ) );
		}
		$v = self::values( $request );
		if ( null === $v ) {
			return new WP_Error( 'moonetix_offerwall_bad_request', __( 'Missing or malformed postback parameters.', 'moonetix-offerwall' ), array( 'status' => 400 ) );
		}
		$expected = hash_hmac( 'sha256', $v['transaction_id'] . ':' . $v['user_id'] . ':' . $v['reward'] . ':' . $v['status'], $key );
		if ( ! hash_equals( $expected, $v['hash'] ) ) {
			return new WP_Error( 'moonetix_offerwall_bad_hash', __( 'The hash does not match. Check the postback secret key in the plugin settings.', 'moonetix-offerwall' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Optional details for the log. They are not part of the hash, so they are only kept when they look right.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function details( $request ) {
		$offer_id = (string) $request->get_param( 'offer_id' );
		$payout   = (string) $request->get_param( 'payout' );
		$country  = strtoupper( (string) $request->get_param( 'country' ) );
		$device   = strtolower( (string) $request->get_param( 'device' ) );
		return array(
			'offer_id' => preg_match( '/^[A-Za-z0-9_\-]{1,32}$/D', $offer_id ) ? $offer_id : '',
			'payout'   => preg_match( '/^\d{1,9}(\.\d{1,5})?$/D', $payout ) ? (float) $payout : 0,
			'country'  => preg_match( '/^[A-Z]{2}$/D', $country ) ? $country : '',
			'device'   => in_array( $device, array( 'desktop', 'android', 'ios', 'mobile', 'tablet' ), true ) ? $device : '',
		);
	}

	/**
	 * Credits or reverses the reward.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle( $request ) {
		$v          = self::values( $request );
		$offer_name = mb_substr( (string) $request->get_param( 'offer_name' ), 0, 191 );
		$wp_user_id = preg_match( '/^[1-9][0-9]{0,18}$/D', $v['user_id'] ) ? (int) $v['user_id'] : 0;

		/**
		 * Maps the user ID sent by Moonetix to a WordPress user ID. By default it is the WordPress user ID itself.
		 *
		 * @param int    $wp_user_id WordPress user ID, 0 when unknown.
		 * @param string $user_id    User ID as sent by Moonetix.
		 */
		$wp_user_id = (int) apply_filters( 'moonetix_offerwall_postback_user', $wp_user_id, $v['user_id'] );
		if ( $wp_user_id > 0 && ! get_userdata( $wp_user_id ) ) {
			$wp_user_id = 0;
		}

		$r = Moonetix_Offerwall_Ledger::handle( $v['transaction_id'], $v['user_id'], $wp_user_id, (float) $v['reward'], (int) $v['status'], $offer_name, self::details( $request ) );

		/**
		 * Fires after a postback was handled.
		 *
		 * @param string $result     credited, reversed, duplicate, ignored, unknown_user or destination_unavailable.
		 * @param int    $wp_user_id WordPress user ID, 0 when unknown.
		 * @param array  $values     The signed postback values.
		 */
		do_action( 'moonetix_offerwall_postback', $r['result'], $wp_user_id, $v );

		$response = new WP_REST_Response(
			array(
				'ok'     => 200 === $r['http'],
				'result' => $r['result'],
			),
			$r['http']
		);
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'X-Robots-Tag', 'noindex' );
		return $response;
	}
}
