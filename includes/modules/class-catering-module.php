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
		$loader->add_action( 'wp_ajax_restaurant_catering_settings_option_action', $this, 'handle_catering_settings_option_action' );
		$loader->add_action( 'restaurant_food_services_catering_request_submitted', $this, 'ensure_catering_request_comments_enabled' );
		$loader->add_filter( 'preprocess_comment', $this, 'enforce_catering_chat_comment_permissions' );
	}

	/**
	 * Registers admin menu pages for catering management.
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
	 * Renders the catering requests admin page.
	 *
	 * @return void
	 */
	public function render_catering_requests_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'restaurant-food-services' ) );
		}

		$args = array(
			'post_type'      => 'catering_request',
			'posts_per_page' => 20,
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		$requests = get_posts( $args );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Catering Requests', 'restaurant-food-services' ); ?></h1>
			<p><?php esc_html_e( 'Recent catering requests and discussion threads are listed below. Use the edit screen to manage pricing, approve requests, and continue the chat with the customer.', 'restaurant-food-services' ); ?></p>

			<?php if ( empty( $requests ) ) : ?>
				<p><?php esc_html_e( 'No catering requests found yet.', 'restaurant-food-services' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'restaurant-food-services' ); ?></th>
							<th><?php esc_html_e( 'Title', 'restaurant-food-services' ); ?></th>
							<th><?php esc_html_e( 'Status', 'restaurant-food-services' ); ?></th>
							<th><?php esc_html_e( 'Date', 'restaurant-food-services' ); ?></th>
							<th><?php esc_html_e( 'Action', 'restaurant-food-services' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $requests as $request ) : ?>
							<?php $status = get_post_meta( $request->ID, 'catering_status', true ); ?>
							<tr>
								<td><?php echo esc_html( (string) $request->ID ); ?></td>
								<td><?php echo esc_html( get_the_title( $request ) ); ?></td>
								<td><?php echo esc_html( $this->get_catering_status_label( $status ? $status : 'pending' ) ); ?></td>
								<td><?php echo esc_html( get_date_from_gmt( $request->post_date_gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
								<td><a class="button" href="<?php echo esc_url( admin_url( 'post.php?post=' . absint( $request->ID ) . '&action=edit' ) ); ?>"><?php esc_html_e( 'Edit Request', 'restaurant-food-services' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the catering settings admin page.
	 *
	 * @return void
	 */
	public function render_catering_settings_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'restaurant-food-services' ) );
		}

		$offering_page = isset( $_GET['offering_page'] ) ? max( 1, absint( $_GET['offering_page'] ) ) : 1;
		$service_page  = isset( $_GET['service_page'] ) ? max( 1, absint( $_GET['service_page'] ) ) : 1;
		$course_page   = isset( $_GET['course_page'] ) ? max( 1, absint( $_GET['course_page'] ) ) : 1;

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Catering Settings', 'restaurant-food-services' ); ?></h1>
			<p><?php esc_html_e( 'Manage catering offerings and service options used by the frontend wizard. Changes are saved instantly without reloading this page.', 'restaurant-food-services' ); ?></p>

			<div class="restaurant-catering-settings-notices" aria-live="polite"></div>

			<div class="restaurant-catering-settings-grid">
				<?php
				echo $this->render_catering_settings_section(
					'restaurant_catering_offering_options',
					$offering_page,
					$offering_page,
					$service_page,
					$course_page
				);

				echo $this->render_catering_settings_section(
					'restaurant_catering_service_options',
					$service_page,
					$offering_page,
					$service_page,
					$course_page
				);

				echo $this->render_catering_settings_section(
					'restaurant_catering_meal_course_options',
					$course_page,
					$offering_page,
					$service_page,
					$course_page
				);
				?>
			</div>

			<div class="restaurant-catering-settings-modal" hidden>
				<div class="restaurant-catering-settings-modal__backdrop" data-catering-modal-close></div>
				<div class="restaurant-catering-settings-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="restaurant-catering-settings-modal-title">
					<h2 id="restaurant-catering-settings-modal-title"><?php esc_html_e( 'Rename Option', 'restaurant-food-services' ); ?></h2>
					<form class="restaurant-catering-settings-modal__form" method="post">
						<?php wp_nonce_field( 'restaurant_catering_settings_options', 'restaurant_catering_settings_nonce' ); ?>
						<input type="hidden" name="operation" value="rename">
						<input type="hidden" name="option_name" value="">
						<input type="hidden" name="option_key" value="">
						<input type="hidden" name="offering_page" value="<?php echo esc_attr( (string) $offering_page ); ?>">
						<input type="hidden" name="service_page" value="<?php echo esc_attr( (string) $service_page ); ?>">
						<input type="hidden" name="course_page" value="<?php echo esc_attr( (string) $course_page ); ?>">

						<p>
							<label for="restaurant-catering-settings-modal-input" class="screen-reader-text"><?php esc_html_e( 'Updated option label', 'restaurant-food-services' ); ?></label>
							<input id="restaurant-catering-settings-modal-input" name="rename_label" type="text" class="regular-text" required>
						</p>

						<div class="restaurant-catering-settings-modal__actions">
							<button type="button" class="button" data-catering-modal-close><?php esc_html_e( 'Cancel', 'restaurant-food-services' ); ?></button>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Update', 'restaurant-food-services' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
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
		$style_path     = dirname( dirname( __DIR__ ) ) . '/assets/css/catering-settings-admin.css';
		$script_version = defined( 'RESTAURANT_FOOD_SERVICES_VERSION' ) ? RESTAURANT_FOOD_SERVICES_VERSION : '1.0.0';
		$style_version  = $script_version;

		if ( file_exists( $script_path ) ) {
			$script_version = (string) filemtime( $script_path );
		}

		if ( file_exists( $style_path ) ) {
			$style_version = (string) filemtime( $style_path );
		}

		wp_enqueue_style(
			'restaurant-food-services-catering-settings-admin',
			RESTAURANT_FOOD_SERVICES_URL . 'assets/css/catering-settings-admin.css',
			array(),
			$style_version
		);

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
				'confirmDelete' => esc_html__( 'Delete this option?', 'restaurant-food-services' ),
			)
		);
	}

	/**
	 * Handles catering settings AJAX actions.
	 *
	 * @return void
	 */
	public function handle_catering_settings_option_action() {
		if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid request method.', 'restaurant-food-services' ) ), 405 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'restaurant-food-services' ) ), 403 );
		}

		if ( ! check_ajax_referer( 'restaurant_catering_settings_options', 'restaurant_catering_settings_nonce', false ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'restaurant-food-services' ) ), 403 );
		}

		$option_name   = isset( $_POST['option_name'] ) ? sanitize_key( wp_unslash( $_POST['option_name'] ) ) : '';
		$operation     = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$offering_page = isset( $_POST['offering_page'] ) ? max( 1, absint( wp_unslash( $_POST['offering_page'] ) ) ) : 1;
		$service_page  = isset( $_POST['service_page'] ) ? max( 1, absint( wp_unslash( $_POST['service_page'] ) ) ) : 1;
		$course_page   = isset( $_POST['course_page'] ) ? max( 1, absint( wp_unslash( $_POST['course_page'] ) ) ) : 1;

		if ( ! $this->is_supported_catering_settings_option( $option_name ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Invalid option target.', 'restaurant-food-services' ) ), 400 );
		}

		$options = $this->get_catering_settings_options( $option_name );
		$message = esc_html__( 'No changes were made.', 'restaurant-food-services' );

		switch ( $operation ) {
			case 'add':
				$label = isset( $_POST['option_label'] ) ? sanitize_text_field( wp_unslash( $_POST['option_label'] ) ) : '';

				if ( '' === $label ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Option label is required.', 'restaurant-food-services' ) ), 400 );
				}

				$key             = $this->create_unique_catering_option_key( $options, $label );
				$options[ $key ] = $label;
				$message         = esc_html__( 'Option added successfully.', 'restaurant-food-services' );
				break;

			case 'rename':
				$key   = isset( $_POST['option_key'] ) ? sanitize_key( wp_unslash( $_POST['option_key'] ) ) : '';
				$label = isset( $_POST['rename_label'] ) ? sanitize_text_field( wp_unslash( $_POST['rename_label'] ) ) : '';

				if ( '' === $key || ! isset( $options[ $key ] ) ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Selected option no longer exists.', 'restaurant-food-services' ) ), 404 );
				}

				if ( '' === $label ) {
					wp_send_json_error( array( 'message' => esc_html__( 'New label is required.', 'restaurant-food-services' ) ), 400 );
				}

				if ( $this->is_default_catering_offering_option_key( $option_name, $key ) ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Default catering offerings cannot be renamed.', 'restaurant-food-services' ) ), 400 );
				}

				$options[ $key ] = $label;
				$message         = esc_html__( 'Option renamed successfully.', 'restaurant-food-services' );
				break;

			case 'delete':
				$key = isset( $_POST['option_key'] ) ? sanitize_key( wp_unslash( $_POST['option_key'] ) ) : '';

				if ( '' === $key || ! isset( $options[ $key ] ) ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Selected option no longer exists.', 'restaurant-food-services' ) ), 404 );
				}

				if ( $this->is_default_catering_offering_option_key( $option_name, $key ) ) {
					wp_send_json_error( array( 'message' => esc_html__( 'Default catering offerings cannot be deleted.', 'restaurant-food-services' ) ), 400 );
				}

				unset( $options[ $key ] );
				$message = esc_html__( 'Option deleted successfully.', 'restaurant-food-services' );
				break;

			case 'paginate':
				$target_page = isset( $_POST['target_page'] ) ? max( 1, absint( wp_unslash( $_POST['target_page'] ) ) ) : 1;

				if ( 'restaurant_catering_offering_options' === $option_name ) {
					$offering_page = $target_page;
				} elseif ( 'restaurant_catering_service_options' === $option_name ) {
					$service_page = $target_page;
				} else {
					$course_page = $target_page;
				}

				$message = esc_html__( 'Page updated.', 'restaurant-food-services' );
				break;

			default:
				wp_send_json_error( array( 'message' => esc_html__( 'Invalid operation.', 'restaurant-food-services' ) ), 400 );
		}

		if ( in_array( $operation, array( 'add', 'rename', 'delete' ), true ) ) {
			$this->save_catering_settings_options( $option_name, $options );
		}

		if ( 'restaurant_catering_offering_options' === $option_name ) {
			$section_page = $offering_page;
		} elseif ( 'restaurant_catering_service_options' === $option_name ) {
			$section_page = $service_page;
		} else {
			$section_page = $course_page;
		}

		wp_send_json_success(
			array(
				'message'     => $message,
				'sectionHtml' => $this->render_catering_settings_section( $option_name, $section_page, $offering_page, $service_page, $course_page ),
			)
		);
	}

	/**
	 * Renders one AJAX-refreshable settings section.
	 *
	 * @param string $option_name Option name.
	 * @param int    $current_page Current page for this table.
	 * @param int    $offering_page Current offerings page.
	 * @param int    $service_page Current services page.
	 * @param int    $course_page Current meal course page.
	 *
	 * @return string
	 */
	protected function render_catering_settings_section( $option_name, $current_page, $offering_page, $service_page, $course_page ) {
		$options_per_page = 8;
		$label            = $this->get_catering_settings_option_label( $option_name );
		$options          = $this->get_catering_settings_options( $option_name );
		$total_items      = count( $options );
		$total_pages      = max( 1, (int) ceil( $total_items / $options_per_page ) );
		$current_page     = min( max( 1, (int) $current_page ), $total_pages );
		$offset           = ( $current_page - 1 ) * $options_per_page;
		$current_rows     = array_slice( $options, $offset, $options_per_page, true );

		ob_start();
		?>
		<section class="restaurant-catering-settings-section" data-option-name="<?php echo esc_attr( $option_name ); ?>">
			<h2><?php echo esc_html( $label ); ?></h2>

			<form method="post" class="restaurant-catering-settings-add-form">
				<?php wp_nonce_field( 'restaurant_catering_settings_options', 'restaurant_catering_settings_nonce' ); ?>
				<input type="hidden" name="operation" value="add">
				<input type="hidden" name="option_name" value="<?php echo esc_attr( $option_name ); ?>">
				<input type="hidden" name="offering_page" value="<?php echo esc_attr( (string) $offering_page ); ?>">
				<input type="hidden" name="service_page" value="<?php echo esc_attr( (string) $service_page ); ?>">
				<input type="hidden" name="course_page" value="<?php echo esc_attr( (string) $course_page ); ?>">
				<div class="restaurant-catering-settings-inline-form">
					<label for="restaurant-settings-option-label-<?php echo esc_attr( $option_name ); ?>" class="screen-reader-text"><?php esc_html_e( 'Option label', 'restaurant-food-services' ); ?></label>
					<input id="restaurant-settings-option-label-<?php echo esc_attr( $option_name ); ?>" type="text" class="regular-text" name="option_label" required placeholder="<?php esc_attr_e( 'Enter option label', 'restaurant-food-services' ); ?>">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Add Option', 'restaurant-food-services' ); ?></button>
				</div>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Label', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Key', 'restaurant-food-services' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'restaurant-food-services' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $current_rows ) ) : ?>
						<tr>
							<td colspan="3"><?php esc_html_e( 'No options found yet.', 'restaurant-food-services' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $current_rows as $option_key => $option_label ) : ?>
										<?php $is_default_offering = $this->is_default_catering_offering_option_key( $option_name, $option_key ); ?>
							<tr>
								<td><?php echo esc_html( $option_label ); ?></td>
								<td><code><?php echo esc_html( $option_key ); ?></code></td>
								<td>
												<?php if ( $is_default_offering ) : ?>
													<span class="description"><?php esc_html_e( 'Default option', 'restaurant-food-services' ); ?></span>
												<?php else : ?>
									<form method="post" class="restaurant-catering-settings-row-actions">
										<?php wp_nonce_field( 'restaurant_catering_settings_options', 'restaurant_catering_settings_nonce' ); ?>
										<input type="hidden" name="option_name" value="<?php echo esc_attr( $option_name ); ?>">
										<input type="hidden" name="option_key" value="<?php echo esc_attr( $option_key ); ?>">
										<input type="hidden" name="offering_page" value="<?php echo esc_attr( (string) $offering_page ); ?>">
										<input type="hidden" name="service_page" value="<?php echo esc_attr( (string) $service_page ); ?>">
										<input type="hidden" name="course_page" value="<?php echo esc_attr( (string) $course_page ); ?>">
										<input type="hidden" name="operation" value="delete">
										<button
											type="button"
											class="button restaurant-catering-settings-rename-button"
											data-option-name="<?php echo esc_attr( $option_name ); ?>"
											data-option-key="<?php echo esc_attr( $option_key ); ?>"
											data-option-label="<?php echo esc_attr( $option_label ); ?>"
										>
											<?php esc_html_e( 'Rename', 'restaurant-food-services' ); ?>
										</button>
										<button type="submit" class="button button-link-delete restaurant-catering-settings-delete-button"><?php esc_html_e( 'Delete', 'restaurant-food-services' ); ?></button>
									</form>
												<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="restaurant-catering-settings-pagination" role="navigation" aria-label="<?php esc_attr_e( 'Settings pagination', 'restaurant-food-services' ); ?>">
					<?php for ( $page_number = 1; $page_number <= $total_pages; $page_number++ ) : ?>
						<form method="post" class="restaurant-catering-settings-pagination-form">
							<?php wp_nonce_field( 'restaurant_catering_settings_options', 'restaurant_catering_settings_nonce' ); ?>
							<input type="hidden" name="operation" value="paginate">
							<input type="hidden" name="option_name" value="<?php echo esc_attr( $option_name ); ?>">
							<input type="hidden" name="target_page" value="<?php echo esc_attr( (string) $page_number ); ?>">
							<input type="hidden" name="offering_page" value="<?php echo esc_attr( (string) $offering_page ); ?>">
							<input type="hidden" name="service_page" value="<?php echo esc_attr( (string) $service_page ); ?>">
							<input type="hidden" name="course_page" value="<?php echo esc_attr( (string) $course_page ); ?>">
							<button type="submit" class="button <?php echo $page_number === $current_page ? 'button-primary' : ''; ?>"><?php echo esc_html( (string) $page_number ); ?></button>
						</form>
					<?php endfor; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php

		return ob_get_clean();
	}

	/**
	 * Returns supported options storage keys.
	 *
	 * @return array<string,string>
	 */
	protected function get_catering_settings_option_registry() {
		return array(
			'restaurant_catering_offering_options' => esc_html__( 'Catering Offerings', 'restaurant-food-services' ),
			'restaurant_catering_service_options'  => esc_html__( 'Service Options', 'restaurant-food-services' ),
			'restaurant_catering_meal_course_options' => esc_html__( 'Meal Courses', 'restaurant-food-services' ),
		);
	}

	/**
	 * Checks if option name is managed by the settings page.
	 *
	 * @param string $option_name Option name.
	 *
	 * @return bool
	 */
	protected function is_supported_catering_settings_option( $option_name ) {
		return isset( $this->get_catering_settings_option_registry()[ $option_name ] );
	}

	/**
	 * Returns human label for option section.
	 *
	 * @param string $option_name Option key.
	 *
	 * @return string
	 */
	protected function get_catering_settings_option_label( $option_name ) {
		$registry = $this->get_catering_settings_option_registry();

		if ( isset( $registry[ $option_name ] ) ) {
			return $registry[ $option_name ];
		}

		return esc_html__( 'Options', 'restaurant-food-services' );
	}

	/**
	 * Returns normalized option map for a settings section.
	 *
	 * @param string $option_name Option key.
	 *
	 * @return array<string,string>
	 */
	protected function get_catering_settings_options( $option_name ) {
		if ( ! $this->is_supported_catering_settings_option( $option_name ) ) {
			return array();
		}

		$options = $this->normalize_catering_settings_options_map( get_option( $option_name, array() ) );

		if ( 'restaurant_catering_offering_options' !== $option_name ) {
			return $options;
		}

		$default_options = $this->get_default_catering_offering_options();

		foreach ( $default_options as $default_key => $default_label ) {
			unset( $options[ $default_key ] );
		}

		return array_merge( $default_options, $options );
	}

	/**
	 * Returns default catering offerings that are always available.
	 *
	 * @return array<string,string>
	 */
	protected function get_default_catering_offering_options() {
		return array(
			'custom_meal_design' => esc_html__( 'Custom Meal Design', 'restaurant-food-services' ),
		);
	}

	/**
	 * Persists a normalized option map.
	 *
	 * @param string              $option_name Option name.
	 * @param array<string,string> $options    Value => label map.
	 *
	 * @return void
	 */
	protected function save_catering_settings_options( $option_name, $options ) {
		$normalized = $this->normalize_catering_settings_options_map( $options );

		if ( 'restaurant_catering_offering_options' === $option_name ) {
			$default_options = $this->get_default_catering_offering_options();

			foreach ( $default_options as $default_key => $default_label ) {
				unset( $normalized[ $default_key ] );
			}
		}

		update_option( $option_name, $normalized );
	}

	/**
	 * Checks if the provided key belongs to an immutable default catering offering.
	 *
	 * @param string $option_name Option name.
	 * @param string $option_key  Option key.
	 *
	 * @return bool
	 */
	protected function is_default_catering_offering_option_key( $option_name, $option_key ) {
		if ( 'restaurant_catering_offering_options' !== $option_name ) {
			return false;
		}

		$defaults = $this->get_default_catering_offering_options();

		return isset( $defaults[ sanitize_key( (string) $option_key ) ] );
	}

	/**
	 * Normalizes a catering options payload into value => label map.
	 *
	 * @param mixed $options Raw options payload.
	 *
	 * @return array<string,string>
	 */
	protected function normalize_catering_settings_options_map( $options ) {
		if ( ! is_array( $options ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $options as $value => $label ) {
			if ( is_array( $label ) ) {
				$candidate_value = isset( $label['value'] ) ? $label['value'] : ( isset( $label['key'] ) ? $label['key'] : '' );
				$candidate_label = isset( $label['label'] ) ? $label['label'] : '';
			} else {
				$candidate_value = $value;
				$candidate_label = $label;
			}

			$candidate_value = sanitize_title( (string) $candidate_value );
			$candidate_label = sanitize_text_field( (string) $candidate_label );

			if ( '' === $candidate_value || '' === $candidate_label ) {
				continue;
			}

			$normalized[ $candidate_value ] = $candidate_label;
		}

		return $normalized;
	}

	/**
	 * Creates a unique option key from the provided label.
	 *
	 * @param array<string,string> $options Existing options map.
	 * @param string               $raw_label Candidate label.
	 *
	 * @return string
	 */
	protected function create_unique_catering_option_key( $options, $raw_label ) {
		$base_key = sanitize_title( $raw_label );

		if ( '' === $base_key ) {
			$base_key = 'option';
		}

		$unique_key = $base_key;
		$suffix     = 2;

		while ( isset( $options[ $unique_key ] ) ) {
			$unique_key = $base_key . '-' . $suffix;
			++$suffix;
		}

		return $unique_key;
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

		if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
			wp_die( esc_html__( 'Invalid request method.', 'restaurant-food-services' ) );
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

		$form_signature = md5(
			wp_json_encode(
				array(
					'event_date'       => $event_date,
					'guest_count'      => $guest_count,
					'location'         => $location,
					'menu_items'       => $structured_items,
					'special_requests' => $special_requests,
				)
			)
		);

		$lock_key = 'restaurant_catering_form_lock_' . absint( get_current_user_id() ) . '_' . $form_signature;

		if ( get_transient( $lock_key ) ) {
			wp_die( esc_html__( 'Duplicate submission detected. Please refresh and try again.', 'restaurant-food-services' ) );
		}

		set_transient( $lock_key, 1, 30 );

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
			'supports'            => array( 'title', 'custom-fields', 'comments' ),
			'hierarchical'        => false,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => true,
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
			'default',
			array(
				'__block_editor_compatible_meta_box' => true,
			)
		);

		add_meta_box(
			'catering_menu_items',
			esc_html__( 'Menu Items', 'restaurant-food-services' ),
			array( $this, 'render_menu_items_meta_box' ),
			'catering_request',
			'normal',
			'high',
			array(
				'__block_editor_compatible_meta_box' => true,
			)
		);

		add_meta_box(
			'catering_pricing',
			esc_html__( 'Pricing & Status', 'restaurant-food-services' ),
			array( $this, 'render_pricing_meta_box' ),
			'catering_request',
			'side',
			'high',
			array(
				'__block_editor_compatible_meta_box' => true,
			)
		);

		add_meta_box(
			'catering_chat_thread',
			esc_html__( 'Discussion / Chat', 'restaurant-food-services' ),
			array( $this, 'render_catering_chat_meta_box' ),
			'catering_request',
			'normal',
			'default',
			array(
				'__block_editor_compatible_meta_box' => true,
			)
		);
	}

	/**
	 * Renders catering chat thread meta box in admin.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return void
	 */
	public function render_catering_chat_meta_box( $post ) {
		$this->render_catering_chat_panel( (int) $post->ID, 'admin' );
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
					<?php $convert_url = wp_nonce_url( add_query_arg( array( 'action' => 'restaurant_convert_to_order', 'catering_id' => $post->ID ), admin_url( 'edit.php' ) ), 'catering_convert_nonce' ); ?>
					<a href="<?php echo esc_url( $convert_url ); ?>" class="button button-primary" style="width: 100%; box-sizing: border-box;">
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

				if ( 'approved' === $status ) {
					$this->maybe_create_order_on_approval( $post_id );
				}

				$this->maybe_trigger_catering_approved_email( $post_id, $previous_status, $status );
			}
		}
	}

	/**
	 * Creates and links a WooCommerce order when a request is approved.
	 *
	 * @param int $catering_id Catering request post ID.
	 *
	 * @return void
	 */
	protected function maybe_create_order_on_approval( $catering_id ) {
		$catering_id = absint( $catering_id );

		if ( $catering_id <= 0 ) {
			return;
		}

		if ( ! function_exists( 'wc_create_order' ) ) {
			return;
		}

		$linked_order_id = $this->get_catering_linked_order_id( $catering_id );

		if ( $linked_order_id > 0 ) {
			$this->sync_catering_checkout_link_meta( $catering_id, $linked_order_id );
			return;
		}

		$order_id = $this->create_catering_order( $catering_id );

		if ( $order_id > 0 ) {
			$this->sync_catering_checkout_link_meta( $catering_id, $order_id );
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

		if ( ! $catering_id ) {
			wp_die( esc_html__( 'Invalid request.', 'restaurant-food-services' ) );
		}

		$legacy_nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		$is_legacy_ok = '' !== $legacy_nonce && wp_verify_nonce( $legacy_nonce, 'catering_convert_nonce' );

		if ( ! $is_legacy_ok ) {
			check_admin_referer( 'catering_convert_nonce' );
		}

		if ( ! current_user_can( 'edit_post', $catering_id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'restaurant-food-services' ) );
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
		$existing_order_id = $this->get_catering_linked_order_id( $catering_id );

		if ( ! empty( $existing_order_id ) ) {
			wp_safe_redirect( admin_url( 'post.php?post=' . absint( $existing_order_id ) . '&action=edit' ) );
			exit;
		}

		$order_id = $this->create_catering_order( $catering_id );

		if ( $order_id <= 0 ) {
			wp_die( esc_html__( 'Failed to create order.', 'restaurant-food-services' ) );
		}

		$this->sync_catering_checkout_link_meta( $catering_id, $order_id );

		$previous_status = get_post_meta( $catering_id, 'catering_status', true );
		update_post_meta( $catering_id, 'catering_status', 'approved' );
		$this->maybe_trigger_catering_approved_email( $catering_id, $previous_status, 'approved' );

		wp_safe_redirect( admin_url( 'post.php?post=' . absint( $order_id ) . '&action=edit' ) );
		exit;
	}

	/**
	 * Creates a WooCommerce order from a catering request.
	 *
	 * @param int $catering_id Catering request post ID.
	 *
	 * @return int New order ID on success, otherwise 0.
	 */
	protected function create_catering_order( $catering_id ) {
		$catering_id = absint( $catering_id );

		if ( $catering_id <= 0 || ! function_exists( 'wc_create_order' ) ) {
			return 0;
		}

		$existing_order_id = $this->get_catering_linked_order_id( $catering_id );

		if ( $existing_order_id > 0 ) {
			return $existing_order_id;
		}

		$menu_items_json = get_post_meta( $catering_id, 'menu_items', true );
		$menu_items      = $this->decode_json_array( $menu_items_json );
		$total_price     = (float) get_post_meta( $catering_id, 'total_price', true );
		$service_fee     = (float) get_post_meta( $catering_id, 'service_fee_amount', true );
		$author_id       = (int) get_post_field( 'post_author', $catering_id );

		if ( empty( $menu_items ) ) {
			return 0;
		}

		$order = wc_create_order(
			array(
				'customer_id' => $author_id > 0 ? $author_id : 0,
			)
		);

		if ( is_wp_error( $order ) ) {
			return 0;
		}

		foreach ( $menu_items as $item ) {
			$product_id = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
			$quantity   = isset( $item['quantity'] ) ? intval( $item['quantity'] ) : 1;
			$unit_price = isset( $item['price'] ) ? (float) $item['price'] : 0.0;
			$product    = wc_get_product( $product_id );

			if ( ! $product || $quantity <= 0 ) {
				continue;
			}

			$item_total = round( $unit_price * $quantity, wc_get_price_decimals() );
			$args       = array();

			if ( $unit_price > 0 ) {
				$args = array(
					'subtotal' => $item_total,
					'total'    => $item_total,
				);
			}

			$order->add_product( $product, $quantity, $args );
		}

		if ( $service_fee > 0 ) {
			$fee_item = new \WC_Order_Item_Fee();
			$fee_item->set_name( esc_html__( 'Catering Service Fee', 'restaurant-food-services' ) );
			$fee_item->set_total( round( $service_fee, wc_get_price_decimals() ) );
			$fee_item->set_tax_status( 'none' );
			$order->add_item( $fee_item );
		}

		$order->calculate_totals( false );

		if ( $total_price > 0 ) {
			$current_total = (float) $order->get_total();
			$difference    = round( $total_price - $current_total, wc_get_price_decimals() );

			if ( abs( $difference ) > 0.009 ) {
				$adjustment = new \WC_Order_Item_Fee();
				$adjustment->set_name( esc_html__( 'Catering Price Adjustment', 'restaurant-food-services' ) );
				$adjustment->set_total( $difference );
				$adjustment->set_tax_status( 'none' );
				$order->add_item( $adjustment );
				$order->calculate_totals( false );
			}
		}

		$event_date = get_post_meta( $catering_id, 'event_date', true );
		$location   = get_post_meta( $catering_id, 'location', true );

		$order->update_meta_data( '_catering_request_id', $catering_id );
		$order->update_meta_data( '_restaurant_order_type', 'catering' );
		$order->update_meta_data( 'catering_event_date', $event_date );
		$order->update_meta_data( 'catering_location', $location );

		$order->save();

		$this->sync_catering_checkout_link_meta( $catering_id, (int) $order->get_id() );

		return (int) $order->get_id();
	}

	/**
	 * Returns linked order ID for a catering request, if available.
	 *
	 * @param int $catering_id Catering request post ID.
	 *
	 * @return int
	 */
	protected function get_catering_linked_order_id( $catering_id ) {
		$converted_order_id = absint( get_post_meta( $catering_id, '_converted_order_id', true ) );
		$linked_order_id    = absint( get_post_meta( $catering_id, '_catering_order_id', true ) );
		$order_id           = $linked_order_id > 0 ? $linked_order_id : $converted_order_id;

		if ( $order_id <= 0 ) {
			return 0;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return 0;
		}

		return (int) $order_id;
	}

	/**
	 * Keeps order linkage + checkout URL meta in sync on the catering request.
	 *
	 * @param int $catering_id Catering request post ID.
	 * @param int $order_id    WooCommerce order ID.
	 *
	 * @return void
	 */
	protected function sync_catering_checkout_link_meta( $catering_id, $order_id ) {
		$catering_id = absint( $catering_id );
		$order_id    = absint( $order_id );

		if ( $catering_id <= 0 || $order_id <= 0 ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$checkout_url = $order->get_checkout_payment_url();

		update_post_meta( $catering_id, '_converted_order_id', $order_id );
		update_post_meta( $catering_id, '_catering_order_id', $order_id );
		update_post_meta( $catering_id, '_catering_checkout_url', esc_url_raw( $checkout_url ) );
	}

	/**
	 * Returns checkout URL for a linked catering order.
	 *
	 * @param int $catering_id Catering request post ID.
	 *
	 * @return string
	 */
	protected function get_catering_checkout_url( $catering_id ) {
		$order_id = $this->get_catering_linked_order_id( $catering_id );

		if ( $order_id <= 0 ) {
			return '';
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return '';
		}

		$checkout_url = $order->get_checkout_payment_url();

		if ( '' !== $checkout_url ) {
			update_post_meta( $catering_id, '_catering_checkout_url', esc_url_raw( $checkout_url ) );
		}

		return (string) $checkout_url;
	}

	/**
	 * Returns display label for catering status values.
	 *
	 * @param string $status Raw status value.
	 *
	 * @return string
	 */
	protected function get_catering_status_label( $status ) {
		$status = sanitize_key( (string) $status );

		$labels = array(
			'pending'  => esc_html__( 'Pending', 'restaurant-food-services' ),
			'approved' => esc_html__( 'Approved', 'restaurant-food-services' ),
			'rejected' => esc_html__( 'Rejected', 'restaurant-food-services' ),
		);

		if ( isset( $labels[ $status ] ) ) {
			return $labels[ $status ];
		}

		return esc_html__( 'Pending', 'restaurant-food-services' );
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
						<th><?php esc_html_e( 'Action', 'restaurant-food-services' ); ?></th>
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
						$checkout_url = '';
						if ( empty( $status ) ) {
							$status = 'pending';
						}

						if ( 'approved' === $status ) {
							$checkout_url = $this->get_catering_checkout_url( (int) $request->ID );
						}

						$status_label = $this->get_catering_status_label( $status );
						?>
						<tr>
							<td><?php echo esc_html( $location ); ?></td>
							<td><?php echo esc_html( $event_date ); ?></td>
							<td><?php echo esc_html( $guest_count ); ?></td>
							<td><span class="catering-status"><?php echo esc_html( $status_label ); ?></span></td>
							<td>
								<?php if ( '' !== $checkout_url ) : ?>
									<a class="button" href="<?php echo esc_url( $checkout_url ); ?>"><?php esc_html_e( 'Proceed to Checkout', 'restaurant-food-services' ); ?></a>
								<?php else : ?>
									<span>&mdash;</span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $request->post_date ); ?></td>
						</tr>
						<tr>
							<td colspan="6">
								<?php $this->render_catering_chat_panel( (int) $request->ID, 'frontend' ); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}

	/**
	 * Ensures newly submitted catering requests keep comments enabled for chat.
	 *
	 * @param int $post_id Catering request post ID.
	 *
	 * @return void
	 */
	public function ensure_catering_request_comments_enabled( $post_id ) {
		$post_id = absint( $post_id );
		$post    = get_post( $post_id );

		if ( ! $post || 'catering_request' !== $post->post_type ) {
			return;
		}

		if ( 'open' === $post->comment_status ) {
			return;
		}

		wp_update_post(
			array(
				'ID'             => $post_id,
				'comment_status' => 'open',
				'ping_status'    => 'closed',
			)
		);
	}

	/**
	 * Restricts chat comments to catering request owner or site admins.
	 *
	 * @param array<string,mixed> $commentdata Comment payload.
	 *
	 * @return array<string,mixed>
	 */
	public function enforce_catering_chat_comment_permissions( $commentdata ) {
		$post_id = isset( $commentdata['comment_post_ID'] ) ? absint( $commentdata['comment_post_ID'] ) : 0;

		if ( $post_id <= 0 ) {
			return $commentdata;
		}

		$post = get_post( $post_id );

		if ( ! $post || 'catering_request' !== $post->post_type ) {
			return $commentdata;
		}

		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in to participate in this discussion.', 'restaurant-food-services' ) );
		}

		$current_user_id = get_current_user_id();
		$can_access      = current_user_can( 'manage_options' ) || (int) $post->post_author === (int) $current_user_id;

		if ( ! $can_access ) {
			wp_die( esc_html__( 'You are not allowed to reply to this request.', 'restaurant-food-services' ) );
		}

		$commentdata['comment_type'] = 'catering_chat';

		return $commentdata;
	}

	/**
	 * Checks if current user can access a catering request chat.
	 *
	 * @param int $post_id Catering request ID.
	 *
	 * @return bool
	 */
	protected function user_can_access_catering_chat( $post_id ) {
		$post = get_post( absint( $post_id ) );

		if ( ! $post || 'catering_request' !== $post->post_type ) {
			return false;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return (int) $post->post_author === (int) get_current_user_id();
	}

	/**
	 * Renders reusable chat panel for catering requests.
	 *
	 * @param int    $post_id Catering request ID.
	 * @param string $context Render context.
	 *
	 * @return void
	 */
	protected function render_catering_chat_panel( $post_id, $context = 'frontend' ) {
		$post_id = absint( $post_id );

		if ( ! $this->user_can_access_catering_chat( $post_id ) ) {
			echo '<p>' . esc_html__( 'You do not have access to this discussion.', 'restaurant-food-services' ) . '</p>';
			return;
		}

		$this->ensure_catering_request_comments_enabled( $post_id );

		$comments = get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'approve',
				'orderby' => 'comment_date_gmt',
				'order'   => 'ASC',
			)
		);

		echo '<div class="restaurant-catering-chat restaurant-catering-chat--' . esc_attr( sanitize_key( $context ) ) . '">';
		echo '<h4>' . esc_html__( 'Discussion', 'restaurant-food-services' ) . '</h4>';
		echo '<div class="restaurant-catering-chat-messages" data-catering-chat-scroll="1">';

		if ( empty( $comments ) ) {
			echo '<p class="restaurant-catering-chat-empty">' . esc_html__( 'No messages yet. Start the discussion below.', 'restaurant-food-services' ) . '</p>';
		} else {
			foreach ( $comments as $comment ) {
				$author_label = get_comment_author( $comment );
				$timestamp    = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $comment->comment_date, true );
				$author_class = 'restaurant-catering-chat-message--user';

				if ( (int) $comment->user_id === (int) get_post_field( 'post_author', $post_id ) ) {
					$author_class = 'restaurant-catering-chat-message--owner';
				} elseif ( $comment->user_id > 0 && user_can( (int) $comment->user_id, 'manage_options' ) ) {
					$author_class = 'restaurant-catering-chat-message--admin';
				}

				echo '<article class="restaurant-catering-chat-message ' . esc_attr( $author_class ) . '">';
				echo '<header><strong>' . esc_html( $author_label ) . '</strong> <span>' . esc_html( $timestamp ) . '</span></header>';
				echo '<div class="restaurant-catering-chat-message__body">' . wp_kses_post( wpautop( $comment->comment_content ) ) . '</div>';
				echo '</article>';
			}
		}

		echo '</div>';

		if ( is_user_logged_in() && comments_open( $post_id ) ) {
			comment_form(
				array(
					'title_reply'          => esc_html__( 'Reply', 'restaurant-food-services' ),
					'label_submit'         => esc_html__( 'Send Message', 'restaurant-food-services' ),
					'comment_notes_before' => '',
					'comment_notes_after'  => '',
					'logged_in_as'         => '',
					'class_form'           => 'restaurant-catering-chat-form',
					'class_submit'         => 'button button-primary',
					'comment_field'        => '<p class="comment-form-comment"><label for="comment">' . esc_html__( 'Message', 'restaurant-food-services' ) . '</label><textarea id="comment" name="comment" cols="45" rows="4" required="required"></textarea></p>',
				),
				$post_id
			);
		}

		echo '</div>';

		static $assets_printed = false;

		if ( $assets_printed ) {
			return;
		}

		$assets_printed = true;

		echo '<style>
		.restaurant-catering-chat{border:1px solid #e5e5e5;border-radius:6px;padding:12px;background:#fff;}
		.restaurant-catering-chat h4{margin:0 0 10px;}
		.restaurant-catering-chat-messages{max-height:260px;overflow:auto;border:1px solid #ececec;border-radius:4px;padding:10px;background:#fafafa;margin-bottom:12px;}
		.restaurant-catering-chat-message{padding:8px 10px;border-radius:4px;background:#fff;border:1px solid #e9e9e9;margin-bottom:8px;}
		.restaurant-catering-chat-message:last-child{margin-bottom:0;}
		.restaurant-catering-chat-message header{display:flex;justify-content:space-between;gap:10px;font-size:12px;color:#666;margin-bottom:6px;}
		.restaurant-catering-chat-message--owner{border-left:3px solid #2c7be5;}
		.restaurant-catering-chat-message--admin{border-left:3px solid #2ea44f;}
		.restaurant-catering-chat-message--user{border-left:3px solid #888;}
		.restaurant-catering-chat-form .comment-form-author,
		.restaurant-catering-chat-form .comment-form-email,
		.restaurant-catering-chat-form .comment-form-url,
		.restaurant-catering-chat-form .form-submit{margin-top:10px;}
		</style>';

		echo '<script>
		(function(){
			function scrollCateringChats(){
				document.querySelectorAll("[data-catering-chat-scroll=\"1\"]").forEach(function(node){
					node.scrollTop = node.scrollHeight;
				});
			}
			if (document.readyState === "loading") {
				document.addEventListener("DOMContentLoaded", scrollCateringChats);
			} else {
				scrollCateringChats();
			}
		})();
		</script>';
	}
}
