<?php
/**
 * Shortcodes and the block that show the offerwall and the balance.
 *
 * @package Moonetix_Offerwall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front end output.
 */
class Moonetix_Offerwall_Frontend {

	/**
	 * What extras() found for this request.
	 *
	 * @var array|null
	 */
	private static $extras = null;

	/**
	 * Registers the shortcodes, the block and the stylesheet.
	 */
	public static function init() {
		add_shortcode( 'moonetix_offerwall', array( __CLASS__, 'offerwall_shortcode' ) );
		add_shortcode( 'moonetix_balance', array( __CLASS__, 'balance_shortcode' ) );
		add_shortcode( 'moonetix_history', array( __CLASS__, 'history_shortcode' ) );
		wp_register_style( 'moonetix-offerwall', MOONETIX_OFFERWALL_URL . 'assets/css/offerwall.css', array(), MOONETIX_OFFERWALL_VERSION );
		wp_register_script( 'moonetix-offerwall', MOONETIX_OFFERWALL_URL . 'assets/js/offerwall.js', array(), MOONETIX_OFFERWALL_VERSION, array( 'in_footer' => true ) );
		register_block_type( MOONETIX_OFFERWALL_DIR . 'blocks/offerwall' );
		register_block_type( MOONETIX_OFFERWALL_DIR . 'blocks/balance' );
		register_block_type( MOONETIX_OFFERWALL_DIR . 'blocks/history' );
		if ( function_exists( 'register_block_pattern' ) ) {
			register_block_pattern_category( 'moonetix', array( 'label' => __( 'Moonetix', 'moonetix-offerwall' ) ) );
			register_block_pattern(
				'moonetix-offerwall/earn-page',
				array(
					'title'       => __( 'Earn page', 'moonetix-offerwall' ),
					'description' => __( 'Balance, offerwall, and reward history on one page.', 'moonetix-offerwall' ),
					'categories'  => array( 'moonetix' ),
					'content'     => self::earn_page_content(),
				)
			);
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'template_redirect', array( __CLASS__, 'no_cache' ) );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ) );
	}

	/**
	 * The signed offerwall link for a user ID.
	 *
	 * @param string $user_id User ID that Moonetix sends back in the postbacks.
	 * @return string
	 */
	public static function wall_url( $user_id ) {
		$args = array( 'user_id' => rawurlencode( $user_id ) );
		$salt = (string) Moonetix_Offerwall_Settings::get( 'link_salt' );
		if ( '' !== $salt ) {
			$args['hash'] = hash_hmac( 'sha256', $user_id, $salt );
		}
		return add_query_arg( $args, MOONETIX_OFFERWALL_WALL_BASE . rawurlencode( (string) Moonetix_Offerwall_Settings::get( 'app_id' ) ) );
	}

	/**
	 * The user ID sent to Moonetix for the current visitor.
	 *
	 * @return string Empty for visitors who are not logged in.
	 */
	public static function current_user_id() {
		$id = get_current_user_id();
		/**
		 * The user ID sent to Moonetix. It comes back in every postback, see the moonetix_offerwall_postback_user filter.
		 *
		 * @param string $user_id WordPress user ID as a string, empty when logged out.
		 * @param int    $wp_id   WordPress user ID.
		 */
		return (string) apply_filters( 'moonetix_offerwall_user_id', $id > 0 ? (string) $id : '', $id );
	}

	/**
	 * Whether the roles of a user are among the roles that may earn. Always true when every logged-in user may earn.
	 *
	 * @param int $wp_user_id User.
	 * @return bool
	 */
	public static function role_allowed( $wp_user_id ) {
		if ( 'roles' !== Moonetix_Offerwall_Settings::get( 'access' ) ) {
			return true;
		}
		$user = get_userdata( (int) $wp_user_id );
		$have = $user && isset( $user->roles ) ? (array) $user->roles : array();
		return (bool) array_intersect( $have, (array) Moonetix_Offerwall_Settings::get( 'roles' ) );
	}

	/**
	 * Whether a logged-in user sees the offerwall. Administrators always do, so they can test it.
	 *
	 * @param int $wp_user_id User.
	 * @return bool
	 */
	public static function can_earn( $wp_user_id ) {
		$wp_user_id = (int) $wp_user_id;
		$allowed    = $wp_user_id > 0 && ( self::role_allowed( $wp_user_id ) || user_can( $wp_user_id, 'manage_options' ) );
		/**
		 * Whether a user may see the offerwall and earn. Rewards for offers already completed are credited either way.
		 *
		 * @param bool $allowed    Result of the role setting.
		 * @param int  $wp_user_id WordPress user ID.
		 */
		return (bool) apply_filters( 'moonetix_offerwall_can_earn', $allowed, $wp_user_id );
	}

	/**
	 * An amount as the user sees it: money for TeraWallet, the balance name for the built-in balance, a plain
	 * number for the points plugins.
	 *
	 * @param float  $amount      Amount.
	 * @param string $destination builtin, mycred, gamipress, or terawallet. Empty for the current setting.
	 * @param bool   $sign        Show + for positive amounts.
	 * @return string
	 */
	public static function amount_text( $amount, $destination = '', $sign = false ) {
		$amount      = (float) $amount;
		$destination = '' !== $destination ? $destination : (string) Moonetix_Offerwall_Settings::get( 'destination' );
		$prefix      = $amount < 0 ? '-' : ( $sign ? '+' : '' );
		if ( 'terawallet' === $destination && function_exists( 'wc_price' ) ) {
			return $prefix . html_entity_decode( wp_strip_all_tags( wc_price( abs( $amount ) ) ), ENT_QUOTES, 'UTF-8' );
		}
		$decimals = abs( $amount - round( $amount ) ) > 0.00001 ? 2 : 0;
		$label    = 'builtin' === $destination ? ' ' . (string) Moonetix_Offerwall_Settings::get( 'balance_label' ) : '';
		return trim( $prefix . number_format_i18n( abs( $amount ), $decimals ) . $label );
	}

	/**
	 * Tells browsers, proxies, and page cache plugins not to store this page, because it carries the signed link
	 * or the balance of one user.
	 */
	public static function private_page() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- standard constant that page cache plugins read.
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	/**
	 * Logged-in pages with the floating button or a reward notice are never cached.
	 */
	public static function no_cache() {
		$x = self::extras();
		if ( $x['dialog'] || $x['toasts'] ) {
			self::private_page();
		}
	}

	/**
	 * Offerwall HTML.
	 *
	 * @param int $height Height in pixels, 0 for the setting.
	 * @return string
	 */
	public static function render( $height = 0 ) {
		wp_enqueue_style( 'moonetix-offerwall' );
		$height = $height > 0 ? min( 3000, max( 400, (int) $height ) ) : (int) Moonetix_Offerwall_Settings::get( 'height' );

		if ( ! Moonetix_Offerwall_Settings::is_connected() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="moonetix-offerwall-note">' . esc_html__( 'Moonetix Offerwall: add your App ID under Settings, Moonetix Offerwall. Only administrators see this message.', 'moonetix-offerwall' ) . '</div>';
			}
			return '';
		}

		$user_id = self::current_user_id();
		if ( '' !== $user_id ) {
			self::private_page();
		}
		if ( '' === $user_id ) {
			return '<div class="moonetix-offerwall-note"><p>' . esc_html( (string) Moonetix_Offerwall_Settings::get( 'guest_message' ) ) . '</p><p><a class="moonetix-offerwall-login" href="' . esc_url( wp_login_url( (string) get_permalink() ) ) . '">' . esc_html__( 'Log in', 'moonetix-offerwall' ) . '</a></p></div>';
		}

		$wp_id = get_current_user_id();
		if ( ! self::can_earn( $wp_id ) ) {
			return '<div class="moonetix-offerwall-note"><p>' . esc_html( (string) Moonetix_Offerwall_Settings::get( 'denied_message' ) ) . '</p></div>';
		}
		$admin_note = '';
		if ( ! self::role_allowed( $wp_id ) && user_can( $wp_id, 'manage_options' ) ) {
			$admin_note = '<div class="moonetix-offerwall-note is-admin"><p>' . esc_html__( 'Only administrators see this note: your role is not one of the roles that can earn, but administrators always see the offerwall so they can test it.', 'moonetix-offerwall' ) . '</p></div>';
		}

		return $admin_note . '<div class="moonetix-offerwall" style="' . esc_attr( 'height:' . $height . 'px' ) . '"><iframe src="' . esc_url( self::wall_url( $user_id ) ) . '" title="' . esc_attr__( 'Offerwall', 'moonetix-offerwall' ) . '" loading="lazy" allow="clipboard-write" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>' . self::credit();
	}

	/**
	 * The "Rewards by Moonetix" link under the offerwall. Off unless the site owner turns it on.
	 *
	 * @return string
	 */
	public static function credit() {
		if ( ! Moonetix_Offerwall_Settings::get( 'credit_link' ) ) {
			return '';
		}
		$code = (string) Moonetix_Offerwall_Settings::get( 'ref_code' );
		$url  = '' !== $code ? 'https://moonetix.com/r/' . rawurlencode( $code ) : 'https://moonetix.com/';
		return '<p class="moonetix-offerwall-credit"><a href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener">' . esc_html__( 'Rewards by Moonetix', 'moonetix-offerwall' ) . '</a></p>';
	}

	/**
	 * Tags and attributes the offerwall output may contain, for wp_kses().
	 *
	 * @return array
	 */
	public static function allowed_html() {
		return array(
			'div'    => array(
				'class' => true,
				'style' => true,
			),
			'p'      => array(
				'class' => true,
			),
			'a'      => array(
				'class'  => true,
				'href'   => true,
				'target' => true,
				'rel'    => true,
			),
			'iframe' => array(
				'src'            => true,
				'title'          => true,
				'loading'        => true,
				'allow'          => true,
				'referrerpolicy' => true,
			),
		);
	}

	/**
	 * [moonetix_offerwall height="900"]
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function offerwall_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'height' => 0 ), $atts, 'moonetix_offerwall' );
		return self::render( absint( $atts['height'] ) );
	}

	/**
	 * [moonetix_balance] shows the balance of the logged-in user.
	 *
	 * @return string
	 */
	public static function balance_shortcode() {
		$id = get_current_user_id();
		if ( $id <= 0 ) {
			return '';
		}
		self::private_page();
		return '<span class="moonetix-offerwall-balance">' . esc_html( self::balance_text( $id ) ) . '</span>';
	}

	/**
	 * [moonetix_history limit="20"] lists the rewards and reversals of the logged-in user.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function history_shortcode( $atts ) {
		$id = get_current_user_id();
		if ( $id <= 0 ) {
			return '';
		}
		wp_enqueue_style( 'moonetix-offerwall' );
		self::private_page();
		$atts = shortcode_atts( array( 'limit' => 20 ), $atts, 'moonetix_history' );
		$rows = Moonetix_Offerwall_Ledger::recent( min( 100, max( 1, absint( $atts['limit'] ) ) ), $id );
		if ( ! $rows ) {
			return '<p class="moonetix-offerwall-history-empty">' . esc_html__( 'No rewards yet. Complete an offer and it shows up here.', 'moonetix-offerwall' ) . '</p>';
		}
		$html = '<table class="moonetix-offerwall-history"><thead><tr><th>' . esc_html__( 'Date', 'moonetix-offerwall' ) . '</th><th>' . esc_html__( 'Offer', 'moonetix-offerwall' ) . '</th><th>' . esc_html__( 'Reward', 'moonetix-offerwall' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$dest     = '' !== (string) $row['destination'] ? (string) $row['destination'] : 'builtin';
			$amount   = 'terawallet' === $dest ? (float) $row['moved'] : (float) $row['reward'];
			$reversed = 'reversed' === $row['result'];
			$text     = self::amount_text( $amount, $dest, true );
			if ( 'adjusted' === $row['result'] ) {
				$row['offer_name'] = '' !== $row['offer_name'] ? $row['offer_name'] : __( 'Balance adjustment', 'moonetix-offerwall' );
			}
			$html    .= '<tr' . ( $reversed ? ' class="is-reversed"' : '' ) . '><td>' . esc_html( get_date_from_gmt( $row['created_at'], get_option( 'date_format' ) ) ) . '</td><td>' . esc_html( '' !== $row['offer_name'] ? $row['offer_name'] : __( 'Offer', 'moonetix-offerwall' ) ) . ( $reversed ? ' <small>' . esc_html__( '(reversed)', 'moonetix-offerwall' ) . '</small>' : '' ) . '</td><td>' . ( $reversed ? '<s>' . esc_html( $text ) . '</s>' : esc_html( $text ) ) . '</td></tr>';
		}
		return $html . '</tbody></table>';
	}

	/**
	 * What the current visitor gets on every page: the floating button, the offerwall window for menu links,
	 * and waiting reward notices.
	 *
	 * @return array{float:bool,dialog:bool,toasts:array}
	 */
	private static function extras() {
		if ( null !== self::$extras ) {
			return self::$extras;
		}
		$memo = array(
			'float'  => false,
			'dialog' => false,
			'toasts' => array(),
		);
		$id   = get_current_user_id();
		if ( $id > 0 && ! is_admin() && Moonetix_Offerwall_Settings::is_connected() && '' !== self::current_user_id() ) {
			if ( self::can_earn( $id ) ) {
				$memo['float']  = Moonetix_Offerwall_Settings::get( 'floating' ) && self::float_here();
				$memo['dialog'] = $memo['float'] || (bool) Moonetix_Offerwall_Settings::get( 'popup_links' );
			}
			if ( Moonetix_Offerwall_Settings::get( 'toast' ) ) {
				$memo['toasts'] = Moonetix_Offerwall_Ledger::take_toasts( $id );
			}
		}
		self::$extras = $memo;
		return $memo;
	}

	/**
	 * Whether the floating button belongs on the current page. It stays away from the pages chosen in the
	 * settings, from the cart and checkout when that is set, and from pages that already show the offerwall.
	 *
	 * @return bool
	 */
	private static function float_here() {
		$show = true;
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			if ( in_array( (int) $post->ID, array_map( 'intval', (array) Moonetix_Offerwall_Settings::get( 'floating_hide' ) ), true ) ) {
				$show = false;
			} elseif ( has_shortcode( (string) $post->post_content, 'moonetix_offerwall' ) || ( function_exists( 'has_block' ) && has_block( 'moonetix-offerwall/offerwall', $post ) ) ) {
				$show = false;
			}
		}
		if ( $show && Moonetix_Offerwall_Settings::get( 'floating_shop' ) && Moonetix_Offerwall_Settings::shop_active() ) {
			if ( is_cart() || is_checkout() || ( function_exists( 'is_account_page' ) && is_account_page() ) ) {
				$show = false;
			}
		}
		/**
		 * Whether the floating button is shown on the current page.
		 *
		 * @param bool $show Result of the visibility settings.
		 */
		return (bool) apply_filters( 'moonetix_offerwall_show_floating', $show );
	}

	/**
	 * Loads the stylesheet and the script when the button or a notice is shown.
	 */
	public static function enqueue() {
		$x = self::extras();
		if ( $x['dialog'] || $x['toasts'] ) {
			wp_enqueue_style( 'moonetix-offerwall' );
			wp_enqueue_script( 'moonetix-offerwall' );
		}
	}

	/**
	 * Text color that reads well on the accent color.
	 *
	 * @param string $hex Color like #0E7A48.
	 * @return string
	 */
	public static function text_on( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( 6 !== strlen( $hex ) ) {
			return '#FFFFFF';
		}
		$lum = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$c     = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$lum[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		}
		$l = 0.2126 * $lum[0] + 0.7152 * $lum[1] + 0.0722 * $lum[2];
		return ( 1.05 / ( $l + 0.05 ) ) >= ( ( $l + 0.05 ) / 0.05 ) ? '#FFFFFF' : '#0A0C0B';
	}

	/**
	 * The balance as shown to the user, with the balance name for the built-in balance.
	 *
	 * @param int $id User.
	 * @return string
	 */
	public static function balance_text( $id ) {
		return self::amount_text( Moonetix_Offerwall_Ledger::balance( $id ) );
	}

	/**
	 * Floating button with its offerwall dialog, and the reward notices.
	 */
	public static function footer() {
		$x = self::extras();
		if ( ! $x['dialog'] && ! $x['toasts'] ) {
			return;
		}
		$accent = (string) Moonetix_Offerwall_Settings::get( 'accent' );
		$style  = '--moonetix-ow-accent:' . $accent . ';--moonetix-ow-on-accent:' . self::text_on( $accent );
		echo '<div class="moonetix-ow" style="' . esc_attr( $style ) . '">';
		if ( $x['float'] ) {
			$side   = 'left' === Moonetix_Offerwall_Settings::get( 'floating_side' ) ? 'left' : 'right';
			$device = (string) Moonetix_Offerwall_Settings::get( 'floating_device' );
			$class  = 'moonetix-ow-float is-' . $side . ( in_array( $device, array( 'mobile', 'desktop' ), true ) ? ' only-' . $device : '' );
			echo '<button type="button" class="' . esc_attr( $class ) . '" data-moonetix-ow-open aria-haspopup="dialog">'
				. '<span class="moonetix-ow-float-t">' . esc_html( (string) Moonetix_Offerwall_Settings::get( 'floating_text' ) ) . '</span>'
				. '<span class="moonetix-ow-float-b">' . esc_html( self::balance_text( get_current_user_id() ) ) . '</span></button>';
		}
		if ( $x['dialog'] ) {
			echo '<dialog class="moonetix-ow-dialog" aria-label="' . esc_attr__( 'Offerwall', 'moonetix-offerwall' ) . '">'
				. '<div class="moonetix-ow-bar"><strong>' . esc_html( (string) Moonetix_Offerwall_Settings::get( 'floating_text' ) ) . '</strong>'
				. '<button type="button" class="moonetix-ow-close" data-moonetix-ow-close aria-label="' . esc_attr__( 'Close', 'moonetix-offerwall' ) . '">&times;</button></div>'
				. '<iframe data-src="' . esc_url( self::wall_url( self::current_user_id() ) ) . '" title="' . esc_attr__( 'Offerwall', 'moonetix-offerwall' ) . '" allow="clipboard-write" referrerpolicy="strict-origin-when-cross-origin"></iframe></dialog>';
		}
		if ( $x['toasts'] ) {
			echo '<div class="moonetix-ow-toasts is-' . esc_attr( 'left' === Moonetix_Offerwall_Settings::get( 'floating_side' ) ? 'left' : 'right' ) . '" role="status" aria-live="polite">';
			foreach ( array_slice( $x['toasts'], -3 ) as $t ) {
				$dest = isset( $t['dest'] ) ? (string) $t['dest'] : '';
				echo '<div class="moonetix-ow-toast"><strong>' . esc_html( self::amount_text( (float) $t['reward'], $dest, true ) ) . '</strong>'
					. ( '' !== (string) $t['offer'] ? '<span>' . esc_html( (string) $t['offer'] ) . '</span>' : '' ) . '</div>';
			}
			echo '</div>';
		}
		echo '</div>';
	}

	/**
	 * Block markup of a ready-made earn page.
	 *
	 * @return string
	 */
	public static function earn_page_content() {
		return '<!-- wp:heading --><h2 class="wp-block-heading">' . esc_html__( 'Earn rewards', 'moonetix-offerwall' ) . '</h2><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p>' . esc_html__( 'Complete an offer below and your reward lands in your account automatically.', 'moonetix-offerwall' ) . '</p><!-- /wp:paragraph -->'
			. '<!-- wp:moonetix-offerwall/balance /-->'
			. '<!-- wp:moonetix-offerwall/offerwall /-->'
			. '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">' . esc_html__( 'Your rewards', 'moonetix-offerwall' ) . '</h3><!-- /wp:heading -->'
			. '<!-- wp:moonetix-offerwall/history /-->';
	}
}
