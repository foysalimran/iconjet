<?php
/**
 * Icon_Cache and Cache_Warmer tests.
 *
 * @package Iconjet
 */

use Iconjet\Cache_Warmer;
use Iconjet\Icon_Cache;
use Iconjet\Icon_Registry;

/**
 * @covers \Iconjet\Icon_Cache
 * @covers \Iconjet\Cache_Warmer
 */
class Test_Icon_Cache extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		Icon_Registry::reset();
		Icon_Cache::flush();
	}

	public function test_resolved_icon_is_saved_non_autoloaded() {
		Icon_Registry::get_icon( 'lucide:heart' );
		Icon_Cache::save();

		$data = get_option( Icon_Cache::OPTION );
		$this->assertSame( ICONJET_VERSION, $data['ver'] );
		$this->assertArrayHasKey( 'lucide:heart', $data['icons'] );

		global $wpdb;
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", Icon_Cache::OPTION ) );
		$this->assertContains( $autoload, array( 'no', 'off' ) );
	}

	public function test_cached_icon_is_served_without_loading_pack() {
		Icon_Cache::set(
			'lucide:heart',
			array(
				'v' => '0 0 1 1',
				'b' => '<path d="M0 0"/>',
				'a' => array(),
			)
		);
		Icon_Cache::save();
		Icon_Cache::reset();
		Icon_Registry::reset();

		$this->assertSame( '0 0 1 1', Icon_Registry::get_icon( 'lucide:heart' )['v'] );
	}

	public function test_cache_is_ignored_after_version_change() {
		update_option(
			Icon_Cache::OPTION,
			array(
				'ver'   => '0.0.1',
				'icons' => array( 'lucide:heart' => array( 'v' => 'old' ) ),
			),
			false
		);
		Icon_Cache::reset();
		$this->assertNull( Icon_Cache::get( 'lucide:heart' ) );
	}

	public function test_oldest_entries_are_evicted_over_cap() {
		for ( $i = 0; $i < Icon_Cache::MAX_ICONS + 5; $i++ ) {
			Icon_Cache::set(
				"lucide:i$i",
				array(
					'v' => '0 0 1 1',
					'b' => '',
					'a' => array(),
				)
			);
		}
		// Touching an old entry makes it newest, so it survives.
		Icon_Cache::set(
			'lucide:i0',
			array(
				'v' => '0 0 1 1',
				'b' => '',
				'a' => array(),
			)
		);
		Icon_Cache::save();
		Icon_Cache::reset();

		$this->assertSame( Icon_Cache::MAX_ICONS, Icon_Cache::count() );
		$this->assertNotNull( Icon_Cache::get( 'lucide:i0' ) );
		$this->assertNull( Icon_Cache::get( 'lucide:i1' ) );
		$this->assertNull( Icon_Cache::get( 'lucide:i5' ) );
		$this->assertNotNull( Icon_Cache::get( 'lucide:i6' ) );
	}

	public function test_refs_are_extracted_from_shortcodes() {
		$content = 'A [iconjet name="lucide:heart"] B [iconjet name=\'Tabler:Mail\' size="2"] [[iconjet name="fa:solid-skip"]] [iconjet_social x="y"] [iconjet name="lucide:heart"]';
		$this->assertSame( array( 'lucide:heart', 'tabler:mail' ), Cache_Warmer::refs_from_content( $content ) );
		$this->assertSame( array(), Cache_Warmer::refs_from_content( 'no shortcodes' ) );
	}

	public function test_saving_a_post_warms_the_cache() {
		self::factory()->post->create( array( 'post_content' => '[iconjet name="bootstrap:alarm"]' ) );
		$this->assertNotNull( Icon_Cache::get( 'bootstrap:alarm' ) );
	}
}
