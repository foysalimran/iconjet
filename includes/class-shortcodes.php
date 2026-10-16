<?php
/**
 * Shortcodes.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * [iconjet] and [iconjet_social], for the Classic Editor, widgets and any
 * page builder that runs shortcodes.
 *
 * Stylesheet loading: shortcodes usually run after wp_head, so the style is
 * (1) enqueued before wp_head when the queried post's content contains an
 * Iconjet shortcode, and (2) enqueued in the shortcode callback for every
 * other case (widgets, builders, templates). WordPress prints styles
 * enqueued after wp_head in wp_footer. The markup does not depend on the
 * stylesheet: size, shape and color are inline; CSS only adds hover/focus.
 */
final class Shortcodes {

	const ICON   = 'iconjet';
	const SOCIAL = 'iconjet_social';

	/**
	 * Attributes of [iconjet_social] that are not platform ids.
	 */
	const SOCIAL_OPTIONS = array( 'style', 'brand_colors', 'size', 'gap', 'color', 'hover_color', 'new_tab', 'nofollow', 'align', 'class' );

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'add_shortcodes' ) );
		add_action( 'wp', array( __CLASS__, 'maybe_enqueue_early' ) );
	}

	/**
	 * Register the shortcodes.
	 */
	public static function add_shortcodes() {
		add_shortcode( self::ICON, array( __CLASS__, 'icon' ) );
		add_shortcode( self::SOCIAL, array( __CLASS__, 'social' ) );
	}

	/**
	 * Enqueue the stylesheet in <head> when the main post uses a shortcode.
	 */
	public static function maybe_enqueue_early() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( $post instanceof \WP_Post && ( has_shortcode( $post->post_content, self::ICON ) || has_shortcode( $post->post_content, self::SOCIAL ) ) ) {
			Blocks::enqueue_style();
		}
	}

	/**
	 * Parse a yes/no style boolean.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function to_bool( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'on' ), true );
	}

	/**
	 * Normalized [iconjet] attributes.
	 *
	 * @param array|string $atts Raw attributes.
	 * @return array
	 */
	public static function icon_atts( $atts ) {
		$atts = shortcode_atts(
			array(
				'name'    => '',
				'size'    => Settings::get()['icon_size'],
				'color'   => '',
				'link'    => '',
				'label'   => '',
				'new_tab' => 'no',
				'class'   => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::ICON
		);

		return array(
			'name'    => strtolower( trim( (string) $atts['name'] ) ),
			'size'    => Icon_Renderer::sanitize_size( $atts['size'] ),
			'color'   => (string) sanitize_hex_color( trim( (string) $atts['color'] ) ),
			'link'    => esc_url_raw( trim( (string) $atts['link'] ) ),
			'label'   => sanitize_text_field( $atts['label'] ),
			'new_tab' => self::to_bool( $atts['new_tab'] ),
			'class'   => implode( ' ', Icon_Renderer::sanitize_classes( $atts['class'] ) ),
		);
	}

	/**
	 * [iconjet name="lucide:heart" size="32" color="#e11d48" link="" label="" new_tab="no" class=""]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function icon( $atts ) {
		$atts = self::icon_atts( $atts );
		$svg  = Icon_Renderer::render(
			$atts['name'],
			array(
				'size'  => $atts['size'],
				'color' => $atts['color'],
				'label' => $atts['link'] ? '' : $atts['label'],
				'class' => trim( 'iconjet-shortcode ' . ( $atts['link'] ? '' : $atts['class'] ) ),
			)
		);

		if ( '' === $svg ) {
			return self::debug_comment( sprintf( 'unknown icon "%s"', $atts['name'] ) );
		}

		Blocks::enqueue_style();

		if ( ! $atts['link'] ) {
			return $svg;
		}

		$name  = Icon_Registry::parse_ref( $atts['name'] );
		$label = '' !== $atts['label'] ? $atts['label'] : str_replace( array( '-', '_' ), ' ', preg_replace( '/^(solid|regular|brands)-/', '', $name[1] ) );

		return sprintf(
			'<a class="%s" href="%s"%s aria-label="%s">%s</a>',
			esc_attr( trim( 'iconjet-icon__link iconjet-shortcode-link ' . $atts['class'] ) ),
			esc_url( $atts['link'] ),
			$atts['new_tab'] ? ' target="_blank" rel="noopener"' : '',
			esc_attr( $label ),
			$svg
		);
	}

	/**
	 * Split [iconjet_social] attributes into links (in the order written) and options.
	 *
	 * @param array|string $atts Raw attributes.
	 * @return array{links: array, args: array}
	 */
	public static function social_atts( $atts ) {
		$atts  = is_array( $atts ) ? $atts : array();
		$links = array();
		$args  = array();

		foreach ( $atts as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}
			$key = strtolower( $key );
			if ( in_array( $key, self::SOCIAL_OPTIONS, true ) ) {
				$args[ $key ] = $value;
			} elseif ( Social_Platforms::get( $key ) ) {
				$links[] = array(
					'id'  => $key,
					'url' => $value,
				);
			}
		}

		foreach ( array( 'brand_colors', 'new_tab', 'nofollow' ) as $flag ) {
			if ( isset( $args[ $flag ] ) ) {
				$args[ $flag ] = self::to_bool( $args[ $flag ] );
			}
		}

		return array(
			'links' => $links,
			'args'  => Social_Renderer::sanitize_args( $args ),
		);
	}

	/**
	 * [iconjet_social facebook="url" x="url" style="circle" brand_colors="yes" size="28" ...]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function social( $atts ) {
		$parsed = self::social_atts( $atts );
		$html   = Social_Renderer::render( $parsed['links'], $parsed['args'] );
		if ( '' === $html ) {
			return self::debug_comment( 'no valid social links' );
		}
		Blocks::enqueue_style();
		return $html;
	}

	/**
	 * HTML comment shown only when WP_DEBUG is on.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	private static function debug_comment( $message ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return '';
		}
		return '<!-- Iconjet: ' . esc_html( str_replace( '--', '', $message ) ) . ' -->';
	}
}
