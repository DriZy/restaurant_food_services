<?php
/**
 * Meals module.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Modules;

use Restaurant\FoodServices\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles meals module hooks.
 */
class Meals_Module extends Abstract_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = 'meals';

	/**
	 * Registers module hooks.
	 *
	 * @param Loader $loader Plugin loader instance.
	 *
	 * @return void
	 */
	public function register_hooks( Loader $loader ) {
		parent::register_hooks( $loader );
		$loader->add_action( 'init', $this, 'register_meals_features' );
		$loader->add_filter( 'woocommerce_product_data_tabs', $this, 'add_meals_product_data_tab' );
		$loader->add_action( 'woocommerce_product_data_panels', $this, 'render_meals_product_data_panel' );
		$loader->add_action( 'woocommerce_process_product_meta', $this, 'save_meals_product_meta' );
		$loader->add_action( 'woocommerce_single_product_summary', $this, 'render_meals_product_summary' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_meal_plan_admin_assets' );
	}

	/**
	 * Registers meals module features.
	 *
	 * @return void
	 */
	public function register_meals_features() {
		do_action( 'restaurant_food_services_meals_ready' );
	}

	/**
	 * Adds the meals tab to the WooCommerce product data tabs.
	 *
	 * @param array<string,mixed> $tabs Existing product data tabs.
	 *
	 * @return array<string,mixed>
	 */
	public function add_meals_product_data_tab( $tabs ) {
		$tabs['restaurant_food_services_meals'] = array(
			'label'    => esc_html__( 'Meals', 'restaurant-food-services' ),
			'target'   => 'restaurant_food_services_meals_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 75,
		);

		return $tabs;
	}

	/**
	 * Renders meals fields in a WooCommerce product data panel.
	 *
	 * @return void
	 */
	public function render_meals_product_data_panel() {
		echo '<div id="restaurant_food_services_meals_data" class="panel woocommerce_options_panel hidden">';

		if ( function_exists( 'woocommerce_wp_checkbox' ) ) {
			woocommerce_wp_checkbox(
				array(
					'id'          => 'is_meal_plan',
					'label'       => esc_html__( 'Is Meal Plan', 'restaurant-food-services' ),
					'description' => esc_html__( 'Enable this product as a meal plan.', 'restaurant-food-services' ),
				)
			);
		}

		echo '<div class="restaurant-food-services-meal-plan-fields">';

		if ( function_exists( 'woocommerce_wp_text_input' ) ) {
			woocommerce_wp_text_input(
				array(
					'id'                => 'meals_per_week',
					'label'             => esc_html__( 'Meals Per Week', 'restaurant-food-services' ),
					'description'       => esc_html__( 'Set how many meals are included weekly.', 'restaurant-food-services' ),
					'desc_tip'          => true,
					'type'              => 'number',
					'wrapper_class'     => 'show_if_meal_plan',
					'custom_attributes' => array(
						'min'  => '1',
						'step' => '1',
					),
				)
			);
		}

		if ( function_exists( 'woocommerce_wp_select' ) ) {
			woocommerce_wp_select(
				array(
					'id'            => 'allow_meal_selection',
					'label'         => esc_html__( 'Allow Meal Selection', 'restaurant-food-services' ),
					'description'   => esc_html__( 'Allow customers to choose meals in this plan.', 'restaurant-food-services' ),
					'desc_tip'      => true,
					'wrapper_class' => 'show_if_meal_plan',
					'options'       => array(
						'no'  => esc_html__( 'No', 'restaurant-food-services' ),
						'yes' => esc_html__( 'Yes', 'restaurant-food-services' ),
					),
				)
			);
		}

		if ( function_exists( 'woocommerce_wp_select' ) ) {
			woocommerce_wp_select(
				array(
					'id'          => 'meal_course',
					'label'       => esc_html__( 'Meal Course', 'restaurant-food-services' ),
					'description' => esc_html__( 'Assign this meal to a catering course group.', 'restaurant-food-services' ),
					'desc_tip'    => true,
					'options'     => $this->get_meal_course_field_options(),
				)
			);
		}

		echo '</div>';

		if ( function_exists( 'woocommerce_wp_text_input' ) ) {
			woocommerce_wp_text_input(
				array(
					'id'          => 'preparation_time',
					'label'       => esc_html__( 'Preparation Time', 'restaurant-food-services' ),
					'description' => esc_html__( 'Enter preparation time (e.g. 20 minutes).', 'restaurant-food-services' ),
					'desc_tip'    => true,
				)
			);
		}

		if ( function_exists( 'woocommerce_wp_select' ) ) {
			woocommerce_wp_select(
				array(
					'id'          => 'spice_level',
					'label'       => esc_html__( 'Spice Level', 'restaurant-food-services' ),
					'description' => esc_html__( 'Select the spice level for this meal.', 'restaurant-food-services' ),
					'desc_tip'    => true,
					'options'     => array(
						''        => esc_html__( 'Select a level', 'restaurant-food-services' ),
						'mild'    => esc_html__( 'Mild', 'restaurant-food-services' ),
						'medium'  => esc_html__( 'Medium', 'restaurant-food-services' ),
						'hot'     => esc_html__( 'Hot', 'restaurant-food-services' ),
						'extra'   => esc_html__( 'Extra Hot', 'restaurant-food-services' ),
					),
				)
			);
		}

		if ( function_exists( 'woocommerce_wp_textarea_input' ) ) {
			woocommerce_wp_textarea_input(
				array(
					'id'          => 'ingredients',
					'label'       => esc_html__( 'Ingredients', 'restaurant-food-services' ),
					'description' => esc_html__( 'List ingredients separated by commas or lines.', 'restaurant-food-services' ),
					'desc_tip'    => true,
				)
			);
		}

		echo '</div>';
	}

	/**
	 * Saves meals product meta fields.
	 *
	 * @param int $post_id Product post ID.
	 *
	 * @return void
	 */
	public function save_meals_product_meta( $post_id ) {
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		// Only allow administrators or the product post author to save meal plan meta
		$current_user = get_current_user_id();
		$post_author  = (int) get_post_field( 'post_author', $post_id );

		if ( ! current_user_can( 'manage_options' ) && $current_user !== $post_author ) {
			return;
		}

		$is_meal_plan = isset( $_POST['is_meal_plan'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, 'is_meal_plan', $is_meal_plan );

		if ( 'yes' === $is_meal_plan ) {
			$meals_per_week = isset( $_POST['meals_per_week'] ) ? absint( wp_unslash( $_POST['meals_per_week'] ) ) : 0;
			$allow_meal     = isset( $_POST['allow_meal_selection'] ) ? sanitize_text_field( wp_unslash( $_POST['allow_meal_selection'] ) ) : 'no';

			if ( $meals_per_week > 0 ) {
				update_post_meta( $post_id, 'meals_per_week', $meals_per_week );
			} else {
				delete_post_meta( $post_id, 'meals_per_week' );
			}

			if ( 'yes' !== $allow_meal ) {
				$allow_meal = 'no';
			}

			update_post_meta( $post_id, 'allow_meal_selection', $allow_meal );
		} else {
			delete_post_meta( $post_id, 'meals_per_week' );
			delete_post_meta( $post_id, 'allow_meal_selection' );
		}

		$this->update_product_meta_field( $post_id, 'preparation_time', 'sanitize_text_field' );
		$this->update_product_meta_field( $post_id, 'spice_level', 'sanitize_text_field' );
		$this->update_product_meta_field( $post_id, 'ingredients', 'sanitize_textarea_field' );
		$this->update_product_meta_field( $post_id, 'meal_course', 'sanitize_key' );
	}

	/**
	 * Returns meal course options for the product admin field.
	 *
	 * @return array<string,string>
	 */
	protected function get_meal_course_field_options() {
		$options = array(
			'' => esc_html__( 'Select a meal course', 'restaurant-food-services' ),
		);

		$raw_options = get_option( 'restaurant_catering_meal_course_options', array() );

		if ( ! is_array( $raw_options ) ) {
			return $options;
		}

		foreach ( $raw_options as $value => $label ) {
			if ( is_array( $label ) ) {
				$value = isset( $label['value'] ) ? $label['value'] : ( isset( $label['key'] ) ? $label['key'] : '' );
				$label = isset( $label['label'] ) ? $label['label'] : '';
			}

			$normalized_key   = sanitize_key( (string) $value );
			$normalized_label = sanitize_text_field( (string) $label );

			if ( '' === $normalized_key || '' === $normalized_label ) {
				continue;
			}

			$options[ $normalized_key ] = $normalized_label;
		}

		return $options;
	}

	/**
	 * Enqueues meal-plan admin UI script in both Classic and block-based editors.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_meal_plan_admin_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		$script_path = dirname( dirname( __DIR__ ) ) . '/assets/js/meals-admin.js';
		$version     = defined( 'RESTAURANT_FOOD_SERVICES_VERSION' ) ? RESTAURANT_FOOD_SERVICES_VERSION : '1.0.0';

		if ( file_exists( $script_path ) ) {
			$version = (string) filemtime( $script_path );
		}

		wp_enqueue_script(
			'restaurant-food-services-meals-admin',
			RESTAURANT_FOOD_SERVICES_URL . 'assets/js/meals-admin.js',
			array( 'jquery' ),
			$version,
			true
		);
	}

	/**
	 * Outputs meals fields on the single product page.
	 *
	 * @return void
	 */
	public function render_meals_product_summary() {
		global $product;

		if ( ! $product || ! method_exists( $product, 'get_id' ) ) {
			return;
		}

		$product_id       = $product->get_id();
		$preparation_time = get_post_meta( $product_id, 'preparation_time', true );
		$spice_level      = get_post_meta( $product_id, 'spice_level', true );
		$ingredients      = get_post_meta( $product_id, 'ingredients', true );

		if ( '' === $preparation_time && '' === $spice_level && '' === $ingredients ) {
			return;
		}

		echo '<div class="restaurant-food-services-meal-meta">';
		echo '<h3>' . esc_html__( 'Meal Details', 'restaurant-food-services' ) . '</h3>';
		echo '<ul>';

		if ( '' !== $preparation_time ) {
			echo '<li><strong>' . esc_html__( 'Preparation Time:', 'restaurant-food-services' ) . '</strong> ' . esc_html( $preparation_time ) . '</li>';
		}

		if ( '' !== $spice_level ) {
			echo '<li><strong>' . esc_html__( 'Spice Level:', 'restaurant-food-services' ) . '</strong> ' . esc_html( ucfirst( $spice_level ) ) . '</li>';
		}

		if ( '' !== $ingredients ) {
			echo '<li><strong>' . esc_html__( 'Ingredients:', 'restaurant-food-services' ) . '</strong> ' . esc_html( $ingredients ) . '</li>';
		}

		echo '</ul>';
		echo '</div>';
	}

	/**
	 * Updates or clears a product meta field.
	 *
	 * @param int      $post_id           Product post ID.
	 * @param string   $field             Meta key and request key.
	 * @param callable $sanitize_callback Sanitization callback.
	 *
	 * @return void
	 */
	protected function update_product_meta_field( $post_id, $field, $sanitize_callback ) {
		if ( ! isset( $_POST[ $field ] ) ) {
			delete_post_meta( $post_id, $field );
			return;
		}

		$value = call_user_func( $sanitize_callback, wp_unslash( $_POST[ $field ] ) );

		if ( '' === $value ) {
			delete_post_meta( $post_id, $field );
			return;
		}

		update_post_meta( $post_id, $field, $value );
	}
}

