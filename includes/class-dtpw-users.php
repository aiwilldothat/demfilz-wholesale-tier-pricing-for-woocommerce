<?php
/**
 * Users screen and profile integration.
 *
 * @package DemfilzWholesaleTierPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tier column, filter and bulk actions on the Users list, plus the tier
 * field on user profiles.
 */
class DTPW_Users {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'show_user_profile', array( $this, 'profile_field' ) );
		add_action( 'edit_user_profile', array( $this, 'profile_field' ) );
		add_action( 'personal_options_update', array( $this, 'profile_save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'profile_save' ) );

		add_filter( 'manage_users_columns', array( $this, 'users_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'users_column_value' ), 10, 3 );
		add_action( 'restrict_manage_users', array( $this, 'users_filter' ) );
		add_action( 'pre_get_users', array( $this, 'apply_users_filter' ) );

		add_filter( 'bulk_actions-users', array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_notice' ) );
	}

	/**
	 * Tier dropdown on the user profile screen (admins only).
	 *
	 * @param WP_User $user User being edited.
	 * @return void
	 */
	public function profile_field( $user ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tiers    = DTPW_Tiers::all();
		$assigned = DTPW_Customers::assigned_tier_id( $user->ID );

		echo '<h2>' . esc_html__( 'Tier pricing', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tr>';
		echo '<th><label for="dtpw_tier">' . esc_html__( 'Pricing tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</label></th>';
		echo '<td><select name="dtpw_tier" id="dtpw_tier">';
		echo '<option value="">' . esc_html__( 'No tier (standard prices)', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</option>';
		foreach ( $tiers as $tier ) {
			echo '<option value="' . esc_attr( $tier['id'] ) . '"' . selected( $assigned, $tier['id'], false ) . '>';
			echo esc_html( $tier['name'] );
			if ( empty( $tier['active'] ) ) {
				echo ' ' . esc_html__( '(inactive)', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
			}
			echo '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'The customer sees this tier\'s prices while logged in. A direct assignment beats any role mapping; with no tier selected here, a role default mapped in Tier Pricing Settings still applies.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</p>';
		echo '</td></tr></table>';
	}

	/**
	 * Save the profile tier dropdown.
	 *
	 * @param int $user_id User being saved.
	 * @return void
	 */
	public function profile_save( $user_id ) {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}
		check_admin_referer( 'update-user_' . $user_id );

		if ( ! isset( $_POST['dtpw_tier'] ) ) {
			return;
		}
		$tier_id = sanitize_key( wp_unslash( $_POST['dtpw_tier'] ) );
		if ( '' === $tier_id ) {
			DTPW_Customers::unassign( $user_id );
		} else {
			DTPW_Customers::assign( $user_id, $tier_id );
		}
	}

	/**
	 * Add the Pricing tier column.
	 *
	 * @param array $columns Users list columns.
	 * @return array
	 */
	public function users_column( $columns ) {
		$columns['dtpw_tier'] = __( 'Pricing tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
		return $columns;
	}

	/**
	 * Render the Pricing tier column value.
	 *
	 * @param string $output    Current output.
	 * @param string $column    Column key.
	 * @param int    $user_id   User id.
	 * @return string
	 */
	public function users_column_value( $output, $column, $user_id ) {
		if ( 'dtpw_tier' !== $column ) {
			return $output;
		}
		$assigned = DTPW_Customers::assigned_tier_id( $user_id );
		if ( '' === $assigned ) {
			// No direct assignment: show a role-mapped tier if one covers this user.
			$via_role = DTPW_Customers::tier_for_user( $user_id );
			if ( null !== $via_role ) {
				return esc_html( $via_role['name'] ) . ' <span class="dtpw-muted">' . esc_html__( '(via role)', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</span>';
			}
			return '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__( 'No pricing tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</span>';
		}
		$tier = DTPW_Tiers::get( $assigned );
		if ( null === $tier ) {
			return esc_html__( 'Missing tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
		}
		$label = esc_html( $tier['name'] );
		if ( empty( $tier['active'] ) ) {
			$label .= ' <span class="dtpw-muted">' . esc_html__( '(inactive)', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</span>';
		}
		return $label;
	}

	/**
	 * Filter-by-tier dropdown above the Users list.
	 *
	 * @param string $which Top or bottom table nav.
	 * @return void
	 */
	public function users_filter( $which ) {
		if ( 'top' !== $which || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tiers = DTPW_Tiers::all();
		if ( empty( $tiers ) ) {
			return;
		}
		$current = '';
		if ( isset( $_GET['dtpw_tier_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
			$current = sanitize_key( wp_unslash( $_GET['dtpw_tier_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		}
		echo '<label class="screen-reader-text" for="dtpw_tier_filter">' . esc_html__( 'Filter by pricing tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</label>';
		echo '<select name="dtpw_tier_filter" id="dtpw_tier_filter">';
		echo '<option value="">' . esc_html__( 'All pricing tiers', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</option>';
		foreach ( $tiers as $tier ) {
			echo '<option value="' . esc_attr( $tier['id'] ) . '"' . selected( $current, $tier['id'], false ) . '>' . esc_html( $tier['name'] ) . '</option>';
		}
		echo '</select> ';
		submit_button( __( 'Filter', 'demfilz-wholesale-tier-pricing-for-woocommerce' ), '', 'dtpw_filter_action', false );
	}

	/**
	 * Apply the tier filter to the Users query.
	 *
	 * @param WP_User_Query $query Users query.
	 * @return void
	 */
	public function apply_users_filter( $query ) {
		global $pagenow;
		if ( ! is_admin() || 'users.php' !== $pagenow ) {
			return;
		}
		if ( ! isset( $_GET['dtpw_tier_filter'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
			return;
		}
		$tier_id = sanitize_key( wp_unslash( $_GET['dtpw_tier_filter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		if ( '' === $tier_id ) {
			return;
		}
		$query->set( 'meta_key', DTPW_Customers::META );
		$query->set( 'meta_value', $tier_id );
	}

	/**
	 * Add assign/remove bulk actions.
	 *
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public function bulk_actions( $actions ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $actions;
		}
		foreach ( DTPW_Tiers::all() as $tier ) {
			$label = $tier['name'];
			if ( empty( $tier['active'] ) ) {
				$label .= ' ' . __( '(inactive)', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
			}
			/* translators: %s: tier name. */
			$actions[ 'dtpw_assign_' . $tier['id'] ] = sprintf( __( 'Assign pricing tier: %s', 'demfilz-wholesale-tier-pricing-for-woocommerce' ), $label );
		}
		$actions['dtpw_remove_tier'] = __( 'Remove pricing tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
		return $actions;
	}

	/**
	 * Handle the bulk actions (WordPress verifies the bulk-users nonce
	 * before this filter runs).
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action key.
	 * @param array  $user_ids Selected user ids.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect, $action, $user_ids ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $redirect;
		}

		$count = 0;
		if ( 'dtpw_remove_tier' === $action ) {
			foreach ( $user_ids as $user_id ) {
				if ( ! current_user_can( 'edit_user', (int) $user_id ) ) {
					continue;
				}
				DTPW_Customers::unassign( (int) $user_id );
				++$count;
			}
			return add_query_arg( 'dtpw_bulk_removed', $count, $redirect );
		}

		if ( 0 === strpos( $action, 'dtpw_assign_' ) ) {
			$tier_id = sanitize_key( substr( $action, strlen( 'dtpw_assign_' ) ) );
			foreach ( $user_ids as $user_id ) {
				if ( ! current_user_can( 'edit_user', (int) $user_id ) ) {
					continue;
				}
				if ( DTPW_Customers::assign( (int) $user_id, $tier_id ) ) {
					++$count;
				}
			}
			return add_query_arg( 'dtpw_bulk_assigned', $count, $redirect );
		}

		return $redirect;
	}

	/**
	 * Success notice after a bulk assign/remove. Shown only on the Users
	 * screen, never elsewhere in the dashboard.
	 *
	 * @return void
	 */
	public function bulk_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'users' !== $screen->id ) {
			return;
		}
		if ( isset( $_GET['dtpw_bulk_assigned'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice display only.
			$count = absint( wp_unslash( $_GET['dtpw_bulk_assigned'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice display only.
			echo '<div class="notice notice-success is-dismissible"><p>';
			/* translators: %d: number of users. */
			echo esc_html( sprintf( _n( 'Pricing tier assigned to %d user.', 'Pricing tier assigned to %d users.', $count, 'demfilz-wholesale-tier-pricing-for-woocommerce' ), $count ) );
			echo '</p></div>';
		}
		if ( isset( $_GET['dtpw_bulk_removed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice display only.
			$count = absint( wp_unslash( $_GET['dtpw_bulk_removed'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice display only.
			echo '<div class="notice notice-success is-dismissible"><p>';
			/* translators: %d: number of users. */
			echo esc_html( sprintf( _n( 'Pricing tier removed from %d user.', 'Pricing tier removed from %d users.', $count, 'demfilz-wholesale-tier-pricing-for-woocommerce' ), $count ) );
			echo '</p></div>';
		}
	}
}
