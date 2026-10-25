<?php
/**
 * Plugin Name:       Demfilz Wholesale Tier Pricing for WooCommerce
 * Plugin URI:        https://demfilz.com/wholesale-tier-pricing-for-woocommerce
 * Description:       Per-customer tier pricing for WooCommerce. Create named pricing tiers that adjust prices by percentage, fixed amount or exact per-product prices, and assign a tier to each customer.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Demfilz
 * Author URI:        https://demfilz.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       demfilz-wholesale-tier-pricing-for-woocommerce
 * WC requires at least: 6.0
 * WC tested up to:   9.5
 *
 * @package DemfilzWholesaleTierPricing
 */

defined( 'ABSPATH' ) || exit;

define( 'DTPW_VERSION', '1.0.0' );
define( 'DTPW_FILE', __FILE__ );
define( 'DTPW_PATH', plugin_dir_path( __FILE__ ) );
define( 'DTPW_URL', plugin_dir_url( __FILE__ ) );
define( 'DTPW_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', DTPW_FILE, true );
		}
	}
);

/**
 * Boot the plugin once all plugins are loaded, guarding for WooCommerce.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Demfilz Wholesale Tier Pricing for WooCommerce requires WooCommerce to be installed and active.', 'demfilz-wholesale-tier-pricing-for-woocommerce' );
					echo '</p></div>';
				}
			);
			return;
		}

		require_once DTPW_PATH . 'includes/class-dtpw-settings.php';
		require_once DTPW_PATH . 'includes/class-dtpw-tiers.php';
		require_once DTPW_PATH . 'includes/class-dtpw-customers.php';
		require_once DTPW_PATH . 'includes/class-dtpw-pricing.php';
		require_once DTPW_PATH . 'includes/class-dtpw-product.php';
		require_once DTPW_PATH . 'includes/class-dtpw-users.php';
		require_once DTPW_PATH . 'includes/class-dtpw-admin.php';
		require_once DTPW_PATH . 'includes/class-dtpw-plugin.php';

		DTPW_Plugin::instance();
	}
);

/**
 * Activation: seed default options.
 */
register_activation_hook(
	__FILE__,
	function () {
		require_once DTPW_PATH . 'includes/class-dtpw-settings.php';
		if ( false === get_option( 'dtpw_settings', false ) ) {
			add_option( 'dtpw_settings', DTPW_Settings::defaults() );
		}
		if ( false === get_option( 'dtpw_tiers', false ) ) {
			add_option( 'dtpw_tiers', array() );
		}
	}
);
