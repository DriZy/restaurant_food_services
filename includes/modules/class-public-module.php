<?php
/**
 * Public frontend module.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Modules;

use Restaurant\FoodServices\Loader;
use Restaurant\FoodServices\Frontend\Catering_Page;
use Restaurant\FoodServices\Frontend\Meal_Plans_Page;
use Restaurant\FoodServices\Frontend\Order_Meals_Page;

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
		$loader->add_action( 'init', $this, 'register_editor_blocks' );
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_public_assets' );
		$loader->add_action( 'restaurant_food_services_render_order_meals_ui', $this, 'render_order_meals_ui' );
		$loader->add_action( 'restaurant_food_services_render_meal_plans_ui', $this, 'render_meal_plans_wizard_ui' );
		$loader->add_action( 'restaurant_food_services_render_catering_ui', $this, 'render_catering_wizard_ui' );
		$loader->add_action( 'wp_ajax_restaurant_add_meal_to_cart', $this, 'ajax_add_meal_to_cart' );
		$loader->add_action( 'wp_ajax_nopriv_restaurant_add_meal_to_cart', $this, 'ajax_add_meal_to_cart' );
		$loader->add_action( 'wp_ajax_restaurant_submit_meal_plan_selection', $this, 'ajax_submit_meal_plan_selection' );
		$loader->add_action( 'wp_ajax_nopriv_restaurant_submit_meal_plan_selection', $this, 'ajax_submit_meal_plan_selection' );
		$loader->add_action( 'wp_ajax_restaurant_location_autocomplete', $this, 'ajax_location_autocomplete' );
		$loader->add_action( 'wp_ajax_nopriv_restaurant_location_autocomplete', $this, 'ajax_location_autocomplete' );
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
		$catering_draft_payload = $this->get_requested_catering_draft_payload();

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
				'locationSearchNonce'  => wp_create_nonce( 'restaurant_location_autocomplete' ),
				'cateringDraftNonce'   => wp_create_nonce( 'restaurant_catering_wizard_draft' ),
				'cateringSubmitNonce'  => wp_create_nonce( 'restaurant_catering_wizard_submit' ),
				'cateringPriceNonce'   => wp_create_nonce( 'restaurant_catering_price_preview' ),
				'cateringDraftPayload' => $catering_draft_payload,
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
	 * Returns saved draft payload requested from account hub.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_requested_catering_draft_payload() {
		if ( ! is_user_logged_in() || ! isset( $_GET['restaurant_draft_id'] ) ) {
			return array();
		}

		$draft_id = absint( wp_unslash( $_GET['restaurant_draft_id'] ) );

		if ( $draft_id <= 0 ) {
			return array();
		}

		$draft = get_post( $draft_id );

		if ( ! $draft || 'catering_request' !== $draft->post_type || (int) $draft->post_author !== get_current_user_id() || 'draft' !== $draft->post_status ) {
			return array();
		}

		$raw_payload = get_post_meta( $draft_id, '_restaurant_catering_draft_data', true );
		$payload     = is_string( $raw_payload ) ? json_decode( $raw_payload, true ) : array();

		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$menu_quantities = isset( $payload['menu_quantities'] ) && is_array( $payload['menu_quantities'] ) ? $payload['menu_quantities'] : array();

		if ( empty( $menu_quantities ) ) {
			$menu_meta = get_post_meta( $draft_id, 'menu_items', true );
			$decoded   = is_string( $menu_meta ) ? json_decode( $menu_meta, true ) : array();
			$menu_quantities = is_array( $decoded ) ? $decoded : array();
		}

		return array(
			'draftId'            => $draft_id,
			'eventType'          => isset( $payload['event_type'] ) ? sanitize_text_field( $payload['event_type'] ) : sanitize_text_field( (string) get_post_meta( $draft_id, 'event_type', true ) ),
			'eventDate'          => isset( $payload['event_date'] ) ? sanitize_text_field( $payload['event_date'] ) : sanitize_text_field( (string) get_post_meta( $draft_id, 'event_date', true ) ),
			'guestCount'         => isset( $payload['guest_count'] ) ? absint( $payload['guest_count'] ) : absint( get_post_meta( $draft_id, 'guest_count', true ) ),
			'location'           => isset( $payload['location'] ) ? sanitize_text_field( $payload['location'] ) : sanitize_text_field( (string) get_post_meta( $draft_id, 'location', true ) ),
			'servingStyle'       => isset( $payload['serving_style'] ) ? sanitize_text_field( $payload['serving_style'] ) : sanitize_text_field( (string) get_post_meta( $draft_id, 'serving_style', true ) ),
			'customDescription'  => isset( $payload['custom_description'] ) ? sanitize_textarea_field( $payload['custom_description'] ) : sanitize_textarea_field( (string) get_post_meta( $draft_id, 'custom_description', true ) ),
			'specialRequests'    => isset( $payload['special_requests'] ) ? sanitize_textarea_field( $payload['special_requests'] ) : sanitize_textarea_field( (string) get_post_meta( $draft_id, 'special_requests', true ) ),
			'dietaryNeeds'       => isset( $payload['dietary_requirements'] ) ? sanitize_textarea_field( $payload['dietary_requirements'] ) : sanitize_textarea_field( (string) get_post_meta( $draft_id, 'dietary_requirements', true ) ),
			'menuQuantities'     => array_map( 'absint', (array) $menu_quantities ),
		);
	}

	/**
	 * Renders the catering multi-step wizard for [restaurant_catering].
	 *
	 * @return void
	 */
	public function render_catering_wizard_ui() {
		$products = $this->get_catering_menu_products();
		$products_by_course = $this->group_catering_products_by_meal_course( $products );
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
		echo '<h3>' . esc_html__( 'Step 2: Select Menu Items or Describe What You Want', 'restaurant-food-services' ) . '</h3>';
		
		// Standard menu list section
		echo '<div class="restaurant-catering-menu-section" data-menu-section="list">';
		echo '<div class="restaurant-filters" data-filter-scope="catering">';
		echo '<button type="button" class="button restaurant-filter-toggle" aria-expanded="false">' . esc_html__( 'Filters', 'restaurant-food-services' ) . '</button>';
		echo '<div class="restaurant-filters__body">';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Search Items', 'restaurant-food-services' ) . '</label><input type="search" class="restaurant-wizard-input input-text" data-filter-search></p>';
		echo '</div>';
		echo '</div>';
		echo '<div class="restaurant-catering-menu-list" role="list">';
		if ( empty( $products_by_course ) ) {
			echo '<p>' . esc_html__( 'No menu items available right now.', 'restaurant-food-services' ) . '</p>';
		} else {
			foreach ( $products_by_course as $course_group ) {
				echo '<section class="restaurant-meal-course-group" data-course-group="' . esc_attr( sanitize_title( $course_group['label'] ) ) . '">';
				echo '<h4 class="restaurant-meal-course-heading">' . esc_html( $course_group['label'] ) . '</h4>';
				echo '<div class="restaurant-meal-course-items">';

				foreach ( $course_group['products'] as $product ) {
					$product_id    = (int) $product->get_id();
					$quantity_id   = 'restaurant-catering-qty-' . $product_id;
					$product_name  = $product->get_name();
					$product_link  = $product->get_permalink();
					$spice_level   = sanitize_text_field( (string) get_post_meta( $product_id, 'spice_level', true ) );
					$spice_label   = '' !== $spice_level ? ucfirst( $spice_level ) : esc_html__( 'Not specified', 'restaurant-food-services' );

					echo '<article class="restaurant-meal-card restaurant-catering-meal-row restaurant-meal-row" role="listitem" data-product-name="' . esc_attr( strtolower( $product_name ) ) . '">';
					echo '<div class="restaurant-meal-row__left">';
					echo '<a class="restaurant-meal-card__image-link restaurant-meal-row__image" href="' . esc_url( $product_link ) . '">';
					echo $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'restaurant-meal-card__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '</a>';
					echo '<div class="restaurant-meal-card__content restaurant-meal-row__content">';
					echo '<h5 class="restaurant-meal-card__title"><a href="' . esc_url( $product_link ) . '">' . esc_html( $product_name ) . '</a></h5>';
					echo '<div class="restaurant-meal-card__price">' . wp_kses_post( $product->get_price_html() ) . '</div>';
					echo '<p class="restaurant-meal-card__spice"><strong>' . esc_html__( 'Spice Level:', 'restaurant-food-services' ) . '</strong> ' . esc_html( $spice_label ) . '</p>';
					echo '</div>';
					echo '</div>';
					echo '<div class="restaurant-meal-row__right">';
					echo '<p class="form-row form-row-wide restaurant-menu-qty">';
					echo '<label class="restaurant-catering-qty-label" for="' . esc_attr( $quantity_id ) . '">' . esc_html__( 'Quantity :', 'restaurant-food-services' ) . '</label>';
					echo '<input id="' . esc_attr( $quantity_id ) . '" type="number" min="0" value="0" class="restaurant-wizard-input input-text restaurant-menu-quantity" data-product-id="' . esc_attr( (string) $product_id ) . '" data-product-name="' . esc_attr( $product_name ) . '" />';
					echo '</p>';
					echo '</div>';
					echo '</article>';
				}

				echo '</div>';
				echo '</section>';
			}
		}
		echo '</div>';
		echo '</div>';
		
		// Custom description section
		echo '<div class="restaurant-catering-menu-section" data-menu-section="custom" style="display: none;">';
		echo '<p class="form-row form-row-wide"><label for="restaurant-catering-custom-description">' . esc_html__( 'Describe What You Want', 'restaurant-food-services' ) . '</label>';
		echo '<textarea id="restaurant-catering-custom-description" name="custom_description" class="restaurant-wizard-input input-text" rows="6" placeholder="' . esc_attr__( 'Please describe your custom menu requirements, dietary restrictions, and any specific dishes or cuisines you\'d like us to consider...', 'restaurant-food-services' ) . '"></textarea></p>';
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
		echo '<div class="restaurant-wizard-submission-status" style="display: none; margin: 15px 0; padding: 12px; border-radius: 4px; border-left: 4px solid #0073aa;">';
		echo '<span class="restaurant-wizard-submission-spinner" style="display: inline-block; width: 16px; height: 16px; border: 2px solid #f3f3f3; border-top: 2px solid #0073aa; border-radius: 50%; animation: restaurant-spin 0.8s linear infinite; margin-right: 10px; vertical-align: middle;"></span>';
		echo '<span class="restaurant-wizard-submission-message"></span>';
		echo '</div>';
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
		$window        = $this->get_meal_plan_subscription_window();
		$is_open       = $this->is_meal_plan_subscription_window_open();
		$disabled_attr = $is_open ? '' : ' disabled="disabled" aria-disabled="true"';

		echo '<div class="restaurant-meal-plans-wizard woocommerce" data-step="1" data-subscriptions-open="' . ( $is_open ? '1' : '0' ) . '">';

		if ( ! $is_open ) {
			echo '<div class="restaurant-wizard-notice restaurant-wizard-notice--closed">';
			echo '<p><strong>' . esc_html__( 'Meal plans for this week are closed. Please subscribe for next week.', 'restaurant-food-services' ) . '</strong></p>';
			echo '<p>' . sprintf( esc_html__( 'Subscription window: %1$s to %2$s', 'restaurant-food-services' ), esc_html( $window['start'] ), esc_html( $window['end'] ) ) . '</p>';
			echo '</div>';
		}

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
		echo '<p class="restaurant-wizard-help">' . esc_html__( 'Enter quantity for each meal you want in your weekly plan.', 'restaurant-food-services' ) . '</p>';
		echo '<div class="restaurant-filters" data-filter-scope="meal-plans">';
		echo '<button type="button" class="button restaurant-filter-toggle" aria-expanded="false">' . esc_html__( 'Filters', 'restaurant-food-services' ) . '</button>';
		echo '<div class="restaurant-filters__body">';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Search Meals', 'restaurant-food-services' ) . '</label><input type="search" class="restaurant-wizard-input input-text" data-filter-search></p>';
		echo '<p class="form-row form-row-wide"><label>' . esc_html__( 'Spice Level', 'restaurant-food-services' ) . '</label><select class="restaurant-wizard-input input-text" data-filter-spice><option value="">' . esc_html__( 'All', 'restaurant-food-services' ) . '</option><option value="mild">' . esc_html__( 'Mild', 'restaurant-food-services' ) . '</option><option value="medium">' . esc_html__( 'Medium', 'restaurant-food-services' ) . '</option><option value="hot">' . esc_html__( 'Hot', 'restaurant-food-services' ) . '</option><option value="extra">' . esc_html__( 'Extra Hot', 'restaurant-food-services' ) . '</option></select></p>';
		echo '</div>';
		echo '</div>';
		echo '<div class="restaurant-catering-menu-list restaurant-meal-plan-meals-list" role="list">';
		if ( empty( $meal_products ) ) {
			echo '<p>' . esc_html__( 'No meals available right now.', 'restaurant-food-services' ) . '</p>';
		} else {
			foreach ( $meal_products as $product ) {
				$product_id  = $product->get_id();
				$spice_level = sanitize_text_field( (string) get_post_meta( $product_id, 'spice_level', true ) );
				$spice_label = '' !== $spice_level ? ucfirst( $spice_level ) : __( 'Not specified', 'restaurant-food-services' );
				$quantity_id = 'restaurant-meal-plan-qty-' . $product_id;
				echo '<article class="restaurant-meal-card restaurant-meal-row restaurant-meal-plan-row" role="listitem" data-product-name="' . esc_attr( strtolower( $product->get_name() ) ) . '" data-spice-level="' . esc_attr( strtolower( $spice_level ) ) . '">';
				echo '<div class="restaurant-meal-row__left">';
				echo '<a class="restaurant-meal-card__image-link restaurant-meal-row__image" href="' . esc_url( $product->get_permalink() ) . '">';
				echo $product->get_image( 'woocommerce_thumbnail', array( 'class' => 'restaurant-meal-card__image' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '</a>';
				echo '<div class="restaurant-meal-card__content restaurant-meal-row__content">';
				echo '<h4 class="restaurant-meal-card__title"><a href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( $product->get_name() ) . '</a></h4>';
				echo '<div class="restaurant-meal-card__price">' . wp_kses_post( $product->get_price_html() ) . '</div>';
				echo '<p class="restaurant-meal-card__spice"><strong>' . esc_html__( 'Spice Level:', 'restaurant-food-services' ) . '</strong> ' . esc_html( $spice_label ) . '</p>';
				echo '</div>';
				echo '</div>';
				echo '<div class="restaurant-meal-row__right">';
				echo '<p class="form-row form-row-wide restaurant-menu-qty">';
				echo '<label class="restaurant-catering-qty-label" for="' . esc_attr( $quantity_id ) . '">' . esc_html__( 'Quantity:', 'restaurant-food-services' ) . '</label>';
				echo '<input id="' . esc_attr( $quantity_id ) . '" type="number" min="0" value="0" class="restaurant-wizard-input input-text restaurant-meal-plan-quantity" data-product-id="' . esc_attr( (string) $product_id ) . '" data-product-name="' . esc_attr( $product->get_name() ) . '" data-product-price="' . esc_attr( (string) (float) $product->get_price() ) . '" />';
				echo '</p>';
				echo '</div>';
				echo '</article>';
			}
		}
		echo '</div>';
		echo '</section>';

		echo '<section class="restaurant-wizard-panel" data-step="4">';
		echo '<h3>' . esc_html__( 'Step 4: Delivery Preferences', 'restaurant-food-services' ) . '</h3>';
		echo '<p class="form-row form-row-wide restaurant-delivery-location">';
		echo '<label>' . esc_html__( 'Delivery Location', 'restaurant-food-services' ) . '</label>';
		echo '<input type="text" class="restaurant-wizard-input input-text" name="delivery_location" autocomplete="off" required />';
		echo '<input type="hidden" name="delivery_latitude" value="" />';
		echo '<input type="hidden" name="delivery_longitude" value="" />';
		echo '<div class="restaurant-location-suggestions" hidden></div>';
		echo '</p>';
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
		echo '<button type="button" class="button button-primary restaurant-wizard-submit"' . $disabled_attr . '>' . esc_html__( 'Submit Meal Plan', 'restaurant-food-services' ) . '</button>';
		echo '</section>';
		echo '</div>';

		echo '<div class="restaurant-wizard-actions">';
		echo '<button type="button" class="button restaurant-wizard-prev" disabled>' . esc_html__( 'Back', 'restaurant-food-services' ) . '</button>';
		echo '<button type="button" class="button button-primary restaurant-wizard-next"' . $disabled_attr . '>' . esc_html__( 'Next', 'restaurant-food-services' ) . '</button>';
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
		$weekly_menu_ids = $this->get_active_weekly_menu_meal_ids();

		if ( ! empty( $weekly_menu_ids ) ) {
			$weekly_products = array();

			foreach ( $weekly_menu_ids as $product_id ) {
				$product = wc_get_product( $product_id );

				if ( ! $product instanceof \WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
					continue;
				}

				$weekly_products[] = $product;
			}

			if ( ! empty( $weekly_products ) ) {
				return $weekly_products;
			}
		}

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
	 * Returns product IDs from currently active admin weekly menu.
	 *
	 * @return array<int,int>
	 */
	protected function get_active_weekly_menu_meal_ids() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'restaurant_weekly_menus';
		$today      = wp_date( 'Y-m-d' );

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

		if ( ! is_string( $table_exists ) || $table_exists !== $table_name ) {
			return array();
		}

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meals FROM {$table_name} WHERE week_start <= %s AND week_end >= %s ORDER BY week_start DESC LIMIT 1",
				$today,
				$today
			)
		);

		if ( ! $row || ! isset( $row->meals ) ) {
			return array();
		}

		$decoded = json_decode( (string) $row->meals, true );

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $decoded ) ) ) );
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
		$saved_options = $this->normalize_catering_options_map( get_option( 'restaurant_catering_offering_options', array() ) );
		$default_options = $this->get_default_catering_offering_options();

		foreach ( $default_options as $default_key => $default_label ) {
			unset( $saved_options[ $default_key ] );
		}

		return array_merge( $default_options, $saved_options );
	}

	/**
	 * Returns default catering offerings always available to customers.
	 *
	 * @return array<string,string>
	 */
	protected function get_default_catering_offering_options() {
		return array(
			'custom_meal_design' => esc_html__( 'Custom Meal Design', 'restaurant-food-services' ),
		);
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
	 * Returns supported catering meal course options.
	 *
	 * @return array<string,string>
	 */
	protected function get_catering_meal_course_options() {
		return $this->normalize_catering_options_map( get_option( 'restaurant_catering_meal_course_options', array() ) );
	}

	/**
	 * Groups catering products by assigned meal course.
	 *
	 * @param array<int,\WC_Product> $products Product objects.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function group_catering_products_by_meal_course( $products ) {
		$course_options = $this->get_catering_meal_course_options();
		$grouped        = array();

		foreach ( $products as $product ) {
			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$course_key = sanitize_key( (string) get_post_meta( $product->get_id(), 'meal_course', true ) );

			if ( '' === $course_key || ! isset( $course_options[ $course_key ] ) ) {
				$course_key = 'other';
			}

			if ( ! isset( $grouped[ $course_key ] ) ) {
				$grouped[ $course_key ] = array(
					'label'    => 'other' === $course_key ? esc_html__( 'Other Meals', 'restaurant-food-services' ) : $course_options[ $course_key ],
					'products' => array(),
				);
			}

			$grouped[ $course_key ]['products'][] = $product;
		}

		$sorted = array();

		foreach ( $course_options as $course_key => $course_label ) {
			if ( isset( $grouped[ $course_key ] ) ) {
				$sorted[] = $grouped[ $course_key ];
				unset( $grouped[ $course_key ] );
			}
		}

		foreach ( $grouped as $remaining_group ) {
			$sorted[] = $remaining_group;
		}

		return $sorted;
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
		if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid request method.', 'restaurant-food-services' ) ), 405 );
		}

		if ( ! check_ajax_referer( 'restaurant_catering_wizard_draft', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'restaurant-food-services' ) ), 403 );
		}

		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please log in to save a draft.', 'restaurant-food-services' ) ), 401 );
		}

		if ( ! post_type_exists( 'catering_request' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Catering drafts are currently unavailable.', 'restaurant-food-services' ) ), 500 );
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
			'custom_description' => isset( $_POST['custom_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_description'] ) ) : '',
			'special_requests' => isset( $_POST['special_requests'] ) ? sanitize_textarea_field( wp_unslash( $_POST['special_requests'] ) ) : '',
			'dietary_requirements' => $dietary_requirements,
			'dietary_needs'    => $dietary_requirements,
		);

		$draft_id = absint( get_user_meta( $user_id, '_restaurant_catering_draft_id', true ) );
		$draft_post = array(
			'post_type'    => 'catering_request',
			'post_status'  => 'draft',
			'post_author'  => $user_id,
			'post_title'   => sprintf(
				/* translators: %s: human readable draft context. */
				__( 'Catering Draft - %s', 'restaurant-food-services' ),
				! empty( $draft['event_type'] ) ? $draft['event_type'] : __( 'New Request', 'restaurant-food-services' )
			),
			'post_content' => '',
		);

		if ( $draft_id > 0 && get_post( $draft_id ) && (int) get_post_field( 'post_author', $draft_id ) === $user_id ) {
			$draft_post['ID'] = $draft_id;
			$draft_id         = wp_update_post( $draft_post, true );
		} else {
			$draft_id = wp_insert_post( $draft_post, true );
		}

		if ( is_wp_error( $draft_id ) || ! $draft_id ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Unable to save draft right now.', 'restaurant-food-services' ) ), 500 );
		}

		$draft_id = absint( $draft_id );
		update_post_meta( $draft_id, '_restaurant_catering_draft_data', wp_json_encode( $draft ) );
		update_post_meta( $draft_id, 'event_type', $draft['event_type'] );
		update_post_meta( $draft_id, 'event_date', $draft['event_date'] );
		update_post_meta( $draft_id, 'guest_count', $draft['guest_count'] );
		update_post_meta( $draft_id, 'location', $draft['location'] );
		update_post_meta( $draft_id, 'serving_style', $draft['serving_style'] );
		update_post_meta( $draft_id, 'menu_items', wp_json_encode( $draft['menu_quantities'] ) );
		update_post_meta( $draft_id, 'custom_description', $draft['custom_description'] );
		update_post_meta( $draft_id, 'special_requests', $draft['special_requests'] );
		update_post_meta( $draft_id, 'dietary_requirements', $draft['dietary_requirements'] );
		update_post_meta( $draft_id, 'dietary_needs', $draft['dietary_needs'] );
		update_user_meta( $user_id, '_restaurant_catering_draft_id', $draft_id );

		wp_send_json_success(
			array(
				'message' => esc_html__( 'Draft saved.', 'restaurant-food-services' ),
				'draft_id' => $draft_id,
			)
		);
	}

	/**
	 * AJAX handler: Calculates catering price preview.
	 *
	 * @return void
	 */
	public function ajax_calculate_catering_price() {
		if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid request method.', 'restaurant-food-services' ) ), 405 );
		}

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
		if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid request method.', 'restaurant-food-services' ) ), 405 );
		}

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
		$custom_description = isset( $_POST['custom_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_description'] ) ) : '';
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

		// Check if this is a custom offering
		$is_custom = strpos( strtolower( $event_type ), 'custom' ) !== false;

		if ( $is_custom ) {
			// For custom offerings, validate custom description
			if ( '' === $custom_description ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Please describe your custom catering needs.', 'restaurant-food-services' ) ), 400 );
			}
			$structured_items = array();
		} else {
			// For standard offerings, validate menu items
			$structured_items = $this->build_structured_catering_items( $menu_quantities );

			if ( empty( $structured_items ) ) {
				wp_send_json_error( array( 'message' => esc_html__( 'Please select at least one menu item.', 'restaurant-food-services' ) ), 400 );
			}
		}

		$pricing     = $is_custom ? array( 'subtotal' => 0, 'service_fee_percent' => 0, 'service_fee_amount' => 0, 'total' => 0 ) : $this->calculate_catering_pricing_details( $structured_items );
		$total_price = $pricing['total'];
		$request_signature = md5(
			wp_json_encode(
				array(
					'event_type'            => $event_type,
					'event_date'            => $event_date,
					'guest_count'           => $guest_count,
					'location'              => $location,
					'serving_style'         => $serving_style,
					'menu_items'            => $structured_items,
					'custom_description'    => $custom_description,
					'special_requests'      => $special_requests,
					'dietary_requirements'  => $dietary_requirements,
				)
			)
		);

		$submission_lock_key = 'restaurant_catering_submit_lock_' . $user_id . '_' . $request_signature;

		if ( get_transient( $submission_lock_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Duplicate submission detected. Please wait a moment and refresh.', 'restaurant-food-services' ) ), 429 );
		}

		set_transient( $submission_lock_key, 1, 30 );

		$recent_signature = (string) get_user_meta( $user_id, '_restaurant_last_catering_signature', true );
		$recent_request   = absint( get_user_meta( $user_id, '_restaurant_last_catering_request_id', true ) );
		$recent_at        = absint( get_user_meta( $user_id, '_restaurant_last_catering_submission_at', true ) );

		if ( '' !== $recent_signature && $recent_signature === $request_signature && $recent_request > 0 && ( time() - $recent_at ) < 900 ) {
			$existing_request = get_post( $recent_request );

			if ( $existing_request && 'catering_request' === $existing_request->post_type && (int) $existing_request->post_author === $user_id ) {
				delete_transient( $submission_lock_key );
				wp_send_json_success(
					array(
						'message'    => esc_html__( 'Catering request already submitted. Reusing your previous request.', 'restaurant-food-services' ),
						'request_id' => (int) $recent_request,
					)
				);
			}
		}

		if ( ! post_type_exists( 'catering_request' ) ) {
			delete_transient( $submission_lock_key );
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
			delete_transient( $submission_lock_key );
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
		update_post_meta( $post_id, 'custom_description', $custom_description );
		update_post_meta( $post_id, 'menu_items', wp_json_encode( $structured_items ) );
		update_post_meta( $post_id, 'selected_items', wp_json_encode( $selected_items ) );
		update_post_meta( $post_id, 'quantities', wp_json_encode( $quantities ) );
		update_post_meta( $post_id, 'user_id', $user_id > 0 ? $user_id : 0 );
		update_post_meta( $post_id, 'service_fee_percent', $pricing['service_fee_percent'] );
		update_post_meta( $post_id, 'service_fee_amount', $pricing['service_fee_amount'] );
		update_post_meta( $post_id, 'subtotal_price', $pricing['subtotal'] );
		update_post_meta( $post_id, 'total_price', $total_price );
		update_post_meta( $post_id, 'catering_status', 'pending' );
		update_post_meta( $post_id, '_submission_signature', $request_signature );

		update_user_meta( $user_id, '_restaurant_last_catering_signature', $request_signature );
		update_user_meta( $user_id, '_restaurant_last_catering_request_id', (int) $post_id );
		update_user_meta( $user_id, '_restaurant_last_catering_submission_at', time() );

		$this->send_catering_request_admin_notification(
			(int) $post_id,
			array(
				'event_type'            => $event_type,
				'event_date'            => $event_date,
				'guest_count'           => $guest_count,
				'location'              => $location,
				'serving_style'         => $serving_style,
				'special_requests'      => $special_requests,
				'dietary_needs'         => $dietary_requirements,
				'custom_description'    => $custom_description,
				'menu_items'            => $structured_items,
				'pricing'               => $pricing,
			)
		);

		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( 'restaurant_catering_wizard_draft' );
		}

		$draft_id = absint( get_user_meta( $user_id, '_restaurant_catering_draft_id', true ) );

		if ( $draft_id > 0 && get_post( $draft_id ) && (int) get_post_field( 'post_author', $draft_id ) === $user_id ) {
			wp_trash_post( $draft_id );
		}

		delete_user_meta( $user_id, '_restaurant_catering_draft_id' );

		do_action( 'restaurant_food_services_catering_request_submitted', (int) $post_id );
		delete_transient( $submission_lock_key );

		wp_send_json_success(
			array(
				'message'    => esc_html__( 'Catering request submitted successfully.', 'restaurant-food-services' ),
				'request_id' => (int) $post_id,
			)
		);
	}

	/**
	 * Sends structured admin email for a newly submitted catering request.
	 *
	 * @param int                $request_id Catering request post ID.
	 * @param array<string,mixed> $payload    Catering request payload.
	 *
	 * @return void
	 */
	protected function send_catering_request_admin_notification( $request_id, $payload ) {
		$admin_email = sanitize_email( (string) get_option( 'admin_email' ) );

		if ( '' === $admin_email ) {
			return;
		}

		$event_type    = isset( $payload['event_type'] ) ? sanitize_text_field( (string) $payload['event_type'] ) : '';
		$event_date    = isset( $payload['event_date'] ) ? sanitize_text_field( (string) $payload['event_date'] ) : '';
		$guest_count   = isset( $payload['guest_count'] ) ? absint( $payload['guest_count'] ) : 0;
		$location      = isset( $payload['location'] ) ? sanitize_text_field( (string) $payload['location'] ) : '';
		$serving_style = isset( $payload['serving_style'] ) ? sanitize_text_field( (string) $payload['serving_style'] ) : '';
		$special       = isset( $payload['special_requests'] ) ? sanitize_textarea_field( (string) $payload['special_requests'] ) : '';
		$dietary       = isset( $payload['dietary_needs'] ) ? sanitize_textarea_field( (string) $payload['dietary_needs'] ) : '';

		$pricing = isset( $payload['pricing'] ) && is_array( $payload['pricing'] ) ? $payload['pricing'] : array();
		$subtotal_html = wc_price( isset( $pricing['subtotal'] ) ? (float) $pricing['subtotal'] : 0.0 );
		$service_html  = wc_price( isset( $pricing['service_fee_amount'] ) ? (float) $pricing['service_fee_amount'] : 0.0 );
		$total_html    = wc_price( isset( $pricing['total'] ) ? (float) $pricing['total'] : 0.0 );

		$menu_items = isset( $payload['menu_items'] ) && is_array( $payload['menu_items'] ) ? $payload['menu_items'] : array();
		$menu_rows  = '';

		foreach ( $menu_items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$name     = isset( $item['product_name'] ) ? esc_html( sanitize_text_field( (string) $item['product_name'] ) ) : esc_html__( 'Item', 'restaurant-food-services' );
			$qty      = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 0;
			$price    = isset( $item['price'] ) ? (float) $item['price'] : 0.0;
			$menu_rows .= '<tr><td style="padding:8px;border:1px solid #ddd;">' . $name . '</td><td style="padding:8px;border:1px solid #ddd;text-align:center;">' . esc_html( (string) $qty ) . '</td><td style="padding:8px;border:1px solid #ddd;text-align:right;">' . wp_kses_post( wc_price( $price ) ) . '</td></tr>';
		}

		if ( '' === $menu_rows ) {
			$menu_rows = '<tr><td colspan="3" style="padding:8px;border:1px solid #ddd;">' . esc_html__( 'No menu items provided.', 'restaurant-food-services' ) . '</td></tr>';
		}

		$edit_link = admin_url( 'post.php?post=' . absint( $request_id ) . '&action=edit' );
		$subject   = sprintf( __( 'New Catering Request #%d', 'restaurant-food-services' ), absint( $request_id ) );

		$message  = '<div style="font-family:Arial,sans-serif;line-height:1.5;color:#222;">';
		$message .= '<h2 style="margin:0 0 12px;">' . esc_html__( 'New Catering Request Submitted', 'restaurant-food-services' ) . '</h2>';
		$message .= '<p style="margin:0 0 14px;">' . esc_html__( 'A new catering request has been submitted. Review the details below.', 'restaurant-food-services' ) . '</p>';
		$message .= '<h3 style="margin:16px 0 8px;">' . esc_html__( 'Event Details', 'restaurant-food-services' ) . '</h3>';
		$message .= '<table style="border-collapse:collapse;width:100%;max-width:760px;">';
		$message .= '<tr><td style="padding:8px;border:1px solid #ddd;width:180px;"><strong>' . esc_html__( 'Request ID', 'restaurant-food-services' ) . '</strong></td><td style="padding:8px;border:1px solid #ddd;">' . esc_html( (string) $request_id ) . '</td></tr>';
		$message .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>' . esc_html__( 'Event Type', 'restaurant-food-services' ) . '</strong></td><td style="padding:8px;border:1px solid #ddd;">' . esc_html( $event_type ) . '</td></tr>';
		$message .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>' . esc_html__( 'Event Date', 'restaurant-food-services' ) . '</strong></td><td style="padding:8px;border:1px solid #ddd;">' . esc_html( $event_date ) . '</td></tr>';
		$message .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>' . esc_html__( 'Guest Count', 'restaurant-food-services' ) . '</strong></td><td style="padding:8px;border:1px solid #ddd;">' . esc_html( (string) $guest_count ) . '</td></tr>';
		$message .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>' . esc_html__( 'Location', 'restaurant-food-services' ) . '</strong></td><td style="padding:8px;border:1px solid #ddd;">' . esc_html( $location ) . '</td></tr>';
		$message .= '<tr><td style="padding:8px;border:1px solid #ddd;"><strong>' . esc_html__( 'Service Option', 'restaurant-food-services' ) . '</strong></td><td style="padding:8px;border:1px solid #ddd;">' . esc_html( $serving_style ) . '</td></tr>';
		$message .= '</table>';

		$message .= '<h3 style="margin:16px 0 8px;">' . esc_html__( 'Selected Menu Items', 'restaurant-food-services' ) . '</h3>';
		$message .= '<table style="border-collapse:collapse;width:100%;max-width:760px;">';
		$message .= '<thead><tr><th style="padding:8px;border:1px solid #ddd;text-align:left;">' . esc_html__( 'Item', 'restaurant-food-services' ) . '</th><th style="padding:8px;border:1px solid #ddd;">' . esc_html__( 'Qty', 'restaurant-food-services' ) . '</th><th style="padding:8px;border:1px solid #ddd;text-align:right;">' . esc_html__( 'Unit Price', 'restaurant-food-services' ) . '</th></tr></thead>';
		$message .= '<tbody>' . $menu_rows . '</tbody>';
		$message .= '</table>';

		$message .= '<h3 style="margin:16px 0 8px;">' . esc_html__( 'Pricing', 'restaurant-food-services' ) . '</h3>';
		$message .= '<p style="margin:0 0 6px;">' . esc_html__( 'Subtotal:', 'restaurant-food-services' ) . ' ' . wp_kses_post( $subtotal_html ) . '</p>';
		$message .= '<p style="margin:0 0 6px;">' . esc_html__( 'Service Fee:', 'restaurant-food-services' ) . ' ' . wp_kses_post( $service_html ) . '</p>';
		$message .= '<p style="margin:0 0 14px;"><strong>' . esc_html__( 'Total:', 'restaurant-food-services' ) . ' ' . wp_kses_post( $total_html ) . '</strong></p>';

		if ( '' !== $special ) {
			$message .= '<p style="margin:0 0 8px;"><strong>' . esc_html__( 'Special Requests:', 'restaurant-food-services' ) . '</strong> ' . nl2br( esc_html( $special ) ) . '</p>';
		}

		if ( '' !== $dietary ) {
			$message .= '<p style="margin:0 0 12px;"><strong>' . esc_html__( 'Dietary Needs:', 'restaurant-food-services' ) . '</strong> ' . nl2br( esc_html( $dietary ) ) . '</p>';
		}

		$message .= '<p style="margin:18px 0 0;"><a href="' . esc_url( $edit_link ) . '" style="display:inline-block;padding:10px 14px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;">' . esc_html__( 'View Request in Admin', 'restaurant-food-services' ) . '</a></p>';
		$message .= '</div>';

		wp_mail(
			$admin_email,
			$subject,
			$message,
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}

	/**
	 * AJAX handler: Submits meal plan wizard selections.
	 *
	 * @return void
	 */
	public function ajax_submit_meal_plan_selection() {
		if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid request method.', 'restaurant-food-services' ) ), 405 );
		}

		if ( ! check_ajax_referer( 'restaurant_meal_plan_wizard_submit', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'restaurant-food-services' ) ), 403 );
		}

		if ( ! $this->is_meal_plan_subscription_window_open() ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Meal plans for this week are closed. Please subscribe for next week.', 'restaurant-food-services' ) ), 400 );
		}

		$plan_type      = isset( $_POST['plan_type'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_type'] ) ) : '';
		$meals_per_week = isset( $_POST['meals_per_week'] ) ? absint( wp_unslash( $_POST['meals_per_week'] ) ) : 0;
		$raw_selected_meals = isset( $_POST['selected_meals'] ) ? (array) wp_unslash( $_POST['selected_meals'] ) : array();
		$selected_meals = array();

		foreach ( $raw_selected_meals as $meal_item ) {
			$product_id = 0;
			$quantity   = 0;

			if ( is_array( $meal_item ) ) {
				$product_id = isset( $meal_item['product_id'] ) ? absint( $meal_item['product_id'] ) : 0;
				$quantity   = isset( $meal_item['quantity'] ) ? absint( $meal_item['quantity'] ) : 0;
			} else {
				$product_id = absint( $meal_item );
				$quantity   = $product_id > 0 ? 1 : 0;
			}

			if ( $product_id <= 0 || $quantity <= 0 ) {
				continue;
			}

			if ( isset( $selected_meals[ $product_id ] ) ) {
				$selected_meals[ $product_id ] += $quantity;
			} else {
				$selected_meals[ $product_id ] = $quantity;
			}
		}

		if ( empty( $selected_meals ) ) {
			foreach ( $this->get_active_weekly_menu_meal_ids() as $weekly_meal_id ) {
				$weekly_meal_id = absint( $weekly_meal_id );

				if ( $weekly_meal_id > 0 ) {
					$selected_meals[ $weekly_meal_id ] = 1;
				}
			}
		}
		$delivery_location = isset( $_POST['delivery_location'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_location'] ) ) : '';
		$delivery_latitude = isset( $_POST['delivery_latitude'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_latitude'] ) ) : '';
		$delivery_longitude = isset( $_POST['delivery_longitude'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_longitude'] ) ) : '';
		$delivery_days  = array( 'sunday' );
		$delivery_time  = isset( $_POST['delivery_time'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_time'] ) ) : 'morning';

		if ( ! in_array( $plan_type, array( 'individual', 'family' ), true ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please choose a valid plan type.', 'restaurant-food-services' ) ), 400 );
		}

		if ( $meals_per_week <= 0 ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please select meals per week.', 'restaurant-food-services' ) ), 400 );
		}

		if ( empty( $selected_meals ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please enter quantity for at least one meal.', 'restaurant-food-services' ) ), 400 );
		}

		if ( '' === $delivery_location ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please provide a delivery location.', 'restaurant-food-services' ) ), 400 );
		}

		if ( ! is_numeric( $delivery_latitude ) || ! is_numeric( $delivery_longitude ) ) {
			$fallback = $this->geocode_location_address( $delivery_location );

			if ( is_array( $fallback ) ) {
				$delivery_latitude  = isset( $fallback['latitude'] ) ? (string) $fallback['latitude'] : '';
				$delivery_longitude = isset( $fallback['longitude'] ) ? (string) $fallback['longitude'] : '';
			} else {
				wp_send_json_error( array( 'message' => esc_html__( 'Unable to validate this location right now. Please try a more specific address.', 'restaurant-food-services' ) ), 400 );
			}
		}

		$location_data = $this->sanitize_location_data(
			array(
				'formatted_address' => $delivery_location,
				'latitude'          => $delivery_latitude,
				'longitude'         => $delivery_longitude,
			)
		);

		if ( '' === $location_data['formatted_address'] ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Please provide a delivery location.', 'restaurant-food-services' ) ), 400 );
		}

		$delivery_days = array( 'sunday' );

		if ( ! in_array( $delivery_time, array( 'morning', 'afternoon', 'evening' ), true ) ) {
			$delivery_time = 'morning';
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart || ! WC()->session ) {
			wp_send_json_error( array( 'message' => esc_html__( 'WooCommerce is unavailable right now.', 'restaurant-food-services' ) ), 500 );
		}

		$order_signature = md5(
			wp_json_encode(
				array(
					'plan_type'      => $plan_type,
					'meals_per_week' => $meals_per_week,
					'selected_meals' => $selected_meals,
					'delivery_time'  => $delivery_time,
					'location'       => $location_data,
				)
			)
		);

		$last_signature = (string) WC()->session->get( 'restaurant_meal_plan_order_signature' );
		$last_order_id  = absint( WC()->session->get( 'restaurant_meal_plan_order_id' ) );
		$lock_owner     = get_current_user_id();
		$lock_key       = 'restaurant_meal_plan_submit_lock_' . absint( $lock_owner ) . '_' . $order_signature;

		if ( get_transient( $lock_key ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Duplicate submission detected. Please wait a moment and try again.', 'restaurant-food-services' ) ), 429 );
		}

		set_transient( $lock_key, 1, 30 );

		if ( '' !== $last_signature && $last_signature === $order_signature && $last_order_id > 0 ) {
			$existing_order = wc_get_order( $last_order_id );

			if ( $existing_order ) {
				delete_transient( $lock_key );
				wp_send_json_success(
					array(
						'message'      => esc_html__( 'Meal plan order already created. Redirecting to checkout.', 'restaurant-food-services' ),
						'cart_count'   => (int) WC()->cart->get_cart_contents_count(),
						'checkout_url' => $existing_order->get_checkout_payment_url(),
					)
				);
			}
		}

		// Prevent stale cart items from previous flows before staging this meal plan.
		WC()->cart->empty_cart();

		$valid_products = array();

		foreach ( $selected_meals as $product_id => $quantity ) {
			$product = wc_get_product( $product_id );

			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				continue;
			}

			$valid_products[] = array(
				'product_id' => (int) $product_id,
				'quantity'   => max( 1, absint( $quantity ) ),
			);
		}

		if ( empty( $valid_products ) ) {
			delete_transient( $lock_key );
			wp_send_json_error( array( 'message' => esc_html__( 'Selected meals are unavailable.', 'restaurant-food-services' ) ), 400 );
		}

		foreach ( $valid_products as $meal_item ) {
			$meal_product_id = isset( $meal_item['product_id'] ) ? absint( $meal_item['product_id'] ) : 0;
			$meal_quantity   = isset( $meal_item['quantity'] ) ? max( 1, absint( $meal_item['quantity'] ) ) : 1;

			if ( $meal_product_id <= 0 ) {
				continue;
			}

			WC()->cart->add_to_cart( $meal_product_id, $meal_quantity );
		}

		$order = wc_create_order(
			array(
				'customer_id' => get_current_user_id(),
			)
		);

		if ( is_wp_error( $order ) || ! $order instanceof \WC_Order ) {
			delete_transient( $lock_key );
			wp_send_json_error( array( 'message' => esc_html__( 'Unable to create your meal plan order right now.', 'restaurant-food-services' ) ), 500 );
		}

		foreach ( $valid_products as $meal_item ) {
			$product_id = isset( $meal_item['product_id'] ) ? absint( $meal_item['product_id'] ) : 0;
			$quantity   = isset( $meal_item['quantity'] ) ? max( 1, absint( $meal_item['quantity'] ) ) : 1;
			$product    = wc_get_product( $product_id );

			if ( ! $product instanceof \WC_Product ) {
				continue;
			}

			$order->add_product( $product, $quantity );
		}

		$selection = array(
			'plan_type'              => $plan_type,
			'meals_per_week'         => $meals_per_week,
			'selected_meals'         => $valid_products,
			'delivery_location_data' => $location_data,
			'delivery_days'          => $delivery_days,
			'delivery_time'          => $delivery_time,
		);

		$order->update_meta_data( '_restaurant_meal_plan_selection', wp_json_encode( $selection ) );
		$order->update_meta_data( '_restaurant_order_type', 'weekly_meal_plan' );
		$order->update_meta_data( 'selected_meals', wp_json_encode( $valid_products ) );
		$order->update_meta_data( 'delivery_days', wp_json_encode( array( 'sunday' ) ) );
		$order->update_meta_data( 'delivery_time_slot', $delivery_time );
		$order->update_meta_data( 'delivery_location_data', wp_json_encode( $location_data ) );
		$order->update_meta_data( 'delivery_location', $location_data['formatted_address'] );
		$order->update_meta_data( 'delivery_location_latitude', $location_data['latitude'] );
		$order->update_meta_data( 'delivery_location_longitude', $location_data['longitude'] );
		$order->update_meta_data( 'meals_per_week', $meals_per_week );
		$order->update_meta_data( 'plan_type', $plan_type );
		$order->calculate_totals();
		$order->save();

		// Trigger subscription creation hooks as checkout would.
		do_action( 'woocommerce_checkout_order_processed', $order->get_id(), array(), $order );

		WC()->session->set( 'restaurant_meal_plan_order_signature', $order_signature );
		WC()->session->set( 'restaurant_meal_plan_order_id', (int) $order->get_id() );
		WC()->session->set( 'restaurant_meal_plan_selection', $selection );

		// Order has been created; keep cart clean for consistency.
		WC()->cart->empty_cart();
		delete_transient( $lock_key );

		wp_send_json_success(
			array(
				'message'      => esc_html__( 'Meal plan order created. Redirecting to checkout.', 'restaurant-food-services' ),
				'cart_count'   => (int) WC()->cart->get_cart_contents_count(),
				'checkout_url' => $order->get_checkout_payment_url(),
			)
		);
	}

	/**
	 * Returns the current meal-plan subscription window (Saturday to Friday) in WP timezone.
	 *
	 * @return array<string,string>
	 */
	protected function get_meal_plan_subscription_window() {
		$timezone = wp_timezone();
		$now      = new \DateTimeImmutable( 'now', $timezone );
		$end      = $now->modify( 'friday this week' )->setTime( 23, 59, 59 );
		$start    = $end->modify( '-6 days' )->setTime( 0, 0, 0 );

		return array(
			'start' => $start->format( 'Y-m-d' ),
			'end'   => $end->format( 'Y-m-d' ),
			'now'   => $now->format( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Checks if users can subscribe to weekly meal plans in the current window.
	 *
	 * @return bool
	 */
	protected function is_meal_plan_subscription_window_open() {
		$timezone = wp_timezone();
		$now      = new \DateTimeImmutable( 'now', $timezone );
		$end      = $now->modify( 'friday this week' )->setTime( 23, 59, 59 );
		$start    = $end->modify( '-6 days' )->setTime( 0, 0, 0 );

		return $now >= $start && $now <= $end;
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
		$order->update_meta_data( '_restaurant_order_type', 'weekly_meal_plan' );
		$order->update_meta_data( 'selected_meals', wp_json_encode( isset( $selection['selected_meals'] ) ? (array) $selection['selected_meals'] : array() ) );
		$order->update_meta_data( 'delivery_days', wp_json_encode( array( 'sunday' ) ) );
		$location_data = isset( $selection['delivery_location_data'] ) && is_array( $selection['delivery_location_data'] ) ? $this->sanitize_location_data( $selection['delivery_location_data'] ) : array(
			'formatted_address' => '',
			'latitude'          => 0.0,
			'longitude'         => 0.0,
		);
		$order->update_meta_data( 'delivery_location_data', wp_json_encode( $location_data ) );
		$order->update_meta_data( 'delivery_location', $location_data['formatted_address'] );
		$order->update_meta_data( 'delivery_location_latitude', $location_data['latitude'] );
		$order->update_meta_data( 'delivery_location_longitude', $location_data['longitude'] );
		$order->update_meta_data( 'meals_per_week', isset( $selection['meals_per_week'] ) ? absint( $selection['meals_per_week'] ) : 0 );
		$order->update_meta_data( 'plan_type', isset( $selection['plan_type'] ) ? sanitize_text_field( $selection['plan_type'] ) : 'individual' );

		WC()->session->__unset( 'restaurant_meal_plan_selection' );
	}

	/**
	 * Returns Google Places API key from wp-config constant or options table.
	 *
	 * Supported constants:
	 * - RESTAURANT_FOOD_SERVICES_GOOGLE_PLACES_API_KEY
	 * - RESTAURANT_FOOD_SERVICES_GOOGLE_MAPS_API_KEY
	 *
	 * Supported option:
	 * - restaurant_food_services_google_places_api_key
	 *
	 * @return string
	 */
	protected function get_google_places_api_key() {
		$api_key = '';

		if ( defined( 'RESTAURANT_FOOD_SERVICES_GOOGLE_PLACES_API_KEY' ) ) {
			$api_key = (string) constant( 'RESTAURANT_FOOD_SERVICES_GOOGLE_PLACES_API_KEY' );
		} elseif ( defined( 'RESTAURANT_FOOD_SERVICES_GOOGLE_MAPS_API_KEY' ) ) {
			$api_key = (string) constant( 'RESTAURANT_FOOD_SERVICES_GOOGLE_MAPS_API_KEY' );
		}

		if ( '' === $api_key ) {
			$api_key = (string) get_option( 'restaurant_food_services_google_places_api_key', '' );
		}

		return sanitize_text_field( trim( $api_key ) );
	}

	/**
	 * Builds HTTP args shared by external location API requests.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_location_api_request_args() {
		return array(
			'timeout' => 8,
			'headers' => array(
				'Accept'     => 'application/json',
				'User-Agent' => 'RestaurantFoodServices/' . ( defined( 'RESTAURANT_FOOD_SERVICES_VERSION' ) ? RESTAURANT_FOOD_SERVICES_VERSION : '1.0.0' ) . '; ' . home_url( '/' ),
			),
		);
	}

	/**
	 * Searches for locations server-side using Google Places first, then Nominatim fallback.
	 *
	 * @param string $query Search text.
	 * @param int    $limit Maximum number of results.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function search_locations( $query, $limit = 6 ) {
		$query = sanitize_text_field( (string) $query );
		$limit = max( 1, min( 10, absint( $limit ) ) );

		if ( '' === $query ) {
			return array();
		}

		$google_api_key = $this->get_google_places_api_key();

		if ( '' !== $google_api_key ) {
			$google_items = $this->search_locations_with_google_places( $query, $limit, $google_api_key );

			if ( ! empty( $google_items ) ) {
				return $google_items;
			}
		}

		return $this->search_locations_with_nominatim( $query, $limit );
	}

	/**
	 * Searches locations using Google Places Text Search API.
	 *
	 * @param string $query   Search text.
	 * @param int    $limit   Maximum number of results.
	 * @param string $api_key Google Places API key.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function search_locations_with_google_places( $query, $limit, $api_key ) {
		$endpoint = (string) apply_filters( 'restaurant_food_services_google_places_search_url', 'https://maps.googleapis.com/maps/api/place/textsearch/json' );
		$url      = add_query_arg(
			array(
				'query' => $query,
				'key'   => $api_key,
			),
			$endpoint
		);

		$response = wp_remote_get( $url, $this->get_location_api_request_args() );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			return array();
		}

		$status = isset( $data['status'] ) ? (string) $data['status'] : '';

		if ( ! in_array( $status, array( 'OK', 'ZERO_RESULTS' ), true ) ) {
			return array();
		}

		if ( empty( $data['results'] ) || ! is_array( $data['results'] ) ) {
			return array();
		}

		$items = array();

		foreach ( array_slice( $data['results'], 0, $limit ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$normalized = $this->sanitize_location_data(
				array(
					'formatted_address' => isset( $entry['formatted_address'] ) ? $entry['formatted_address'] : ( isset( $entry['name'] ) ? $entry['name'] : '' ),
					'latitude'          => isset( $entry['geometry']['location']['lat'] ) ? $entry['geometry']['location']['lat'] : 0,
					'longitude'         => isset( $entry['geometry']['location']['lng'] ) ? $entry['geometry']['location']['lng'] : 0,
				)
			);

			if ( '' === $normalized['formatted_address'] ) {
				continue;
			}

			$items[] = $normalized;
		}

		return $items;
	}

	/**
	 * Searches locations using OpenStreetMap Nominatim API.
	 *
	 * @param string $query Search text.
	 * @param int    $limit Maximum number of results.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function search_locations_with_nominatim( $query, $limit ) {
		$endpoint = (string) apply_filters( 'restaurant_food_services_location_search_url', 'https://nominatim.openstreetmap.org/search' );
		$url      = add_query_arg(
			array(
				'q'              => $query,
				'format'         => 'jsonv2',
				'addressdetails' => 1,
				'limit'          => $limit,
			),
			$endpoint
		);

		$response = wp_remote_get( $url, $this->get_location_api_request_args() );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			return array();
		}

		$items = array();

		foreach ( $data as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$normalized = $this->sanitize_location_data(
				array(
					'formatted_address' => isset( $entry['display_name'] ) ? $entry['display_name'] : '',
					'latitude'          => isset( $entry['lat'] ) ? $entry['lat'] : 0,
					'longitude'         => isset( $entry['lon'] ) ? $entry['lon'] : 0,
				)
			);

			if ( '' === $normalized['formatted_address'] ) {
				continue;
			}

			$items[] = $normalized;
		}

		return $items;
	}

	/**
	 * AJAX handler: returns location autocomplete suggestions.
	 *
	 * @return void
	 */
	public function ajax_location_autocomplete() {
		if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid request method.', 'restaurant-food-services' ) ), 405 );
		}

		if ( ! check_ajax_referer( 'restaurant_location_autocomplete', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'restaurant-food-services' ) ), 403 );
		}

		$query = '';

		if ( isset( $_POST['q'] ) ) {
			$query = sanitize_text_field( wp_unslash( $_POST['q'] ) );
		} elseif ( isset( $_GET['q'] ) ) {
			$query = sanitize_text_field( wp_unslash( $_GET['q'] ) );
		}

		if ( strlen( $query ) < 3 ) {
			wp_send_json_success( array( 'items' => array() ) );
		}

		$items = $this->search_locations( $query, 6 );

		wp_send_json_success( array( 'items' => $items ) );
	}

	/**
	 * Geocodes a location string to coordinates using configured provider.
	 *
	 * @param string $address Address text.
	 *
	 * @return array<string,float>|null
	 */
	protected function geocode_location_address( $address ) {
		$address = sanitize_text_field( (string) $address );

		if ( '' === $address ) {
			return null;
		}

		$items = $this->search_locations( $address, 1 );

		if ( empty( $items ) || ! is_array( $items[0] ) ) {
			return null;
		}

		$lat = isset( $items[0]['latitude'] ) ? (float) $items[0]['latitude'] : null;
		$lng = isset( $items[0]['longitude'] ) ? (float) $items[0]['longitude'] : null;

		if ( null === $lat || null === $lng ) {
			return null;
		}

		return array(
			'latitude'  => $lat,
			'longitude' => $lng,
		);
	}

	/**
	 * Normalizes location payload for meal plan storage.
	 *
	 * @param array<string,mixed> $location_data Raw location payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function sanitize_location_data( $location_data ) {
		$formatted_address = isset( $location_data['formatted_address'] ) ? sanitize_text_field( (string) $location_data['formatted_address'] ) : '';
		$latitude          = isset( $location_data['latitude'] ) ? (float) $location_data['latitude'] : 0.0;
		$longitude         = isset( $location_data['longitude'] ) ? (float) $location_data['longitude'] : 0.0;

		if ( $latitude < -90 || $latitude > 90 ) {
			$latitude = 0.0;
		}

		if ( $longitude < -180 || $longitude > 180 ) {
			$longitude = 0.0;
		}

		return array(
			'formatted_address' => $formatted_address,
			'latitude'          => $latitude,
			'longitude'         => $longitude,
		);
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
		if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			wp_send_json_error(
				array(
					'message' => esc_html__( 'Invalid request method.', 'restaurant-food-services' ),
				),
				405
			);
		}

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
	 * Registers Gutenberg helper blocks that output the plugin shortcodes.
	 *
	 * @return void
	 */
	public function register_editor_blocks() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$script_path = dirname( dirname( __DIR__ ) ) . '/assets/js/editor-blocks.js';
		$version     = defined( 'RESTAURANT_FOOD_SERVICES_VERSION' ) ? RESTAURANT_FOOD_SERVICES_VERSION : '1.0.0';

		if ( file_exists( $script_path ) ) {
			$version = (string) filemtime( $script_path );
		}

		wp_register_script(
			'restaurant-food-services-editor-blocks',
			RESTAURANT_FOOD_SERVICES_URL . 'assets/js/editor-blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-components', 'wp-block-editor' ),
			$version,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'restaurant-food-services-editor-blocks', 'restaurant-food-services' );
		}

		$blocks = array(
			'restaurant-order-meals' => 'restaurant_order_meals',
			'restaurant-meal-plans'  => 'restaurant_meal_plans',
			'restaurant-catering'    => 'restaurant_catering',
			'restaurant-account'     => 'restaurant_account',
			'restaurant-signup'      => 'restaurant_signup',
		);

		foreach ( $blocks as $block_slug => $shortcode_tag ) {
			register_block_type(
				'restaurant-food-services/' . $block_slug,
				array(
					'editor_script'   => 'restaurant-food-services-editor-blocks',
					'render_callback' => static function () use ( $shortcode_tag ) {
						return do_shortcode( '[' . sanitize_key( $shortcode_tag ) . ']' );
					},
				)
			);
		}
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

