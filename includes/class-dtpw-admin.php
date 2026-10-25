<?php
/**
 * Admin menu, pages and form handlers.
 *
 * @package DemfilzWholesaleTierPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * The Tier Pricing admin area: Tiers, Customers and Settings pages.
 */
class DTPW_Admin {

	/**
	 * Admin page hook suffixes captured from add_menu_page/add_submenu_page,
	 * so asset enqueueing works on localized sites too.
	 *
	 * @var array
	 */
	private $page_hooks = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_filter( 'plugin_action_links_' . DTPW_BASENAME, array( $this, 'action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_dtpw_save_tier', array( $this, 'handle_save_tier' ) );
		add_action( 'admin_post_dtpw_delete_tier', array( $this, 'handle_delete_tier' ) );
		add_action( 'admin_post_dtpw_assign', array( $this, 'handle_assign' ) );
		add_action( 'admin_post_dtpw_unassign', array( $this, 'handle_unassign' ) );
		add_action( 'admin_post_dtpw_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/**
	 * Register the Tier Pricing menu and submenus.
	 *
	 * @return void
	 */
	public function menu() {
		$this->page_hooks['tiers'] = add_menu_page(
			__( 'Tier Pricing', 'demfilz-wholesale-tier-pricing-for-woocommerce' ),
			__( 'Tier Pricing', 'demfilz-wholesale-tier-pricing-for-woocommerce' ),
			'manage_woocommerce',
			'dtpw-tiers',
			array( $this, 'page_tiers' ),
			'dashicons-tag',
			56
		);
		add_submenu_page(
			'dtpw-tiers',
			__( 'Tiers', 'demfilz-wholesale-tier-pricing-for-woocommerce' ),
			__( 'Tiers', 'demfilz-wholesale-tier-pricing-for-woocommerce' ),
			'manage_woocommerce',
			'dtpw-tiers',
			array( $this, 'page_tiers' )
		);
		$this->page_hooks['customers'] = add_submenu_page(
			'dtpw-tiers',
			__( 'Customers', 'demfilz-wholesale-tier-pricing-for-woocommerce' ),
			__( 'Customers', 'demfilz-wholesale-tier-pricing-for-woocommerce' ),
			'manage_woocommerce',
			'dtpw-customers',
			array( $this, 'page_customers' )
		);
		$this->page_hooks['settings']  = add_submenu_page(
			'dtpw-tiers',
			__( 'Settings', 'demfilz-wholesale-tier-pricing-for-woocommerce' ),
			__( 'Settings', 'demfilz-wholesale-tier-pricing-for-woocommerce' ),
			'manage_woocommerce',
			'dtpw-settings',
			array( $this, 'page_settings' )
		);
	}

	/**
	 * Add quick links on the Plugins list row.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function action_links( $links ) {
		$extra = array(
			'<a href="' . esc_url( add_query_arg( 'page', 'dtpw-tiers', admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Tiers', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</a>',
			'<a href="' . esc_url( add_query_arg( 'page', 'dtpw-settings', admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Settings', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</a>',
		);
		return array_merge( $extra, $links );
	}

	/**
	 * Enqueue admin assets on our pages and the product editor.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		$is_ours    = in_array( $hook, $this->page_hooks, true );
		$is_product = in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'product' === get_post_type();

		if ( ! $is_ours && ! $is_product ) {
			return;
		}

		wp_enqueue_style( 'dtpw-admin', DTPW_URL . 'admin/css/admin.css', array(), DTPW_VERSION );
		wp_enqueue_script( 'dtpw-admin', DTPW_URL . 'admin/js/admin.js', array( 'jquery' ), DTPW_VERSION, true );

		if ( isset( $this->page_hooks['customers'] ) && $this->page_hooks['customers'] === $hook ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
		}
	}

	/**
	 * Tiers page.
	 *
	 * @return void
	 */
	public function page_tiers() {
		$tiers   = DTPW_Tiers::all();
		$editing = null;
		if ( isset( $_GET['edit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page state.
			$editing = DTPW_Tiers::get( sanitize_key( wp_unslash( $_GET['edit'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page state.
		}

		// After a failed save, re-fill the form with what was submitted.
		$prefill = null;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only form re-fill after a redirect.
		if ( isset( $_GET['dtpw_notice'], $_GET['dtpw_name'] ) && 'tier_error' === sanitize_key( wp_unslash( $_GET['dtpw_notice'] ) ) ) {
			$prefill_type = isset( $_GET['dtpw_type'] ) ? sanitize_key( wp_unslash( $_GET['dtpw_type'] ) ) : 'percent';
			if ( ! in_array( $prefill_type, array( 'percent', 'fixed', 'price' ), true ) ) {
				$prefill_type = 'percent';
			}
			$prefill = array(
				'name'   => sanitize_text_field( wp_unslash( $_GET['dtpw_name'] ) ),
				'type'   => $prefill_type,
				'amount' => isset( $_GET['dtpw_amount'] ) ? sanitize_text_field( wp_unslash( $_GET['dtpw_amount'] ) ) : '',
				'active' => ! empty( $_GET['dtpw_active'] ),
			);
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		require DTPW_PATH . 'admin/views/tiers.php';
	}

	/**
	 * Customers page.
	 *
	 * @return void
	 */
	public function page_customers() {
		$tiers = DTPW_Tiers::all();

		$paged = 1;
		if ( isset( $_GET['paged'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
			$paged = max( 1, absint( wp_unslash( $_GET['paged'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination.
		}
		$per_page       = 20;
		$query          = new WP_User_Query(
			array(
				'meta_key'     => DTPW_Customers::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- admin assignments table.
				'meta_compare' => 'EXISTS',
				'number'       => $per_page,
				'offset'       => ( $paged - 1 ) * $per_page,
				'orderby'      => 'display_name',
				'order'        => 'ASC',
				'count_total'  => true,
			)
		);
		$assigned_users = $query->get_results();
		$total          = (int) $query->get_total();
		$total_pages    = (int) ceil( $total / $per_page );

		require DTPW_PATH . 'admin/views/customers.php';
	}

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	public function page_settings() {
		$settings = DTPW_Settings::all();
		$tiers    = DTPW_Tiers::all();
		$roles    = wp_roles()->roles;
		require DTPW_PATH . 'admin/views/settings.php';
	}

	/**
	 * Create or update a tier.
	 *
	 * @return void
	 */
	public function handle_save_tier() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage pricing tiers.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) );
		}
		check_admin_referer( 'dtpw_save_tier' );

		$raw = array(
			'id'     => isset( $_POST['tier_id'] ) ? sanitize_key( wp_unslash( $_POST['tier_id'] ) ) : '',
			'name'   => isset( $_POST['tier_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tier_name'] ) ) : '',
			'type'   => isset( $_POST['tier_type'] ) ? sanitize_key( wp_unslash( $_POST['tier_type'] ) ) : '',
			'amount' => isset( $_POST['tier_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['tier_amount'] ) ) : '',
			'active' => ! empty( $_POST['tier_active'] ),
		);

		$tier = DTPW_Tiers::sanitize( $raw );
		if ( is_wp_error( $tier ) ) {
			$args = array(
				'page'        => 'dtpw-tiers',
				'dtpw_notice' => 'tier_error',
				'dtpw_msg'    => rawurlencode( $tier->get_error_message() ),
				'dtpw_name'   => rawurlencode( $raw['name'] ),
				'dtpw_type'   => $raw['type'],
				'dtpw_amount' => rawurlencode( $raw['amount'] ),
				'dtpw_active' => $raw['active'] ? '1' : '0',
			);
			if ( '' !== $raw['id'] ) {
				$args['edit'] = $raw['id'];
			}
			wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
			exit;
		}

		DTPW_Tiers::save( $tier );
		DTPW_Customers::flush_cache();

		$notice = ( 'price' !== $tier['type'] && $tier['amount'] > 0 ) ? 'tier_saved_surcharge' : 'tier_saved';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'dtpw-tiers',
					'dtpw_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Delete a tier.
	 *
	 * @return void
	 */
	public function handle_delete_tier() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage pricing tiers.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) );
		}
		check_admin_referer( 'dtpw_delete_tier' );

		$tier_id = isset( $_GET['tier'] ) ? sanitize_key( wp_unslash( $_GET['tier'] ) ) : '';
		if ( '' !== $tier_id ) {
			DTPW_Tiers::delete( $tier_id );
			DTPW_Customers::flush_cache();
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'dtpw-tiers',
					'dtpw_notice' => 'tier_deleted',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Assign a tier to one or more customers (assign panel and per-row change).
	 *
	 * @return void
	 */
	public function handle_assign() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to assign pricing tiers.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) );
		}
		check_admin_referer( 'dtpw_assign' );

		$tier_id = isset( $_POST['tier_id'] ) ? sanitize_key( wp_unslash( $_POST['tier_id'] ) ) : '';

		$user_ids = array();
		if ( isset( $_POST['customer_ids'] ) ) {
			$user_ids = array_map( 'absint', (array) wp_unslash( $_POST['customer_ids'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via absint map.
		}
		$user_ids = array_filter( $user_ids );

		$notice = 'assign_error';
		$done   = 0;
		if ( '' !== $tier_id && ! empty( $user_ids ) ) {
			foreach ( $user_ids as $user_id ) {
				if ( ! current_user_can( 'edit_user', $user_id ) ) {
					continue;
				}
				if ( DTPW_Customers::assign( $user_id, $tier_id ) ) {
					++$done;
				}
			}
			if ( $done > 0 ) {
				$notice = 'assigned';
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'dtpw-customers',
					'dtpw_notice' => $notice,
					'dtpw_count'  => $done,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Remove a customer's tier assignment.
	 *
	 * @return void
	 */
	public function handle_unassign() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to assign pricing tiers.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) );
		}
		check_admin_referer( 'dtpw_unassign' );

		$user_id = isset( $_GET['user'] ) ? absint( wp_unslash( $_GET['user'] ) ) : 0;
		if ( $user_id > 0 && current_user_can( 'edit_user', $user_id ) ) {
			DTPW_Customers::unassign( $user_id );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'dtpw-customers',
					'dtpw_notice' => 'unassigned',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) );
		}
		check_admin_referer( 'dtpw_save_settings' );

		$raw = array(
			'sale_mode'     => isset( $_POST['sale_mode'] ) ? sanitize_key( wp_unslash( $_POST['sale_mode'] ) ) : 'apply',
			'only_if_lower' => ! empty( $_POST['only_if_lower'] ),
			'show_original' => ! empty( $_POST['show_original'] ),
			'badge_enabled' => ! empty( $_POST['badge_enabled'] ),
			'badge_text'    => isset( $_POST['badge_text'] ) ? sanitize_text_field( wp_unslash( $_POST['badge_text'] ) ) : '',
			'rounding'      => isset( $_POST['rounding'] ) ? sanitize_key( wp_unslash( $_POST['rounding'] ) ) : 'none',
			'role_map'      => array(),
		);
		if ( isset( $_POST['role_map'] ) && is_array( $_POST['role_map'] ) ) {
			$raw['role_map'] = map_deep( wp_unslash( $_POST['role_map'] ), 'sanitize_key' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via map_deep.
		}

		DTPW_Settings::save( $raw );
		DTPW_Customers::flush_cache();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'dtpw-settings',
					'dtpw_notice' => 'settings_saved',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Admin notices for our redirect results. Shown only on this plugin's
	 * own screens, never elsewhere in the dashboard.
	 *
	 * @return void
	 */
	public function notices() {
		if ( ! in_array( get_current_screen() ? get_current_screen()->id : '', $this->page_hooks, true ) ) {
			return;
		}
		if ( ! isset( $_GET['dtpw_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice display only.
			return;
		}
		$notice = sanitize_key( wp_unslash( $_GET['dtpw_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice display only.

		$messages = array(
			'tier_saved'           => array( 'success', __( 'Tier saved.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) ),
			'tier_saved_surcharge' => array( 'warning', __( 'Tier saved. Note: a positive amount is a surcharge that increases prices. Use a negative amount for a discount.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) ),
			'tier_deleted'         => array( 'success', __( 'Tier deleted. Its customers now see standard prices (or their role default from Settings).', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) ),
			'unassigned'           => array( 'success', __( 'Assignment removed.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) ),
			'settings_saved'       => array( 'success', __( 'Settings saved.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) ),
			'assign_error'         => array( 'error', __( 'Pick at least one customer and a tier.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) ),
		);

		if ( 'tier_error' === $notice ) {
			$msg = '';
			if ( isset( $_GET['dtpw_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice display only.
				$msg = sanitize_text_field( wp_unslash( $_GET['dtpw_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice display only.
			}
			if ( '' === $msg ) {
				$msg = __( 'The tier could not be saved.', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
			}
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
			return;
		}

		if ( 'assigned' === $notice ) {
			$count = 1;
			if ( isset( $_GET['dtpw_count'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice display only.
				$count = max( 1, absint( wp_unslash( $_GET['dtpw_count'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- notice display only.
			}
			echo '<div class="notice notice-success is-dismissible"><p>';
			/* translators: %d: number of customers. */
			echo esc_html( sprintf( _n( 'Tier assigned to %d customer.', 'Tier assigned to %d customers.', $count, 'demfilz-wholesale-tier-pricing-for-woocommerce' ), $count ) );
			echo '</p></div>';
			return;
		}

		if ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $messages[ $notice ][0] ) . ' is-dismissible"><p>' . esc_html( $messages[ $notice ][1] ) . '</p></div>';
		}
	}
}
