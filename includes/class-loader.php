<?php
/**
 * Registers actions and filters for the plugin.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages WordPress hooks for the plugin.
 */
class Loader {

	/**
	 * Registered actions.
	 *
	 * @var array<int,array{hook:string,component:object,callback:string}>
	 */
	protected $actions = array();

	/**
	 * Registered filters.
	 *
	 * @var array<int,array{hook:string,component:object,callback:string}>
	 */
	protected $filters = array();

	/**
	 * Adds an action to the collection.
	 *
	 * @param string $hook      The hook name.
	 * @param object $component The object instance.
	 * @param string $callback  The callback method name.
	 *
	 * @return void
	 */
	public function add_action( $hook, $component, $callback ) {
		$this->actions[] = array(
			'hook'      => $hook,
			'component' => $component,
			'callback'  => $callback,
		);
	}

	/**
	 * Adds a filter to the collection.
	 *
	 * @param string $hook      The hook name.
	 * @param object $component The object instance.
	 * @param string $callback  The callback method name.
	 *
	 * @return void
	 */
	public function add_filter( $hook, $component, $callback ) {
		$this->filters[] = array(
			'hook'      => $hook,
			'component' => $component,
			'callback'  => $callback,
		);
	}

	/**
	 * Registers all collected hooks with WordPress.
	 *
	 * @return void
	 */
	public function run() {
		foreach ( $this->actions as $action ) {
			add_action( $action['hook'], array( $action['component'], $action['callback'] ) );
		}

		foreach ( $this->filters as $filter ) {
			add_filter( $filter['hook'], array( $filter['component'], $filter['callback'] ) );
		}
	}
}


