<?php
/**
 * REST permission tests.
 *
 * @package Iconjet
 */

/**
 * @covers \Iconjet\Rest_Controller
 */
class Test_Rest extends WP_UnitTestCase {

	public function test_routes_require_capabilities() {
		$cases = array(
			array( 'GET', '/iconjet/v1/packs', 'subscriber', 403 ),
			array( 'GET', '/iconjet/v1/packs', 'editor', 200 ),
			array( 'GET', '/iconjet/v1/custom-icons', 'editor', 200 ),
			array( 'POST', '/iconjet/v1/custom-icons', 'editor', 403 ),
			array( 'DELETE', '/iconjet/v1/custom-icons/x', 'editor', 403 ),
			array( 'POST', '/iconjet/v1/custom-icons', 'administrator', 400 ),
			array( 'DELETE', '/iconjet/v1/custom-icons/missing', 'administrator', 404 ),
		);
		foreach ( $cases as list( $method, $route, $role, $status ) ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
			$response = rest_do_request( new WP_REST_Request( $method, $route ) );
			$this->assertSame( $status, $response->get_status(), "$method $route as $role" );
		}
		wp_set_current_user( 0 );
		$this->assertSame( 401, rest_do_request( new WP_REST_Request( 'GET', '/iconjet/v1/packs' ) )->get_status() );
	}

	public function test_packs_response_respects_settings() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		update_option( 'iconjet_settings', array( 'packs' => array( 'tabler' => false ) ) );
		$data = rest_do_request( new WP_REST_Request( 'GET', '/iconjet/v1/packs' ) )->get_data();
		$ids  = wp_list_pluck( $data['packs'], 'id' );
		$this->assertContains( 'lucide', $ids );
		$this->assertNotContains( 'tabler', $ids );
		$this->assertArrayNotHasKey( 'icons', $data['packs'][0] );
		$this->assertStringContainsString( 'assets/packs/lucide.json', $data['packs'][0]['url'] );
	}

	public function test_settings_are_sanitized() {
		$saved = \Iconjet\Settings::sanitize(
			array(
				'packs'        => array(
					'lucide' => '0',
					'bogus'  => true,
				),
				'icon_size'    => 9999,
				'social_style' => 'evil',
			)
		);
		$this->assertFalse( $saved['packs']['lucide'] );
		$this->assertArrayNotHasKey( 'bogus', $saved['packs'] );
		$this->assertSame( 512, $saved['icon_size'] );
		$this->assertSame( 'circle', $saved['social_style'] );
	}
}
