<?php
/**
 * Social platform data.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Reads assets/social/platforms.json (small: each platform embeds its SVG).
 */
final class Social_Platforms {

	/**
	 * Platforms keyed by id, or null when not loaded yet.
	 *
	 * @var array|null
	 */
	private static $platforms = null;

	/**
	 * All platforms keyed by id, in display order.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$platforms ) {
			self::$platforms = array();
			$data            = wp_json_file_decode( ICONJET_DIR . 'assets/social/platforms.json', array( 'associative' => true ) );
			if ( is_array( $data ) && isset( $data['platforms'] ) && is_array( $data['platforms'] ) ) {
				foreach ( $data['platforms'] as $platform ) {
					self::$platforms[ $platform['id'] ] = $platform;
				}
			}
		}
		return self::$platforms;
	}

	/**
	 * One platform.
	 *
	 * @param string $id Platform id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * The platform whose logo is an icon reference.
	 *
	 * @param string $ref Icon reference "pack:name".
	 * @return array|null
	 */
	public static function by_icon( $ref ) {
		foreach ( self::all() as $platform ) {
			if ( isset( $platform['icon'] ) && $platform['icon'] === $ref ) {
				return $platform;
			}
		}
		return null;
	}
}
