<?php
/**
 * Inline SVG renderer.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Turns icon data into an accessible inline <svg>.
 */
final class Icon_Renderer {

	const MIN_SIZE = 1;
	const MAX_SIZE = 1024;

	/**
	 * Render an icon by reference.
	 *
	 * @param string $ref  Icon reference, e.g. "lucide:heart".
	 * @param array  $args {
	 *     Optional.
	 *
	 *     @type int    $size  Width and height in px. Default: settings icon size.
	 *     @type string $label Accessible name. Empty = decorative (aria-hidden).
	 *     @type string $color Hex color applied via CSS color (icons paint with currentColor).
	 *     @type string $class Extra space-separated CSS classes.
	 * }
	 * @return string Sanitized SVG markup, or '' when the icon is unknown.
	 */
	public static function render( $ref, array $args = array() ) {
		$icon = Icon_Registry::get_icon( $ref );
		if ( ! $icon ) {
			return '';
		}
		$parts        = Icon_Registry::parse_ref( $ref );
		$args['pack'] = $parts[0];
		return self::render_data( $icon, $args );
	}

	/**
	 * Render icon data (viewBox, inner markup, root attributes).
	 *
	 * @param array $icon Icon data: v, b, a.
	 * @param array $args See render(); also accepts 'pack' for the pack class.
	 * @return string Sanitized SVG markup.
	 */
	public static function render_data( array $icon, array $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'size'  => Settings::get()['icon_size'],
				'label' => '',
				'color' => '',
				'class' => '',
				'pack'  => '',
			)
		);

		$size = self::sanitize_size( $args['size'] );

		$classes = array( 'iconjet-icon' );
		if ( '' !== $args['pack'] ) {
			$classes[] = 'iconjet-' . sanitize_html_class( $args['pack'] );
		}
		$classes = array_merge( $classes, self::sanitize_classes( $args['class'] ) );

		$attrs = array(
			'xmlns'   => 'http://www.w3.org/2000/svg',
			'viewBox' => isset( $icon['v'] ) ? $icon['v'] : '0 0 24 24',
			'width'   => $size,
			'height'  => $size,
		);
		// Root paint attributes (fill/stroke = currentColor, stroke widths...).
		foreach ( isset( $icon['a'] ) && is_array( $icon['a'] ) ? $icon['a'] : array() as $name => $value ) {
			$attrs[ $name ] = $value;
		}
		$attrs['class'] = implode( ' ', array_unique( $classes ) );

		$label = trim( wp_strip_all_tags( (string) $args['label'] ) );
		if ( '' !== $label ) {
			$attrs['role']       = 'img';
			$attrs['aria-label'] = $label;
		} else {
			$attrs['aria-hidden'] = 'true';
			$attrs['focusable']   = 'false';
		}

		$color = sanitize_hex_color( (string) $args['color'] );
		if ( $color ) {
			$attrs['style'] = 'color:' . $color;
		}

		$html = '<svg';
		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . $name . '="' . esc_attr( (string) $value ) . '"';
		}
		$html .= '>' . ( isset( $icon['b'] ) ? $icon['b'] : '' ) . '</svg>';

		return Kses::sanitize( $html );
	}

	/**
	 * Clamp a size to a positive integer in px.
	 *
	 * @param mixed $size Requested size.
	 * @return int
	 */
	public static function sanitize_size( $size ) {
		$size = absint( $size );
		if ( $size < self::MIN_SIZE ) {
			$size = (int) Settings::defaults()['icon_size'];
		}
		return min( $size, self::MAX_SIZE );
	}

	/**
	 * Sanitize a space-separated class list.
	 *
	 * @param string $classes Class list.
	 * @return string[]
	 */
	public static function sanitize_classes( $classes ) {
		$out = array();
		foreach ( preg_split( '/\s+/', (string) $classes, -1, PREG_SPLIT_NO_EMPTY ) as $class ) {
			$class = sanitize_html_class( $class );
			if ( '' !== $class ) {
				$out[] = $class;
			}
		}
		return $out;
	}
}
