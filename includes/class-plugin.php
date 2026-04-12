<?php
/**
 * Main plugin bootstrap.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices;

use Restaurant\FoodServices\Modules\Catering_Module;
use Restaurant\FoodServices\Modules\Delivery_Module;
use Restaurant\FoodServices\Modules\Emails_Module;
use Restaurant\FoodServices\Modules\Meals_Module;
use Restaurant\FoodServices\Modules\Module_Interface;
use Restaurant\FoodServices\Modules\Subscriptions_Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps the plugin.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Hook loader.
	 *
	 * @var Loader
	 */
	protected $loader;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Registered plugin modules.
	 *
	 * @var array<int,Module_Interface>
	 */
	protected $modules = array();

	/**
	 * Returns the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->version = defined( 'RESTAURANT_FOOD_SERVICES_VERSION' ) ? RESTAURANT_FOOD_SERVICES_VERSION : '1.0.0';
		$this->loader  = new Loader();
		$this->modules = $this->load_modules();

		$this->define_hooks();
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 *
	 * @throws \Exception If unserializing is attempted.
	 */
	public function __wakeup() {
		throw new \Exception( esc_html__( 'Cannot unserialize singleton plugin instance.', 'restaurant-food-services' ) );
	}

	/**
	 * Registers the plugin hooks.
	 *
	 * @return void
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Returns the loader instance.
	 *
	 * @return Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Returns the plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Defines the plugin hooks.
	 *
	 * @return void
	 */
	protected function define_hooks() {
		$this->loader->add_action( 'init', $this, 'bootstrap' );

		foreach ( $this->modules as $module ) {
			$module->register_hooks( $this->loader );
		}
	}

	/**
	 * Loads all base modules.
	 *
	 * @return array<int,Module_Interface>
	 */
	protected function load_modules() {
		return array(
			new Emails_Module(),
			new Meals_Module(),
			new Subscriptions_Module(),
			new Catering_Module(),
			new Delivery_Module(),
		);
	}

	/**
	 * Loads translations and fires a ready action for extensions.
	 *
	 * @return void
	 */
	public function bootstrap() {
		load_plugin_textdomain( 'restaurant-food-services', false, dirname( RESTAURANT_FOOD_SERVICES_BASENAME ) . '/languages' );

		/**
		 * Fires after Restaurant Food Services is bootstrapped.
		 */
		do_action( 'restaurant_food_services_loaded' );
	}
}



