<?php
/**
 * Catering Request Submitted Email - Plain Text Template
 *
 * @package RestaurantFoodServices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string $email_heading */
/** @var \WC_Email $email */
/** @var object $catering */

echo '= ' . esc_html( $email_heading ) . " =\n\n";

esc_html_e( 'Thank you for submitting your catering request!', 'restaurant-food-services' );
echo "\n";

esc_html_e( 'We have received your request and will review it shortly. Here are the details of your catering request:', 'restaurant-food-services' );
echo "\n\n";

echo "Event Location: " . ( isset( $catering->location ) ? esc_html( $catering->location ) : '' ) . "\n";
echo "Event Date: " . ( isset( $catering->event_date ) ? esc_html( $catering->event_date ) : '' ) . "\n";
echo "Number of Guests: " . ( isset( $catering->guest_count ) ? esc_html( $catering->guest_count ) : '' ) . "\n";
echo "Estimated Total: $" . ( isset( $catering->total_price ) ? esc_html( number_format( (float) $catering->total_price, 2 ) ) : '0.00' ) . "\n\n";

if ( ! empty( $catering->menu_items ) ) {
	echo "Menu Items:\n";
	foreach ( $catering->menu_items as $item ) {
		echo "  - " . ( isset( $item['product_name'] ) ? esc_html( $item['product_name'] ) : '' ) . " x " . ( isset( $item['quantity'] ) ? esc_html( $item['quantity'] ) : 1 ) . "\n";
	}
	echo "\n";
}

esc_html_e( 'We will contact you shortly to confirm the details and finalize your order.', 'restaurant-food-services' );
echo "\n\n";

echo "======================\n\n";

do_action( 'woocommerce_email_footer', $email );


