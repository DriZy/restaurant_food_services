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

		$emails['restaurant_subscription_created']          = new \Restaurant\FoodServices\Emails\Subscription_Created_Email();
		$emails['restaurant_catering_request_submitted']   = new \Restaurant\FoodServices\Emails\Catering_Request_Submitted_Email();
		$emails['restaurant_catering_approved']            = new \Restaurant\FoodServices\Emails\Catering_Approved_Email();

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
			return;
		}

		$emails = WC()->mailer()->get_emails();
		$email  = isset( $emails['restaurant_catering_request_submitted'] ) ? $emails['restaurant_catering_request_submitted'] : null;

		if ( $email ) {
			$email->trigger( $catering_id, $catering_data );
		}
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
		$total_price = get_post_meta( $catering_id, 'total_price', true );
		$menu_items = get_post_meta( $catering_id, 'menu_items', true );
		$status = get_post_meta( $catering_id, 'catering_status', true );

		return array(
			'catering_id'   => $catering_id,
			'event_date'    => $event_date,
			'guest_count'   => $guest_count,
			'location'      => $location,
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


