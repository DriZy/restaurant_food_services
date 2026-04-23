<?php
/**
 * Order Details Email.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Emails;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Customer order details email.
 */
class Order_Details_Email extends \WC_Email {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'restaurant_order_details';
		$this->customer_email = true;
		$this->title          = __( 'Order Details (Restaurant)', 'restaurant-food-services' );
		$this->description    = __( 'Email sent to customers with complete order details when an order is placed.', 'restaurant-food-services' );
		$this->template_html  = 'emails/restaurant-order-details.php';
		$this->template_plain = 'emails/plain/restaurant-order-details.php';
		$this->template_base  = RESTAURANT_FOOD_SERVICES_PATH . 'templates/';

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject() {
		return __( 'Your {site_title} order #{order_number} details', 'restaurant-food-services' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading() {
		return __( 'Order Details', 'restaurant-food-services' );
	}

	/**
	 * Trigger email.
	 *
	 * @param int            $order_id Order ID.
	 * @param \WC_Order|null $order    Optional order object.
	 *
	 * @return void
	 */
	public function trigger( $order_id, $order = null ) {
		$this->setup_locale();

		$this->object = $order instanceof \WC_Order ? $order : wc_get_order( $order_id );

		if ( ! $this->object instanceof \WC_Order ) {
			$this->restore_locale();
			return;
		}

		$this->placeholders['{order_date}']   = wc_format_datetime( $this->object->get_date_created() );
		$this->placeholders['{order_number}'] = $this->object->get_order_number();

		$this->recipient = $this->object->get_billing_email();

		if ( ! $this->recipient ) {
			$user = $this->object->get_user();
			$this->recipient = $user ? $user->user_email : '';
		}

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * Get HTML content.
	 *
	 * @return string
	 */
	public function get_content_html() {
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Get plain text content.
	 *
	 * @return string
	 */
	public function get_content_plain() {
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'              => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}
}

