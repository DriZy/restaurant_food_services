<?php
/**
 * Catering Request Submitted Email - HTML Template
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
	<?php esc_html_e( 'Thank you for submitting your catering request!', 'restaurant-food-services' ); ?>
</p>

<p>
	<?php esc_html_e( 'We have received your request and will review it shortly. Here are the details of your catering request:', 'restaurant-food-services' ); ?>
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
			<td style="border: 1px solid #ddd; padding: 10px;"><strong><?php esc_html_e( 'Event Type', 'restaurant-food-services' ); ?></strong></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( isset( $catering->event_type ) ? $catering->event_type : '' ); ?></td>
		</tr>
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
			<td style="border: 1px solid #ddd; padding: 10px;"><strong><?php esc_html_e( 'Estimated Total', 'restaurant-food-services' ); ?></strong></td>
			<td style="border: 1px solid #ddd; padding: 10px;">$<?php echo isset( $catering->total_price ) ? esc_html( number_format( (float) $catering->total_price, 2 ) ) : '0.00'; ?></td>
		</tr>
		<tr>
			<td style="border: 1px solid #ddd; padding: 10px;"><strong><?php esc_html_e( 'Service Style', 'restaurant-food-services' ); ?></strong></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( isset( $catering->serving_style ) ? $catering->serving_style : '' ); ?></td>
		</tr>
	</tbody>
</table>

<?php if ( ! empty( $catering->special_requests ) ) : ?>
	<p style="margin-top: 20px;">
		<strong><?php esc_html_e( 'Special Requests:', 'restaurant-food-services' ); ?></strong><br>
		<?php echo nl2br( esc_html( $catering->special_requests ) ); ?>
	</p>
<?php endif; ?>

<?php if ( ! empty( $catering->dietary_requirements ) ) : ?>
	<p style="margin-top: 20px;">
		<strong><?php esc_html_e( 'Dietary Requirements:', 'restaurant-food-services' ); ?></strong><br>
		<?php echo nl2br( esc_html( $catering->dietary_requirements ) ); ?>
	</p>
<?php endif; ?>

<?php if ( ! empty( $catering->custom_description ) ) : ?>
	<p style="margin-top: 20px;">
		<strong><?php esc_html_e( 'Custom Meal Description:', 'restaurant-food-services' ); ?></strong><br>
		<?php echo nl2br( esc_html( $catering->custom_description ) ); ?>
	</p>
<?php endif; ?>

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
				?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<p>
	<?php esc_html_e( 'We will contact you shortly to confirm the details and finalize your order.', 'restaurant-food-services' ); ?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
?>


