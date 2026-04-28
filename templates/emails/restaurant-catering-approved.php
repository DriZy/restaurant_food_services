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
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( isset( $catering->location ) ? $catering->location : '' ); ?></td>
		</tr>
		<tr>
			<td style="border: 1px solid #ddd; padding: 10px;"><strong><?php esc_html_e( 'Event Date', 'restaurant-food-services' ); ?></strong></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( isset( $catering->event_date ) ? $catering->event_date : '' ); ?></td>
		</tr>
		<tr>
			<td style="border: 1px solid #ddd; padding: 10px;"><strong><?php esc_html_e( 'Number of Guests', 'restaurant-food-services' ); ?></strong></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( isset( $catering->guest_count ) ? $catering->guest_count : '' ); ?></td>
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
				echo esc_html( isset( $item['product_name'] ) ? $item['product_name'] : '' );
				echo ' x ' . esc_html( isset( $item['quantity'] ) ? $item['quantity'] : 1 );
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
// Generate URLs for action buttons.
$my_account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
$catering_requests_url = add_query_arg( 'catering_request', isset( $catering->catering_id ) ? $catering->catering_id : 0, trailingslashit( $my_account_url ) . 'my-catering-requests' );
?>

<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
	<p style="margin-bottom: 15px;">
		<strong><?php esc_html_e( 'Next Steps:', 'restaurant-food-services' ); ?></strong>
	</p>
	<table cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin-bottom: 20px;">
		<tr>
			<td style="padding-right: 10px; padding-bottom: 10px;">
				<a href="<?php echo esc_url( $catering_requests_url ); ?>" style="display: inline-block; padding: 12px 24px; background-color: #2ea44f; color: #fff; text-decoration: none; border-radius: 3px; font-weight: 600;">
					<?php esc_html_e( '💰 Complete Order & Checkout', 'restaurant-food-services' ); ?>
				</a>
			</td>
		</tr>
		<tr>
			<td style="padding-top: 10px;">
				<a href="<?php echo esc_url( $catering_requests_url ); ?>" style="display: inline-block; padding: 12px 24px; background-color: #0073aa; color: #fff; text-decoration: none; border-radius: 3px; font-weight: 600;">
					<?php esc_html_e( '💬 Send a Message', 'restaurant-food-services' ); ?>
				</a>
			</td>
		</tr>
	</table>
	<p style="font-size: 12px; color: #666; margin: 10px 0 0 0;">
		<?php esc_html_e( 'Use the buttons above to proceed with payment or send a message directly to the store owner from your account.', 'restaurant-food-services' ); ?>
	</p>
</div>

<?php
do_action( 'woocommerce_email_footer', $email );
?>


