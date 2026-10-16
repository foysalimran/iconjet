<?php
/**
 * Plugin Name:       Iconjet
 * Plugin URI:        https://iconjet.dev
 * Description:       Lightweight SVG icons and social icons for Gutenberg, Elementor, and any page builder.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            ThemeAtelier
 * Author URI:        https://themeatelier.net
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       iconjet
 * Domain Path:       /languages
 *
 * @package Iconjet
 */

defined( 'ABSPATH' ) || exit;

define( 'ICONJET_VERSION', '1.0.0' );
define( 'ICONJET_FILE', __FILE__ );
define( 'ICONJET_DIR', plugin_dir_path( __FILE__ ) );
define( 'ICONJET_URL', plugin_dir_url( __FILE__ ) );

/*
 * Autoloader.
 *
 * Iconjet\Icon_Registry           -> includes/class-icon-registry.php
 * Iconjet\Integrations\Elementor  -> includes/integrations/class-elementor.php
 * Iconjet\Vendor\enshrined\...    -> vendor-prefixed/enshrined/svg-sanitize/src/... (PSR-4, Strauss-prefixed)
 */
spl_autoload_register(
	static function ( $class_name ) {
		$vendor = 'Iconjet\\Vendor\\enshrined\\svgSanitize\\';
		if ( 0 === strpos( $class_name, $vendor ) ) {
			$file = ICONJET_DIR . 'vendor-prefixed/enshrined/svg-sanitize/src/' . str_replace( '\\', '/', substr( $class_name, strlen( $vendor ) ) ) . '.php';
		} elseif ( 0 === strpos( $class_name, 'Iconjet\\' ) ) {
			$parts = explode( '\\', substr( $class_name, strlen( 'Iconjet\\' ) ) );
			$name  = array_pop( $parts );
			$dir   = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
			$file  = ICONJET_DIR . 'includes/' . $dir . 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		} else {
			return;
		}
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

Iconjet\Plugin::instance()->boot();
