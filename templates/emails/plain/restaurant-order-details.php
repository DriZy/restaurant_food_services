<?php
/**
 * Order Details Email - Plain text.
 *
 * @package RestaurantFoodServices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var \WC_Order $order */
/** @var string $email_heading */
/** @var \WC_Email $email */

echo wp_kses_post( $email_heading ) . "\n\n";

echo esc_html__( 'Thank you for your order. Here are your full order details:', 'restaurant-food-services' ) . "\n\n";

echo esc_html__( 'Order Number:', 'restaurant-food-services' ) . ' ' . esc_html( $order->get_order_number() ) . "\n";
echo esc_html__( 'Order Date:', 'restaurant-food-services' ) . ' ' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . "\n";
echo esc_html__( 'Order Total:', 'restaurant-food-services' ) . ' ' . wp_strip_all_tags( $order->get_formatted_order_total() ) . "\n\n";

foreach ( $order->get_items() as $item ) {
	echo '- ' . esc_html( $item->get_name() ) . ' x ' . absint( $item->get_quantity() ) . "\n";
}

echo "\n";
echo esc_html__( 'Billing Email:', 'restaurant-food-services' ) . ' ' . sanitize_email( (string) $order->get_billing_email() ) . "\n";

echo "\n----------------------------------------\n\n";

if ( $email->get_additional_content() ) {
	echo wp_strip_all_tags( wptexturize( $email->get_additional_content() ) ) . "\n\n";
}

