<?php
/**
 * Shared item data extraction and URL helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Canity_Item_Helpers {

	/**
	 * @param array<string, mixed> $item
	 */
	public static function get_item_id( array $item ) {
		return isset( $item['id'] ) ? trim( (string) $item['id'] ) : '';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public static function detail_url( $resource, array $item ) {
		$business_slug = isset( $item['businessSlug'] ) ? trim( (string) $item['businessSlug'] ) : '';
		if ( '' === $business_slug ) {
			$business_slug = Canity_API::get_business_slug();
		}
		if ( '' === $business_slug ) {
			return '';
		}
		$base = untrailingslashit( CANITY_PUBLIC_BASE ) . '/b/' . rawurlencode( $business_slug );

		switch ( $resource ) {
			case 'services':
				$slug = isset( $item['slug'] ) ? trim( (string) $item['slug'] ) : '';
				return '' !== $slug ? $base . '/s/' . rawurlencode( $slug ) : '';
			case 'events':
				$slug = isset( $item['slug'] ) ? trim( (string) $item['slug'] ) : '';
				return '' !== $slug ? $base . '/e/' . rawurlencode( $slug ) : '';
			case 'packages':
				$id = self::get_item_id( $item );
				return '' !== $id ? $base . '/packages/' . rawurlencode( $id ) : '';
		}

		return '';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public static function booking_url( $type, array $item ) {
		$url = self::detail_url( $type, $item );
		if ( '' === $url ) {
			return '';
		}

		return untrailingslashit( $url ) . '/checkout';
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public static function format_address_line( array $item ) {
		$parts = array_filter(
			[
				isset( $item['address'] ) ? trim( (string) $item['address'] ) : '',
				trim( ( (string) ( $item['zip'] ?? '' ) ) . ' ' . ( (string) ( $item['city'] ?? '' ) ) ),
			]
		);

		return [] !== $parts ? implode( ', ', $parts ) : '';
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<string, mixed>|null
	 */
	public static function get_responsible_trainer( array $item ) {
		$trainer = $item['responsibleTrainer'] ?? null;
		if ( ! is_array( $trainer ) ) {
			return null;
		}

		$name = trim( (string) ( $trainer['name'] ?? '' ) );
		if ( '' === $name ) {
			return null;
		}

		return $trainer;
	}

	/**
	 * @param array<int, mixed> $categories
	 * @return list<string>
	 */
	public static function extract_category_labels( $categories ) {
		if ( ! is_array( $categories ) || [] === $categories ) {
			return [];
		}

		$labels = [];
		foreach ( $categories as $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}
			$label = trim( (string) ( $category['title'] ?? $category['name'] ?? '' ) );
			if ( '' !== $label ) {
				$labels[] = $label;
			}
		}

		return $labels;
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<int, string>
	 */
	public static function get_package_offer_labels( array $item ) {
		$labels = [];

		foreach ( [ 'services', 'events' ] as $key ) {
			$offerings = $item[ $key ] ?? [];
			if ( ! is_array( $offerings ) ) {
				continue;
			}
			foreach ( $offerings as $offering ) {
				if ( is_array( $offering ) && ! empty( $offering['title'] ) ) {
					$labels[] = (string) $offering['title'];
				}
			}
		}

		return $labels;
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public static function has_displayable_price( array $item ) {
		return 'FREE' === (string) ( $item['paymentType'] ?? '' )
			|| ( isset( $item['price'] ) && (int) $item['price'] > 0 );
	}

	public static function format_list_price_label( $payment_type, $price_cents, $currency ) {
		if ( 'FREE' === $payment_type ) {
			return __( 'free', 'canity' );
		}

		return Canity_Helpers::format_list_price( $price_cents, $currency );
	}

	public static function should_show_price_vat_mark( $payment_type, $price_cents ) {
		return 'FREE' !== $payment_type && $price_cents > 0;
	}

	/**
	 * @param array<int, mixed> $items
	 */
	public static function list_has_vatable_prices( $items ) {
		if ( ! is_array( $items ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$payment = (string) ( $item['paymentType'] ?? '' );
			$price   = isset( $item['price'] ) ? (int) $item['price'] : 0;
			if ( self::should_show_price_vat_mark( $payment, $price ) ) {
				return true;
			}
		}

		return false;
	}
}
