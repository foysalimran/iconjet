<?php
/**
 * Social icons markup.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a list of social links.
 *
 * Mirrors src/blocks/social-icons/shared.js so the shortcode looks exactly
 * like the Social Icons block: shape, size and colors are inline styles; the
 * stylesheet only adds hover and focus states.
 */
final class Social_Renderer {

	const STYLES   = array( 'circle', 'rounded', 'square', 'icon' );
	const ALIGN    = array( 'left', 'center', 'right' );
	const MIN_SIZE = 16;
	const MAX_SIZE = 128;
	const MAX_GAP  = 64;

	const RADIUS = array(
		'circle'  => '50%',
		'rounded' => '22%',
		'square'  => '0',
	);

	const FALLBACK_BACKGROUND = '#1e1e1e';
	const FALLBACK_GLYPH      = '#ffffff';

	/**
	 * URL schemes allowed for social links (core protocols plus app links).
	 */
	const PROTOCOLS = array( 'http', 'https', 'mailto', 'tel', 'sms', 'viber', 'weixin' );

	/**
	 * Default arguments.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'style'        => Settings::get()['social_style'],
			'size'         => 28,
			'gap'          => 8,
			'brand_colors' => true,
			'color'        => '',
			'hover_color'  => '',
			'new_tab'      => true,
			'nofollow'     => false,
			'align'        => '',
			'class'        => '',
		);
	}

	/**
	 * Normalize arguments.
	 *
	 * @param array $args Raw arguments.
	 * @return array
	 */
	public static function sanitize_args( array $args ) {
		$args  = wp_parse_args( $args, self::defaults() );
		$style = sanitize_key( $args['style'] );
		$align = sanitize_key( $args['align'] );

		return array(
			'style'        => in_array( $style, self::STYLES, true ) ? $style : 'circle',
			'size'         => max( self::MIN_SIZE, min( self::MAX_SIZE, absint( $args['size'] ) ) ),
			'gap'          => min( self::MAX_GAP, absint( $args['gap'] ) ),
			'brand_colors' => (bool) $args['brand_colors'],
			'color'        => (string) sanitize_hex_color( (string) $args['color'] ),
			'hover_color'  => (string) sanitize_hex_color( (string) $args['hover_color'] ),
			'new_tab'      => (bool) $args['new_tab'],
			'nofollow'     => (bool) $args['nofollow'],
			'align'        => in_array( $align, self::ALIGN, true ) ? $align : '',
			'class'        => implode( ' ', Icon_Renderer::sanitize_classes( $args['class'] ) ),
		);
	}

	/**
	 * Sanitize a profile URL.
	 *
	 * @param string $url URL.
	 * @return string Empty when unsafe or empty.
	 */
	public static function sanitize_url( $url ) {
		return esc_url_raw( trim( (string) $url ), self::PROTOCOLS );
	}

	/**
	 * Render social links.
	 *
	 * @param array $links List of [ 'id' => platform id, 'url' => URL ].
	 * @param array $args  See defaults().
	 * @return string HTML, or '' when no valid links.
	 */
	public static function render( array $links, array $args = array() ) {
		$args  = self::sanitize_args( $args );
		$items = '';

		foreach ( $links as $link ) {
			$platform = isset( $link['id'] ) ? Social_Platforms::get( $link['id'] ) : null;
			$url      = isset( $link['url'] ) ? self::sanitize_url( $link['url'] ) : '';
			if ( ! $platform || '' === $url ) {
				continue;
			}
			$items .= self::render_item( $platform, $url, $args );
		}

		if ( '' === $items ) {
			return '';
		}

		$classes = array( 'iconjet-social', 'iconjet-social--' . $args['style'] );
		if ( $args['hover_color'] ) {
			$classes[] = 'has-hover-color';
		}
		if ( $args['class'] ) {
			$classes[] = $args['class'];
		}

		$style = array(
			'display'         => 'flex',
			'flex-wrap'       => 'wrap',
			'align-items'     => 'center',
			'gap'             => $args['gap'] . 'px',
			'justify-content' => $args['align'],
			'list-style-type' => 'none',
			'padding'         => '0',
		);

		return sprintf(
			'<ul class="%s" style="%s">%s</ul>',
			esc_attr( implode( ' ', $classes ) ),
			esc_attr( self::css( $style ) ),
			$items
		);
	}

	/**
	 * One list item.
	 *
	 * @param array  $platform Platform data.
	 * @param string $url      Sanitized URL.
	 * @param array  $args     Sanitized arguments.
	 * @return string
	 */
	private static function render_item( array $platform, $url, array $args ) {
		$is_app_link = (bool) preg_match( '/^(mailto|tel|sms):/i', $url );
		$rel         = array();
		$target      = '';
		if ( $args['new_tab'] && ! $is_app_link ) {
			$target = ' target="_blank"';
			$rel[]  = 'noopener';
		}
		if ( $args['nofollow'] ) {
			$rel[] = 'nofollow';
		}

		$glyph = 'icon' === $args['style'] ? $args['size'] : (int) round( $args['size'] * 0.55 );
		$svg   = Icon_Renderer::render_data(
			array(
				'v' => $platform['v'],
				'b' => $platform['b'],
				'a' => isset( $platform['a'] ) ? $platform['a'] : array( 'fill' => 'currentColor' ),
			),
			array( 'size' => $glyph )
		);

		return sprintf(
			'<li class="iconjet-social__item" style="margin:0"><a class="%s" href="%s"%s%s aria-label="%s" style="%s">%s</a></li>',
			esc_attr( 'iconjet-social__link iconjet-social__link--' . $platform['id'] ),
			esc_url( $url, self::PROTOCOLS ),
			$target,
			$rel ? ' rel="' . esc_attr( implode( ' ', $rel ) ) . '"' : '',
			esc_attr( $platform['label'] ),
			esc_attr( self::css( self::link_style( $platform, $args ) ) ),
			$svg
		);
	}

	/**
	 * Inline style for a link (see linkStyle() in shared.js).
	 *
	 * @param array $platform Platform.
	 * @param array $args     Sanitized arguments.
	 * @return array
	 */
	private static function link_style( array $platform, array $args ) {
		$style = array(
			'display'         => 'inline-flex',
			'align-items'     => 'center',
			'justify-content' => 'center',
			'width'           => $args['size'] . 'px',
			'height'          => $args['size'] . 'px',
		);
		if ( 'icon' === $args['style'] ) {
			$style['color'] = $args['brand_colors'] ? $platform['color'] : $args['color'];
		} else {
			$style['border-radius']    = self::RADIUS[ $args['style'] ];
			$style['background-color'] = $args['brand_colors'] ? $platform['color'] : ( $args['color'] ? $args['color'] : self::FALLBACK_BACKGROUND );
			$style['color']            = $args['brand_colors'] ? $platform['fg'] : self::FALLBACK_GLYPH;
		}
		if ( $args['hover_color'] ) {
			$style['--iconjet-hover'] = $args['hover_color'];
		}
		return $style;
	}

	/**
	 * Build a style attribute value, skipping empty values.
	 *
	 * @param array $rules Property => value.
	 * @return string
	 */
	private static function css( array $rules ) {
		$out = array();
		foreach ( $rules as $property => $value ) {
			if ( '' !== (string) $value ) {
				$out[] = $property . ':' . $value;
			}
		}
		return implode( ';', $out );
	}
}
