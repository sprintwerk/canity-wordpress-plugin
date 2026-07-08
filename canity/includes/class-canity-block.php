<?php
/**
 * Gutenberg blocks wrapping the existing shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Canity_Block {

	const LIST_BLOCK_NAME   = 'canity/list';
	const DETAIL_BLOCK_NAME = 'canity/detail';

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register' ] );
	}

	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$block_js_path = CANITY_PLUGIN_DIR . 'assets/js/block.js';
		$block_js_ver  = file_exists( $block_js_path ) ? (string) filemtime( $block_js_path ) : CANITY_VERSION;

		wp_register_script(
			'canity-block-editor',
			CANITY_PLUGIN_URL . 'assets/js/block.js',
			[ 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor' ],
			$block_js_ver,
			true
		);

		wp_set_script_translations(
			'canity-block-editor',
			'canity',
			CANITY_PLUGIN_DIR . 'languages'
		);

		register_block_type(
			self::LIST_BLOCK_NAME,
			[
				'api_version'     => 2,
				'editor_script'   => 'canity-block-editor',
				'attributes'      => [
					'type'  => [
						'type'    => 'string',
						'default' => 'services',
					],
					'limit' => [
						'type'    => 'number',
						'default' => 0,
					],
					'detail' => [
						'type'    => 'string',
						'default' => '',
					],
				],
				'render_callback' => [ __CLASS__, 'render_list' ],
			]
		);

		register_block_type(
			self::DETAIL_BLOCK_NAME,
			[
				'api_version'     => 2,
				'editor_script'   => 'canity-block-editor',
				'render_callback' => [ __CLASS__, 'render_detail' ],
			]
		);
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public static function render_list( $attributes ) {
		$type    = isset( $attributes['type'] ) ? (string) $attributes['type'] : 'services';
		if ( ! in_array( $type, Canity_Shortcodes::ALLOWED_TYPES, true ) ) {
			$type = 'services';
		}
		$limit  = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 0;
		$detail = isset( $attributes['detail'] ) ? (string) $attributes['detail'] : '';

		return Canity_Shortcodes::render( $type, [ 'limit' => $limit, 'detail' => $detail ] );
	}

	/**
	 * @param array<string, mixed> $attributes
	 */
	public static function render_detail( $attributes ) {
		unset( $attributes );

		return Canity_Detail::shortcode( [] );
	}
}
