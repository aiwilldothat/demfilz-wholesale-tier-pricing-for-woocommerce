<?php
/**
 * Per-product tier override metabox.
 *
 * @package DemfilzWholesaleTierPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Adds a "Tier pricing" panel to the product edit screen where each tier can
 * be set to inherit, exclude the product, or use a custom rule.
 */
class DTPW_Product {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post_product', array( $this, 'save' ), 10, 2 );
	}

	/**
	 * Register the metabox on the product screen.
	 *
	 * @return void
	 */
	public function add_metabox() {
		if ( empty( DTPW_Tiers::all() ) ) {
			return;
		}
		add_meta_box(
			'dtpw-product-overrides',
			__( 'Tier pricing', 'demfilz-wholesale-tier-pricing-for-woocommerce' ),
			array( $this, 'render' ),
			'product',
			'normal',
			'default'
		);
	}

	/**
	 * Render the per-tier override rows.
	 *
	 * @param WP_Post $post Product post.
	 * @return void
	 */
	public function render( $post ) {
		$tiers     = DTPW_Tiers::all();
		$overrides = get_post_meta( $post->ID, '_dtpw_overrides', true );
		if ( ! is_array( $overrides ) ) {
			$overrides = array();
		}
		wp_nonce_field( 'dtpw_product_overrides', 'dtpw_product_nonce' );

		echo '<p class="description">';
		echo esc_html__( 'Override how each pricing tier treats this product. For a "specific prices per product" tier, enter this product\'s exact tier price (leave empty for the standard price). Variations of a variable product follow the parent product settings.', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
		echo '</p>';
		echo '<table class="widefat striped dtpw-overrides-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Default rule', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'This product', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</th>';
		echo '<th>' . esc_html__( 'Custom rule', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $tiers as $tier ) {
			$row    = isset( $overrides[ $tier['id'] ] ) ? $overrides[ $tier['id'] ] : array();
			$mode   = isset( $row['mode'] ) ? $row['mode'] : 'inherit';
			$type   = isset( $row['type'] ) ? $row['type'] : $tier['type'];
			$amount = isset( $row['amount'] ) ? (float) $row['amount'] : '';
			$field  = 'dtpw_overrides[' . $tier['id'] . ']';

			echo '<tr class="dtpw-override-row">';
			echo '<td><strong>' . esc_html( $tier['name'] ) . '</strong>';
			if ( empty( $tier['active'] ) ) {
				echo ' <span class="dtpw-muted">' . esc_html__( '(inactive)', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</span>';
			}
			echo '</td>';
			echo '<td>' . wp_kses_post( DTPW_Tiers::summary( $tier ) ) . '</td>';

			// A price-kind tier: one exact price for this product, nothing else.
			if ( 'price' === $tier['type'] ) {
				$tier_price = ( isset( $row['mode'], $row['type'] ) && 'custom' === $row['mode'] && 'price' === $row['type'] ) ? DTPW_Tiers::trim_num( $amount ) : '';
				echo '<td colspan="2">';
				echo '<label class="screen-reader-text" for="dtpw-price-' . esc_attr( $tier['id'] ) . '">';
				/* translators: %s: tier name. */
				echo esc_html( sprintf( __( 'Tier price for %s', 'demfilz-wholesale-tier-pricing-for-woocommerce' ), $tier['name'] ) );
				echo '</label>';
				echo '<input type="number" step="0.01" min="0" class="small-text" id="dtpw-price-' . esc_attr( $tier['id'] ) . '" name="' . esc_attr( $field . '[price]' ) . '" value="' . esc_attr( $tier_price ) . '" placeholder="' . esc_attr__( 'standard price', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '" /> ';
				echo '<span class="dtpw-muted">' . esc_html__( 'Exact price for this tier. Empty = standard price.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</span>';
				echo '</td></tr>';
				continue;
			}

			echo '<td><select name="' . esc_attr( $field . '[mode]' ) . '" class="dtpw-mode">';
			echo '<option value="inherit"' . selected( $mode, 'inherit', false ) . '>' . esc_html__( 'Inherit tier rule', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</option>';
			echo '<option value="exclude"' . selected( $mode, 'exclude', false ) . '>' . esc_html__( 'Exclude from this tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</option>';
			echo '<option value="custom"' . selected( $mode, 'custom', false ) . '>' . esc_html__( 'Custom rule', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</option>';
			echo '</select></td>';
			echo '<td class="dtpw-custom-fields' . ( 'custom' === $mode ? '' : ' dtpw-hidden' ) . '">';
			echo '<select name="' . esc_attr( $field . '[type]' ) . '">';
			echo '<option value="percent"' . selected( $type, 'percent', false ) . '>' . esc_html__( 'Percentage (+/-)', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</option>';
			echo '<option value="fixed"' . selected( $type, 'fixed', false ) . '>' . esc_html__( 'Fixed amount (+/-)', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</option>';
			echo '<option value="price"' . selected( $type, 'price', false ) . '>' . esc_html__( 'Specific price', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) . '</option>';
			echo '</select> ';
			echo '<input type="number" step="0.01" class="small-text" name="' . esc_attr( $field . '[amount]' ) . '" value="' . esc_attr( '' === $amount ? '' : DTPW_Tiers::trim_num( $amount ) ) . '" placeholder="0.00" />';
			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Save the override rows.
	 *
	 * @param int     $post_id Product id.
	 * @param WP_Post $post    Product post.
	 * @return void
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST['dtpw_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dtpw_product_nonce'] ) ), 'dtpw_product_overrides' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$raw = array();
		if ( isset( $_POST['dtpw_overrides'] ) && is_array( $_POST['dtpw_overrides'] ) ) {
			$raw = map_deep( wp_unslash( $_POST['dtpw_overrides'] ), 'sanitize_text_field' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via map_deep.
		}

		$clean = self::sanitize_overrides( $raw );

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, '_dtpw_overrides' );
		} else {
			update_post_meta( $post_id, '_dtpw_overrides', $clean );
		}
	}

	/**
	 * Sanitize a raw overrides submission down to stored rows. Inherit rows
	 * and rows for unknown tiers are dropped; custom rows need a valid type
	 * and numeric amount. Price-kind tiers submit a single 'price' field
	 * which is stored as a custom price rule.
	 *
	 * @param array $raw Raw (unslashed, text-sanitized) overrides input.
	 * @return array
	 */
	public static function sanitize_overrides( $raw ) {
		$clean = array();
		if ( ! is_array( $raw ) ) {
			return $clean;
		}
		$tiers = DTPW_Tiers::all();

		foreach ( $raw as $tier_id => $row ) {
			$tier_id = sanitize_key( $tier_id );
			if ( ! isset( $tiers[ $tier_id ] ) || ! is_array( $row ) ) {
				continue;
			}

			// Price-kind tiers: a single per-product exact price.
			if ( 'price' === $tiers[ $tier_id ]['type'] ) {
				$price = isset( $row['price'] ) ? trim( (string) $row['price'] ) : '';
				if ( '' !== $price && is_numeric( $price ) && (float) $price >= 0 ) {
					$clean[ $tier_id ] = array(
						'mode'   => 'custom',
						'type'   => 'price',
						'amount' => (float) $price,
					);
				}
				continue;
			}

			$mode = isset( $row['mode'] ) ? (string) $row['mode'] : 'inherit';
			if ( 'exclude' === $mode ) {
				$clean[ $tier_id ] = array( 'mode' => 'exclude' );
				continue;
			}
			if ( 'custom' !== $mode ) {
				continue;
			}
			$type   = isset( $row['type'] ) ? (string) $row['type'] : '';
			$amount = isset( $row['amount'] ) ? trim( (string) $row['amount'] ) : '';
			if ( ! in_array( $type, array( 'percent', 'fixed', 'price' ), true ) || '' === $amount || ! is_numeric( $amount ) ) {
				continue;
			}
			if ( 'price' === $type && (float) $amount < 0 ) {
				continue;
			}
			$clean[ $tier_id ] = array(
				'mode'   => 'custom',
				'type'   => $type,
				'amount' => (float) $amount,
			);
		}

		return $clean;
	}
}
