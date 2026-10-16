<?php
/**
 * SVG allowlist for wp_kses().
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Strict allowlist of SVG elements and attributes.
 *
 * Covers everything the bundled packs use (path, rect, circle, ellipse) plus
 * the shapes, groups, gradients, clip paths, masks, symbols and internal
 * <use> references found in typical uploaded icons (Figma exports reuse
 * shapes with <use href="#id">). Deliberately excluded: script, style,
 * foreignObject, image, a, animate*, set, and every event handler attribute.
 * <use> is only kept when its href is a "#fragment" (see sanitize()).
 *
 * Keys are lowercase because wp_kses() compares lowercased names.
 */
final class Kses {

	/**
	 * Cached allowlist.
	 *
	 * @var array|null
	 */
	private static $allowed = null;

	/**
	 * SVG names that are case-sensitive in XML. wp_kses() lowercases them.
	 */
	const CAMEL_CASE = array(
		'viewBox',
		'preserveAspectRatio',
		'pathLength',
		'gradientUnits',
		'gradientTransform',
		'spreadMethod',
		'clipPathUnits',
		'maskUnits',
		'maskContentUnits',
		'linearGradient',
		'radialGradient',
		'clipPath',
	);

	/**
	 * Run SVG markup through wp_kses() with the allowlist, then restore the
	 * camelCase names, so the SVG is also valid in XML contexts (feeds, XHTML).
	 * HTML parsers accept either case.
	 *
	 * @param string $svg SVG markup.
	 * @return string
	 */
	public static function sanitize( $svg ) {
		$svg = wp_kses( $svg, self::svg() );

		// Defense in depth: keep <use> only with a single internal "#id" href.
		// Svg_Sanitizer also checks that the id exists in the same SVG.
		$svg = preg_replace_callback(
			'/<use\b[^>]*>(?:\s*<\/use>)?/i',
			static function ( $m ) {
				$internal = preg_match( '/\shref="#[A-Za-z_][\w.-]*"/', $m[0] );
				$count    = preg_match_all( '/\s(?:xlink:)?href\s*=/i', $m[0] );
				return ( $internal && 1 === $count ) ? $m[0] : '';
			},
			$svg
		);

		static $pattern = null, $map = null;
		if ( null === $pattern ) {
			$map     = array_combine( array_map( 'strtolower', self::CAMEL_CASE ), self::CAMEL_CASE );
			$pattern = '/(<\/?|\s)(' . implode( '|', array_keys( $map ) ) . ')(?=[\s=>\/])/';
		}
		return preg_replace_callback(
			$pattern,
			static function ( $m ) use ( $map ) {
				return $m[1] . $map[ $m[2] ];
			},
			$svg
		);
	}

	/**
	 * Allowed SVG markup for wp_kses().
	 *
	 * @return array
	 */
	public static function svg() {
		if ( null !== self::$allowed ) {
			return self::$allowed;
		}

		$paint = array_fill_keys(
			array(
				'fill',
				'fill-opacity',
				'fill-rule',
				'stroke',
				'stroke-width',
				'stroke-linecap',
				'stroke-linejoin',
				'stroke-miterlimit',
				'stroke-dasharray',
				'stroke-dashoffset',
				'stroke-opacity',
				'opacity',
				'clip-path',
				'clip-rule',
				'mask',
				'transform',
				'vector-effect',
				'paint-order',
				'id',
			),
			true
		);

		$shape = static function ( array $attrs ) use ( $paint ) {
			return array_merge( $paint, array_fill_keys( $attrs, true ) );
		};

		$gradient = array_fill_keys(
			array( 'id', 'gradientunits', 'gradienttransform', 'spreadmethod', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'fx', 'fy', 'fr' ),
			true
		);

		self::$allowed = array(
			'svg'            => $shape(
				array( 'xmlns', 'viewbox', 'width', 'height', 'class', 'role', 'aria-hidden', 'aria-label', 'focusable', 'style', 'preserveaspectratio' )
			),
			'g'              => $paint,
			'path'           => $shape( array( 'd', 'pathlength' ) ),
			'rect'           => $shape( array( 'x', 'y', 'width', 'height', 'rx', 'ry' ) ),
			'circle'         => $shape( array( 'cx', 'cy', 'r' ) ),
			'ellipse'        => $shape( array( 'cx', 'cy', 'rx', 'ry' ) ),
			'line'           => $shape( array( 'x1', 'y1', 'x2', 'y2' ) ),
			'polyline'       => $shape( array( 'points' ) ),
			'polygon'        => $shape( array( 'points' ) ),
			'use'            => $shape( array( 'href', 'x', 'y', 'width', 'height' ) ),
			'symbol'         => array_fill_keys( array( 'id', 'viewbox', 'preserveaspectratio', 'x', 'y', 'width', 'height' ), true ),
			'defs'           => array(),
			'lineargradient' => $gradient,
			'radialgradient' => $gradient,
			'stop'           => array_fill_keys( array( 'offset', 'stop-color', 'stop-opacity' ), true ),
			'clippath'       => array_fill_keys( array( 'id', 'clippathunits', 'transform' ), true ),
			'mask'           => array_fill_keys( array( 'id', 'maskunits', 'maskcontentunits', 'x', 'y', 'width', 'height' ), true ),
		);

		return self::$allowed;
	}
}
