<?php
/**
 * Delivery module.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Modules;

use Restaurant\FoodServices\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles delivery module hooks.
 */
class Delivery_Module extends Abstract_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = 'delivery';

	/**
	 * Registers module hooks.
	 *
	 * @param Loader $loader Plugin loader instance.
	 *
	 * @return void
	 */
	public function register_hooks( Loader $loader ) {
		parent::register_hooks( $loader );
		$loader->add_action( 'init', $this, 'register_delivery_features' );
		$loader->add_filter( 'woocommerce_checkout_fields', $this, 'add_checkout_fields' );
		$loader->add_action( 'woocommerce_checkout_process', $this, 'validate_checkout_fields' );
		$loader->add_action( 'woocommerce_checkout_update_order_meta', $this, 'save_order_meta' );
		$loader->add_action( 'woocommerce_admin_order_data_after_billing_address', $this, 'display_admin_order_meta' );
		$loader->add_action( 'admin_menu', $this, 'register_delivery_admin_menu' );
	}

	/**
	 * Registers delivery module features.
	 *
	 * @return void
	 */
	public function register_delivery_features() {
		do_action( 'restaurant_food_services_delivery_ready' );
	}

	/**
	 * Adds delivery fields to WooCommerce checkout.
	 *
	 * @param array<string,mixed> $fields Checkout fields.
	 *
	 * @return array<string,mixed>
	 */
	public function add_checkout_fields( $fields ) {
		$fields['order']['delivery_date'] = array(
			'type'        => 'date',
			'label'       => esc_html__( 'Delivery Date', 'restaurant-food-services' ),
			'required'    => true,
			'class'       => array( 'form-row-first' ),
			'priority'    => 110,
			'clear'       => false,
		);

		$fields['order']['delivery_time_slot'] = array(
			'type'        => 'select',
			'label'       => esc_html__( 'Delivery Time Slot', 'restaurant-food-services' ),
			'required'    => true,
			'class'       => array( 'form-row-last' ),
			'priority'    => 120,
			'options'     => $this->get_time_slot_options(),
			'clear'       => true,
		);

		return $fields;
	}

	/**
	 * Validates required delivery fields at checkout.
	 *
	 * @return void
	 */
	public function validate_checkout_fields() {
		$delivery_date = isset( $_POST['delivery_date'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_date'] ) ) : '';
		$time_slot     = isset( $_POST['delivery_time_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_time_slot'] ) ) : '';

		if ( '' === $delivery_date ) {
			wc_add_notice( esc_html__( 'Please select a delivery date.', 'restaurant-food-services' ), 'error' );
		} elseif ( ! $this->is_valid_date( $delivery_date ) ) {
			wc_add_notice( esc_html__( 'Please enter a valid delivery date.', 'restaurant-food-services' ), 'error' );
		}

		if ( '' === $time_slot ) {
			wc_add_notice( esc_html__( 'Please select a delivery time slot.', 'restaurant-food-services' ), 'error' );
		} elseif ( ! array_key_exists( $time_slot, $this->get_time_slot_options() ) ) {
			wc_add_notice( esc_html__( 'Please select a valid delivery time slot.', 'restaurant-food-services' ), 'error' );
		}
	}

	/**
	 * Saves delivery checkout data to order meta.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return void
	 */
	public function save_order_meta( $order_id ) {
		$delivery_date = isset( $_POST['delivery_date'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_date'] ) ) : '';
		$time_slot     = isset( $_POST['delivery_time_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_time_slot'] ) ) : '';

		if ( '' !== $delivery_date ) {
			update_post_meta( $order_id, 'delivery_date', $delivery_date );
		}

		if ( '' !== $time_slot && array_key_exists( $time_slot, $this->get_time_slot_options() ) ) {
			update_post_meta( $order_id, 'delivery_time_slot', $time_slot );
		}
	}

	/**
	 * Displays delivery details on the admin order page.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return void
	 */
	public function display_admin_order_meta( $order ) {
		$delivery_date = $order->get_meta( 'delivery_date' );
		$time_slot     = $order->get_meta( 'delivery_time_slot' );

		if ( '' === $delivery_date && '' === $time_slot ) {
			return;
		}

		echo '<p><strong>' . esc_html__( 'Delivery Date:', 'restaurant-food-services' ) . '</strong> ' . esc_html( $delivery_date ) . '</p>';

		if ( '' !== $time_slot ) {
			$options = $this->get_time_slot_options();
			$label   = isset( $options[ $time_slot ] ) ? $options[ $time_slot ] : $time_slot;

			echo '<p><strong>' . esc_html__( 'Delivery Time Slot:', 'restaurant-food-services' ) . '</strong> ' . esc_html( $label ) . '</p>';
		}
	}

	/**
	 * Returns delivery time slot options.
	 *
	 * @return array<string,string>
	 */
	protected function get_time_slot_options() {
		$slots = array(
			''              => esc_html__( 'Select a time slot', 'restaurant-food-services' ),
			'morning'       => esc_html__( 'Morning (08:00 - 11:00)', 'restaurant-food-services' ),
			'afternoon'     => esc_html__( 'Afternoon (12:00 - 15:00)', 'restaurant-food-services' ),
			'evening'       => esc_html__( 'Evening (16:00 - 19:00)', 'restaurant-food-services' ),
		);

		return apply_filters( 'restaurant_food_services_delivery_time_slots', $slots );
	}

	/**
	 * Validates date format for YYYY-MM-DD.
	 *
	 * @param string $date Date string.
	 *
	 * @return bool
	 */
	protected function is_valid_date( $date ) {
		$parsed_date = \DateTime::createFromFormat( 'Y-m-d', $date );

		return $parsed_date && $parsed_date->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Registers admin menu for delivery schedule.
	 *
	 * @return void
	 */
	public function register_delivery_admin_menu() {
		add_submenu_page(
			'restaurant-food-services',
			esc_html__( 'Delivery Schedule', 'restaurant-food-services' ),
			esc_html__( 'Delivery Schedule', 'restaurant-food-services' ),
			'manage_options',
			'restaurant-delivery-schedule',
			array( $this, 'render_delivery_schedule_admin_page' )
		);
	}

	/**
	 * Renders the delivery schedule admin page.
	 *
	 * @return void
	 */
	public function render_delivery_schedule_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'restaurant-food-services' ) );
		}

		$status_filter = '';
		$date_from     = '';
		$date_to       = '';

		$filter_nonce = isset( $_GET['restaurant_delivery_filter_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['restaurant_delivery_filter_nonce'] ) ) : '';

		if ( '' !== $filter_nonce && wp_verify_nonce( $filter_nonce, 'restaurant_delivery_filter' ) ) {
			$status_filter = isset( $_GET['status_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['status_filter'] ) ) : '';
			$date_from     = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
			$date_to       = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

			$allowed_statuses = array_keys( wc_get_order_statuses() );
			$allowed_statuses[] = 'all';

			if ( ! in_array( $status_filter, $allowed_statuses, true ) ) {
				$status_filter = '';
			}

			if ( '' !== $date_from && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
				$date_from = '';
			}

			if ( '' !== $date_to && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
				$date_to = '';
			}
		}

		$query_args = array(
			'limit'  => -1,
			'status' => array_keys( wc_get_order_statuses() ),
		);

		if ( ! empty( $status_filter ) && 'all' !== $status_filter ) {
			$query_args['status'] = array( $status_filter );
		}

		$orders = wc_get_orders( $query_args );

		$deliveries = array();

		foreach ( $orders as $order ) {
			$delivery_date = $order->get_meta( 'delivery_date' );
			$time_slot     = $order->get_meta( 'delivery_time_slot' );

			if ( empty( $delivery_date ) ) {
				continue;
			}

			if ( ! empty( $date_from ) && $delivery_date < $date_from ) {
				continue;
			}

			if ( ! empty( $date_to ) && $delivery_date > $date_to ) {
				continue;
			}

			$deliveries[] = array(
				'order_id'      => $order->get_id(),
				'customer_name' => $order->get_formatted_billing_full_name(),
				'delivery_date' => $delivery_date,
				'time_slot'     => $time_slot,
				'status'        => $order->get_status(),
				'total'         => $order->get_total(),
				'address'       => $order->get_formatted_billing_address(),
			);
		}

		usort( $deliveries, function( $a, $b ) {
			return strcmp( $a['delivery_date'], $b['delivery_date'] );
		} );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Delivery Schedule', 'restaurant-food-services' ); ?></h1>

			<form method="get" style="margin: 20px 0;">
				<input type="hidden" name="page" value="restaurant-delivery-schedule">
				<?php wp_nonce_field( 'restaurant_delivery_filter', 'restaurant_delivery_filter_nonce' ); ?>

				<label for="status_filter"><?php esc_html_e( 'Order Status:', 'restaurant-food-services' ); ?></label>
				<select name="status_filter" id="status_filter">
					<option value="all" <?php selected( $status_filter, 'all' ); ?>><?php esc_html_e( '-- All Statuses --', 'restaurant-food-services' ); ?></option>
					<?php foreach ( wc_get_order_statuses() as $status_key => $status_label ) : ?>
						<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status_filter, $status_key ); ?>>
							<?php echo esc_html( $status_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>

				<label for="date_from"><?php esc_html_e( 'From:', 'restaurant-food-services' ); ?></label>
				<input type="date" name="date_from" id="date_from" value="<?php echo esc_attr( $date_from ); ?>">

				<label for="date_to"><?php esc_html_e( 'To:', 'restaurant-food-services' ); ?></label>
				<input type="date" name="date_to" id="date_to" value="<?php echo esc_attr( $date_to ); ?>">

				<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'restaurant-food-services' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=restaurant-delivery-schedule' ) ); ?>" class="button">
					<?php esc_html_e( 'Reset', 'restaurant-food-services' ); ?>
				</a>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Order ID', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Customer Name', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Delivery Date', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Time Slot', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Status', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Total', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Address', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'restaurant-food-services' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $deliveries ) ) : ?>
						<tr>
							<td colspan="8"><?php esc_html_e( 'No deliveries scheduled.', 'restaurant-food-services' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $deliveries as $delivery ) : ?>
							<?php
							$time_slot_options = $this->get_time_slot_options();
							$time_slot_label = isset( $time_slot_options[ $delivery['time_slot'] ] ) ? $time_slot_options[ $delivery['time_slot'] ] : $delivery['time_slot'];
							?>
							<tr>
								<td><?php echo esc_html( '#' . $delivery['order_id'] ); ?></td>
								<td><?php echo esc_html( $delivery['customer_name'] ); ?></td>
								<td><?php echo esc_html( $delivery['delivery_date'] ); ?></td>
								<td><?php echo esc_html( $time_slot_label ); ?></td>
								<td><?php echo esc_html( $delivery['status'] ); ?></td>
								<td><?php echo esc_html( wc_price( $delivery['total'] ) ); ?></td>
								<td><?php echo esc_html( $delivery['address'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $delivery['order_id'] ) . '&action=edit' ) ); ?>" class="button button-small">
										<?php esc_html_e( 'View', 'restaurant-food-services' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
