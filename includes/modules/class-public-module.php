<?php
/**
 * Public frontend module.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Modules;

use Restaurant\FoodServices\Loader;
use Restaurant\FoodServices\Public\Catering_Page;
use Restaurant\FoodServices\Public\Meal_Plans_Page;
use Restaurant\FoodServices\Public\Order_Meals_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers frontend shortcodes.
 */
class Public_Module extends Abstract_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = 'public';

	/**
	 * Order Meals page renderer.
	 *
	 * @var Order_Meals_Page
	 */
	protected $order_meals_page;

	/**
	 * Meal Plans page renderer.
	 *
	 * @var Meal_Plans_Page
	 */
	protected $meal_plans_page;

	/**
	 * Catering page renderer.
	 *
	 * @var Catering_Page
	 */
	protected $catering_page;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->order_meals_page = new Order_Meals_Page();
		$this->meal_plans_page  = new Meal_Plans_Page();
		$this->catering_page    = new Catering_Page();
	}

	/**
	 * Registers module hooks.
	 *
	 * @param Loader $loader Plugin loader instance.
	 *
	 * @return void
	 */
	public function register_hooks( Loader $loader ) {
		parent::register_hooks( $loader );
		$loader->add_action( 'init', $this, 'register_shortcodes' );
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_assets' );
		$loader->add_action( 'restaurant_food_services_render_order_meals_ui', $this, 'render_order_meals_ui' );
		$loader->add_action( 'restaurant_food_services_render_meal_plans_ui', $this, 'render_meal_plans_wizard_ui' );
		$loader->add_action( 'restaurant_food_services_render_catering_ui', $this, 'render_catering_wizard_ui' );
		$loader->add_action( 'wp_ajax_restaurant_add_meal_to_cart', $this, 'ajax_add_meal_to_cart' );
		$loader->add_action( 'wp_ajax_nopriv_restaurant_add_meal_to_cart', $this, 'ajax_add_meal_to_cart' );
		$loader->add_action( 'wp_ajax_restaurant_submit_meal_plan_selection', $this, 'ajax_submit_meal_plan_selection' );
		$loader->add_action( 'wp_ajax_nopriv_restaurant_submit_meal_plan_selection', $this, 'ajax_submit_meal_plan_selection' );
		$loader->add_action( 'wp_ajax_restaurant_save_catering_draft', $this, 'ajax_save_catering_draft' );
		$loader->add_action( 'wp_ajax_nopriv_restaurant_save_catering_draft', $this, 'ajax_save_catering_draft' );
		$loader->add_action( 'wp_ajax_restaurant_submit_catering_request', $this, 'ajax_submit_catering_request' );
		$loader->add_action( 'wp_ajax_nopriv_restaurant_submit_catering_request', $this, 'ajax_submit_catering_request' );
		$loader->add_action( 'wp_ajax_restaurant_submit_catering', $this, 'ajax_submit_catering_request' );
		$loader->add_action( 'wp_ajax_nopriv_restaurant_submit_catering', $this, 'ajax_submit_catering_request' );
		$loader->add_action( 'wp_ajax_restaurant_calculate_catering_price', $this, 'ajax_calculate_catering_price' );
		$loader->add_action( 'wp_ajax_nopriv_restaurant_calculate_catering_price', $this, 'ajax_calculate_catering_price' );
		$loader->add_action( 'woocommerce_checkout_create_order', $this, 'attach_meal_plan_selection_to_order' );
		$loader->add_action( 'woocommerce_add_to_cart', $this, 'handle_woocommerce_add_to_cart' );
	}

	/**
	 * Enqueues frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_public_assets() {
		wp_enqueue_style(
			'restaurant-food-services-public',
			RESTAURANT_FOOD_SERVICES_URL . 'assets/css/public-frontend.css',
			array(),
			defined( 'RESTAURANT_FOOD_SERVICES_VERSION' ) ? RESTAURANT_FOOD_SERVICES_VERSION : '1.0.0'
		);

		wp_enqueue_script(
			'restaurant-food-services-public',
			RESTAURANT_FOOD_SERVICES_URL . 'assets/js/public-frontend.js',
			array( 'jquery' ),
			defined( 'RESTAURANT_FOOD_SERVICES_VERSION' ) ? RESTAURANT_FOOD_SERVICES_VERSION : '1.0.0',
			true
		);

		wp_localize_script(
			'restaurant-food-services-public',
			'RestaurantFoodServicesPublic',
			array(
				'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'restaurant_add_meal_to_cart' ),
				'mealPlanSubmitNonce'  => wp_create_nonce( 'restaurant_meal_plan_wizard_submit' ),
				'cateringDraftNonce'   => wp_create_nonce( 'restaurant_catering_wizard_draft' ),
				'cateringSubmitNonce'  => wp_create_nonce( 'restaurant_catering_wizard_submit' ),
				'cateringPriceNonce'   => wp_create_nonce( 'restaurant_catering_price_preview' ),
			)
		);

		wp_enqueue_script(
			'restaurant-food-services-meal-plans-wizard',
			RESTAURANT_FOOD_SERVICES_URL . 'assets/js/meal-plans-wizard.js',
			array( 'jquery', 'restaurant-food-services-public' ),
			defined( 'RESTAURANT_FOOD_SERVICES_VERSION' ) ? RESTAURANT_FOOD_SERVICES_VERSION : '1.0.0',
			true
		);

		wp_enqueue_script(
			'restaurant-food-services-catering-wizard',
			RESTAURANT_FOOD_SERVICES_URL . 'assets/js/catering-wizard.js',
			array( 'jquery', 'restaurant-food-services-public' ),
			defined( 'RESTAURANT_FOOD_SERVICES_VERSION' ) ? RESTAURANT_FOOD_SERVICES_VERSION : '1.0.0',
			true
		);
	}

	/**
	 * Renders the catering multi-step wizard for [restaurant_catering].
	 *
	 * @return void
	 */
	public function render_catering_wizard_ui() {
		$products = $this->get_catering_menu_products();
		$min_event_date = gmdate( 'Y-m-d', strtotime( '+1 day', current_time( 'timestamp', true ) ) );
		$offering_options = $this->get_catering_offering_options();
		$service_options  = $this->get_catering_service_options();

		echo '<div class="restaurant-catering-wizard woocommerce" data-step="1">';
		echo '<ol class="restaurant-wizard-steps">';
		echo '<li data-step="1" class="is-active">' . esc_html__( 'Event Details', 'restaurant-food-services' ) . '</li>';
		echo '<li data-step="2">' . esc_html__( 'Menu Selection', 'restaurant-food-services' ) . '</li>';
		echo '<li data-step="3">' . esc_html__( 'Special Requirements', 'restaurant-food-services' ) . '</li>';
		echo '<li data-step="4">' . esc_html__( 'Summary', 'restaurant-food-services' ) . '</li>';
		echo '</ol>';

		echo '<div class="restaurant-wizard-panels">';

		echo '<section class="restaurant-wizard-panel is-active" data-step="1">';
		echo '<h3>' . esc_html__( 'Step 1: Event Details', 'restaurant-food-services' ) . '</h3>';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Catering Offering', 'restaurant-food-services' ) . '</label><select class="restaurant-wizard-input input-text" name="event_type">';
		echo '<option value="">' . esc_html__( 'Select an offering', 'restaurant-food-services' ) . '</option>';
		foreach ( $offering_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Event Date', 'restaurant-food-services' ) . '</label><input type="date" class="restaurant-wizard-input input-text" name="event_date" min="' . esc_attr( $min_event_date ) . '" /></p>';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Guest Count', 'restaurant-food-services' ) . '</label><input type="number" min="1" class="restaurant-wizard-input input-text" name="guest_count" /></p>';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Location', 'restaurant-food-services' ) . '</label><input type="text" class="restaurant-wizard-input input-text" name="location" /></p>';
		echo '</section>';

		echo '<section class="restaurant-wizard-panel" data-step="2">';
		echo '<h3>' . esc_html__( 'Step 2: Select Menu Items', 'restaurant-food-services' ) . '</h3>';
		echo '<div class="restaurant-filters" data-filter-scope="catering">';
		echo '<button type="button" class="button restaurant-filter-toggle" aria-expanded="false">' . esc_html__( 'Filters', 'restaurant-food-services' ) . '</button>';
		echo '<div class="restaurant-filters__body">';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Search Items', 'restaurant-food-services' ) . '</label><input type="search" class="restaurant-wizard-input input-text" data-filter-search></p>';
		echo '</div>';
		echo '</div>';
		echo '<div class="restaurant-catering-price-preview" data-subtotal="0" data-service-fee="0" data-total="0">';
		echo '<p><strong>' . esc_html__( 'Subtotal:', 'restaurant-food-services' ) . '</strong> <span class="restaurant-price-subtotal">' . wp_kses_post( wc_price( 0 ) ) . '</span></p>';
		echo '<p><strong>' . esc_html__( 'Service Fee:', 'restaurant-food-services' ) . '</strong> <span class="restaurant-price-service-fee">' . wp_kses_post( wc_price( 0 ) ) . '</span></p>';
		echo '<p><strong>' . esc_html__( 'Total:', 'restaurant-food-services' ) . '</strong> <span class="restaurant-price-total">' . wp_kses_post( wc_price( 0 ) ) . '</span></p>';
		echo '</div>';
		echo '<div class="restaurant-meals-grid" role="list">';
		if ( empty( $products ) ) {
			echo '<p>' . esc_html__( 'No menu items available right now.', 'restaurant-food-services' ) . '</p>';
		} else {
			foreach ( $products as $product ) {
				echo '<article class="restaurant-meal-card" role="listitem" data-product-name="' . esc_attr( strtolower( $product->get_name() ) ) . '">';
				echo '<a class="restaurant-meal-card__image-link" href="' . esc_url( $product->get_permalink() ) . '">';
				echo $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'restaurant-meal-card__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</a>';
				echo '<div class="restaurant-meal-card__content">';
				echo '<h4 class="restaurant-meal-card__title"><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h4>';
				echo '<div class="restaurant-meal-card__price">' . wp_kses_post( $product->get_price_html() ) . '</div>';
				echo '<p class="form-row form-row-wide restaurant-menu-qty"><label>' . esc_html__( 'Quantity', 'restaurant-food-services' ) . '</label><input type="number" min="0" value="0" class="restaurant-wizard-input input-text restaurant-menu-quantity" data-product-id="' . esc_attr( (string) $product->get_id() ) . '" data-product-name="' . esc_attr( $product->get_name() ) . '" /></p>';
				echo '</div>';
				echo '</article>';
			}
		}
		echo '</div>';
		echo '</section>';

		echo '<section class="restaurant-wizard-panel" data-step="3">';
		echo '<h3>' . esc_html__( 'Step 3: Special Requirements', 'restaurant-food-services' ) . '</h3>';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Service Option', 'restaurant-food-services' ) . '</label><select class="restaurant-wizard-input input-text" name="serving_style">';
		echo '<option value="">' . esc_html__( 'Select service option', 'restaurant-food-services' ) . '</option>';
		foreach ( $service_options as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Special Requests', 'restaurant-food-services' ) . '</label><textarea class="restaurant-wizard-input input-text" name="special_requests" rows="4"></textarea></p>';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Dietary Needs', 'restaurant-food-services' ) . '</label><textarea class="restaurant-wizard-input input-text" name="dietary_needs" rows="4" placeholder="Vegetarian, Gluten-free, Nut allergies..."></textarea></p>';
		echo '</section>';

		echo '<section class="restaurant-wizard-panel" data-step="4">';
		echo '<h3>' . esc_html__( 'Step 4: Summary', 'restaurant-food-services' ) . '</h3>';
		echo '<div class="restaurant-wizard-summary"></div>';
		echo '<button type="button" class="button restaurant-catering-save-draft">' . esc_html__( 'Save Draft', 'restaurant-food-services' ) . '</button> ';
		echo '<button type="button" class="button button-primary restaurant-catering-submit">' . esc_html__( 'Submit Catering Request', 'restaurant-food-services' ) . '</button>';
		echo '</section>';

		echo '</div>';

		echo '<div class="restaurant-wizard-actions">';
		echo '<button type="button" class="button restaurant-wizard-prev" disabled>' . esc_html__( 'Back', 'restaurant-food-services' ) . '</button>';
		echo '<button type="button" class="button button-primary restaurant-wizard-next">' . esc_html__( 'Next', 'restaurant-food-services' ) . '</button>';
		echo '</div>';
		echo '<aside class="restaurant-summary-sidebar" data-summary-sidebar="catering">';
		echo '<h4>' . esc_html__( 'Summary Sidebar', 'restaurant-food-services' ) . '</h4>';
		echo '<ul class="restaurant-summary-sidebar__items"></ul>';
		echo '<p class="restaurant-summary-sidebar__total"><strong>' . esc_html__( 'Total Price:', 'restaurant-food-services' ) . '</strong> <span class="restaurant-summary-sidebar__total-value">' . wp_kses_post( wc_price( 0 ) ) . '</span></p>';
		echo '</aside>';
		echo '</div>';
	}

	/**
	 * Renders the meal plan wizard for [restaurant_meal_plans].
	 *
	 * @return void
	 */
	public function render_meal_plans_wizard_ui() {
		$meal_products = $this->get_meal_choice_products();

		echo '<div class="restaurant-meal-plans-wizard woocommerce" data-step="1">';
		echo '<ol class="restaurant-wizard-steps">';
		echo '<li data-step="1" class="is-active">' . esc_html__( 'Plan Type', 'restaurant-food-services' ) . '</li>';
		echo '<li data-step="2">' . esc_html__( 'Meals Per Week', 'restaurant-food-services' ) . '</li>';
		echo '<li data-step="3">' . esc_html__( 'Choose Meals', 'restaurant-food-services' ) . '</li>';
		echo '<li data-step="4">' . esc_html__( 'Delivery Preferences', 'restaurant-food-services' ) . '</li>';
		echo '<li data-step="5">' . esc_html__( 'Summary', 'restaurant-food-services' ) . '</li>';
		echo '</ol>';

		echo '<div class="restaurant-wizard-panels">';
		echo '<section class="restaurant-wizard-panel is-active" data-step="1">';
		echo '<h3>' . esc_html__( 'Step 1: Choose Plan Type', 'restaurant-food-services' ) . '</h3>';
		echo '<label><input type="radio" name="plan_type" value="individual"> ' . esc_html__( 'Individual', 'restaurant-food-services' ) . '</label>';
		echo '<label><input type="radio" name="plan_type" value="family"> ' . esc_html__( 'Family', 'restaurant-food-services' ) . '</label>';
		echo '</section>';

		echo '<section class="restaurant-wizard-panel" data-step="2">';
		echo '<h3>' . esc_html__( 'Step 2: Select Meals Per Week', 'restaurant-food-services' ) . '</h3>';
		echo '<select name="meals_per_week" class="restaurant-wizard-input input-text">';
		echo '<option value="">' . esc_html__( 'Select meals per week', 'restaurant-food-services' ) . '</option>';
		foreach ( array( 3, 5, 7, 10, 14 ) as $count ) {
			echo '<option value="' . esc_attr( (string) $count ) . '">' . esc_html( (string) $count ) . '</option>';
		}
		echo '</select>';
		echo '</section>';

		echo '<section class="restaurant-wizard-panel" data-step="3">';
		echo '<h3>' . esc_html__( 'Step 3: Choose Meals', 'restaurant-food-services' ) . '</h3>';
		echo '<p class="restaurant-wizard-help">' . esc_html__( 'Select your preferred meals from the options below.', 'restaurant-food-services' ) . '</p>';
		echo '<div class="restaurant-filters" data-filter-scope="meal-plans">';
		echo '<button type="button" class="button restaurant-filter-toggle" aria-expanded="false">' . esc_html__( 'Filters', 'restaurant-food-services' ) . '</button>';
		echo '<div class="restaurant-filters__body">';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Search Meals', 'restaurant-food-services' ) . '</label><input type="search" class="restaurant-wizard-input input-text" data-filter-search></p>';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Spice Level', 'restaurant-food-services' ) . '</label><select class="restaurant-wizard-input input-text" data-filter-spice><option value="">' . esc_html__( 'All', 'restaurant-food-services' ) . '</option><option value="mild">' . esc_html__( 'Mild', 'restaurant-food-services' ) . '</option><option value="medium">' . esc_html__( 'Medium', 'restaurant-food-services' ) . '</option><option value="hot">' . esc_html__( 'Hot', 'restaurant-food-services' ) . '</option><option value="extra">' . esc_html__( 'Extra Hot', 'restaurant-food-services' ) . '</option></select></p>';
		echo '</div>';
		echo '</div>';
		echo '<div class="restaurant-meals-grid" role="list">';
		if ( empty( $meal_products ) ) {
			echo '<p>' . esc_html__( 'No meals available right now.', 'restaurant-food-services' ) . '</p>';
		} else {
			foreach ( $meal_products as $product ) {
				$product_id  = $product->get_id();
				$spice_level = sanitize_text_field( (string) get_post_meta( $product_id, 'spice_level', true ) );
				$spice_label = '' !== $spice_level ? ucfirst( $spice_level ) : __( 'Not specified', 'restaurant-food-services' );
				echo '<article class="restaurant-meal-card" role="listitem" data-product-name="' . esc_attr( strtolower( $product->get_name() ) ) . '" data-spice-level="' . esc_attr( strtolower( $spice_level ) ) . '">';
				echo '<label class="restaurant-meal-card__selector">';
				echo '<input type="checkbox" class="restaurant-meal-select" value="' . esc_attr( (string) $product_id ) . '" data-product-name="' . esc_attr( $product->get_name() ) . '" data-product-price="' . esc_attr( (string) (float) $product->get_price() ) . '">';
				echo '<span>' . esc_html__( 'Select', 'restaurant-food-services' ) . '</span>';
				echo '</label>';
				echo '<a class="restaurant-meal-card__image-link" href="' . esc_url( $product->get_permalink() ) . '">';
				echo $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'restaurant-meal-card__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</a>';
				echo '<div class="restaurant-meal-card__content">';
				echo '<h4 class="restaurant-meal-card__title"><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h4>';
				echo '<div class="restaurant-meal-card__price">' . wp_kses_post( $product->get_price_html() ) . '</div>';
				echo '<p class="restaurant-meal-card__spice"><strong>' . esc_html__( 'Spice Level:', 'restaurant-food-services' ) . '</strong> ' . esc_html( $spice_label ) . '</p>';
				echo '</div>';
				echo '</article>';
			}
		}
		echo '</div>';
		echo '</section>';

		echo '<section class="restaurant-wizard-panel" data-step="4">';
		echo '<h3>' . esc_html__( 'Step 4: Delivery Preferences', 'restaurant-food-services' ) . '</h3>';
		echo '<div class="restaurant-delivery-days">';
		foreach ( array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ) as $day ) {
			echo '<label><input type="checkbox" class="restaurant-delivery-day" value="' . esc_attr( $day ) . '"> ' . esc_html( ucfirst( $day ) ) . '</label>';
		}
		echo '</div>';
		echo '<p class="form-row form-row-wide restaurant-delivery-time"><label>' . esc_html__( 'Preferred Time Slot', 'restaurant-food-services' ) . '</label><select name="delivery_time" class="restaurant-wizard-input input-text"><option value="morning">' . esc_html__( 'Morning', 'restaurant-food-services' ) . '</option><option value="afternoon">' . esc_html__( 'Afternoon', 'restaurant-food-services' ) . '</option><option value="evening">' . esc_html__( 'Evening', 'restaurant-food-services' ) . '</option></select></p>';
		echo '</section>';

		echo '<section class="restaurant-wizard-panel" data-step="5">';
		echo '<h3>' . esc_html__( 'Step 5: Summary', 'restaurant-food-services' ) . '</h3>';
		echo '<div class="restaurant-wizard-summary"></div>';
		echo '<button type="button" class="button button-primary restaurant-wizard-submit">' . esc_html__( 'Submit Meal Plan', 'restaurant-food-services' ) . '</button>';
		echo '</section>';
		echo '</div>';

		echo '<div class="restaurant-wizard-actions">';
		echo '<button type="button" class="button restaurant-wizard-prev" disabled>' . esc_html__( 'Back', 'restaurant-food-services' ) . '</button>';
		echo '<button type="button" class="button button-primary restaurant-wizard-next">' . esc_html__( 'Next', 'restaurant-food-services' ) . '</button>';
		echo '</div>';
		echo '<aside class="restaurant-summary-sidebar" data-summary-sidebar="meal-plans">';
		echo '<h4>' . esc_html__( 'Summary Sidebar', 'restaurant-food-services' ) . '</h4>';
		echo '<ul class="restaurant-summary-sidebar__items"></ul>';
		echo '<p class="restaurant-summary-sidebar__total"><strong>' . esc_html__( 'Total Price:', 'restaurant-food-services' ) . '</strong> <span class="restaurant-summary-sidebar__total-value">' . wp_kses_post( wc_price( 0 ) ) . '</span></p>';
		echo '</aside>';
		echo '</div>';
	}

	/**
	 * Returns meal products for the wizard meal selection step.
	 *
	 * @return array<int,\WC_Product>
	 */
	protected function get_meal_choice_products() {
		if ( ! class_exists( '\\WC_Product_Query' ) ) {
			return array();
		}

		$query = new \WC_Product_Query(
			array(
				'status'  => 'publish',
				'limit'   => 24,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'     => 'is_meal_plan',
						'value'   => 'yes',
						'compare' => '!=',
					),
					array(
						'key'     => 'is_meal_plan',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$products = $query->get_products();

		return array_values(
			array_filter(
				$products,
				static function ( $product ) {
					return $product instanceof \WC_Product && $product->is_purchasable() && $product->is_in_stock();
				}
			)
		);
	}

	/**
	 * Returns available WooCommerce products for catering menu selection.
	 *
	 * @return array<int,\WC_Product>
	 */
	protected function get_catering_menu_products() {
		if ( ! class_exists( '\\WC_Product_Query' ) ) {
			return array();
		}

		$query = new \WC_Product_Query(
			array(
				'status'  => 'publish',
				'limit'   => 24,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
			)
		);

		return array_values(
			array_filter(
				$query->get_products(),
				static function ( $product ) {
					return $product instanceof \WC_Product && $product->is_purchasable() && $product->is_in_stock();
				}
			)
		);
	}

	/**
	 * Returns supported catering offerings.
	 *
	 * @return array<string,string>
	 */
	protected function get_catering_offering_options() {
		return $this->normalize_catering_options_map( get_option( 'restaurant_catering_offering_options', array() ) );
	}

	/**
	 * Returns supported catering service options.
	 *
	 * @return array<string,string>
	 */
	protected function get_catering_service_options() {
		return $this->normalize_catering_options_map( get_option( 'restaurant_catering_service_options', array() ) );
	}

	/**
	 * Normalizes a saved catering options payload into value => label map.
	 *
	 * @param mixed $options Raw options payload.
	 *
	 * @return array<string,string>
	 */
	protected function normalize_catering_options_map( $options ) {
		if ( ! is_array( $options ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $options as $value => $label ) {
			if ( is_array( $label ) ) {
				$candidate_value = isset( $label['value'] ) ? $label['value'] : ( isset( $label['key'] ) ? $label['key'] : '' );
				$candidate_label = isset( $label['label'] ) ? $label['label'] : '';
			} else {
				$candidate_value = $value;
				$candidate_label = $label;
			}

			$candidate_value = sanitize_title( (string) $candidate_value );
			$candidate_label = sanitize_text_field( (string) $candidate_label );

			if ( '' === $candidate_value || '' === $candidate_label ) {
				continue;
			}

			$normalized[ $candidate_value ] = $candidate_label;
		}

		return $normalized;
	}

	/**
	 * Builds structured catering items from posted quantity map.
	 *
	 * @param array<string,mixed> $menu_quantities Menu quantities keyed by product ID.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function build_structured_catering_items( $menu_quantities ) {
		$structured = array();

		foreach ( (array) $menu_quantities as $product_id => $quantity ) {
			$product_id = absint( $product_id );
			$quantity   = absint( $quantity );

			if ( $product_id <= 0 || $quantity <= 0 ) {
				continue;
			}

			$product = wc_get_product( $product_id );

			if ( ! $product || ! $product->is_purchasable() ) {
				continue;
			}

			$structured[] = array(
				'product_id'   => $product_id,
				'product_name' => $product->get_name(),
				'quantity'     => $quantity,
				'price'        => (float) $product->get_price(),
			);
		}

		return $structured;
	}

	/**
	 * Calculates total cost for structured catering items.
	 *
	 * @param array<int,array<string,mixed>> $structured_items Structured menu item array.
	 *
	 * @return float
	 */
	protected function calculate_catering_total( $structured_items ) {
		$total = 0.0;

		foreach ( $structured_items as $item ) {
			$price    = isset( $item['price'] ) ? (float) $item['price'] : 0.0;
			$quantity = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			$total   += $price * $quantity;
		}

		return $total;
	}

	/**
	 * Returns configurable catering service fee percent.
	 *
	 * @return float
	 */
	protected function get_catering_service_fee_percent() {
		$percent = (float) get_option( 'restaurant_catering_service_fee_percent', 10 );

		if ( $percent < 0 ) {
			$percent = 0;
		}

		return (float) apply_filters( 'restaurant_food_services_catering_service_fee_percent', $percent );
	}

	/**
	 * Builds a full pricing breakdown for catering selections.
	 *
	 * @param array<int,array<string,mixed>> $structured_items Structured menu item array.
	 *
	 * @return array<string,float>
	 */
	protected function calculate_catering_pricing_details( $structured_items ) {
		$subtotal            = $this->calculate_catering_total( $structured_items );
		$service_fee_percent = $this->get_catering_service_fee_percent();
		$service_fee_amount  = round( $subtotal * ( $service_fee_percent / 100 ), wc_get_price_decimals() );
		$total               = round( $subtotal + $service_fee_amount, wc_get_price_decimals() );

		return array(
			'subtotal'            => (float) $subtotal,
			'service_fee_percent' => (float) $service_fee_percent,
			'service_fee_amount'  => (float) $service_fee_amount,
			'total'               => (float) $total,
		);
	}

	/**
	 * AJAX handler: Saves catering wizard draft in WooCommerce session.
	 *
	 * @return void
	 */
	public function ajax_save_catering_draft() {
		if ( ! check_ajax_referer( 'restaurant_catering_wizard_draft', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'restaurant-food-services' ) ), 403 );
		}

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unable to save draft right now.', 'restaurant-food-services' ) ), 500 );
		}

		$menu_quantities = isset( $_POST['menu_quantities'] ) ? (array) wp_unslash( $_POST['menu_quantities'] ) : array();
		$serving_style   = isset( $_POST['serving_style'] ) ? sanitize_text_field( wp_unslash( $_POST['serving_style'] ) ) : '';
		$dietary_requirements = isset( $_POST['dietary_requirements'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dietary_requirements'] ) ) : '';
		if ( '' === $dietary_requirements && isset( $_POST['dietary_needs'] ) ) {
			$dietary_requirements = sanitize_textarea_field( wp_unslash( $_POST['dietary_needs'] ) );
		}

		$draft = array(
			'event_type'       => isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : '',
			'event_date'       => isset( $_POST['event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_date'] ) ) : '',
			'guest_count'      => isset( $_POST['guest_count'] ) ? absint( wp_unslash( $_POST['guest_count'] ) ) : 0,
			'location'         => isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '',
			'serving_style'    => $serving_style,
			'menu_quantities'  => array_map( 'absint', $menu_quantities ),
			'special_requests' => isset( $_POST['special_requests'] ) ? sanitize_textarea_field( wp_unslash( $_POST['special_requests'] ) ) : '',
			'dietary_requirements' => $dietary_requirements,
			'dietary_needs'    => $dietary_requirements,
		);

		WC()->session->set( 'restaurant_catering_wizard_draft', $draft );

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Draft saved.', 'restaurant-food-services' ),
			)
		);
	}

	/**
	 * AJAX handler: Calculates catering price preview.
	 *
	 * @return void
	 */
	public function ajax_calculate_catering_price() {
		if ( ! check_ajax_referer( 'restaurant_catering_price_preview', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'restaurant-food-services' ) ), 403 );
		}

		$menu_quantities = isset( $_POST['menu_quantities'] ) ? (array) wp_unslash( $_POST['menu_quantities'] ) : array();
		$structured_items = $this->build_structured_catering_items( $menu_quantities );
		$pricing          = $this->calculate_catering_pricing_details( $structured_items );

		wp_send_json_success(
			array(
				'subtotal'            => $pricing['subtotal'],
				'service_fee_percent' => $pricing['service_fee_percent'],
				'service_fee_amount'  => $pricing['service_fee_amount'],
				'total'               => $pricing['total'],
				'subtotal_html'       => wc_price( $pricing['subtotal'] ),
				'service_fee_html'    => wc_price( $pricing['service_fee_amount'] ),
				'total_html'          => wc_price( $pricing['total'] ),
			)
		);
	}

	/**
	 * AJAX handler: Final submission for the catering wizard.
	 *
	 * @return void
	 */
	public function ajax_submit_catering_request() {
		if ( ! check_ajax_referer( 'restaurant_catering_wizard_submit', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'restaurant-food-services' ) ), 403 );
		}

		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please log in to submit a catering request.', 'restaurant-food-services' ) ), 401 );
		}

		$event_type  = isset( $_POST['event_type'] ) ? sanitize_text_field( wp_unslash( $_POST['event_type'] ) ) : '';
		$event_date  = isset( $_POST['event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_date'] ) ) : '';
		$guest_count = isset( $_POST['guest_count'] ) ? absint( wp_unslash( $_POST['guest_count'] ) ) : 0;
		$location    = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
		$serving_style = isset( $_POST['serving_style'] ) ? sanitize_text_field( wp_unslash( $_POST['serving_style'] ) ) : '';
		$menu_quantities = isset( $_POST['menu_quantities'] ) ? (array) wp_unslash( $_POST['menu_quantities'] ) : array();
		$special_requests = isset( $_POST['special_requests'] ) ? sanitize_textarea_field( wp_unslash( $_POST['special_requests'] ) ) : '';
		$dietary_requirements = isset( $_POST['dietary_requirements'] ) ? sanitize_textarea_field( wp_unslash( $_POST['dietary_requirements'] ) ) : '';
		if ( '' === $dietary_requirements && isset( $_POST['dietary_needs'] ) ) {
			$dietary_requirements = sanitize_textarea_field( wp_unslash( $_POST['dietary_needs'] ) );
		}

		$allowed_event_types  = array_keys( $this->get_catering_offering_options() );
		$allowed_service_opts = array_keys( $this->get_catering_service_options() );

		if ( ! in_array( $event_type, $allowed_event_types, true ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please select a valid catering offering.', 'restaurant-food-services' ) ), 400 );
		}

		if ( 'packed meals' === strtolower( $serving_style ) ) {
			$serving_style = 'packed_meals';
		}

		if ( ! in_array( $serving_style, $allowed_service_opts, true ) ) {
			$serving_style = '';
		}

		if ( '' === $event_date || $guest_count <= 0 || '' === $location ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please complete all event details.', 'restaurant-food-services' ) ), 400 );
		}

		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $event_date ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please provide a valid event date.', 'restaurant-food-services' ) ), 400 );
		}

		$structured_items = $this->build_structured_catering_items( $menu_quantities );

		if ( empty( $structured_items ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please select at least one menu item.', 'restaurant-food-services' ) ), 400 );
		}

		$pricing     = $this->calculate_catering_pricing_details( $structured_items );
		$total_price = $pricing['total'];

		if ( ! post_type_exists( 'catering_request' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Catering request type is unavailable.', 'restaurant-food-services' ) ), 500 );
		}

		$selected_items = array();
		$quantities     = array();

		foreach ( $structured_items as $item ) {
			$product_id = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
			$quantity   = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;

			if ( $product_id <= 0 || $quantity <= 0 ) {
				continue;
			}

			$selected_items[]         = $product_id;
			$quantities[ $product_id ] = $quantity;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'catering_request',
				'post_status' => 'publish',
				'post_title'  => sprintf( 'Catering Request - %s - %s', sanitize_text_field( $location ), sanitize_text_field( $event_date ) ),
				'post_author' => $user_id > 0 ? $user_id : 0,
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Failed to submit catering request.', 'restaurant-food-services' ) ), 500 );
		}

		update_post_meta( $post_id, 'event_type', $event_type );
		update_post_meta( $post_id, 'event_date', $event_date );
		update_post_meta( $post_id, 'guest_count', $guest_count );
		update_post_meta( $post_id, 'location', $location );
		update_post_meta(
			$post_id,
			'event_details',
			wp_json_encode(
				array(
					'event_type'  => $event_type,
					'event_date'  => $event_date,
					'guest_count' => $guest_count,
					'location'    => $location,
				)
			)
		);
		update_post_meta( $post_id, 'special_requests', $special_requests );
		update_post_meta( $post_id, 'serving_style', $serving_style );
		update_post_meta( $post_id, 'dietary_requirements', $dietary_requirements );
		update_post_meta( $post_id, 'dietary_needs', $dietary_requirements );
		update_post_meta( $post_id, 'menu_items', wp_json_encode( $structured_items ) );
		update_post_meta( $post_id, 'selected_items', wp_json_encode( $selected_items ) );
		update_post_meta( $post_id, 'quantities', wp_json_encode( $quantities ) );
		update_post_meta( $post_id, 'user_id', $user_id > 0 ? $user_id : 0 );
		update_post_meta( $post_id, 'service_fee_percent', $pricing['service_fee_percent'] );
		update_post_meta( $post_id, 'service_fee_amount', $pricing['service_fee_amount'] );
		update_post_meta( $post_id, 'subtotal_price', $pricing['subtotal'] );
		update_post_meta( $post_id, 'total_price', $total_price );
		update_post_meta( $post_id, 'catering_status', 'pending' );

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( 'restaurant_catering_wizard_draft' );
		}

		do_action( 'restaurant_food_services_catering_request_submitted', (int) $post_id );

		wp_send_json_success(
			array(
				'message'    => esc_html__( 'Catering request submitted successfully.', 'restaurant-food-services' ),
				'request_id' => (int) $post_id,
			)
		);
	}

	/**
	 * AJAX handler: Submits meal plan wizard selections.
	 *
	 * @return void
	 */
	public function ajax_submit_meal_plan_selection() {
		if ( ! check_ajax_referer( 'restaurant_meal_plan_wizard_submit', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'restaurant-food-services' ) ), 403 );
		}

		$plan_type      = isset( $_POST['plan_type'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_type'] ) ) : '';
		$meals_per_week = isset( $_POST['meals_per_week'] ) ? absint( wp_unslash( $_POST['meals_per_week'] ) ) : 0;
		$selected_meals = isset( $_POST['selected_meals'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['selected_meals'] ) ) : array();
		$delivery_days  = isset( $_POST['delivery_days'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['delivery_days'] ) ) : array();
		$delivery_time  = isset( $_POST['delivery_time'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_time'] ) ) : 'morning';

		if ( ! in_array( $plan_type, array( 'individual', 'family' ), true ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please choose a valid plan type.', 'restaurant-food-services' ) ), 400 );
		}

		if ( $meals_per_week <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please select meals per week.', 'restaurant-food-services' ) ), 400 );
		}

		if ( empty( $selected_meals ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please choose at least one meal.', 'restaurant-food-services' ) ), 400 );
		}

		$allowed_days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$delivery_days = array_values( array_intersect( $allowed_days, $delivery_days ) );

		if ( empty( $delivery_days ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please select delivery days.', 'restaurant-food-services' ) ), 400 );
		}

		if ( ! in_array( $delivery_time, array( 'morning', 'afternoon', 'evening' ), true ) ) {
			$delivery_time = 'morning';
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
			wp_send_json_error( array( 'message' => esc_html__( 'WooCommerce is unavailable right now.', 'restaurant-food-services' ) ), 500 );
		}

		$valid_products = array();

		foreach ( $selected_meals as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				continue;
			}

			$valid_products[] = (int) $product_id;
		}

		if ( empty( $valid_products ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Selected meals are unavailable.', 'restaurant-food-services' ) ), 400 );
		}

		foreach ( $valid_products as $product_id ) {
			WC()->cart->add_to_cart( $product_id, 1 );
		}

		WC()->session->set(
			'restaurant_meal_plan_selection',
			array(
				'plan_type'      => $plan_type,
				'meals_per_week' => $meals_per_week,
				'selected_meals' => $valid_products,
				'delivery_days'  => $delivery_days,
				'delivery_time'  => $delivery_time,
			)
		);

		wp_send_json_success(
			array(
				'message'      => esc_html__( 'Meal plan added to cart. Complete checkout to activate subscription.', 'restaurant-food-services' ),
				'cart_count'   => (int) WC()->cart->get_cart_contents_count(),
				'checkout_url' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
			)
		);
	}

	/**
	 * Attaches meal plan wizard selections to WooCommerce order meta during checkout.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 *
	 * @return void
	 */
	public function attach_meal_plan_selection_to_order( $order ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session || ! $order instanceof \WC_Order ) {
			return;
		}

		$selection = WC()->session->get( 'restaurant_meal_plan_selection' );

		if ( ! is_array( $selection ) || empty( $selection ) ) {
			return;
		}

		$order->update_meta_data( '_restaurant_meal_plan_selection', wp_json_encode( $selection ) );
		$order->update_meta_data( 'selected_meals', wp_json_encode( isset( $selection['selected_meals'] ) ? (array) $selection['selected_meals'] : array() ) );
		$order->update_meta_data( 'delivery_days', wp_json_encode( isset( $selection['delivery_days'] ) ? (array) $selection['delivery_days'] : array() ) );
		$order->update_meta_data( 'meals_per_week', isset( $selection['meals_per_week'] ) ? absint( $selection['meals_per_week'] ) : 0 );
		$order->update_meta_data( 'plan_type', isset( $selection['plan_type'] ) ? sanitize_text_field( $selection['plan_type'] ) : 'individual' );

		WC()->session->__unset( 'restaurant_meal_plan_selection' );
	}

	/**
	 * Forwards WooCommerce add-to-cart events for plugin integrations.
	 *
	 * @param string $cart_item_key Added cart item key.
	 *
	 * @return void
	 */
	public function handle_woocommerce_add_to_cart( $cart_item_key ) {
		do_action( 'restaurant_food_services_woocommerce_add_to_cart', $cart_item_key );
	}


	/**
	 * Renders meal products grid for [restaurant_order_meals].
	 *
	 * @return void
	 */
	public function render_order_meals_ui() {
		if ( ! class_exists( '\\WC_Product_Query' ) ) {
			echo '<p>' . esc_html__( 'Meals are unavailable right now.', 'restaurant-food-services' ) . '</p>';
			return;
		}

		$query = new \WC_Product_Query(
			array(
				'status'     => 'publish',
				'limit'      => 12,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'return'     => 'objects',
				'meta_query' => array(
					array(
						'key'     => 'is_meal_plan',
						'value'   => 'yes',
						'compare' => '=',
					),
				),
			)
		);

		$products = $query->get_products();

		if ( empty( $products ) ) {
			echo '<p>' . esc_html__( 'No meal products found.', 'restaurant-food-services' ) . '</p>';
			return;
		}

		echo '<div class="restaurant-filters" data-filter-scope="order-meals">';
		echo '<button type="button" class="button restaurant-filter-toggle" aria-expanded="false">' . esc_html__( 'Filters', 'restaurant-food-services' ) . '</button>';
		echo '<div class="restaurant-filters__body">';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Search Meals', 'restaurant-food-services' ) . '</label><input type="search" class="restaurant-wizard-input input-text" data-filter-search></p>';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Spice Level', 'restaurant-food-services' ) . '</label><select class="restaurant-wizard-input input-text" data-filter-spice><option value="">' . esc_html__( 'All', 'restaurant-food-services' ) . '</option><option value="mild">' . esc_html__( 'Mild', 'restaurant-food-services' ) . '</option><option value="medium">' . esc_html__( 'Medium', 'restaurant-food-services' ) . '</option><option value="hot">' . esc_html__( 'Hot', 'restaurant-food-services' ) . '</option><option value="extra">' . esc_html__( 'Extra Hot', 'restaurant-food-services' ) . '</option></select></p>';
		echo '</div>';
		echo '</div>';

		echo '<div class="restaurant-meals-grid" role="list">';

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$product_id  = $product->get_id();
			$spice_level = sanitize_text_field( (string) get_post_meta( $product_id, 'spice_level', true ) );
			$spice_label = '' !== $spice_level ? ucfirst( $spice_level ) : __( 'Not specified', 'restaurant-food-services' );

			echo '<article class="restaurant-meal-card" role="listitem" data-product-name="' . esc_attr( strtolower( $product->get_name() ) ) . '" data-spice-level="' . esc_attr( strtolower( $spice_level ) ) . '">';
			echo '<a class="restaurant-meal-card__image-link" href="' . esc_url( $product->get_permalink() ) . '">';
			echo $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'restaurant-meal-card__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</a>';
			echo '<div class="restaurant-meal-card__content">';
			echo '<h3 class="restaurant-meal-card__title"><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h3>';
			echo '<div class="restaurant-meal-card__price">' . wp_kses_post( $product->get_price_html() ) . '</div>';
			echo '<p class="restaurant-meal-card__spice"><strong>' . esc_html__( 'Spice Level:', 'restaurant-food-services' ) . '</strong> ' . esc_html( $spice_label ) . '</p>';
			echo '<div class="restaurant-meal-card__actions">' . $this->get_add_to_cart_button_html( $product ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '</div>';
			echo '</article>';
		}

		echo '</div>';
	}

	/**
	 * Builds WooCommerce-compatible add-to-cart button HTML.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return string
	 */
	protected function get_add_to_cart_button_html( $product ) {
		if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return '<span class="button disabled" aria-disabled="true">' . esc_html__( 'Unavailable', 'restaurant-food-services' ) . '</span>';
		}

		$product_type = $product->get_type();
		$classes      = array( 'button', 'product_type_' . $product_type, 'restaurant-ajax-add-to-cart' );

		$attributes = array(
			'href'            => '#',
			'data-product_id' => $product->get_id(),
			'data-product_sku'=> $product->get_sku(),
			'data-quantity'   => '1',
			'data-product_type'=> $product_type,
			'class'           => implode( ' ', array_map( 'sanitize_html_class', $classes ) ),
			'rel'             => 'nofollow',
		);

		$button_label = $product->add_to_cart_text();
		$attr_html    = '';

		foreach ( $attributes as $key => $value ) {
			$attr_html .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( (string) $value ) );
		}

		return sprintf( '<a%s>%s</a>', $attr_html, esc_html( $button_label ) );
	}

	/**
	 * AJAX handler: Adds a meal product to WooCommerce cart.
	 *
	 * @return void
	 */
	public function ajax_add_meal_to_cart() {
		if ( ! check_ajax_referer( 'restaurant_add_meal_to_cart', 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Security check failed.', 'restaurant-food-services' ),
				),
				403
			);
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Cart is unavailable right now.', 'restaurant-food-services' ),
				),
				500
			);
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? max( 1, absint( wp_unslash( $_POST['quantity'] ) ) ) : 1;

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'This meal cannot be added to cart.', 'restaurant-food-services' ),
				),
				400
			);
		}

		$added = WC()->cart->add_to_cart( $product_id, $quantity );

		if ( ! $added ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Unable to add meal to cart.', 'restaurant-food-services' ),
				),
				400
			);
		}

		wp_send_json_success(
			array(
				'cart_count' => (int) WC()->cart->get_cart_contents_count(),
				'message'    => esc_html__( 'Meal added to cart.', 'restaurant-food-services' ),
			)
		);
	}

	/**
	 * Registers frontend shortcodes for public entry pages.
	 *
	 * @return void
	 */
	public function register_shortcodes() {
		add_shortcode( 'restaurant_order_meals', array( $this, 'render_order_meals_shortcode' ) );
		add_shortcode( 'restaurant_meal_plans', array( $this, 'render_meal_plans_shortcode' ) );
		add_shortcode( 'restaurant_catering', array( $this, 'render_catering_shortcode' ) );
	}

	/**
	 * Renders [restaurant_order_meals].
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_order_meals_shortcode( $atts ) {
		return $this->order_meals_page->render( $atts );
	}

	/**
	 * Renders [restaurant_meal_plans].
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_meal_plans_shortcode( $atts ) {
		return $this->meal_plans_page->render( $atts );
	}

	/**
	 * Renders [restaurant_catering].
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_catering_shortcode( $atts ) {
		return $this->catering_page->render( $atts );
	}
}

