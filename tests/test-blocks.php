<?php
/**
 * Block registration and post content kses tests.
 *
 * @package Iconjet
 */

/**
 * @covers \Iconjet\Blocks
 */
class Test_Blocks extends WP_UnitTestCase {

	private function fixture() {
		return file_get_contents( __DIR__ . '/fixtures/blocks.html' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	public function test_blocks_are_registered() {
		$registry = WP_Block_Type_Registry::get_instance();
		$this->assertTrue( $registry->is_registered( 'iconjet/icon' ) );
		$this->assertTrue( $registry->is_registered( 'iconjet/social-icons' ) );
		$this->assertSame( 'content', $registry->get_registered( 'iconjet/icon' )->attributes['icon']['role'] );
	}

	/**
	 * Authors lack unfiltered_html, so their content goes through kses.
	 * Saved block markup (SVG, inline styles, CSS custom properties) must
	 * survive unchanged, or the blocks would become invalid.
	 */
	public function test_author_saved_block_markup_survives_kses() {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$this->assertFalse( current_user_can( 'unfiltered_html' ) );
		kses_init();

		$content = $this->fixture();
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_title'   => 'Author post',
					'post_content' => $content,
					'post_status'  => 'publish',
					'post_author'  => $author,
				)
			)
		);

		$this->assertSame( $content, get_post( $post_id )->post_content );
	}

	/**
	 * An author saves an Iconjet block carrying script, event handlers,
	 * foreignObject, external <use> references and external url() paint.
	 * Everything outside the allowlist is stripped from the markup and from
	 * the SVG stored in the block attributes; internal references stay.
	 */
	public function test_author_cannot_inject_through_an_iconjet_block() {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		kses_init();

		$body  = '<path d="M0 0h24v24H0z" onclick="alert(1)" fill="url(https://evil.example/p.svg#g)"/>'
			. '<use href="https://evil.example/s.svg#a" fill="url(#ok)"/>'
			. '<use xlink:href="javascript:alert(2)"/>'
			. '<use href="#ok"/>';
		$attrs = serialize_block_attributes(
			array(
				'icon' => 'custom:x',
				'svg'  => array(
					'v' => '0 0 24 24',
					'b' => $body,
					'a' => array( 'fill' => 'currentColor' ),
				),
			)
		);
		$html  = '<div style="display:flex" class="wp-block-iconjet-icon"><span class="iconjet-icon__box" style="display:inline-flex;line-height:0;background-image:url(https://evil.example/bg.png)">'
			. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" onload="alert(3)" style="background:url(https://evil.example/x.png)">'
			. $body . '<script>alert(4)</script><foreignObject><div onclick="alert(5)">x</div></foreignObject>'
			. '</svg></span></div>';

		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_title'   => 'Attack',
					'post_status'  => 'publish',
					'post_author'  => $author,
					'post_content' => "<!-- wp:iconjet/icon $attrs -->\n$html\n<!-- /wp:iconjet/icon -->",
				)
			)
		);
		$saved   = get_post( $post_id )->post_content;
		$blocks  = parse_blocks( $saved );
		$stored  = $blocks[0]['attrs']['svg']['b'];

		foreach ( array( $saved, $stored ) as $markup ) {
			$lower = strtolower( $markup );
			foreach ( array( 'onload', 'onclick', '<script', 'foreignobject', 'evil.example', 'javascript:' ) as $needle ) {
				$this->assertStringNotContainsString( $needle, $lower, "Found $needle" );
			}
			$this->assertStringContainsString( '<use href="#ok"', $markup, 'Internal reference kept' );
			$this->assertStringContainsString( 'fill="url(#ok)"', $markup, 'Internal url() kept' );
		}
		$this->assertStringContainsString( 'viewBox="0 0 24 24"', $saved );
		$this->assertDoesNotMatchRegularExpression( '/<svg[^>]*style=/', $saved, 'No style on <svg> for authors' );
	}

	public function test_svg_is_not_allowed_outside_iconjet_post_saves() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertStringNotContainsString( '<svg', wp_kses_post( '<svg viewBox="0 0 1 1"><path d="M0 0"/></svg>' ) );

		// Content without Iconjet blocks keeps core behavior: SVG is removed.
		kses_init();
		$post_id = wp_insert_post(
			wp_slash(
				array(
					'post_title'   => 'Plain',
					'post_content' => '<p>x</p><svg viewBox="0 0 1 1"><path d="M0 0"/></svg>',
				)
			)
		);
		$this->assertStringNotContainsString( '<svg', get_post( $post_id )->post_content );
	}

	public function test_plugin_does_not_change_safe_style_css() {
		$this->assertFalse( has_filter( 'safe_style_css' ) );
	}

	public function test_style_loads_only_when_a_block_renders() {
		wp_dequeue_style( 'iconjet' );
		do_blocks( '<!-- wp:paragraph --><p>No icons</p><!-- /wp:paragraph -->' );
		$this->assertFalse( wp_style_is( 'iconjet', 'enqueued' ) );

		do_blocks( $this->fixture() );
		$this->assertTrue( wp_style_is( 'iconjet', 'enqueued' ) );
	}

	public function test_navigation_wraps_iconjet_blocks_in_list_items() {
		$filtered = apply_filters( 'block_core_navigation_listable_blocks', array() );
		$this->assertContains( 'iconjet/icon', $filtered );
		$this->assertContains( 'iconjet/social-icons', $filtered );
	}
}
