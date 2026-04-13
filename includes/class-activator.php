<?php
/**
 * Plugin activation handler.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes tasks required on plugin activation.
 */
class Activator {

	/**
	 * Runs activation routines.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_subscriptions_table();
		self::schedule_subscription_cron();

		add_option( 'restaurant_food_services_version', RESTAURANT_FOOD_SERVICES_VERSION );
		add_option( 'restaurant_food_services_installed_at', gmdate( 'c' ) );
		update_option( 'restaurant_food_services_db_version', '1.0.0' );

		flush_rewrite_rules();
	}

	/**
	 * Schedules the daily subscription processor cron event.
	 *
	 * @return void
	 */
	protected static function schedule_subscription_cron() {
		$hook = 'restaurant_food_services_daily_subscription_cron';

		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', $hook );
		}
	}

	/**
	 * Creates or updates the subscriptions table.
	 *
	 * @return void
	 */
	protected static function create_subscriptions_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $wpdb->prefix . 'restaurant_subscriptions';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			plan_id bigint(20) unsigned NOT NULL,
			plan_type varchar(50) NOT NULL DEFAULT 'individual',
			meals_per_week int(11) unsigned NOT NULL DEFAULT 0,
			selected_meals longtext NULL,
			delivery_days longtext NULL,
			status varchar(50) NOT NULL DEFAULT 'active',
			next_order_date date DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY plan_id (plan_id),
			KEY status (status),
			KEY next_order_date (next_order_date)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}


