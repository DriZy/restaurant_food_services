<?php
/**
 * Plugin Name: Restaurant Food Services
 * Plugin URI: https://example.com/
 * Description: A production-ready WordPress plugin scaffold for Restaurant Food Services Plugin.
 * Version: 1.0.0
 * Author: Tabi Idris
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * Text Domain: restaurant-food-services
 * Domain Path: /languages
 *
 * @package RestaurantFoodServices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'RESTAURANT_FOOD_SERVICES_VERSION' ) ) {
	define( 'RESTAURANT_FOOD_SERVICES_VERSION', '1.0.0' );
}

if ( ! defined( 'RESTAURANT_FOOD_SERVICES_FILE' ) ) {
	define( 'RESTAURANT_FOOD_SERVICES_FILE', __FILE__ );
}

if ( ! defined( 'RESTAURANT_FOOD_SERVICES_PATH' ) ) {
	define( 'RESTAURANT_FOOD_SERVICES_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'RESTAURANT_FOOD_SERVICES_URL' ) ) {
	define( 'RESTAURANT_FOOD_SERVICES_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'RESTAURANT_FOOD_SERVICES_BASENAME' ) ) {
	define( 'RESTAURANT_FOOD_SERVICES_BASENAME', plugin_basename( __FILE__ ) );
}

require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/class-loader.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/class-bootstrap.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/class-lifecycle-notices.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/public/class-order-meals-page.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/public/class-meal-plans-page.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/public/class-catering-page.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-module-interface.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-abstract-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-public-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-meals-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-subscriptions-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-catering-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-delivery-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-emails-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/class-plugin.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/class-activator.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/class-deactivator.php';

/**
 * Runs plugin activation with lifecycle warning/error capture.
 *
 * @return void
 */
function restaurant_food_services_activate() {
	\Restaurant\FoodServices\Lifecycle_Notices::capture(
		array( \Restaurant\FoodServices\Activator::class, 'activate' ),
		'Activation'
	);
}

/**
 * Runs plugin deactivation with lifecycle warning/error capture.
 *
 * @return void
 */
function restaurant_food_services_deactivate() {
	\Restaurant\FoodServices\Lifecycle_Notices::capture(
		array( \Restaurant\FoodServices\Deactivator::class, 'deactivate' ),
		'Deactivation'
	);
}

register_activation_hook( __FILE__, 'restaurant_food_services_activate' );
register_deactivation_hook( __FILE__, 'restaurant_food_services_deactivate' );

\Restaurant\FoodServices\Bootstrap::init();




