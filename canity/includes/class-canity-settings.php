<?php
/**
 * Settings page under Settings → CANITY.
 * Uses the WordPress Settings API; nonce handling is provided by options.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Canity_Settings {

	const OPTION_GROUP = 'canity_settings';
	const OPTION_NAME  = 'canity_options';
	const PAGE_SLUG    = 'canity';

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
		add_action( 'update_option_' . self::OPTION_NAME, [ 'Canity_API', 'flush_cache' ] );
		add_action( 'admin_post_canity_flush_cache', [ __CLASS__, 'handle_flush_cache' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		$css_path = CANITY_PLUGIN_DIR . 'assets/css/admin-settings.css';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : CANITY_VERSION;

		wp_enqueue_style(
			'canity-admin-settings',
			CANITY_PLUGIN_URL . 'assets/css/admin-settings.css',
			[],
			$css_ver
		);

		$js_path = CANITY_PLUGIN_DIR . 'assets/js/admin-settings.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : CANITY_VERSION;

		wp_enqueue_script(
			'canity-admin-settings',
			CANITY_PLUGIN_URL . 'assets/js/admin-settings.js',
			[],
			$js_ver,
			true
		);
	}

	public static function register_page() {
		add_options_page(
			__( 'CANITY', 'canity' ),
			__( 'CANITY', 'canity' ),
			'manage_options',
			self::PAGE_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ __CLASS__, 'sanitize' ],
				'default'           => [
					'api_token'      => '',
					'token_prefix'   => '',
					'business_slug'  => '',
					'business_name'  => '',
					'enqueue_css'    => true,
					'embed_detail'   => false,
					'detail_mode'    => 'modal',
					'detail_page_id' => 0,
				],
			]
		);

		add_settings_section(
			'canity_main',
			__( 'API access', 'canity' ),
			static function () {
				echo '<p>' . esc_html__( 'Connect your WordPress site to your CANITY business using a Partner API token.', 'canity' ) . '</p>';
				echo '<p class="description">' . esc_html__( 'The token is stored in plain text in the WordPress database (standard for API plugins). Only administrators with "manage_options" can change it; it is included in backups. Only a shortened prefix is shown in the admin UI.', 'canity' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		add_settings_field(
			'api_token',
			__( 'API connection', 'canity' ),
			[ __CLASS__, 'render_api_token_field' ],
			self::PAGE_SLUG,
			'canity_main'
		);

		add_settings_section(
			'canity_appearance',
			__( 'Appearance', 'canity' ),
			static function () {
				echo '<p>' . esc_html__( 'Control whether the bundled CANITY design is loaded on your website.', 'canity' ) . '</p>';
			},
			self::PAGE_SLUG
		);

		add_settings_field(
			'enqueue_css',
			__( 'CANITY design', 'canity' ),
			[ __CLASS__, 'render_enqueue_css_field' ],
			self::PAGE_SLUG,
			'canity_appearance'
		);

		add_settings_field(
			'embed_detail',
			__( 'Detail view', 'canity' ),
			[ __CLASS__, 'render_detail_settings_field' ],
			self::PAGE_SLUG,
			'canity_appearance'
		);
	}

	const DETAIL_MODES = [ 'modal', 'page', 'inline' ];

	const DETAIL_OVERRIDES = [ 'external', 'modal', 'page', 'inline' ];

	public static function is_detail_embedded() {
		$options = get_option( self::OPTION_NAME, [] );
		return is_array( $options ) && ! empty( $options['embed_detail'] );
	}

	public static function get_detail_mode() {
		$options = get_option( self::OPTION_NAME, [] );
		$mode    = is_array( $options ) && isset( $options['detail_mode'] )
			? (string) $options['detail_mode']
			: 'modal';

		return in_array( $mode, self::DETAIL_MODES, true ) ? $mode : 'modal';
	}

	public static function get_detail_page_id() {
		$options = get_option( self::OPTION_NAME, [] );
		return is_array( $options ) && isset( $options['detail_page_id'] )
			? max( 0, (int) $options['detail_page_id'] )
			: 0;
	}

	public static function get_detail_page_url() {
		$page_id = self::get_detail_page_id();
		if ( $page_id <= 0 ) {
			return '';
		}

		$url = get_permalink( $page_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param string $override Shortcode/block override or empty for global default.
	 */
	public static function resolve_detail_mode( $override = '' ) {
		$override = strtolower( trim( (string) $override ) );
		if ( in_array( $override, self::DETAIL_OVERRIDES, true ) ) {
			return $override;
		}

		if ( ! self::is_detail_embedded() ) {
			return 'external';
		}

		return self::get_detail_mode();
	}

	public static function render_detail_settings_field() {
		$embedded      = self::is_detail_embedded();
		$mode          = self::get_detail_mode();
		$detail_page   = self::get_detail_page_id();
		$option_name   = self::OPTION_NAME;
		$page_mode_cls = 'page' === $mode ? '' : ' hidden';
		$mode_cls      = $embedded ? '' : ' hidden';

		printf(
			'<label for="canity_embed_detail"><input type="checkbox" id="canity_embed_detail" name="%1$s[embed_detail]" value="1" %2$s /> %3$s</label>',
			esc_attr( $option_name ),
			checked( $embedded, true, false ),
			esc_html__( 'Embed detail view on the website', 'canity' )
		);
		echo '<p class="description">' . esc_html__( 'Off: click opens the detail page on canity.de. On: details are shown locally; the booking button still links to canity.de.', 'canity' ) . '</p>';

		echo '<fieldset id="canity-detail-mode-wrap" class="canity-settings-detail' . esc_attr( $mode_cls ) . '">';
		echo '<legend class="screen-reader-text">' . esc_html__( 'Display mode', 'canity' ) . '</legend>';

		$modes = [
			'modal'  => __( 'Dialog (modal)', 'canity' ),
			'page'   => __( 'Dedicated page', 'canity' ),
			'inline' => __( 'Expand inline', 'canity' ),
		];

		foreach ( $modes as $value => $label ) {
			printf(
				'<label style="display:block;margin:.5em 0;"><input type="radio" name="%1$s[detail_mode]" value="%2$s" %3$s /> %4$s</label>',
				esc_attr( $option_name ),
				esc_attr( $value ),
				checked( $mode, $value, false ),
				esc_html( $label )
			);
		}

		echo '<div id="canity-detail-page-wrap" class="canity-settings-detail-page' . esc_attr( $page_mode_cls ) . '">';
		echo '<label for="canity_detail_page_id">' . esc_html__( 'Detail page', 'canity' ) . '</label>';
		wp_dropdown_pages(
			[
				'name'              => esc_attr( $option_name ) . '[detail_page_id]',
				'id'                => 'canity_detail_page_id',
				'selected'          => absint( $detail_page ),
				'show_option_none'  => esc_html__( '— Select page —', 'canity' ),
				'option_none_value' => '0',
			]
		);
		echo '<p class="description">' . esc_html__( 'Add the [canity_detail] shortcode to the selected page.', 'canity' ) . '</p>';
		echo '</div>';
		echo '</fieldset>';
	}

	public static function is_css_enabled() {
		$options = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $options ) || ! array_key_exists( 'enqueue_css', $options ) ) {
			return true;
		}

		return ! empty( $options['enqueue_css'] );
	}

	public static function render_enqueue_css_field() {
		$enabled = self::is_css_enabled();

		printf(
			'<label for="canity_enqueue_css"><input type="checkbox" id="canity_enqueue_css" name="%1$s[enqueue_css]" value="1" %2$s /> %3$s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $enabled, true, false ),
			esc_html__( 'Use CANITY design (CSS)', 'canity' )
		);
		echo '<p class="description">' . esc_html__( 'Disable this option to skip loading the bundled CANITY CSS. The HTML structure with classes like .canity-grid and .canity-card remains so you can style it with your theme CSS.', 'canity' ) . '</p>';
	}

	public static function render_api_token_field() {
		$options       = get_option( self::OPTION_NAME, [] );
		$has_token     = ! empty( $options['api_token'] );
		$business_slug = isset( $options['business_slug'] ) ? (string) $options['business_slug'] : '';
		$business_name = isset( $options['business_name'] ) ? (string) $options['business_name'] : '';
		$token_prefix  = isset( $options['token_prefix'] ) ? (string) $options['token_prefix'] : '';
		if ( '' === $token_prefix && $has_token ) {
			$token_prefix = Canity_API::format_token_prefix( (string) $options['api_token'] );
		}

		echo '<div class="canity-settings-token">';

		if ( $has_token && '' !== $token_prefix ) {
			echo '<div class="canity-settings-token__current">';
			echo '<p class="canity-settings-token__heading">' . esc_html__( 'Currently used token', 'canity' ) . '</p>';
			echo '<p class="canity-settings-token__value">';
			printf( '<code>%s…</code>', esc_html( $token_prefix ) );
			echo '<span class="dashicons dashicons-lock" title="' . esc_attr__( 'Partially visible and not editable for security reasons', 'canity' ) . '" aria-hidden="true"></span>';
			echo '<span class="screen-reader-text">' . esc_html__( 'Partially visible and not editable for security reasons', 'canity' ) . '</span>';
			echo '</p>';
			echo '<p class="description">' . esc_html__( 'The full token is stored and cannot be shown again for security reasons.', 'canity' ) . '</p>';

			if ( '' !== $business_name || '' !== $business_slug ) {
				echo '<p class="canity-settings-token__business">';
				echo '<span class="canity-settings-token__business-label">' . esc_html__( 'Linked business', 'canity' ) . '</span>';
				if ( '' !== $business_name ) {
					echo '<strong class="canity-settings-token__business-name">' . esc_html( $business_name ) . '</strong>';
				}
				if ( '' !== $business_slug ) {
					printf(
						'<span class="canity-settings-token__business-slug"><code>%s</code></span>',
						esc_html( $business_slug )
					);
				}
				echo '</p>';
			}
			echo '</div>';
		}

		echo '<div class="canity-settings-token__replace">';
		if ( $has_token ) {
			echo '<label class="canity-settings-token__replace-label" for="canity_api_token">' . esc_html__( 'Set new token', 'canity' ) . '</label>';
			echo '<p class="description canity-settings-token__replace-hint">' . esc_html__( 'Enter a new token here to replace the current one. Leave blank to keep the stored token.', 'canity' ) . '</p>';
		} else {
			echo '<label class="canity-settings-token__replace-label" for="canity_api_token">' . esc_html__( 'Partner API token', 'canity' ) . '</label>';
			echo '<p class="description canity-settings-token__replace-hint">' . esc_html__( 'Create the token in CANITY under My Business → Partner API. It is shown only once.', 'canity' ) . '</p>';
		}

		printf(
			'<div class="canity-settings-token__row"><input type="password" id="canity_api_token" name="%1$s[api_token]" value="" class="regular-text" autocomplete="off" placeholder="cnty_sk_…" />',
			esc_attr( self::OPTION_NAME )
		);
		submit_button( __( 'Save', 'canity' ), 'primary', 'submit', false );
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	public static function render_flush_cache_section() {
		?>
		<h2><?php echo esc_html__( 'Cache', 'canity' ); ?></h2>
		<p><?php echo esc_html__( 'API responses are cached for 15 minutes. Clear the cache if you want CANITY changes to appear on your site immediately.', 'canity' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'canity_flush_cache' ); ?>
			<input type="hidden" name="action" value="canity_flush_cache" />
			<?php
			submit_button(
				__( 'Clear cache now', 'canity' ),
				'secondary',
				'canity_flush_cache',
				false
			);
			?>
		</form>
		<?php
	}

	public static function handle_flush_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission for this action.', 'canity' ) );
		}

		check_admin_referer( 'canity_flush_cache' );

		Canity_API::flush_cache();

		set_transient( 'canity_cache_flushed_' . get_current_user_id(), 1, 30 );

		$redirect = wp_get_referer();
		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	public static function sanitize( $input ) {
		$existing = get_option( self::OPTION_NAME, [] );
		$existing = is_array( $existing ) ? $existing : [];

		$new_token = isset( $input['api_token'] )
			? trim( sanitize_text_field( wp_unslash( $input['api_token'] ) ) )
			: '';
		$token     = '' !== $new_token
			? $new_token
			: ( isset( $existing['api_token'] ) ? trim( (string) $existing['api_token'] ) : '' );

		if ( '' === $token ) {
			add_settings_error(
				self::OPTION_GROUP,
				'canity_no_api_token',
				__( 'Please enter a CANITY Partner API token.', 'canity' ),
				'error'
			);
			return $existing;
		}

		$token_changed = '' !== $new_token;
		if ( $token_changed ) {
			$validation = Canity_API::validate_token( $token );
			if ( is_wp_error( $validation ) ) {
				add_settings_error(
					self::OPTION_GROUP,
					$validation->get_error_code(),
					$validation->get_error_message(),
					'error'
				);
				return $existing;
			}

			$business_slug = $validation['slug'];
			$business_name = $validation['name'];
		} else {
			$business_slug = isset( $existing['business_slug'] ) ? (string) $existing['business_slug'] : '';
			$business_name = isset( $existing['business_name'] ) ? (string) $existing['business_name'] : '';
		}

		$detail_mode = isset( $input['detail_mode'] )
			? sanitize_text_field( wp_unslash( $input['detail_mode'] ) )
			: 'modal';
		if ( ! in_array( $detail_mode, self::DETAIL_MODES, true ) ) {
			$detail_mode = 'modal';
		}

		$detail_page_id = isset( $input['detail_page_id'] ) ? (int) $input['detail_page_id'] : 0;
		if ( $detail_page_id > 0 ) {
			$page = get_post( $detail_page_id );
			if ( ! $page || 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
				$detail_page_id = 0;
			}
		}

		return [
			'api_token'      => $token,
			'token_prefix'   => Canity_API::format_token_prefix( $token ),
			'business_slug'  => $business_slug,
			'business_name'  => $business_name,
			'enqueue_css'    => ! empty( $input['enqueue_css'] ),
			'embed_detail'   => ! empty( $input['embed_detail'] ),
			'detail_mode'    => $detail_mode,
			'detail_page_id' => $detail_page_id,
		];
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'CANITY settings', 'canity' ); ?></h1>
			<?php
			$flushed_key = 'canity_cache_flushed_' . get_current_user_id();
			if ( get_transient( $flushed_key ) ) {
				delete_transient( $flushed_key );
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html__( 'The CANITY cache was cleared.', 'canity' ); ?></p>
				</div>
				<?php
			}
			?>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button( __( 'Save', 'canity' ) );
				?>
			</form>
			<?php self::render_flush_cache_section(); ?>
		</div>
		<?php
	}

}
