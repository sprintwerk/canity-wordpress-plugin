<?php
/**
 * Shortcode:
 *   [canity type="services|events|packages" limit="0" detail=""]
 *   [canity_detail]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Canity_Shortcodes {

	const SHORTCODE = 'canity';

	const ALLOWED_TYPES = [ 'services', 'events', 'packages' ];

	public static function init() {
		add_shortcode( self::SHORTCODE, [ __CLASS__, 'shortcode' ] );
		// Gutenberg renders blocks before wpautop; empty <p> tags appear between our elements.
		add_filter( 'the_content', [ __CLASS__, 'strip_autop_artifacts' ], 20 );
	}

	/**
	 * Remove empty paragraphs WordPress wpautop inserts into plugin markup.
	 */
	public static function strip_autop_artifacts( $content ) {
		if ( ! is_string( $content ) || false === strpos( $content, 'canity-' ) ) {
			return $content;
		}

		return preg_replace( '#<p>\s*(?:&nbsp;|\xc2\xa0|\s)*</p>#iu', '', $content );
	}

	/**
	 * Shortcode entry point. Dispatches on the `type` attribute.
	 *
	 * @param array<string, mixed>|string $atts
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			[
				'type'   => '',
				'limit'  => 0,
				'detail' => '',
			],
			(array) $atts,
			self::SHORTCODE
		);

		$type = strtolower( trim( (string) $atts['type'] ) );
		if ( ! in_array( $type, self::ALLOWED_TYPES, true ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="canity-error">' . esc_html(
					sprintf(
						/* translators: %s: list of allowed values */
						__( 'CANITY shortcode: the "type" attribute is missing or invalid. Allowed: %s.', 'canity' ),
						implode( ', ', self::ALLOWED_TYPES )
					)
				) . '</div>';
			}
			return '';
		}

		return self::render(
			$type,
			[
				'limit'  => $atts['limit'],
				'detail' => $atts['detail'],
			]
		);
	}

	/**
	 * Renders a resource list. Output is fully escaped.
	 *
	 * @param string               $resource services|events|packages
	 * @param array<string, mixed> $atts
	 */
	public static function render( $resource, $atts = [] ) {
		$atts = shortcode_atts(
			[
				'limit'  => 0,
				'detail' => '',
			],
			$atts,
			self::SHORTCODE
		);

		$detail_mode = Canity_Settings::resolve_detail_mode( (string) $atts['detail'] );

		$limit = max( 0, (int) $atts['limit'] );

		$list = Canity_API::fetch_list( $resource, $limit );
		if ( is_wp_error( $list ) ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="canity-error">' . esc_html( $list->get_error_message() ) . '</div>';
			}
			return '';
		}

		$items        = $list['items'];
		$hidden_count = $list['hidden_customers_only'];

		if ( empty( $items ) && 0 === $hidden_count ) {
			return '<div class="canity-empty">' . esc_html__( 'No entries found.', 'canity' ) . '</div>';
		}

		Canity_Assets::mark_needed( $detail_mode );

		$grid_class = 'canity-grid canity-grid--' . sanitize_html_class( $resource );
		if ( in_array( $detail_mode, [ 'modal', 'inline' ], true ) ) {
			$grid_class .= ' canity-grid--interactive';
		}
		if ( 'inline' === $detail_mode ) {
			$grid_class .= ' canity-grid--inline';
		}

		ob_start();
		echo '<div class="canity-list">';
		echo '<div class="' . esc_attr( $grid_class ) . '" data-canity-detail-mode="' . esc_attr( $detail_mode ) . '">';
		foreach ( $items as $item ) {
			self::render_card( $resource, (array) $item, $detail_mode );
		}
		if ( $hidden_count > 0 ) {
			self::render_customers_only_tile( $resource, $hidden_count, ! empty( $items ) );
		}
		echo '</div>';
		if ( Canity_Item_Helpers::list_has_vatable_prices( $items ) ) {
			echo '<p class="canity-list__vat-note">' . esc_html__( '* incl. VAT', 'canity' ) . '</p>';
		}
		echo '</div>';
		return self::strip_autop_artifacts( ob_get_clean() );
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public static function public_detail_url( $resource, array $item ) {
		return Canity_Item_Helpers::detail_url( $resource, $item );
	}

	private static function render_card( $resource, array $item, $detail_mode ) {
		if ( 'inline' === $detail_mode ) {
			echo '<div class="canity-grid__item">';
		}

		switch ( $resource ) {
			case 'services':
				self::render_service_card( $item, $resource, $detail_mode );
				break;
			case 'events':
				self::render_event_card( $item, $resource, $detail_mode );
				break;
			case 'packages':
				self::render_package_card( $item, $resource, $detail_mode );
				break;
		}

		if ( 'inline' === $detail_mode ) {
			echo '</div>';
			$id = Canity_Item_Helpers::get_item_id( $item );
			if ( '' !== $id ) {
				printf(
					'<div class="canity-inline-detail" id="canity-inline-%1$s-%2$s" data-canity-type="%1$s" data-canity-id="%2$s" hidden>
						<button type="button" class="canity-inline-detail__close" data-canity-inline-close aria-label="%3$s">&times;</button>
						<div class="canity-inline-detail__content"></div>
					</div>',
					esc_attr( $resource ),
					esc_attr( $id ),
					esc_attr__( 'Close', 'canity' )
				);
			}
		}
	}

	/**
	 * Teaser tile for Stammkunden-only offerings the Partner API leaves out of the list.
	 *
	 * Website visitors are never signed in to CANITY, so the tile links to the business's
	 * full services/events list on CANITY, where Stammkund:innen can sign in and see those offerings.
	 *
	 * @param string $resource             services|events
	 * @param int    $count                Number of hidden offerings.
	 * @param bool   $has_public_offerings False when the tile stands alone, so it drops "more".
	 */
	private static function render_customers_only_tile( $resource, $count, $has_public_offerings ) {
		$business_name = Canity_API::get_business_name();
		$business_slug = Canity_API::get_business_slug();

		if ( $has_public_offerings ) {
			$label = '' !== $business_name
				/* translators: 1: number of offerings, 2: business name */
				? _n( '%1$d more offering is exclusive to regular customers of %2$s.', '%1$d more offerings are exclusive to regular customers of %2$s.', $count, 'canity' )
				/* translators: %d: number of offerings */
				: _n( '%d more offering is exclusive to regular customers.', '%d more offerings are exclusive to regular customers.', $count, 'canity' );
		} else {
			$label = '' !== $business_name
				/* translators: 1: number of offerings, 2: business name */
				? _n( '%1$d offering is exclusive to regular customers of %2$s.', '%1$d offerings are exclusive to regular customers of %2$s.', $count, 'canity' )
				/* translators: %d: number of offerings */
				: _n( '%d offering is exclusive to regular customers.', '%d offerings are exclusive to regular customers.', $count, 'canity' );
		}
		$text = sprintf( $label, (int) $count, $business_name );

		$list_paths  = [
			'services' => '/s',
			'events'   => '/e',
		];
		$profile_url = '' !== $business_slug && isset( $list_paths[ $resource ] )
			? untrailingslashit( CANITY_PUBLIC_BASE ) . '/b/' . rawurlencode( $business_slug ) . $list_paths[ $resource ]
			: '';

		// A <div> wrapper keeps wpautop from wrapping the tile in <p>; the link stretches over it like on the cards.
		echo '<div class="canity-customers-only-tile' . ( '' !== $profile_url ? ' canity-customers-only-tile--linked' : '' ) . '">';
		echo '<div class="canity-customers-only-tile__circle">';
		Canity_Helpers::echo_svg_icon( Canity_Helpers::icon_lock() );
		echo '</div>';
		if ( '' !== $profile_url ) {
			printf(
				'<div class="canity-customers-only-tile__text"><a class="canity-customers-only-tile__link" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></div>',
				esc_url( $profile_url ),
				esc_html( $text )
			);
		} else {
			echo '<div class="canity-customers-only-tile__text">' . esc_html( $text ) . '</div>';
		}
		echo '</div>';
	}

	private static function render_service_card( array $item, $resource, $detail_mode ) {
		$title       = (string) ( $item['title'] ?? '' );
		$subtitle    = (string) ( $item['shortDescription'] ?? '' );
		$image_url   = Canity_Helpers::extract_image_url( $item['image'] ?? $item['serviceImage'] ?? null );
		$is_online   = ! empty( $item['isOnline'] );
		$duration    = isset( $item['duration'] ) ? (int) $item['duration'] : 0;
		$payment     = (string) ( $item['paymentType'] ?? '' );
		$price_cents = isset( $item['price'] ) ? (int) $item['price'] : 0;
		$currency    = (string) ( $item['currency'] ?? 'EUR' );

		$external_url = Canity_Item_Helpers::detail_url( $resource, $item );

		self::open_card( 'service', $detail_mode, $external_url );
		self::render_hero( $image_url, 'service', null, $title );
		if ( $is_online ) {
			self::render_online_badge();
		}
		echo '<div class="canity-card__title-block">';
		self::render_card_title( $title, $resource, $item, $detail_mode, $external_url );
		if ( '' !== $subtitle ) {
			echo '<div class="canity-card__subtitle">' . esc_html( $subtitle ) . '</div>';
		}
		echo '</div>';

		echo '<div class="canity-card__section">';
		echo '<div class="canity-card__info-row">';
		if ( $duration > 0 ) {
			echo '<div class="canity-card__info-item">';
			self::render_icon_badge( Canity_Helpers::icon_clock() );
			printf(
				'<span class="canity-card__info-text">%s %s</span>',
				esc_html( (string) $duration ),
				esc_html__( 'minutes', 'canity' )
			);
			echo '</div>';
		}
		echo '<div class="canity-card__info-item">';
		self::render_icon_badge( Canity_Helpers::icon_tag() );
		self::render_list_price( $payment, $price_cents, $currency, 'span', 'canity-card__info-text' );
		echo '</div>';
		echo '</div>';
		echo '</div>';

		echo '</div>';
	}

	private static function render_event_card( array $item, $resource, $detail_mode ) {
		$title       = (string) ( $item['title'] ?? '' );
		$image_url   = Canity_Helpers::extract_image_url( $item['image'] ?? $item['eventImage'] ?? null );
		$is_online   = ! empty( $item['isOnline'] );
		$additional  = isset( $item['additionalDates'] ) && is_array( $item['additionalDates'] ) ? $item['additionalDates'] : [];
		$dates_count = 1 + count( $additional );
		$payment     = (string) ( $item['paymentType'] ?? '' );
		$price_cents = isset( $item['price'] ) ? (int) $item['price'] : 0;
		$currency    = (string) ( $item['currency'] ?? 'EUR' );

		$date_badge   = Canity_Date_Helpers::format_event_date_badge( $item );
		$external_url = Canity_Item_Helpers::detail_url( $resource, $item );

		self::open_card( 'event', $detail_mode, $external_url );
		self::render_hero( $image_url, 'event', $date_badge, $title );
		if ( $is_online ) {
			self::render_online_badge();
		}
		echo '<div class="canity-card__title-block">';
		self::render_card_title( $title, $resource, $item, $detail_mode, $external_url );
		echo '<div class="canity-card__subtitle">';
		printf(
			esc_html(
				/* translators: %d: number of dates */
				_n( '%d date', '%d dates', $dates_count, 'canity' )
			),
			(int) $dates_count
		);
		echo '</div>';
		echo '</div>';

		echo '<div class="canity-card__section">';
		echo '<div class="canity-card__info-item">';
		self::render_icon_badge( Canity_Helpers::icon_tag() );
		self::render_list_price( $payment, $price_cents, $currency, 'span', 'canity-card__info-text' );
		echo '</div>';
		echo '</div>';

		echo '</div>';
	}

	private static function render_package_card( array $item, $resource, $detail_mode ) {
		$title       = (string) ( $item['title'] ?? '' );
		$units       = isset( $item['unitCount'] ) ? (int) $item['unitCount'] : 0;
		$price_cents = isset( $item['price'] ) ? (int) $item['price'] : 0;
		$currency    = (string) ( $item['currency'] ?? 'EUR' );

		$external_url = Canity_Item_Helpers::detail_url( $resource, $item );

		self::open_card( 'package', $detail_mode, $external_url );
		echo '<div class="canity-card__icon-circle">';
		Canity_Helpers::echo_svg_icon( Canity_Helpers::icon_package_card() );
		echo '</div>';
		echo '<div class="canity-card__title-block">';
		self::render_card_title( $title, $resource, $item, $detail_mode, $external_url );
		echo '<div class="canity-card__subtitle">';
		printf(
			esc_html(
				/* translators: %d: number of units */
				_n( '%d unit', '%d units', $units, 'canity' )
			),
			(int) $units
		);
		echo '</div>';
		echo '</div>';
		self::render_list_price( '', $price_cents, $currency, 'div', 'canity-card__price' );
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	private static function render_card_title( $title, $resource, array $item, $detail_mode, $external_url ) {
		$id = Canity_Item_Helpers::get_item_id( $item );

		if ( 'external' === $detail_mode ) {
			self::render_title_link( $title, $external_url, true );
			return;
		}

		if ( 'page' === $detail_mode ) {
			$page_url = Canity_Settings::get_detail_page_url();
			if ( '' !== $page_url && '' !== $id ) {
				$href = add_query_arg(
					[
						'canity_type' => $resource,
						'canity_id'   => $id,
					],
					$page_url
				);
				self::render_title_link( $title, $href, false );
				return;
			}
			self::render_title_link( $title, $external_url, true );
			return;
		}

		if ( in_array( $detail_mode, [ 'modal', 'inline' ], true ) && '' !== $id ) {
			printf(
				'<div class="canity-card__title"><button type="button" class="canity-card__trigger" data-canity-type="%1$s" data-canity-id="%2$s" aria-expanded="false"><span class="canity-card__title-text">%3$s</span></button></div>',
				esc_attr( $resource ),
				esc_attr( $id ),
				esc_html( $title )
			);
			return;
		}

		echo '<div class="canity-card__title">' . esc_html( $title ) . '</div>';
	}

	private static function render_title_link( $title, $url, $new_tab ) {
		if ( '' !== $url ) {
			if ( $new_tab ) {
				printf(
					'<div class="canity-card__title"><a class="canity-card__link" href="%1$s" target="_blank" rel="noopener noreferrer"><span class="canity-card__title-text">%2$s</span></a></div>',
					esc_url( $url ),
					esc_html( $title )
				);
			} else {
				printf(
					'<div class="canity-card__title"><a class="canity-card__link" href="%1$s"><span class="canity-card__title-text">%2$s</span></a></div>',
					esc_url( $url ),
					esc_html( $title )
				);
			}
			return;
		}
		echo '<div class="canity-card__title">' . esc_html( $title ) . '</div>';
	}

	private static function render_hero( $image_url, $resource, $date_badge, $alt ) {
		$placeholder = CANITY_PLUGIN_URL . 'assets/img/placeholder_' . $resource . '.png';
		$src         = '' !== $image_url ? $image_url : $placeholder;

		echo '<div class="canity-card__hero">';
		printf(
			'<img class="canity-card__hero-image" src="%1$s" alt="%2$s" loading="lazy" />',
			esc_url( $src ),
			esc_attr( $alt )
		);
		if ( null !== $date_badge ) {
			if ( isset( $date_badge['type'] ) && 'range' === $date_badge['type'] ) {
				echo '<div class="canity-card__date canity-card__date--range">';
				echo '<div class="canity-card__date-part">';
				echo '<span class="canity-card__date-day">' . esc_html( $date_badge['start']['day'] ) . '</span>';
				echo '<span class="canity-card__date-month">' . esc_html( $date_badge['start']['month'] ) . '</span>';
				echo '</div>';
				echo '<span class="canity-card__date-separator" aria-hidden="true">—</span>';
				echo '<div class="canity-card__date-part">';
				echo '<span class="canity-card__date-day">' . esc_html( $date_badge['end']['day'] ) . '</span>';
				echo '<span class="canity-card__date-month">' . esc_html( $date_badge['end']['month'] ) . '</span>';
				echo '</div>';
				echo '</div>';
			} else {
				echo '<div class="canity-card__date">';
				echo '<span class="canity-card__date-day">' . esc_html( $date_badge['day'] ) . '</span>';
				echo '<span class="canity-card__date-month">' . esc_html( $date_badge['month'] ) . '</span>';
				echo '</div>';
			}
		}
		echo '</div>';
	}

	private static function render_online_badge() {
		echo '<div class="canity-card__online">';
		Canity_Helpers::echo_svg_icon( Canity_Helpers::icon_play() );
		echo '<span>' . esc_html__( 'Online', 'canity' ) . '</span></div>';
	}

	private static function open_card( $modifier, $detail_mode, $external_url ) {
		$class = 'canity-card canity-card--' . $modifier;
		if ( 'external' === $detail_mode && '' !== $external_url ) {
			$class .= ' canity-card--linked';
		}
		if ( 'page' === $detail_mode && '' !== Canity_Settings::get_detail_page_url() ) {
			$class .= ' canity-card--linked';
		}
		if ( in_array( $detail_mode, [ 'modal', 'inline' ], true ) ) {
			$class .= ' canity-card--triggerable';
		}
		printf( '<div class="%s">', esc_attr( $class ) );
	}

	private static function render_icon_badge( $svg ) {
		echo '<span class="canity-card__icon-badge">';
		Canity_Helpers::echo_svg_icon( $svg );
		echo '</span>';
	}

	private static function render_list_price( $payment_type, $price_cents, $currency, $wrapper_tag, $wrapper_class ) {
		printf( '<%1$s class="%2$s">', tag_escape( $wrapper_tag ), esc_attr( $wrapper_class ) );
		echo esc_html( Canity_Item_Helpers::format_list_price_label( $payment_type, $price_cents, $currency ) );
		if ( Canity_Item_Helpers::should_show_price_vat_mark( $payment_type, $price_cents ) ) {
			echo '<span class="canity-card__price-mark" aria-hidden="true">*</span>';
		}
		printf( '</%s>', tag_escape( $wrapper_tag ) );
	}
}
