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

		return self::restore_case( $svg );
	}

	/**
	 * Restore camelCase SVG element and attribute names lowercased by kses.
	 *
	 * Also matches names inside JSON-encoded block attributes, where "<" is
	 * written as \u003c.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	public static function restore_case( $html ) {
		static $pattern = null, $map = null;
		if ( null === $pattern ) {
			$map     = array_combine( array_map( 'strtolower', self::CAMEL_CASE ), self::CAMEL_CASE );
			$pattern = '/(<\/?|\\\\u003c\/?|\s)(' . implode( '|', array_keys( $map ) ) . ')(?=[\s=>\/\\\\])/';
		}
		return preg_replace_callback(
			$pattern,
			static function ( $m ) use ( $map ) {
				return $m[1] . $map[ $m[2] ];
			},
			$html
		);
	}

	/**
	 * Presentation attributes that may reference a paint server, clip path,
	 * mask or marker with url(...).
	 */
	const URL_ATTRS = array( 'fill', 'stroke', 'clip-path', 'mask', 'filter', 'marker-start', 'marker-mid', 'marker-end' );

	/**
	 * Allowlist added to post kses while saving content with Iconjet blocks.
	 *
	 * Same as svg() but without the style attribute on <svg>, so users
	 * without unfiltered_html cannot add CSS (and url()) through it. Saved
	 * block markup never puts a style on the <svg>.
	 *
	 * @return array
	 */
	public static function svg_for_post() {
		$allowed = self::svg();
		unset( $allowed['svg']['style'] );
		return $allowed;
	}

	/**
	 * Remove references to anything outside the SVG from markup that kses
	 * already sanitized:
	 * - <use>: href / xlink:href must be "#id", otherwise the attribute is removed.
	 * - fill, stroke, clip-path, mask, filter, marker-*: a url(...) value
	 *   must be "url(#id)", otherwise the attribute is removed.
	 * - style on Iconjet elements (class contains "iconjet"): declarations
	 *   with url() are removed. Iconjet never writes url() in styles; core
	 *   kses would otherwise allow e.g. background-image:url() for authors.
	 *
	 * @param string $html Sanitized markup.
	 * @return string
	 */
	public static function harden( $html ) {
		if ( ! is_string( $html ) || ( false === stripos( $html, '<use' ) && ! preg_match( '/url\s*\(/i', $html ) ) ) {
			return $html;
		}
		$fragment = '/^#[A-Za-z_][\w.-]*$/';
		$local    = '/^url\(\s*[\'"]?#[A-Za-z_][\w.-]*[\'"]?\s*\)$/i';

		$tags = new \WP_HTML_Tag_Processor( $html );
		while ( $tags->next_tag() ) {
			if ( 'USE' === $tags->get_tag() ) {
				foreach ( array( 'href', 'xlink:href' ) as $name ) {
					$value = $tags->get_attribute( $name );
					if ( null !== $value && ( ! is_string( $value ) || ! preg_match( $fragment, trim( $value ) ) ) ) {
						$tags->remove_attribute( $name );
					}
				}
			}
			$class = $tags->get_attribute( 'class' );
			$style = $tags->get_attribute( 'style' );
			if ( is_string( $class ) && false !== strpos( $class, 'iconjet' ) && is_string( $style ) && preg_match( '/url\s*\(/i', $style ) ) {
				$keep = array_filter(
					array_map( 'trim', explode( ';', $style ) ),
					static function ( $decl ) {
						return '' !== $decl && ! preg_match( '/url\s*\(/i', $decl );
					}
				);
				$tags->set_attribute( 'style', implode( ';', $keep ) );
			}
			foreach ( self::URL_ATTRS as $name ) {
				$value = $tags->get_attribute( $name );
				if ( is_string( $value ) && preg_match( '/url\s*\(/i', $value ) && ! preg_match( $local, trim( $value ) ) ) {
					$tags->remove_attribute( $name );
				}
			}
		}//end while
		return $tags->get_updated_html();
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
