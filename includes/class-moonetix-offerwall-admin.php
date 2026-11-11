<?php
/**
 * Admin screens: Settings, Moonetix Offerwall (three tabs), and the balance box on user profiles.
 *
 * @package Moonetix_Offerwall
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screens.
 */
class Moonetix_Offerwall_Admin {

	const PAGE = 'moonetix-offerwall';

	const PER_PAGE = 25;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( MOONETIX_OFFERWALL_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'profile_box' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile_box' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'profile_save' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'profile_save' ) );
		add_action( 'admin_notices', array( __CLASS__, 'welcome_notice' ) );
		add_action( 'admin_post_moonetix_offerwall_welcome', array( __CLASS__, 'dismiss_welcome' ) );
		add_action( 'admin_post_moonetix_offerwall_create_page', array( __CLASS__, 'create_page' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
		add_filter( 'site_status_tests', array( __CLASS__, 'site_health_tests' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard_widget' ) );
	}

	/**
	 * Remembers on activation to show the welcome notice once.
	 */
	public static function activate() {
		update_option( 'moonetix_offerwall_welcome', 1, false );
	}

	/**
	 * One-time notice after activation, until the owner opens the settings or closes it.
	 */
	public static function welcome_notice() {
		if ( ! current_user_can( 'manage_options' ) || ! get_option( 'moonetix_offerwall_welcome' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'settings_page_' . self::PAGE === $screen->id ) {
			delete_option( 'moonetix_offerwall_welcome' );
			return;
		}
		$close = wp_nonce_url( admin_url( 'admin-post.php?action=moonetix_offerwall_welcome' ), 'moonetix_offerwall_welcome' );
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Moonetix Offerwall is active.', 'moonetix-offerwall' ) . '</strong> '
			. esc_html__( 'Connect your offerwall in three short steps, then add the Earn page.', 'moonetix-offerwall' ) . '</p><p>'
			. '<a class="button button-primary" href="' . esc_url( self::url( 'settings' ) ) . '">' . esc_html__( 'Set it up', 'moonetix-offerwall' ) . '</a> '
			. '<a class="button" href="' . esc_url( $close ) . '">' . esc_html__( 'Not now', 'moonetix-offerwall' ) . '</a></p></div>';
	}

	/**
	 * Closes the welcome notice.
	 */
	public static function dismiss_welcome() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'moonetix-offerwall' ), 403 );
		}
		check_admin_referer( 'moonetix_offerwall_welcome' );
		delete_option( 'moonetix_offerwall_welcome' );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Creates a draft Earn page with the balance, the offerwall, and the reward history.
	 */
	public static function create_page() {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'publish_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'moonetix-offerwall' ), 403 );
		}
		check_admin_referer( 'moonetix_offerwall_create_page' );
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_title'   => __( 'Earn', 'moonetix-offerwall' ),
				'post_content' => Moonetix_Offerwall_Frontend::earn_page_content(),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}
		update_option( 'moonetix_offerwall_page', (int) $id, false );
		wp_safe_redirect( self::url( 'settings', array( 'moonetix_page' => (int) $id ) ) );
		exit;
	}

	/**
	 * Docs, support, and dashboard links on the Plugins screen.
	 *
	 * @param array  $links Links under the description.
	 * @param string $file  Plugin file.
	 * @return array
	 */
	public static function row_meta( $links, $file ) {
		if ( plugin_basename( MOONETIX_OFFERWALL_FILE ) === $file ) {
			$links[] = '<a href="https://docs.moonetix.com/wordpress" target="_blank" rel="noopener">' . esc_html__( 'Docs', 'moonetix-offerwall' ) . '</a>';
			$links[] = '<a href="https://moonetix.com/contact" target="_blank" rel="noopener">' . esc_html__( 'Support', 'moonetix-offerwall' ) . '</a>';
			$links[] = '<a href="https://moonetix.com/" target="_blank" rel="noopener">' . esc_html__( 'Moonetix dashboard', 'moonetix-offerwall' ) . '</a>';
		}
		return $links;
	}

	/**
	 * Adds the setup check to Tools, Site Health.
	 *
	 * @param array $tests Tests.
	 * @return array
	 */
	public static function site_health_tests( $tests ) {
		$tests['direct']['moonetix_offerwall'] = array(
			'label' => __( 'Moonetix Offerwall setup', 'moonetix-offerwall' ),
			'test'  => array( __CLASS__, 'site_health_result' ),
		);
		return $tests;
	}

	/**
	 * Result of the Site Health test.
	 *
	 * @return array
	 */
	public static function site_health_result() {
		$checks = self::checks( false );
		$bad    = array_filter(
			$checks,
			static function ( $c ) {
				return 'bad' === $c[0];
			}
		);
		$warn   = array_filter(
			$checks,
			static function ( $c ) {
				return 'warn' === $c[0];
			}
		);
		$items  = '';
		foreach ( array_merge( $bad, $warn ) as $c ) {
			$items .= '<li>' . esc_html( $c[1] . ( '' !== $c[2] ? ': ' . $c[2] : '' ) ) . '</li>';
		}
		return array(
			'label'       => $bad ? __( 'Moonetix Offerwall is not fully set up', 'moonetix-offerwall' ) : ( $warn ? __( 'Moonetix Offerwall needs a check', 'moonetix-offerwall' ) : __( 'Moonetix Offerwall is set up', 'moonetix-offerwall' ) ),
			'status'      => $bad ? 'critical' : ( $warn ? 'recommended' : 'good' ),
			'badge'       => array(
				'label' => __( 'Moonetix Offerwall', 'moonetix-offerwall' ),
				'color' => 'green',
			),
			'description' => '<p>' . esc_html__( 'Your users only get their rewards when the offerwall is connected and postbacks reach this site.', 'moonetix-offerwall' ) . '</p>' . ( '' !== $items ? '<ul>' . $items . '</ul>' : '' ),
			'actions'     => '<p><a href="' . esc_url( self::url( 'settings' ) ) . '">' . esc_html__( 'Open the Moonetix Offerwall settings', 'moonetix-offerwall' ) . '</a></p>',
			'test'        => 'moonetix_offerwall',
		);
	}

