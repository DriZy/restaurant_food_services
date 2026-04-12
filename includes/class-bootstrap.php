<?php
/**
 * Plugin bootstrap hooks.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin startup lifecycle.
 */
class Bootstrap {

	/**
	 * Registers the initial plugin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'on_plugins_loaded' ) );
	}

	/**
	 * Boots plugin modules after dependency checks.
	 *
	 * @return void
	 */
	public static function on_plugins_loaded() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'woocommerce_notice' ) );
			return;
		}

		Plugin::instance()->run();
	}

	/**
	 * Displays an admin notice when WooCommerce is inactive.
	 *
	 * @return void
	 */
	public static function woocommerce_notice() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Restaurant Food Services Plugin requires WooCommerce to be active.', 'restaurant-food-services' ) . '</p></div>';
	}
}

