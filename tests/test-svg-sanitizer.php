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

	public function set_up() {
		parent::set_up();
		Custom_Icons::reset();
	}

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
		$result = Svg_Sanitizer::sanitize( $this->fixture( 'xxe.svg' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'iconjet_svg_doctype', $result->get_error_code() );
	}

	public function test_rename_keeps_reference() {
		$icon    = Custom_Icons::add( $this->fixture( 'clean.svg' ), 'Old name' );
		$renamed = Custom_Icons::rename( $icon['name'], 'New <b>name</b>' );
		$this->assertSame( 'old-name', $renamed['name'] );
		$this->assertSame( 'New name', $renamed['label'] );
		$this->assertWPError( Custom_Icons::rename( $icon['name'], '' ) );
		$this->assertWPError( Custom_Icons::rename( 'missing', 'x' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$request = new \WP_REST_Request( 'POST', '/iconjet/v1/custom-icons/old-name' );
		$request->set_body_params( array( 'label' => 'Nope' ) );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );
	}

	public function test_rejects_bad_input() {
		$this->assertWPError( Svg_Sanitizer::sanitize( '' ) );
		$this->assertWPError( Svg_Sanitizer::sanitize( 'not svg' ) );
		$this->assertWPError( Svg_Sanitizer::sanitize( '<html><body>x</body></html>' ) );
		$this->assertWPError( Svg_Sanitizer::sanitize( '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h1"/></svg>' ) );
		$this->assertWPError( Svg_Sanitizer::sanitize( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"><script>x</script></svg>' ) );
		$this->assertWPError( Svg_Sanitizer::sanitize( '<svg viewBox="0 0 1 1">' . str_repeat( ' ', Svg_Sanitizer::MAX_BYTES ) . '</svg>' ) );
	}

	public function test_custom_icons_are_stored_one_post_each() {
		$a = Custom_Icons::add( $this->fixture( 'clean.svg' ), 'My Icon' );
		$b = Custom_Icons::add( $this->fixture( 'clean.svg' ), 'My Icon' );
		$this->assertSame( 'my-icon', $a['name'] );
		$this->assertSame( 'my-icon-2', $b['name'] );
		$this->assertSame( 2, Custom_Icons::count() );

		$posts = get_posts(
			array(
				'post_type'   => Custom_Icons::POST_TYPE,
				'numberposts' => -1,
			)
		);
		$this->assertCount( 2, $posts );
		$this->assertSame( '0 0 32 32', get_post_meta( $posts[0]->ID, Custom_Icons::META_KEY, true )['v'] );

		Custom_Icons::reset();
		$this->assertSame( 'My Icon', Custom_Icons::get( 'my-icon-2' )['label'] );
		$this->assertStringContainsString( 'iconjet-custom', \Iconjet\Icon_Renderer::render( 'custom:my-icon-2' ) );

		$this->assertTrue( Custom_Icons::delete( 'my-icon' ) );
		$this->assertFalse( Custom_Icons::delete( 'my-icon' ) );
		$this->assertNull( Custom_Icons::get( 'my-icon' ) );
		$this->assertSame( array( 'my-icon-2' ), array_keys( Custom_Icons::all() ) );
		$this->assertSame( '', \Iconjet\Icon_Renderer::render( 'custom:my-icon' ) );
	}

	public function test_post_type_is_private() {
		$type = get_post_type_object( Custom_Icons::POST_TYPE );
		$this->assertFalse( $type->public );
		$this->assertFalse( $type->publicly_queryable );
		$this->assertTrue( $type->exclude_from_search );
		$this->assertFalse( $type->show_ui );
		$this->assertFalse( $type->show_in_rest );
		$this->assertFalse( $type->query_var );
		$this->assertFalse( $type->rewrite );

		Custom_Icons::add( $this->fixture( 'clean.svg' ), 'Searchable' );
		$this->assertSame(
			array(),
			get_posts(
				array(
					's'         => 'Searchable',
					'post_type' => 'any',
				)
			)
		);
		$this->assertArrayNotHasKey( '/wp/v2/' . Custom_Icons::POST_TYPE, rest_get_server()->get_routes() );
	}

	public function test_icon_over_20kb_after_sanitizing_is_rejected() {
		$paths = str_repeat( '<path d="M1 1h22v22H1zM2 2h20v20H2zM3 3h18v18H3z"/>', 420 );
		$svg   = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' . $paths . '</svg>';
		$this->assertLessThan( Svg_Sanitizer::MAX_BYTES, strlen( $svg ) );
		$this->assertGreaterThan( Custom_Icons::MAX_STORED_BYTES, strlen( $svg ) );

		$result = Custom_Icons::add( $svg, 'Big' );
		$this->assertWPError( $result );
		$this->assertSame( 'iconjet_svg_too_complex', $result->get_error_code() );
		$this->assertSame( 0, Custom_Icons::count() );
	}

	public function test_figma_internal_use_references_are_kept() {
		$icon = Svg_Sanitizer::sanitize( $this->fixture( 'figma.svg' ) );
		$this->assertIsArray( $icon );
		$this->assertSame( 2, substr_count( $icon['b'], 'href="#path0_fill"' ) );
		$this->assertSame( 1, substr_count( $icon['b'], 'href="#path1_fill"' ) );
		$this->assertStringNotContainsString( 'xlink', $icon['b'] );
		$this->assertStringContainsString( '<path id="path0_fill"', $icon['b'] );
		$this->assertStringContainsString( '<clipPath id="clip0_12_34">', $icon['b'] );
		$this->assertStringContainsString( 'clip-path="url(#clip0_12_34)"', $icon['b'] );

		$stored = Custom_Icons::add( $this->fixture( 'figma.svg' ), 'Figma' );
		$svg    = \Iconjet\Icon_Renderer::render( 'custom:' . $stored['name'] );
		$this->assertSame( 3, substr_count( $svg, '<use ' ) );
	}

	public function test_external_data_and_dangling_use_is_removed() {
		$icon = Svg_Sanitizer::sanitize( $this->fixture( 'use-attacks.svg' ) );
		$this->assertIsArray( $icon );
		$body = strtolower( $icon['b'] );

		// Survivors: <use href="#ok">, and the <use> that had both href="#ok"
		// and an external xlink:href (the external URL is stripped, the
		// internal reference stays). Every external, data:, javascript: and
		// dangling <use> is gone.
		$this->assertSame( 2, substr_count( $body, '<use' ), $icon['b'] );
		$this->assertSame( 2, substr_count( $body, '<use href="#ok"' ), $icon['b'] );
		foreach ( array( 'evil.example', 'data:', 'javascript', '#missing', 'xlink' ) as $needle ) {
			$this->assertStringNotContainsString( $needle, $body, "Found $needle" );
		}
	}

	public function test_use_reference_loop_rejects_file() {
		$this->assertWPError( Svg_Sanitizer::sanitize( $this->fixture( 'use-loop.svg' ) ) );
	}

	public function test_kses_drops_non_fragment_use_at_render_time() {
		$out = \Iconjet\Kses::sanitize( '<svg viewBox="0 0 1 1"><use href="#a"/><use href="https://evil.example/x.svg#a"/><use href="data:x"/></svg>' );
		$this->assertSame( 1, substr_count( $out, '<use' ) );
		$this->assertStringContainsString( 'href="#a"', $out );
	}

	public function test_uninstall_removes_icons_and_options() {
		Custom_Icons::add( $this->fixture( 'clean.svg' ), 'Gone' );
		update_option( 'iconjet_settings', array( 'icon_size' => 30 ) );
		update_option(
			'iconjet_icon_cache',
			array(
				'ver'   => ICONJET_VERSION,
				'icons' => array(),
			),
			false
		);

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'iconjet/iconjet.php' );
		}
		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertSame(
			array(),
			get_posts(
				array(
					'post_type'   => Custom_Icons::POST_TYPE,
					'post_status' => 'any',
					'numberposts' => -1,
				)
			)
		);
		$this->assertSame( 'gone', get_option( 'iconjet_settings', 'gone' ) );
		$this->assertSame( 'gone', get_option( 'iconjet_icon_cache', 'gone' ) );
	}
}
