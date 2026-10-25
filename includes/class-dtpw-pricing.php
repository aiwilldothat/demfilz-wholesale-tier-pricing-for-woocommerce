<?php
/**
 * The frontend pricing engine.
 *
 * @package DemfilzWholesaleTierPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Applies the current customer's tier to product prices on the frontend.
 */
class DTPW_Pricing {

	/**
	 * Guard flag: when true the price filters pass values through untouched
	 * (used to read the unadjusted price for strikethrough display).
	 *
	 * @var bool
	 */
	private $suspended = false;

	/**
	 * Admin-ajax actions that edit orders in wp-admin and must never be
	 * priced with the acting admin's own tier.
	 *
	 * @var array
	 */
	private $blocked_ajax_actions = array(
		'woocommerce_calc_line_taxes',
		'woocommerce_add_order_item',
		'woocommerce_save_order_items',
		'woocommerce_remove_order_item',
		'woocommerce_add_order_fee',
		'woocommerce_add_order_shipping',
		'woocommerce_add_order_tax',
		'woocommerce_add_coupon_discount',
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'woocommerce_product_get_price', array( $this, 'filter_price' ), 99, 2 );
		add_filter( 'woocommerce_product_variation_get_price', array( $this, 'filter_price' ), 99, 2 );
		add_filter( 'woocommerce_variation_prices_price', array( $this, 'filter_variation_price' ), 99, 3 );
		add_filter( 'woocommerce_get_variation_prices_hash', array( $this, 'variation_prices_hash' ), 99, 3 );
		add_filter( 'woocommerce_get_price_html', array( $this, 'price_html' ), 20, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'snapshot_order_tier' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'snapshot_order_tier' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'show_order_tier' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_style' ) );
	}

