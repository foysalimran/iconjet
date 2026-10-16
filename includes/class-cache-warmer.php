<?php
/**
 * Fills Icon_Cache when content is saved.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the icons a post uses at save time (in the admin), so visitors
 * almost never trigger a full pack load.
 *
 * Only [iconjet] needs warming: [iconjet_social] icons ship inside
 * platforms.json and blocks store their SVG in post content.
 */
final class Cache_Warmer {

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'wp_after_insert_post', array( __CLASS__, 'on_save_post' ), 10, 2 );
	}

	/**
	 * Warm icons used by a saved post.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function on_save_post( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! $post instanceof \WP_Post ) {
			return;
		}
		self::warm( self::refs_from_content( $post->post_content ) );
	}

	/**
	 * Find icon references in [iconjet] shortcodes.
	 *
	 * @param string $content Content to scan.
	 * @return string[] Unique icon references.
	 */
	public static function refs_from_content( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, '[iconjet' ) ) {
			return array();
		}
		$refs = array();
		if ( preg_match_all( '/' . get_shortcode_regex( array( 'iconjet' ) ) . '/', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				// Skip escaped shortcodes: [[iconjet ...]].
				if ( '[' === $m[1] && ']' === $m[6] ) {
					continue;
				}
				$atts = shortcode_parse_atts( $m[3] );
				if ( is_array( $atts ) && ! empty( $atts['name'] ) ) {
					$refs[] = strtolower( trim( $atts['name'] ) );
				}
			}
		}
		return array_values( array_unique( $refs ) );
	}

	/**
	 * Resolve references so they land in Icon_Cache.
	 *
	 * @param string[] $refs Icon references.
	 */
	public static function warm( array $refs ) {
		foreach ( $refs as $ref ) {
			Icon_Registry::get_icon( $ref );
		}
	}
}