	/**
	 * Small widget on the WordPress dashboard.
	 */
	public static function dashboard_widget() {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'moonetix_offerwall', __( 'Moonetix Offerwall', 'moonetix-offerwall' ), array( __CLASS__, 'dashboard_widget_body' ) );
		}
	}

	/**
	 * Body of the dashboard widget.
	 */
	public static function dashboard_widget_body() {
		$st     = Moonetix_Offerwall_Ledger::stats( 7 );
		$checks = self::checks( false );
		$open   = count(
			array_filter(
				$checks,
				static function ( $c ) {
					return 'bad' === $c[0];
				}
			)
		);
		$label  = 'builtin' === Moonetix_Offerwall_Settings::get( 'destination' ) ? ' ' . (string) Moonetix_Offerwall_Settings::get( 'balance_label' ) : '';
		echo '<p><strong>' . esc_html__( 'Last 7 days', 'moonetix-offerwall' ) . '</strong></p><ul>';
		/* translators: %s: number of rewards. */
		echo '<li>' . esc_html( sprintf( _n( '%s reward credited', '%s rewards credited', $st['credited'], 'moonetix-offerwall' ), number_format_i18n( $st['credited'] ) ) ) . '</li>';
		/* translators: %s: amount with the balance name. */
		echo '<li>' . esc_html( sprintf( __( '%s paid to your users', 'moonetix-offerwall' ), 'terawallet' === Moonetix_Offerwall_Settings::get( 'destination' ) ? Moonetix_Offerwall_Frontend::amount_text( $st['wallet'], 'terawallet' ) : number_format_i18n( $st['reward'], abs( $st['reward'] - round( $st['reward'] ) ) > 0.00001 ? 2 : 0 ) . $label ) ) . '</li>';
		/* translators: %s: number of reversals. */
		echo '<li>' . esc_html( sprintf( _n( '%s reversal', '%s reversals', $st['reversed'], 'moonetix-offerwall' ), number_format_i18n( $st['reversed'] ) ) ) . '</li></ul>';
		if ( $open > 0 ) {
			/* translators: %d: number of problems. */
			echo '<p class="mxow-bad" style="color:#b32d2e">' . esc_html( sprintf( _n( '%d setup problem', '%d setup problems', $open, 'moonetix-offerwall' ), $open ) ) . '</p>';
		}
		echo '<p><a href="' . esc_url( self::url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'moonetix-offerwall' ) . '</a> &middot; <a href="' . esc_url( self::url( 'postbacks' ) ) . '">' . esc_html__( 'Postbacks', 'moonetix-offerwall' ) . '</a> &middot; <a href="https://moonetix.com/" target="_blank" rel="noopener">' . esc_html__( 'Your Moonetix earnings', 'moonetix-offerwall' ) . '</a></p>';
	}

	/**
	 * Stylesheet and script of the settings page, only there.
	 *
	 * @param string $hook Admin page hook.
	 */
	public static function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'moonetix-offerwall-admin', MOONETIX_OFFERWALL_URL . 'assets/css/admin.css', array(), MOONETIX_OFFERWALL_VERSION );
		wp_enqueue_script( 'moonetix-offerwall-admin', MOONETIX_OFFERWALL_URL . 'assets/js/admin.js', array(), MOONETIX_OFFERWALL_VERSION, array( 'in_footer' => true ) );
	}

	/**
	 * URL of a tab of the settings page.
	 *
	 * @param string $tab  settings, postbacks, or help.
	 * @param array  $args More query arguments.
	 * @return string
	 */
	private static function url( $tab = 'settings', $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE, 'tab' => $tab ), $args ), admin_url( 'options-general.php' ) );
	}

	/**
	 * The settings page with its tabs.
	 */
	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab switch, read only.
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$tab  = in_array( $tab, array( 'settings', 'postbacks', 'help' ), true ) ? $tab : 'settings';
		$tabs = array(
			'settings'  => __( 'Settings', 'moonetix-offerwall' ),
			'postbacks' => __( 'Postbacks', 'moonetix-offerwall' ),
			'help'      => __( 'How to use', 'moonetix-offerwall' ),
		);
		?>
		<div class="wrap mxow">
			<div class="mxow-head">
				<h1><?php esc_html_e( 'Moonetix Offerwall', 'moonetix-offerwall' ); ?></h1>
				<p><?php esc_html_e( 'Show the Moonetix offerwall to your logged-in users and credit their rewards automatically.', 'moonetix-offerwall' ); ?>
					<a href="https://moonetix.com/" target="_blank" rel="noopener"><?php esc_html_e( 'Moonetix dashboard', 'moonetix-offerwall' ); ?></a>
				</p>
			</div>
			<?php settings_errors( Moonetix_Offerwall_Settings::OPTION ); ?>
			<?php self::failure_notice(); ?>
			<nav class="nav-tab-wrapper mxow-tabs" aria-label="<?php esc_attr_e( 'Moonetix Offerwall sections', 'moonetix-offerwall' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a href="<?php echo esc_url( self::url( $key ) ); ?>" class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>"<?php echo $tab === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php
			if ( 'postbacks' === $tab ) {
				self::tab_postbacks();
			} elseif ( 'help' === $tab ) {
				self::tab_help();
			} else {
				self::tab_settings();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Status box at the top of the Settings tab.
	 */
	private static function status_box() {
		$run    = isset( $_GET['moonetix_check'], $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'moonetix_offerwall_check' );
		$checks = self::checks( $run );
		$bad    = count(
			array_filter(
				$checks,
				static function ( $c ) {
					return 'ok' !== $c[0];
				}
			)
		);
		$state  = 0 === $bad ? 'ok' : ( in_array( 'bad', array_column( $checks, 0 ), true ) ? 'bad' : 'warn' );
		?>
		<section class="mxow-card mxow-status is-<?php echo esc_attr( $state ); ?>" id="moonetix-offerwall-check" aria-labelledby="mxow-status-h">
			<div class="mxow-status-head">
				<h2 id="mxow-status-h">
					<?php
					if ( 0 === $bad ) {
						esc_html_e( 'Everything is set up', 'moonetix-offerwall' );
					} else {
						/* translators: %d: number of open items. */
						echo esc_html( sprintf( _n( '%d thing needs your attention', '%d things need your attention', $bad, 'moonetix-offerwall' ), $bad ) );
					}
					?>
				</h2>
				<?php if ( ! $run ) : ?>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( self::url( 'settings', array( 'moonetix_check' => 1 ) ), 'moonetix_offerwall_check' ) . '#moonetix-offerwall-check' ); ?>"><?php esc_html_e( 'Test the postback URL', 'moonetix-offerwall' ); ?></a>
				<?php endif; ?>
			</div>
			<ul class="mxow-checks">
				<?php foreach ( $checks as $c ) : ?>
					<li class="is-<?php echo esc_attr( $c[0] ); ?>"><span class="mxow-dot" aria-hidden="true"></span><span class="screen-reader-text"><?php echo esc_html( 'ok' === $c[0] ? __( 'OK:', 'moonetix-offerwall' ) : ( 'warn' === $c[0] ? __( 'Check:', 'moonetix-offerwall' ) : __( 'Problem:', 'moonetix-offerwall' ) ) ); ?></span> <strong><?php echo esc_html( $c[1] ); ?></strong><?php echo '' !== $c[2] ? ' <span class="mxow-muted">' . esc_html( $c[2] ) . '</span>' : ''; ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}

	/**
	 * Card with the Earn page: a button that creates it, or links to the page made before.
	 */
	private static function earn_page_box() {
		$id   = (int) get_option( 'moonetix_offerwall_page' );
		$page = $id > 0 ? get_post( $id ) : null;
		if ( $page && 'trash' === $page->post_status ) {
			$page = null;
		}
		?>
		<section class="mxow-card mxow-earn" aria-labelledby="mxow-earn-h">
			<div>
				<h2 id="mxow-earn-h"><?php esc_html_e( 'Earn page', 'moonetix-offerwall' ); ?></h2>
				<p class="mxow-muted">
					<?php
					if ( $page ) {
						echo 'publish' === $page->post_status ? esc_html__( 'Your Earn page is live.', 'moonetix-offerwall' ) : esc_html__( 'Your Earn page is a draft. Check it and publish it.', 'moonetix-offerwall' );
					} else {
						esc_html_e( 'One click makes a page with the balance, the offerwall, and the reward history. It starts as a draft.', 'moonetix-offerwall' );
					}
					?>
				</p>
			</div>
			<div class="mxow-inline">
				<?php if ( $page ) : ?>
					<?php if ( 'publish' === $page->post_status ) : ?>
						<a class="button" href="<?php echo esc_url( get_permalink( $page ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'moonetix-offerwall' ); ?></a>
					<?php endif; ?>
					<a class="button button-primary" href="<?php echo esc_url( (string) get_edit_post_link( $page->ID, 'url' ) ); ?>"><?php esc_html_e( 'Edit page', 'moonetix-offerwall' ); ?></a>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="moonetix_offerwall_create_page">
						<?php wp_nonce_field( 'moonetix_offerwall_create_page' ); ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Create the Earn page', 'moonetix-offerwall' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * A secret key field. Saved keys are never printed, and keys set in wp-config.php cannot be changed here.
	 *
	 * @param string $key   link_salt or postback_key.
	 * @param string $label Field label.
	 * @param string $help  Help text.
	 */
	private static function secret_field( $key, $label, $help ) {
		$name   = Moonetix_Offerwall_Settings::OPTION;
		$set    = '' !== (string) Moonetix_Offerwall_Settings::get( $key );
		$locked = Moonetix_Offerwall_Settings::locked( $key );
		$id     = 'mxow-' . $key;
		?>
		<div class="mxow-field">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<?php if ( $locked ) : ?>
				<input type="text" class="regular-text" id="<?php echo esc_attr( $id ); ?>" value="<?php esc_attr_e( 'Set in wp-config.php', 'moonetix-offerwall' ); ?>" disabled>
			<?php else : ?>
				<div class="mxow-inline">
					<input type="password" class="regular-text code" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="" autocomplete="new-password" spellcheck="false" placeholder="<?php echo $set ? esc_attr__( 'Saved. Enter a new one to replace it.', 'moonetix-offerwall' ) : ''; ?>">
					<?php if ( $set ) : ?>
						<label class="mxow-check"><input type="checkbox" name="<?php echo esc_attr( $name . '[' . $key . '_clear]' ); ?>" value="1"> <?php esc_html_e( 'Remove', 'moonetix-offerwall' ); ?></label>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<p class="description"><?php echo esc_html( $help ); ?></p>
		</div>
		<?php
	}

	/**
	 * Settings tab.
	 */
	private static function tab_settings() {
		$s     = Moonetix_Offerwall_Settings::all();
		$name  = Moonetix_Offerwall_Settings::OPTION;
		$dests = Moonetix_Offerwall_Settings::destinations();
		self::status_box();
		self::earn_page_box();
		?>
		<form method="post" action="options.php" class="mxow-form">
			<?php settings_fields( self::PAGE ); ?>

			<section class="mxow-card" aria-labelledby="mxow-s1">
				<h2 id="mxow-s1"><span class="mxow-step" aria-hidden="true">1</span><?php esc_html_e( 'Connect your offerwall', 'moonetix-offerwall' ); ?></h2>
				<p class="mxow-intro"><?php esc_html_e( 'Copy these from your Moonetix dashboard. The keys stay on this server and are never shown to visitors.', 'moonetix-offerwall' ); ?></p>
				<div class="mxow-field">
					<label for="mxow-app-id"><?php esc_html_e( 'App ID', 'moonetix-offerwall' ); ?></label>
					<input type="text" class="regular-text code" id="mxow-app-id" name="<?php echo esc_attr( $name ); ?>[app_id]" value="<?php echo esc_attr( $s['app_id'] ); ?>" autocomplete="off" spellcheck="false">
					<p class="description"><?php esc_html_e( 'In your Moonetix dashboard, open Offerwalls and click Copy ID next to your offerwall. You can also paste your offerwall link: the ID is taken from it.', 'moonetix-offerwall' ); ?></p>
				</div>
				<?php
				self::secret_field( 'link_salt', __( 'Link hash salt', 'moonetix-offerwall' ), __( 'In your Moonetix dashboard, open Offerwalls, then your offerwall, Integration, 2. Link security, and click Copy next to Link hash salt. It signs the offerwall link of every user, so nobody can open it as someone else.', 'moonetix-offerwall' ) );
				self::secret_field( 'postback_key', __( 'Postback secret key', 'moonetix-offerwall' ), __( 'In your Moonetix dashboard, open Postback in the menu and copy the Postback secret key of this offerwall. Every postback is checked with it, so only Moonetix can credit points.', 'moonetix-offerwall' ) );
				?>
				<div class="mxow-field">
					<label for="mxow-pb-url"><?php esc_html_e( 'Your postback URL', 'moonetix-offerwall' ); ?></label>
					<div class="mxow-copy">
						<input type="text" class="large-text code" id="mxow-pb-url" readonly value="<?php echo esc_attr( Moonetix_Offerwall_Postback::url_template() ); ?>">
						<button type="button" class="button" data-mxow-copy="mxow-pb-url" data-done="<?php esc_attr_e( 'Copied', 'moonetix-offerwall' ); ?>"><?php esc_html_e( 'Copy', 'moonetix-offerwall' ); ?></button>
					</div>
					<p class="description"><?php esc_html_e( 'Click Copy. In your Moonetix dashboard, open Postback in the menu, paste it into the Postback URL field of this offerwall, and save. Then open Send a test postback there and send one: it shows up on the Postbacks tab of this plugin.', 'moonetix-offerwall' ); ?></p>
				</div>
				<p class="mxow-muted mxow-small"><?php esc_html_e( 'Optional, for developers: define MOONETIX_OFFERWALL_LINK_SALT and MOONETIX_OFFERWALL_POSTBACK_KEY in wp-config.php to keep the keys out of the database.', 'moonetix-offerwall' ); ?></p>
			</section>

			<section class="mxow-card" aria-labelledby="mxow-s2">
				<h2 id="mxow-s2"><span class="mxow-step" aria-hidden="true">2</span><?php esc_html_e( 'Rewards', 'moonetix-offerwall' ); ?></h2>
				<div class="mxow-field">
					<label for="mxow-destination"><?php esc_html_e( 'Credit rewards to', 'moonetix-offerwall' ); ?></label>
					<select id="mxow-destination" name="<?php echo esc_attr( $name ); ?>[destination]" data-mxow-switch="destination">
						<?php foreach ( $dests as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['destination'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( ! isset( $dests[ $s['destination'] ] ) ) : ?>
						<p class="description mxow-bad"><?php esc_html_e( 'The plugin chosen before is not active. Rewards are not credited until you activate it again or choose another option.', 'moonetix-offerwall' ); ?></p>
					<?php endif; ?>
					<p class="description"><?php esc_html_e( 'myCRED, GamiPress, and TeraWallet (the WooCommerce wallet) show up here when they are active. You pay your users yourself, in the way you choose.', 'moonetix-offerwall' ); ?></p>
				</div>
				<?php if ( function_exists( 'mycred_get_types' ) ) : ?>
				<div class="mxow-field" data-mxow-when="destination=mycred">
					<label for="mxow-mycred-type"><?php esc_html_e( 'myCRED point type', 'moonetix-offerwall' ); ?></label>
					<select id="mxow-mycred-type" name="<?php echo esc_attr( $name ); ?>[mycred_type]">
						<?php foreach ( (array) mycred_get_types() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['mycred_type'], $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<?php endif; ?>
				<?php if ( function_exists( 'gamipress_get_points_types' ) ) : ?>
				<div class="mxow-field" data-mxow-when="destination=gamipress">
					<label for="mxow-gamipress-type"><?php esc_html_e( 'GamiPress points type', 'moonetix-offerwall' ); ?></label>
					<select id="mxow-gamipress-type" name="<?php echo esc_attr( $name ); ?>[gamipress_type]">
						<?php foreach ( (array) gamipress_get_points_types() as $key => $type ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['gamipress_type'], $key ); ?>><?php echo esc_html( is_array( $type ) && isset( $type['plural_name'] ) ? $type['plural_name'] : $key ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'GamiPress counts whole points, so rewards are rounded. Use a whole-number currency in your Moonetix dashboard, for example 1,000 points per dollar.', 'moonetix-offerwall' ); ?></p>
				</div>
				<?php endif; ?>
				<?php if ( Moonetix_Offerwall_Settings::wallet_active() ) : ?>
				<?php
				$cur = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : 'USD';
				$dec = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
				/* translators: 1: number of points, 2: amount with currency code. */
				$example = __( 'A reward of %1$s points adds %2$s to the wallet.', 'moonetix-offerwall' );
				?>
				<div class="mxow-field" data-mxow-when="destination=terawallet">
					<label for="mxow-wallet-rate">
						<?php
						/* translators: %s: store currency code, for example EUR. */
						echo esc_html( sprintf( __( 'Points for 1 %s', 'moonetix-offerwall' ), $cur ) );
						?>
					</label>
					<input type="text" inputmode="decimal" class="small-text" style="width:9em" id="mxow-wallet-rate" name="<?php echo esc_attr( $name ); ?>[wallet_rate]" value="<?php echo esc_attr( rtrim( rtrim( number_format( (float) $s['wallet_rate'], 6, '.', '' ), '0' ), '.' ) ); ?>" data-mxow-rate data-cur="<?php echo esc_attr( $cur ); ?>" data-dec="<?php echo esc_attr( (string) $dec ); ?>" data-example="<?php echo esc_attr( $example ); ?>">
					<p class="description"><?php esc_html_e( 'The wallet holds money, so every reward is divided by this number and rounded to the decimals of your store prices.', 'moonetix-offerwall' ); ?> <strong data-mxow-rate-out></strong></p>
					<p class="description"><?php esc_html_e( 'Tip: to skip the conversion, make your Moonetix currency your store currency (in your Moonetix dashboard, Rewards: the currency name, and the conversion rate in that currency for each $1), and enter 1 here.', 'moonetix-offerwall' ); ?></p>
					<p class="description"><?php esc_html_e( 'Users spend the wallet at checkout. A reversed reward is taken back from the wallet, even if that takes it below zero.', 'moonetix-offerwall' ); ?></p>
				</div>
				<?php endif; ?>
				<div class="mxow-field" data-mxow-when="destination=builtin">
					<label for="mxow-label"><?php esc_html_e( 'Balance name', 'moonetix-offerwall' ); ?></label>
					<input type="text" class="regular-text" id="mxow-label" name="<?php echo esc_attr( $name ); ?>[balance_label]" value="<?php echo esc_attr( $s['balance_label'] ); ?>" maxlength="40">
					<p class="description"><?php esc_html_e( 'Shown after the number, for example 1,250 Coins. Use the same name as your Moonetix currency.', 'moonetix-offerwall' ); ?></p>
				</div>
				<?php self::share_box( $s ); ?>
			</section>

			<section class="mxow-card" aria-labelledby="mxow-s3">
				<h2 id="mxow-s3"><span class="mxow-step" aria-hidden="true">3</span><?php esc_html_e( 'Who can earn', 'moonetix-offerwall' ); ?></h2>
				<p class="mxow-intro"><?php esc_html_e( 'Every logged-in user sees the offerwall. To keep it for some users, for example paying members or customers, choose their roles.', 'moonetix-offerwall' ); ?></p>
				<fieldset class="mxow-field">
					<legend class="screen-reader-text"><?php esc_html_e( 'Who can earn', 'moonetix-offerwall' ); ?></legend>
					<label class="mxow-check mxow-radio"><input type="radio" name="<?php echo esc_attr( $name ); ?>[access]" value="all" data-mxow-switch="access" <?php checked( $s['access'], 'all' ); ?>> <?php esc_html_e( 'All logged-in users', 'moonetix-offerwall' ); ?></label>
					<label class="mxow-check mxow-radio"><input type="radio" name="<?php echo esc_attr( $name ); ?>[access]" value="roles" data-mxow-switch="access" <?php checked( $s['access'], 'roles' ); ?>> <?php esc_html_e( 'Only users with these roles', 'moonetix-offerwall' ); ?></label>
				</fieldset>
				<div class="mxow-sub" data-mxow-when="access=roles">
					<div class="mxow-field">
						<div class="mxow-roles" role="group" aria-label="<?php esc_attr_e( 'Roles that can earn', 'moonetix-offerwall' ); ?>">
							<?php foreach ( Moonetix_Offerwall_Settings::role_names() as $key => $label ) : ?>
								<label class="mxow-check"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[roles][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $s['roles'], true ), true ); ?>> <?php echo esc_html( $label ); ?></label>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="mxow-field">
						<label for="mxow-denied"><?php esc_html_e( 'Message for everyone else', 'moonetix-offerwall' ); ?></label>
						<input type="text" class="large-text" id="mxow-denied" name="<?php echo esc_attr( $name ); ?>[denied_message]" value="<?php echo esc_attr( $s['denied_message'] ); ?>" maxlength="300">
						<p class="description"><?php esc_html_e( 'Shown instead of the offerwall. Tell them how to get access, for example by upgrading their membership.', 'moonetix-offerwall' ); ?></p>
					</div>
					<p class="mxow-muted mxow-small"><?php esc_html_e( 'Administrators always see the offerwall, so they can test it. Rewards for offers a user already completed are still credited.', 'moonetix-offerwall' ); ?></p>
				</div>
			</section>

			<section class="mxow-card" aria-labelledby="mxow-s4">
				<h2 id="mxow-s4"><span class="mxow-step" aria-hidden="true">4</span><?php esc_html_e( 'Display', 'moonetix-offerwall' ); ?></h2>
				<div class="mxow-grid">
					<div class="mxow-field">
						<label for="mxow-height"><?php esc_html_e( 'Offerwall height', 'moonetix-offerwall' ); ?></label>
						<div class="mxow-inline"><input type="number" class="small-text" id="mxow-height" name="<?php echo esc_attr( $name ); ?>[height]" value="<?php echo esc_attr( (string) $s['height'] ); ?>" min="400" max="3000" step="10"> <span>px</span></div>
					</div>
					<div class="mxow-field">
						<label for="mxow-guest"><?php esc_html_e( 'Message for logged-out visitors', 'moonetix-offerwall' ); ?></label>
						<input type="text" class="large-text" id="mxow-guest" name="<?php echo esc_attr( $name ); ?>[guest_message]" value="<?php echo esc_attr( $s['guest_message'] ); ?>" maxlength="300">
					</div>
				</div>
				<div class="mxow-toggle">
					<label class="mxow-check"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[floating]" value="1" data-mxow-switch="floating" <?php checked( (int) $s['floating'], 1 ); ?>> <strong><?php esc_html_e( 'Floating Earn button', 'moonetix-offerwall' ); ?></strong></label>
					<p class="description"><?php esc_html_e( 'A button with the balance in the corner of every page. It opens the offerwall in a window, so users find it from anywhere.', 'moonetix-offerwall' ); ?></p>
					<div class="mxow-sub mxow-grid" data-mxow-when="floating=1">
						<div class="mxow-field">
							<label for="mxow-ftext"><?php esc_html_e( 'Button text', 'moonetix-offerwall' ); ?></label>
							<input type="text" class="regular-text" id="mxow-ftext" name="<?php echo esc_attr( $name ); ?>[floating_text]" value="<?php echo esc_attr( $s['floating_text'] ); ?>" maxlength="24">
						</div>
						<div class="mxow-field">
							<label for="mxow-fside"><?php esc_html_e( 'Side', 'moonetix-offerwall' ); ?></label>
							<select id="mxow-fside" name="<?php echo esc_attr( $name ); ?>[floating_side]">
								<option value="right" <?php selected( $s['floating_side'], 'right' ); ?>><?php esc_html_e( 'Right', 'moonetix-offerwall' ); ?></option>
								<option value="left" <?php selected( $s['floating_side'], 'left' ); ?>><?php esc_html_e( 'Left', 'moonetix-offerwall' ); ?></option>
							</select>
						</div>
						<div class="mxow-field">
							<label for="mxow-accent"><?php esc_html_e( 'Color', 'moonetix-offerwall' ); ?></label>
							<input type="color" id="mxow-accent" name="<?php echo esc_attr( $name ); ?>[accent]" value="<?php echo esc_attr( $s['accent'] ); ?>">
						</div>
						<div class="mxow-field">
							<label for="mxow-fdevice"><?php esc_html_e( 'Show on', 'moonetix-offerwall' ); ?></label>
							<select id="mxow-fdevice" name="<?php echo esc_attr( $name ); ?>[floating_device]">
								<option value="all" <?php selected( $s['floating_device'], 'all' ); ?>><?php esc_html_e( 'All devices', 'moonetix-offerwall' ); ?></option>
								<option value="mobile" <?php selected( $s['floating_device'], 'mobile' ); ?>><?php esc_html_e( 'Phones and small tablets only', 'moonetix-offerwall' ); ?></option>
								<option value="desktop" <?php selected( $s['floating_device'], 'desktop' ); ?>><?php esc_html_e( 'Computers and large tablets only', 'moonetix-offerwall' ); ?></option>
							</select>
						</div>
					</div>
					<div class="mxow-sub" data-mxow-when="floating=1">
						<?php self::hide_pages_field( $s ); ?>
					</div>
				</div>
				<div class="mxow-toggle">
					<label class="mxow-check"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[popup_links]" value="1" <?php checked( (int) $s['popup_links'], 1 ); ?>> <strong><?php esc_html_e( 'Open the offerwall window from your own menu items and buttons', 'moonetix-offerwall' ); ?></strong></label>
					<p class="description">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: the CSS class name. */
								esc_html__( 'Add the CSS class %s to a menu item (Appearance, Menus, Screen Options, CSS Classes) or to a block, for example a button or a navigation link (Advanced, Additional CSS classes). A click opens the offerwall in a window, without the floating button. Visitors who cannot earn follow the link as usual, so point it to your Earn page.', 'moonetix-offerwall' ),
								'<code>moonetix-ow-open</code>'
							),
							array( 'code' => array() )
						);
						?>
					</p>
				</div>
				<div class="mxow-toggle">
					<label class="mxow-check"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[toast]" value="1" <?php checked( (int) $s['toast'], 1 ); ?>> <strong><?php esc_html_e( 'Reward notices', 'moonetix-offerwall' ); ?></strong></label>
					<p class="description"><?php esc_html_e( 'After a reward, the user sees a short notice like "+250 Coins" on the next page they open.', 'moonetix-offerwall' ); ?></p>
				</div>
				<div class="mxow-toggle">
					<label class="mxow-check"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[credit_link]" value="1" data-mxow-switch="credit_link" <?php checked( (int) $s['credit_link'], 1 ); ?>> <strong><?php esc_html_e( 'Show a "Rewards by Moonetix" link under the offerwall', 'moonetix-offerwall' ); ?></strong></label>
					<p class="description"><?php esc_html_e( 'Optional and off by default. With your referral code, you earn a share of what the publishers you bring to Moonetix earn.', 'moonetix-offerwall' ); ?></p>
					<div class="mxow-sub" data-mxow-when="credit_link=1">
						<div class="mxow-field">
							<label for="mxow-ref"><?php esc_html_e( 'Your Moonetix referral code', 'moonetix-offerwall' ); ?></label>
							<input type="text" class="regular-text code" id="mxow-ref" name="<?php echo esc_attr( $name ); ?>[ref_code]" value="<?php echo esc_attr( $s['ref_code'] ); ?>" maxlength="20" spellcheck="false" placeholder="yourcode">
							<p class="description"><?php esc_html_e( 'In your Moonetix dashboard, open Referrals and copy Your referral link. Paste the link or only the code after moonetix.com/r/. Leave it empty to link to moonetix.com without a code.', 'moonetix-offerwall' ); ?></p>
						</div>
					</div>
				</div>
			</section>

			<section class="mxow-card" aria-labelledby="mxow-s5">
				<h2 id="mxow-s5"><span class="mxow-step" aria-hidden="true">5</span><?php esc_html_e( 'Data', 'moonetix-offerwall' ); ?></h2>
				<div class="mxow-toggle">
					<label class="mxow-check"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_data]" value="1" <?php checked( (int) $s['delete_data'], 1 ); ?>> <strong><?php esc_html_e( 'Delete all plugin data when the plugin is deleted', 'moonetix-offerwall' ); ?></strong></label>
					<p class="description"><?php esc_html_e( 'Settings, reward history, and built-in balances. Leave it off if you might install the plugin again.', 'moonetix-offerwall' ); ?></p>
				</div>
			</section>

			<div class="mxow-save"><?php submit_button( __( 'Save changes', 'moonetix-offerwall' ), 'primary', 'submit', false ); ?></div>
		</form>
		<?php
	}

	/**
	 * Explains where the share of the users is set, with the same calculator as the Rewards section of the Moonetix
	 * dashboard. Its fields have no name, so nothing in it is saved or sent.
	 *
	 * @param array $s Settings.
	 */
	private static function share_box( $s ) {
		$unit   = 'builtin' === $s['destination'] ? (string) $s['balance_label'] : __( 'points', 'moonetix-offerwall' );
		$wallet = Moonetix_Offerwall_Settings::wallet_active();
		$cur    = $wallet && function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';
		/* translators: %s: dollar amount, for example 0.500. */
		$keep = __( 'for every $1 you earn, and you keep $%s', 'moonetix-offerwall' );
		?>
		<div class="mxow-toggle mxow-calc" data-mxow-calc data-keep="<?php echo esc_attr( $keep ); ?>" data-unit="<?php echo esc_attr( $unit ); ?>" data-unit-other="<?php esc_attr_e( 'points', 'moonetix-offerwall' ); ?>" data-zero="<?php esc_attr_e( '0, offer not shown', 'moonetix-offerwall' ); ?>">
			<strong><?php esc_html_e( 'How much do your users get?', 'moonetix-offerwall' ); ?></strong>
			<p class="description"><?php esc_html_e( 'You set this in your Moonetix dashboard: Offerwalls, your offerwall, Rewards. Enter the same values here to see what your users get and what you keep. Nothing here is saved or sent.', 'moonetix-offerwall' ); ?></p>
			<div class="mxow-calc-row">
				<label for="mxow-calc-rate"><?php esc_html_e( 'Conversion rate', 'moonetix-offerwall' ); ?>
					<input type="text" inputmode="decimal" id="mxow-calc-rate" value="1000" maxlength="12" data-calc="rate">
					<span class="mxow-hint"><?php esc_html_e( 'Units of your currency for $1 of your earnings, before the split.', 'moonetix-offerwall' ); ?></span>
				</label>
				<label for="mxow-calc-split"><?php esc_html_e( 'Split to user (%)', 'moonetix-offerwall' ); ?>
					<input type="text" inputmode="numeric" id="mxow-calc-split" value="50" maxlength="3" data-calc="split">
					<span class="mxow-hint"><?php esc_html_e( 'The share of your earnings your users get, 1 to 100. The rest is yours.', 'moonetix-offerwall' ); ?></span>
				</label>
				<label for="mxow-calc-round"><?php esc_html_e( 'Reward rounding', 'moonetix-offerwall' ); ?>
					<select id="mxow-calc-round" data-calc="round">
						<option value="round"><?php esc_html_e( 'Whole numbers', 'moonetix-offerwall' ); ?></option>
						<option value="three"><?php esc_html_e( 'Up to 3 decimals', 'moonetix-offerwall' ); ?></option>
					</select>
					<span class="mxow-hint"><?php esc_html_e( 'Offers that would give 0 are not shown to your users.', 'moonetix-offerwall' ); ?></span>
				</label>
			</div>
			<div class="mxow-eq" aria-live="polite">
				<div class="mxow-eq-box"><span><?php esc_html_e( 'Conversion rate', 'moonetix-offerwall' ); ?></span><strong data-calc-out="rate"></strong><span><?php esc_html_e( 'per $1', 'moonetix-offerwall' ); ?></span></div>
				<span class="mxow-eq-op" aria-hidden="true">&times;</span>
				<div class="mxow-eq-box"><span><?php esc_html_e( 'Split to user', 'moonetix-offerwall' ); ?></span><strong data-calc-out="split"></strong><span><?php esc_html_e( 'of your earnings', 'moonetix-offerwall' ); ?></span></div>
				<span class="mxow-eq-op" aria-hidden="true">=</span>
				<div class="mxow-eq-box is-hero"><span><?php esc_html_e( 'Your user gets', 'moonetix-offerwall' ); ?></span><strong data-calc-out="result"></strong><span data-calc-out="keep"></span></div>
			</div>
			<table class="mxow-ex">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'An offer pays you', 'moonetix-offerwall' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Your user gets', 'moonetix-offerwall' ); ?></th>
						<?php if ( $wallet ) : ?>
							<th scope="col" data-calc-wallet>
								<?php
								/* translators: %s: store currency code, for example EUR. */
								echo esc_html( sprintf( __( 'In the wallet (%s)', 'moonetix-offerwall' ), $cur ) );
								?>
							</th>
						<?php endif; ?>
						<th scope="col"><?php esc_html_e( 'You keep', 'moonetix-offerwall' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array( '0.05', '0.37', '2.5' ) as $payout ) : ?>
						<tr data-calc-payout="<?php echo esc_attr( $payout ); ?>">
							<td>$<?php echo esc_html( number_format( (float) $payout, 3 ) ); ?></td>
							<td data-calc-cell="user"></td>
							<?php if ( $wallet ) : ?>
								<td data-calc-cell="wallet" data-calc-wallet></td>
							<?php endif; ?>
							<td data-calc-cell="keep"></td>
						</tr>
					<?php endforeach; ?>
					<tr class="mxow-ex-try" data-calc-payout="">
						<td><label class="mxow-ex-in"><span class="screen-reader-text"><?php esc_html_e( 'Try your own payout in US dollars', 'moonetix-offerwall' ); ?></span><span aria-hidden="true">$</span><input type="text" inputmode="decimal" maxlength="9" value="1.00" data-calc="try"></label></td>
						<td data-calc-cell="user"></td>
						<?php if ( $wallet ) : ?>
							<td data-calc-cell="wallet" data-calc-wallet></td>
						<?php endif; ?>
						<td data-calc-cell="keep"></td>
					</tr>
				</tbody>
			</table>
			<p class="mxow-small"><a href="https://docs.moonetix.com/rewards" target="_blank" rel="noopener"><?php esc_html_e( 'How rewards are calculated', 'moonetix-offerwall' ); ?></a> &middot; <a href="https://moonetix.com/offerwalls" target="_blank" rel="noopener"><?php esc_html_e( 'Open your offerwalls in the Moonetix dashboard', 'moonetix-offerwall' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * Pages where the floating button is hidden, and the shop pages switch when WooCommerce is active.
	 *
	 * @param array $s Settings.
	 */
	private static function hide_pages_field( $s ) {
		$name   = Moonetix_Offerwall_Settings::OPTION;
		$hidden = array_map( 'intval', (array) $s['floating_hide'] );
		$pages  = get_pages(
			array(
				'sort_column'  => 'post_title',
				'post_status'  => 'publish,private',
				'hierarchical' => 0,
				'number'       => 500,
			)
		);
		$pages  = is_array( $pages ) ? $pages : array();
		usort(
			$pages,
			static function ( $a, $b ) use ( $hidden ) {
				return (int) in_array( (int) $b->ID, $hidden, true ) - (int) in_array( (int) $a->ID, $hidden, true );
			}
		);
		?>
		<div class="mxow-field">
			<label for="mxow-page-filter"><?php esc_html_e( 'Hide the button on these pages', 'moonetix-offerwall' ); ?></label>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[floating_hide_sent]" value="1">
			<?php foreach ( array_diff( $hidden, array_map( 'intval', wp_list_pluck( $pages, 'ID' ) ) ) as $kept ) : ?>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[floating_hide][]" value="<?php echo esc_attr( (string) $kept ); ?>">
			<?php endforeach; ?>
			<?php if ( $pages ) : ?>
				<input type="search" class="regular-text" id="mxow-page-filter" placeholder="<?php esc_attr_e( 'Find a page', 'moonetix-offerwall' ); ?>" data-mxow-filter="mxow-page-list">
				<div class="mxow-pages" id="mxow-page-list" role="group" aria-label="<?php esc_attr_e( 'Pages', 'moonetix-offerwall' ); ?>">
					<?php foreach ( $pages as $page ) : ?>
						<label class="mxow-check"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[floating_hide][]" value="<?php echo esc_attr( (string) $page->ID ); ?>" <?php checked( in_array( (int) $page->ID, $hidden, true ), true ); ?>> <?php echo esc_html( '' !== $page->post_title ? $page->post_title : __( '(no title)', 'moonetix-offerwall' ) ); ?></label>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="mxow-muted"><?php esc_html_e( 'You have no published pages yet.', 'moonetix-offerwall' ); ?></p>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'The button is always hidden on pages that already show the offerwall.', 'moonetix-offerwall' ); ?></p>
		</div>
		<?php if ( Moonetix_Offerwall_Settings::shop_active() ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[floating_shop_sent]" value="1">
			<p class="mxow-shop"><label class="mxow-check"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[floating_shop]" value="1" <?php checked( (int) $s['floating_shop'], 1 ); ?>> <?php esc_html_e( 'Hide it on the cart, the checkout, and My account, so it never gets in the way of an order', 'moonetix-offerwall' ); ?></label></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Postbacks tab: search and a paged list.
	 */
	private static function tab_postbacks() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter, search, and paging of the list.
		$filter = isset( $_GET['moonetix_user'] ) ? absint( $_GET['moonetix_user'] ) : 0;
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$q       = self::query();
		$page    = Moonetix_Offerwall_Ledger::page( $filter > 0 ? '' : $q, $filter, ( $paged - 1 ) * self::PER_PAGE, self::PER_PAGE );
		$rows    = $page[0];
		$total   = $page[1];
		$pages   = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$labels  = self::result_labels();
		$devices = array(
			'desktop' => __( 'Desktop', 'moonetix-offerwall' ),
			'android' => 'Android',
			'ios'     => 'iOS',
			'mobile'  => __( 'Mobile', 'moonetix-offerwall' ),
			'tablet'  => __( 'Tablet', 'moonetix-offerwall' ),
		);
		$num     = static function ( $v ) {
			return rtrim( rtrim( number_format( (float) $v, 5, '.', '' ), '0' ), '.' );
		};
		?>
		<section class="mxow-card" id="moonetix-offerwall-log">
			<div class="mxow-log-head">
				<form method="get" action="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" class="mxow-search" role="search">
					<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>">
					<input type="hidden" name="tab" value="postbacks">
					<label class="screen-reader-text" for="mxow-q"><?php esc_html_e( 'Search postbacks', 'moonetix-offerwall' ); ?></label>
					<input type="search" id="mxow-q" name="moonetix_q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'User, email, transaction ID, or offer', 'moonetix-offerwall' ); ?>">
					<button type="submit" class="button"><?php esc_html_e( 'Search', 'moonetix-offerwall' ); ?></button>
				</form>
				<p class="mxow-muted">
					<?php
					if ( $filter > 0 ) {
						$who = get_userdata( $filter );
						/* translators: %s: user name. */
						echo esc_html( sprintf( __( 'Postbacks of %s', 'moonetix-offerwall' ), $who ? $who->user_login : '#' . $filter ) ) . ' &middot; <a href="' . esc_url( self::url( 'postbacks' ) ) . '">' . esc_html__( 'Show everyone', 'moonetix-offerwall' ) . '</a>';
					} elseif ( '' !== $q ) {
						/* translators: 1: number of results, 2: search text. */
						echo esc_html( sprintf( _n( '%1$d result for "%2$s"', '%1$d results for "%2$s"', $total, 'moonetix-offerwall' ), $total, $q ) ) . ' &middot; <a href="' . esc_url( self::url( 'postbacks' ) ) . '">' . esc_html__( 'Show everyone', 'moonetix-offerwall' ) . '</a>';
					} else {
						/* translators: %d: number of postbacks. */
						echo esc_html( sprintf( _n( '%d postback', '%d postbacks', $total, 'moonetix-offerwall' ), $total ) );
					}
					?>
				</p>
			</div>
			<?php if ( ! $rows ) : ?>
				<p class="mxow-empty"><?php echo '' !== $q || $filter > 0 ? esc_html__( 'Nothing found.', 'moonetix-offerwall' ) : esc_html__( 'No postbacks yet. Send a test postback from the Postback page of your Moonetix dashboard.', 'moonetix-offerwall' ); ?></p>
			<?php else : ?>
			<div class="mxow-table-wrap">
			<table class="widefat striped mxow-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Time', 'moonetix-offerwall' ); ?></th>
						<th scope="col"><?php esc_html_e( 'User', 'moonetix-offerwall' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Offer', 'moonetix-offerwall' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Country, device', 'moonetix-offerwall' ); ?></th>
						<th scope="col" class="num"><?php esc_html_e( 'Reward', 'moonetix-offerwall' ); ?></th>
						<th scope="col" class="num"><?php esc_html_e( 'You earned (USD)', 'moonetix-offerwall' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Result', 'moonetix-offerwall' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Transaction ID', 'moonetix-offerwall' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $rows as $row ) :
						$uid  = (int) $row['wp_user_id'];
						$user = $uid > 0 ? get_userdata( $uid ) : false;
						?>
					<tr>
						<td><?php echo esc_html( get_date_from_gmt( $row['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
						<td>
							<?php if ( $user ) : ?>
								<a href="<?php echo esc_url( self::url( 'postbacks', array( 'moonetix_user' => $uid ) ) ); ?>"><?php echo esc_html( $user->user_login ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $row['external_user_id'] ); ?>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $row['offer_name'] ); ?><?php echo '' !== $row['offer_id'] && '0' !== $row['offer_id'] ? ' <span class="mxow-muted">#' . esc_html( $row['offer_id'] ) . '</span>' : ''; ?></td>
						<td><?php echo esc_html( implode( ', ', array_filter( array( $row['country'], isset( $devices[ $row['device'] ] ) ? $devices[ $row['device'] ] : $row['device'] ) ) ) ); ?></td>
						<td class="num"><?php echo esc_html( $num( $row['reward'] ) ); ?></td>
						<td class="num"><?php echo (float) $row['payout'] > 0 ? esc_html( $num( $row['payout'] ) ) : ''; ?></td>
						<td><span class="mxow-pill is-<?php echo esc_attr( $row['result'] ); ?>"><?php echo esc_html( isset( $labels[ $row['result'] ] ) ? $labels[ $row['result'] ] : $row['result'] ); ?></span></td>
						<td><code><?php echo esc_html( $row['transaction_id'] ); ?></code></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
				<?php if ( $pages > 1 ) : ?>
				<nav class="mxow-pager" aria-label="<?php esc_attr_e( 'Pages', 'moonetix-offerwall' ); ?>">
					<?php
					$keep = array_filter(
						array(
							'moonetix_user' => $filter > 0 ? $filter : null,
							'moonetix_q'    => '' !== $q ? $q : null,
						)
					);
					if ( $paged > 1 ) {
						echo '<a class="button" href="' . esc_url( self::url( 'postbacks', $keep + array( 'paged' => $paged - 1 ) ) ) . '">' . esc_html__( 'Newer', 'moonetix-offerwall' ) . '</a>';
					}
					/* translators: 1: current page, 2: number of pages. */
					echo ' <span class="mxow-muted">' . esc_html( sprintf( __( 'Page %1$d of %2$d', 'moonetix-offerwall' ), $paged, $pages ) ) . '</span> ';
					if ( $paged < $pages ) {
						echo '<a class="button" href="' . esc_url( self::url( 'postbacks', $keep + array( 'paged' => $paged + 1 ) ) ) . '">' . esc_html__( 'Older', 'moonetix-offerwall' ) . '</a>';
					}
					?>
				</nav>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * How to use tab.
	 */
	private static function tab_help() {
		?>
		<div class="mxow-help">
			<section class="mxow-card">
				<h2><?php esc_html_e( 'Show the offerwall', 'moonetix-offerwall' ); ?></h2>
				<ul class="mxow-list">
					<li><strong><?php esc_html_e( 'Moonetix Offerwall block', 'moonetix-offerwall' ); ?></strong> <?php esc_html_e( 'or', 'moonetix-offerwall' ); ?> <code>[moonetix_offerwall]</code>: <?php esc_html_e( 'the offerwall for the logged-in user. Optional: height="900".', 'moonetix-offerwall' ); ?></li>
					<li><strong><?php esc_html_e( 'Moonetix Balance block', 'moonetix-offerwall' ); ?></strong> <?php esc_html_e( 'or', 'moonetix-offerwall' ); ?> <code>[moonetix_balance]</code>: <?php esc_html_e( 'the balance of the logged-in user, for example in the header.', 'moonetix-offerwall' ); ?></li>
					<li><code>[moonetix_history]</code>: <?php esc_html_e( 'the rewards of the logged-in user, for an account page. Optional: limit="20".', 'moonetix-offerwall' ); ?></li>
					<li><?php esc_html_e( 'The floating Earn button and the reward notices are turned on under Settings, Display. Choose the devices, and the pages where the button stays hidden.', 'moonetix-offerwall' ); ?></li>
					<li>
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: the CSS class name. */
								esc_html__( 'Your own menu item or button can open the offerwall in a window: turn on menu links under Display and add the CSS class %s.', 'moonetix-offerwall' ),
								'<code>moonetix-ow-open</code>'
							),
							array( 'code' => array() )
						);
						?>
					</li>
					<li><?php esc_html_e( 'To keep the offerwall for members or customers, choose their roles under Who can earn.', 'moonetix-offerwall' ); ?></li>
				</ul>
			</section>
			<section class="mxow-card">
				<h2><?php esc_html_e( 'Rewards and payouts', 'moonetix-offerwall' ); ?></h2>
				<ul class="mxow-list">
					<li><?php esc_html_e( 'Moonetix pays you for every completed offer. You decide how your users redeem their points and you pay them yourself.', 'moonetix-offerwall' ); ?></li>
					<li><?php esc_html_e( 'How much your users get is set in your Moonetix dashboard, under Rewards: the conversion rate and the split to user. The calculator under Settings, Rewards shows the result.', 'moonetix-offerwall' ); ?></li>
					<li><?php esc_html_e( 'On a WooCommerce shop, credit rewards to TeraWallet: users spend them at checkout like money.', 'moonetix-offerwall' ); ?></li>
					<li><?php esc_html_e( 'With the built-in balance, take points away on the user\'s profile after you pay them out. The user sees the change and the reason.', 'moonetix-offerwall' ); ?></li>
					<li><?php esc_html_e( 'A reversed reward is taken back, so a balance can go below zero.', 'moonetix-offerwall' ); ?></li>
				</ul>
			</section>
			<section class="mxow-card">
				<h2><?php esc_html_e( 'Security', 'moonetix-offerwall' ); ?></h2>
				<ul class="mxow-list">
					<li><?php esc_html_e( 'Every offerwall link is signed for the logged-in user, so nobody can earn as someone else.', 'moonetix-offerwall' ); ?></li>
					<li><?php esc_html_e( 'Every postback is checked with your postback secret key. A postback without the right key changes nothing.', 'moonetix-offerwall' ); ?></li>
					<li><?php esc_html_e( 'Each transaction is credited once, even if a postback arrives twice at the same moment.', 'moonetix-offerwall' ); ?></li>
					<li><?php esc_html_e( 'If a key gets out, replace it in your Moonetix dashboard and here.', 'moonetix-offerwall' ); ?></li>
				</ul>
			</section>
			<section class="mxow-card">
				<h2><?php esc_html_e( 'Help', 'moonetix-offerwall' ); ?></h2>
				<ul class="mxow-list">
					<li><a href="https://docs.moonetix.com/wordpress" target="_blank" rel="noopener"><strong><?php esc_html_e( 'WordPress setup guide', 'moonetix-offerwall' ); ?></strong></a>: <?php esc_html_e( 'every step and setting of this plugin, with answers to common problems.', 'moonetix-offerwall' ); ?></li>
					<li><a href="https://docs.moonetix.com/testing" target="_blank" rel="noopener"><strong><?php esc_html_e( 'Test your setup', 'moonetix-offerwall' ); ?></strong></a>: <?php esc_html_e( 'send a test postback and check your offerwall before your users do.', 'moonetix-offerwall' ); ?></li>
					<li><a href="https://moonetix.com/login" target="_blank" rel="noopener"><strong><?php esc_html_e( 'Live chat', 'moonetix-offerwall' ); ?></strong></a>: <?php esc_html_e( 'log in to your Moonetix dashboard and open the chat in the bottom right corner.', 'moonetix-offerwall' ); ?></li>
					<li><a href="mailto:partners@moonetix.com"><strong>partners@moonetix.com</strong></a>: <?php esc_html_e( 'write to us about your account, offers, or this plugin.', 'moonetix-offerwall' ); ?></li>
					<li><a href="https://moonetix.com/academy" target="_blank" rel="noopener"><strong><?php esc_html_e( 'Moonetix Academy', 'moonetix-offerwall' ); ?></strong></a>: <?php esc_html_e( 'guides to bring more users to your offerwall and earn more from them.', 'moonetix-offerwall' ); ?></li>
				</ul>
			</section>
		</div>
		<?php
	}
	/**
	 * Adds the settings page.
	 */
	public static function menu() {
		add_options_page( __( 'Moonetix Offerwall', 'moonetix-offerwall' ), __( 'Moonetix Offerwall', 'moonetix-offerwall' ), 'manage_options', self::PAGE, array( __CLASS__, 'page' ) );
	}
	/**
	 * Registers the setting and the privacy policy text.
	 */
	public static function register() {
		register_setting(
			self::PAGE,
			Moonetix_Offerwall_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Moonetix_Offerwall_Settings', 'sanitize' ),
				'default'           => Moonetix_Offerwall_Settings::defaults(),
			)
		);
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content(
				__( 'Moonetix Offerwall', 'moonetix-offerwall' ),
				wp_kses_post(
					'<p>' . __( 'When a logged-in user opens the offerwall, this site loads it from Moonetix (wall.moonetix.com) and sends Moonetix the user\'s account ID on this site. Moonetix also receives the IP address, browser, and device details of the visitor, as with any website, to show offers that fit the country and device and to prevent fraud. When the user completes an offer, Moonetix sends this site the account ID, the reward, the offer, and the country and device type of the completion, and this site keeps a record of it to credit the reward.', 'moonetix-offerwall' ) . '</p>' .
					/* translators: 1: Moonetix privacy policy URL, 2: Moonetix terms URL. */
					'<p>' . sprintf( __( 'Moonetix privacy policy: %1$s. Moonetix terms: %2$s.', 'moonetix-offerwall' ), 'https://moonetix.com/privacy', 'https://moonetix.com/terms' ) . '</p>'
				)
			);
		}
	}
	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'moonetix-offerwall' ) . '</a>' );
		return $links;
	}
	/**
	 * Warning when a reward could not be credited in the last 3 days because the points plugin was not active.
	 */
	private static function failure_notice() {
		$f = get_option( 'moonetix_offerwall_last_failure' );
		if ( ! is_array( $f ) || empty( $f['time'] ) || (int) $f['time'] < time() - 3 * DAY_IN_SECONDS ) {
			return;
		}
		$names = array(
			'mycred'     => 'myCRED',
			'gamipress'  => 'GamiPress',
			'terawallet' => 'TeraWallet',
		);
		if ( 'database' === $f['destination'] ) {
			echo '<div class="notice notice-error"><p>' . esc_html(
				sprintf(
					/* translators: %s: date and time. */
					__( 'On %s a reward could not be saved, because the plugin could not write to its table in the database. Nothing was credited, and Moonetix sends the postback again later. Deactivate and activate the plugin to repair the table, or ask your host to check the database.', 'moonetix-offerwall' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $f['time'] )
				)
			) . '</p></div>';
			return;
		}
		$name  = isset( $names[ $f['destination'] ] ) ? $names[ $f['destination'] ] : (string) $f['destination'];
		echo '<div class="notice notice-error"><p>' . esc_html(
			sprintf(
				/* translators: 1: date and time, 2: plugin name. */
				__( 'On %1$s a reward could not be credited to %2$s. The plugin was not active, or it refused the change, for example because the user\'s wallet is locked. Moonetix sends a failed postback again several times. Fix it, or choose another place for rewards, so the next try succeeds.', 'moonetix-offerwall' ),
				wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $f['time'] ),
				$name
			)
		) . '</p></div>';
	}
	/**
	 * The search text of the postback list.
	 *
	 * @return string
	 */
	private static function query() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only search of the list.
		return isset( $_GET['moonetix_q'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['moonetix_q'] ) ), 0, 191 ) : '';
	}
	/**
	 * Setup checks. The loopback request to the postback URL only runs when the administrator asks for it.
	 *
	 * @param bool $remote Also call the postback URL of this site.
	 * @return array List of array( state, label, detail ), state is ok, warn, or bad.
	 */
	public static function checks( $remote ) {
		$s     = Moonetix_Offerwall_Settings::all();
		$dests = Moonetix_Offerwall_Settings::destinations();
		$out   = array();
		$out[] = '' !== $s['app_id'] ? array( 'ok', __( 'App ID is set', 'moonetix-offerwall' ), '' ) : array( 'bad', __( 'App ID is missing', 'moonetix-offerwall' ), __( 'Without it the offerwall does not load.', 'moonetix-offerwall' ) );
		$out[] = '' !== $s['link_salt'] ? array( 'ok', __( 'Link hash salt is set', 'moonetix-offerwall' ), '' ) : array( 'warn', __( 'Link hash salt is empty', 'moonetix-offerwall' ), __( 'Fine only if your offerwall does not require signed links. Otherwise your users see an error.', 'moonetix-offerwall' ) );
		$out[] = '' !== $s['postback_key'] ? array( 'ok', __( 'Postback secret key is set', 'moonetix-offerwall' ), '' ) : array( 'bad', __( 'Postback secret key is missing', 'moonetix-offerwall' ), __( 'Rewards are not credited until you add it.', 'moonetix-offerwall' ) );
		$out[] = isset( $dests[ $s['destination'] ] ) ? array( 'ok', __( 'The place for rewards is available', 'moonetix-offerwall' ), $dests[ $s['destination'] ] ) : array( 'bad', __( 'The plugin chosen for rewards is not active', 'moonetix-offerwall' ), __( 'Activate it again or choose another option.', 'moonetix-offerwall' ) );
		$out[] = 0 === strpos( home_url(), 'https://' ) ? array( 'ok', __( 'The site uses HTTPS', 'moonetix-offerwall' ), '' ) : array( 'bad', __( 'The site does not use HTTPS', 'moonetix-offerwall' ), __( 'The offerwall only loads on https pages.', 'moonetix-offerwall' ) );
		$last  = Moonetix_Offerwall_Ledger::recent( 1 );
		$out[] = $last ? array( 'ok', __( 'Postbacks arrive', 'moonetix-offerwall' ), sprintf( /* translators: %s: date and time. */ __( 'Last one: %s', 'moonetix-offerwall' ), get_date_from_gmt( $last[0]['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ) : array( 'warn', __( 'No postback has arrived yet', 'moonetix-offerwall' ), __( 'Send a test postback from the Postback page of your Moonetix dashboard.', 'moonetix-offerwall' ) );
		if ( $remote ) {
			$res  = wp_remote_get( rest_url( Moonetix_Offerwall_Postback::NAMESPACE_V1 . '/postback' ), array( 'timeout' => 10 ) );
			$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
			if ( in_array( $code, array( 400, 503 ), true ) ) {
				$out[] = array( 'ok', __( 'The postback URL can be reached', 'moonetix-offerwall' ), '' );
			} elseif ( 0 === $code ) {
				$out[] = array( 'warn', __( 'The site could not call its own postback URL', 'moonetix-offerwall' ), __( 'Some hosts block this test. Send a test postback from your Moonetix dashboard to be sure.', 'moonetix-offerwall' ) );
			} else {
				/* translators: %d: HTTP status code. */
				$out[] = array( 'bad', __( 'The postback URL is blocked', 'moonetix-offerwall' ), sprintf( __( 'It answered with HTTP %d. A security plugin or the host may block the WordPress REST API for visitors. Allow the moonetix-offerwall route.', 'moonetix-offerwall' ), $code ) );
			}
		}
		return $out;
	}
	/**
	 * Balance box on the user profile screen, for administrators.
	 *
	 * @param WP_User $user User being edited.
	 */
	public static function profile_box( $user ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$builtin = 'builtin' === Moonetix_Offerwall_Settings::get( 'destination' );
		$rows    = Moonetix_Offerwall_Ledger::recent( 5, (int) $user->ID, true );
		$labels  = self::result_labels();
		?>
		<h2 id="moonetix-offerwall"><?php esc_html_e( 'Moonetix Offerwall', 'moonetix-offerwall' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Balance', 'moonetix-offerwall' ); ?></th>
				<td>
					<strong><?php echo esc_html( Moonetix_Offerwall_Frontend::balance_text( (int) $user->ID ) ); ?></strong>
					<a href="<?php echo esc_url( self::url( 'postbacks', array( 'moonetix_user' => (int) $user->ID ) ) ); ?>"><?php esc_html_e( 'See all postbacks', 'moonetix-offerwall' ); ?></a>
					<?php if ( $rows ) : ?>
						<ul>
							<?php foreach ( $rows as $row ) : ?>
								<li><?php echo esc_html( get_date_from_gmt( $row['created_at'], get_option( 'date_format' ) ) . ': ' . ( '' !== $row['offer_name'] ? $row['offer_name'] : $row['transaction_id'] ) . ' (' . ( (float) $row['reward'] < 0 ? '' : '+' ) . rtrim( rtrim( number_format( (float) $row['reward'], 5, '.', '' ), '0' ), '.' ) . ', ' . ( isset( $labels[ $row['result'] ] ) ? $labels[ $row['result'] ] : $row['result'] ) . ')' ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</td>
			</tr>
			<?php if ( $builtin ) : ?>
			<tr>
				<th scope="row"><label for="moonetix-offerwall-adjust"><?php esc_html_e( 'Change the balance', 'moonetix-offerwall' ); ?></label></th>
				<td>
					<?php wp_nonce_field( 'moonetix_offerwall_adjust_' . (int) $user->ID, 'moonetix_offerwall_adjust_nonce' ); ?>
					<input type="number" step="any" id="moonetix-offerwall-adjust" name="moonetix_offerwall_adjust" class="small-text" style="width:9em" placeholder="-500">
					<input type="text" name="moonetix_offerwall_adjust_note" class="regular-text" maxlength="191" placeholder="<?php esc_attr_e( 'Reason, for example: Paid out', 'moonetix-offerwall' ); ?>" aria-label="<?php esc_attr_e( 'Reason', 'moonetix-offerwall' ); ?>">
					<p class="description"><?php esc_html_e( 'A negative number takes points away, for example when you pay the user out. The user sees the change and the reason in [moonetix_history].', 'moonetix-offerwall' ); ?></p>
				</td>
			</tr>
			<?php else : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Change the balance', 'moonetix-offerwall' ); ?></th>
				<td><p class="description"><?php esc_html_e( 'Rewards go to your points or wallet plugin. Change the balance with its own tools.', 'moonetix-offerwall' ); ?></p></td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}
	/**
	 * Saves a manual balance change from the profile screen.
	 *
	 * @param int $user_id User being saved.
	 */
	public static function profile_save( $user_id ) {
		if ( ! current_user_can( 'manage_options' ) || 'builtin' !== Moonetix_Offerwall_Settings::get( 'destination' ) ) {
			return;
		}
		if ( ! isset( $_POST['moonetix_offerwall_adjust_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['moonetix_offerwall_adjust_nonce'] ) ), 'moonetix_offerwall_adjust_' . (int) $user_id ) ) {
			return;
		}
		$raw = isset( $_POST['moonetix_offerwall_adjust'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['moonetix_offerwall_adjust'] ) ) ) : '';
		if ( '' === $raw || ! is_numeric( $raw ) || 0.0 === (float) $raw || abs( (float) $raw ) > 1000000000 ) {
			return;
		}
		$note = isset( $_POST['moonetix_offerwall_adjust_note'] ) ? sanitize_text_field( wp_unslash( $_POST['moonetix_offerwall_adjust_note'] ) ) : '';
		Moonetix_Offerwall_Ledger::adjust( (int) $user_id, (float) $raw, $note, get_current_user_id() );
	}
	/**
	 * Readable names of the log results.
	 *
	 * @return array
	 */
	private static function result_labels() {
		return array(
			'credited'     => __( 'Credited', 'moonetix-offerwall' ),
			'reversed'     => __( 'Reversed', 'moonetix-offerwall' ),
			'ignored'      => __( 'Reversal without a credit, ignored', 'moonetix-offerwall' ),
			'unknown_user' => __( 'No such user on this site', 'moonetix-offerwall' ),
			'adjusted'     => __( 'Changed by an admin', 'moonetix-offerwall' ),
		);
	}
}
