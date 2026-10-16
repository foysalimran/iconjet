<?php
/**
 * Plugin settings.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Settings stored in one option, registered with the Settings API and
 * exposed to the admin app through /wp/v2/settings.
 */
final class Settings {

	const OPTION        = 'iconjet_settings';
	const SOCIAL_STYLES = array( 'circle', 'square', 'rounded', 'icon' );

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'packs'        => array_fill_keys( array_keys( Icon_Registry::index() ), true ),
			'icon_size'    => 24,
			'social_style' => 'circle',
		);
	}

	/**
	 * Current settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$defaults = self::defaults();
		$saved    = get_option( self::OPTION, array() );
		$saved    = is_array( $saved ) ? $saved : array();

		$settings          = array_merge( $defaults, array_intersect_key( $saved, $defaults ) );
		$settings['packs'] = array_merge(
			$defaults['packs'],
			isset( $saved['packs'] ) && is_array( $saved['packs'] ) ? array_intersect_key( $saved['packs'], $defaults['packs'] ) : array()
		);
		return $settings;
	}

	/**
	 * Whether a pack is enabled. The custom pack is always enabled.
	 *
	 * @param string $id Pack id.
	 * @return bool
	 */
	public static function is_pack_enabled( $id ) {
		if ( Icon_Registry::CUSTOM_PACK === $id ) {
			return true;
		}
		$packs = self::get()['packs'];
		return ! empty( $packs[ $id ] );
	}

	/**
	 * Register the setting (runs on init and rest_api_init).
	 */
	public static function register() {
		$pack_properties = array();
		foreach ( array_keys( Icon_Registry::index() ) as $id ) {
			$pack_properties[ $id ] = array( 'type' => 'boolean' );
		}

		register_setting(
			'iconjet',
			self::OPTION,
			array(
				'type'              => 'object',
				'description'       => __( 'Iconjet settings.', 'iconjet' ),
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'packs'        => array(
								'type'                 => 'object',
								'additionalProperties' => false,
								'properties'           => $pack_properties,
							),
							'icon_size'    => array(
								'type'    => 'integer',
								'minimum' => 8,
								'maximum' => 512,
							),
							'social_style' => array(
								'type' => 'string',
								'enum' => self::SOCIAL_STYLES,
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Sanitize settings before they are saved.
	 *
	 * @param mixed $input Raw value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$current  = self::get();

		$packs = array();
		foreach ( array_keys( $defaults['packs'] ) as $id ) {
			$packs[ $id ] = isset( $input['packs'][ $id ] )
				? rest_sanitize_boolean( $input['packs'][ $id ] )
				: $current['packs'][ $id ];
		}

		$size = isset( $input['icon_size'] ) ? absint( $input['icon_size'] ) : $current['icon_size'];
		$size = max( 8, min( 512, $size ) );

		$style = isset( $input['social_style'] ) ? sanitize_key( $input['social_style'] ) : $current['social_style'];
		if ( ! in_array( $style, self::SOCIAL_STYLES, true ) ) {
			$style = $defaults['social_style'];
		}

		return array(
			'packs'        => $packs,
			'icon_size'    => $size,
			'social_style' => $style,
		);
	}
}
