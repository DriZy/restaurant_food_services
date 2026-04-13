<?php
/**
 * Meal Plans frontend page renderer.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Meal Plans shortcode container.
 */
class Meal_Plans_Page {

	/**
	 * Renders the shortcode output.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'Meal Plans', 'restaurant-food-services' ),
			),
			(array) $atts,
			'restaurant_meal_plans'
		);

		ob_start();
		?>
		<section class="restaurant-frontend-page restaurant-meal-plans woocommerce" data-page="meal-plans">
			<header class="restaurant-frontend-page__header">
				<h2><?php echo esc_html( $atts['title'] ); ?></h2>
			</header>
			<div id="restaurant-meal-plans-app" class="restaurant-frontend-page__container">
				<?php do_action( 'restaurant_food_services_render_meal_plans_ui' ); ?>
			</div>
			<div class="restaurant-mobile-cta" data-cta="meal-plans">
				<button type="button" class="restaurant-mobile-cta__button" data-action="start-plan"><?php esc_html_e( 'Start Plan', 'restaurant-food-services' ); ?></button>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
}

