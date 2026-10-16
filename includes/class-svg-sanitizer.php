<?php
/**
 * Sanitizer for uploaded SVG files.
 *
 * @package Iconjet
 */

namespace Iconjet;

use Iconjet\Vendor\enshrined\svgSanitize\Sanitizer;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API property names.

/**
 * Cleans an uploaded SVG and converts it to Iconjet icon data.
 *
 * Two layers:
 * 1. enshrined/svg-sanitize (bundled, namespace-prefixed with Strauss) removes
 *    scripts, event handlers, external references and XML attacks.
 * 2. The result is reduced to the strict Kses::svg() allowlist, which also
 *    drops <style>, <use>, <image>, <a> and anything not needed for an icon.
 *
 * CSS from style="" attributes and simple ".class { }" rules is converted to
 * presentation attributes first, so icons exported from design tools keep
 * their colors after <style> is removed.
 */
final class Svg_Sanitizer {

	const MAX_BYTES = 51200;
	// 50 KB.

	/**
	 * CSS properties converted to SVG presentation attributes.
	 */
	const CSS_PROPS = array(
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
		'clip-rule',
		'stop-color',
		'stop-opacity',
	);

	/**
	 * Root attributes kept as icon paint attributes.
	 */
	const ROOT_PAINT = array( 'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'fill-rule', 'clip-rule' );

	/**
	 * Sanitize an SVG document.
	 *
	 * @param string $dirty Raw SVG.
	 * @return array{v:string,b:string,a:array}|\WP_Error
	 */
	public static function sanitize( $dirty ) {
		if ( ! is_string( $dirty ) || '' === trim( $dirty ) ) {
			return new \WP_Error( 'iconjet_svg_empty', __( 'The file is empty.', 'iconjet' ) );
		}
		if ( strlen( $dirty ) > self::MAX_BYTES ) {
			return new \WP_Error( 'iconjet_svg_too_large', __( 'SVG files must be 50 KB or smaller.', 'iconjet' ) );
		}

		// DTDs and entities enable XXE and billion-laughs attacks; icons never need them.
		if ( preg_match( '/<!(DOCTYPE|ENTITY)/i', $dirty ) ) {
			return new \WP_Error( 'iconjet_svg_doctype', __( 'The file contains a DOCTYPE or entity declarations, which are not allowed for security reasons. Export the SVG again without them.', 'iconjet' ) );
		}

		$sanitizer = new Sanitizer();
		$sanitizer->removeRemoteReferences( true );
		$sanitizer->removeXMLTag( true );
		try {
			// Returns false for unparsable XML, including any DOCTYPE/entity (XXE) payload.
			$clean = $sanitizer->sanitize( $dirty );
		} catch ( \Throwable $e ) {
			// Thrown for well-formed XML without an <svg> root.
			$clean = false;
		}
		if ( ! is_string( $clean ) || '' === $clean ) {
			return new \WP_Error( 'iconjet_svg_invalid', __( 'The file is not a valid SVG.', 'iconjet' ) );
		}

		$previous = libxml_use_internal_errors( true );
		$doc      = new \DOMDocument();
		$loaded   = $doc->loadXML( $clean, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$root = $loaded ? $doc->documentElement : null;
		if ( ! $root || 'svg' !== strtolower( $root->localName ) ) {
			return new \WP_Error( 'iconjet_svg_invalid', __( 'The file is not a valid SVG.', 'iconjet' ) );
		}

		$view_box = self::view_box( $root );
		if ( '' === $view_box ) {
			return new \WP_Error( 'iconjet_svg_no_viewbox', __( 'The SVG needs a viewBox or a numeric width and height.', 'iconjet' ) );
		}

		self::inline_css( $doc );
		self::prune( $root );
		self::resolve_uses( $doc );

		$attrs = array();
		foreach ( self::ROOT_PAINT as $name ) {
			if ( $root->hasAttribute( $name ) ) {
				$attrs[ $name ] = $root->getAttribute( $name );
			}
		}
		// Icons without an explicit fill follow the text color.
		if ( ! isset( $attrs['fill'] ) ) {
			$attrs['fill'] = 'currentColor';
		}

		$body = '';
		foreach ( $root->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				$body .= $doc->saveXML( $child );
			}
		}
		// Strip namespace prefixes left by editors, then apply the allowlist.
		$body = preg_replace( '/\s+xmlns(:\w+)?="[^"]*"/', '', $body );
		$body = Kses::sanitize( $body );
		$body = trim( preg_replace( '/>\s+</', '><', $body ) );

		if ( '' === $body || ! preg_match( '/<(path|rect|circle|ellipse|line|polyline|polygon)\b/i', $body ) ) {
			return new \WP_Error( 'iconjet_svg_no_shapes', __( 'The SVG has no drawable shapes left after sanitizing.', 'iconjet' ) );
		}

		$attrs = array_map(
			static function ( $value ) {
				return preg_replace( '/[^\w#%.,()\s-]/', '', $value );
			},
			$attrs
		);

		return array(
			'v' => $view_box,
			'b' => $body,
			'a' => $attrs,
		);
	}

	/**
	 * Read the viewBox, or build one from numeric width/height.
	 *
	 * @param \DOMElement $root Root <svg>.
	 * @return string
	 */
	private static function view_box( \DOMElement $root ) {
		$view_box = trim( preg_replace( '/[\s,]+/', ' ', $root->getAttribute( 'viewBox' ) ) );
		if ( preg_match( '/^-?[\d.]+ -?[\d.]+ [\d.]+ [\d.]+$/', $view_box ) ) {
			return $view_box;
		}
		$width  = (float) $root->getAttribute( 'width' );
		$height = (float) $root->getAttribute( 'height' );
		if ( $width > 0 && $height > 0 ) {
			return '0 0 ' . $width . ' ' . $height;
		}
		return '';
	}

	/**
	 * Remove elements outside the allowlist together with their content, and
	 * all text and comment nodes. (wp_kses() alone would keep the inner text
	 * of a removed <text> or <a> element.)
	 *
	 * @param \DOMElement $element Element to clean.
	 */
	private static function prune( \DOMElement $element ) {
		$allowed = Kses::svg();
		foreach ( iterator_to_array( $element->childNodes ) as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType && isset( $allowed[ strtolower( $child->localName ) ] ) ) {
				self::prune( $child );
			} else {
				$element->removeChild( $child );
			}
		}
	}

