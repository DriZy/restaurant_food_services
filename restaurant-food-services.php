<?php
/**
 * Plugin Name: Restaurant Food Services Plugin
 * Plugin URI: https://example.com/
 * Description: A production-ready WordPress plugin scaffold for Restaurant Food Services Plugin.
 * Version: 1.0.0
 * Author: Restaurant
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
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-module-interface.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-abstract-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-meals-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-subscriptions-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-catering-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-delivery-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/modules/class-emails-module.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/class-plugin.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/class-activator.php';
require_once RESTAURANT_FOOD_SERVICES_PATH . 'includes/class-deactivator.php';

register_activation_hook( __FILE__, array( \Restaurant\FoodServices\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Restaurant\FoodServices\Deactivator::class, 'deactivate' ) );

\Restaurant\FoodServices\Bootstrap::init();




