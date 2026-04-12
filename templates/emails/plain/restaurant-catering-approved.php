<?php
/**
 * Catering Approved Email - Plain Text Template
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

esc_html_e( 'Great news! Your catering request has been approved!', 'restaurant-food-services' );
echo "\n\n";

esc_html_e( 'Here are the details of your approved catering request:', 'restaurant-food-services' );
echo "\n\n";

echo "Event Location: " . ( isset( $catering->location ) ? esc_html( $catering->location ) : '' ) . "\n";
echo "Event Date: " . ( isset( $catering->event_date ) ? esc_html( $catering->event_date ) : '' ) . "\n";
echo "Number of Guests: " . ( isset( $catering->guest_count ) ? esc_html( $catering->guest_count ) : '' ) . "\n";
echo "Total Price: $" . ( isset( $catering->total_price ) ? esc_html( number_format( (float) $catering->total_price, 2 ) ) : '0.00' ) . "\n";
echo "Status: APPROVED\n\n";

if ( ! empty( $catering->menu_items ) ) {
	echo "Menu Items:\n";
	foreach ( $catering->menu_items as $item ) {
		$line = "  - " . ( isset( $item['product_name'] ) ? esc_html( $item['product_name'] ) : '' ) . " x " . ( isset( $item['quantity'] ) ? esc_html( $item['quantity'] ) : 1 );
		if ( isset( $item['price'] ) ) {
			$line .= " @ $" . esc_html( number_format( (float) $item['price'], 2 ) );
		}
		echo $line . "\n";
	}
	echo "\n";
}

esc_html_e( 'Please proceed with payment to finalize your order. If you have any questions, please do not hesitate to contact us.', 'restaurant-food-services' );
echo "\n\n";

echo "======================\n\n";

do_action( 'woocommerce_email_footer', $email );


