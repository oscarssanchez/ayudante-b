<?php

namespace AyudanteAI;

class Api {
	/**
	 * Capability required to use the API.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * OpenAI API URL
	 */
	const OPENAI_API_URL = 'https://api.openai.com/v1';

	/**
	 * Init method.
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Generate images endpoint.
		register_rest_route( AYUDANTEAI_REST_NAMESPACE, '/generate/images', [
			'methods'  => 'POST',
			'callback' => [ $this, 'generate_images' ],
			'show_in_index'       => false,
			'permission_callback' => function() {
				return current_user_can( self::CAPABILITY );
			},
			'args'                => [
				'prompt'  => [
					'description' => 'Text prompt to generate images.',
					'type'        => 'string',
					'format'      => 'string',
					'required'    => true,
				],
				'image_size' => [
					'description' => 'Requested image sizes.',
					'type'        => 'string',
					'required'    => true,
				],
				'image_number' => [
					'description' => 'Requested number of images.',
					'type'        => 'integer',
					'required'    => true,
				],
			],
		] );

		register_rest_route( AYUDANTEAI_REST_NAMESPACE, '/create-attachment/images', [
			'methods'  => 'POST',
			'callback' => [ $this, 'create_image_attachment' ],
			'permission_callback' => function() {
				return current_user_can( self::CAPABILITY );
			},
			'show_in_index'       => false,
				'args' => [
					'post_title' => [
						'description' => 'Post title for the attachment.',
						'type'        => 'string',
						'required'    => true,
					],
					'attachment_url' => [
						'description' => 'Attachment URL.',
						'type'        => 'string',
						'required'    => true,
					]
				],
			] );
	}

	/**
	 * Create image attachment from a URL
	 */
	public function create_image_attachment( \WP_REST_Request $request ) {
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$post_title     = $request->get_param( 'post_title' );
		$attachment_url = $request->get_param( 'attachment_url' );

		try {
			$image_id = media_sideload_image( $attachment_url, null, $post_title, 'id' );

			$response = [
				'id' => $image_id,
				'url' => wp_get_attachment_url( $image_id ),
			];

			return rest_ensure_response( $response );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'ayudanteai_error', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	/**
	 * Callback for the generate images endpoint.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Error|\WP_HTTP_Response|\WP_REST_Response
	 */
	public function generate_images( \WP_REST_Request $request ) {
		$prompt       = $request->get_param( 'prompt' );
		$image_size   = $request->get_param( 'image_size' );
		$image_number = $request->get_param( 'image_number' );

		$body = [
			'prompt' => $prompt,
			'size' => $image_size,
			'n' => $image_number,
		];

		try {
			$response = $this->remote_request(
				'images/generations',
				'POST',
				wp_json_encode( $body )
			);

			return rest_ensure_response( json_decode( wp_remote_retrieve_body( $response ) ) );
		} catch ( \Exception $e ) {
			return rest_ensure_response ( new \WP_Error( 'ayudante_ai_generate_images_error', esc_html( $e->getMessage() ) ) );
		}
	}

	/**
	 * Remote request method.
	 *
	 * @param $endpoint
	 * @param string $method
	 * @param null $body
	 * @return array|\WP_Error
	 */
	public function remote_request( $endpoint, $method = 'GET', $body = null ) {
		$args = [
			'headers' => [
				'Authorization' => 'Bearer ' . get_option( 'openai_token' ),
				'Content-Type'  => 'application/json',
			],
			'timeout' => 30,
			'method'  => $method,
			'body'    => $body,
		];

		$url = self::OPENAI_API_URL . '/' . $endpoint;

		return wp_remote_request( $url, $args );
	}
}