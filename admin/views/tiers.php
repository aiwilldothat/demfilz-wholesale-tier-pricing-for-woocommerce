<?php
/**
 * Tiers page: list plus add/edit form.
 *
 * @package DemfilzWholesaleTierPricing
 *
 * @var array      $tiers   All tiers keyed by id.
 * @var array|null $editing Tier being edited, or null for the add form.
 * @var array|null $prefill Submitted values to restore after a failed save.
 */

defined( 'ABSPATH' ) || exit;

$dtpw_form_name   = $editing ? $editing['name'] : '';
$dtpw_form_type   = $editing ? $editing['type'] : 'percent';
$dtpw_form_amount = ( $editing && 'price' !== $editing['type'] ) ? DTPW_Tiers::trim_num( $editing['amount'] ) : '';
$dtpw_form_active = ! $editing || ! empty( $editing['active'] );
if ( is_array( $prefill ) ) {
	$dtpw_form_name   = $prefill['name'];
	$dtpw_form_type   = $prefill['type'];
	$dtpw_form_amount = $prefill['amount'];
	$dtpw_form_active = ! empty( $prefill['active'] );
}
?>
<div class="wrap dtpw-wrap">
	<h1><?php esc_html_e( 'Pricing tiers', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></h1>
	<p class="dtpw-intro">
		<?php esc_html_e( 'Create named tiers, then assign them to customers on the Customers page, the Users list or any user profile. A logged-in customer with a tier sees that tier\'s prices across the whole store.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?>
	</p>

	<div class="dtpw-columns">
		<div class="dtpw-col-list">
			<?php if ( empty( $tiers ) ) : ?>
				<div class="dtpw-card dtpw-empty">
					<h2><?php esc_html_e( 'No tiers yet', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></h2>
					<p><?php esc_html_e( 'Create your first tier with the form on this page. Typical examples: Wholesale at 20% off, Trade at $5.00 off, VIP with its own exact price on each product.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></p>
				</div>
			<?php else : ?>
				<table class="widefat striped dtpw-tiers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Adjustment', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Status', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Customers', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $tiers as $dtpw_tier ) :
							$dtpw_count      = DTPW_Tiers::count_users( $dtpw_tier['id'] );
							$dtpw_edit_url   = add_query_arg(
								array(
									'page' => 'dtpw-tiers',
									'edit' => $dtpw_tier['id'],
								),
								admin_url( 'admin.php' )
							);
							$dtpw_delete_url = wp_nonce_url(
								add_query_arg(
									array(
										'action' => 'dtpw_delete_tier',
										'tier'   => $dtpw_tier['id'],
									),
									admin_url( 'admin-post.php' )
								),
								'dtpw_delete_tier'
							);
							/* translators: 1: tier name, 2: number of assigned customers. */
							$dtpw_confirm = sprintf( __( 'Delete the tier "%1$s"? %2$d assigned customer(s) will revert to standard prices (or their role default).', 'demfilz-wholesale-tier-pricing-for-woocommerce' ), $dtpw_tier['name'], $dtpw_count );
							?>
							<tr>
								<td><strong><?php echo esc_html( $dtpw_tier['name'] ); ?></strong></td>
								<td><?php echo wp_kses_post( DTPW_Tiers::summary( $dtpw_tier ) ); ?></td>
								<td>
									<?php if ( ! empty( $dtpw_tier['active'] ) ) : ?>
										<span class="dtpw-status dtpw-status-active"><?php esc_html_e( 'Active', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></span>
									<?php else : ?>
										<span class="dtpw-status dtpw-status-inactive"><?php esc_html_e( 'Inactive', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( (string) $dtpw_count ); ?></td>
								<td>
									<a href="<?php echo esc_url( $dtpw_edit_url ); ?>"><?php esc_html_e( 'Edit', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></a> |
									<a href="<?php echo esc_url( $dtpw_delete_url ); ?>" class="dtpw-delete dtpw-confirm" data-confirm="<?php echo esc_attr( $dtpw_confirm ); ?>"><?php esc_html_e( 'Delete', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="dtpw-col-form">
			<div class="dtpw-card">
				<h2>
					<?php
					if ( $editing ) {
						/* translators: %s: tier name. */
						echo esc_html( sprintf( __( 'Edit tier: %s', 'demfilz-wholesale-tier-pricing-for-woocommerce' ), $editing['name'] ) );
					} else {
						esc_html_e( 'Add a tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
					}
					?>
				</h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="dtpw_save_tier" />
					<?php wp_nonce_field( 'dtpw_save_tier' ); ?>
					<?php if ( $editing ) : ?>
						<input type="hidden" name="tier_id" value="<?php echo esc_attr( $editing['id'] ); ?>" />
					<?php endif; ?>

					<p>
						<label for="dtpw-tier-name"><strong><?php esc_html_e( 'Name', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></strong></label><br />
						<input type="text" id="dtpw-tier-name" name="tier_name" class="regular-text" required value="<?php echo esc_attr( $dtpw_form_name ); ?>" placeholder="<?php esc_attr_e( 'e.g. Wholesale', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?>" />
					</p>

					<p><strong><?php esc_html_e( 'Adjustment kind', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></strong></p>
					<p class="dtpw-radio-group">
						<label><input type="radio" name="tier_type" value="percent" <?php checked( $dtpw_form_type, 'percent' ); ?> /> <?php esc_html_e( 'Percentage: adjust by a signed percent, e.g. -10 is 10% off, 5 is a 5% surcharge.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></label><br />
						<label><input type="radio" name="tier_type" value="fixed" <?php checked( $dtpw_form_type, 'fixed' ); ?> /> <?php esc_html_e( 'Fixed amount: add a signed value, e.g. -5 takes 5.00 off every price.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></label><br />
						<label><input type="radio" name="tier_type" value="price" <?php checked( $dtpw_form_type, 'price' ); ?> /> <?php esc_html_e( 'Specific prices per product: set each product\'s exact price for this tier on its product edit screen. Products without a price stay at the standard price.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></label>
					</p>

					<p class="dtpw-amount-row<?php echo 'price' === $dtpw_form_type ? ' dtpw-note-hidden' : ''; ?>">
						<label for="dtpw-tier-amount"><strong><?php esc_html_e( 'Amount', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></strong> <span class="dtpw-muted"><?php esc_html_e( '(not used for per-product price tiers)', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></span></label><br />
						<input type="number" step="0.01" id="dtpw-tier-amount" name="tier_amount" class="regular-text" value="<?php echo esc_attr( $dtpw_form_amount ); ?>" placeholder="-10" />
						<span class="description dtpw-amount-hint" data-percent="<?php esc_attr_e( 'Percent. Negative = discount.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?>" data-fixed="<?php esc_attr_e( 'Amount in store currency. Negative = discount.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?>" data-price=""></span>
					</p>
					<p class="description dtpw-price-note<?php echo 'price' === $dtpw_form_type ? '' : ' dtpw-note-hidden'; ?>"><?php esc_html_e( 'No amount needed: after saving, open each product and enter its exact price for this tier in the Tier pricing panel.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></p>

					<p>
						<label><input type="checkbox" name="tier_active" value="1" <?php checked( $dtpw_form_active ); ?> /> <?php esc_html_e( 'Active (inactive tiers are kept but never applied)', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></label>
					</p>

					<p>
						<button type="submit" class="button button-primary"><?php echo esc_html( $editing ? __( 'Update tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) : __( 'Add tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ) ); ?></button>
						<?php if ( $editing ) : ?>
							<a class="button" href="<?php echo esc_url( add_query_arg( 'page', 'dtpw-tiers', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Cancel', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></a>
						<?php endif; ?>
					</p>
				</form>
			</div>
		</div>
	</div>
</div>