	/**
	 * Keep <use> only when it references an element inside this SVG.
	 *
	 * The href (or legacy xlink:href) must be "#id" and the id must exist in
	 * the document after pruning, and must not be the <use> itself or one of
	 * its ancestors (a reference loop). Anything else, including external
	 * and data: URLs, removes the <use>. Kept references are written as plain
	 * href, which all current browsers support.
	 *
	 * @param \DOMDocument $doc Document.
	 */
	private static function resolve_uses( \DOMDocument $doc ) {
		$xlink = 'http://www.w3.org/1999/xlink';
		$ids   = array();
		foreach ( ( new \DOMXPath( $doc ) )->query( '//*[@id]' ) as $el ) {
			$ids[ $el->getAttribute( 'id' ) ] = $el;
		}

		foreach ( iterator_to_array( $doc->getElementsByTagName( 'use' ) ) as $use ) {
			$href = $use->hasAttribute( 'href' ) ? $use->getAttribute( 'href' ) : $use->getAttributeNS( $xlink, 'href' );
			$use->removeAttribute( 'href' );
			$use->removeAttributeNS( $xlink, 'href' );

			$target = null;
			if ( preg_match( '/^#([A-Za-z_][\w.-]*)$/', trim( $href ), $m ) && isset( $ids[ $m[1] ] ) ) {
				$target = $ids[ $m[1] ];
				for ( $node = $use; $node; $node = $node->parentNode ) {
					if ( $node === $target ) {
						$target = null;
						break;
					}
				}
			}

			if ( $target ) {
				$use->setAttribute( 'href', '#' . $m[1] );
			} elseif ( $use->parentNode ) {
				$use->parentNode->removeChild( $use );
			}
		}//end foreach
	}

	/**
	 * Move CSS from <style> class rules and style="" into presentation attributes.
	 *
	 * Only single-class selectors (".a" or ".a, .b") are supported; anything
	 * else is dropped with the <style> element.
	 *
	 * @param \DOMDocument $doc Document.
	 */
	private static function inline_css( \DOMDocument $doc ) {
		$class_rules = array();
		$styles      = array();
		foreach ( $doc->getElementsByTagName( 'style' ) as $style ) {
			$styles[] = $style;
			if ( preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $style->textContent, $rules, PREG_SET_ORDER ) ) {
				foreach ( $rules as $rule ) {
					$decls = self::parse_declarations( $rule[2] );
					foreach ( explode( ',', $rule[1] ) as $selector ) {
						$selector = trim( $selector );
						if ( preg_match( '/^\.([\w-]+)$/', $selector, $m ) ) {
							$class_rules[ $m[1] ] = array_merge( isset( $class_rules[ $m[1] ] ) ? $class_rules[ $m[1] ] : array(), $decls );
						}
					}
				}
			}
		}
		foreach ( $styles as $style ) {
			$style->parentNode->removeChild( $style );
		}

		foreach ( $doc->getElementsByTagName( '*' ) as $el ) {
			$decls = array();
			foreach ( preg_split( '/\s+/', $el->getAttribute( 'class' ), -1, PREG_SPLIT_NO_EMPTY ) as $class ) {
				if ( isset( $class_rules[ $class ] ) ) {
					$decls = array_merge( $decls, $class_rules[ $class ] );
				}
			}
			$decls = array_merge( $decls, self::parse_declarations( $el->getAttribute( 'style' ) ) );
			foreach ( $decls as $prop => $value ) {
				$el->setAttribute( $prop, $value );
			}
			$el->removeAttribute( 'class' );
			$el->removeAttribute( 'style' );
		}
	}

	/**
	 * Parse "a: b; c: d" into allowed property => value pairs.
	 *
	 * @param string $css Declarations.
	 * @return array
	 */
	private static function parse_declarations( $css ) {
		$out = array();
		foreach ( explode( ';', (string) $css ) as $decl ) {
			$pair = explode( ':', $decl, 2 );
			if ( 2 !== count( $pair ) ) {
				continue;
			}
			$prop  = strtolower( trim( $pair[0] ) );
			$value = trim( $pair[1] );
			// Paint values only: colors, numbers, keywords, url(#local-id).
			if ( in_array( $prop, self::CSS_PROPS, true ) && preg_match( '/^(?:[\w#%.,\s-]+|url\(#[\w-]+\))$/', $value ) ) {
				$out[ $prop ] = $value;
			}
		}
		return $out;
	}
}
