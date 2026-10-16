<?php
/**
 * Shortcode tests.
 *
 * @package Iconjet
 */

use Iconjet\Shortcodes;

/**
 * @covers \Iconjet\Shortcodes
 * @covers \Iconjet\Social_Renderer
 */
class Test_Shortcodes extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		wp_dequeue_style( 'iconjet' );
	}

	public function test_icon_shortcode_renders_svg_and_enqueues_style() {
		$html = do_shortcode( '[iconjet name="lucide:heart" size="32" color="#e11d48"]' );
		$this->assertStringStartsWith( '<svg', $html );
		$this->assertStringContainsString( 'width="32"', $html );
		$this->assertStringContainsString( 'style="color:#e11d48"', $html );
		$this->assertStringContainsString( 'iconjet-shortcode', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
		$this->assertTrue( wp_style_is( 'iconjet', 'enqueued' ) );
	}

	public function test_icon_attributes_are_sanitized() {
		$html = do_shortcode( '[iconjet name=" LUCIDE:Heart " size="abc" color="red;background:url(x)" class="ok bad\"><script>" label="<b>Love</b>"]' );
		$this->assertStringContainsString( 'iconjet-lucide', $html );
		$this->assertStringContainsString( 'width="24"', $html, 'Invalid size falls back to the default' );
		$this->assertStringNotContainsString( 'style=', $html );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( 'aria-label="Love"', $html );
	}

	public function test_icon_link_and_new_tab() {
		$html = do_shortcode( '[iconjet name="tabler:mail" link="mailto:hello@example.com" label="Email us" new_tab="yes"]' );
		$this->assertMatchesRegularExpression( '#^<a class="iconjet-icon__link iconjet-shortcode-link" href="mailto:hello@example.com" target="_blank" rel="noopener" aria-label="Email us"><svg#', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html, 'The link has the name; the SVG is decorative' );

		$unsafe = do_shortcode( '[iconjet name="tabler:mail" link="javascript:alert(1)"]' );
		$this->assertStringNotContainsString( 'javascript', $unsafe );
		$this->assertStringStartsWith( '<svg', $unsafe, 'Unsafe link is dropped, icon stays' );

		$fallback = do_shortcode( '[iconjet name="fa:solid-house-chimney" link="https://example.com"]' );
		$this->assertStringContainsString( 'aria-label="house chimney"', $fallback, 'Linked icon without label uses its name' );
	}

	public function test_unknown_icon_outputs_debug_comment_only_with_wp_debug() {
		$this->assertTrue( WP_DEBUG );
		$this->assertSame( '<!-- Iconjet: unknown icon &quot;lucide:nope&quot; -->', do_shortcode( '[iconjet name="lucide:nope"]' ) );
		$this->assertSame( '<!-- Iconjet: unknown icon &quot;&gt;&lt;script&gt;&quot; -->', do_shortcode( '[iconjet name="--><script>"]' ) );
		$this->assertFalse( wp_style_is( 'iconjet', 'enqueued' ) );
	}

	public function test_social_shortcode_keeps_attribute_order_and_options() {
		$html = do_shortcode( '[iconjet_social x="https://x.com/a" facebook="https://facebook.com/b" email="mailto:c@example.com" style="rounded" size="40" gap="12" align="center" hover_color="#111111" nofollow="yes"]' );
		$this->assertMatchesRegularExpression( '/iconjet-social__link--x.*iconjet-social__link--facebook.*iconjet-social__link--email/s', $html );
		$this->assertStringContainsString( 'iconjet-social iconjet-social--rounded has-hover-color', $html );
		$this->assertStringContainsString( 'gap:12px;justify-content:center;list-style-type:none', $html );
		$this->assertStringContainsString( 'width:40px;height:40px;border-radius:22%;background-color:#0866FF;color:#FFFFFF;--iconjet-hover:#111111', $html );
		$this->assertStringContainsString( 'href="https://x.com/a" target="_blank" rel="noopener nofollow"', $html );
		$this->assertStringContainsString( 'href="mailto:c@example.com" rel="nofollow"', $html, 'mailto: never opens a new tab' );
		$this->assertTrue( wp_style_is( 'iconjet', 'enqueued' ) );
	}

	public function test_social_custom_colors_and_icon_style() {
		$html = do_shortcode( '[iconjet_social github="https://github.com/a" style="icon" brand_colors="no" color="#334155" new_tab="no"]' );
		$this->assertStringContainsString( 'width:28px;height:28px;color:#334155', $html );
		$this->assertStringNotContainsString( 'target=', $html );
		$this->assertStringContainsString( 'width="28"', $html, 'Icon-only glyph uses the full size' );
	}

	public function test_social_rejects_bad_values() {
		$html = do_shortcode( '[iconjet_social facebook="javascript:alert(1)" unknown="https://x.com" x="https://x.com/ok" style="evil" size="9999" gap="-3" color="url(x)"]' );
		$this->assertStringNotContainsString( 'javascript', $html );
		$this->assertStringNotContainsString( 'iconjet-social__link--facebook', $html );
		$this->assertStringContainsString( 'iconjet-social--circle', $html );
		$this->assertStringContainsString( 'width:128px', $html );
		$this->assertStringContainsString( 'gap:3px', $html, 'absint() turns -3 into 3' );
		$this->assertSame( '<!-- Iconjet: no valid social links -->', do_shortcode( '[iconjet_social facebook=""]' ) );
	}

	public function test_style_is_enqueued_before_wp_head_for_shortcode_posts() {
		$id = self::factory()->post->create( array( 'post_content' => 'Hi [iconjet name="lucide:heart"]' ) );
		$this->go_to( get_permalink( $id ) );
		Shortcodes::maybe_enqueue_early();
		$this->assertTrue( wp_style_is( 'iconjet', 'enqueued' ) );
	}

	public function test_preview_route_uses_shortcode_renderer() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$request = new WP_REST_Request( 'POST', '/iconjet/v1/preview' );
		$request->set_body_params(
			array(
				'type' => 'icon',
				'atts' => array(
					'name' => 'lucide:heart',
					'size' => '40',
				),
			)
		);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( do_shortcode( '[iconjet name="lucide:heart" size="40"]' ), $response->get_data()['html'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );
	}
}
