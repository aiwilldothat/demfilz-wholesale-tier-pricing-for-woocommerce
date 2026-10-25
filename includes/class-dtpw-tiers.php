<?php
/**
 * Tier storage, sanitization and helpers.
 *
 * @package DemfilzWholesaleTierPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD for pricing tiers stored in the dtpw_tiers option.
 *
 * A tier is: array(
 *   'id'     => string slug,
 *   'name'   => string,
 *   'type'   => 'percent'|'fixed'|'price',
 *   'amount' => float (signed for percent/fixed; unused/0 for price - a
 *               price-kind tier gets its exact prices per product via the
 *               product Tier pricing panel),
 *   'active' => bool,
 * ).
 */
class DTPW_Tiers {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION = 'dtpw_tiers';

	/**
	 * All tiers keyed by id.
	 *
	 * @return array
	 */
	public static function all() {
		$tiers = get_option( self::OPTION, array() );
		return is_array( $tiers ) ? $tiers : array();
	}

	/**
	 * One tier by id.
	 *
	 * @param string $id Tier id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$tiers = self::all();
		return isset( $tiers[ $id ] ) ? $tiers[ $id ] : null;
	}

	/**
	 * One tier by id, only if it is active.
	 *
	 * @param string $id Tier id.
	 * @return array|null
	 */
	public static function get_active( $id ) {
		$tier = self::get( $id );
		if ( $tier && ! empty( $tier['active'] ) ) {
			return $tier;
		}
		return null;
	}

	/**
	 * Sanitize a raw (already unslashed) tier submission.
	 *
	 * @param array $raw Raw input (name, type, amount, active, id for edits).
	 * @return array|WP_Error Sanitized tier or error.
	 */
	public static function sanitize( $raw ) {
		$name = isset( $raw['name'] ) ? sanitize_text_field( $raw['name'] ) : '';
		if ( '' === $name ) {
			return new WP_Error( 'dtpw_no_name', __( 'The tier needs a name.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) );
		}

		$type = isset( $raw['type'] ) ? (string) $raw['type'] : '';
		if ( ! in_array( $type, array( 'percent', 'fixed', 'price' ), true ) ) {
			return new WP_Error( 'dtpw_bad_type', __( 'Choose a valid adjustment kind.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) );
		}

		// A price-kind tier has no storewide amount: its exact prices are set
		// per product on the product Tier pricing panel.
		if ( 'price' === $type ) {
			$amount = 0.0;
		} else {
			$amount_raw = isset( $raw['amount'] ) ? trim( (string) $raw['amount'] ) : '';
			if ( '' === $amount_raw || ! is_numeric( $amount_raw ) ) {
				return new WP_Error( 'dtpw_bad_amount', __( 'Enter a numeric amount.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) );
			}
			$amount = (float) $amount_raw;
		}

		$id = isset( $raw['id'] ) ? sanitize_key( $raw['id'] ) : '';
		if ( '' === $id ) {
			$id = self::unique_id( $name );
		}

		return array(
			'id'     => $id,
			'name'   => $name,
			'type'   => $type,
			'amount' => $amount,
			'active' => ! empty( $raw['active'] ),
		);
	}

	/**
	 * Insert or update a sanitized tier.
	 *
	 * @param array $tier Sanitized tier (from self::sanitize()).
	 * @return void
	 */
	public static function save( $tier ) {
		$tiers                = self::all();
		$tiers[ $tier['id'] ] = $tier;
		update_option( self::OPTION, $tiers );
	}

	/**
	 * Delete a tier and unassign every user that carried it.
	 *
	 * @param string $id Tier id.
	 * @return int Number of users that were unassigned.
	 */
	public static function delete( $id ) {
		$tiers = self::all();
		if ( ! isset( $tiers[ $id ] ) ) {
			return 0;
		}
		unset( $tiers[ $id ] );
		update_option( self::OPTION, $tiers );

		// Drop the tier from any role mapping so Settings shows the truth.
		$settings = DTPW_Settings::all();
		if ( ! empty( $settings['role_map'] ) && in_array( $id, $settings['role_map'], true ) ) {
			$settings['role_map'] = array_filter(
				$settings['role_map'],
				function ( $mapped ) use ( $id ) {
					return $mapped !== $id;
				}
			);
			update_option( DTPW_Settings::OPTION, $settings );
		}

		$user_ids = get_users(
			array(
				'meta_key'   => '_dtpw_tier', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin-side cleanup, small result set.
				'meta_value' => $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- admin-side cleanup, small result set.
				'fields'     => 'ID',
				'number'     => -1,
			)
		);
		foreach ( $user_ids as $user_id ) {
			delete_user_meta( $user_id, '_dtpw_tier' );
		}
		return count( $user_ids );
	}

	/**
	 * Number of users assigned to a tier.
	 *
	 * @param string $id Tier id.
	 * @return int
	 */
	public static function count_users( $id ) {
		$query = new WP_User_Query(
			array(
				'meta_key'    => '_dtpw_tier', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin screen count only.
				'meta_value'  => $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- admin screen count only.
				'count_total' => true,
				'number'      => 1,
				'fields'      => 'ID',
			)
		);
		return (int) $query->get_total();
	}

	/**
	 * Plain-English summary of a tier's adjustment, e.g. "10% off" or
	 * "Exactly $20.00". Contains wc_price() markup; escape with wp_kses_post().
	 *
	 * @param array $tier Tier.
	 * @return string HTML summary.
	 */
	public static function summary( $tier ) {
		$amount = (float) $tier['amount'];
		switch ( $tier['type'] ) {
			case 'percent':
				if ( $amount < 0 ) {
					/* translators: %s: percentage number. */
					return sprintf( __( '%s%% off', 'demfilz-wholesale-tier-pricing-for-woocommerce' ), self::trim_num( abs( $amount ) ) );
				}
				/* translators: %s: percentage number. */
				return sprintf( __( '%s%% more', 'demfilz-wholesale-tier-pricing-for-woocommerce' ), self::trim_num( $amount ) );
			case 'fixed':
				if ( $amount < 0 ) {
					/* translators: %s: formatted price amount. */
					return sprintf( __( '%s off', 'demfilz-wholesale-tier-pricing-for-woocommerce' ), wc_price( abs( $amount ) ) );
				}
				/* translators: %s: formatted price amount. */
				return sprintf( __( '%s more', 'demfilz-wholesale-tier-pricing-for-woocommerce' ), wc_price( $amount ) );
			case 'price':
				return __( 'Specific prices per product', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
		}
		return '';
	}

	/**
	 * Trim trailing zeros from a number for display (10.0 -> 10, 12.5 -> 12.5).
	 *
	 * @param float $num Number.
	 * @return string
	 */
	public static function trim_num( $num ) {
		$str = rtrim( rtrim( number_format( (float) $num, 2, '.', '' ), '0' ), '.' );
		return '' === $str ? '0' : $str;
	}

	/**
	 * Build a unique tier id from a name.
	 *
	 * @param string $name Tier name.
	 * @return string
	 */
	private static function unique_id( $name ) {
		$base = sanitize_key( str_replace( ' ', '-', strtolower( $name ) ) );
		if ( '' === $base ) {
			$base = 'tier';
		}
		$tiers = self::all();
		$id    = $base;
		$n     = 2;
		while ( isset( $tiers[ $id ] ) ) {
			$id = $base . '-' . $n;
			++$n;
		}
		return $id;
	}
}
