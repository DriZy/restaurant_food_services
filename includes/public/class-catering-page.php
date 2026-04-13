<?php
/**
 * Catering frontend page renderer.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Public;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the Catering shortcode container.
 */
class Catering_Page {

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
				'title'      => __( 'Catering', 'restaurant-food-services' ),
				'show_form'  => 'no',
			),
			(array) $atts,
			'restaurant_catering'
		);

		$show_form = 'no' !== strtolower( (string) $atts['show_form'] );

		ob_start();
		?>
		<section class="restaurant-frontend-page restaurant-catering woocommerce" data-page="catering">
			<header class="restaurant-frontend-page__header">
				<h2><?php echo esc_html( $atts['title'] ); ?></h2>
			</header>
			<div id="restaurant-catering-app" class="restaurant-frontend-page__container">
				<?php do_action( 'restaurant_food_services_render_catering_ui' ); ?>
				<?php if ( $show_form ) : ?>
					<div class="restaurant-catering-form-container">
						<?php echo do_shortcode( '[restaurant_catering_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>
			</div>
			<div class="restaurant-mobile-cta" data-cta="catering">
				<button type="button" class="restaurant-mobile-cta__button" data-action="request-catering"><?php esc_html_e( 'Request Catering', 'restaurant-food-services' ); ?></button>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
}

