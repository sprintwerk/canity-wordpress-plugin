<?php
/**
 * Canity Partner API client with transient caching.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Canity_API {

	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	const CACHE_VERSION_OPTION = 'canity_cache_version';

	const LIST_TAKE = 1000;

	/** @var array<string, mixed>|WP_Error|null */
	private static $business_request_cache = null;

	/**
	 * Fetches a resource list from the CANITY Partner API.
	 *
	 * @param string $resource One of: services, events, packages.
	 * @param int    $limit    Optional max items to request from the API (0 = up to LIST_TAKE).
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public static function fetch( $resource, $limit = 0 ) {
		$api_token = self::get_api_token();
		if ( '' === $api_token ) {
			return new WP_Error(
				'canity_no_api_token',
				__( 'Please enter your CANITY Partner API token in the settings first.', 'canity' )
			);
		}

		$limit = max( 0, (int) $limit );
		$take  = $limit > 0 ? min( $limit, self::LIST_TAKE ) : self::LIST_TAKE;

		$path = self::endpoint_path( $resource, $take );
		if ( null === $path ) {
			return new WP_Error( 'canity_invalid_resource', __( 'Unknown resource.', 'canity' ) );
		}

		$cache_key = self::cache_key( $api_token, $resource, $limit );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = self::request( $path, $api_token );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response['data'];
		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			return new WP_Error( 'canity_invalid_json', __( 'The CANITY API response could not be read.', 'canity' ) );
		}

		$items = $data['data'];
		set_transient( $cache_key, $items, self::CACHE_TTL );

		return $items;
	}

	/**
	 * Fetches a single resource from the CANITY Partner API.
	 *
	 * @param string $resource One of: services, events, packages.
	 * @param string $id       Resource UUID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function fetch_detail( $resource, $id ) {
		$api_token = self::get_api_token();
		if ( '' === $api_token ) {
			return new WP_Error(
				'canity_no_api_token',
				__( 'Please enter your CANITY Partner API token in the settings first.', 'canity' )
			);
		}

		$id = trim( (string) $id );
		if ( '' === $id ) {
			return new WP_Error( 'canity_invalid_id', __( 'Invalid resource ID.', 'canity' ) );
		}

		$path = self::detail_endpoint_path( $resource, $id );
		if ( null === $path ) {
			return new WP_Error( 'canity_invalid_resource', __( 'Unknown resource.', 'canity' ) );
		}

		$cache_key = self::detail_cache_key( $api_token, $resource, $id );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$response = self::request( $path, $api_token );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$item = self::extract_detail_item( $response['data'] );
		if ( null === $item ) {
			return new WP_Error( 'canity_invalid_json', __( 'The CANITY API response could not be read.', 'canity' ) );
		}

		set_transient( $cache_key, $item, self::CACHE_TTL );

		return $item;
	}

	/**
	 * Resolves a single item for detail views (prefers the detail endpoint over full list scans).
	 *
	 * @param string $resource One of: services, events, packages.
	 * @param string $id       Resource UUID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function resolve_item( $resource, $id ) {
		$id = trim( (string) $id );
		if ( '' === $id ) {
			return new WP_Error( 'canity_invalid_id', __( 'Invalid resource ID.', 'canity' ) );
		}

		$detail = self::fetch_detail( $resource, $id );
		if ( ! is_wp_error( $detail ) ) {
			return $detail;
		}

		$list_item = self::find_in_list( $resource, $id );
		if ( null !== $list_item ) {
			return $list_item;
		}

		return $detail;
	}

	/**
	 * Resolves a single item for public, unauthenticated detail requests.
	 *
	 * Only items present in the partner's public catalog list are exposed, so
	 * the public REST endpoint cannot be used to proxy arbitrary items by ID.
	 *
	 * @param string $resource One of: services, events, packages.
	 * @param string $id       Resource UUID.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function resolve_public_item( $resource, $id ) {
		$id = trim( (string) $id );
		if ( '' === $id ) {
			return new WP_Error( 'canity_invalid_id', __( 'Invalid resource ID.', 'canity' ) );
		}

		if ( ! self::is_public_id( $resource, $id ) ) {
			return new WP_Error(
				'canity_not_found',
				__( 'The requested item is not available.', 'canity' ),
				[ 'status' => 404 ]
			);
		}

		return self::resolve_item( $resource, $id );
	}

	/**
	 * Checks whether an ID belongs to the partner's publicly listed catalog.
	 *
	 * @param string $resource One of: services, events, packages.
	 * @param string $id       Resource UUID.
	 */
	private static function is_public_id( $resource, $id ) {
		$items = self::fetch( $resource );
		if ( is_wp_error( $items ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['id'] ) && (string) $item['id'] === $id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fetches the partner business profile (cached).
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public static function fetch_business() {
		if ( null !== self::$business_request_cache ) {
			return self::$business_request_cache;
		}

		$api_token = self::get_api_token();
		if ( '' === $api_token ) {
			self::$business_request_cache = new WP_Error(
				'canity_no_api_token',
				__( 'Please enter your CANITY Partner API token in the settings first.', 'canity' )
			);

			return self::$business_request_cache;
		}

		$cache_key = self::business_cache_key( $api_token );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			self::$business_request_cache = $cached;

			return self::$business_request_cache;
		}

		$response = self::request( '/partner/v1/business', $api_token );
		if ( is_wp_error( $response ) ) {
			self::$business_request_cache = $response;

			return self::$business_request_cache;
		}

		$data = $response['data'];
		if ( ! is_array( $data ) ) {
			self::$business_request_cache = new WP_Error( 'canity_invalid_json', __( 'The CANITY API response could not be read.', 'canity' ) );

			return self::$business_request_cache;
		}

		set_transient( $cache_key, $data, self::CACHE_TTL );
		self::$business_request_cache = $data;

		return self::$business_request_cache;
	}

	/**
	 * Finds an item in the cached list by ID.
	 *
	 * @param string $resource
	 * @param string $id
	 * @return array<string, mixed>|null
	 */
	public static function find_in_list( $resource, $id ) {
		$api_token = self::get_api_token();
		if ( '' === $api_token ) {
			return null;
		}

		$id = trim( (string) $id );
		if ( '' === $id ) {
			return null;
		}

		foreach ( self::get_cached_list_batches( $api_token, $resource ) as $items ) {
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				if ( isset( $item['id'] ) && (string) $item['id'] === $id ) {
					return $item;
				}
			}
		}

		return null;
	}

	/**
	 * Validates a token via /me and loads business profile data for link building.
	 *
	 * Token validity and scopes are checked on /partner/v1/me only. /business is a
	 * data endpoint (slug, name) and may fail independently when the business is
	 * not publicly visible (DRAFT, suspended, or terminated subscription).
	 *
	 * @param string $api_token Raw partner API token.
	 * @return array{slug: string, name: string}|WP_Error
	 */
	public static function validate_token( $api_token ) {
		$me_response = self::request( '/partner/v1/me', $api_token );
		if ( is_wp_error( $me_response ) ) {
			return $me_response;
		}

		$scope_error = self::validate_scopes( $me_response['data'] );
		if ( is_wp_error( $scope_error ) ) {
			return $scope_error;
		}

		$business_response = self::request( '/partner/v1/business', $api_token );
		if ( is_wp_error( $business_response ) ) {
			return self::map_business_request_error( $business_response );
		}

		$slug = isset( $business_response['data']['slug'] )
			? trim( (string) $business_response['data']['slug'] )
			: '';
		$name = isset( $business_response['data']['name'] )
			? trim( (string) $business_response['data']['name'] )
			: '';

		if ( '' === $slug ) {
			return new WP_Error(
				'canity_no_business_slug',
				__( 'The CANITY API did not return a profile URL for this business.', 'canity' )
			);
		}

		return [
			'slug' => $slug,
			'name' => $name,
		];
	}

	/**
	 * Invalidates all cached API responses (lists, details, business).
	 *
	 * Bumps a stored version integer included in every cache key so flush works
	 * with persistent object caches (Redis/Memcached) without wildcard DB deletes.
	 */
	public static function flush_cache() {
		self::$business_request_cache = null;
		self::bump_cache_version();
	}

	public static function get_business_slug() {
		$options = get_option( Canity_Settings::CREDENTIALS_OPTION, [] );
		return isset( $options['business_slug'] ) ? trim( (string) $options['business_slug'] ) : '';
	}

	public static function get_api_token() {
		$options = get_option( Canity_Settings::CREDENTIALS_OPTION, [] );
		return isset( $options['api_token'] ) ? trim( (string) $options['api_token'] ) : '';
	}

	/**
	 * Returns the display prefix for a partner token (matches CANITY UI).
	 *
	 * @param string $token Raw partner API token.
	 */
	public static function format_token_prefix( $token ) {
		$prefix = 'cnty_sk_';
		$token  = trim( (string) $token );
		if ( 0 !== strpos( $token, $prefix ) ) {
			return '';
		}
		$body = substr( $token, strlen( $prefix ) );
		if ( '' === $body ) {
			return '';
		}
		return $prefix . substr( $body, 0, 8 );
	}

	/**
	 * @return array{data: array<string, mixed>}|WP_Error
	 */
	private static function request( $path, $api_token ) {
		$url = untrailingslashit( CANITY_API_BASE ) . $path;

		$response = wp_remote_get(
			$url,
			[
				'timeout' => 10,
				'headers' => [
					'Accept'        => 'application/json',
					'Authorization' => 'Bearer ' . $api_token,
					'User-Agent'    => 'CANITY-WP-Plugin/' . CANITY_VERSION,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $code ) {
			return new WP_Error(
				'canity_api_unauthorized',
				__( 'The CANITY API token is invalid or expired.', 'canity' )
			);
		}
		if ( 403 === $code ) {
			return new WP_Error(
				'canity_api_forbidden',
				__( 'The CANITY API token does not have permission for this request.', 'canity' )
			);
		}
		if ( 200 !== $code ) {
			return new WP_Error(
				'canity_api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'The CANITY API returned status %d.', 'canity' ),
					$code
				),
				[ 'status' => $code ]
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'canity_invalid_json', __( 'The CANITY API response could not be read.', 'canity' ) );
		}

		return [ 'data' => $data ];
	}

	/**
	 * @param array<string, mixed> $me_data
	 * @return true|WP_Error
	 */
	private static function validate_scopes( $me_data ) {
		$scopes = isset( $me_data['scopes'] ) && is_array( $me_data['scopes'] )
			? $me_data['scopes']
			: [];

		$required = [ 'services:read', 'events:read', 'packages:read', 'business:read' ];
		$missing  = array_diff( $required, $scopes );
		if ( [] === $missing ) {
			return true;
		}

		return new WP_Error(
			'canity_api_missing_scopes',
			__( 'The CANITY API token does not have all required permissions (scopes).', 'canity' )
		);
	}

	/**
	 * Maps /business errors after a successful /me check.
	 *
	 * The partner API returns 404 without user context when the business is in
	 * DRAFT, suspended, or has a terminated subscription — indistinguishable
	 * from the client, so the message covers all three cases.
	 *
	 * @param WP_Error $error
	 * @return WP_Error
	 */
	private static function map_business_request_error( $error ) {
		if ( 'canity_api_error' !== $error->get_error_code() ) {
			return $error;
		}

		$status = 0;
		$data   = $error->get_error_data( 'canity_api_error' );
		if ( is_array( $data ) && isset( $data['status'] ) ) {
			$status = (int) $data['status'];
		}
		if ( 404 === $status ) {
			return new WP_Error(
				'canity_business_not_public',
				__( 'Token is valid, but the business is not publicly available right now (e.g. draft, suspended, or ended subscription).', 'canity' ),
				[ 'status' => $status ]
			);
		}

		return $error;
	}

	private static function endpoint_path( $resource, $take ) {
		$take  = max( 1, min( (int) $take, self::LIST_TAKE ) );
		$query = 'skip=0&take=' . $take;
		switch ( $resource ) {
			case 'services':
				return '/partner/v1/services?' . $query;
			case 'events':
				return '/partner/v1/events?' . $query;
			case 'packages':
				return '/partner/v1/packages?' . $query;
		}
		return null;
	}

	private static function detail_endpoint_path( $resource, $id ) {
		$encoded = rawurlencode( $id );
		switch ( $resource ) {
			case 'services':
				return '/partner/v1/services/' . $encoded;
			case 'events':
				return '/partner/v1/events/' . $encoded;
			case 'packages':
				return '/partner/v1/packages/' . $encoded;
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|null
	 */
	private static function extract_detail_item( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}
		if ( isset( $data['id'] ) ) {
			return $data;
		}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) && isset( $data['data']['id'] ) ) {
			return $data['data'];
		}
		return null;
	}

	private static function detail_cache_key( $api_token, $resource, $id ) {
		return 'canity_detail_v' . self::get_cache_version() . '_' . $resource . '_' . md5( $api_token . $id );
	}

	private static function cache_key( $api_token, $resource, $limit = 0 ) {
		$limit_suffix = $limit > 0 ? '_l' . (int) $limit : '';
		return 'canity_' . $resource . '_v' . self::get_cache_version() . $limit_suffix . '_' . md5( $api_token );
	}

	/**
	 * Returns already-cached list batches for a resource (no API calls).
	 *
	 * @return list<array<int, array<string, mixed>>>
	 */
	private static function get_cached_list_batches( $api_token, $resource ) {
		$batches = [];

		$full_list = get_transient( self::cache_key( $api_token, $resource, 0 ) );
		if ( false !== $full_list && is_array( $full_list ) ) {
			$batches[] = $full_list;
		}

		for ( $limit = 1; $limit <= 50; $limit++ ) {
			$cached = get_transient( self::cache_key( $api_token, $resource, $limit ) );
			if ( false !== $cached && is_array( $cached ) ) {
				$batches[] = $cached;
			}
		}

		return $batches;
	}

	private static function business_cache_key( $api_token ) {
		return 'canity_business_v' . self::get_cache_version() . '_' . md5( $api_token );
	}

	private static function get_cache_version() {
		return (int) get_option( self::CACHE_VERSION_OPTION, 1 );
	}

	private static function bump_cache_version() {
		$version = self::get_cache_version() + 1;
		update_option( self::CACHE_VERSION_OPTION, $version, false );
	}
}