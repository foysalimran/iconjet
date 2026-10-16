<?php
/**
 * Removes all Iconjet data when the plugin is deleted.
 *
 * Content created with the blocks keeps its saved SVG markup.
 *
 * @package Iconjet
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete Iconjet options and custom icon posts for the current site.
 */
function iconjet_uninstall_site() {
	foreach ( array( 'iconjet_settings', 'iconjet_icon_cache', 'iconjet_version' ) as $iconjet_option ) {
		delete_option( $iconjet_option );
	}

	// Custom icons: one post per icon in the iconjet_icon post type. The
	// post type is not registered during uninstall, so query every status.
	do {
		$iconjet_query = new WP_Query(
			array(
				'post_type'              => 'iconjet_icon',
				'post_status'            => array_keys( get_post_stati() ),
				'fields'                 => 'ids',
				'posts_per_page'         => 100,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		$iconjet_ids   = $iconjet_query->posts;
		foreach ( $iconjet_ids as $iconjet_id ) {
			wp_delete_post( $iconjet_id, true );
		}
	} while ( $iconjet_ids );
}

if ( is_multisite() ) {
	$iconjet_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $iconjet_site_ids as $iconjet_site_id ) {
		// Only options and posts are deleted, so no plugins or theme need to load on the switched site.
		switch_to_blog( $iconjet_site_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog
		iconjet_uninstall_site();
		restore_current_blog();
	}
} else {
	iconjet_uninstall_site();
}
