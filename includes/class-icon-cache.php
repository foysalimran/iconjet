<?php
/**
 * Persistent cache of resolved icons.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Stores icons used by shortcodes and Elementor in one non-autoloaded option,
 * so a page view reads a small option instead of decoding a multi-MB pack.
 *
 * - Keyed by plugin version: an update invalidates everything.
 * - Capped at MAX_ICONS; the oldest entries are evicted first (entries are
 *   kept in insertion order, so the head of the array is the oldest).
 * - Writes are batched and saved once on shutdown.
 *
 * Block content does not use this cache: blocks save their SVG in post content.
 */
final class Icon_Cache {

	const OPTION    = 'iconjet_icon_cache';
	const MAX_ICONS = 2000;

	/**
	 * Cached icons keyed by reference, or null when not loaded yet.
	 *
	 * @var array|null
	 */
	private static $icons = null;

	/**
	 * Whether there are unsaved changes.
	 *
	 * @var bool
	 */
	private static $dirty = false;

	/**
	 * Load the option once per request.
	 */
	private static function load() {
		if ( null !== self::$icons ) {
			return;
		}
		$data        = get_option( self::OPTION );
		self::$icons = is_array( $data ) && isset( $data['ver'], $data['icons'] ) && ICONJET_VERSION === $data['ver'] && is_array( $data['icons'] )
			? $data['icons']
			: array();
	}

	/**
	 * Get a cached icon.
	 *
	 * @param string $ref Icon reference.
	 * @return array|null
	 */
	public static function get( $ref ) {
		self::load();
		return isset( self::$icons[ $ref ] ) ? self::$icons[ $ref ] : null;
	}

	/**
	 * Add or refresh an icon. Saved on shutdown.
	 *
	 * @param string $ref  Icon reference.
	 * @param array  $icon Icon data (v, b, a).
	 */
	public static function set( $ref, array $icon ) {
		self::load();
		// Re-insert so the entry moves to the end (newest).
		unset( self::$icons[ $ref ] );
		self::$icons[ $ref ] = $icon;

		if ( ! self::$dirty ) {
			self::$dirty = true;
			add_action( 'shutdown', array( __CLASS__, 'save' ) );
		}
	}

	/**
	 * Persist pending changes, evicting the oldest entries over the cap.
	 */
	public static function save() {
		if ( ! self::$dirty ) {
			return;
		}
		self::$dirty = false;

		if ( count( self::$icons ) > self::MAX_ICONS ) {
			self::$icons = array_slice( self::$icons, -self::MAX_ICONS, null, true );
		}
		update_option(
			self::OPTION,
			array(
				'ver'   => ICONJET_VERSION,
				'icons' => self::$icons,
			),
			false
		);
	}

	/**
	 * Number of cached icons.
	 *
	 * @return int
	 */
	public static function count() {
		self::load();
		return count( self::$icons );
	}

	/**
	 * Delete the cache.
	 */
	public static function flush() {
		delete_option( self::OPTION );
		self::reset();
	}

	/**
	 * Reset static state without touching the database (tests).
	 */
	public static function reset() {
		if ( self::$dirty ) {
			remove_action( 'shutdown', array( __CLASS__, 'save' ) );
		}
		self::$icons = null;
		self::$dirty = false;
	}
}
