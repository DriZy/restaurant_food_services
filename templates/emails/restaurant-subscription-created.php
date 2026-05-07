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

<!-- Plan Overview -->
<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #ddd; margin-bottom: 20px;">
	<thead>
		<tr style="background-color: #f5f5f5;">
			<th style="border: 1px solid #ddd; padding: 10px;"><?php esc_html_e( 'Plan Type', 'restaurant-food-services' ); ?></th>
			<th style="border: 1px solid #ddd; padding: 10px;"><?php esc_html_e( 'Meals per Week', 'restaurant-food-services' ); ?></th>
			<th style="border: 1px solid #ddd; padding: 10px;"><?php esc_html_e( 'Status', 'restaurant-food-services' ); ?></th>
			<th style="border: 1px solid #ddd; padding: 10px;"><?php esc_html_e( 'Next Order Date', 'restaurant-food-services' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( isset( $subscription->plan_name ) ? $subscription->plan_name : ( isset( $subscription->plan_type ) && 'family' === $subscription->plan_type ? 'Family Plan' : 'Individual Plan' ) ); ?></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( isset( $subscription->meals_per_week ) ? $subscription->meals_per_week : '' ); ?></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( ucfirst( isset( $subscription->status ) ? $subscription->status : 'active' ) ); ?></td>
			<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( isset( $subscription->next_order_date ) ? $subscription->next_order_date : '' ); ?></td>
		</tr>
	</tbody>
</table>

<!-- Delivery Preferences -->
<h3 style="margin: 20px 0 10px; color: #333; border-bottom: 2px solid #fbb03b; padding-bottom: 8px;"><?php esc_html_e( 'Delivery Preferences', 'restaurant-food-services' ); ?></h3>
<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #ddd; margin-bottom: 20px;">
	<tbody>
		<?php if ( isset( $subscription->delivery_location ) && ! empty( $subscription->delivery_location ) ) : ?>
			<tr>
				<td style="border: 1px solid #ddd; padding: 10px; width: 180px;"><strong><?php esc_html_e( 'Delivery Location', 'restaurant-food-services' ); ?></strong></td>
				<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( $subscription->delivery_location ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( isset( $subscription->delivery_days ) && ! empty( $subscription->delivery_days ) ) : ?>
			<tr>
				<td style="border: 1px solid #ddd; padding: 10px; width: 180px;"><strong><?php esc_html_e( 'Delivery Days', 'restaurant-food-services' ); ?></strong></td>
				<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( $subscription->delivery_days ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( isset( $subscription->delivery_time_slot ) && ! empty( $subscription->delivery_time_slot ) ) : ?>
			<tr>
				<td style="border: 1px solid #ddd; padding: 10px; width: 180px;"><strong><?php esc_html_e( 'Preferred Time Slot', 'restaurant-food-services' ); ?></strong></td>
				<td style="border: 1px solid #ddd; padding: 10px;"><?php
					$time_labels = array(
						'morning' => 'Morning (8:00 AM - 12:00 PM)',
						'afternoon' => 'Afternoon (12:00 PM - 5:00 PM)',
						'evening' => 'Evening (5:00 PM - 9:00 PM)',
					);
					$time_slot = $subscription->delivery_time_slot;
					echo esc_html( isset( $time_labels[ $time_slot ] ) ? $time_labels[ $time_slot ] : ucfirst( $time_slot ) );
				?></td>
			</tr>
		<?php endif; ?>
		<?php if ( isset( $subscription->family_size ) && $subscription->family_size > 1 ) : ?>
			<tr>
				<td style="border: 1px solid #ddd; padding: 10px; width: 180px;"><strong><?php esc_html_e( 'Family Size', 'restaurant-food-services' ); ?></strong></td>
				<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( $subscription->family_size ); ?> <?php echo esc_html( _n( 'person', 'persons', $subscription->family_size, 'restaurant-food-services' ) ); ?></td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>

<!-- Selected Meals -->
<?php if ( isset( $subscription->selected_meals ) && ! empty( $subscription->selected_meals ) ) : ?>
	<h3 style="margin: 20px 0 10px; color: #333; border-bottom: 2px solid #fbb03b; padding-bottom: 8px;"><?php esc_html_e( 'Selected Meals', 'restaurant-food-services' ); ?></h3>
	<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #ddd; margin-bottom: 20px;">
		<thead>
			<tr style="background-color: #f5f5f5;">
				<th style="border: 1px solid #ddd; padding: 10px; text-align: left;"><?php esc_html_e( 'Meal', 'restaurant-food-services' ); ?></th>
				<th style="border: 1px solid #ddd; padding: 10px; text-align: center;"><?php esc_html_e( 'Qty', 'restaurant-food-services' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			$meals_list = is_string( $subscription->selected_meals ) ? json_decode( $subscription->selected_meals, true ) : $subscription->selected_meals;
			if ( is_array( $meals_list ) ) {
				foreach ( $meals_list as $meal_item ) {
					if ( is_array( $meal_item ) && ! empty( $meal_item ) ) {
						$meal_name = isset( $meal_item['product_name'] ) ? $meal_item['product_name'] : ( isset( $meal_item['name'] ) ? $meal_item['name'] : 'Meal' );
						$qty = isset( $meal_item['quantity'] ) ? absint( $meal_item['quantity'] ) : 1;
						?>
						<tr>
							<td style="border: 1px solid #ddd; padding: 10px;"><?php echo esc_html( $meal_name ); ?></td>
							<td style="border: 1px solid #ddd; padding: 10px; text-align: center;"><?php echo esc_html( $qty ); ?></td>
						</tr>
						<?php
					}
				}
			}
			?>
		</tbody>
	</table>
<?php endif; ?>

<p>
	<?php esc_html_e( 'You can manage your subscription from your account page.', 'restaurant-food-services' ); ?>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
?>


