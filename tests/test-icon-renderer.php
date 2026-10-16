<?php
/**
 * Icon_Renderer tests.
 *
 * @package Iconjet
 */

use Iconjet\Icon_Cache;
use Iconjet\Icon_Registry;
use Iconjet\Icon_Renderer;

/**
 * @covers \Iconjet\Icon_Renderer
 */
class Test_Icon_Renderer extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		Icon_Registry::reset();
		Icon_Cache::flush();
	}

	public function test_renders_decorative_icon() {
		$svg = Icon_Renderer::render( 'lucide:heart', array( 'size' => 32 ) );

		$this->assertStringStartsWith( '<svg', $svg );
		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $svg );
		$this->assertStringContainsString( 'width="32"', $svg );
		$this->assertStringContainsString( 'height="32"', $svg );
		$this->assertStringContainsString( 'stroke="currentColor"', $svg );
		$this->assertStringContainsString( 'fill="none"', $svg );
		$this->assertStringContainsString( 'class="iconjet-icon iconjet-lucide"', $svg );
		$this->assertStringContainsString( 'aria-hidden="true"', $svg );
		$this->assertStringContainsString( 'focusable="false"', $svg );
		$this->assertStringNotContainsString( 'role=', $svg );
		$this->assertStringContainsString( '<path', $svg );
	}

	public function test_label_makes_icon_accessible() {
		$svg = Icon_Renderer::render( 'tabler:mail', array( 'label' => 'Email <b>us</b>' ) );

		$this->assertStringContainsString( 'role="img"', $svg );
		$this->assertStringContainsString( 'aria-label="Email us"', $svg );
		$this->assertStringNotContainsString( 'aria-hidden', $svg );
	}

	public function test_fill_pack_uses_current_color() {
		$svg = Icon_Renderer::render( 'simple:github' );
		$this->assertStringContainsString( 'fill="currentColor"', $svg );
		$this->assertStringContainsString( 'iconjet-simple', $svg );
	}

	public function test_color_size_and_class_are_sanitized() {
		$svg = Icon_Renderer::render(
			'lucide:heart',
			array(
				'color' => '#e11d48',
				'size'  => '99999',
				'class' => 'my-class "><script>x</script> other',
			)
		);
		$this->assertStringContainsString( 'style="color:#e11d48"', $svg );
		$this->assertStringContainsString( 'width="1024"', $svg );
		$this->assertStringContainsString( 'my-class', $svg );
		$this->assertStringNotContainsString( '<script', $svg );
		$this->assertStringNotContainsString( '">', substr( $svg, 0, strpos( $svg, 'class=' ) ) );

		$bad = Icon_Renderer::render( 'lucide:heart', array( 'color' => 'red;background:url(x)' ) );
		$this->assertStringNotContainsString( 'style=', $bad );
	}

	public function test_default_size_comes_from_settings() {
		update_option( 'iconjet_settings', array( 'icon_size' => 40 ) );
		$this->assertStringContainsString( 'width="40"', Icon_Renderer::render( 'lucide:heart' ) );
	}

	public function test_unknown_or_invalid_refs_render_nothing() {
		$this->assertSame( '', Icon_Renderer::render( 'lucide:does-not-exist' ) );
		$this->assertSame( '', Icon_Renderer::render( 'nopack:heart' ) );
		$this->assertSame( '', Icon_Renderer::render( '../../wp-config:x' ) );
		$this->assertSame( '', Icon_Renderer::render( 'lucide' ) );
		$this->assertSame( '', Icon_Renderer::render( '' ) );
	}

	public function test_disabled_pack_still_renders() {
		update_option( 'iconjet_settings', array( 'packs' => array( 'lucide' => false ) ) );
		$this->assertNotSame( '', Icon_Renderer::render( 'lucide:heart' ) );
		$ids = wp_list_pluck( Icon_Registry::packs( true ), 'id' );
		$this->assertNotContains( 'lucide', $ids );
	}

	public function test_every_pack_renders_its_first_icon() {
		foreach ( array_keys( Icon_Registry::index() ) as $pack_id ) {
			$pack = Icon_Registry::get_pack( $pack_id );
			$name = array_key_first( $pack['icons'] );
			$svg  = Icon_Renderer::render( $pack_id . ':' . $name );
			$this->assertMatchesRegularExpression( '/<(path|rect|circle|ellipse)\b/', $svg, "$pack_id:$name" );
		}
	}
}
