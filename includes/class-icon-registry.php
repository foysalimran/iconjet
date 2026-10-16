<?php
/**
 * Icon pack registry.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Knows which packs exist and resolves "pack:name" references to icon data.
 *
 * Pack JSON files are large (up to ~4 MB), so a pack is only decoded when an
 * icon is not already in Icon_Cache. Decoded packs are kept for the rest of
 * the request in a static property.
 */
final class Icon_Registry {

	/**
	 * Valid icon reference: "pack:name".
	 */
	const REF_PATTERN = '/^([a-z0-9-]+):([a-z0-9_-]+)$/';

	/**
	 * Pack id used for uploaded icons.
	 */
	const CUSTOM_PACK = 'custom';

	/**
	 * Parsed index.json, keyed by pack id.
	 *
	 * @var array|null
	 */
	private static $index = null;

	/**
	 * Decoded pack files for this request, keyed by pack id.
	 *
	 * @var array
	 */
	private static $packs = array();

	/**
	 * Split an icon reference into pack id and icon name.
	 *
	 * @param string $ref Icon reference, e.g. "lucide:heart".
	 * @return array{0:string,1:string}|null
	 */
	public static function parse_ref( $ref ) {
		if ( ! is_string( $ref ) || ! preg_match( self::REF_PATTERN, $ref, $m ) ) {
			return null;
		}
		return array( $m[1], $m[2] );
	}

	/**
	 * Bundled packs from index.json, keyed by id.
	 *
	 * @return array
	 */
	public static function index() {
		if ( null === self::$index ) {
			self::$index = array();
			$data        = wp_json_file_decode( ICONJET_DIR . 'assets/packs/index.json', array( 'associative' => true ) );
			if ( is_array( $data ) && isset( $data['packs'] ) && is_array( $data['packs'] ) ) {
				foreach ( $data['packs'] as $pack ) {
					self::$index[ $pack['id'] ] = $pack;
				}
			}
		}
		return self::$index;
	}

	/**
	 * Packs available for pickers: enabled bundled packs plus the custom pack
	 * when it has icons.
	 *
	 * @param bool $enabled_only Only return packs enabled in settings.
	 * @return array List of pack descriptors (no icon data).
	 */
	public static function packs( $enabled_only = true ) {
		$packs = array();
		foreach ( self::index() as $id => $pack ) {
			if ( $enabled_only && ! Settings::is_pack_enabled( $id ) ) {
				continue;
			}
			$pack['url'] = self::pack_url( $id );
			$packs[]     = $pack;
		}

		$custom_count = count( Custom_Icons::all() );
		if ( $custom_count ) {
			$packs[] = array(
				'id'         => self::CUSTOM_PACK,
				'label'      => __( 'Custom', 'iconjet' ),
				'style'      => '',
				'license'    => '',
				'count'      => $custom_count,
				'categories' => array(),
				'url'        => '',
			);
		}
		return $packs;
	}

	/**
	 * Whether a pack id exists (bundled or custom).
	 *
	 * @param string $id Pack id.
	 * @return bool
	 */
	public static function pack_exists( $id ) {
		return self::CUSTOM_PACK === $id || isset( self::index()[ $id ] );
	}

	/**
	 * Public URL of a bundled pack JSON file, used by the editor pickers.
	 *
	 * @param string $id Pack id.
	 * @return string
	 */
	public static function pack_url( $id ) {
		return add_query_arg( 'ver', ICONJET_VERSION, ICONJET_URL . 'assets/packs/' . rawurlencode( $id ) . '.json' );
	}

	/**
	 * Load a full pack. Expensive for bundled packs; prefer get_icon().
	 *
	 * @param string $id Pack id.
	 * @return array|null
	 */
	public static function get_pack( $id ) {
		if ( self::CUSTOM_PACK === $id ) {
			return Custom_Icons::as_pack();
		}
		if ( ! isset( self::index()[ $id ] ) ) {
			return null;
		}
		if ( ! array_key_exists( $id, self::$packs ) ) {
			// $id is validated against index.json above, so the path is safe.
			$data               = wp_json_file_decode( ICONJET_DIR . 'assets/packs/' . $id . '.json', array( 'associative' => true ) );
			self::$packs[ $id ] = is_array( $data ) && isset( $data['icons'] ) ? $data : null;
		}
		return self::$packs[ $id ];
	}

	/**
	 * Resolve an icon reference to render-ready data.
	 *
	 * Disabled packs still resolve, so existing content keeps rendering.
	 *
	 * @param string $ref Icon reference, e.g. "lucide:heart".
	 * @return array{v:string,b:string,a:array}|null viewBox, inner markup, root attributes.
	 */
	public static function get_icon( $ref ) {
		$parts = self::parse_ref( $ref );
		if ( ! $parts || ! self::pack_exists( $parts[0] ) ) {
			return null;
		}
		list( $pack_id, $name ) = $parts;

		if ( self::CUSTOM_PACK === $pack_id ) {
			$icon = Custom_Icons::get( $name );
			return $icon ? array(
				'v' => $icon['v'],
				'b' => $icon['b'],
				'a' => isset( $icon['a'] ) ? $icon['a'] : array(),
			) : null;
		}

		$cached = Icon_Cache::get( $ref );
		if ( null !== $cached ) {
			return $cached;
		}

		$pack = self::get_pack( $pack_id );
		if ( ! $pack || ! isset( $pack['icons'][ $name ] ) ) {
			return null;
		}
		$raw  = $pack['icons'][ $name ];
		$icon = array(
			'v' => (string) $raw['v'],
			'b' => (string) $raw['b'],
			'a' => array_merge(
				isset( $pack['attrs'] ) ? (array) $pack['attrs'] : array(),
				isset( $raw['a'] ) ? (array) $raw['a'] : array()
			),
		);
		Icon_Cache::set( $ref, $icon );
		return $icon;
	}

	/**
	 * Reset static state (tests).
	 */
	public static function reset() {
		self::$index = null;
		self::$packs = array();
	}
}
