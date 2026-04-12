<?php
/**
 * Subscription Created Email
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer Subscription Created Email
 *
 * An email sent to the customer when they create a subscription.
 *
 * @class       Subscription_Created_Email
 * @extends     \WC_Email
 */
class Subscription_Created_Email extends \WC_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'restaurant_subscription_created';
		$this->customer_email = true;
		$this->title          = __( 'Subscription Created', 'restaurant-food-services' );
		$this->description    = __( 'Email sent to customers when they create a new subscription', 'restaurant-food-services' );
		$this->template_html  = 'emails/restaurant-subscription-created.php';
		$this->template_plain = 'emails/plain/restaurant-subscription-created.php';
		$this->template_base  = RESTAURANT_FOOD_SERVICES_PATH . 'templates/';

		parent::__construct();
	}

	/**
	 * Get email subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Your {site_title} subscription has been created!', 'restaurant-food-services' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Subscription Confirmed', 'restaurant-food-services' );
	}

	/**
	 * Default content to show below main email content.
	 *
	 * @return string
	 */
	public function get_default_additional_content() {
		return __( 'Thank you for your subscription! Your meals will be delivered according to your schedule.', 'restaurant-food-services' );
	}

	/**
	 * Trigger email.
	 *
	 * @param int   $subscription_id Subscription ID.
	 * @param array $subscription_data Subscription data.
	 *
	 * @return void
	 */
	public function trigger( $subscription_id, $subscription_data = array() ) {
		$this->setup_locale();

		if ( ! empty( $subscription_data ) ) {
			$this->object           = (object) $subscription_data;
			$this->recipient        = isset( $subscription_data['user_email'] ) ? $subscription_data['user_email'] : '';
			$this->subscription_id  = $subscription_id;
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * Get content html.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'subscription'       => $this->object,
				'blogname'           => $this->get_blogname(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Get content plain.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'subscription'       => $this->object,
				'blogname'           => $this->get_blogname(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
}


