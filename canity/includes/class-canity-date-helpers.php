<?php
/**
 * Shared date and time formatting for CANITY items.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Canity_Date_Helpers {

	/**
	 * @param array<string, mixed> $item
	 * @return array<string, mixed>|null
	 */
	public static function format_event_date_badge( array $item ) {
		$timestamps = self::get_event_date_timestamps( $item );
		if ( [] === $timestamps ) {
			return null;
		}

		if ( 1 === count( $timestamps ) ) {
			return array_merge(
				[ 'type' => 'single' ],
				self::format_date_badge_parts( $timestamps[0] )
			);
		}

		return [
			'type'  => 'range',
			'start' => self::format_date_badge_parts( $timestamps[0] ),
			'end'   => self::format_date_badge_parts( $timestamps[ count( $timestamps ) - 1 ] ),
		];
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<int, int>
	 */
	public static function get_event_date_timestamps( array $item ) {
		$timestamps = [];

		$starts_at = (string) ( $item['startsAt'] ?? '' );
		if ( '' !== $starts_at ) {
			$ts = strtotime( $starts_at );
			if ( false !== $ts ) {
				$timestamps[] = $ts;
			}
		}

		$additional = $item['additionalDates'] ?? [];
		if ( is_array( $additional ) ) {
			foreach ( $additional as $extra ) {
				$iso = '';
				if ( is_array( $extra ) ) {
					$iso = (string) ( $extra['startsAt'] ?? '' );
				} elseif ( is_string( $extra ) ) {
					$iso = $extra;
				}
				if ( '' === $iso ) {
					continue;
				}
				$ts = strtotime( $iso );
				if ( false !== $ts ) {
					$timestamps[] = $ts;
				}
			}
		}

		sort( $timestamps );

		return $timestamps;
	}

	/**
	 * @param int $timestamp Unix timestamp.
	 * @return array{day: string, month: string}
	 */
	public static function format_date_badge_parts( $timestamp ) {
		return [
			'day'   => date_i18n( 'd', $timestamp ),
			'month' => mb_strtoupper( date_i18n( 'M', $timestamp ) ),
		];
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public static function format_event_datetime_line( array $item ) {
		$starts_at = (string) ( $item['startsAt'] ?? '' );
		if ( '' === $starts_at ) {
			return '';
		}

		$start_ts = strtotime( $starts_at );
		if ( false === $start_ts ) {
			return '';
		}

		$date_part = self::format_german_event_date( $start_ts, 'long' );
		$time_part = self::format_event_time_range( $item );
		if ( '' === $time_part ) {
			return $date_part;
		}

		return $date_part . ' | ' . $time_part;
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public static function format_event_start_date( array $item ) {
		$starts_at = (string) ( $item['startsAt'] ?? '' );
		if ( '' === $starts_at ) {
			return '';
		}

		$start_ts = strtotime( $starts_at );
		if ( false === $start_ts ) {
			return '';
		}

		return self::format_german_event_date( $start_ts, 'long' );
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public static function format_event_time_range( array $item ) {
		$starts_at = (string) ( $item['startsAt'] ?? '' );
		if ( '' === $starts_at ) {
			return '';
		}

		$start_ts = strtotime( $starts_at );
		if ( false === $start_ts ) {
			return '';
		}

		$ends_at = (string) ( $item['endsAt'] ?? '' );
		$end_ts  = '' !== $ends_at ? strtotime( $ends_at ) : false;
		if ( false !== $end_ts ) {
			return sprintf(
				/* translators: 1: start time, 2: end time */
				__( '%1$s – %2$s', 'canity' ),
				date_i18n( 'H:i', $start_ts ),
				date_i18n( 'H:i', $end_ts )
			);
		}

		return date_i18n( get_option( 'time_format' ), $start_ts );
	}

	/**
	 * @param int    $timestamp Unix timestamp.
	 * @param string $style     'short' or 'long'.
	 */
	public static function format_german_event_date( $timestamp, $style = 'long' ) {
		$weekday = self::german_weekday_name( (int) date_i18n( 'w', $timestamp ) );
		$day     = date_i18n( 'j', $timestamp );
		$month   = 'short' === $style
			? self::german_month_short_name( (int) date_i18n( 'n', $timestamp ) )
			: self::german_month_full_name( (int) date_i18n( 'n', $timestamp ) );

		if ( 'short' === $style ) {
			return $weekday . ', ' . $day . '. ' . $month . '.';
		}

		return $weekday . ', ' . $day . '. ' . $month;
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<int, array{date: string, time: string}>
	 */
	public static function get_additional_event_dates( array $item ) {
		$additional = $item['additionalDates'] ?? [];
		if ( ! is_array( $additional ) || [] === $additional ) {
			return [];
		}

		$dates = [];
		foreach ( $additional as $extra ) {
			$line = self::format_additional_event_date_line( $extra, $item );
			if ( null !== $line ) {
				$dates[] = $line;
			}
		}

		return $dates;
	}

	/**
	 * @param array<string, mixed>|string $extra
	 * @param array<string, mixed>        $item
	 * @return array{date: string, time: string}|null
	 */
	public static function format_additional_event_date_line( $extra, array $item ) {
		$starts_at = '';
		$ends_at   = '';

		if ( is_array( $extra ) ) {
			$starts_at = (string) ( $extra['startsAt'] ?? '' );
			$ends_at   = (string) ( $extra['endsAt'] ?? '' );
		} elseif ( is_string( $extra ) ) {
			$starts_at = $extra;
		}

		if ( '' === $starts_at ) {
			return null;
		}

		$start_ts = strtotime( $starts_at );
		if ( false === $start_ts ) {
			return null;
		}

		$end_ts = '' !== $ends_at ? strtotime( $ends_at ) : self::infer_event_end_timestamp( $start_ts, $item );
		$time   = self::format_event_time_range_for_timestamps( $start_ts, $end_ts );

		return [
			'date' => self::format_german_event_date( $start_ts, 'long' ),
			'time' => $time,
		];
	}

	/**
	 * @param array<string, mixed> $item
	 */
	public static function infer_event_end_timestamp( $start_ts, array $item ) {
		$main_start = (string) ( $item['startsAt'] ?? '' );
		$main_end   = (string) ( $item['endsAt'] ?? '' );
		if ( '' !== $main_start && '' !== $main_end ) {
			$main_start_ts = strtotime( $main_start );
			$main_end_ts   = strtotime( $main_end );
			if ( false !== $main_start_ts && false !== $main_end_ts && $main_end_ts > $main_start_ts ) {
				return $start_ts + ( $main_end_ts - $main_start_ts );
			}
		}

		$duration = isset( $item['duration'] ) ? (int) $item['duration'] : 0;
		if ( $duration > 0 ) {
			return $start_ts + ( $duration * 60 );
		}

		return false;
	}

	public static function format_event_time_range_for_timestamps( $start_ts, $end_ts ) {
		if ( false === $start_ts ) {
			return '';
		}

		if ( false !== $end_ts && $end_ts > $start_ts ) {
			return sprintf(
				/* translators: 1: start time, 2: end time */
				__( '%1$s – %2$s', 'canity' ),
				date_i18n( 'H:i', $start_ts ),
				date_i18n( 'H:i', $end_ts )
			);
		}

		return date_i18n( 'H:i', $start_ts );
	}

	private static function german_weekday_name( $index ) {
		$weekdays = [
			__( 'Sunday', 'canity' ),
			__( 'Monday', 'canity' ),
			__( 'Tuesday', 'canity' ),
			__( 'Wednesday', 'canity' ),
			__( 'Thursday', 'canity' ),
			__( 'Friday', 'canity' ),
			__( 'Saturday', 'canity' ),
		];

		return $weekdays[ $index ] ?? '';
	}

	private static function german_month_short_name( $index ) {
		$months = [
			1  => __( 'Jan.', 'canity' ),
			2  => __( 'Feb.', 'canity' ),
			3  => __( 'Mar.', 'canity' ),
			4  => __( 'Apr.', 'canity' ),
			5  => __( 'May', 'canity' ),
			6  => __( 'Jun.', 'canity' ),
			7  => __( 'Jul.', 'canity' ),
			8  => __( 'Aug.', 'canity' ),
			9  => __( 'Sep.', 'canity' ),
			10 => __( 'Oct.', 'canity' ),
			11 => __( 'Nov.', 'canity' ),
			12 => __( 'Dec.', 'canity' ),
		];

		return $months[ $index ] ?? '';
	}

	private static function german_month_full_name( $index ) {
		$months = [
			1  => __( 'January', 'canity' ),
			2  => __( 'February', 'canity' ),
			3  => __( 'March', 'canity' ),
			4  => __( 'April', 'canity' ),
			5  => __( 'May', 'canity' ),
			6  => __( 'June', 'canity' ),
			7  => __( 'July', 'canity' ),
			8  => __( 'August', 'canity' ),
			9  => __( 'September', 'canity' ),
			10 => __( 'October', 'canity' ),
			11 => __( 'November', 'canity' ),
			12 => __( 'December', 'canity' ),
		];

		return $months[ $index ] ?? '';
	}
}
