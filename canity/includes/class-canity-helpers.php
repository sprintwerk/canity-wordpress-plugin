<?php
/**
 * Shared formatting and icon helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Canity_Helpers {

	/**
	 * @param mixed $field
	 */
	public static function extract_image_url( $field ) {
		if ( is_string( $field ) ) {
			return $field;
		}
		if ( is_array( $field ) ) {
			foreach ( [ 'url', 'src', 'href' ] as $key ) {
				if ( ! empty( $field[ $key ] ) && is_string( $field[ $key ] ) ) {
					return $field[ $key ];
				}
			}
		}
		return '';
	}

	public static function format_list_price( $price_cents, $currency ) {
		if ( $price_cents <= 0 ) {
			return '';
		}

		if ( 0 === $price_cents % 100 ) {
			return ( (int) ( $price_cents / 100 ) ) . ' ' . self::currency_symbol( $currency );
		}

		return number_format( $price_cents / 100, 2, ',', '.' ) . ' ' . self::currency_symbol( $currency );
	}

	public static function format_detail_price( $price_cents, $currency ) {
		if ( $price_cents <= 0 ) {
			return '';
		}

		$amount = number_format( $price_cents / 100, 2, ',', '.' );

		return $amount . ' ' . self::currency_symbol( $currency );
	}

	/**
	 * Allowed HTML for plugin SVG icons.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_svg_html() {
		return [
			'svg'      => [
				'viewbox'           => true,
				'width'             => true,
				'height'            => true,
				'fill'              => true,
				'stroke'            => true,
				'stroke-width'      => true,
				'stroke-linecap'    => true,
				'stroke-linejoin'   => true,
				'aria-hidden'       => true,
			],
			'path'     => [
				'd'    => true,
				'fill' => true,
			],
			'circle'   => [
				'cx' => true,
				'cy' => true,
				'r'  => true,
			],
			'polyline' => [
				'points' => true,
			],
			'rect'     => [
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
			],
			'line'     => [
				'x1' => true,
				'y1' => true,
				'x2' => true,
				'y2' => true,
			],
		];
	}

	/**
	 * Echo a plugin SVG icon (sanitized for output).
	 *
	 * @param string $svg Raw SVG markup from icon_*() helpers.
	 */
	public static function echo_svg_icon( $svg ) {
		echo wp_kses( $svg, self::allowed_svg_html() );
	}

	public static function currency_symbol( $currency ) {
		switch ( strtoupper( (string) $currency ) ) {
			case 'EUR':
				return '€';
			case 'USD':
				return '$';
			case 'GBP':
				return '£';
			case 'CHF':
				return 'CHF';
		}

		return (string) $currency;
	}

	public static function icon_play() {
		return '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm-2 14V8l6 4z"/></svg>';
	}

	public static function icon_clock() {
		return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg>';
	}

	public static function icon_tag() {
		return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.5"/></svg>';
	}

	public static function icon_ticket() {
		return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>';
	}

	public static function icon_lock() {
		return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><line x1="12" y1="15" x2="12" y2="17"/></svg>';
	}

	public static function icon_package_card() {
		return '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 12a2 2 0 0 1 2-2V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v4a2 2 0 0 1 0 4v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 1-2-2zm-9 4.5h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2v-2h2v2z"/></svg>';
	}

	public static function icon_calendar() {
		return '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
	}

	public static function icon_package_header() {
		return '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M20 12a2 2 0 0 1 2-2V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v4a2 2 0 0 1 0 4v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 1-2-2zm-9 4.5h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2v-2h2v2z"/></svg>';
	}
}