	/**
	 * Whether tier pricing applies to the current request/user.
	 *
	 * @return bool
	 */
	public function should_apply() {
		if ( $this->suspended ) {
			return false;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return false;
		}

		if ( wp_doing_ajax() && isset( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context guard, no state change here.
			$action = sanitize_key( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context guard, no state change here.
			if ( in_array( $action, $this->blocked_ajax_actions, true ) ) {
				return false;
			}
		}

		$tier = DTPW_Customers::current_tier();

		/**
		 * Allow sites to veto or force tier application for this request.
		 *
		 * @param bool       $apply Whether the tier will be applied.
		 * @param array|null $tier  The resolved tier, if any.
		 */
		return (bool) apply_filters( 'dtpw_should_apply', null !== $tier, $tier );
	}

	/**
	 * Filter a simple/variation product's active price.
	 *
	 * @param mixed      $price   Price as passed by WooCommerce (may be '').
	 * @param WC_Product $product Product object.
	 * @return mixed
	 */
	public function filter_price( $price, $product ) {
		if ( '' === $price || null === $price || ! $this->should_apply() ) {
			return $price;
		}
		$tier = DTPW_Customers::current_tier();
		if ( null === $tier ) {
			return $price;
		}
		return $this->adjust( (float) $price, $product, $tier, DTPW_Settings::all() );
	}

	/**
	 * Filter one variation's price inside the cached variable-price ranges.
	 *
	 * @param mixed      $price     Variation price.
	 * @param WC_Product $variation Variation object.
	 * @param WC_Product $product   Parent variable product.
	 * @return mixed
	 */
	public function filter_variation_price( $price, $variation, $product ) {
		unset( $product );
		return $this->filter_price( $price, $variation );
	}

	/**
	 * Vary WooCommerce's cached variation price ranges by tier and settings.
	 *
	 * @param array      $hash        Hash input array.
	 * @param WC_Product $product     Product object.
	 * @param bool       $for_display Display context flag.
	 * @return array
	 */
	public function variation_prices_hash( $hash, $product, $for_display ) {
		unset( $product, $for_display );
		if ( ! $this->should_apply() ) {
			return $hash;
		}
		$tier = DTPW_Customers::current_tier();
		if ( null !== $tier ) {
			$hash['dtpw'] = md5( wp_json_encode( array( $tier, DTPW_Settings::all() ) ) );
		}
		return $hash;
	}

	/**
	 * Core adjustment math. Pure: no user/context reads, fully testable.
	 *
	 * @param float      $price    Base (active) price.
	 * @param WC_Product $product  Product or variation object.
	 * @param array      $tier     Resolved tier.
	 * @param array      $settings Plugin settings.
	 * @return float
	 */
	public function adjust( $price, $product, $tier, $settings ) {
		if ( $product->is_type( 'external' ) ) {
			return $price;
		}
		if ( 'skip' === $settings['sale_mode'] && $product->is_on_sale() ) {
			return $price;
		}

		$rule = $this->rule_for_product( $product, $tier );
		if ( null === $rule ) {
			return $price;
		}

		switch ( $rule['type'] ) {
			case 'percent':
				$adjusted = $price * ( 1 + ( (float) $rule['amount'] ) / 100 );
				break;
			case 'fixed':
				$adjusted = $price + (float) $rule['amount'];
				break;
			case 'price':
				$adjusted = (float) $rule['amount'];
				break;
			default:
				return $price;
		}

		$adjusted = $this->round_price( $adjusted, $settings['rounding'] );
		$adjusted = max( 0.0, $adjusted );

		if ( ! empty( $settings['only_if_lower'] ) && $adjusted > $price ) {
			return $price;
		}

		return $adjusted;
	}

	/**
	 * Resolve the rule that applies to a product for a tier: a per-product
	 * custom override beats the tier default; an exclude override returns
	 * null (no adjustment). A price-kind tier has no storewide default -
	 * only products given an exact tier price on the product panel change.
	 *
	 * @param WC_Product $product Product or variation object.
	 * @param array      $tier    Resolved tier.
	 * @return array|null Array with type + amount, or null for no adjustment.
	 */
	private function rule_for_product( $product, $tier ) {
		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		$overrides  = get_post_meta( $product_id, '_dtpw_overrides', true );

		if ( is_array( $overrides ) && isset( $overrides[ $tier['id'] ] ) ) {
			$row = $overrides[ $tier['id'] ];
			if ( isset( $row['mode'] ) && 'exclude' === $row['mode'] ) {
				return null;
			}
			if ( isset( $row['mode'], $row['type'], $row['amount'] ) && 'custom' === $row['mode'] ) {
				return array(
					'type'   => $row['type'],
					'amount' => (float) $row['amount'],
				);
			}
		}

		if ( 'price' === $tier['type'] ) {
			return null;
		}

		return array(
			'type'   => $tier['type'],
			'amount' => (float) $tier['amount'],
		);
	}

	/**
	 * Apply the configured rounding mode.
	 *
	 * @param float  $price Price.
	 * @param string $mode  none|whole|end95|end99.
	 * @return float
	 */
	public function round_price( $price, $mode ) {
		switch ( $mode ) {
			case 'whole':
				return (float) round( $price );
			case 'end95':
				return max( 0.0, (float) ceil( $price ) - 0.05 );
			case 'end99':
				return max( 0.0, (float) ceil( $price ) - 0.01 );
		}
		return $price;
	}

	/**
	 * The product's price with tier filters suspended (the store price).
	 *
	 * @param WC_Product $product Product object.
	 * @return string Raw price string as stored.
	 */
	public function unadjusted_price( $product ) {
		$this->suspended = true;
		$price           = $product->get_price();
		$this->suspended = false;
		return $price;
	}

	/**
	 * Optionally decorate the price HTML with the struck-through store price
	 * and/or the tier badge.
	 *
	 * @param string     $html    Price HTML from WooCommerce.
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public function price_html( $html, $product ) {
		if ( '' === $html || ! $this->should_apply() ) {
			return $html;
		}
		$tier = DTPW_Customers::current_tier();
		if ( null === $tier ) {
			return $html;
		}

		$settings = DTPW_Settings::all();
		if ( empty( $settings['show_original'] ) && empty( $settings['badge_enabled'] ) ) {
			return $html;
		}

		if ( ! empty( $settings['show_original'] ) && ! $product->is_type( 'variable' ) ) {
			$original = $this->unadjusted_price( $product );
			$adjusted = $product->get_price();
			if ( '' !== $original && '' !== $adjusted && abs( (float) $original - (float) $adjusted ) > 0.001 ) {
				$html = '<del aria-hidden="true">' . wc_price( (float) $original ) . '</del> <ins class="dtpw-price">' . wc_price( (float) $adjusted ) . '</ins>';
			}
		}

		if ( ! empty( $settings['badge_enabled'] ) ) {
			$badge = str_replace( '{tier}', $tier['name'], (string) $settings['badge_text'] );
			if ( '' !== trim( $badge ) ) {
				$html .= ' <span class="dtpw-badge">' . esc_html( $badge ) . '</span>';
			}
		}

		return $html;
	}

	/**
	 * Record which tier priced the order. Runs for both the classic checkout
	 * (woocommerce_checkout_create_order) and the block/Store API checkout
	 * (woocommerce_store_api_checkout_update_order_meta).
	 *
	 * @param WC_Order $order Order being created at checkout.
	 * @param array    $data  Posted checkout data (classic checkout only, unused).
	 * @return void
	 */
	public function snapshot_order_tier( $order, $data = array() ) {
		unset( $data );
		if ( ! $this->should_apply() ) {
			return;
		}
		$tier = DTPW_Customers::current_tier();
		if ( null !== $tier ) {
			$order->update_meta_data( '_dtpw_order_tier', $tier['name'] );
		}
	}

	/**
	 * Show the recorded tier on the admin order details panel.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function show_order_tier( $order ) {
		$tier_name = $order->get_meta( '_dtpw_order_tier' );
		if ( '' === $tier_name ) {
			return;
		}
		echo '<p class="form-field form-field-wide dtpw-order-tier"><strong>';
		echo esc_html__( 'Pricing tier:', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
		echo '</strong> ' . esc_html( $tier_name ) . '</p>';
	}

	/**
	 * Small frontend stylesheet for the badge and price emphasis, only when
	 * a tier is active and a display option needs it.
	 *
	 * @return void
	 */
	public function enqueue_front_style() {
		if ( ! $this->should_apply() ) {
			return;
		}
		$settings = DTPW_Settings::all();
		if ( empty( $settings['show_original'] ) && empty( $settings['badge_enabled'] ) ) {
			return;
		}
		wp_enqueue_style( 'dtpw-front', DTPW_URL . 'public/css/front.css', array(), DTPW_VERSION );
	}
}
