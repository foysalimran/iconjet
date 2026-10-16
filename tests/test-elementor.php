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
}
