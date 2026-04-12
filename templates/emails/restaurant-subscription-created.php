<?php
/**
 * Subscription Created Email - HTML Template
 *
 * @package RestaurantFoodServices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string $email_heading */
/** @var \WC_Email $email */
/** @var object $subscription */

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php esc_html_e( 'Thank you for creating a subscription with us!', 'restaurant-food-services' ); ?>
</p>

<p>
	<?php esc_html_e( 'Your subscription details:', 'restaurant-food-services' ); ?>
</p>

<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #ddd;">
	<thead>
		<tr style="background-color: #f5f5f5;">
			<th style="border: 1px solid #ddd; padding: 10px;"><?php esc_html_e( 'Plan', 'restaurant-food-services' ); ?></th>
			<th style="border: 1px solid #ddd; padding: 10px;"><?php esc_html_e( 'Meals per Week', 'restaurant-food-services' ); ?></th>
			<th style="border: 1px solid #ddd; padding: 10px;"><?php esc_html_e( 'Status', 'restaurant-food-services' ); ?></th>
			<th style="border: 1px solid #ddd; padding: 10px;"><?php esc_html_e( 'Next Order Date', 'restaurant-food-services' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( $subscription->plan_name ?? '' ); ?></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( $subscription->meals_per_week ?? '' ); ?></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( ucfirst( $subscription->status ?? 'active' ) ); ?></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( $subscription->next_order_date ?? '' ); ?></td>
		</tr>
	</tbody>
</table>

<p>
	<?php esc_html_e( 'You can manage your subscription from your account page.', 'restaurant-food-services' ); ?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
?>


