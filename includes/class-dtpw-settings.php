<?php
/**
 * Plugin settings storage and sanitization.
 *
 * @package DemfilzWholesaleTierPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads, sanitizes and saves the dtpw_settings option.
 */
class DTPW_Settings {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION = 'dtpw_settings';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'sale_mode'     => 'apply',
			'only_if_lower' => false,
			'show_original' => true,
			'badge_enabled' => false,
			'badge_text'    => '{tier} price',
			'rounding'      => 'none',
			'role_map'      => array(),
		);
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	/**
	 * One setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitize a raw (already unslashed) settings array and persist it.
	 *
	 * @param array $raw Raw input.
	 * @return array The sanitized settings that were saved.
	 */
	public static function save( $raw ) {
		$clean = self::defaults();

		$clean['sale_mode']     = ( isset( $raw['sale_mode'] ) && 'skip' === $raw['sale_mode'] ) ? 'skip' : 'apply';
		$clean['only_if_lower'] = ! empty( $raw['only_if_lower'] );
		$clean['show_original'] = ! empty( $raw['show_original'] );
		$clean['badge_enabled'] = ! empty( $raw['badge_enabled'] );

		if ( isset( $raw['badge_text'] ) ) {
			$badge = sanitize_text_field( $raw['badge_text'] );
			if ( '' !== $badge ) {
				$clean['badge_text'] = $badge;
			}
		}

		$rounding = isset( $raw['rounding'] ) ? (string) $raw['rounding'] : 'none';
		if ( in_array( $rounding, array( 'none', 'whole', 'end95', 'end99' ), true ) ) {
			$clean['rounding'] = $rounding;
		}

		$clean['role_map'] = array();
		if ( isset( $raw['role_map'] ) && is_array( $raw['role_map'] ) ) {
			$tiers = DTPW_Tiers::all();
			foreach ( $raw['role_map'] as $role => $tier_id ) {
				$role    = sanitize_key( $role );
				$tier_id = sanitize_key( $tier_id );
				if ( '' !== $role && '' !== $tier_id && isset( $tiers[ $tier_id ] ) ) {
					$clean['role_map'][ $role ] = $tier_id;
				}
			}
		}

		update_option( self::OPTION, $clean );
		return $clean;
	}
}
