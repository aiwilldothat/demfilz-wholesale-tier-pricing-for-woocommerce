<?php
/**
 * Settings page.
 *
 * @package DemfilzWholesaleTierPricing
 *
 * @var array $settings Current settings.
 * @var array $tiers    All tiers keyed by id.
 * @var array $roles    WP roles array (role key => data).
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap dtpw-wrap">
	<h1><?php esc_html_e( 'Tier Pricing settings', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></h1>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="dtpw_save_settings" />
		<?php wp_nonce_field( 'dtpw_save_settings' ); ?>

		<div class="dtpw-card">
			<h2><?php esc_html_e( 'Price display', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></h2>
			<p>
				<label><input type="checkbox" name="show_original" value="1" <?php checked( ! empty( $settings['show_original'] ) ); ?> />
				<?php esc_html_e( 'Show the standard price struck through next to the tier price (simple products and variations)', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></label>
			</p>
			<p>
				<label><input type="checkbox" name="badge_enabled" value="1" <?php checked( ! empty( $settings['badge_enabled'] ) ); ?> />
				<?php esc_html_e( 'Show a badge next to tier prices', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></label>
			</p>
			<p>
				<label for="dtpw-badge-text"><?php esc_html_e( 'Badge text', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></label><br />
				<input type="text" id="dtpw-badge-text" name="badge_text" class="regular-text" value="<?php echo esc_attr( $settings['badge_text'] ); ?>" />
				<span class="description"><?php esc_html_e( '{tier} is replaced with the tier name.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></span>
			</p>
		</div>

		<div class="dtpw-card">
			<h2><?php esc_html_e( 'Behaviour', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></h2>
			<p>
				<label for="dtpw-sale-mode"><strong><?php esc_html_e( 'Products already on sale', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></strong></label><br />
				<select id="dtpw-sale-mode" name="sale_mode">
					<option value="apply" <?php selected( $settings['sale_mode'], 'apply' ); ?>><?php esc_html_e( 'Apply the tier adjustment to the sale price', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></option>
					<option value="skip" <?php selected( $settings['sale_mode'], 'skip' ); ?>><?php esc_html_e( 'Skip tier pricing while a product is on sale', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></option>
				</select>
			</p>
			<p>
				<label><input type="checkbox" name="only_if_lower" value="1" <?php checked( ! empty( $settings['only_if_lower'] ) ); ?> />
				<?php esc_html_e( 'Never charge more than the standard price (blocks surcharge results)', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></label>
			</p>
			<p>
				<label for="dtpw-rounding"><strong><?php esc_html_e( 'Round adjusted prices', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></strong></label><br />
				<select id="dtpw-rounding" name="rounding">
					<option value="none" <?php selected( $settings['rounding'], 'none' ); ?>><?php esc_html_e( 'No rounding', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></option>
					<option value="whole" <?php selected( $settings['rounding'], 'whole' ); ?>><?php esc_html_e( 'Round to the nearest whole amount', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></option>
					<option value="end95" <?php selected( $settings['rounding'], 'end95' ); ?>><?php esc_html_e( 'End prices in .95', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></option>
					<option value="end99" <?php selected( $settings['rounding'], 'end99' ); ?>><?php esc_html_e( 'End prices in .99', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></option>
				</select>
			</p>
		</div>

		<div class="dtpw-card">
			<h2><?php esc_html_e( 'Role-based defaults', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Give every user of a role a tier automatically. A direct per-customer assignment always beats the role default.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></p>
			<?php if ( empty( $tiers ) ) : ?>
				<p><?php esc_html_e( 'Create a tier first to map roles.', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></p>
			<?php else : ?>
				<table class="widefat striped dtpw-role-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Role', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'Default tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $roles as $dtpw_role_key => $dtpw_role_data ) :
							$dtpw_mapped = isset( $settings['role_map'][ $dtpw_role_key ] ) ? $settings['role_map'][ $dtpw_role_key ] : '';
							?>
							<tr>
								<td><?php echo esc_html( translate_user_role( $dtpw_role_data['name'] ) ); ?></td>
								<td>
									<label class="screen-reader-text" for="dtpw-role-<?php echo esc_attr( $dtpw_role_key ); ?>">
										<?php
										/* translators: %s: role name. */
										echo esc_html( sprintf( __( 'Default tier for role %s', 'demfilz-wholesale-tier-pricing-for-woocommerce' ), translate_user_role( $dtpw_role_data['name'] ) ) );
										?>
									</label>
									<select id="dtpw-role-<?php echo esc_attr( $dtpw_role_key ); ?>" name="role_map[<?php echo esc_attr( $dtpw_role_key ); ?>]">
										<option value=""><?php esc_html_e( 'No tier', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></option>
										<?php foreach ( $tiers as $dtpw_tier ) : ?>
											<option value="<?php echo esc_attr( $dtpw_tier['id'] ); ?>" <?php selected( $dtpw_mapped, $dtpw_tier['id'] ); ?>><?php echo esc_html( $dtpw_tier['name'] ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'demfilz-wholesale-tier-pricing-for-woocommerce' ); ?></button></p>
	</form>
</div>
