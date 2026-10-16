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

	public function test_kses_still_strips_dangerous_svg_for_authors() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		kses_init();
		$dirty = '<svg viewBox="0 0 1 1" onload="alert(1)"><script>alert(2)</script><path d="M0 0" onclick="x"/><use href="https://evil.example/a.svg#x"/></svg>';
		$clean = wp_kses_post( $dirty );
		$this->assertStringNotContainsString( 'onload', $clean );
		$this->assertStringNotContainsString( '<script', $clean );
		$this->assertStringNotContainsString( 'onclick', $clean );
		$this->assertStringContainsString( '<path', $clean );
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
