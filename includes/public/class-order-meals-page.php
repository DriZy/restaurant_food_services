<?php
/**
 * Order Meals frontend page renderer.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Order Meals shortcode container.
 */
class Order_Meals_Page {

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
				'title' => __( 'Order Meals', 'restaurant-food-services' ),
			),
			(array) $atts,
			'restaurant_order_meals'
		);

		ob_start();
		?>
		<section class="restaurant-frontend-page restaurant-order-meals woocommerce" data-page="order-meals">
			<header class="restaurant-frontend-page__header">
				<h2><?php echo esc_html( $atts['title'] ); ?></h2>
			</header>
			<div id="restaurant-order-meals-app" class="restaurant-frontend-page__container">
				<?php do_action( 'restaurant_food_services_render_order_meals_ui' ); ?>
			</div>
			<div class="restaurant-mobile-cta" data-cta="order-meals">
				<button type="button" class="restaurant-mobile-cta__button" data-action="add-to-cart"><?php esc_html_e( 'Add to Cart', 'restaurant-food-services' ); ?></button>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
}

