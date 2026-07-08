<?php
/**
 * Public REST routes for client-side detail loading.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Canity_REST {

	const NAMESPACE = 'canity/v1';

	/** Max REST requests per IP within RATE_LIMIT_WINDOW. */
	const RATE_LIMIT_MAX = 60;

	/** Rate-limit window in seconds. */
	const RATE_LIMIT_WINDOW = MINUTE_IN_SECONDS;

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/detail',
			[
				'methods'             => 'GET',
				'callback'            => [ __CLASS__, 'get_detail' ],
				// Public read (data is already on the frontend); throttled per IP below.
				'permission_callback' => [ __CLASS__, 'public_permission' ],
				'args'                => [
					'type' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => static function ( $value ) {
							return in_array( $value, Canity_Shortcodes::ALLOWED_TYPES, true );
						},
					],
					'id'   => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Allows unauthenticated reads; rate-limited to limit outbound API amplification on cache misses.
	 *
	 * @return true|WP_Error
	 */
	public static function public_permission() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		if ( '' === $ip ) {
			return true;
		}

		$key   = 'canity_rest_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT_MAX ) {
			return new WP_Error(
				'canity_rate_limited',
				__( 'Too many requests. Please try again in a minute.', 'canity' ),
				[ 'status' => 429 ]
			);
		}

		set_transient( $key, $count + 1, self::RATE_LIMIT_WINDOW );

		return true;
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_detail( $request ) {
		$type = (string) $request->get_param( 'type' );
		$id   = (string) $request->get_param( 'id' );

		$item = Canity_API::resolve_item( $type, $id );
		if ( is_wp_error( $item ) ) {
			return new WP_Error(
				$item->get_error_code(),
				$item->get_error_message(),
				[ 'status' => 404 ]
			);
		}

		return new WP_REST_Response(
			[
				'html'        => Canity_Detail::render_from_item( $type, $item ),
				'booking_url' => Canity_Detail::booking_url( $type, $item ),
			],
			200
		);
	}
}
