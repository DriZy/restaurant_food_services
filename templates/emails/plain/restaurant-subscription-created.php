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
echo "----------------------------------------\n\n";

echo "Plan Type: " . ( isset( $subscription->plan_name ) ? esc_html( $subscription->plan_name ) : ( isset( $subscription->plan_type ) && 'family' === $subscription->plan_type ? 'Family Plan' : 'Individual Plan' ) ) . "\n";
echo "Meals per Week: " . ( isset( $subscription->meals_per_week ) ? esc_html( $subscription->meals_per_week ) : '' ) . "\n";
echo "Status: " . ( isset( $subscription->status ) ? esc_html( ucfirst( $subscription->status ) ) : 'Active' ) . "\n";
echo "Next Order Date: " . ( isset( $subscription->next_order_date ) ? esc_html( $subscription->next_order_date ) : '' ) . "\n";

if ( isset( $subscription->family_size ) && $subscription->family_size > 1 ) {
	echo "\nFamily Size: " . esc_html( $subscription->family_size ) . " " . esc_html( _n( 'person', 'persons', $subscription->family_size, 'restaurant-food-services' ) ) . "\n";
}

echo "\n" . esc_html__( 'Delivery Preferences', 'restaurant-food-services' ) . ":\n";
echo "----------------------------------------\n";

if ( isset( $subscription->delivery_location ) && ! empty( $subscription->delivery_location ) ) {
	echo "Delivery Location: " . esc_html( $subscription->delivery_location ) . "\n";
}

if ( isset( $subscription->delivery_days ) && ! empty( $subscription->delivery_days ) ) {
	echo "Delivery Days: " . esc_html( $subscription->delivery_days ) . "\n";
}

if ( isset( $subscription->delivery_time_slot ) && ! empty( $subscription->delivery_time_slot ) ) {
	$time_labels = array(
		'morning' => 'Morning (8:00 AM - 12:00 PM)',
		'afternoon' => 'Afternoon (12:00 PM - 5:00 PM)',
		'evening' => 'Evening (5:00 PM - 9:00 PM)',
	);
	$time_slot = $subscription->delivery_time_slot;
	echo "Preferred Time Slot: " . ( isset( $time_labels[ $time_slot ] ) ? $time_labels[ $time_slot ] : ucfirst( $time_slot ) ) . "\n";
}

echo "\n";

if ( isset( $subscription->selected_meals ) && ! empty( $subscription->selected_meals ) ) {
	echo esc_html__( 'Selected Meals', 'restaurant-food-services' ) . ":\n";
	echo "----------------------------------------\n";

	$meals_list = is_string( $subscription->selected_meals ) ? json_decode( $subscription->selected_meals, true ) : $subscription->selected_meals;
	if ( is_array( $meals_list ) ) {
		foreach ( $meals_list as $meal_item ) {
			if ( is_array( $meal_item ) && ! empty( $meal_item ) ) {
				$meal_name = isset( $meal_item['product_name'] ) ? $meal_item['product_name'] : ( isset( $meal_item['name'] ) ? $meal_item['name'] : 'Meal' );
				$qty = isset( $meal_item['quantity'] ) ? absint( $meal_item['quantity'] ) : 1;
				echo esc_html( $meal_name ) . " x" . esc_html( $qty ) . "\n";
			}
		}
	}
	echo "\n";
}

esc_html_e( 'You can manage your subscription from your account page.', 'restaurant-food-services' );
echo "\n\n";

echo "======================\n\n";

do_action( 'woocommerce_email_footer', $email );

