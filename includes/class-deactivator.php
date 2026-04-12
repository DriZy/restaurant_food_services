<?php
/**
 * Plugin deactivation handler.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes tasks required on plugin deactivation.
 */
class Deactivator {

	/**
	 * Runs deactivation routines.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'restaurant_food_services_daily_subscription_cron' );
		flush_rewrite_rules();
	}
}


