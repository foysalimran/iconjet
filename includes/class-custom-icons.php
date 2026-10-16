<?php
/**
 * Uploaded (custom) icons.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Stores uploaded icons as the "custom" pack.
 *
 * Storage: one post per icon in the private iconjet_icon post type.
 * - post_name  = icon name (the "name" in "custom:name")
 * - post_title = label
 * - post meta  = sanitized icon data (v, b, a)
 *
 * The SVG lives in post meta rather than post_content because content is run
 * through the HTML kses filters on save, which would rewrite the SVG.
 *
 * One post per icon keeps each row small (max MAX_STORED_BYTES), so rendering
 * one icon loads one icon, and no single query approaches MySQL's
 * max_allowed_packet. Only sanitized markup is stored, never the file.
 */
final class Custom_Icons {

	const POST_TYPE        = 'iconjet_icon';
	const META_KEY         = '_iconjet_svg';
	const MAX_ICONS        = 500;
	const MAX_STORED_BYTES = 20480;
	// 20 KB of sanitized markup.

	/**
	 * Icons resolved during this request, keyed by name (null = not found).
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Register the private post type (on init).
	 */
	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'               => __( 'Iconjet custom icons', 'iconjet' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
				'delete_with_user'    => false,
				'supports'            => array( 'title' ),
			)
		);
	}

	/**
	 * Number of custom icons.
	 *
	 * @return int
	 */
	public static function count() {
		$counts = wp_count_posts( self::POST_TYPE );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * All custom icons keyed by name. Used by the editor picker and admin.
	 *
	 * @return array
	 */
	public static function all() {
		$query = new \WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'posts_per_page'         => self::MAX_ICONS,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
			)
		);
		$icons = array();
		foreach ( $query->posts as $post ) {
			$icon = self::from_post( $post );
			if ( $icon ) {
				$icons[ $post->post_name ] = $icon;
			}
		}
		return $icons;
	}

	/**
	 * Get one custom icon.
	 *
	 * @param string $name Icon name.
	 * @return array|null
	 */
	public static function get( $name ) {
		if ( ! array_key_exists( $name, self::$cache ) ) {
			$post                 = self::find_post( $name );
			self::$cache[ $name ] = $post ? self::from_post( $post ) : null;
		}
		return self::$cache[ $name ];
	}

	/**
	 * Sanitize and store an SVG.
	 *
	 * @param string $svg   Raw SVG markup.
	 * @param string $label Display name (usually the file name).
	 * @return array|\WP_Error Stored icon including its 'name', or an error.
	 */
	public static function add( $svg, $label ) {
		if ( self::count() >= self::MAX_ICONS ) {
			/* translators: %d: maximum number of custom icons. */
			return new \WP_Error( 'iconjet_custom_limit', sprintf( __( 'You can store up to %d custom icons.', 'iconjet' ), self::MAX_ICONS ) );
		}

		$icon = Svg_Sanitizer::sanitize( $svg );
		if ( is_wp_error( $icon ) ) {
			return $icon;
		}
		if ( strlen( $icon['v'] . $icon['b'] . wp_json_encode( $icon['a'] ) ) > self::MAX_STORED_BYTES ) {
			return new \WP_Error( 'iconjet_svg_too_complex', __( 'This icon is too complex: it must be 20 KB or smaller after sanitizing. Simplify it in your design tool and try again.', 'iconjet' ) );
		}

		$label = sanitize_text_field( $label );
		$label = '' !== $label ? $label : __( 'Custom icon', 'iconjet' );

		// wp_insert_post() expects slashed data and unslashes it, meta included.
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $label,
					'post_name'   => self::base_name( $label ),
					'meta_input'  => array( self::META_KEY => $icon ),
				)
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// WordPress made the slug unique (my-icon, my-icon-2, ...).
		$name = get_post_field( 'post_name', $post_id );
		unset( self::$cache[ $name ] );

		return array(
			'name'  => $name,
			'label' => $label,
		) + $icon;
	}

	/**
	 * Delete a custom icon.
	 *
	 * @param string $name Icon name.
	 * @return bool Whether it existed.
	 */
	public static function delete( $name ) {
		$post = self::find_post( $name );
		if ( ! $post ) {
			return false;
		}
		wp_delete_post( $post->ID, true );
		unset( self::$cache[ $name ] );
		return true;
	}

	/**
	 * Custom icons in the same format as a bundled pack file.
	 *
	 * @return array
	 */
	public static function as_pack() {
		$icons = array();
		foreach ( self::all() as $name => $icon ) {
			$icons[ $name ] = array(
				'v' => $icon['v'],
				'b' => $icon['b'],
				'a' => $icon['a'],
				't' => array( strtolower( $icon['label'] ) ),
			);
		}
		return array(
			'id'         => Icon_Registry::CUSTOM_PACK,
			'label'      => __( 'Custom', 'iconjet' ),
			'license'    => '',
			'count'      => count( $icons ),
			'attrs'      => array(),
			'categories' => array(),
			'icons'      => $icons,
		);
	}

	/**
	 * Reset the request cache (tests).
	 */
	public static function reset() {
		self::$cache = array();
	}

	/**
	 * Find the post for an icon name.
	 *
	 * @param string $name Icon name.
	 * @return \WP_Post|null
	 */
	private static function find_post( $name ) {
		if ( ! is_string( $name ) || ! preg_match( '/^[a-z0-9_-]+$/', $name ) ) {
			return null;
		}
		$query = new \WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => 'publish',
				'name'                   => $name,
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
			)
		);
		return $query->posts ? $query->posts[0] : null;
	}

	/**
	 * Icon data from a post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array|null
	 */
	private static function from_post( \WP_Post $post ) {
		$data = get_post_meta( $post->ID, self::META_KEY, true );
		if ( ! is_array( $data ) || empty( $data['v'] ) || empty( $data['b'] ) ) {
			return null;
		}
		return array(
			'v'     => (string) $data['v'],
			'b'     => (string) $data['b'],
			'a'     => isset( $data['a'] ) && is_array( $data['a'] ) ? $data['a'] : array(),
			'label' => $post->post_title,
		);
	}

	/**
	 * Base icon name from a label: lowercase a-z, 0-9, "-" and "_".
	 *
	 * @param string $label Label.
	 * @return string
	 */
	private static function base_name( $label ) {
		$base = preg_replace( '/[^a-z0-9_-]/', '', str_replace( ' ', '-', strtolower( remove_accents( $label ) ) ) );
		$base = trim( preg_replace( '/-+/', '-', $base ), '-_' );
		return '' !== $base ? substr( $base, 0, 60 ) : 'icon';
	}
}
