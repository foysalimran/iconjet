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

		/*
		 * Post content kses (wp_filter_post_kses, content_save_pre priority 10)
		 * for users without unfiltered_html. The SVG allowlist is active only
		 * between priority 9 and 11, and only for content with Iconjet blocks;
		 * every other wp_kses_post() call is unchanged. At priority 20 the
		 * result is hardened and SVG name case is restored.
		 */
		add_filter( 'content_save_pre', array( __CLASS__, 'begin_post_kses' ), 9 );
		add_filter( 'content_save_pre', array( __CLASS__, 'end_post_kses' ), 11 );
		add_filter( 'content_save_pre', array( __CLASS__, 'after_post_kses' ), 20 );
		add_filter( 'wp_kses_allowed_html', array( __CLASS__, 'allow_svg_in_post_content' ), 10, 2 );
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
	 * Whether the SVG allowlist is active (while saving Iconjet content).
	 *
	 * @var bool
	 */
	private static $allow_svg = false;

	/**
	 * Start of post kses: allow SVG if the content has Iconjet blocks.
	 *
	 * @param string $content Slashed post content.
	 * @return string
	 */
	public static function begin_post_kses( $content ) {
		self::$allow_svg = self::has_iconjet_blocks( $content );
		return $content;
	}

	/**
	 * End of post kses.
	 *
	 * @param string $content Slashed post content.
	 * @return string
	 */
	public static function end_post_kses( $content ) {
		self::$allow_svg = false;
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
		if ( ! self::$allow_svg || 'post' !== $context || ! is_array( $tags ) ) {
			return $tags;
		}
		foreach ( Kses::svg_for_post() as $tag => $attrs ) {
			$tags[ $tag ] = isset( $tags[ $tag ] ) && is_array( $tags[ $tag ] ) ? array_merge( $tags[ $tag ], $attrs ) : $attrs;
		}
		return $tags;
	}

	/**
	 * After post kses: harden SVG (only when kses ran) and restore name case.
	 *
	 * @param string $content Slashed post content.
	 * @return string
	 */
	public static function after_post_kses( $content ) {
		if ( ! self::has_iconjet_blocks( $content ) ) {
			return $content;
		}
		$content = wp_unslash( $content );
		if ( has_filter( 'content_save_pre', 'wp_filter_post_kses' ) ) {
			$content = self::harden_content( $content );
		}
		return wp_slash( Kses::restore_case( $content ) );
	}

	/**
	 * Apply Kses::harden() to the markup and to the SVG strings stored in
	 * Iconjet block attributes (which the editor renders).
	 *
	 * @param string $content Unslashed post content.
	 * @return string
	 */
	public static function harden_content( $content ) {
		$blocks  = parse_blocks( $content );
		$changed = false;
		$blocks  = self::harden_blocks( $blocks, $changed );
		if ( $changed ) {
			$content = serialize_blocks( $blocks );
		}
		return Kses::harden( $content );
	}

	/**
	 * Harden SVG strings in Iconjet block attributes, recursively.
	 *
	 * @param array $blocks  Parsed blocks.
	 * @param bool  $changed Set to true when anything changed.
	 * @return array
	 */
	private static function harden_blocks( array $blocks, &$changed ) {
		foreach ( $blocks as &$block ) {
			$attrs = &$block['attrs'];
			if ( 'iconjet/icon' === $block['blockName'] && isset( $attrs['svg']['b'] ) && is_string( $attrs['svg']['b'] ) ) {
				$clean = Kses::harden( $attrs['svg']['b'] );
				if ( $clean !== $attrs['svg']['b'] ) {
					$attrs['svg']['b'] = $clean;
					$changed           = true;
				}
			}
			if ( 'iconjet/social-icons' === $block['blockName'] && isset( $attrs['items'] ) && is_array( $attrs['items'] ) ) {
				foreach ( $attrs['items'] as &$item ) {
					if ( isset( $item['b'] ) && is_string( $item['b'] ) ) {
						$clean = Kses::harden( $item['b'] );
						if ( $clean !== $item['b'] ) {
							$item['b'] = $clean;
							$changed   = true;
						}
					}
				}
				unset( $item );
			}
			unset( $attrs );
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::harden_blocks( $block['innerBlocks'], $changed );
			}
		}//end foreach
		unset( $block );
		return $blocks;
	}

	/**
	 * Whether content contains Iconjet blocks.
	 *
	 * @param mixed $content Content.
	 * @return bool
	 */
	private static function has_iconjet_blocks( $content ) {
		return is_string( $content ) && false !== strpos( $content, 'wp:iconjet/' );
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
