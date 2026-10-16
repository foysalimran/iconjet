<?php
/**
 * Block registration.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Icon and Social Icons blocks.
 *
 * Both blocks are static: save() writes the full SVG into post content.
 */
final class Blocks {

	/**
	 * Frontend stylesheet handle (also used by shortcodes and Elementor).
	 */
	const STYLE = 'iconjet';

	const BLOCKS = array( 'iconjet/icon', 'iconjet/social-icons' );

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
		add_filter( 'wp_kses_allowed_html', array( __CLASS__, 'allow_svg_in_post_content' ), 10, 2 );
		// After wp_filter_post_kses (priority 10).
		add_filter( 'content_save_pre', array( __CLASS__, 'restore_svg_case' ), 20 );
		add_filter( 'block_core_navigation_listable_blocks', array( __CLASS__, 'navigation_listable_blocks' ) );
		foreach ( self::BLOCKS as $name ) {
			add_filter( 'render_block_' . $name, array( __CLASS__, 'enqueue_style_on_render' ) );
		}
	}

	/**
	 * Register the stylesheet and both blocks from build/.
	 */
	public static function register_blocks() {
		self::register_style();
		register_block_type( ICONJET_DIR . 'build/blocks/icon' );
		register_block_type( ICONJET_DIR . 'build/blocks/social-icons' );
	}

	/**
	 * Register the frontend stylesheet. Not enqueued here: see enqueue_style().
	 */
	public static function register_style() {
		if ( ! wp_style_is( self::STYLE, 'registered' ) ) {
			wp_register_style( self::STYLE, ICONJET_URL . 'assets/css/iconjet.css', array(), ICONJET_VERSION );
		}
	}

	/**
	 * Enqueue the stylesheet. Called only when Iconjet markup is output, so
	 * pages without Iconjet content load nothing. When called after wp_head
	 * (classic themes render content after the head), WordPress prints it in
	 * the footer.
	 */
	public static function enqueue_style() {
		self::register_style();
		wp_enqueue_style( self::STYLE );
	}

	/**
	 * Enqueue the stylesheet when a block renders.
	 *
	 * @param string $content Block content.
	 * @return string
	 */
	public static function enqueue_style_on_render( $content ) {
		if ( '' !== trim( (string) $content ) ) {
			self::enqueue_style();
		}
		return $content;
	}

	/**
	 * Allow Iconjet's SVG allowlist in post content.
	 *
	 * Users without unfiltered_html (authors, contributors, and admins on
	 * multisite) have post content filtered by kses on save. Without this,
	 * the saved SVG would be stripped and the blocks would become invalid.
	 * The allowlist is strict: no scripts, event handlers, styles, images or
	 * foreignObject (see Kses::svg()).
	 *
	 * @param array  $tags    Allowed tags.
	 * @param string $context Context.
	 * @return array
	 */
	public static function allow_svg_in_post_content( $tags, $context ) {
		if ( 'post' !== $context || ! is_array( $tags ) ) {
			return $tags;
		}
		foreach ( Kses::svg() as $tag => $attrs ) {
			$tags[ $tag ] = isset( $tags[ $tag ] ) && is_array( $tags[ $tag ] ) ? array_merge( $tags[ $tag ], $attrs ) : $attrs;
		}
		return $tags;
	}

	/**
	 * Undo kses lowercasing of SVG names (viewBox, linearGradient...) in
	 * content that contains Iconjet blocks.
	 *
	 * The kses filters lowercase attribute and element names, in the markup and in
	 * block attributes. The block editor compares names case-sensitively, so
	 * without this an author's saved block would be reported as invalid the
	 * next time it is opened. Runs after kses, so it only renames; it cannot
	 * add anything kses removed.
	 *
	 * @param string $content Slashed post content.
	 * @return string
	 */
	public static function restore_svg_case( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, 'wp:iconjet/' ) ) {
			return $content;
		}
		return wp_slash( Kses::restore_case( wp_unslash( $content ) ) );
	}

	/**
	 * Render Iconjet blocks inside Navigation as list items.
	 *
	 * @param array $blocks Block names wrapped in <li> by Navigation.
	 * @return array
	 */
	public static function navigation_listable_blocks( $blocks ) {
		return array_values( array_unique( array_merge( (array) $blocks, self::BLOCKS ) ) );
	}
}
