<?php
/**
 * Customers page: assign panel plus assignments table.
 *
 * @package DemfilzWholesaleTierPricing
 *
 * @var array $tiers          All tiers keyed by id.
 * @var array $assigned_users WP_User objects with an assignment.
 * @var int   $total          Total assigned users.
 * @var int   $total_pages    Total pages.
 * @var int   $paged          Current page.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap dtpw-wrap">
	<h1><?php esc_html_e( 'Tier customers', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></h1>

	<?php if ( empty( $tiers ) ) : ?>
		<div class="dtpw-card dtpw-empty">
			<h2><?php esc_html_e( 'Create a tier first', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></h2>
			<p>
				<?php esc_html_e( 'You need at least one pricing tier before assigning customers.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?>
				<a href="<?php echo esc_url( add_query_arg( 'page', 'dtpw-tiers', admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Create a tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<div class="dtpw-card">
			<h2><?php esc_html_e( 'Assign customers to a tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dtpw-assign-form">
				<input type="hidden" name="action" value="dtpw_assign" />
				<?php wp_nonce_field( 'dtpw_assign' ); ?>
				<p>
					<label for="dtpw-customer-search"><strong><?php esc_html_e( 'Customers', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></strong></label><br />
					<select id="dtpw-customer-search" class="wc-customer-search dtpw-customer-search" multiple="multiple" name="customer_ids[]" data-placeholder="<?php esc_attr_e( 'Search customers by name or email', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?>" data-action="woocommerce_json_search_customers"></select>
				</p>
				<p>
					<label for="dtpw-assign-tier"><strong><?php esc_html_e( 'Tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></strong></label><br />
					<select id="dtpw-assign-tier" name="tier_id">
						<?php foreach ( $tiers as $dtpw_tier ) : ?>
							<option value="<?php echo esc_attr( $dtpw_tier['id'] ); ?>">
								<?php
								echo esc_html( $dtpw_tier['name'] );
								if ( empty( $dtpw_tier['active'] ) ) {
									echo ' ' . esc_html__( '(inactive)', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
								}
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Assign tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></button>
				</p>
				<p class="description"><?php esc_html_e( 'You can also assign tiers from the Users list (column, filter and bulk actions) or on any user profile. Role-based defaults live in Settings.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></p>
			</form>
		</div>

		<h2 class="dtpw-table-heading">
			<?php
			/* translators: %d: number of customers with a tier. */
			echo esc_html( sprintf( _n( '%d customer with a tier', '%d customers with a tier', $total, 'demfilz-wholesale-tier-pricing-for-woocommerce' ), $total ) );
			?>
		</h2>
		<p class="description"><?php esc_html_e( 'Only direct assignments are listed here. Customers covered by a role default (see Settings) are not shown, but the Users list marks them "(via role)".', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></p>

		<?php if ( empty( $assigned_users ) ) : ?>
			<div class="dtpw-card dtpw-empty">
				<p><?php esc_html_e( 'No customers are assigned to a tier yet. Use the panel above to assign your first customer.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></p>
			</div>
		<?php else : ?>
			<table class="widefat striped dtpw-customers-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Customer', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Email', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $assigned_users as $dtpw_user ) :
						$dtpw_assigned_id  = DTPW_Customers::assigned_tier_id( $dtpw_user->ID );
						$dtpw_unassign_url = wp_nonce_url(
							add_query_arg(
								array(
									'action' => 'dtpw_unassign',
									'user'   => $dtpw_user->ID,
								),
								admin_url( 'admin-post.php' )
							),
							'dtpw_unassign'
						);
						?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_user_link( $dtpw_user->ID ) ); ?>"><strong><?php echo esc_html( $dtpw_user->display_name ); ?></strong></a>
							</td>
							<td><?php echo esc_html( $dtpw_user->user_email ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dtpw-row-form">
									<input type="hidden" name="action" value="dtpw_assign" />
									<?php wp_nonce_field( 'dtpw_assign' ); ?>
									<input type="hidden" name="customer_ids[]" value="<?php echo esc_attr( (string) $dtpw_user->ID ); ?>" />
									<label class="screen-reader-text" for="dtpw-row-tier-<?php echo esc_attr( (string) $dtpw_user->ID ); ?>"><?php esc_html_e( 'Change tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></label>
									<select name="tier_id" id="dtpw-row-tier-<?php echo esc_attr( (string) $dtpw_user->ID ); ?>">
										<?php foreach ( $tiers as $dtpw_tier ) : ?>
											<option value="<?php echo esc_attr( $dtpw_tier['id'] ); ?>" <?php selected( $dtpw_assigned_id, $dtpw_tier['id'] ); ?>>
												<?php
												echo esc_html( $dtpw_tier['name'] );
												if ( empty( $dtpw_tier['active'] ) ) {
													echo ' ' . esc_html__( '(inactive)', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
												}
												?>
											</option>
										<?php endforeach; ?>
									</select>
									<button type="submit" class="button"><?php esc_html_e( 'Change', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></button>
								</form>
							</td>
							<td>
								<a href="<?php echo esc_url( $dtpw_unassign_url ); ?>" class="dtpw-delete dtpw-confirm" data-confirm="<?php esc_attr_e( 'Remove this customer\'s tier? They will see standard prices.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?>"><?php esc_html_e( 'Remove', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'    => add_query_arg( 'paged', '%#%' ),
								'format'  => '',
								'current' => $paged,
								'total'   => $total_pages,
							)
						)
					);
					?>
				</div></div>
			<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
