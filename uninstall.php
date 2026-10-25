<?php
/**
 * Uninstall cleanup for Demfilz Wholesale Tier Pricing for WooCommerce.
 *
 * Removes plugin options, per-user tier assignments and per-product override
 * meta. Historical order snapshots (_dtpw_order_tier) are intentionally kept
 * because they document how past orders were priced.
 *
 * @package DemfilzWholesaleTierPricing
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'dtpw_tiers' );
delete_option( 'dtpw_settings' );

delete_metadata( 'user', 0, '_dtpw_tier', '', true );
delete_post_meta_by_key( '_dtpw_overrides' );
