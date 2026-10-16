<?php
/**
 * Plugin bootstrap.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every component to WordPress.
 */
final class Plugin {

	/**
	 * Option that stores the plugin version the site last ran.
	 */
	const VERSION_OPTION = 'iconjet_version';

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function boot() {
		add_action( 'init', array( $this, 'maybe_upgrade' ), 1 );
		add_action( 'init', array( Custom_Icons::class, 'register_post_type' ) );
		add_action( 'init', array( Settings::class, 'register' ) );
		add_action( 'rest_api_init', array( Settings::class, 'register' ) );
		add_action( 'rest_api_init', array( new Rest_Controller(), 'register_routes' ) );

		Blocks::register();
		Shortcodes::register();
		if ( is_admin() ) {
			Admin::register();
		}
		Cache_Warmer::register();
	}

	/**
	 * Clear version-dependent caches after the plugin is updated.
	 */
	public function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) === ICONJET_VERSION ) {
			return;
		}
		Icon_Cache::flush();
		update_option( self::VERSION_OPTION, ICONJET_VERSION );
	}
}
