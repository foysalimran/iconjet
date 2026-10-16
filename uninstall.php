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
 * Delete Iconjet options for the current site.
 */
function iconjet_uninstall_site() {
	foreach ( array( 'iconjet_settings', 'iconjet_custom_icons', 'iconjet_icon_cache', 'iconjet_version' ) as $iconjet_option ) {
		delete_option( $iconjet_option );
	}
}

if ( is_multisite() ) {
	$iconjet_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $iconjet_site_ids as $iconjet_site_id ) {
		// Only options are deleted, so no plugins or theme need to load on the switched site.
		switch_to_blog( $iconjet_site_id ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog
		iconjet_uninstall_site();
		restore_current_blog();
	}
} else {
	iconjet_uninstall_site();
}
