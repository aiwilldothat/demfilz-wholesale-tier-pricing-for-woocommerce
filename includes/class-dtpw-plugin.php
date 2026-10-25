<?php
/**
 * Plugin wiring.
 *
 * @package DemfilzWholesaleTierPricing
 */

defined( 'ABSPATH' ) || exit;

/**
 * Singleton that wires the plugin parts together.
 */
class DTPW_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var DTPW_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Pricing engine.
	 *
	 * @var DTPW_Pricing
	 */
	public $pricing;

	/**
	 * Get or build the instance.
	 *
	 * @return DTPW_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Wire everything up.
	 *
	 * @return void
	 */
	private function boot() {
		$this->pricing = new DTPW_Pricing();
		$this->pricing->register();

		if ( is_admin() ) {
			$admin = new DTPW_Admin();
			$admin->register();

			$product = new DTPW_Product();
			$product->register();

			$users = new DTPW_Users();
			$users->register();
		}
	}
}
