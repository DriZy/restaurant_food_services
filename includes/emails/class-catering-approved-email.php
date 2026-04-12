<?php
/**
 * Catering Approved Email
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer Catering Approved Email
 *
 * An email sent to the customer when their catering request is approved.
 *
 * @class       Catering_Approved_Email
 * @extends     \WC_Email
 */
class Catering_Approved_Email extends \WC_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'restaurant_catering_approved';
		$this->customer_email = true;
		$this->title          = __( 'Catering Approved', 'restaurant-food-services' );
		$this->description    = __( 'Email sent to customers when their catering request is approved', 'restaurant-food-services' );
		$this->template_html  = 'emails/restaurant-catering-approved.php';
		$this->template_plain = 'emails/plain/restaurant-catering-approved.php';
		$this->template_base  = RESTAURANT_FOOD_SERVICES_PATH . 'templates/';

		parent::__construct();
	}

	/**
	 * Get email subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Your {site_title} catering request has been approved!', 'restaurant-food-services' );
	}

	/**
	 * Get email heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Catering Request Approved', 'restaurant-food-services' );
	}

	/**
	 * Default content to show below main email content.
	 *
	 * @return string
	 */
	public function get_default_additional_content() {
		return __( 'Great news! Your catering request has been approved. Please proceed with payment to confirm your order. If you have any questions, please contact us.', 'restaurant-food-services' );
	}

	/**
	 * Trigger email.
	 *
	 * @param int   $catering_id Catering request post ID.
	 * @param array $catering_data Catering data.
	 *
	 * @return void
	 */
	public function trigger( $catering_id, $catering_data = array() ) {
		$this->setup_locale();

		if ( ! empty( $catering_data ) ) {
			$this->object       = (object) $catering_data;
			$this->recipient    = isset( $catering_data['user_email'] ) ? $catering_data['user_email'] : '';
			$this->catering_id  = $catering_id;
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
				'catering'           => $this->object,
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
				'catering'           => $this->object,
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


