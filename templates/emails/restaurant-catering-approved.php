<?php
/**
 * Catering Approved Email - HTML Template
 *
 * @package RestaurantFoodServices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string $email_heading */
/** @var \WC_Email $email */
/** @var object $catering */

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php esc_html_e( 'Great news! Your catering request has been approved!', 'restaurant-food-services' ); ?>
</p>

<p>
	<?php esc_html_e( 'Here are the details of your approved catering request:', 'restaurant-food-services' ); ?>
</p>

<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #ddd;">
	<thead>
		<tr style="background-color: #f5f5f5;">
			<th style="border: 1px solid #ddd; padding: 10px;"><?php esc_html_e( 'Detail', 'restaurant-food-services' ); ?></th>
			<th style="border: 1px solid #ddd; padding: 10px;"><?php esc_html_e( 'Information', 'restaurant-food-services' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td style="border: 1px solid #ddd; padding: 10px;"><strong><?php esc_html_e( 'Event Location', 'restaurant-food-services' ); ?></strong></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( $catering->location ?? '' ); ?></td>
		</tr>
		<tr>
			<td style="border: 1px solid #ddd; padding: 10px;"><strong><?php esc_html_e( 'Event Date', 'restaurant-food-services' ); ?></strong></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( $catering->event_date ?? '' ); ?></td>
		</tr>
		<tr>
			<td style="border: 1px solid #ddd; padding: 10px;"><strong><?php esc_html_e( 'Number of Guests', 'restaurant-food-services' ); ?></strong></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( $catering->guest_count ?? '' ); ?></td>
		</tr>
		<tr>
			<td style="border: 1px solid #ddd; padding: 10px;"><strong><?php esc_html_e( 'Total Price', 'restaurant-food-services' ); ?></strong></td>
			<td style="border: 1px solid #ddd; padding: 10px;">$<?php echo isset( $catering->total_price ) ? esc_html( number_format( (float) $catering->total_price, 2 ) ) : '0.00'; ?></td>
		</tr>
		<tr>
			<td style="border: 1px solid #ddd; padding: 10px;"><strong><?php esc_html_e( 'Status', 'restaurant-food-services' ); ?></strong></td>
			<td style="border: 1px solid #ddd; padding: 10px; color: green;"><strong><?php esc_html_e( 'Approved', 'restaurant-food-services' ); ?></strong></td>
		</tr>
	</tbody>
</table>

<?php if ( ! empty( $catering->menu_items ) ) : ?>
	<p style="margin-top: 20px;">
		<strong><?php esc_html_e( 'Menu Items:', 'restaurant-food-services' ); ?></strong>
	</p>
	<ul>
		<?php foreach ( $catering->menu_items as $item ) : ?>
			<li>
				<?php
				echo esc_html( $item['product_name'] ?? '' );
				echo ' x ' . esc_html( $item['quantity'] ?? 1 );
				if ( isset( $item['price'] ) ) {
					echo ' @ $' . esc_html( number_format( (float) $item['price'], 2 ) );
				}
				?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<p>
	<?php esc_html_e( 'Please proceed with payment to finalize your order. If you have any questions, please do not hesitate to contact us.', 'restaurant-food-services' ); ?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
?>


