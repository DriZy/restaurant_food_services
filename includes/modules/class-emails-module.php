<?php
/**
 * Emails module.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Modules;

use Restaurant\FoodServices\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles emails module hooks.
 */
class Emails_Module extends Abstract_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = 'emails';

	/**
	 * Registers module hooks.
	 *
	 * @param Loader $loader Plugin loader instance.
	 *
	 * @return void
	 */
	public function register_hooks( Loader $loader ) {
		parent::register_hooks( $loader );
		$loader->add_filter( 'woocommerce_email_classes', $this, 'register_email_classes' );
		$loader->add_action( 'woocommerce_checkout_order_processed', $this, 'send_order_details_email' );
		$loader->add_action( 'restaurant_food_services_subscription_created', $this, 'send_subscription_created_email' );
		$loader->add_action( 'restaurant_food_services_catering_request_submitted', $this, 'send_catering_request_submitted_email' );
		$loader->add_action( 'restaurant_food_services_catering_approved', $this, 'send_catering_approved_email' );
	}

	/**
	 * Registers custom email classes with WooCommerce.
	 *
	 * @param array $emails Existing WooCommerce email classes.
	 *
	 * @return array Modified email classes.
	 */
	public function register_email_classes( $emails ) {
		require_once dirname( __FILE__ ) . '/../emails/class-subscription-created-email.php';
		require_once dirname( __FILE__ ) . '/../emails/class-catering-request-submitted-email.php';
		require_once dirname( __FILE__ ) . '/../emails/class-catering-approved-email.php';
		require_once dirname( __FILE__ ) . '/../emails/class-order-details-email.php';

		$emails['restaurant_subscription_created']          = new \Restaurant\FoodServices\Emails\Subscription_Created_Email();
		$emails['restaurant_catering_request_submitted']   = new \Restaurant\FoodServices\Emails\Catering_Request_Submitted_Email();
		$emails['restaurant_catering_approved']            = new \Restaurant\FoodServices\Emails\Catering_Approved_Email();
		$emails['restaurant_order_details']                = new \Restaurant\FoodServices\Emails\Order_Details_Email();

		return $emails;
	}

	/**
	 * Sends subscription created email.
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return void
	 */
	public function send_subscription_created_email( $subscription_id ) {
		$subscription_data = $this->get_subscription_data( $subscription_id );

		if ( empty( $subscription_data ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$emails = WC()->mailer()->get_emails();
		$email  = isset( $emails['restaurant_subscription_created'] ) ? $emails['restaurant_subscription_created'] : null;

		if ( $email ) {
			$email->trigger( $subscription_id, $subscription_data );
		}
	}

	/**
	 * Sends catering request submitted email.
	 *
	 * @param int $catering_id Catering request post ID.
	 *
	 * @return void
	 */
	public function send_catering_request_submitted_email( $catering_id ) {
		$catering_data = $this->get_catering_data( $catering_id );

		if ( empty( $catering_data ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			$this->send_catering_request_submitted_fallback_email( $catering_data );
			return;
		}

		$emails = WC()->mailer()->get_emails();
		$email  = isset( $emails['restaurant_catering_request_submitted'] ) ? $emails['restaurant_catering_request_submitted'] : null;

		if ( $email && $email->is_enabled() ) {
			$email->trigger( $catering_id, $catering_data );
			return;
		}

		$this->send_catering_request_submitted_fallback_email( $catering_data );
	}

	/**
	 * Sends order details email when checkout order is created.
	 *
	 * @param int              $order_id    Order ID.
	 * @param array<string,mixed> $posted_data Checkout posted data.
	 * @param \WC_Order|null   $order       Order object.
	 *
	 * @return void
	 */
	public function send_order_details_email( $order_id, $posted_data = array(), $order = null ) {
		$order = $order instanceof \WC_Order ? $order : wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( 'yes' === $order->get_meta( '_restaurant_order_details_email_sent', true ) ) {
			return;
		}

		if ( function_exists( 'WC' ) && WC()->mailer() ) {
			$emails = WC()->mailer()->get_emails();
			$email  = isset( $emails['restaurant_order_details'] ) ? $emails['restaurant_order_details'] : null;

			if ( $email && $email->is_enabled() ) {
				$email->trigger( $order->get_id(), $order );
				$order->update_meta_data( '_restaurant_order_details_email_sent', 'yes' );
				$order->save();
				return;
			}
		}

		$this->send_order_details_fallback_email( $order );
		$order->update_meta_data( '_restaurant_order_details_email_sent', 'yes' );
		$order->save();
	}

	/**
	 * Sends catering approved email.
	 *
	 * @param int $catering_id Catering request post ID.
	 *
	 * @return void
	 */
	public function send_catering_approved_email( $catering_id ) {
		$catering_data = $this->get_catering_data( $catering_id );

		if ( empty( $catering_data ) ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$emails = WC()->mailer()->get_emails();
		$email  = isset( $emails['restaurant_catering_approved'] ) ? $emails['restaurant_catering_approved'] : null;

		if ( $email ) {
			$email->trigger( $catering_id, $catering_data );
		}
	}

	/**
	 * Gets subscription data.
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return array
	 */
	protected function get_subscription_data( $subscription_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'restaurant_subscriptions';
		$subscription = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$subscription_id
			)
		);

		if ( ! $subscription ) {
			return array();
		}

		$user = get_user_by( 'id', $subscription->user_id );
		$product = wc_get_product( $subscription->plan_id );
		$plan_type = isset( $subscription->plan_type ) ? sanitize_text_field( (string) $subscription->plan_type ) : '';

		if ( ! $user ) {
			return array();
		}

		$plan_name = '';

		if ( $product ) {
			$plan_name = $product->get_name();
		} elseif ( '' !== $plan_type ) {
			$plan_name = sprintf(
				/* translators: %s: plan type label */
				__( '%s Meal Plan', 'restaurant-food-services' ),
				ucfirst( $plan_type )
			);
		} else {
			$plan_name = __( 'Meal Plan Subscription', 'restaurant-food-services' );
		}

		return array(
			'subscription_id'  => $subscription->id,
			'plan_id'          => $subscription->plan_id,
			'plan_name'        => $plan_name,
			'plan_type'        => $plan_type,
			'meals_per_week'   => $subscription->meals_per_week,
			'user_id'          => $subscription->user_id,
			'user_email'       => $user->user_email,
			'user_name'        => $user->display_name,
			'status'           => $subscription->status,
			'next_order_date'  => $subscription->next_order_date,
			'created_at'       => $subscription->created_at,
		);
	}

	/**
	 * Gets catering request data.
	 *
	 * @param int $catering_id Catering request post ID.
	 *
	 * @return array
	 */
	protected function get_catering_data( $catering_id ) {
		$post = get_post( $catering_id );

		if ( ! $post || 'catering_request' !== $post->post_type ) {
			return array();
		}

		$author = get_user_by( 'id', $post->post_author );

		if ( ! $author ) {
			return array();
		}

		$event_date = get_post_meta( $catering_id, 'event_date', true );
		$guest_count = get_post_meta( $catering_id, 'guest_count', true );
		$location = get_post_meta( $catering_id, 'location', true );
		$event_type = get_post_meta( $catering_id, 'event_type', true );
		$serving_style = get_post_meta( $catering_id, 'serving_style', true );
		$special_requests = get_post_meta( $catering_id, 'special_requests', true );
		$dietary_requirements = get_post_meta( $catering_id, 'dietary_requirements', true );
		$custom_description = get_post_meta( $catering_id, 'custom_description', true );
		$total_price = get_post_meta( $catering_id, 'total_price', true );
		$menu_items = get_post_meta( $catering_id, 'menu_items', true );
		$status = get_post_meta( $catering_id, 'catering_status', true );

		return array(
			'catering_id'   => $catering_id,
			'event_type'    => $event_type,
			'event_date'    => $event_date,
			'guest_count'   => $guest_count,
			'location'      => $location,
			'serving_style' => $serving_style,
			'special_requests' => $special_requests,
			'dietary_requirements' => $dietary_requirements,
			'custom_description' => $custom_description,
			'total_price'   => $total_price,
			'menu_items'    => $this->decode_json_array( $menu_items ),
			'status'        => $status,
			'user_id'       => $post->post_author,
			'user_email'    => $author->user_email,
			'user_name'     => $author->display_name,
			'created_at'    => $post->post_date,
		);
	}

	/**
	 * Sends fallback catering-submitted email when WC email class is unavailable.
	 *
	 * @param array<string,mixed> $catering_data Catering request data.
	 *
	 * @return void
	 */
	protected function send_catering_request_submitted_fallback_email( $catering_data ) {
		$recipient = isset( $catering_data['user_email'] ) ? sanitize_email( (string) $catering_data['user_email'] ) : '';

		if ( '' === $recipient ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site title */
			__( 'Your %s catering request has been received', 'restaurant-food-services' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);

		$message = array(
			__( 'Thank you for submitting your catering request.', 'restaurant-food-services' ),
			'',
			sprintf( __( 'Request ID: #%d', 'restaurant-food-services' ), isset( $catering_data['catering_id'] ) ? absint( $catering_data['catering_id'] ) : 0 ),
			sprintf( __( 'Event Type: %s', 'restaurant-food-services' ), isset( $catering_data['event_type'] ) ? sanitize_text_field( (string) $catering_data['event_type'] ) : '-' ),
			sprintf( __( 'Event Date: %s', 'restaurant-food-services' ), isset( $catering_data['event_date'] ) ? sanitize_text_field( (string) $catering_data['event_date'] ) : '-' ),
			sprintf( __( 'Guest Count: %s', 'restaurant-food-services' ), isset( $catering_data['guest_count'] ) ? absint( $catering_data['guest_count'] ) : 0 ),
			sprintf( __( 'Location: %s', 'restaurant-food-services' ), isset( $catering_data['location'] ) ? sanitize_text_field( (string) $catering_data['location'] ) : '-' ),
		);

		wp_mail( $recipient, $subject, implode( "\n", $message ) );
	}

	/**
	 * Sends fallback order details email with essential order fields.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 *
	 * @return void
	 */
	protected function send_order_details_fallback_email( $order ) {
		$recipient = sanitize_email( (string) $order->get_billing_email() );

		if ( '' === $recipient ) {
			$user = $order->get_user();
			$recipient = $user ? sanitize_email( (string) $user->user_email ) : '';
		}

		if ( '' === $recipient ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: site title, 2: order number */
			__( '%1$s order #%2$s details', 'restaurant-food-services' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$order->get_order_number()
		);

		$lines = array(
			sprintf( __( 'Order #%s', 'restaurant-food-services' ), $order->get_order_number() ),
			sprintf( __( 'Date: %s', 'restaurant-food-services' ), wc_format_datetime( $order->get_date_created() ) ),
			'',
			__( 'Items:', 'restaurant-food-services' ),
		);

		foreach ( $order->get_items() as $item ) {
			$lines[] = sprintf(
				'- %1$s x %2$d',
				sanitize_text_field( $item->get_name() ),
				absint( $item->get_quantity() )
			);
		}

		$lines[] = '';
		$lines[] = sprintf( __( 'Total: %s', 'restaurant-food-services' ), wp_strip_all_tags( $order->get_formatted_order_total() ) );

		wp_mail( $recipient, $subject, implode( "\n", $lines ) );
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
}


