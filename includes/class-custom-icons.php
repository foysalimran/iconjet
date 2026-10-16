<?php
/**
 * Uploaded (custom) icons.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Stores uploaded icons as the "custom" pack.
 *
 * Storage: one non-autoloaded option (iconjet_custom_icons). Only sanitized
 * markup is stored, never the uploaded file. An option fits the expected
 * volume (capped at MAX_ICONS x 50 KB, typically a few KB each), needs no
 * schema or migrations, is read in one query, and is removed by uninstall.php
 * with a single delete_option(). A custom table would add schema upkeep for
 * no benefit at this size.
 */
final class Custom_Icons {

	const OPTION    = 'iconjet_custom_icons';
	const MAX_ICONS = 500;

	/**
	 * All custom icons keyed by name.
	 *
	 * @return array
	 */
	public static function all() {
		$icons = get_option( self::OPTION, array() );
		return is_array( $icons ) ? $icons : array();
	}

	/**
	 * Get one custom icon.
	 *
	 * @param string $name Icon name.
	 * @return array|null
	 */
	public static function get( $name ) {
		$icons = self::all();
		return isset( $icons[ $name ] ) ? $icons[ $name ] : null;
	}

	/**
	 * Sanitize and store an SVG.
	 *
	 * @param string $svg   Raw SVG markup.
	 * @param string $label Display name (usually the file name).
	 * @return array|\WP_Error Stored icon including its 'name', or an error.
	 */
	public static function add( $svg, $label ) {
		$icons = self::all();
		if ( count( $icons ) >= self::MAX_ICONS ) {
			/* translators: %d: maximum number of custom icons. */
			return new \WP_Error( 'iconjet_custom_limit', sprintf( __( 'You can store up to %d custom icons.', 'iconjet' ), self::MAX_ICONS ) );
		}

		$icon = Svg_Sanitizer::sanitize( $svg );
		if ( is_wp_error( $icon ) ) {
			return $icon;
		}

		$label = sanitize_text_field( $label );
		$label = '' !== $label ? $label : __( 'Custom icon', 'iconjet' );
		$name  = self::unique_name( $label, $icons );

		$icon['label']   = $label;
		$icon['created'] = time();
		$icons[ $name ]  = $icon;
		update_option( self::OPTION, $icons, false );

		return array( 'name' => $name ) + $icon;
	}

	/**
	 * Delete a custom icon.
	 *
	 * @param string $name Icon name.
	 * @return bool Whether it existed.
	 */
	public static function delete( $name ) {
		$icons = self::all();
		if ( ! isset( $icons[ $name ] ) ) {
			return false;
		}
		unset( $icons[ $name ] );
		update_option( self::OPTION, $icons, false );
		return true;
	}

	/**
	 * Custom icons in the same format as a bundled pack file.
	 *
	 * @return array
	 */
	public static function as_pack() {
		$icons = array();
		foreach ( self::all() as $name => $icon ) {
			$icons[ $name ] = array(
				'v' => $icon['v'],
				'b' => $icon['b'],
				'a' => isset( $icon['a'] ) ? $icon['a'] : array(),
				't' => array( strtolower( $icon['label'] ) ),
			);
		}
		return array(
			'id'         => Icon_Registry::CUSTOM_PACK,
			'label'      => __( 'Custom', 'iconjet' ),
			'license'    => '',
			'count'      => count( $icons ),
			'attrs'      => array(),
			'categories' => array(),
			'icons'      => $icons,
		);
	}

	/**
	 * Build a unique icon name from a label.
	 *
	 * @param string $label Label.
	 * @param array  $icons Existing icons.
	 * @return string
	 */
	private static function unique_name( $label, array $icons ) {
		$base = preg_replace( '/[^a-z0-9_-]/', '', str_replace( ' ', '-', strtolower( remove_accents( $label ) ) ) );
		$base = trim( preg_replace( '/-+/', '-', $base ), '-_' );
		$base = '' !== $base ? substr( $base, 0, 60 ) : 'icon';
		$name = $base;
		$i    = 2;
		while ( isset( $icons[ $name ] ) ) {
			$name = $base . '-' . $i;
			++$i;
		}
		return $name;
	}
}
