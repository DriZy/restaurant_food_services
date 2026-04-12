<?php
/**
 * Defines module registration behavior.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Modules;

use Restaurant\FoodServices\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for plugin modules.
 */
interface Module_Interface {

	/**
	 * Registers all module hooks with the loader.
	 *
	 * @param Loader $loader Plugin loader instance.
	 *
	 * @return void
	 */
	public function register_hooks( Loader $loader );
}

