<?php
/**
 * Conditional stylesheet and script loader.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Canity_Assets {

	const STYLE_HANDLE = 'canity';
	const SCRIPT_HANDLE = 'canity';

	private static $needed = false;

	private static $interactive = false;

	public static function init() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register' ] );
		add_action( 'wp_footer', [ __CLASS__, 'enqueue_if_needed' ] );
		add_action( 'wp_footer', [ __CLASS__, 'render_modal_shells' ], 20 );
	}

	public static function register() {
		wp_register_style(
			self::STYLE_HANDLE,
			CANITY_PLUGIN_URL . 'assets/css/canity.css',
			[],
			CANITY_VERSION
		);

		$js_path = CANITY_PLUGIN_DIR . 'assets/js/canity.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : CANITY_VERSION;

		wp_register_script(
			self::SCRIPT_HANDLE,
			CANITY_PLUGIN_URL . 'assets/js/canity.js',
			[],
			$js_ver,
			true
		);

		wp_localize_script(
			self::SCRIPT_HANDLE,
			'canityConfig',
			[
				'restUrl'   => esc_url_raw( rest_url( 'canity/v1/detail' ) ),
				'hashPrefix' => 'canity-detail',
				'i18n'      => [
					'close'       => __( 'Close', 'canity' ),
					'loading'     => __( 'Loading…', 'canity' ),
					'error'       => __( 'Details could not be loaded.', 'canity' ),
					'terms'       => __( 'Terms & Conditions', 'canity' ),
					'detailTitle' => __( 'Details', 'canity' ),
				],
			]
		);
	}

	/**
	 * @param string $detail_mode Optional resolved detail mode for the current render.
	 */
	public static function mark_needed( $detail_mode = 'external' ) {
		self::$needed = true;

		if ( in_array( $detail_mode, [ 'modal', 'inline' ], true ) ) {
			self::$interactive = true;
		}

		if ( Canity_Settings::is_css_enabled() && ! wp_style_is( self::STYLE_HANDLE, 'enqueued' ) ) {
			wp_enqueue_style( self::STYLE_HANDLE );
		}

		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			wp_enqueue_script( self::SCRIPT_HANDLE );
		}
	}

	public static function enqueue_if_needed() {
		if ( ! self::$needed ) {
			return;
		}

		if ( Canity_Settings::is_css_enabled() && ! wp_style_is( self::STYLE_HANDLE, 'enqueued' ) ) {
			wp_enqueue_style( self::STYLE_HANDLE );
		}

		if ( ! wp_script_is( self::SCRIPT_HANDLE, 'enqueued' ) ) {
			wp_enqueue_script( self::SCRIPT_HANDLE );
		}
	}

	public static function render_modal_shells() {
		if ( self::$needed ) {
			self::render_terms_modal_shell();
		}

		if ( self::$interactive ) {
			self::render_detail_modal_shell();
		}
	}

	public static function render_detail_modal_shell() {
		?>
		<div id="canity-modal" class="canity-modal" hidden>
			<div class="canity-modal__backdrop" data-canity-modal-close></div>
			<div class="canity-modal__panel" role="dialog" aria-modal="true" aria-labelledby="canity-modal-title" tabindex="-1">
				<button type="button" class="canity-modal__close" data-canity-modal-close aria-label="<?php echo esc_attr__( 'Close', 'canity' ); ?>">&times;</button>
				<div class="canity-modal__content">
					<h2 id="canity-modal-title" class="canity-modal__title canity-sr-only"><?php echo esc_html__( 'Details', 'canity' ); ?></h2>
					<div class="canity-modal__body"></div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function render_terms_modal_shell() {
		?>
		<div id="canity-terms-modal" class="canity-modal canity-terms-modal" hidden>
			<div class="canity-modal__backdrop" data-canity-terms-close></div>
			<div class="canity-modal__panel" role="dialog" aria-modal="true" aria-labelledby="canity-terms-modal-title" tabindex="-1">
				<button type="button" class="canity-modal__close" data-canity-terms-close aria-label="<?php echo esc_attr__( 'Close', 'canity' ); ?>">&times;</button>
				<div class="canity-modal__content canity-terms-modal__content">
					<h2 id="canity-terms-modal-title" class="canity-terms-modal__title"><?php echo esc_html__( 'Terms & Conditions', 'canity' ); ?></h2>
					<div class="canity-detail__terms canity-terms-modal__body"></div>
				</div>
			</div>
		</div>
		<?php
	}
}
