<?php
/**
 * Catering module.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices\Modules;

use Restaurant\FoodServices\Loader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles catering module hooks.
 */
class Catering_Module extends Abstract_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	protected $slug = 'catering';

	/**
	 * Registers module hooks.
	 *
	 * @param Loader $loader Plugin loader instance.
	 *
	 * @return void
	 */
	public function register_hooks( Loader $loader ) {
		parent::register_hooks( $loader );
		$loader->add_action( 'init', $this, 'register_catering_features' );
		$loader->add_action( 'init', $this, 'register_catering_request_post_type' );
		$loader->add_action( 'init', $this, 'register_my_catering_requests_endpoint' );
		$loader->add_action( 'init', $this, 'register_catering_form_shortcode' );
		$loader->add_action( 'wp_footer', $this, 'handle_catering_form_submission' );
		$loader->add_action( 'add_meta_boxes_catering_request', $this, 'add_catering_admin_meta_boxes' );
		$loader->add_action( 'save_post_catering_request', $this, 'save_catering_request_meta' );
		$loader->add_action( 'init', $this, 'handle_convert_to_order_action' );
		$loader->add_filter( 'woocommerce_account_menu_items', $this, 'add_catering_menu_item' );
		$loader->add_action( 'woocommerce_account_my-catering-requests_endpoint', $this, 'render_my_catering_requests_page' );
		$loader->add_action( 'admin_menu', $this, 'register_catering_admin_menu' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_catering_admin_assets' );
		$loader->add_action( 'wp_ajax_restaurant_catering_settings_option_action', $this, 'ajax_catering_settings_option_action' );
	}

	/**
	 * Enqueues admin-only assets for catering settings.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_catering_admin_assets( $hook_suffix ) {
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'restaurant-catering-settings' !== $current_page && false === strpos( (string) $hook_suffix, 'restaurant-catering-settings' ) ) {
			return;
		}

		$script_path    = dirname( dirname( __DIR__ ) ) . '/assets/js/catering-settings-admin.js';
		$script_version = defined( 'RESTAURANT_FOOD_SERVICES_VERSION' ) ? RESTAURANT_FOOD_SERVICES_VERSION : '1.0.0';

		if ( file_exists( $script_path ) ) {
			$script_version = (string) filemtime( $script_path );
		}

		wp_enqueue_script(
			'restaurant-food-services-catering-settings-admin',
			RESTAURANT_FOOD_SERVICES_URL . 'assets/js/catering-settings-admin.js',
			array( 'jquery' ),
			$script_version,
			true
		);

		wp_localize_script(
			'restaurant-food-services-catering-settings-admin',
			'RestaurantFoodServicesCateringSettings',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'errorMessage'  => esc_html__( 'Request failed. Please refresh and try again.', 'restaurant-food-services' ),
			)
		);
	}

	/**
	 * Registers catering module features.
	 *
	 * @return void
	 */
	public function register_catering_features() {
		do_action( 'restaurant_food_services_catering_ready' );
	}

	/**
	 * Registers the catering form shortcode.
	 *
	 * @return void
	 */
	public function register_catering_form_shortcode() {
		add_shortcode( 'restaurant_catering_form', array( $this, 'render_catering_form' ) );
	}

	/**
	 * Renders the catering form shortcode.
	 *
	 * @return string
	 */
	public function render_catering_form() {
		$products = $this->get_catering_menu_products();

		ob_start();
		?>
		<form method="post" class="restaurant-catering-form" enctype="multipart/form-data">
			<?php wp_nonce_field( 'restaurant_catering_form_nonce', 'restaurant_catering_nonce' ); ?>

			<div class="form-group">
				<label for="event_date"><?php esc_html_e( 'Event Date', 'restaurant-food-services' ); ?> <span class="required">*</span></label>
				<input type="date" id="event_date" name="event_date" required>
			</div>

			<div class="form-group">
				<label for="guest_count"><?php esc_html_e( 'Guest Count', 'restaurant-food-services' ); ?> <span class="required">*</span></label>
				<input type="number" id="guest_count" name="guest_count" min="1" required>
			</div>

			<div class="form-group">
				<label><?php esc_html_e( 'Menu Items', 'restaurant-food-services' ); ?> <span class="required">*</span></label>
				<?php if ( ! empty( $products ) ) : ?>
					<div class="menu-items-container">
						<?php foreach ( $products as $product ) : ?>
							<div class="menu-item-row">
								<input type="checkbox" name="menu_items[]" value="<?php echo esc_attr( $product->get_id() ); ?>" id="menu_<?php echo esc_attr( $product->get_id() ); ?>">
								<label for="menu_<?php echo esc_attr( $product->get_id() ); ?>">
									<?php echo esc_html( $product->get_name() ); ?>
								</label>
								<input type="number" name="quantities[<?php echo esc_attr( $product->get_id() ); ?>]" min="1" placeholder="<?php esc_attr_e( 'Qty', 'restaurant-food-services' ); ?>" style="width: 60px; margin-left: 10px;">
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p><?php esc_html_e( 'No menu items available.', 'restaurant-food-services' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="form-group">
				<label for="location"><?php esc_html_e( 'Event Location', 'restaurant-food-services' ); ?> <span class="required">*</span></label>
				<input type="text" id="location" name="location" required>
			</div>

			<div class="form-group">
				<label for="special_requests"><?php esc_html_e( 'Special Requests', 'restaurant-food-services' ); ?></label>
				<textarea id="special_requests" name="special_requests" rows="4"></textarea>
			</div>

			<button type="submit" name="submit_catering_form" class="button button-primary">
				<?php esc_html_e( 'Submit Request', 'restaurant-food-services' ); ?>
			</button>
		</form>
		<style>
			.restaurant-catering-form .form-group { margin-bottom: 20px; }
			.restaurant-catering-form label { display: block; margin-bottom: 5px; font-weight: 600; }
			.restaurant-catering-form input[type="text"],
			.restaurant-catering-form input[type="date"],
			.restaurant-catering-form input[type="number"],
			.restaurant-catering-form textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
			.restaurant-catering-form .required { color: red; }
			.restaurant-catering-form .menu-item-row { margin-bottom: 10px; }
			.restaurant-catering-form .menu-item-row input[type="checkbox"] { margin-right: 10px; }
		</style>
		<?php

		return ob_get_clean();
	}

	/**
	 * Handles catering form submission.
	 *
	 * @return void
	 */
	public function handle_catering_form_submission() {
		if ( empty( $_POST['submit_catering_form'] ) ) {
			return;
		}

		if ( ! isset( $_POST['restaurant_catering_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['restaurant_catering_nonce'] ) ), 'restaurant_catering_form_nonce' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'restaurant-food-services' ) );
		}

		$event_date = isset( $_POST['event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_date'] ) ) : '';
		$guest_count = isset( $_POST['guest_count'] ) ? absint( wp_unslash( $_POST['guest_count'] ) ) : 0;
		$location = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';
		$special_requests = isset( $_POST['special_requests'] ) ? sanitize_textarea_field( wp_unslash( $_POST['special_requests'] ) ) : '';
		$menu_items = isset( $_POST['menu_items'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['menu_items'] ) ) : array();
		$quantities = isset( $_POST['quantities'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['quantities'] ) ) : array();

		if ( empty( $event_date ) || $guest_count <= 0 || empty( $location ) || empty( $menu_items ) ) {
			wp_die( esc_html__( 'Please fill in all required fields.', 'restaurant-food-services' ) );
		}

		$structured_items = $this->build_structured_menu_items( $menu_items, $quantities );

		if ( empty( $structured_items ) ) {
			wp_die( esc_html__( 'No valid menu items selected.', 'restaurant-food-services' ) );
		}

		$this->create_catering_request_post( $event_date, $guest_count, $location, $special_requests, $structured_items );
	}

	/**
	 * Gets available catering menu products.
	 *
	 * @return array<int,\WC_Product>
	 */
	protected function get_catering_menu_products() {
		$args = array(
			'posts_per_page' => -1,
			'orderby'        => 'name',
			'order'          => 'ASC',
		);

		return wc_get_products( $args );
	}

	/**
	 * Builds structured menu items from form data.
	 *
	 * @param array<int,int> $menu_items Menu product IDs.
	 * @param array<int,int> $quantities Quantities per product.
	 *
	 * @return array<int,array>
	 */
	protected function build_structured_menu_items( $menu_items, $quantities ) {
		$structured = array();

		foreach ( $menu_items as $product_id ) {
			if ( ! isset( $quantities[ $product_id ] ) || $quantities[ $product_id ] <= 0 ) {
				continue;
			}

			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$structured[] = array(
				'product_id'   => (int) $product_id,
				'product_name' => $product->get_name(),
				'quantity'     => (int) $quantities[ $product_id ],
				'price'        => (float) $product->get_price(),
			);
		}

		return $structured;
	}

	/**
	 * Creates a catering_request post from form data.
	 *
	 * @param string         $event_date        Event date.
	 * @param int            $guest_count       Number of guests.
	 * @param string         $location          Event location.
	 * @param string         $special_requests  Special requests.
	 * @param array<int,mixed> $structured_items Structured menu items.
	 *
	 * @return void
	 */
	protected function create_catering_request_post( $event_date, $guest_count, $location, $special_requests, $structured_items ) {
		$post_title = sprintf(
			'Catering Request - %s - %s',
			esc_html( $location ),
			esc_html( $event_date )
		);

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'catering_request',
				'post_title'  => $post_title,
				'post_status' => 'publish',
				'post_author' => get_current_user_id(),
			)
		);

		if ( is_wp_error( $post_id ) ) {
			wp_die( esc_html__( 'Failed to create catering request.', 'restaurant-food-services' ) );
		}

		update_post_meta( $post_id, 'event_date', $event_date );
		update_post_meta( $post_id, 'guest_count', $guest_count );
		update_post_meta( $post_id, 'location', $location );
		update_post_meta( $post_id, 'special_requests', $special_requests );
		update_post_meta( $post_id, 'menu_items', wp_json_encode( $structured_items ) );
		update_post_meta( $post_id, 'catering_status', 'pending' );

		do_action( 'restaurant_food_services_catering_request_submitted', (int) $post_id );

		wp_safe_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'blocking' => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		$redirect_url = wp_get_referer();

		if ( ! $redirect_url ) {
			$redirect_url = home_url( '/' );
		}

		wp_safe_redirect( add_query_arg( 'catering_request_created', $post_id, $redirect_url ) );
		exit;
	}

	/**
	 * Registers the catering_request custom post type.
	 *
	 * @return void
	 */
	public function register_catering_request_post_type() {
		$labels = array(
			'name'               => esc_html_x( 'Catering Requests', 'Post Type General Name', 'restaurant-food-services' ),
			'singular_name'      => esc_html_x( 'Catering Request', 'Post Type Singular Name', 'restaurant-food-services' ),
			'menu_name'          => esc_html__( 'Catering Requests', 'restaurant-food-services' ),
			'name_admin_bar'     => esc_html__( 'Catering Request', 'restaurant-food-services' ),
			'archives'           => esc_html__( 'Catering Request Archives', 'restaurant-food-services' ),
			'attributes'         => esc_html__( 'Catering Request Attributes', 'restaurant-food-services' ),
			'parent_item_colon'  => esc_html__( 'Parent Catering Request:', 'restaurant-food-services' ),
			'all_items'          => esc_html__( 'All Catering Requests', 'restaurant-food-services' ),
			'add_new_item'       => esc_html__( 'Add New Catering Request', 'restaurant-food-services' ),
			'add_new'            => esc_html__( 'Add New', 'restaurant-food-services' ),
			'new_item'           => esc_html__( 'New Catering Request', 'restaurant-food-services' ),
			'edit_item'          => esc_html__( 'Edit Catering Request', 'restaurant-food-services' ),
			'update_item'        => esc_html__( 'Update Catering Request', 'restaurant-food-services' ),
			'view_item'          => esc_html__( 'View Catering Request', 'restaurant-food-services' ),
			'view_items'         => esc_html__( 'View Catering Requests', 'restaurant-food-services' ),
			'search_items'       => esc_html__( 'Search Catering Request', 'restaurant-food-services' ),
			'not_found'          => esc_html__( 'Not Found', 'restaurant-food-services' ),
			'not_found_in_trash' => esc_html__( 'Not found in Trash', 'restaurant-food-services' ),
			'featured_image'     => esc_html__( 'Featured Image', 'restaurant-food-services' ),
			'set_featured_image' => esc_html__( 'Set featured image', 'restaurant-food-services' ),
			'remove_featured_image' => esc_html__( 'Remove featured image', 'restaurant-food-services' ),
			'use_featured_image' => esc_html__( 'Use as featured image', 'restaurant-food-services' ),
			'insert_into_item'   => esc_html__( 'Insert into Catering Request', 'restaurant-food-services' ),
			'uploaded_to_this_item' => esc_html__( 'Uploaded to this Catering Request', 'restaurant-food-services' ),
			'items_list'         => esc_html__( 'Catering Requests list', 'restaurant-food-services' ),
			'items_list_navigation' => esc_html__( 'Catering Requests list navigation', 'restaurant-food-services' ),
			'filter_items_list'  => esc_html__( 'Filter Catering Requests list', 'restaurant-food-services' ),
		);

		$args = array(
			'label'               => esc_html__( 'Catering Request', 'restaurant-food-services' ),
			'description'         => esc_html__( 'Admin-only custom post type for managing catering requests.', 'restaurant-food-services' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'custom-fields' ),
			'hierarchical'        => false,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'can_export'          => true,
			'exclude_from_search' => true,
			'capability_type'     => array( 'catering_request', 'catering_requests' ),
			'capabilities'        => array(
				'create_posts'       => 'manage_options',
				'edit_posts'         => 'manage_options',
				'edit_others_posts'  => 'manage_options',
				'publish_posts'      => 'manage_options',
				'read_private_posts' => 'manage_options',
				'delete_posts'       => 'manage_options',
				'delete_private_posts'   => 'manage_options',
				'delete_published_posts' => 'manage_options',
				'delete_others_posts'    => 'manage_options',
				'edit_private_posts'     => 'manage_options',
				'edit_published_posts'   => 'manage_options',
			),
			'map_meta_cap'        => true,
			'menu_icon'           => 'dashicons-clipboard',
		);

		register_post_type( 'catering_request', $args );
	}

	/**
	 * Adds admin meta boxes to catering_request posts.
	 *
	 * @return void
	 */
	public function add_catering_admin_meta_boxes() {
		add_meta_box(
			'catering_structured_request',
			esc_html__( 'Structured Request Data', 'restaurant-food-services' ),
			array( $this, 'render_structured_request_meta_box' ),
			'catering_request',
			'normal',
			'default'
		);

		add_meta_box(
			'catering_menu_items',
			esc_html__( 'Menu Items', 'restaurant-food-services' ),
			array( $this, 'render_menu_items_meta_box' ),
			'catering_request',
			'normal',
			'high'
		);

		add_meta_box(
			'catering_pricing',
			esc_html__( 'Pricing & Status', 'restaurant-food-services' ),
			array( $this, 'render_pricing_meta_box' ),
			'catering_request',
			'side',
			'high'
		);
	}

	/**
	 * Renders structured wizard request data.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function render_structured_request_meta_box( $post ) {
		$event_type           = sanitize_text_field( (string) get_post_meta( $post->ID, 'event_type', true ) );
		$event_date           = sanitize_text_field( (string) get_post_meta( $post->ID, 'event_date', true ) );
		$guest_count          = absint( get_post_meta( $post->ID, 'guest_count', true ) );
		$location             = sanitize_text_field( (string) get_post_meta( $post->ID, 'location', true ) );
		$selected_items_json  = (string) get_post_meta( $post->ID, 'selected_items', true );
		$serving_style        = sanitize_text_field( (string) get_post_meta( $post->ID, 'serving_style', true ) );
		$dietary_requirements = sanitize_textarea_field( (string) get_post_meta( $post->ID, 'dietary_requirements', true ) );
		$special_requests     = sanitize_textarea_field( (string) get_post_meta( $post->ID, 'special_requests', true ) );

		if ( '' === $dietary_requirements ) {
			$dietary_requirements = sanitize_textarea_field( (string) get_post_meta( $post->ID, 'dietary_needs', true ) );
		}

		echo '<table class="widefat striped" style="margin-top:10px">';
		echo '<tbody>';
		echo '<tr><th style="width:220px">' . esc_html__( 'Event Type', 'restaurant-food-services' ) . '</th><td>' . esc_html( $event_type ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Event Date', 'restaurant-food-services' ) . '</th><td>' . esc_html( $event_date ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Guest Count', 'restaurant-food-services' ) . '</th><td>' . esc_html( (string) $guest_count ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Location', 'restaurant-food-services' ) . '</th><td>' . esc_html( $location ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Serving Style', 'restaurant-food-services' ) . '</th><td>' . esc_html( $serving_style ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Dietary Requirements', 'restaurant-food-services' ) . '</th><td>' . nl2br( esc_html( $dietary_requirements ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Special Requests', 'restaurant-food-services' ) . '</th><td>' . nl2br( esc_html( $special_requests ) ) . '</td></tr>';
		echo '</tbody>';
		echo '</table>';

		echo '<p style="margin-top:12px"><strong>' . esc_html__( 'Selected Items (JSON)', 'restaurant-food-services' ) . '</strong></p>';
		echo '<textarea readonly style="width:100%;min-height:120px;font-family:monospace;">' . esc_textarea( $selected_items_json ) . '</textarea>';
	}

	/**
	 * Renders the menu items meta box.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function render_menu_items_meta_box( $post ) {
		wp_nonce_field( 'catering_request_nonce', 'catering_request_nonce_field' );

		$menu_items_json = get_post_meta( $post->ID, 'menu_items', true );
		$menu_items      = $this->decode_json_array( $menu_items_json );

		if ( empty( $menu_items ) ) {
			echo '<p>' . esc_html__( 'No menu items for this request.', 'restaurant-food-services' ) . '</p>';
			return;
		}

		echo '<table class="widefat catering-items-table" style="margin-top: 10px;">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'Item', 'restaurant-food-services' ) . '</th>';
		echo '<th>' . esc_html__( 'Price', 'restaurant-food-services' ) . '</th>';
		echo '<th style="width: 120px;">' . esc_html__( 'Quantity', 'restaurant-food-services' ) . '</th>';
		echo '<th style="width: 100px;">' . esc_html__( 'Subtotal', 'restaurant-food-services' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $menu_items as $index => $item ) {
			$product_id   = (int) $item['product_id'];
			$product_name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';
			$price        = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
			$quantity     = isset( $item['quantity'] ) ? intval( $item['quantity'] ) : 1;
			$subtotal     = $price * $quantity;

			echo '<tr>';
			echo '<td>' . esc_html( $product_name ) . '</td>';
			echo '<td>$' . number_format( $price, 2 ) . '</td>';
			echo '<td><input type="number" name="menu_item_quantities[' . esc_attr( $index ) . ']" value="' . esc_attr( $quantity ) . '" min="1" style="width: 100px;"></td>';
			echo '<td class="subtotal-cell">$' . number_format( $subtotal, 2 ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';

		echo '<script type="text/javascript">
			jQuery(function($) {
				$(document).on("change", "input[name^=\"menu_item_quantities\"]", function() {
					var $row = $(this).closest("tr");
					var price = parseFloat($row.find("td:eq(1)").text().replace("$", ""));
					var qty = parseInt($(this).val()) || 1;
					var subtotal = (price * qty).toFixed(2);
					$row.find(".subtotal-cell").text("$" + subtotal);
				});
			});
		</script>';
	}

	/**
	 * Renders the pricing and status meta box.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function render_pricing_meta_box( $post ) {
		$menu_items_json = get_post_meta( $post->ID, 'menu_items', true );
		$menu_items      = $this->decode_json_array( $menu_items_json );
		$total_price     = (float) get_post_meta( $post->ID, 'total_price', true );
		$status          = get_post_meta( $post->ID, 'catering_status', true );
		$converted_order_id = get_post_meta( $post->ID, '_converted_order_id', true );

		if ( empty( $status ) ) {
			$status = 'pending';
		}

		if ( 0 === $total_price ) {
			$total_price = $this->calculate_menu_items_total( $menu_items );
		}

		?>
		<div class="catering-pricing-box">
			<p>
				<label for="total_price"><?php esc_html_e( 'Total Price', 'restaurant-food-services' ); ?>:</label><br>
				<input type="number" id="total_price" name="total_price" value="<?php echo esc_attr( number_format( $total_price, 2 ) ); ?>" step="0.01" min="0" style="width: 100%; padding: 5px; font-size: 16px; font-weight: bold;">
			</p>

			<?php if ( $converted_order_id ) : ?>
				<p style="background: #e8f5e9; padding: 10px; border-radius: 4px; margin: 10px 0;">
					<strong><?php esc_html_e( 'Converted to Order', 'restaurant-food-services' ); ?>:</strong><br>
					<a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $converted_order_id ) . '&action=edit' ) ); ?>" target="_blank">
						<?php echo '#' . esc_html( $converted_order_id ); ?>
					</a>
				</p>
			<?php else : ?>
				<p>
					<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'restaurant_convert_to_order', 'catering_id' => $post->ID, 'nonce' => wp_create_nonce( 'catering_convert_nonce' ) ) ) ); ?>" class="button button-primary" style="width: 100%; box-sizing: border-box;">
									<?php esc_html_e( 'Convert to WooCommerce Order', 'restaurant-food-services' ); ?>
					</a>
				</p>
			<?php endif; ?>

			<p>
				<label for="catering_status"><?php esc_html_e( 'Status', 'restaurant-food-services' ); ?>:</label><br>
				<select id="catering_status" name="catering_status" style="width: 100%; padding: 5px;">
					<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'restaurant-food-services' ); ?></option>
					<option value="approved" <?php selected( $status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'restaurant-food-services' ); ?></option>
					<option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'restaurant-food-services' ); ?></option>
				</select>
			</p>
		</div>
		<?php
	}

	/**
	 * Saves catering request meta fields.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 */
	public function save_catering_request_meta( $post_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['catering_request_nonce_field'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['catering_request_nonce_field'] ) ), 'catering_request_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$menu_items_json = get_post_meta( $post_id, 'menu_items', true );
		$menu_items      = $this->decode_json_array( $menu_items_json );

		if ( isset( $_POST['menu_item_quantities'] ) && is_array( $_POST['menu_item_quantities'] ) ) {
			$posted_quantities = (array) wp_unslash( $_POST['menu_item_quantities'] );

			foreach ( $posted_quantities as $index => $quantity ) {
				$index    = absint( $index );
				$quantity = absint( $quantity );

				if ( isset( $menu_items[ $index ] ) && $quantity > 0 ) {
					$menu_items[ $index ]['quantity'] = $quantity;
				}
			}

			update_post_meta( $post_id, 'menu_items', wp_json_encode( $menu_items ) );
		}

		if ( isset( $_POST['total_price'] ) ) {
			$total_price = floatval( sanitize_text_field( wp_unslash( $_POST['total_price'] ) ) );
			update_post_meta( $post_id, 'total_price', $total_price );
		}

		$previous_status = get_post_meta( $post_id, 'catering_status', true );

		if ( isset( $_POST['catering_status'] ) ) {
			$status = sanitize_text_field( wp_unslash( $_POST['catering_status'] ) );

			if ( in_array( $status, array( 'pending', 'approved', 'rejected' ), true ) ) {
				update_post_meta( $post_id, 'catering_status', $status );
				$this->maybe_trigger_catering_approved_email( $post_id, $previous_status, $status );
			}
		}
	}

	/**
	 * Sends approval email once when status changes to approved.
	 *
	 * @param int    $post_id         Catering request post ID.
	 * @param string $previous_status Previous status.
	 * @param string $new_status      New status.
	 *
	 * @return void
	 */
	protected function maybe_trigger_catering_approved_email( $post_id, $previous_status, $new_status ) {
		if ( 'approved' !== $new_status || 'approved' === $previous_status ) {
			return;
		}

		if ( get_post_meta( $post_id, '_restaurant_catering_approved_email_sent', true ) ) {
			return;
		}

		update_post_meta( $post_id, '_restaurant_catering_approved_email_sent', 'yes' );
		do_action( 'restaurant_food_services_catering_approved', (int) $post_id );
	}

	/**
	 * Calculates total price for menu items.
	 *
	 * @param array<int,array> $menu_items Menu items array.
	 *
	 * @return float
	 */
	protected function calculate_menu_items_total( $menu_items ) {
		$total = 0;

		foreach ( $menu_items as $item ) {
			$price    = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
			$quantity = isset( $item['quantity'] ) ? intval( $item['quantity'] ) : 1;
			$total   += $price * $quantity;
		}

		return $total;
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
	 * Handles the convert-to-order action.
	 *
	 * @return void
	 */
	public function handle_convert_to_order_action() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'restaurant_convert_to_order' !== $action ) {
			return;
		}

		if ( ! is_admin() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'restaurant-food-services' ) );
		}

		$catering_id = isset( $_GET['catering_id'] ) ? absint( $_GET['catering_id'] ) : 0;
		$nonce       = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';

		if ( ! $catering_id || ! wp_verify_nonce( $nonce, 'catering_convert_nonce' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'restaurant-food-services' ) );
		}

		if ( ! current_user_can( 'edit_post', $catering_id ) ) {
			wp_die( esc_html__( 'Permission denied.', 'restaurant-food-services' ) );
		}

		$this->convert_catering_to_order( $catering_id );
	}

	/**
	 * Converts a catering request to a WooCommerce order.
	 *
	 * @param int $catering_id Catering request post ID.
	 *
	 * @return void
	 */
	protected function convert_catering_to_order( $catering_id ) {
		$existing_order_id = get_post_meta( $catering_id, '_converted_order_id', true );

		if ( ! empty( $existing_order_id ) ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . absint( $existing_order_id ) . '&action=edit' ) );
			exit;
		}

		$menu_items_json = get_post_meta( $catering_id, 'menu_items', true );
		$menu_items      = $this->decode_json_array( $menu_items_json );
		$total_price     = (float) get_post_meta( $catering_id, 'total_price', true );

		if ( empty( $menu_items ) ) {
			wp_die( esc_html__( 'No menu items to convert.', 'restaurant-food-services' ) );
		}

		if ( ! function_exists( 'wc_create_order' ) ) {
			wp_die( esc_html__( 'WooCommerce is not active.', 'restaurant-food-services' ) );
		}

		$order = wc_create_order();

		if ( is_wp_error( $order ) ) {
			wp_die( esc_html__( 'Failed to create order.', 'restaurant-food-services' ) );
		}

		foreach ( $menu_items as $item ) {
			$product_id = (int) $item['product_id'];
			$quantity   = isset( $item['quantity'] ) ? intval( $item['quantity'] ) : 1;
			$product    = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$order->add_product( $product, $quantity );
		}

		if ( $total_price > 0 ) {
			$order->set_total( $total_price );
		} else {
			$order->calculate_totals();
		}

		$event_date = get_post_meta( $catering_id, 'event_date', true );
		$location   = get_post_meta( $catering_id, 'location', true );

		$order->update_meta_data( '_catering_request_id', $catering_id );
		$order->update_meta_data( 'catering_event_date', $event_date );
		$order->update_meta_data( 'catering_location', $location );

		$order->save();

		update_post_meta( $catering_id, '_converted_order_id', $order->get_id() );

		$previous_status = get_post_meta( $catering_id, 'catering_status', true );
		update_post_meta( $catering_id, 'catering_status', 'approved' );
		$this->maybe_trigger_catering_approved_email( $catering_id, $previous_status, 'approved' );

		wp_safe_redirect( admin_url( 'post.php?post=' . absint( $order->get_id() ) . '&action=edit' ) );
		exit;
	}

	/**
	 * Registers the "My Catering Requests" endpoint for WooCommerce My Account.
	 *
	 * @return void
	 */
	public function register_my_catering_requests_endpoint() {
		add_rewrite_endpoint( 'my-catering-requests', EP_ROOT | EP_PAGES );
	}

	/**
	 * Adds "Catering Requests" link to WooCommerce My Account menu.
	 *
	 * @param array $items Existing menu items.
	 *
	 * @return array Modified menu items.
	 */
	public function add_catering_menu_item( $items ) {
		$items['my-catering-requests'] = esc_html__( 'Catering Requests', 'restaurant-food-services' );
		return $items;
	}

	/**
	 * Renders the "My Catering Requests" page content.
	 *
	 * @return void
	 */
	public function render_my_catering_requests_page() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			echo '<p>' . esc_html__( 'Please log in to view your catering requests.', 'restaurant-food-services' ) . '</p>';
			return;
		}

		$args = array(
			'post_type'      => 'catering_request',
			'posts_per_page' => -1,
			'author'         => $user_id,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$catering_requests = get_posts( $args );

		?>
		<h2><?php esc_html_e( 'My Catering Requests', 'restaurant-food-services' ); ?></h2>

		<?php if ( empty( $catering_requests ) ) : ?>
			<p><?php esc_html_e( 'You have no catering requests.', 'restaurant-food-services' ); ?></p>
		<?php else : ?>
			<table class="woocommerce-orders-table woocommerce-MyAccount-orders">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Location', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Date', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Guests', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Status', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Submitted', 'restaurant-food-services' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $catering_requests as $request ) : ?>
						<?php
						$location = get_post_meta( $request->ID, 'location', true );
						$event_date = get_post_meta( $request->ID, 'event_date', true );
						$guest_count = get_post_meta( $request->ID, 'guest_count', true );
						$status = get_post_meta( $request->ID, 'catering_status', true );
						if ( empty( $status ) ) {
							$status = 'pending';
						}
						$status_label = $this->get_catering_status_label( $status );
						?>
						<tr>
							<td><?php echo esc_html( $location ); ?></td>
							<td><?php echo esc_html( $event_date ); ?></td>
							<td><?php echo esc_html( $guest_count ); ?></td>
							<td><span class="catering-status"><?php echo esc_html( $status_label ); ?></span></td>
							<td><?php echo esc_html( $request->post_date ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}

	/**
	 * Gets the label for a catering request status.
	 *
	 * @param string $status Status value.
	 *
	 * @return string Status label.
	 */
	protected function get_catering_status_label( $status ) {
		$labels = array(
			'pending'  => __( 'Pending', 'restaurant-food-services' ),
			'approved' => __( 'Approved', 'restaurant-food-services' ),
			'rejected' => __( 'Rejected', 'restaurant-food-services' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
	}

	/**
	 * Registers admin menu for catering requests.
	 *
	 * @return void
	 */
	public function register_catering_admin_menu() {
		add_submenu_page(
			'restaurant-food-services',
			esc_html__( 'Catering Requests', 'restaurant-food-services' ),
			esc_html__( 'Catering Requests', 'restaurant-food-services' ),
			'manage_options',
			'restaurant-catering-requests',
			array( $this, 'render_catering_requests_admin_page' )
		);

		add_submenu_page(
			'restaurant-food-services',
			esc_html__( 'Catering Settings', 'restaurant-food-services' ),
			esc_html__( 'Catering Settings', 'restaurant-food-services' ),
			'manage_options',
			'restaurant-catering-settings',
			array( $this, 'render_catering_settings_admin_page' )
		);
	}

	/**
	 * Renders catering settings admin page.
	 *
	 * @return void
	 */
	public function render_catering_settings_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'restaurant-food-services' ) );
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$this->handle_catering_settings_save();
		}

		$offering_options = $this->get_saved_catering_options( 'restaurant_catering_offering_options' );
		$service_options  = $this->get_saved_catering_options( 'restaurant_catering_service_options' );
		$offering_page = $this->get_catering_settings_page_number( 'offering_page' );
		$service_page  = $this->get_catering_settings_page_number( 'service_page' );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Catering Settings', 'restaurant-food-services' ); ?></h1>
			<div class="restaurant-catering-settings-notices"></div>
			<?php settings_errors( 'restaurant_catering_settings' ); ?>

			<?php
			$this->render_catering_settings_options_table(
				'restaurant_catering_offering_options',
				esc_html__( 'Catering Offerings', 'restaurant-food-services' ),
				$offering_options,
				'offering_page',
				$offering_page,
				$service_page
			);

			$this->render_catering_settings_options_table(
				'restaurant_catering_service_options',
				esc_html__( 'Service Options', 'restaurant-food-services' ),
				$service_options,
				'service_page',
				$offering_page,
				$service_page
			);

			echo '<style>
			.restaurant-catering-settings-modal[hidden]{display:none;}
			.restaurant-catering-settings-modal.is-open{display:block;position:fixed;inset:0;z-index:100000;}
			.restaurant-catering-settings-modal__backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45);}
			.restaurant-catering-settings-modal__dialog{position:relative;z-index:1;max-width:520px;margin:8vh auto;background:#fff;border-radius:8px;padding:24px;box-shadow:0 10px 35px rgba(0,0,0,.2);}
			.restaurant-catering-settings-modal__actions{display:flex;gap:8px;justify-content:flex-end;margin-top:16px;}
			.restaurant-catering-settings-notices{margin:16px 0;}
			</style>';

			echo '<div class="restaurant-catering-settings-modal" hidden>';
			echo '<div class="restaurant-catering-settings-modal__backdrop" data-catering-modal-close></div>';
			echo '<div class="restaurant-catering-settings-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="restaurant-catering-settings-modal-title">';
			echo '<h2 id="restaurant-catering-settings-modal-title">' . esc_html__( 'Rename Option', 'restaurant-food-services' ) . '</h2>';
			echo '<form class="restaurant-catering-settings-modal__form">';
			wp_nonce_field( 'restaurant_catering_settings_save', 'restaurant_catering_settings_nonce' );
			echo '<input type="hidden" name="catering_settings_action" value="rename">';
			echo '<input type="hidden" name="option_name" value="">';
			echo '<input type="hidden" name="option_key" value="">';
			echo '<input type="hidden" name="offering_page" value="' . esc_attr( (string) $offering_page ) . '">';
			echo '<input type="hidden" name="service_page" value="' . esc_attr( (string) $service_page ) . '">';
			echo '<p><label for="restaurant-catering-settings-modal-input">' . esc_html__( 'New label', 'restaurant-food-services' ) . '</label></p>';
			echo '<input id="restaurant-catering-settings-modal-input" type="text" name="rename_label" class="regular-text" required>';
			echo '<div class="restaurant-catering-settings-modal__actions">';
			echo '<button type="button" class="button" data-catering-modal-close>' . esc_html__( 'Cancel', 'restaurant-food-services' ) . '</button>';
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Update', 'restaurant-food-services' ) . '</button>';
			echo '</div>';
			echo '</form>';
			echo '</div>';
			echo '</div>';
			?>
		</div>
		<?php
	}

	/**
	 * Saves catering settings from the admin form.
	 *
	 * @return void
	 */
	protected function handle_catering_settings_save() {
		$action = isset( $_POST['catering_settings_action'] ) ? sanitize_key( wp_unslash( $_POST['catering_settings_action'] ) ) : '';

		if ( '' === $action ) {
			return;
		}

		if ( ! isset( $_POST['restaurant_catering_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['restaurant_catering_settings_nonce'] ) ), 'restaurant_catering_settings_save' ) ) {
			add_settings_error( 'restaurant_catering_settings', 'restaurant_catering_settings_nonce_error', esc_html__( 'Security check failed. Please try again.', 'restaurant-food-services' ), 'error' );
			return;
		}

		$option_name = isset( $_POST['option_name'] ) ? sanitize_key( wp_unslash( $_POST['option_name'] ) ) : '';

		$result = $this->process_catering_settings_action( $action, $option_name );

		add_settings_error(
			'restaurant_catering_settings',
			$result['code'],
			$result['message'],
			$result['success'] ? 'updated' : 'error'
		);
	}

	/**
	 * AJAX handler for add/rename/delete settings actions.
	 *
	 * @return void
	 */
	public function ajax_catering_settings_option_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'restaurant-food-services' ) ), 403 );
		}

		if ( ! check_ajax_referer( 'restaurant_catering_settings_save', 'restaurant_catering_settings_nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed. Please refresh and try again.', 'restaurant-food-services' ) ), 403 );
		}

		$action      = isset( $_POST['catering_settings_action'] ) ? sanitize_key( wp_unslash( $_POST['catering_settings_action'] ) ) : '';
		$option_name = isset( $_POST['option_name'] ) ? sanitize_key( wp_unslash( $_POST['option_name'] ) ) : '';

		$result = $this->process_catering_settings_action( $action, $option_name );

		if ( empty( $result['option_name'] ) ) {
			wp_send_json_error( array( 'message' => $result['message'] ), 400 );
		}

		$offering_options = $this->get_saved_catering_options( 'restaurant_catering_offering_options' );
		$service_options  = $this->get_saved_catering_options( 'restaurant_catering_service_options' );
		$offering_page    = $this->get_catering_settings_page_number( 'offering_page' );
		$service_page     = $this->get_catering_settings_page_number( 'service_page' );

		ob_start();

		if ( 'restaurant_catering_offering_options' === $result['option_name'] ) {
			$this->render_catering_settings_options_table(
				'restaurant_catering_offering_options',
				esc_html__( 'Catering Offerings', 'restaurant-food-services' ),
				$offering_options,
				'offering_page',
				$offering_page,
				$service_page
			);
		} else {
			$this->render_catering_settings_options_table(
				'restaurant_catering_service_options',
				esc_html__( 'Service Options', 'restaurant-food-services' ),
				$service_options,
				'service_page',
				$offering_page,
				$service_page
			);
		}

		$section_html = ob_get_clean();

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'message'     => $result['message'],
					'optionName'  => $result['option_name'],
					'sectionHtml' => $section_html,
				)
			);
		}

		wp_send_json_error(
			array(
				'message'     => $result['message'],
				'optionName'  => $result['option_name'],
				'sectionHtml' => $section_html,
			),
			400
		);
	}

	/**
	 * Applies one add/rename/delete settings action.
	 *
	 * @param string $action Action key.
	 * @param string $option_name Option name.
	 *
	 * @return array<string,mixed>
	 */
	protected function process_catering_settings_action( $action, $option_name ) {
		if ( ! in_array( $option_name, array( 'restaurant_catering_offering_options', 'restaurant_catering_service_options' ), true ) ) {
			return array(
				'success'     => false,
				'code'        => 'restaurant_catering_settings_invalid_option',
				'message'     => esc_html__( 'Invalid settings group.', 'restaurant-food-services' ),
				'option_name' => '',
			);
		}

		$options = $this->get_saved_catering_options( $option_name );

		switch ( $action ) {
			case 'add':
				$new_label = isset( $_POST['new_label'] ) ? sanitize_text_field( wp_unslash( $_POST['new_label'] ) ) : '';

				if ( '' === $new_label ) {
					return array(
						'success'     => false,
						'code'        => 'restaurant_catering_settings_add_empty',
						'message'     => esc_html__( 'Enter a label before adding an option.', 'restaurant-food-services' ),
						'option_name' => $option_name,
					);
				}

				$new_key = $this->generate_catering_option_key( $new_label, $options );
				$options[ $new_key ] = $new_label;
				update_option( $option_name, $options );

				return array(
					'success'     => true,
					'code'        => 'restaurant_catering_settings_added',
					'message'     => esc_html__( 'Option added.', 'restaurant-food-services' ),
					'option_name' => $option_name,
				);

			case 'rename':
				$option_key = isset( $_POST['option_key'] ) ? sanitize_title( wp_unslash( $_POST['option_key'] ) ) : '';
				$new_label  = isset( $_POST['rename_label'] ) ? sanitize_text_field( wp_unslash( $_POST['rename_label'] ) ) : '';

				if ( '' === $option_key || ! isset( $options[ $option_key ] ) ) {
					return array(
						'success'     => false,
						'code'        => 'restaurant_catering_settings_rename_invalid',
						'message'     => esc_html__( 'Option not found for rename.', 'restaurant-food-services' ),
						'option_name' => $option_name,
					);
				}

				if ( '' === $new_label ) {
					return array(
						'success'     => false,
						'code'        => 'restaurant_catering_settings_rename_empty',
						'message'     => esc_html__( 'Enter a new label before renaming.', 'restaurant-food-services' ),
						'option_name' => $option_name,
					);
				}

				$options[ $option_key ] = $new_label;
				update_option( $option_name, $options );

				return array(
					'success'     => true,
					'code'        => 'restaurant_catering_settings_renamed',
					'message'     => esc_html__( 'Option renamed.', 'restaurant-food-services' ),
					'option_name' => $option_name,
				);

			case 'delete':
				$option_key = isset( $_POST['option_key'] ) ? sanitize_title( wp_unslash( $_POST['option_key'] ) ) : '';

				if ( '' === $option_key || ! isset( $options[ $option_key ] ) ) {
					return array(
						'success'     => false,
						'code'        => 'restaurant_catering_settings_delete_invalid',
						'message'     => esc_html__( 'Option not found for deletion.', 'restaurant-food-services' ),
						'option_name' => $option_name,
					);
				}

				unset( $options[ $option_key ] );
				update_option( $option_name, $options );

				return array(
					'success'     => true,
					'code'        => 'restaurant_catering_settings_deleted',
					'message'     => esc_html__( 'Option deleted.', 'restaurant-food-services' ),
					'option_name' => $option_name,
				);
		}

		return array(
			'success'     => false,
			'code'        => 'restaurant_catering_settings_unknown_action',
			'message'     => esc_html__( 'Unknown settings action.', 'restaurant-food-services' ),
			'option_name' => $option_name,
		);
	}

	/**
	 * Gets saved catering options.
	 *
	 * @param string $option_name Option key.
	 *
	 * @return array<string,string>
	 */
	protected function get_saved_catering_options( $option_name ) {
		$saved = get_option( $option_name, array() );

		if ( ! is_array( $saved ) || empty( $saved ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $saved as $value => $label ) {
			$value = sanitize_title( (string) $value );
			$label = sanitize_text_field( (string) $label );

			if ( '' === $value || '' === $label ) {
				continue;
			}

			$normalized[ $value ] = $label;
		}

		return $normalized;
	}

	/**
	 * Renders one settings section with add/rename/delete table controls.
	 *
	 * @param string              $option_name Option key.
	 * @param string              $section_title Section heading.
	 * @param array<string,string> $options Option map.
	 * @param string              $current_page_param Current section page query key.
	 * @param int                 $offering_page Current offerings page.
	 * @param int                 $service_page Current services page.
	 *
	 * @return void
	 */
	protected function render_catering_settings_options_table( $option_name, $section_title, $options, $current_page_param, $offering_page, $service_page ) {
		echo '<section class="restaurant-catering-settings-section" data-option-name="' . esc_attr( $option_name ) . '">';

		$per_page = 8;
		$page     = 'offering_page' === $current_page_param ? $offering_page : $service_page;
		$total    = count( $options );
		$pages    = max( 1, (int) ceil( $total / $per_page ) );
		$page     = min( max( 1, $page ), $pages );
		$offset   = ( $page - 1 ) * $per_page;
		$rows     = array_slice( $options, $offset, $per_page, true );

		echo '<h2 style="margin-top:28px;">' . esc_html( $section_title ) . '</h2>';
		echo '<form method="post" style="margin: 12px 0 16px; display:flex; gap:8px; align-items:center;">';
		wp_nonce_field( 'restaurant_catering_settings_save', 'restaurant_catering_settings_nonce' );
		echo '<input type="hidden" name="catering_settings_action" value="add">';
		echo '<input type="hidden" name="option_name" value="' . esc_attr( $option_name ) . '">';
		echo '<input type="hidden" name="offering_page" value="' . esc_attr( (string) $offering_page ) . '">';
		echo '<input type="hidden" name="service_page" value="' . esc_attr( (string) $service_page ) . '">';
		echo '<input type="text" name="new_label" class="regular-text" placeholder="' . esc_attr__( 'Enter new option label', 'restaurant-food-services' ) . '" required>';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Add Option', 'restaurant-food-services' ) . '</button>';
		echo '</form>';

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th style="width:32%;">' . esc_html__( 'Key', 'restaurant-food-services' ) . '</th>';
		echo '<th>' . esc_html__( 'Label', 'restaurant-food-services' ) . '</th>';
		echo '<th style="width:280px;">' . esc_html__( 'Actions', 'restaurant-food-services' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="3">' . esc_html__( 'No options added yet.', 'restaurant-food-services' ) . '</td></tr>';
		} else {
			foreach ( $rows as $key => $label ) {
				echo '<tr>';
				echo '<td><code>' . esc_html( $key ) . '</code></td>';
				echo '<td>' . esc_html( $label ) . '</td>';
				echo '<td>';

				echo '<form method="post" class="restaurant-catering-settings-rename-form" style="display:inline-flex; gap:6px; margin-right:8px;">';
				wp_nonce_field( 'restaurant_catering_settings_save', 'restaurant_catering_settings_nonce' );
				echo '<input type="hidden" name="catering_settings_action" value="rename">';
				echo '<input type="hidden" name="option_name" value="' . esc_attr( $option_name ) . '">';
				echo '<input type="hidden" name="option_key" value="' . esc_attr( $key ) . '">';
				echo '<input type="hidden" name="offering_page" value="' . esc_attr( (string) $offering_page ) . '">';
				echo '<input type="hidden" name="service_page" value="' . esc_attr( (string) $service_page ) . '">';
				echo '<input type="hidden" name="rename_label" value="' . esc_attr( $label ) . '">';
				echo '<button type="button" class="button restaurant-catering-settings-rename-button" data-option-key="' . esc_attr( $key ) . '" data-option-label="' . esc_attr( $label ) . '" data-option-name="' . esc_attr( $option_name ) . '">' . esc_html__( 'Rename', 'restaurant-food-services' ) . '</button>';
				echo '</form>';

				echo '<form method="post" class="restaurant-catering-settings-delete-form" style="display:inline-block;">';
				wp_nonce_field( 'restaurant_catering_settings_save', 'restaurant_catering_settings_nonce' );
				echo '<input type="hidden" name="catering_settings_action" value="delete">';
				echo '<input type="hidden" name="option_name" value="' . esc_attr( $option_name ) . '">';
				echo '<input type="hidden" name="option_key" value="' . esc_attr( $key ) . '">';
				echo '<input type="hidden" name="offering_page" value="' . esc_attr( (string) $offering_page ) . '">';
				echo '<input type="hidden" name="service_page" value="' . esc_attr( (string) $service_page ) . '">';
				echo '<button type="submit" class="button restaurant-catering-settings-delete-button" onclick="return confirm(\'' . esc_js( __( 'Delete this option?', 'restaurant-food-services' ) ) . '\');">' . esc_html__( 'Delete', 'restaurant-food-services' ) . '</button>';
				echo '</form>';

				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';

		if ( $pages > 1 ) {
			echo '<p style="margin-top:10px;">';
			for ( $i = 1; $i <= $pages; $i++ ) {
				$url = add_query_arg(
					array(
						'page'          => 'restaurant-catering-settings',
						'offering_page' => 'offering_page' === $current_page_param ? $i : $offering_page,
						'service_page'  => 'service_page' === $current_page_param ? $i : $service_page,
					),
					admin_url( 'admin.php' )
				);

				if ( $i === $page ) {
					echo '<span class="button button-primary" style="margin-right:6px;">' . esc_html( (string) $i ) . '</span>';
				} else {
					echo '<a class="button" style="margin-right:6px;" href="' . esc_url( $url ) . '">' . esc_html( (string) $i ) . '</a>';
				}
			}
			echo '</p>';
		}

		echo '</section>';
	}

	/**
	 * Returns a validated settings page number.
	 *
	 * @param string $param_name Query parameter name.
	 *
	 * @return int
	 */
	protected function get_catering_settings_page_number( $param_name ) {
		$value = isset( $_REQUEST[ $param_name ] ) ? absint( wp_unslash( $_REQUEST[ $param_name ] ) ) : 1;

		return $value > 0 ? $value : 1;
	}

	/**
	 * Generates a unique option key from label.
	 *
	 * @param string              $label Source label.
	 * @param array<string,string> $existing Existing options map.
	 *
	 * @return string
	 */
	protected function generate_catering_option_key( $label, $existing ) {
		$base = sanitize_title( $label );

		if ( '' === $base ) {
			$base = 'option';
		}

		$key     = $base;
		$suffix  = 2;

		while ( isset( $existing[ $key ] ) ) {
			$key = $base . '_' . $suffix;
			++$suffix;
		}

		return $key;
	}


	/**
	 * Renders the catering requests admin page.
	 *
	 * @return void
	 */
	public function render_catering_requests_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'restaurant-food-services' ) );
		}

		$status_filter = '';
		$date_from     = '';
		$date_to       = '';

		$filter_nonce = isset( $_GET['restaurant_catering_filter_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['restaurant_catering_filter_nonce'] ) ) : '';

		if ( '' !== $filter_nonce && wp_verify_nonce( $filter_nonce, 'restaurant_catering_filter' ) ) {
			$status_filter = isset( $_GET['status_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['status_filter'] ) ) : '';
			$date_from     = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
			$date_to       = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';

			if ( ! in_array( $status_filter, array( '', 'pending', 'approved', 'rejected' ), true ) ) {
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
			'post_type'      => 'catering_request',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $status_filter ) ) {
			$query_args['meta_query'] = array(
				array(
					'key'   => 'catering_status',
					'value' => $status_filter,
					'compare' => '=',
				),
			);
		}

		$catering_requests = get_posts( $query_args );

		if ( ! empty( $date_from ) || ! empty( $date_to ) ) {
			$catering_requests = array_filter( $catering_requests, function( $request ) use ( $date_from, $date_to ) {
				$event_date = get_post_meta( $request->ID, 'event_date', true );

				if ( ! empty( $date_from ) && $event_date < $date_from ) {
					return false;
				}

				if ( ! empty( $date_to ) && $event_date > $date_to ) {
					return false;
				}

				return true;
			} );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Catering Requests', 'restaurant-food-services' ); ?></h1>

			<form method="get" style="margin: 20px 0;">
				<input type="hidden" name="page" value="restaurant-catering-requests">
				<?php wp_nonce_field( 'restaurant_catering_filter', 'restaurant_catering_filter_nonce' ); ?>

				<label for="status_filter"><?php esc_html_e( 'Status:', 'restaurant-food-services' ); ?></label>
				<select name="status_filter" id="status_filter">
					<option value=""><?php esc_html_e( '-- All --', 'restaurant-food-services' ); ?></option>
					<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'restaurant-food-services' ); ?></option>
					<option value="approved" <?php selected( $status_filter, 'approved' ); ?>><?php esc_html_e( 'Approved', 'restaurant-food-services' ); ?></option>
					<option value="rejected" <?php selected( $status_filter, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'restaurant-food-services' ); ?></option>
				</select>

				<label for="date_from"><?php esc_html_e( 'From:', 'restaurant-food-services' ); ?></label>
				<input type="date" name="date_from" id="date_from" value="<?php echo esc_attr( $date_from ); ?>">

				<label for="date_to"><?php esc_html_e( 'To:', 'restaurant-food-services' ); ?></label>
				<input type="date" name="date_to" id="date_to" value="<?php echo esc_attr( $date_to ); ?>">

				<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'restaurant-food-services' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=restaurant-catering-requests' ) ); ?>" class="button">
					<?php esc_html_e( 'Reset', 'restaurant-food-services' ); ?>
				</a>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Location', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Event Date', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Guest Count', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Total Price', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Status', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Submitted', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'restaurant-food-services' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $catering_requests ) ) : ?>
						<tr>
							<td colspan="7"><?php esc_html_e( 'No catering requests found.', 'restaurant-food-services' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $catering_requests as $request ) : ?>
							<?php
							$location = get_post_meta( $request->ID, 'location', true );
							$event_date = get_post_meta( $request->ID, 'event_date', true );
							$guest_count = get_post_meta( $request->ID, 'guest_count', true );
							$total_price = (float) get_post_meta( $request->ID, 'total_price', true );
							$status = get_post_meta( $request->ID, 'catering_status', true );
							if ( empty( $status ) ) {
								$status = 'pending';
							}
							$status_label = $this->get_catering_status_label( $status );
							?>
							<tr>
								<td><?php echo esc_html( $location ); ?></td>
								<td><?php echo esc_html( $event_date ); ?></td>
								<td><?php echo esc_html( $guest_count ); ?></td>
								<td><?php echo esc_html( '$' . number_format( $total_price, 2 ) ); ?></td>
								<td><?php echo esc_html( $status_label ); ?></td>
								<td><?php echo esc_html( $request->post_date ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $request->ID ) . '&action=edit' ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Edit', 'restaurant-food-services' ); ?>
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
