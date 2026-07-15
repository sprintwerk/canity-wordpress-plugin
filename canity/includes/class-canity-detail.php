<?php
/**
 * Detail view rendering for services, events, and packages.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Canity_Detail {

	const SHORTCODE = 'canity_detail';

	public static function init() {
		add_shortcode( self::SHORTCODE, [ __CLASS__, 'shortcode' ] );
		add_filter( 'query_vars', [ __CLASS__, 'register_query_vars' ] );
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public static function register_query_vars( $vars ) {
		$vars[] = 'canity_type';
		$vars[] = 'canity_id';

		return $vars;
	}

	/**
	 * @param array<string, mixed>|string $atts
	 */
	public static function shortcode( $atts ) {
		unset( $atts );

		$type = sanitize_key( (string) get_query_var( 'canity_type', '' ) );
		$id   = sanitize_text_field( (string) get_query_var( 'canity_id', '' ) );

		if ( ! in_array( $type, Canity_Shortcodes::ALLOWED_TYPES, true ) || '' === $id ) {
			return '';
		}

		Canity_Assets::mark_needed();

		$result = self::render( $type, $id );
		if ( is_wp_error( $result ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="canity-error">' . esc_html( $result->get_error_message() ) . '</div>';
			}
			return '';
		}

		return $result;
	}

	/**
	 * @return string|WP_Error
	 */
	public static function render( $type, $id ) {
		$item = Canity_API::resolve_public_item( $type, $id );
		if ( is_wp_error( $item ) ) {
			return $item;
		}

		return self::render_from_item( $type, $item );
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public static function render_from_item( $type, array $item ) {
		ob_start();
		echo '<div class="canity-detail canity-detail--' . esc_attr( sanitize_html_class( $type ) ) . '">';

		switch ( $type ) {
			case 'services':
				self::render_service( $item );
				break;
			case 'events':
				self::render_event( $item );
				break;
			case 'packages':
				self::render_package( $item );
				break;
		}

		self::render_cta( $type, $item );
		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public static function booking_url( $type, array $item ) {
		return Canity_Item_Helpers::booking_url( $type, $item );
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_service( array $item ) {
		$title     = (string) ( $item['title'] ?? '' );
		$image_url = Canity_Helpers::extract_image_url( $item['image'] ?? null );

		self::render_hero( $image_url, 'service', $title );
		
		echo '<div class="canity-detail__body">';
		if ( ! empty( $item['isOnline'] ) ) {
			self::render_online_badge();
		}

		echo '<h2 class="canity-detail__title">' . esc_html( $title ) . '</h2>';
		self::render_service_meta_row( $item );
		self::render_description_section( $item );
		self::render_service_location_section( $item );		
		self::render_legal_section( $item );
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_event( array $item ) {
		$title     = (string) ( $item['title'] ?? '' );
		$image_url = Canity_Helpers::extract_image_url( $item['image'] ?? null );

		self::render_hero( $image_url, 'event', $title );

		echo '<div class="canity-detail__body">';
		if ( ! empty( $item['isOnline'] ) ) {
			self::render_online_badge();
		}

		echo '<h2 class="canity-detail__title">' . esc_html( $title ) . '</h2>';
		self::render_event_header( $item );
		self::render_event_schedule( $item );
		self::render_description_section( $item );
		self::render_legal_section( $item );
		self::render_gallery( $item['gallery'] ?? [] );
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_package( array $item ) {
		$title = (string) ( $item['title'] ?? '' );

		echo '<div class="canity-detail__body">';
		echo '<div class="canity-detail__icon-circle" aria-hidden="true">';
		Canity_Helpers::echo_svg_icon( Canity_Helpers::icon_package_header() );
		echo '</div>';
		echo '<h2 class="canity-detail__title">' . esc_html( $title ) . '</h2>';
		self::render_package_meta_row( $item );
		self::render_package_redeem_section();
		self::render_package_valid_for_section( $item );
		self::render_legal_section( $item );
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_package_meta_row( array $item ) {
		$units     = isset( $item['unitCount'] ) ? (int) $item['unitCount'] : 0;
		$has_price = isset( $item['price'] ) && (int) $item['price'] > 0;

		if ( $units <= 0 && ! $has_price ) {
			return;
		}

		echo '<div class="canity-detail__meta-row">';
		if ( $units > 0 ) {
			self::render_package_units_block( $units );
		}
		if ( $has_price ) {
			self::render_price_block( $item );
		}
		self::render_package_validity_block( $item );

		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_package_validity_block( array $item ) {
		$months = isset( $item['validityMonths'] ) ? (int) $item['validityMonths'] : 0;
		if ( $months <= 0 ) {
			return;
		}

		echo '<div class="canity-detail__validity">';
		echo '<span class="canity-detail__icon-badge">';
		Canity_Helpers::echo_svg_icon( Canity_Helpers::icon_calendar() );
		echo '</span>';
		echo '<div class="canity-detail__validity-text">';
		echo '<span class="canity-detail__validity-label">' . esc_html__( 'Validity', 'canity' ) . '</span>';
		printf(
			'<span class="canity-detail__validity-value">%s</span>',
			esc_html(
				sprintf(
					/* translators: %d: number of months */
					_n( '%d month', '%d months', $months, 'canity' ),
					$months
				)
			)
		);
		echo '</div></div>';
	}

	private static function render_package_redeem_section() {
		echo '<section class="canity-detail__section">';
		echo '<h3 class="canity-detail__section-title">' . esc_html__( 'Redeem package flexibly', 'canity' ) . '</h3>';
		echo '<p class="canity-detail__section-text">';
		echo esc_html__(
			'You can redeem your package when booking the corresponding appointment. It is available under "Payment method".',
			'canity'
		);
		echo '</p></section>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_package_valid_for_section( array $item ) {
		$offers = Canity_Item_Helpers::get_package_offer_labels( $item );

		echo '<section class="canity-detail__section">';
		echo '<h3 class="canity-detail__section-title">' . esc_html__( 'Valid for', 'canity' ) . '</h3>';
		echo '<p class="canity-detail__section-text">';
		echo esc_html__(
			'These offers can be booked with the package.',
			'canity'
		);
		echo '</p>';
		if ( [] !== $offers ) {
			echo '<ul class="canity-detail__offer-list">';
			foreach ( $offers as $offer ) {
				echo '<li>' . esc_html( $offer ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</section>';
	}

	/**
	 * @param int $units
	 */
	private static function render_package_units_block( $units ) {
		echo '<div class="canity-detail__units">';
		echo '<span class="canity-detail__icon-badge">';
		Canity_Helpers::echo_svg_icon( Canity_Helpers::icon_ticket() );
		echo '</span>';
		echo '<div class="canity-detail__units-text">';
		echo '<span class="canity-detail__units-label">' . esc_html__( 'Units', 'canity' ) . '</span>';
		printf(
			'<span class="canity-detail__units-value">%s</span>',
			esc_html(
				sprintf(
					/* translators: %d: number of units */
					_n( '%d unit', '%d units', $units, 'canity' ),
					$units
				)
			)
		);
		echo '</div></div>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_cta( $type, array $item ) {
		$url = self::booking_url( $type, $item );
		if ( '' === $url ) {
			return;
		}

		$label = 'packages' === $type
			? __( 'Buy', 'canity' )
			: __( 'Book', 'canity' );

		printf(
			'<div class="canity-detail__cta-wrap"><a class="canity-detail__cta" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></div>',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	private static function render_hero( $image_url, $resource, $alt, $date_badge = null ) {
		$placeholder = CANITY_PLUGIN_URL . 'assets/img/placeholder_' . $resource . '.png';
		$src         = '' !== $image_url ? $image_url : $placeholder;

		echo '<div class="canity-detail__hero">';
		printf(
			'<img class="canity-detail__hero-image" src="%1$s" alt="%2$s" loading="lazy" />',
			esc_url( $src ),
			esc_attr( $alt )
		);
		if ( is_array( $date_badge ) ) {
			echo '<div class="canity-detail__date">';
			echo '<span class="canity-detail__date-day">' . esc_html( $date_badge['day'] ) . '</span>';
			echo '<span class="canity-detail__date-month">' . esc_html( $date_badge['month'] ) . '</span>';
			echo '</div>';
		}
		echo '</div>';
	}

	private static function render_online_badge() {
		echo '<div class="canity-detail__online">';
		Canity_Helpers::echo_svg_icon( Canity_Helpers::icon_play() );
		echo '<span>' . esc_html__( 'Online', 'canity' ) . '</span></div>';
	}

	private static function render_service_meta_row( array $item ) {
		$duration  = isset( $item['duration'] ) ? (int) $item['duration'] : 0;
		$has_price = Canity_Item_Helpers::has_displayable_price( $item );

		if ( $duration <= 0 && ! $has_price ) {
			return;
		}

		echo '<div class="canity-detail__meta-row">';
		if ( $duration > 0 ) {
			self::render_duration_block( $item );
		}
		if ( $has_price ) {
			self::render_price_block( $item );
		}
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_service_location_section( array $item ) {
		$street    = isset( $item['address'] ) ? trim( (string) $item['address'] ) : '';
		$city_line = trim( ( (string) ( $item['zip'] ?? '' ) ) . ' ' . ( (string) ( $item['city'] ?? '' ) ) );
		$note      = trim( (string) ( $item['addressDescription'] ?? '' ) );
		$weblink   = trim( (string) ( $item['weblink'] ?? '' ) );


		echo '<section class="canity-detail__section">';
		echo '<h3 class="canity-detail__section-title">' . esc_html__( 'Location', 'canity' ) . '</h3>';
		echo '<div class="canity-detail__field">';
		echo '<span class="canity-detail__field-label">' . esc_html__( 'Address', 'canity' ) . '</span>';
		if ( '' !== $street || '' !== $city_line ) {
			if ( '' !== $street ) {
				echo '<span class="canity-detail__field-value">' . esc_html( $street ) . '</span>';
			}
			if ( '' !== $city_line ) {
				echo '<span class="canity-detail__field-value">' . esc_html( $city_line ) . '</span>';
			}
		} else {
			echo '<span class="canity-detail__field-value"> – </span>';
		}
		echo '</div>';
		
		self::render_schedule_field(
			__( 'Note', 'canity' ),
			'' !== $note ? $note : '–'
		);
		if ( '' !== $weblink ) {
			printf(
				'<div class="canity-detail__field"><span class="canity-detail__field-label">%1$s</span><a class="canity-detail__field-value canity-detail__field-link" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a></div>',
				esc_html__( 'Online link', 'canity' ),
				esc_url( $weblink ),
				esc_html__( 'Go to service', 'canity' )
			);
		}
		echo '</section>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_event_header( array $item ) {
		$datetime  = Canity_Date_Helpers::format_event_datetime_line( $item );
		$has_price = Canity_Item_Helpers::has_displayable_price( $item );

		if ( '' === $datetime && ! $has_price ) {
			return;
		}

		echo '<div class="canity-detail__header">';
		if ( '' !== $datetime ) {
			echo '<span class="canity-detail__datetime">' . esc_html( $datetime ) . '</span>';
		}
		self::render_price_block( $item );
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_event_schedule( array $item ) {
		$start_date = Canity_Date_Helpers::format_event_start_date( $item );
		$time_range = Canity_Date_Helpers::format_event_time_range( $item );
		$trainer    = Canity_Item_Helpers::get_responsible_trainer( $item );
		$address    = Canity_Item_Helpers::format_address_line( $item );
		$note       = trim( (string) ( $item['addressDescription'] ?? '' ) );
		$weblink    = trim( (string) ( $item['weblink'] ?? '' ) );

		if ( '' === $start_date && '' === $time_range && null === $trainer && '' === $address && '' === $note && '' === $weblink ) {
			return;
		}

		echo '<section class="canity-detail__section">';
		echo '<h3 class="canity-detail__section-title">' . esc_html__( 'Dates & location', 'canity' ) . '</h3>';
		echo '<div class="canity-detail__schedule">';
		echo '<div class="canity-detail__schedule-col">';
		if ( '' !== $start_date ) {
			self::render_schedule_field( __( 'Start date', 'canity' ), $start_date );
		}
		if ( '' !== $time_range ) {
			self::render_schedule_field( __( 'Time', 'canity' ), $time_range );
		}
		self::render_additional_event_dates_field( $item );
		if ( null !== $trainer ) {
			self::render_responsible_trainer_field( $trainer );
		}
		echo '</div>';
		echo '<div class="canity-detail__schedule-col">';
		self::render_schedule_field( __( 'Address', 'canity' ), '' !== $address ? $address : '–' );
		self::render_schedule_field(
			__( 'Note', 'canity' ),
			'' !== $note ? $note : '–'
		);
		if ( '' !== $weblink ) {
			printf(
				'<div class="canity-detail__field"><span class="canity-detail__field-label">%1$s</span><a class="canity-detail__field-value canity-detail__field-link" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s</a></div>',
				esc_html__( 'Online link', 'canity' ),
				esc_url( $weblink ),
				esc_html__( 'Go to event', 'canity' )
			);
		}
		echo '</div>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_description_section( array $item ) {
		$description = trim( (string) ( $item['description'] ?? '' ) );
		$categories  = $item['categories'] ?? [];

		echo '<section class="canity-detail__section">';
		echo '<h3 class="canity-detail__section-title">' . esc_html__( 'Description', 'canity' ) . '</h3>';
		echo '<div class="canity-detail__description">';
		echo wp_kses_post( wpautop( '' !== $description ? $description : '-' ) );
		self::render_category_tags( $categories );
		echo '</div>';
		echo '</section>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_legal_section( array $item ) {
		$has_cancellation = array_key_exists( 'cancellationEnabled', $item );
		$terms_html       = self::get_terms_html();

		if ( ! $has_cancellation && '' === $terms_html ) {
			return;
		}

		if ( $has_cancellation && '' !== $terms_html ) {
			$section_title = __( 'Cancellation policy and terms & conditions', 'canity' );
		} elseif ( $has_cancellation ) {
			$section_title = __( 'Cancellation policy', 'canity' );
		} else {
			$section_title = __( 'Terms & Conditions', 'canity' );
		}

		echo '<section class="canity-detail__section">';
		echo '<h3 class="canity-detail__section-title">' . esc_html( $section_title ) . '</h3>';

		if ( $has_cancellation ) {
			$enabled = ! empty( $item['cancellationEnabled'] );
			$days    = isset( $item['cancellationDays'] ) ? $item['cancellationDays'] : null;

			self::render_schedule_field(
				__( 'Free cancellation available', 'canity' ),
				$enabled
					? __( 'yes', 'canity' )
					: __( 'no', 'canity' )
			);

			if ( $enabled && null !== $days && '' !== $days ) {
				$deadline = sprintf(
					/* translators: %d: number of days before start */
					__( '%d day(s) before start', 'canity' ),
					(int) $days
				);
			} else {
				$deadline = '–';
			}

			self::render_schedule_field(
				__( 'Free cancellation deadline', 'canity' ),
				$deadline
			);
		}

		if ( '' !== $terms_html ) {
			echo $terms_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in get_terms_html().
		}

		echo '</section>';
	}

	/**
	 * @return string HTML for business terms, or empty string.
	 */
	private static function get_terms_html() {
		$business = Canity_API::fetch_business();
		if ( is_wp_error( $business ) ) {
			return '';
		}

		$terms_enabled = ! empty( $business['termsTextEnabled'] );

		if ( $terms_enabled ) {
			$terms_text = trim( (string) ( $business['termsText'] ?? '' ) );
			if ( '' === $terms_text ) {
				return '';
			}

			return self::render_terms_trigger( wp_kses_post( wpautop( $terms_text ) ) );
		}

		$terms_url = trim( (string) ( $business['termsUrl'] ?? '' ) );
		if ( '' === $terms_url ) {
			return '';
		}

		return self::render_terms_trigger( '', $terms_url );
	}

	/**
	 * @param string $content Sanitized HTML for inline terms.
	 * @param string $url     External terms URL.
	 */
	private static function render_terms_trigger( $content = '', $url = '' ) {
		if ( '' === $content && '' === $url ) {
			return '';
		}

		if ( '' !== $url ) {
			return sprintf(
				'<div class="canity-detail__terms-trigger">
					<button type="button" class="canity-detail__terms-button" data-canity-terms-url="%1$s">%2$s</button>
				</div>',
				esc_url( $url ),
				esc_html__( 'View terms', 'canity' )
			);
		}

		return sprintf(
			'<div class="canity-detail__terms-trigger">
				<button type="button" class="canity-detail__terms-button" data-canity-terms-open aria-haspopup="dialog">%1$s</button>
				<div class="canity-detail__terms-source" hidden>%2$s</div>
			</div>',
			esc_html__( 'View terms', 'canity' ),
			$content
		);
	}

	/**
	 * @param array<string, mixed> $item
	 * @param bool               $include_vat Whether to show the VAT-inclusive price label.
	 */
	private static function render_price_block( array $item, $include_vat = true ) {
		$payment = (string) ( $item['paymentType'] ?? '' );
		if ( 'FREE' === $payment ) {
			echo '<div class="canity-detail__price">';
			echo '<span class="canity-detail__icon-badge">';
			Canity_Helpers::echo_svg_icon( Canity_Helpers::icon_tag() );
			echo '</span>';
			echo '<div class="canity-detail__price-text">';
			echo '<span class="canity-detail__price-label">' . esc_html__( 'Price', 'canity' ) . '</span>';
			echo '<span class="canity-detail__price-value">' . esc_html__( 'Free', 'canity' ) . '</span>';
			echo '</div></div>';
			return;
		}

		$price_cents = isset( $item['price'] ) ? (int) $item['price'] : 0;
		$currency    = (string) ( $item['currency'] ?? 'EUR' );
		$formatted   = Canity_Helpers::format_detail_price( $price_cents, $currency );
		if ( '' === $formatted ) {
			return;
		}

		$price_label = $include_vat
			? __( 'Price (incl. VAT)', 'canity' )
			: __( 'Price', 'canity' );

		echo '<div class="canity-detail__price">';
		echo '<span class="canity-detail__icon-badge">';
		Canity_Helpers::echo_svg_icon( Canity_Helpers::icon_tag() );
		echo '</span>';
		echo '<div class="canity-detail__price-text">';
		echo '<span class="canity-detail__price-label">' . esc_html( $price_label ) . '</span>';
		echo '<span class="canity-detail__price-value">' . esc_html( $formatted ) . '</span>';
		echo '</div></div>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_duration_block( array $item ) {
		$duration = isset( $item['duration'] ) ? (int) $item['duration'] : 0;
		if ( $duration <= 0 ) {
			return;
		}

		echo '<div class="canity-detail__duration">';
		echo '<span class="canity-detail__icon-badge">';
		Canity_Helpers::echo_svg_icon( Canity_Helpers::icon_clock() );
		echo '</span>';
		echo '<div class="canity-detail__duration-text">';
		echo '<span class="canity-detail__duration-label">' . esc_html__( 'Duration', 'canity' ) . '</span>';
		printf(
			'<span class="canity-detail__duration-value">%s</span>',
			esc_html(
				sprintf(
					/* translators: %d: duration in minutes */
					__( '%d min', 'canity' ),
					$duration
				)
			)
		);
		echo '</div></div>';
	}

	private static function render_schedule_field( $label, $value ) {
		echo '<div class="canity-detail__field">';
		echo '<span class="canity-detail__field-label">' . esc_html( $label ) . '</span>';
		echo '<span class="canity-detail__field-value">' . esc_html( $value ) . '</span>';
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_additional_event_dates_field( array $item ) {
		$dates = Canity_Date_Helpers::get_additional_event_dates( $item );
		if ( [] === $dates ) {
			return;
		}

		$initial_visible = 2;
		$visible           = array_slice( $dates, 0, $initial_visible );
		$hidden            = array_slice( $dates, $initial_visible );

		echo '<div class="canity-detail__field canity-detail__field--additional-dates">';
		echo '<span class="canity-detail__field-label">' . esc_html__( 'Additional dates', 'canity' ) . '</span>';
		echo '<ul class="canity-detail__additional-dates">';
		foreach ( $visible as $entry ) {
			self::render_additional_event_date_item( $entry );
		}
		echo '</ul>';

		if ( [] !== $hidden ) {
			echo '<details class="canity-detail__additional-dates-more">';
			echo '<summary class="canity-detail__additional-dates-toggle">' . esc_html__( 'show all dates', 'canity' ) . '</summary>';
			echo '<ul class="canity-detail__additional-dates">';
			foreach ( $hidden as $entry ) {
				self::render_additional_event_date_item( $entry );
			}
			echo '</ul>';
			echo '</details>';
		}

		echo '</div>';
	}

	/**
	 * @param array{date: string, time: string} $entry
	 */
	private static function render_additional_event_date_item( array $entry ) {
		echo '<li class="canity-detail__additional-date">';
		echo '<span class="canity-detail__additional-date-day">' . esc_html( $entry['date'] ) . '</span>';
		if ( '' !== $entry['time'] ) {
			echo ' <span class="canity-detail__additional-date-time">' . esc_html( $entry['time'] ) . '</span>';
		}
		echo '</li>';
	}

	/**
	 * @param array<string, mixed> $trainer
	 */
	private static function render_responsible_trainer_field( array $trainer ) {
		$name = trim( (string) ( $trainer['name'] ?? '' ) );
		if ( '' === $name ) {
			return;
		}

		$image_url = Canity_Helpers::extract_image_url( $trainer['image'] ?? null );
		$initial   = mb_strtoupper( mb_substr( $name, 0, 1 ) );

		echo '<div class="canity-detail__field">';
		echo '<span class="canity-detail__field-label">' . esc_html__( 'Responsible', 'canity' ) . '</span>';
		echo '<div class="canity-detail__trainer">';
		if ( '' !== $image_url ) {
			printf(
				'<img class="canity-detail__trainer-avatar" src="%1$s" alt="" loading="lazy" />',
				esc_url( $image_url )
			);
		} else {
			echo '<span class="canity-detail__trainer-avatar canity-detail__trainer-avatar--initial" aria-hidden="true">' . esc_html( $initial ) . '</span>';
		}
		echo '<span class="canity-detail__trainer-name">' . esc_html( $name ) . '</span>';
		echo '</div></div>';
	}

	/**
	 * @param array<int, mixed> $categories
	 */
	private static function render_category_tags( $categories ) {
		$labels = Canity_Item_Helpers::extract_category_labels( $categories );
		if ( [] === $labels ) {
			return;
		}

		echo '<div class="canity-detail__tags">';
		foreach ( $labels as $label ) {
			echo '<span class="canity-detail__tag">' . esc_html( $label ) . '</span>';
		}
		echo '</div>';
	}

	/**
	 * @param array<int, mixed> $gallery
	 */
	private static function render_gallery( $gallery ) {
		if ( ! is_array( $gallery ) || [] === $gallery ) {
			return;
		}

		echo '<div class="canity-detail__gallery">';
		foreach ( $gallery as $image ) {
			$url = Canity_Helpers::extract_image_url( $image );
			if ( '' === $url ) {
				continue;
			}
			printf(
				'<img class="canity-detail__gallery-image" src="%1$s" alt="" loading="lazy" />',
				esc_url( $url )
			);
		}
		echo '</div>';
	}
}
