<?php
/**
 * Customer to tier resolution and assignment helpers.
 *
 * @package DemfilzWholesaleTierPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Resolves which tier applies to a user and manages assignments.
 */
class DTPW_Customers {

	/**
	 * User meta key holding the assigned tier id.
	 *
	 * @var string
	 */
	const META = '_dtpw_tier';

	/**
	 * Per-request cache of resolved tiers, keyed by user id.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Reset the per-request cache (used after assignments change and in tests).
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = array();
	}

	/**
	 * The tier id directly assigned to a user ('' if none).
	 *
	 * @param int $user_id User id.
	 * @return string
	 */
	public static function assigned_tier_id( $user_id ) {
		$id = get_user_meta( $user_id, self::META, true );
		return is_string( $id ) ? $id : '';
	}

	/**
	 * Resolve the ACTIVE tier that applies to a user, or null.
	 *
	 * Direct assignment wins; otherwise the first of the user's roles that
	 * appears in the settings role map. Inactive or deleted tiers resolve
	 * to null.
	 *
	 * @param int $user_id User id.
	 * @return array|null
	 */
	public static function tier_for_user( $user_id ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return null;
		}
		if ( array_key_exists( $user_id, self::$cache ) ) {
			return self::$cache[ $user_id ];
		}

		$tier = null;

		$direct = self::assigned_tier_id( $user_id );
		if ( '' !== $direct ) {
			$tier = DTPW_Tiers::get_active( $direct );
		}

		if ( null === $tier ) {
			$role_map = DTPW_Settings::get( 'role_map' );
			if ( is_array( $role_map ) && ! empty( $role_map ) ) {
				$user = get_userdata( $user_id );
				if ( $user && is_array( $user->roles ) ) {
					foreach ( $user->roles as $role ) {
						if ( isset( $role_map[ $role ] ) ) {
							$tier = DTPW_Tiers::get_active( $role_map[ $role ] );
							if ( null !== $tier ) {
								break;
							}
						}
					}
				}
			}
		}

		self::$cache[ $user_id ] = $tier;
		return $tier;
	}

	/**
	 * Tier for the currently logged-in user, or null.
	 *
	 * @return array|null
	 */
	public static function current_tier() {
		if ( ! is_user_logged_in() ) {
			return null;
		}
		return self::tier_for_user( get_current_user_id() );
	}

	/**
	 * Assign a tier to a user.
	 *
	 * @param int    $user_id User id.
	 * @param string $tier_id Tier id (must exist).
	 * @return bool True on success.
	 */
	public static function assign( $user_id, $tier_id ) {
		$user_id = (int) $user_id;
		$tier_id = sanitize_key( $tier_id );
		if ( $user_id <= 0 || null === DTPW_Tiers::get( $tier_id ) ) {
			return false;
		}
		update_user_meta( $user_id, self::META, $tier_id );
		self::flush_cache();
		return true;
	}

	/**
	 * Remove a user's direct tier assignment.
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public static function unassign( $user_id ) {
		delete_user_meta( (int) $user_id, self::META );
		self::flush_cache();
	}
}
