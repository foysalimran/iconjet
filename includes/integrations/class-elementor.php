<?php
/**
 * Elementor integration.
 *
 * @package Iconjet
 */

namespace Iconjet\Integrations;

use Iconjet\Blocks;
use Iconjet\Cache_Warmer;
use Iconjet\Custom_Icons;
use Iconjet\Icon_Registry;
use Iconjet\Icon_Renderer;
use Iconjet\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Iconjet packs to Elementor's Icon Library, rendered as inline SVG.
 *
 * Checked against Elementor 4.3.5 (includes/managers/icons.php, assets/js/editor.js):
 *
 * - Tabs: the `elementor/icons_manager/additional_tabs` filter. A tab config
 *   is name, label, labelIcon, prefix, displayPrefix, ver, plus either
 *   `fetchJson` (URL of { "icons": [ names ] }, fetched without credentials)
 *   or an inline `icons` array.
 * - Frontend: Icons_Manager::render_font_icon() calls the tab's
 *   `render_callback( $icon, $attributes, $tag )` before any font logic, so
 *   our SVG is used with the "Inline Font Icons" (e_font_icon_svg) feature
 *   both on and off. No `url` stylesheet is set: Frontend::enqueue_icon_fonts()
 *   would load it on every page that uses the library.
 * - Editor: the Icon Library modal and the live preview (renderIcon() in
 *   editor.js) only output <i class="{displayPrefix} {prefix}{name}"></i>,
 *   which is empty without an icon font. A small script (build/elementor/),
 *   loaded only in the editor and the preview iframe, fills those elements
 *   with the SVG from the pack files. No icon font is created.
 *
 * The stored control value is "iconjet-e iconjet-e--{pack} iconjet-e-{pack}--{name}"
 * with library "iconjet-{pack}". Icon names never contain "--".
 *
 * Font Awesome is not added (Elementor ships it). Disabled packs are left out
 * of the editor's Icon Library but stay registered on the frontend, so
 * existing content keeps rendering.
 */
final class Elementor {

	const CLASS_PREFIX = 'iconjet-e';
	const LIBRARY      = 'iconjet-';

	/**
	 * Packs never offered in Elementor.
	 */
	const SKIP = array( 'fa' );

	/**
	 * Whether hooks are registered.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Register hooks. Called only when Elementor is loaded.
	 */
	public static function register() {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		add_filter( 'elementor/icons_manager/additional_tabs', array( __CLASS__, 'add_tabs' ) );
		add_action( 'elementor/editor/after_enqueue_scripts', array( __CLASS__, 'enqueue_editor_script' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'enqueue_editor_script' ) );
		add_action( 'elementor/preview/enqueue_styles', array( Blocks::class, 'enqueue_style' ) );
		add_action( 'elementor/document/after_save', array( __CLASS__, 'warm_cache' ), 10, 2 );
	}

	/**
	 * Whether Elementor is rendering its editor or preview.
	 *
	 * @return bool
	 */
	private static function is_editor() {
		$plugin = \Elementor\Plugin::$instance;
		return ( $plugin->editor && $plugin->editor->is_edit_mode() ) || ( $plugin->preview && $plugin->preview->is_preview_mode() );
	}

	/**
	 * Add one tab per pack.
	 *
	 * @param array $tabs Additional tabs.
	 * @return array
	 */
	public static function add_tabs( $tabs ) {
		$in_editor = self::is_editor();

		foreach ( Icon_Registry::index() as $id => $pack ) {
			if ( in_array( $id, self::SKIP, true ) ) {
				continue;
			}
			// Hidden from the picker when disabled; still rendered on the frontend.
			if ( $in_editor && ! Settings::is_pack_enabled( $id ) ) {
				continue;
			}
			$tabs[ self::LIBRARY . $id ] = self::tab(
				$id,
				$pack['label'],
				array( 'fetchJson' => add_query_arg( 'ver', ICONJET_VERSION, ICONJET_URL . 'assets/elementor/' . rawurlencode( $id ) . '.json' ) ),
				ICONJET_VERSION
			);
		}

		$custom = $in_editor ? array_keys( Custom_Icons::all() ) : array();
		if ( ! $in_editor || $custom ) {
			// Inline list: custom icons change, and fetchJson requests carry no
			// credentials. The version changes with the list, so Elementor's
			// cached copy is refreshed.
			$tabs[ self::LIBRARY . Icon_Registry::CUSTOM_PACK ] = self::tab(
				Icon_Registry::CUSTOM_PACK,
				__( 'Custom', 'iconjet' ),
				array( 'icons' => $custom ),
				ICONJET_VERSION . '-' . substr( md5( implode( ',', $custom ) ), 0, 8 )
			);
		}

		return $tabs;
	}

