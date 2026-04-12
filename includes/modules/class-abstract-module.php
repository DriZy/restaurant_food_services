<?php
/**
 * Base class for plugin modules.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Modules;

use Restaurant\FoodServices\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides common module behavior.
 */
abstract class Abstract_Module implements Module_Interface {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = '';

	/**
	 * Returns module slug.
	 *
	 * @return string
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Registers default module hooks.
	 *
	 * @param Loader $loader Plugin loader instance.
	 *
	 * @return void
	 */
	public function register_hooks( Loader $loader ) {
		$loader->add_action( 'init', $this, 'boot' );
	}

	/**
	 * Fires a generic module boot event.
	 *
	 * @return void
	 */
	public function boot() {
		do_action( 'restaurant_food_services_module_boot', $this->get_slug(), $this );
	}
}

