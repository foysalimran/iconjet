<?php
/**
 * Svg_Sanitizer tests.
 *
 * @package Iconjet
 */

use Iconjet\Custom_Icons;
use Iconjet\Icon_Renderer;
use Iconjet\Svg_Sanitizer;

/**
 * @covers \Iconjet\Svg_Sanitizer
 * @covers \Iconjet\Custom_Icons
 */
class Test_Svg_Sanitizer extends WP_UnitTestCase {

	private function fixture( $name ) {
		return file_get_contents( __DIR__ . '/fixtures/' . $name ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public function test_malicious_svg_is_neutralized() {
		$icon = Svg_Sanitizer::sanitize( $this->fixture( 'malicious.svg' ) );
		$this->assertIsArray( $icon );

		$all = strtolower( $icon['b'] . wp_json_encode( $icon['a'] ) );
		foreach ( array( '<script', 'onload', 'onclick', 'onmouseover', 'javascript:', '<a', '<image', '<use', 'foreignobject', '<style', 'evil.example', 'behavior', '<text', 'hello', 'xlink' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $all, "Found $needle" );
		}

		// The safe parts survive, and class/style colors became attributes.
		$this->assertSame( '0 0 24 24', $icon['v'] );
		$this->assertStringContainsString( '<path', $icon['b'] );
		$this->assertStringNotContainsString( '<circle', $icon['b'], 'Content of the removed <a> is dropped too' );
		$this->assertStringContainsString( 'fill="#e11d48"', $icon['b'] );
		$this->assertStringContainsString( 'fill="#0a66c2"', $icon['b'] );
	}

	public function test_rendered_custom_icon_is_safe() {
		$stored = Custom_Icons::add( $this->fixture( 'malicious.svg' ), 'Evil <script>' );
		$this->assertIsArray( $stored );
		$this->assertSame( 'evil', $stored['name'] );

		$svg = Icon_Renderer::render( 'custom:evil' );
		$this->assertStringContainsString( 'iconjet-custom', $svg );
		$this->assertStringNotContainsString( 'script', strtolower( $svg ) );
		$this->assertStringNotContainsString( 'onload', strtolower( $svg ) );
	}

	public function test_viewbox_from_dimensions_and_default_fill() {
		$icon = Svg_Sanitizer::sanitize( $this->fixture( 'clean.svg' ) );
		$this->assertSame( '0 0 32 32', $icon['v'] );
		$this->assertSame( 'currentColor', $icon['a']['fill'] );
		$this->assertStringContainsString( '<g><path d="M4 4h24v24H4z" /></g>', $icon['b'] );
	}

	public function test_gradients_keep_camel_case() {
		$icon = Svg_Sanitizer::sanitize( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><defs><linearGradient id="g" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#f00"/></linearGradient></defs><rect width="10" height="10" fill="url(#g)"/></svg>' );
		$this->assertStringContainsString( '<linearGradient id="g" gradientUnits="userSpaceOnUse">', $icon['b'] );
		$this->assertStringContainsString( '</linearGradient>', $icon['b'] );
		$this->assertStringContainsString( 'fill="url(#g)"', $icon['b'] );
	}

	public function test_xxe_payload_is_rejected() {
		$this->assertWPError( Svg_Sanitizer::sanitize( $this->fixture( 'xxe.svg' ) ) );
	}

	public function test_rejects_bad_input() {
		$this->assertWPError( Svg_Sanitizer::sanitize( '' ) );
		$this->assertWPError( Svg_Sanitizer::sanitize( 'not svg' ) );
		$this->assertWPError( Svg_Sanitizer::sanitize( '<html><body>x</body></html>' ) );
		$this->assertWPError( Svg_Sanitizer::sanitize( '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1"/></svg>' ) );
		$this->assertWPError( Svg_Sanitizer::sanitize( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><script>x</script></svg>' ) );
		$this->assertWPError( Svg_Sanitizer::sanitize( '<svg viewBox="0 0 1 1">' . str_repeat( ' ', Svg_Sanitizer::MAX_BYTES ) . '</svg>' ) );
	}

	public function test_custom_icon_names_are_unique_and_deletable() {
		$a = Custom_Icons::add( $this->fixture( 'clean.svg' ), 'My Icon' );
		$b = Custom_Icons::add( $this->fixture( 'clean.svg' ), 'My Icon' );
		$this->assertSame( 'my-icon', $a['name'] );
		$this->assertSame( 'my-icon-2', $b['name'] );
		$this->assertTrue( Custom_Icons::delete( 'my-icon' ) );
		$this->assertFalse( Custom_Icons::delete( 'my-icon' ) );
		$this->assertSame( array( 'my-icon-2' ), array_keys( Custom_Icons::all() ) );
	}
}
