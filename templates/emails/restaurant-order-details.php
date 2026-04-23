<?php
/**
 * Order Details Email - HTML.
 *
 * @package RestaurantFoodServices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var \WC_Order $order */
/** @var string $email_heading */
/** @var \WC_Email $email */

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php esc_html_e( 'Thank you for your order. Here are your full order details:', 'restaurant-food-services' ); ?>
</p>

<p>
	<strong><?php esc_html_e( 'Order Number:', 'restaurant-food-services' ); ?></strong>
	<?php echo esc_html( $order->get_order_number() ); ?><br>
	<strong><?php esc_html_e( 'Order Date:', 'restaurant-food-services' ); ?></strong>
	<?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?><br>
	<strong><?php esc_html_e( 'Order Total:', 'restaurant-food-services' ); ?></strong>
	<?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
</p>

<?php
do_action( 'woocommerce_email_order_details', $order, false, false, $email );
do_action( 'woocommerce_email_order_meta', $order, false, false, $email );
do_action( 'woocommerce_email_customer_details', $order, false, false, $email );

if ( $email->get_additional_content() ) {
	echo wp_kses_post( wpautop( wptexturize( $email->get_additional_content() ) ) );
}

do_action( 'woocommerce_email_footer', $email );

