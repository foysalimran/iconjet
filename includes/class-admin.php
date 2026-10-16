<?php
/**
 * Admin pages.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * "Iconjet" admin menu. Every page is rendered by one React app
 * (build/admin/index.js) that reads the page from data-page.
 *
 * Capabilities: Icon Library and Shortcode Generator are for anyone who can
 * edit posts (they only read icons); Custom Icons and Settings need
 * manage_options, matching the REST routes they call.
 *
 * Assets load only on these pages (no dashboard-wide notices or scripts).
 */
final class Admin {

	const SLUG = 'iconjet';

	/**
	 * Page hook suffixes => app page id.
	 *
	 * @var array
	 */
	private static $hooks = array();

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ICONJET_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Pages: slug => [ page id, menu title, capability ].
	 *
	 * @return array
	 */
	private static function pages() {
		return array(
			self::SLUG                => array( 'library', __( 'Icon Library', 'iconjet' ), 'edit_posts' ),
			self::SLUG . '-generator' => array( 'generator', __( 'Shortcode Generator', 'iconjet' ), 'edit_posts' ),
			self::SLUG . '-custom'    => array( 'custom', __( 'Custom Icons', 'iconjet' ), 'manage_options' ),
			self::SLUG . '-settings'  => array( 'settings', __( 'Settings', 'iconjet' ), 'manage_options' ),
		);
	}

	/**
	 * Add the menu.
	 */
	public static function add_menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Menu icon data URI, the format add_menu_page() expects.
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="black" d="M12 2.5l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.3l-5.8 3.1 1.1-6.5-4.7-4.6 6.5-.9z"/></svg>'
		);

		self::$hooks[ add_menu_page( __( 'Iconjet', 'iconjet' ), __( 'Iconjet', 'iconjet' ), 'edit_posts', self::SLUG, array( __CLASS__, 'render' ), $icon, 81 ) ] = 'library';

		foreach ( self::pages() as $slug => $page ) {
			$hook = add_submenu_page( self::SLUG, $page[1] . ' ‹ ' . __( 'Iconjet', 'iconjet' ), $page[1], $page[2], $slug, array( __CLASS__, 'render' ) );
			if ( $hook ) {
				self::$hooks[ $hook ] = $page[0];
			}
		}
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		if ( current_user_can( 'manage_options' ) ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG . '-settings' ) ) . '">' . esc_html__( 'Settings', 'iconjet' ) . '</a>' );
		}
		return $links;
	}

	/**
	 * Page container. The React app renders into it.
	 */
	public static function render() {
		$screen = get_current_screen();
		$page   = $screen && isset( self::$hooks[ $screen->id ] ) ? self::$hooks[ $screen->id ] : 'library';
		$titles = array_combine( wp_list_pluck( self::pages(), 0 ), wp_list_pluck( self::pages(), 1 ) );
		?>
		<div class="wrap iconjet-admin-wrap">
			<h1><?php echo esc_html( $titles[ $page ] ); ?></h1>
			<div id="iconjet-admin" data-page="<?php echo esc_attr( $page ); ?>">
				<p class="hide-if-js"><?php esc_html_e( 'Iconjet needs JavaScript to show this page.', 'iconjet' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue the app on Iconjet pages only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( ! isset( self::$hooks[ $hook_suffix ] ) ) {
			return;
		}
		$asset_file = ICONJET_DIR . 'build/admin/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( 'iconjet-admin', ICONJET_URL . 'build/admin/index.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'iconjet-admin', 'iconjet' );
		wp_add_inline_script(
			'iconjet-admin',
			'window.iconjetAdmin = ' . wp_json_encode(
				array(
					'page'      => self::$hooks[ $hook_suffix ],
					'canManage' => current_user_can( 'manage_options' ),
					'urls'      => array(
						'generator' => admin_url( 'admin.php?page=' . self::SLUG . '-generator' ),
						'custom'    => admin_url( 'admin.php?page=' . self::SLUG . '-custom' ),
					),
				)
			) . ';',
			'before'
		);

		Blocks::register_style();
		wp_enqueue_style( 'iconjet-admin', ICONJET_URL . 'build/admin/index.css', array( 'wp-components', Blocks::STYLE ), $asset['version'] );
		wp_style_add_data( 'iconjet-admin', 'rtl', 'replace' );
	}
}
