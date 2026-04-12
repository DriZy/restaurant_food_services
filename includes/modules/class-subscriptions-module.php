<?php
/**
 * Subscriptions module.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Modules;

use Restaurant\FoodServices\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles subscriptions module hooks.
 */
class Subscriptions_Module extends Abstract_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = 'subscriptions';

	/**
	 * Registers module hooks.
	 *
	 * @param Loader $loader Plugin loader instance.
	 *
	 * @return void
	 */
	public function register_hooks( Loader $loader ) {
		parent::register_hooks( $loader );
		$loader->add_action( 'init', $this, 'register_subscriptions_features' );
		$loader->add_action( 'init', $this, 'maybe_schedule_daily_cron' );
		$loader->add_action( 'init', $this, 'register_my_subscriptions_endpoint' );
		$loader->add_action( 'woocommerce_checkout_order_processed', $this, 'create_subscriptions_from_order' );
		$loader->add_action( 'restaurant_food_services_daily_subscription_cron', $this, 'process_daily_subscriptions' );
		$loader->add_filter( 'woocommerce_account_menu_items', $this, 'add_subscriptions_menu_item' );
		$loader->add_action( 'woocommerce_account_my-subscriptions_endpoint', $this, 'render_my_subscriptions_page' );
		$loader->add_action( 'init', $this, 'handle_subscription_action' );
		$loader->add_action( 'admin_menu', $this, 'register_subscriptions_admin_menu' );
	}

	/**
	 * Registers subscriptions module features.
	 *
	 * @return void
	 */
	public function register_subscriptions_features() {
		do_action( 'restaurant_food_services_subscriptions_ready' );
	}

	/**
	 * Ensures the daily subscription cron event exists.
	 *
	 * @return void
	 */
	public function maybe_schedule_daily_cron() {
		$hook = 'restaurant_food_services_daily_subscription_cron';

		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', $hook );
		}
	}

	/**
	 * Creates subscription records after WooCommerce checkout.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return void
	 */
	public function create_subscriptions_from_order( $order_id ) {
		if ( empty( $order_id ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( $order->get_meta( '_restaurant_subscriptions_created' ) ) {
			return;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'restaurant_subscriptions';
		$user_id    = (int) $order->get_user_id();

		foreach ( $order->get_items() as $item ) {
			$product_id = (int) $item->get_product_id();

			if ( $product_id <= 0 || ! $this->is_meal_plan_product( $product_id ) ) {
				continue;
			}

			$meals_per_week = (int) get_post_meta( $product_id, 'meals_per_week', true );

			if ( $meals_per_week <= 0 ) {
				$meals_per_week = 1;
			}

			$selected_meals = $this->extract_order_context_value( $order, $item, 'selected_meals' );
			$delivery_days  = $this->extract_order_context_value( $order, $item, 'delivery_days' );
			$next_order     = $this->extract_next_order_date( $order );

			$inserted = $wpdb->insert(
				$table_name,
				array(
					'user_id'         => $user_id,
					'plan_id'         => $product_id,
					'meals_per_week'  => $meals_per_week,
					'selected_meals'  => wp_json_encode( $selected_meals ),
					'delivery_days'   => wp_json_encode( $delivery_days ),
					'status'          => 'active',
					'next_order_date' => $next_order,
					'created_at'      => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( false !== $inserted && $wpdb->insert_id > 0 ) {
				do_action( 'restaurant_food_services_subscription_created', (int) $wpdb->insert_id );
			}
		}

		$order->update_meta_data( '_restaurant_subscriptions_created', 'yes' );
		$order->save();
	}

	/**
	 * Processes active subscriptions due today and creates renewal orders.
	 *
	 * @return void
	 */
	public function process_daily_subscriptions() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			return;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'restaurant_subscriptions';
		$today      = current_time( 'Y-m-d' );

		$subscriptions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE status = %s AND next_order_date = %s",
				'active',
				$today
			)
		);

		if ( empty( $subscriptions ) ) {
			return;
		}

		foreach ( $subscriptions as $subscription ) {
			$this->create_renewal_order_for_subscription( $subscription );
		}
	}

	/**
	 * Creates a WooCommerce renewal order from a subscription row.
	 *
	 * @param object $subscription Subscription database row.
	 *
	 * @return void
	 */
	protected function create_renewal_order_for_subscription( $subscription ) {
		$customer_id = (int) $subscription->user_id;
		$plan_id     = (int) $subscription->plan_id;

		if ( $customer_id <= 0 || $plan_id <= 0 ) {
			return;
		}

		$order = wc_create_order(
			array(
				'customer_id' => $customer_id,
			)
		);

		if ( is_wp_error( $order ) ) {
			return;
		}

		$selected_meals = $this->decode_json_array( $subscription->selected_meals );
		$delivery_days  = $this->decode_json_array( $subscription->delivery_days );
		$added_items    = $this->add_selected_meals_to_order( $order, $selected_meals );

		if ( 0 === $added_items ) {
			$plan_product = wc_get_product( $plan_id );

			if ( $plan_product ) {
				$order->add_product( $plan_product, 1 );
			}
		}

		$order->update_meta_data( 'delivery_date', $subscription->next_order_date );
		$order->update_meta_data( 'delivery_days', wp_json_encode( $delivery_days ) );
		$order->update_meta_data( 'restaurant_subscription_id', (int) $subscription->id );
		$order->update_meta_data( 'meals_per_week', (int) $subscription->meals_per_week );
		$order->calculate_totals();
		$order->save();

		$this->update_subscription_next_order_date( (int) $subscription->id, $subscription->next_order_date );
	}

	/**
	 * Adds selected meal products to an order.
	 *
	 * @param \WC_Order         $order          Order object.
	 * @param array<int,mixed> $selected_meals Selected meal data.
	 *
	 * @return int
	 */
	protected function add_selected_meals_to_order( $order, $selected_meals ) {
		$added_items = 0;

		foreach ( $selected_meals as $meal ) {
			$product_id = 0;
			$quantity   = 1;

			if ( is_numeric( $meal ) ) {
				$product_id = (int) $meal;
			} elseif ( is_array( $meal ) ) {
				$product_id = isset( $meal['product_id'] ) ? (int) $meal['product_id'] : 0;
				$quantity   = isset( $meal['quantity'] ) ? max( 1, (int) $meal['quantity'] ) : 1;
			}

			if ( $product_id <= 0 ) {
				continue;
			}

			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$order->add_product( $product, $quantity );
			$added_items++;
		}

		return $added_items;
	}

	/**
	 * Decodes JSON into an array.
	 *
	 * @param mixed $value JSON string value.
	 *
	 * @return array<int,mixed>
	 */
	protected function decode_json_array( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Updates subscription next order date to next week.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param string $current_date    Current next order date.
	 *
	 * @return void
	 */
	protected function update_subscription_next_order_date( $subscription_id, $current_date ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'restaurant_subscriptions';
		$timestamp  = strtotime( $current_date . ' +7 days' );
		$next_date  = $timestamp ? gmdate( 'Y-m-d', $timestamp ) : gmdate( 'Y-m-d', strtotime( '+7 days' ) );

		$wpdb->update(
			$table_name,
			array( 'next_order_date' => $next_date ),
			array( 'id' => (int) $subscription_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Checks whether a product is marked as a meal plan.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return bool
	 */
	protected function is_meal_plan_product( $product_id ) {
		return 'yes' === get_post_meta( $product_id, 'is_meal_plan', true );
	}

	/**
	 * Extracts a data field from item meta, order meta, or checkout payload.
	 *
	 * @param \WC_Order      $order Order instance.
	 * @param \WC_Order_Item $item  Order item instance.
	 * @param string          $key   Data key.
	 *
	 * @return array<int,mixed>
	 */
	protected function extract_order_context_value( $order, $item, $key ) {
		$value = $item->get_meta( $key, true );

		if ( '' === $value || null === $value ) {
			$value = $order->get_meta( $key, true );
		}

		if ( ( '' === $value || null === $value ) && isset( $_POST[ $key ] ) ) {
			$value = wp_unslash( $_POST[ $key ] );
		}

		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'sanitize_text_field', $value ) ) );
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );

		if ( is_array( $decoded ) ) {
			return array_values( array_filter( array_map( 'sanitize_text_field', $decoded ) ) );
		}

		$parts = array_map( 'trim', explode( ',', $value ) );

		return array_values( array_filter( array_map( 'sanitize_text_field', $parts ) ) );
	}

	/**
	 * Derives next order date from order data.
	 *
	 * @param \WC_Order $order Order instance.
	 *
	 * @return string
	 */
	protected function extract_next_order_date( $order ) {
		$delivery_date = $order->get_meta( 'delivery_date', true );

		if ( is_string( $delivery_date ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $delivery_date ) ) {
			return $delivery_date;
		}

		return gmdate( 'Y-m-d' );
	}

	/**
	 * Registers the "My Subscriptions" endpoint for WooCommerce My Account.
	 *
	 * @return void
	 */
	public function register_my_subscriptions_endpoint() {
		add_rewrite_endpoint( 'my-subscriptions', EP_ROOT | EP_PAGES );
	}

	/**
	 * Adds the "My Subscriptions" menu item to WooCommerce My Account.
	 *
	 * @param array $items Existing menu items.
	 *
	 * @return array Modified menu items.
	 */
	public function add_subscriptions_menu_item( $items ) {
		$items['my-subscriptions'] = __( 'My Subscriptions', 'restaurant-food-services' );

		return $items;
	}

	/**
	 * Renders the "My Subscriptions" page content.
	 *
	 * @return void
	 */
	public function render_my_subscriptions_page() {
		global $wpdb;

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'Please log in to view your subscriptions.', 'restaurant-food-services' ) . '</p>';
			return;
		}

		$table_name   = $wpdb->prefix . 'restaurant_subscriptions';
		$subscriptions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC",
				$user_id
			)
		);

		?>
		<h2><?php esc_html_e( 'My Subscriptions', 'restaurant-food-services' ); ?></h2>

		<?php if ( empty( $subscriptions ) ) : ?>
			<p><?php esc_html_e( 'You have no active subscriptions.', 'restaurant-food-services' ); ?></p>
		<?php else : ?>
			<table class="woocommerce-orders-table woocommerce-MyAccount-orders">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Plan', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Meals/Week', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Status', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Next Order', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'restaurant-food-services' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $subscriptions as $subscription ) : ?>
						<?php
						$product = wc_get_product( $subscription->plan_id );
						$product_name = $product ? $product->get_name() : esc_html__( 'Unknown Plan', 'restaurant-food-services' );
						$status_label = $this->get_subscription_status_label( $subscription->status );
						?>
						<tr>
							<td><?php echo esc_html( $product_name ); ?></td>
							<td><?php echo esc_html( $subscription->meals_per_week ); ?></td>
							<td><span class="subscription-status"><?php echo esc_html( $status_label ); ?></span></td>
							<td><?php echo esc_html( $subscription->next_order_date ); ?></td>
							<td>
								<?php if ( 'active' === $subscription->status ) : ?>
									<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'pause_subscription', 'subscription_id' => $subscription->id, 'nonce' => wp_create_nonce( 'subscription_action' ) ), wc_get_account_endpoint_url( 'my-subscriptions' ) ) ); ?>" class="button">
										<?php esc_html_e( 'Pause', 'restaurant-food-services' ); ?>
									</a>
									<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'cancel_subscription', 'subscription_id' => $subscription->id, 'nonce' => wp_create_nonce( 'subscription_action' ) ), wc_get_account_endpoint_url( 'my-subscriptions' ) ) ); ?>" class="button" onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'restaurant-food-services' ); ?>')">
										<?php esc_html_e( 'Cancel', 'restaurant-food-services' ); ?>
									</a>
								<?php elseif ( 'paused' === $subscription->status ) : ?>
									<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'resume_subscription', 'subscription_id' => $subscription->id, 'nonce' => wp_create_nonce( 'subscription_action' ) ), wc_get_account_endpoint_url( 'my-subscriptions' ) ) ); ?>" class="button">
										<?php esc_html_e( 'Resume', 'restaurant-food-services' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}

	/**
	 * Handles custom actions for subscriptions (pause, cancel, resume).
	 *
	 * @return void
	 */
	public function handle_subscription_action() {
		if ( ! is_user_logged_in() || ! isset( $_GET['action'] ) || ! isset( $_GET['subscription_id'] ) ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );

		if ( ! in_array( $action, array( 'pause_subscription', 'cancel_subscription', 'resume_subscription' ), true ) ) {
			return;
		}

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'subscription_action' ) ) {
			return;
		}

		$subscription_id = absint( $_GET['subscription_id'] );
		$user_id = get_current_user_id();

		$this->update_subscription_status( $subscription_id, $user_id, $action );

		wp_safe_redirect( wc_get_account_endpoint_url( 'my-subscriptions' ) );
		exit;
	}

	/**
	 * Updates subscription status based on user action.
	 *
	 * @param int    $subscription_id Subscription ID.
	 * @param int    $user_id         User ID.
	 * @param string $action          Action to perform.
	 *
	 * @return void
	 */
	protected function update_subscription_status( $subscription_id, $user_id, $action ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'restaurant_subscriptions';

		$subscription = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d AND user_id = %d",
				$subscription_id,
				$user_id
			)
		);

		if ( ! $subscription ) {
			return;
		}

		$new_status = '';

		switch ( $action ) {
			case 'pause_subscription':
				$new_status = 'paused';
				break;
			case 'resume_subscription':
				$new_status = 'active';
				break;
			case 'cancel_subscription':
				$new_status = 'cancelled';
				break;
		}

		if ( ! empty( $new_status ) ) {
			$wpdb->update(
				$table_name,
				array( 'status' => $new_status ),
				array( 'id' => $subscription_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Gets the label for a subscription status.
	 *
	 * @param string $status Status value.
	 *
	 * @return string Status label.
	 */
	protected function get_subscription_status_label( $status ) {
		$labels = array(
			'active'    => __( 'Active', 'restaurant-food-services' ),
			'paused'    => __( 'Paused', 'restaurant-food-services' ),
			'cancelled' => __( 'Cancelled', 'restaurant-food-services' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
	}

	/**
	 * Registers admin menu for subscriptions manager.
	 *
	 * @return void
	 */
	public function register_subscriptions_admin_menu() {
		add_menu_page(
			esc_html__( 'Restaurant Services', 'restaurant-food-services' ),
			esc_html__( 'Restaurant Services', 'restaurant-food-services' ),
			'manage_options',
			'restaurant-food-services',
			array( $this, 'render_subscriptions_admin_page' ),
			'dashicons-food',
			25
		);

		add_submenu_page(
			'restaurant-food-services',
			esc_html__( 'Subscriptions Manager', 'restaurant-food-services' ),
			esc_html__( 'Subscriptions', 'restaurant-food-services' ),
			'manage_options',
			'restaurant-subscriptions-manager',
			array( $this, 'render_subscriptions_admin_page' )
		);
	}

	/**
	 * Renders the subscriptions manager admin page.
	 *
	 * @return void
	 */
	public function render_subscriptions_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'restaurant-food-services' ) );
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'restaurant_subscriptions';

		$status_filter = '';
		$date_from     = '';
		$date_to       = '';

		$filter_nonce = isset( $_GET['restaurant_subscriptions_filter_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['restaurant_subscriptions_filter_nonce'] ) ) : '';

		if ( '' !== $filter_nonce && wp_verify_nonce( $filter_nonce, 'restaurant_subscriptions_filter' ) ) {
			$status_filter = isset( $_GET['status_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['status_filter'] ) ) : '';
			$date_from     = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
			$date_to       = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

			if ( ! in_array( $status_filter, array( '', 'active', 'paused', 'cancelled' ), true ) ) {
				$status_filter = '';
			}

			if ( '' !== $date_from && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_from ) ) {
				$date_from = '';
			}

			if ( '' !== $date_to && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_to ) ) {
				$date_to = '';
			}
		}

		$query = "SELECT * FROM {$table_name} WHERE 1=1";
		$params = array();

		if ( ! empty( $status_filter ) ) {
			$query  .= ' AND status = %s';
			$params[] = $status_filter;
		}

		if ( ! empty( $date_from ) ) {
			$query  .= ' AND created_at >= %s';
			$params[] = $date_from . ' 00:00:00';
		}

		if ( ! empty( $date_to ) ) {
			$query  .= ' AND created_at <= %s';
			$params[] = $date_to . ' 23:59:59';
		}

		$query .= ' ORDER BY created_at DESC';

		$subscriptions = empty( $params ) ? $wpdb->get_results( $query ) : $wpdb->get_results( $wpdb->prepare( $query, $params ) );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Subscriptions Manager', 'restaurant-food-services' ); ?></h1>

			<form method="get" style="margin: 20px 0;">
				<input type="hidden" name="page" value="restaurant-subscriptions-manager">
				<?php wp_nonce_field( 'restaurant_subscriptions_filter', 'restaurant_subscriptions_filter_nonce' ); ?>

				<label for="status_filter"><?php esc_html_e( 'Status:', 'restaurant-food-services' ); ?></label>
				<select name="status_filter" id="status_filter">
					<option value=""><?php esc_html_e( '-- All --', 'restaurant-food-services' ); ?></option>
					<option value="active" <?php selected( $status_filter, 'active' ); ?>><?php esc_html_e( 'Active', 'restaurant-food-services' ); ?></option>
					<option value="paused" <?php selected( $status_filter, 'paused' ); ?>><?php esc_html_e( 'Paused', 'restaurant-food-services' ); ?></option>
					<option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'restaurant-food-services' ); ?></option>
				</select>

				<label for="date_from"><?php esc_html_e( 'From:', 'restaurant-food-services' ); ?></label>
				<input type="date" name="date_from" id="date_from" value="<?php echo esc_attr( $date_from ); ?>">

				<label for="date_to"><?php esc_html_e( 'To:', 'restaurant-food-services' ); ?></label>
				<input type="date" name="date_to" id="date_to" value="<?php echo esc_attr( $date_to ); ?>">

				<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'restaurant-food-services' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=restaurant-subscriptions-manager' ) ); ?>" class="button">
					<?php esc_html_e( 'Reset', 'restaurant-food-services' ); ?>
				</a>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'User', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Plan', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Meals/Week', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Status', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Next Order Date', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Created', 'restaurant-food-services' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $subscriptions ) ) : ?>
						<tr>
							<td colspan="7"><?php esc_html_e( 'No subscriptions found.', 'restaurant-food-services' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $subscriptions as $sub ) : ?>
							<?php
							$user = get_user_by( 'id', $sub->user_id );
							$product = wc_get_product( $sub->plan_id );
							$user_name = $user ? $user->display_name : esc_html__( 'Unknown', 'restaurant-food-services' );
							$product_name = $product ? $product->get_name() : esc_html__( 'Unknown', 'restaurant-food-services' );
							$status_label = $this->get_subscription_status_label( $sub->status );
							?>
							<tr>
								<td><?php echo esc_html( $sub->id ); ?></td>
								<td><?php echo esc_html( $user_name ); ?></td>
								<td><?php echo esc_html( $product_name ); ?></td>
								<td><?php echo esc_html( $sub->meals_per_week ); ?></td>
								<td><?php echo esc_html( $status_label ); ?></td>
								<td><?php echo esc_html( $sub->next_order_date ); ?></td>
								<td><?php echo esc_html( $sub->created_at ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
