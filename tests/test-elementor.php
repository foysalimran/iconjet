<?php
/**
 * Elementor adapter tests (Elementor itself is not loaded in the test suite).
 *
 * @package Iconjet
 */

use Iconjet\Icon_Cache;
use Iconjet\Integrations\Elementor;

/**
 * @covers \Iconjet\Integrations\Elementor
 */
class Test_Elementor extends WP_UnitTestCase {

	private function icon( $pack, $name ) {
		return array(
			'library' => 'iconjet-' . $pack,
			'value'   => "iconjet-e iconjet-e--$pack iconjet-e-$pack--$name",
		);
	}

	public function test_adapter_is_not_loaded_without_elementor() {
		$this->assertSame( 0, did_action( 'elementor/loaded' ) );
		$this->assertFalse( has_filter( 'elementor/icons_manager/additional_tabs' ) );
		$this->assertFalse( has_action( 'elementor/document/after_save' ) );
	}

	public function test_reference_from_icon_value() {
		$this->assertSame( 'lucide:heart', Elementor::ref_from_icon( $this->icon( 'lucide', 'heart' ) ) );
		$this->assertSame( 'material:arrow_back', Elementor::ref_from_icon( $this->icon( 'material', 'arrow_back' ) ) );
		$this->assertSame( 'tabler:a-b-2', Elementor::ref_from_icon( $this->icon( 'tabler', 'a-b-2' ) ) );
		$this->assertSame(
			'',
			Elementor::ref_from_icon(
				array(
					'library' => 'fa-solid',
					'value'   => 'fas fa-star',
				)
			)
		);
		$this->assertSame( '', Elementor::ref_from_icon( 'nope' ) );
	}

	public function test_render_callback_outputs_inline_svg() {
		$svg = Elementor::render_icon( $this->icon( 'lucide', 'heart' ), array( 'aria-hidden' => 'true' ) );
		$this->assertStringStartsWith( '<svg', $svg );
		$this->assertStringContainsString( 'e-font-icon-svg iconjet-elementor', $svg );
		$this->assertStringContainsString( 'fill="none"', $svg );
		$this->assertTrue( wp_style_is( 'iconjet', 'enqueued' ) );

		$labelled = Elementor::render_icon( $this->icon( 'simple', 'github' ), array( 'aria-label' => 'GitHub' ) );
		$this->assertStringContainsString( 'aria-label="GitHub"', $labelled );

		$this->assertSame( '', Elementor::render_icon( $this->icon( 'lucide', 'does-not-exist' ) ) );
	}

	public function test_refs_are_collected_from_element_data() {
		$data = array(
			array(
				'elType'   => 'container',
				'elements' => array(
					array(
						'widgetType' => 'icon',
						'settings'   => array( 'selected_icon' => $this->icon( 'lucide', 'heart' ) ),
					),
					array(
						'widgetType' => 'icon-list',
						'settings'   => array(
							'icon_list' => array(
								array( 'selected_icon' => $this->icon( 'phosphor', 'airplane' ) ),
								array(
									'selected_icon' => array(
										'library' => 'fa-solid',
										'value'   => 'fas fa-star',
									),
								),
							),
						),
					),
					array(
						'widgetType' => 'text-editor',
						'settings'   => array( 'editor' => '<p>[iconjet name="bootstrap:alarm"]</p>' ),
					),
				),
			),
		);
		$refs = array();
		Elementor::collect_refs( $data, $refs );
		$this->assertSame( array( 'lucide:heart', 'phosphor:airplane', 'bootstrap:alarm' ), $refs );
	}

	public function test_saving_a_document_warms_the_cache() {
		Icon_Cache::flush();
		$document = new class() {
			public function get_elements_data() {
				return array(
					array(
						'settings' => array(
							'selected_icon' => array(
								'library' => 'iconjet-tabler',
								'value'   => 'iconjet-e iconjet-e--tabler iconjet-e-tabler--rocket',
							),
						),
					),
				);
			}
		};
		Elementor::warm_cache( $document, array() );
		$this->assertNotNull( Icon_Cache::get( 'tabler:rocket' ) );
	}

	/**
	 * Markup as Elementor 4.3 prints a Social Icons item.
	 */
	private function social_item_html( $id, $icon_value ) {
		$parts  = explode( ' ', $icon_value, 2 );
		$social = str_replace( 'fa-', '', $parts[1] );
		return '<a class="' . esc_attr( 'elementor-icon elementor-social-icon elementor-social-icon-' . $social . ' elementor-repeater-item-' . $id ) . '" href="#">'
			. '<span class="elementor-screen-only">' . esc_html( ucwords( $social ) ) . '</span></a>';
	}

	public function test_social_icons_widget_gets_brand_classes_and_names() {
		$items = array(
			array( '_id' => 'aaa1111', 'social_icon' => $this->icon( 'simple', 'facebook' ) ),
			array( '_id' => 'bbb2222', 'social_icon' => $this->icon( 'tabler', 'heart' ) ),
			array(
				'_id'         => 'ccc3333',
				'social_icon' => array(
					'library' => 'fa-brands',
					'value'   => 'fab fa-facebook',
				),
			),
		);
		$html = '';
		foreach ( $items as $item ) {
			$html .= $this->social_item_html( $item['_id'], $item['social_icon']['value'] );
		}
		$widget = new class( $items ) {
			private $items;
			public function __construct( $items ) {
				$this->items = $items;
			}
			public function get_name() {
				return 'social-icons';
			}
			public function get_settings_for_display() {
				return array( 'social_icon_list' => $this->items );
			}
		};

		$out = Elementor::social_icons_content( $html, $widget );

		$this->assertStringContainsString( 'elementor-repeater-item-aaa1111 iconjet-brand-facebook"', $out );
		$this->assertStringContainsString( '<span class="elementor-screen-only">Facebook</span>', $out );
		// Not a platform logo: icon name, no brand class.
		$this->assertStringContainsString( '<span class="elementor-screen-only">Heart</span>', $out );
		$this->assertStringContainsString( 'elementor-repeater-item-bbb2222"', $out );
		// Elementor's own icons are untouched.
		$this->assertStringContainsString( 'elementor-repeater-item-ccc3333"', $out );
		$this->assertTrue( wp_style_is( Elementor::SOCIAL_STYLE, 'enqueued' ) );
	}

	public function test_other_widgets_are_untouched() {
		$widget = new class() {
			public function get_name() {
				return 'icon';
			}
		};
		$this->assertSame( '<p>x</p>', Elementor::social_icons_content( '<p>x</p>', $widget ) );
	}
}
