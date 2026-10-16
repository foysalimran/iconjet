<?php
/**
 * REST API.
 *
 * @package Iconjet
 */

namespace Iconjet;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under iconjet/v1.
 *
 * Authentication is WordPress cookie auth with the wp_rest nonce
 * (X-WP-Nonce header sent by @wordpress/api-fetch). Every route checks a
 * capability in permission_callback.
 *
 *   GET    /packs                 edit_posts       Enabled packs, social data URL, editor defaults.
 *   GET    /custom-icons          edit_posts       Custom pack (same format as pack files).
 *   POST   /custom-icons          manage_options   Upload an SVG (multipart field "file").
 *   DELETE /custom-icons/{name}   manage_options   Delete a custom icon.
 *
 * Settings are saved through the core /wp/v2/settings endpoint (see Settings).
 */
final class Rest_Controller {

	const NAMESPACE_V1 = 'iconjet/v1';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/packs',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_packs' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/custom-icons',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_custom_icons' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_custom_icon' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'label' => array(
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/custom-icons/(?P<name>[a-z0-9_-]+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_custom_icon' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'name' => array(
						'type'     => 'string',
						'pattern'  => '^[a-z0-9_-]+$',
						'required' => true,
					),
				),
			)
		);
	}

	/**
	 * Editors and above may read icon data.
	 *
	 * @return bool
	 */
	public function can_edit() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Only administrators may change custom icons.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /packs
	 *
	 * @return \WP_REST_Response
	 */
	public function get_packs() {
		$settings = Settings::get();
		return rest_ensure_response(
			array(
				'packs'      => Icon_Registry::packs( true ),
				'social_url' => add_query_arg( 'ver', ICONJET_VERSION, ICONJET_URL . 'assets/social/platforms.json' ),
				'defaults'   => array(
					'icon_size'    => $settings['icon_size'],
					'social_style' => $settings['social_style'],
				),
			)
		);
	}

	/**
	 * GET /custom-icons
	 *
	 * @return \WP_REST_Response
	 */
	public function get_custom_icons() {
		return rest_ensure_response( Custom_Icons::as_pack() );
	}

	/**
	 * POST /custom-icons
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_custom_icon( \WP_REST_Request $request ) {
		$files = $request->get_file_params();
		$file  = isset( $files['file'] ) && is_array( $files['file'] ) ? $files['file'] : null;

		if ( ! $file || ! isset( $file['error'], $file['tmp_name'], $file['name'], $file['size'] ) || UPLOAD_ERR_OK !== $file['error'] ) {
			return new \WP_Error( 'iconjet_upload_missing', __( 'No file was uploaded.', 'iconjet' ), array( 'status' => 400 ) );
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'iconjet_upload_invalid', __( 'Invalid upload.', 'iconjet' ), array( 'status' => 400 ) );
		}
		if ( 'svg' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ) {
			return new \WP_Error( 'iconjet_upload_type', __( 'Only SVG files can be uploaded.', 'iconjet' ), array( 'status' => 400 ) );
		}
		if ( (int) $file['size'] > Svg_Sanitizer::MAX_BYTES ) {
			return new \WP_Error( 'iconjet_svg_too_large', __( 'SVG files must be 50 KB or smaller.', 'iconjet' ), array( 'status' => 400 ) );
		}

		// Local temporary upload file, already validated above.
		$svg = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $svg ) {
			return new \WP_Error( 'iconjet_upload_invalid', __( 'Invalid upload.', 'iconjet' ), array( 'status' => 400 ) );
		}

		$label = $request->get_param( 'label' );
		if ( ! $label ) {
			$label = pathinfo( sanitize_file_name( $file['name'] ), PATHINFO_FILENAME );
		}

		$icon = Custom_Icons::add( $svg, $label );
		if ( is_wp_error( $icon ) ) {
			$icon->add_data( array( 'status' => 400 ) );
			return $icon;
		}

		$response = rest_ensure_response( $icon );
		$response->set_status( 201 );
		return $response;
	}

	/**
	 * DELETE /custom-icons/{name}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_custom_icon( \WP_REST_Request $request ) {
		$name = (string) $request->get_param( 'name' );
		if ( ! Custom_Icons::delete( $name ) ) {
			return new \WP_Error( 'iconjet_not_found', __( 'Icon not found.', 'iconjet' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