	/**
	 * One tab config.
	 *
	 * @param string $id     Pack id.
	 * @param string $label  Pack label.
	 * @param array  $source fetchJson or icons.
	 * @param string $ver    Cache version.
	 * @return array
	 */
	private static function tab( $id, $label, array $source, $ver ) {
		return array(
			'name'            => self::LIBRARY . $id,
			/* translators: %s: icon pack name. */
			'label'           => sprintf( __( '%s (Iconjet)', 'iconjet' ), $label ),
			'labelIcon'       => 'eicon-star-o',
			'prefix'          => self::CLASS_PREFIX . '-' . $id . '--',
			'displayPrefix'   => self::CLASS_PREFIX . ' ' . self::CLASS_PREFIX . '--' . $id,
			'ver'             => $ver,
			'native'          => false,
			'render_callback' => array( __CLASS__, 'render_icon' ),
		) + $source;
	}

	/**
	 * Icon reference from an Elementor icon value.
	 *
	 * @param array $icon Elementor icon: [ 'value' => classes, 'library' => 'iconjet-{pack}' ].
	 * @return string "pack:name", or '' when not an Iconjet icon.
	 */
	public static function ref_from_icon( $icon ) {
		if ( ! is_array( $icon ) || empty( $icon['value'] ) || ! is_string( $icon['value'] ) ) {
			return '';
		}
		foreach ( preg_split( '/\s+/', $icon['value'] ) as $class ) {
			if ( preg_match( '/^' . self::CLASS_PREFIX . '-([a-z0-9-]+?)--([a-z0-9_-]+)$/', $class, $m ) ) {
				return $m[1] . ':' . $m[2];
			}
		}
		return '';
	}

	/**
	 * Tab render_callback: inline SVG for Elementor widgets.
	 *
	 * Elementor sizes and colors icons with CSS (font-size, color, fill); the
	 * SVG uses currentColor and our stylesheet keeps stroke icons unfilled.
	 *
	 * @param array  $icon       Icon value.
	 * @param array  $attributes HTML attributes Elementor wants on the icon.
	 * @param string $tag        Tag Elementor would use for a font icon (unused).
	 * @return string
	 */
	public static function render_icon( $icon, $attributes = array(), $tag = 'i' ) {
		unset( $tag );
		$ref = self::ref_from_icon( $icon );
		if ( '' === $ref ) {
			return '';
		}

		$classes = array( 'e-font-icon-svg', 'iconjet-elementor' );
		if ( ! empty( $attributes['class'] ) ) {
			$classes = array_merge( $classes, (array) $attributes['class'] );
		}

		$svg = Icon_Renderer::render(
			$ref,
			array(
				'class' => implode( ' ', array_map( 'strval', $classes ) ),
				'label' => isset( $attributes['aria-label'] ) ? (string) $attributes['aria-label'] : '',
			)
		);
		if ( '' !== $svg ) {
			Blocks::enqueue_style();
		}
		return $svg;
	}

	/**
	 * Editor/preview script that draws Iconjet icons in Elementor's editor.
	 */
	public static function enqueue_editor_script() {
		$asset_file = ICONJET_DIR . 'build/elementor/index.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;
		wp_enqueue_script( 'iconjet-elementor', ICONJET_URL . 'build/elementor/index.js', $asset['dependencies'], $asset['version'], true );

		$packs = array();
		foreach ( array_keys( Icon_Registry::index() ) as $id ) {
			if ( ! in_array( $id, self::SKIP, true ) ) {
				$packs[ $id ] = Icon_Registry::pack_url( $id );
			}
		}
		wp_add_inline_script(
			'iconjet-elementor',
			'window.iconjetElementor = ' . wp_json_encode(
				array(
					'packs'  => $packs,
					'custom' => rest_url( 'iconjet/v1/custom-icons' ),
					'nonce'  => wp_create_nonce( 'wp_rest' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Cache the Iconjet icons an Elementor document uses, at save time.
	 *
	 * @param \Elementor\Core\Base\Document $document Document.
	 * @param array                         $data     Saved data.
	 */
	public static function warm_cache( $document, $data ) {
		unset( $data );
		if ( ! is_object( $document ) || ! method_exists( $document, 'get_elements_data' ) ) {
			return;
		}
		$refs = array();
		self::collect_refs( (array) $document->get_elements_data(), $refs );
		Cache_Warmer::warm( array_values( array_unique( $refs ) ) );
	}

	/**
	 * Walk Elementor element data: icon controls and [iconjet] in any text.
	 *
	 * @param mixed $value Element data.
	 * @param array $refs  Collected references.
	 */
	public static function collect_refs( $value, array &$refs ) {
		if ( is_string( $value ) ) {
			$refs = array_merge( $refs, Cache_Warmer::refs_from_content( $value ) );
			return;
		}
		if ( ! is_array( $value ) ) {
			return;
		}
		if ( isset( $value['library'], $value['value'] ) && is_string( $value['library'] ) && 0 === strpos( $value['library'], self::LIBRARY ) ) {
			$ref = self::ref_from_icon( $value );
			if ( $ref ) {
				$refs[] = $ref;
			}
			return;
		}
		foreach ( $value as $item ) {
			self::collect_refs( $item, $refs );
		}
	}
}
