<?php
/**
 * Subscription Created Email - Plain Text Template
 *
 * @package RestaurantFoodServices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string $email_heading */
/** @var \WC_Email $email */
/** @var object $subscription */

echo '= ' . esc_html( $email_heading ) . " =\n\n";

esc_html_e( 'Thank you for creating a subscription with us!', 'restaurant-food-services' );
echo "\n\n";

esc_html_e( 'Your subscription details:', 'restaurant-food-services' );
echo "\n";

echo "Plan: " . ( isset( $subscription->plan_name ) ? esc_html( $subscription->plan_name ) : '' ) . "\n";
echo "Meals per Week: " . ( isset( $subscription->meals_per_week ) ? esc_html( $subscription->meals_per_week ) : '' ) . "\n";
echo "Status: " . ( isset( $subscription->status ) ? esc_html( ucfirst( $subscription->status ) ) : 'Active' ) . "\n";
echo "Next Order Date: " . ( isset( $subscription->next_order_date ) ? esc_html( $subscription->next_order_date ) : '' ) . "\n\n";

esc_html_e( 'You can manage your subscription from your account page.', 'restaurant-food-services' );
echo "\n\n";

echo "======================\n\n";

do_action( 'woocommerce_email_footer', $email );


