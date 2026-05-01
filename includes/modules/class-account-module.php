<?php
/**
 * Account module.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Modules;

use Restaurant\FoodServices\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles frontend account authentication and dashboard rendering.
 */
class Account_Module extends Abstract_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = 'account';

	/**
	 * Custom WooCommerce account endpoint.
	 *
	 * @var string
	 */
	protected $endpoint = 'restaurant-hub';

	/**
	 * Shortcode tag.
	 *
	 * @var string
	 */
	protected $shortcode = 'restaurant_account_hub';

	/**
	 * Registers module hooks.
	*
	 * @param Loader $loader Plugin loader instance.
	 *
	 * @return void
	 */
	public function register_hooks( Loader $loader ) {
		parent::register_hooks( $loader );
		$loader->add_action( 'init', $this, 'register_account_endpoint' );
		$loader->add_action( 'init', $this, 'register_account_shortcode' );
		$loader->add_action( 'init', $this, 'handle_draft_delete_action' );
		$loader->add_action( 'init', $this, 'block_wp_login' );
		$loader->add_filter( 'woocommerce_account_menu_items', $this, 'add_account_menu_item' );
		$loader->add_action( 'woocommerce_account_' . $this->endpoint . '_endpoint', $this, 'render_account_endpoint' );
		$loader->add_action( 'woocommerce_before_customer_login_form', $this, 'render_login_intro' );
		$loader->add_filter( 'woocommerce_login_redirect', $this, 'redirect_to_restaurant_hub_after_login' );
		$loader->add_filter( 'woocommerce_logout_redirect', $this, 'redirect_to_signup_after_logout' );
		$loader->add_filter( 'login_url', $this, 'filter_login_url', 10, 3 );
		$loader->add_filter( 'logout_url', $this, 'filter_logout_url', 10, 2 );
	}

	/**
	 * Filters the login URL to point to the custom signup page.
	 *
	 * @param string $login_url    The original login URL.
	 * @param string $redirect     The redirect destination.
	 * @param bool   $force_reauth Whether to force reauthentication.
	 *
	 * @return string
	 */
	public function filter_login_url( $login_url, $redirect = '', $force_reauth = false ) {
		$signup_page_id = $this->get_signup_page_id();
		if ( $signup_page_id > 0 ) {
			$url = get_permalink( $signup_page_id );
			if ( ! empty( $redirect ) ) {
				$url = add_query_arg( 'redirect_to', urlencode( $redirect ), $url );
			}
			return $url;
		}
		return $login_url;
	}

	/**
	 * Filters the logout URL to point to the custom signup page.
	 *
	 * @param string $logout_url The original logout URL.
	 * @param string $redirect   The redirect destination.
	 *
	 * @return string
	 */
	public function filter_logout_url( $logout_url, $redirect = '' ) {
		if ( ! empty( $redirect ) ) {
			return $logout_url;
		}

		$signup_page_id = $this->get_signup_page_id();
		if ( $signup_page_id > 0 ) {
			return add_query_arg( 'redirect_to', urlencode( get_permalink( $signup_page_id ) ), $logout_url );
		}

		return $logout_url;
	}

	/**
	 * Blocks access to wp-login.php and redirects to custom login page.
	 *
	 * @return void
	 */
	public function block_wp_login() {
		global $pagenow;

		if ( 'wp-login.php' === $pagenow && 'GET' === $_SERVER['REQUEST_METHOD'] && ! isset( $_REQUEST['action'] ) && ! isset( $_REQUEST['interim-login'] ) ) {
			if ( is_user_logged_in() ) {
				wp_safe_redirect( $this->redirect_to_restaurant_hub_after_login() );
				exit;
			}

			$signup_page_id = $this->get_signup_page_id();
			if ( $signup_page_id > 0 ) {
				wp_safe_redirect( get_permalink( $signup_page_id ) );
				exit;
			}
		}
	}

	/**
	 * Registers the custom account endpoint.
	 *
	 * @return void
	 */
	public function register_account_endpoint() {
		add_rewrite_endpoint( $this->endpoint, EP_ROOT | EP_PAGES );
	}

	/**
	 * Registers the account hub shortcode.
	 *
	 * @return void
	 */
	public function register_account_shortcode() {
		add_shortcode( $this->shortcode, array( $this, 'render_account_shortcode' ) );
		add_shortcode( 'restaurant_account', array( $this, 'render_account_shortcode' ) );
		add_shortcode( 'restaurant_signup', array( $this, 'render_signup_shortcode' ) );
		add_shortcode( 'restaurant_login_logout', array( $this, 'render_login_logout_shortcode' ) );
	}

	/**
	 * Renders a login/logout button.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_login_logout_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'class' => '',
			),
			(array) $atts,
			'restaurant_login_logout'
		);

		$output = '<div class="restaurant-auth-nav">';
		$current_url = home_url( add_query_arg( array(), $GLOBALS['wp']->request ) );

		if ( is_user_logged_in() ) {
			$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
			$logout_url  = $this->get_logout_url();

			$is_account_active = ( function_exists( 'is_account_page' ) && is_account_page() ) || ( rtrim( $current_url, '/' ) === rtrim( $account_url, '/' ) );
			$account_class     = $is_account_active ? trim( $atts['class'] . ' is-active' ) : $atts['class'];

			$output .= sprintf(
				'<a href="%s" class="%s"><span class="dashicons dashicons-admin-users"></span> %s</a>',
				esc_url( $account_url ),
				esc_attr( $account_class ),
				esc_html__( 'My Account', 'restaurant-food-services' )
			);

			$output .= sprintf(
				'<a href="%s" class="%s"><span class="dashicons dashicons-signout"></span> %s</a>',
				esc_url( $logout_url ),
				esc_attr( $atts['class'] ),
				esc_html__( 'Sign Out', 'restaurant-food-services' )
			);
		} else {
			$signup_page_id = $this->get_signup_page_id();
			$url            = ( $signup_page_id > 0 ) ? get_permalink( $signup_page_id ) : wp_login_url();
			
			$is_login_active = ( rtrim( $current_url, '/' ) === rtrim( $url, '/' ) );
			$login_class     = $is_login_active ? trim( $atts['class'] . ' is-active' ) : $atts['class'];

			$output .= sprintf(
				'<a href="%s" class="%s"><span class="dashicons dashicons-admin-users"></span> %s</a>',
				esc_url( $url ),
				esc_attr( $login_class ),
				esc_html__( 'Sign In', 'restaurant-food-services' )
			);
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Renders a standalone frontend signup shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_signup_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'Create Your Account', 'restaurant-food-services' ),
			),
			(array) $atts,
			'restaurant_signup'
		);

		if ( is_user_logged_in() ) {
			$account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
			$logout_url  = $this->get_logout_url();

			return sprintf(
				'<div class="woocommerce"><div class="woocommerce-message"><p>%s</p><p><a class="button" href="%s">%s</a> <a class="button button-secondary" href="%s">%s</a></p></div></div>',
				esc_html__( 'You are already signed in.', 'restaurant-food-services' ),
				esc_url( $account_url ),
				esc_html__( 'Go to My Account', 'restaurant-food-services' ),
				esc_url( $logout_url ),
				esc_html__( 'Log Out', 'restaurant-food-services' )
			);
		}

		ob_start();
		?>
		<section class="restaurant-account-shortcode woocommerce">
			<header class="restaurant-frontend-page__header">
				<h2><?php echo esc_html( $atts['title'] ); ?></h2>
			</header>
			<?php echo $this->render_signup_form_only_view(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Handles draft delete action from account UI.
	 *
	 * @return void
	 */
	public function handle_draft_delete_action() {
		if ( ! is_user_logged_in() || ! isset( $_GET['restaurant_delete_catering_draft'] ) ) {
			return;
		}

		$draft_id = absint( wp_unslash( $_GET['restaurant_delete_catering_draft'] ) );
		$nonce    = isset( $_GET['_restaurant_draft_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_restaurant_draft_nonce'] ) ) : '';

		if ( $draft_id <= 0 || ! wp_verify_nonce( $nonce, 'restaurant_delete_catering_draft_' . $draft_id ) ) {
			return;
		}

		$draft_post = get_post( $draft_id );

		if ( ! $draft_post || 'catering_request' !== $draft_post->post_type || (int) $draft_post->post_author !== get_current_user_id() ) {
			return;
		}

		wp_trash_post( $draft_id );

		$current_draft_id = absint( get_user_meta( get_current_user_id(), '_restaurant_catering_draft_id', true ) );

		if ( $current_draft_id === $draft_id ) {
			delete_user_meta( get_current_user_id(), '_restaurant_catering_draft_id' );
		}

		$redirect = remove_query_arg(
			array( 'restaurant_delete_catering_draft', '_restaurant_draft_nonce' ),
			wc_get_account_endpoint_url( $this->endpoint )
		);
		$redirect = add_query_arg( 'restaurant_draft_deleted', 1, $redirect );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Inserts the custom account hub item into the WooCommerce menu.
	 *
	 * @param array<string,string> $items Existing menu items.
	 *
	 * @return array<string,string>
	 */
	public function add_account_menu_item( $items ) {
		// Remove the default "downloads" menu item if present (user requested)
		if ( isset( $items['downloads'] ) ) {
			unset( $items['downloads'] );
		}

		$menu_items = array();

		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$menu_items[ $this->endpoint ] = esc_html__( 'Restaurant Hub', 'restaurant-food-services' );
			}

			$menu_items[ $key ] = $label;
		}

		if ( ! isset( $menu_items[ $this->endpoint ] ) ) {
			$menu_items[ $this->endpoint ] = esc_html__( 'Restaurant Hub', 'restaurant-food-services' );
		}

		return $menu_items;
	}

	/**
	 * Renders the custom account endpoint.
	 *
	 * @return void
	 */
	public function render_account_endpoint() {
		echo $this->render_account_view(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Redirects users to the restaurant hub after login.
	 *
	 * @return string
	 */
	public function redirect_to_restaurant_hub_after_login() {
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			return wc_get_account_endpoint_url( $this->endpoint );
		}
		return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
	}

	/**
	 * Redirects users to the signup/login page after logout.
	 *
	 * @return string
	 */
	public function redirect_to_signup_after_logout() {
		$signup_page_id = $this->get_signup_page_id();
		if ( $signup_page_id > 0 ) {
			return get_permalink( $signup_page_id );
		}
		// Fallback to login page if no signup page found
		return function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
	}

	/**
	 * Finds the page ID containing the signup shortcode.
	 *
	 * @return int
	 */
	protected function get_signup_page_id() {
		$cached_id = absint( get_transient( 'restaurant_food_services_signup_page_id' ) );
		if ( $cached_id > 0 ) {
			return $cached_id;
		}

		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => '[restaurant_signup',
			)
		);

		if ( ! empty( $pages ) && isset( $pages[0]->ID ) ) {
			set_transient( 'restaurant_food_services_signup_page_id', absint( $pages[0]->ID ), DAY_IN_SECONDS );
			return absint( $pages[0]->ID );
		}

		return 0;
	}

	/**
	 * Returns the logout URL with redirect to signup/login page.
	 *
	 * @return string
	 */
	protected function get_logout_url() {
		$redirect_url = $this->redirect_to_signup_after_logout();
		
		if ( function_exists( 'wc_logout_url' ) ) {
			return wc_logout_url( $redirect_url );
		}
		
		return wp_logout_url( $redirect_url );
	}

	/**
	 * Renders the account shortcode.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 *
	 * @return string
	 */
	public function render_account_shortcode( $atts = array() ) {
		$atts = shortcode_atts(
			array(
				'title' => __( 'My Restaurant Hub', 'restaurant-food-services' ),
			),
			(array) $atts,
			$this->shortcode
		);

		ob_start();
		?>
		<section class="restaurant-account-shortcode woocommerce">
			<header class="restaurant-frontend-page__header">
				<h2><?php echo esc_html( $atts['title'] ); ?></h2>
			</header>
			<?php echo $this->render_account_view(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Renders a friendly login intro on WooCommerce's sign-in screen.
	 *
	 * @return void
	 */
	public function render_login_intro() {
		if ( is_user_logged_in() ) {
			return;
		}

		?>
		<div class="restaurant-account-auth__intro">
			<p class="restaurant-account-auth__eyebrow"><?php esc_html_e( 'Welcome back', 'restaurant-food-services' ); ?></p>
			<h2><?php esc_html_e( 'Sign in to manage your restaurant orders', 'restaurant-food-services' ); ?></h2>
			<p><?php esc_html_e( 'Use your account to track catering requests, weekly meal plan orders, saved drafts, and billing details.', 'restaurant-food-services' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Returns the full account view.
	 *
	 * @return string
	 */
	protected function render_account_view() {
		if ( ! is_user_logged_in() ) {
			return $this->render_auth_view();
		}

		$user          = wp_get_current_user();
		$account_url    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
		$edit_account   = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'edit-account' ) : wc_get_endpoint_url( 'edit-account', '', $account_url );
		$edit_address   = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'edit-address' ) : wc_get_endpoint_url( 'edit-address', '', $account_url );
		$orders         = $this->get_customer_orders( (int) $user->ID );
		$grouped_orders  = $this->group_orders_by_type( $orders );
		$drafts         = $this->get_saved_catering_drafts( (int) $user->ID );
		$active_requests = $this->get_active_catering_requests( (int) $user->ID );
		$profile        = $this->get_profile_summary( $user );
		$catering_url   = $this->get_catering_page_url();
		$meal_plans_url = $this->get_meal_plans_page_url();
		$shop_url       = $this->get_shop_url();

		ob_start();
		?>
		<div class="restaurant-account-hub">
			<?php if ( isset( $_GET['restaurant_draft_deleted'] ) ) : ?>
				<div class="woocommerce-message" role="status"><?php esc_html_e( 'Draft deleted.', 'restaurant-food-services' ); ?></div>
			<?php endif; ?>
			<section class="restaurant-account-hero">
				<div>
					<p class="restaurant-account-hero__eyebrow"><?php esc_html_e( 'My Account', 'restaurant-food-services' ); ?></p>
					<h2><?php echo esc_html( sprintf( __( 'Hello, %s', 'restaurant-food-services' ), $profile['display_name'] ) ); ?></h2>
					<p><?php esc_html_e( 'Keep track of your catering requests, weekly meal plan requests, direct purchases, and saved drafts in one place.', 'restaurant-food-services' ); ?></p>
				</div>
				<div class="restaurant-account-hero__actions">
					<a class="button" href="<?php echo esc_url( $edit_account ); ?>"><?php esc_html_e( 'Edit Account', 'restaurant-food-services' ); ?></a>
					<a class="button" href="<?php echo esc_url( $edit_address ); ?>"><?php esc_html_e( 'Addresses', 'restaurant-food-services' ); ?></a>
					<a class="button button-secondary" href="<?php echo esc_url( $this->get_logout_url() ); ?>"><?php esc_html_e( 'Log Out', 'restaurant-food-services' ); ?></a>
				</div>
			</section>

			<section class="restaurant-account-quick-actions">
				<h3><?php esc_html_e( 'Quick Actions', 'restaurant-food-services' ); ?></h3>
				<div class="restaurant-quick-actions-grid">
					<?php if ( $catering_url ) : ?>
						<a href="<?php echo esc_url( $catering_url ); ?>" class="restaurant-quick-action-button restaurant-quick-action-button--catering">
							<span class="restaurant-quick-action-icon">🍽️</span>
							<span class="restaurant-quick-action-label"><?php esc_html_e( 'Request Catering', 'restaurant-food-services' ); ?></span>
						</a>
					<?php endif; ?>
					<?php if ( $meal_plans_url ) : ?>
						<a href="<?php echo esc_url( $meal_plans_url ); ?>" class="restaurant-quick-action-button restaurant-quick-action-button--meals">
							<span class="restaurant-quick-action-icon">🥗</span>
							<span class="restaurant-quick-action-label"><?php esc_html_e( 'Meal Plans', 'restaurant-food-services' ); ?></span>
						</a>
					<?php endif; ?>
					<?php if ( $shop_url ) : ?>
						<a href="<?php echo esc_url( $shop_url ); ?>" class="restaurant-quick-action-button restaurant-quick-action-button--shop">
							<span class="restaurant-quick-action-icon">🛒</span>
							<span class="restaurant-quick-action-label"><?php esc_html_e( 'Shop', 'restaurant-food-services' ); ?></span>
						</a>
					<?php endif; ?>
				</div>
			</section>

			<div class="restaurant-account-overview-grid">
				<section class="restaurant-account-card restaurant-account-profile-card">
					<h3><?php esc_html_e( 'Your Details', 'restaurant-food-services' ); ?></h3>
					<ul class="restaurant-account-profile-list">
						<li><strong><?php esc_html_e( 'Name', 'restaurant-food-services' ); ?></strong><span><?php echo esc_html( $profile['full_name'] ); ?></span></li>
						<li><strong><?php esc_html_e( 'Email', 'restaurant-food-services' ); ?></strong><span><?php echo esc_html( $profile['email'] ); ?></span></li>
						<li><strong><?php esc_html_e( 'Phone', 'restaurant-food-services' ); ?></strong><span><?php echo esc_html( $profile['phone'] ); ?></span></li>
						<li><strong><?php esc_html_e( 'Billing Address', 'restaurant-food-services' ); ?></strong><span><?php echo esc_html( $profile['billing_address'] ); ?></span></li>
					</ul>
				</section>

				<section class="restaurant-account-card restaurant-account-stats-card">
					<h3><?php esc_html_e( 'Order Summary', 'restaurant-food-services' ); ?></h3>
					<div class="restaurant-account-stats">
						<?php foreach ( $this->get_order_summary_cards( $grouped_orders ) as $card ) : ?>
							<div class="restaurant-account-stat">
								<strong><?php echo esc_html( $card['label'] ); ?></strong>
								<span><?php echo esc_html( $card['count'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</section>
			</div>

			<section class="restaurant-account-card restaurant-account-drafts-card">
				<div class="restaurant-account-section-header">
					<h3><?php esc_html_e( 'Saved Drafts', 'restaurant-food-services' ); ?></h3>
					<span><?php echo esc_html( sprintf( _n( '%d draft', '%d drafts', count( $drafts ), 'restaurant-food-services' ), count( $drafts ) ) ); ?></span>
				</div>
				<?php echo $this->render_drafts_list( $drafts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>

			<section class="restaurant-account-card restaurant-account-requests-card">
				<div class="restaurant-account-section-header">
					<h3><?php esc_html_e( 'Active Catering Requests', 'restaurant-food-services' ); ?></h3>
					<span><?php echo esc_html( sprintf( _n( '%d request', '%d requests', count( $active_requests ), 'restaurant-food-services' ), count( $active_requests ) ) ); ?></span>
				</div>
				<?php echo $this->render_catering_requests_list( $active_requests ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</section>

			<?php foreach ( $this->get_order_group_configs() as $group_key => $group_config ) : ?>
				<section class="restaurant-account-card restaurant-account-orders-card restaurant-account-orders-card--<?php echo esc_attr( $group_key ); ?>">
					<div class="restaurant-account-section-header">
						<div>
							<h3><?php echo esc_html( $group_config['title'] ); ?></h3>
							<p><?php echo esc_html( $group_config['description'] ); ?></p>
						</div>
						<span class="restaurant-account-section-count"><?php echo esc_html( count( $grouped_orders[ $group_key ] ) ); ?></span>
					</div>
					<?php echo $this->render_orders_table( $grouped_orders[ $group_key ], $group_config['empty_message'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</section>
			<?php endforeach; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Renders the logged-out authentication view.
	 *
	 * @return string
	 */
	protected function render_auth_view() {
		return $this->render_signup_form_only_view();
	}

	/**
	 * Renders the signup-first frontend authentication interface.
	 *
	 * @return string
	 */
	protected function render_signup_form_only_view() {
		$registration_enabled = 'yes' === get_option( 'woocommerce_enable_myaccount_registration', 'yes' );
		$generate_username    = 'yes' === get_option( 'woocommerce_registration_generate_username' );
		$generate_password    = 'yes' === get_option( 'woocommerce_registration_generate_password' );
		$default_tab          = $this->get_signup_auth_default_tab();

		if ( ! $registration_enabled ) {
			$default_tab = 'signin';
		}

		ob_start();
		?>
		<div class="restaurant-account-auth">
			<section class="restaurant-account-auth__card restaurant-account-auth__copy">
				<p class="restaurant-account-auth__eyebrow"><?php echo esc_html( sprintf( __( 'Join %s', 'restaurant-food-services' ), get_bloginfo( 'name' ) ) ); ?></p>
				<h2><?php esc_html_e( 'Manage your account in one place', 'restaurant-food-services' ); ?></h2>
				<p><?php esc_html_e( 'Start with signup, then switch to sign in anytime to manage catering requests, meal plans, order history, and saved drafts.', 'restaurant-food-services' ); ?></p>
			</section>
			<section class="restaurant-account-auth__card restaurant-account-auth__form" data-auth-switcher data-auth-default-tab="<?php echo esc_attr( $default_tab ); ?>">
					<?php if ( $registration_enabled ) : ?>
						<div class="restaurant-account-auth__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Sign up or sign in', 'restaurant-food-services' ); ?>">
							<button type="button" id="restaurant-auth-signup-tab" class="restaurant-account-auth__tab<?php echo 'signup' === $default_tab ? ' is-active' : ''; ?>" role="tab" data-auth-target="signup" aria-controls="restaurant-auth-signup-panel" aria-selected="<?php echo 'signup' === $default_tab ? 'true' : 'false'; ?>"><?php esc_html_e( 'Sign up', 'restaurant-food-services' ); ?></button>
							<button type="button" id="restaurant-auth-signin-tab" class="restaurant-account-auth__tab<?php echo 'signin' === $default_tab ? ' is-active' : ''; ?>" role="tab" data-auth-target="signin" aria-controls="restaurant-auth-signin-panel" aria-selected="<?php echo 'signin' === $default_tab ? 'true' : 'false'; ?>"><?php esc_html_e( 'Sign in', 'restaurant-food-services' ); ?></button>
						</div>

						<div class="restaurant-account-auth__panel" data-auth-panel="signup" id="restaurant-auth-signup-panel" role="tabpanel" aria-labelledby="restaurant-auth-signup-tab"<?php echo 'signup' === $default_tab ? '' : ' hidden'; ?>>
						<form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?> >
							<?php do_action( 'woocommerce_register_form_start' ); ?>

							<?php if ( ! $generate_username ) : ?>
								<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
									<label for="reg_username"><?php esc_html_e( 'Username', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
									<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username" value="<?php echo isset( $_POST['username'] ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" />
								</p>
							<?php endif; ?>

							<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
								<label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
								<input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo isset( $_POST['email'] ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" />
							</p>

							<?php if ( ! $generate_password ) : ?>
								<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
									<label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
									<input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" />
								</p>
							<?php else : ?>
								<p><?php esc_html_e( 'A password will be sent to your email address.', 'woocommerce' ); ?></p>
							<?php endif; ?>

							<?php do_action( 'woocommerce_register_form' ); ?>
							<?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
							<p class="woocommerce-form-row form-row">
								<button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"><?php esc_html_e( 'Sign Up', 'restaurant-food-services' ); ?></button>
							</p>

							<?php do_action( 'woocommerce_register_form_end' ); ?>
						</form>
						</div>
					<?php else : ?>
						<div class="woocommerce-info"><p><?php esc_html_e( 'Signup is currently disabled. Please sign in instead or contact the site administrator.', 'restaurant-food-services' ); ?></p></div>
					<?php endif; ?>

					<div class="restaurant-account-auth__panel" data-auth-panel="signin" id="restaurant-auth-signin-panel" role="tabpanel" aria-labelledby="restaurant-auth-signin-tab"<?php echo 'signin' === $default_tab ? '' : ' hidden'; ?>>
						<?php echo $this->render_signin_form( $registration_enabled ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
			</section>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Returns the preferred default auth tab for the signup shortcode.
	 *
	 * @return string
	 */
	protected function get_signup_auth_default_tab() {
		$default_tab = 'signup';

		if ( isset( $_POST['login'] ) ) {
			$default_tab = 'signin';
		} elseif ( isset( $_POST['register'] ) ) {
			$default_tab = 'signup';
		}

		if ( isset( $_GET['auth'] ) ) {
			$requested_tab = sanitize_key( wp_unslash( $_GET['auth'] ) );

			if ( in_array( $requested_tab, array( 'signup', 'signin' ), true ) ) {
				$default_tab = $requested_tab;
			}
		}

		return $default_tab;
	}

	/**
	 * Renders a WooCommerce-compatible sign-in form.
	 *
	 * @return string
	 */
	protected function render_signin_form( $allow_signup_switch = true ) {
		ob_start();
		?>
		<form class="woocommerce-form woocommerce-form-login login" method="post">
			<?php do_action( 'woocommerce_login_form_start' ); ?>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="username"><?php esc_html_e( 'Username or email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username" value="<?php echo isset( $_POST['username'] ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" />
			</p>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" />
			</p>

			<?php do_action( 'woocommerce_login_form' ); ?>
			<?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>

			<p class="form-row">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" <?php checked( ! empty( $_POST['rememberme'] ) ); ?> />
					<span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
				</label>
				<button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>"><?php esc_html_e( 'Sign In', 'restaurant-food-services' ); ?></button>
			</p>
			<p class="woocommerce-LostPassword lost_password">
				<a href="<?php echo esc_url( function_exists( 'wc_lostpassword_url' ) ? wc_lostpassword_url() : wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a>
			</p>

			<?php if ( $allow_signup_switch ) : ?>
				<p class="restaurant-account-auth__switch-note">
					<?php esc_html_e( 'Need a new account?', 'restaurant-food-services' ); ?>
				</p>
				<p class="form-row restaurant-account-auth__switch-action">
					<button type="button" class="woocommerce-button button button-secondary restaurant-account-auth__switch-button" data-auth-target="signup">
						<?php esc_html_e( 'Register / Sign up instead', 'restaurant-food-services' ); ?>
					</button>
				</p>
			<?php endif; ?>

			<?php do_action( 'woocommerce_login_form_end' ); ?>
		</form>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Returns profile summary data.
	 *
	 * @param \WP_User $user Current user object.
	 *
	 * @return array<string,string>
	 */
	protected function get_profile_summary( $user ) {
		$customer = function_exists( 'WC' ) && WC()->customer ? WC()->customer : null;
		$first    = trim( (string) get_user_meta( (int) $user->ID, 'first_name', true ) );
		$last     = trim( (string) get_user_meta( (int) $user->ID, 'last_name', true ) );
		$billing  = '';

		if ( $customer ) {
			$billing = trim( implode( ', ', array_filter( array(
				$customer->get_billing_address_1(),
				$customer->get_billing_city(),
				$customer->get_billing_state(),
				$customer->get_billing_postcode(),
			) ) ) );
		}

		return array(
			'display_name'    => $user->display_name ? $user->display_name : $user->user_login,
			'full_name'       => trim( $first . ' ' . $last ) ? trim( $first . ' ' . $last ) : ( $user->display_name ? $user->display_name : $user->user_login ),
			'email'           => (string) $user->user_email,
			'phone'           => $customer ? (string) $customer->get_billing_phone() : (string) get_user_meta( (int) $user->ID, 'billing_phone', true ),
			'billing_address' => $billing ? $billing : esc_html__( 'Not set yet', 'restaurant-food-services' ),
		);
	}

	/**
	 * Returns the current customer's orders.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array<int,\WC_Order>
	 */
	protected function get_customer_orders( $user_id ) {
		if ( $user_id <= 0 || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'status'      => array_keys( wc_get_order_statuses() ),
			)
		);

		return is_array( $orders ) ? $orders : array();
	}

	/**
	 * Groups orders by service type.
	 *
	 * @param array<int,\WC_Order> $orders Orders list.
	 *
	 * @return array<string,array<int,\WC_Order>>
	 */
	protected function group_orders_by_type( $orders ) {
		$groups = array(
			'catering'          => array(),
			'weekly_meal_plan'  => array(),
			'direct_purchase'   => array(),
		);

		foreach ( $orders as $order ) {
			if ( ! $order instanceof \WC_Order ) {
				continue;
			}

			$type = $this->get_order_type( $order );
			$groups[ $type ][] = $order;
		}

		return $groups;
	}

	/**
	 * Returns summary cards for grouped orders.
	 *
	 * @param array<string,array<int,\WC_Order>> $grouped_orders Grouped orders.
	 *
	 * @return array<int,array<string,int|string>>
	 */
	protected function get_order_summary_cards( $grouped_orders ) {
		$configs = $this->get_order_group_configs();
		$cards   = array();

		foreach ( $configs as $group_key => $config ) {
			$cards[] = array(
				'label' => $config['title'],
				'count' => isset( $grouped_orders[ $group_key ] ) ? count( $grouped_orders[ $group_key ] ) : 0,
			);
		}

		return $cards;
	}

	/**
	 * Returns the grouped order section configuration.
	 *
	 * @return array<string,array<string,string>>
	 */
	protected function get_order_group_configs() {
		return array(
			'catering' => array(
				'title'         => esc_html__( 'Catering Requests', 'restaurant-food-services' ),
				'description'   => esc_html__( 'Orders created from approved catering requests.', 'restaurant-food-services' ),
				'empty_message' => esc_html__( 'No catering orders yet.', 'restaurant-food-services' ),
			),
			'weekly_meal_plan' => array(
				'title'         => esc_html__( 'Weekly Meal Plan Requests', 'restaurant-food-services' ),
				'description'   => esc_html__( 'Meal plan orders and renewals tied to your weekly subscription.', 'restaurant-food-services' ),
				'empty_message' => esc_html__( 'No weekly meal plan orders yet.', 'restaurant-food-services' ),
			),
			'direct_purchase' => array(
				'title'         => esc_html__( 'Direct Purchases', 'restaurant-food-services' ),
				'description'   => esc_html__( 'Standard WooCommerce orders not tied to a service workflow.', 'restaurant-food-services' ),
				'empty_message' => esc_html__( 'No direct purchases yet.', 'restaurant-food-services' ),
			),
		);
	}

	/**
	 * Infers the order type.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 *
	 * @return string
	 */
	protected function get_order_type( $order ) {
		$explicit = sanitize_key( (string) $order->get_meta( '_restaurant_order_type', true ) );

		if ( in_array( $explicit, array( 'catering', 'weekly_meal_plan', 'direct_purchase' ), true ) ) {
			return $explicit;
		}

		if ( $order->get_meta( '_catering_request_id', true ) || $order->get_meta( '_restaurant_catering_request_id', true ) ) {
			return 'catering';
		}

		if ( $order->get_meta( '_restaurant_meal_plan_selection', true ) || $order->get_meta( 'restaurant_subscription_id', true ) || $order->get_meta( 'plan_type', true ) ) {
			return 'weekly_meal_plan';
		}

		return 'direct_purchase';
	}

	/**
	 * Renders the saved draft list.
	 *
	 * @param array<int,\WP_Post> $drafts Draft posts.
	 *
	 * @return string
	 */
	protected function render_drafts_list( $drafts ) {
		if ( empty( $drafts ) ) {
			return '<p>' . esc_html__( 'No saved drafts found yet.', 'restaurant-food-services' ) . '</p>';
		}

		ob_start();
		?>
		<ul class="restaurant-account-draft-list">
			<?php foreach ( $drafts as $draft ) : ?>
				<?php
				$event_type = (string) get_post_meta( $draft->ID, 'event_type', true );
				$event_date = (string) get_post_meta( $draft->ID, 'event_date', true );
				$location   = (string) get_post_meta( $draft->ID, 'location', true );
				$guest_count = absint( get_post_meta( $draft->ID, 'guest_count', true ) );
				$updated_at  = get_post_modified_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), false, $draft, true );
				?>
				<li class="restaurant-account-draft-item">
					<div>
						<strong><?php echo esc_html( $draft->post_title ? $draft->post_title : __( 'Saved Draft', 'restaurant-food-services' ) ); ?></strong>
						<p><?php echo esc_html( trim( implode( ' • ', array_filter( array(
							$event_type ? $event_type : '',
							$event_date ? $event_date : '',
							$location ? $location : '',
							$guest_count ? sprintf( _n( '%d guest', '%d guests', $guest_count, 'restaurant-food-services' ), $guest_count ) : '',
						) ) ) ) ); ?></p>
						<small><?php echo esc_html( sprintf( __( 'Last updated %s', 'restaurant-food-services' ), $updated_at ) ); ?></small>
					</div>
					<div class="restaurant-account-draft-item__actions">
						<a class="button" href="<?php echo esc_url( $this->get_resume_draft_url( (int) $draft->ID ) ); ?>"><?php esc_html_e( 'Continue', 'restaurant-food-services' ); ?></a>
						<a class="button" href="<?php echo esc_url( $this->get_delete_draft_url( (int) $draft->ID ) ); ?>"><?php esc_html_e( 'Delete', 'restaurant-food-services' ); ?></a>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Gets active catering requests for the current user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array<int,\WP_Post>
	 */
	protected function get_active_catering_requests( $user_id ) {
		$args = array(
			'post_type'      => 'catering_request',
			'posts_per_page' => -1,
			'author'         => absint( $user_id ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		return get_posts( $args );
	}

	/**
	 * Renders a list of catering requests for the account hub.
	 *
	 * @param array<int,\WP_Post> $catering_requests Catering request posts.
	 *
	 * @return string
	 */
	protected function render_catering_requests_list( $catering_requests ) {
		ob_start();
		?>
		<div class="restaurant-catering-requests-list">
			<?php foreach ( $catering_requests as $request ) : ?>
				<?php
				$location       = sanitize_text_field( (string) get_post_meta( $request->ID, 'location', true ) );
				$event_date     = sanitize_text_field( (string) get_post_meta( $request->ID, 'event_date', true ) );
				$guest_count    = absint( get_post_meta( $request->ID, 'guest_count', true ) );
				$status         = sanitize_key( (string) get_post_meta( $request->ID, 'catering_status', true ) );
				$total_price    = (float) get_post_meta( $request->ID, 'total_price', true );

				if ( empty( $status ) ) {
					$status = 'pending';
				}

				$status_labels = array(
					'pending'  => esc_html__( 'Pending Review', 'restaurant-food-services' ),
					'approved' => esc_html__( 'Approved', 'restaurant-food-services' ),
					'rejected' => esc_html__( 'Rejected', 'restaurant-food-services' ),
				);

				$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : esc_html__( 'Unknown', 'restaurant-food-services' );
				$detail_url   = add_query_arg( 'catering_request', $request->ID, home_url( '/my-account/my-catering-requests' ) );
				?>
				<div class="restaurant-catering-request-item">
					<div class="restaurant-catering-request-header">
						<div class="restaurant-catering-request-info">
							<h4><?php echo esc_html( $location ); ?></h4>
							<p class="restaurant-catering-request-date"><?php echo esc_html( $event_date ); ?> • <?php echo esc_html( $guest_count ); ?> <?php esc_html_e( 'guests', 'restaurant-food-services' ); ?></p>
						</div>
						<div class="restaurant-catering-request-meta">
							<span class="restaurant-catering-status-badge restaurant-catering-status-badge--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_label ); ?></span>
							<span class="restaurant-catering-request-price"><?php echo wp_kses_post( wc_price( $total_price ) ); ?></span>
						</div>
					</div>
					<div class="restaurant-catering-request-actions">
						<a href="<?php echo esc_url( $detail_url ); ?>" class="button button-small"><?php esc_html_e( 'View Details & Chat', 'restaurant-food-services' ); ?></a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Returns saved catering drafts for the current user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array<int,\WP_Post>
	 */
	protected function get_saved_catering_drafts( $user_id ) {
		if ( $user_id <= 0 ) {
			return array();
		}

		$drafts = get_posts(
			array(
				'post_type'      => 'catering_request',
				'post_status'    => 'draft',
				'author'         => $user_id,
				'posts_per_page' => -1,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		return is_array( $drafts ) ? $drafts : array();
	}

	/**
	 * Returns the best guess URL for the catering page containing shortcode.
	 *
	 * @return string
	 */
	protected function get_catering_page_url() {
		$cached_id = absint( get_transient( 'restaurant_food_services_catering_page_id' ) );

		if ( $cached_id > 0 ) {
			$cached_url = get_permalink( $cached_id );

			if ( $cached_url ) {
				return (string) $cached_url;
			}
		}

		$page = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => '[restaurant_catering',
			)
		);

		if ( ! empty( $page ) && isset( $page[0]->ID ) ) {
			set_transient( 'restaurant_food_services_catering_page_id', absint( $page[0]->ID ), DAY_IN_SECONDS );
			$url = get_permalink( (int) $page[0]->ID );

			if ( $url ) {
				return (string) $url;
			}
		}

		return home_url( '/' );
	}

	/**
	 * Returns the URL for the meal plans page.
	 *
	 * @return string
	 */
	protected function get_meal_plans_page_url() {
		$cached_id = absint( get_transient( 'restaurant_food_services_meal_plans_page_id' ) );

		if ( $cached_id > 0 ) {
			$cached_url = get_permalink( $cached_id );

			if ( $cached_url ) {
				return (string) $cached_url;
			}
		}

		$page = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				's'              => '[restaurant_meal_plans',
			)
		);

		if ( ! empty( $page ) && isset( $page[0]->ID ) ) {
			set_transient( 'restaurant_food_services_meal_plans_page_id', absint( $page[0]->ID ), DAY_IN_SECONDS );
			$url = get_permalink( (int) $page[0]->ID );

			if ( $url ) {
				return (string) $url;
			}
		}

		return '';
	}

	/**
	 * Returns the URL for the WooCommerce shop page.
	 *
	 * @return string
	 */
	protected function get_shop_url() {
		if ( function_exists( 'wc_get_shop_page_url' ) ) {
			return wc_get_shop_page_url();
		}

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$shop_url = wc_get_page_permalink( 'shop' );
			if ( $shop_url ) {
				return $shop_url;
			}
		}

		return home_url( '/shop' );
	}

	/**
	 * Builds a draft resume URL for the catering wizard.
	 *
	 * @param int $draft_id Draft post ID.
	 *
	 * @return string
	 */
	protected function get_resume_draft_url( $draft_id ) {
		return (string) add_query_arg(
			array(
				'restaurant_draft_id' => absint( $draft_id ),
			),
			$this->get_catering_page_url()
		);
	}

	/**
	 * Builds a signed draft delete URL.
	 *
	 * @param int $draft_id Draft post ID.
	 *
	 * @return string
	 */
	protected function get_delete_draft_url( $draft_id ) {
		$draft_id = absint( $draft_id );

		return (string) add_query_arg(
			array(
				'restaurant_delete_catering_draft' => $draft_id,
				'_restaurant_draft_nonce'         => wp_create_nonce( 'restaurant_delete_catering_draft_' . $draft_id ),
			),
			wc_get_account_endpoint_url( $this->endpoint )
		);
	}

	/**
	 * Renders orders table for a grouped order category.
	 *
	 * @param array<int,\WC_Order> $orders Orders list.
	 * @param string $empty_message Message to show when no orders.
	 *
	 * @return string
	 */
	protected function render_orders_table( $orders, $empty_message = '' ) {
		if ( empty( $orders ) ) {
			return '<p>' . esc_html( $empty_message ?: __( 'No orders found.', 'restaurant-food-services' ) ) . '</p>';
		}

		ob_start();
		?>
		<table class="woocommerce-orders-table woocommerce-MyAccount-orders">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Order', 'woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Date', 'woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Status', 'woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Total', 'woocommerce' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $orders as $order ) : ?>
					<?php
					$order_id = $order->get_id();
					$order_number = $order->get_order_number();
					$order_date = wc_format_datetime( $order->get_date_created() );
					$order_status = $order->get_status();
					$order_total = $order->get_formatted_order_total();
					$order_url = $order->get_view_order_url();
					?>
					<tr>
						<td><a href="<?php echo esc_url( $order_url ); ?>"><?php echo esc_html( sprintf( _x( '#%s', 'hash before order number', 'woocommerce' ), $order_number ) ); ?></a></td>
						<td><?php echo esc_html( $order_date ); ?></td>
						<td><mark class="order-status status-<?php echo esc_attr( $order_status ); ?>"><span><?php echo esc_html( wc_get_order_status_name( $order_status ) ); ?></span></mark></td>
						<td><?php echo wp_kses_post( $order_total ); ?></td>
						<td><a href="<?php echo esc_url( $order_url ); ?>" class="button button-small"><?php esc_html_e( 'View', 'woocommerce' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php

		return (string) ob_get_clean();
	}
}
