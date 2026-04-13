<?php
/**
 * Activation/deactivation lifecycle notices.
 *
 * @package RestaurantFoodServices
 */

namespace Restaurant\FoodServices;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures lifecycle warnings/errors and displays them in wp-admin.
 */
class Lifecycle_Notices {

	/**
	 * Option key for buffered notices.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'restaurant_food_services_lifecycle_notices';

	/**
	 * Runs a callback while capturing PHP warnings/notices/exceptions.
	 *
	 * @param callable $callback Lifecycle callback.
	 * @param string   $context  Context label.
	 *
	 * @return void
	 */
	public static function capture( $callback, $context ) {
		$captured = array();
		$context  = (string) $context;

		register_shutdown_function(
			static function () use ( &$captured, $context ) {
				$error = error_get_last();

				if ( ! is_array( $error ) || empty( $error['message'] ) ) {
					return;
				}

				$fatal_types = array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_PARSE );

				if ( ! in_array( (int) $error['type'], $fatal_types, true ) ) {
					return;
				}

				$captured[] = array(
					'type'    => 'error',
					'message' => sprintf(
						'%1$s Error: %2$s in %3$s on line %4$d',
						$context,
						(string) $error['message'],
						isset( $error['file'] ) ? (string) $error['file'] : '',
						isset( $error['line'] ) ? (int) $error['line'] : 0
					),
					'cause'   => 'Fatal runtime error: ' . (string) $error['message'],
				);

				foreach ( $captured as $notice ) {
					self::add_notice(
						$notice['type'],
						$notice['message'],
						isset( $notice['cause'] ) ? (string) $notice['cause'] : ''
					);
				}
			}
		);

		set_error_handler(
			static function ( $errno, $errstr, $errfile, $errline ) use ( &$captured, $context ) {
				if ( ! ( error_reporting() & $errno ) ) {
					return false;
				}

				$severity = self::map_error_label( $errno );
				$type     = self::map_notice_type( $errno );
				$cause    = self::map_error_cause( $errno, $errstr );
				$message  = sprintf(
					'%1$s %2$s: %3$s in %4$s on line %5$d',
					$context,
					$severity,
					$errstr,
					$errfile,
					(int) $errline
				);

				$captured[] = array(
					'type'    => $type,
					'message' => $message,
					'cause'   => $cause,
				);

				return true;
			}
		);

		try {
			call_user_func( $callback );
		} catch ( \Exception $e ) {
			$previous = $e->getPrevious();
			$cause    = get_class( $e );

			if ( $previous instanceof \Exception ) {
				$cause .= ' <- ' . get_class( $previous ) . ': ' . $previous->getMessage();
			}

			$captured[] = array(
				'type'    => 'error',
				'message' => sprintf(
					'%1$s Error: %2$s in %3$s on line %4$d',
					$context,
					$e->getMessage(),
					$e->getFile(),
					(int) $e->getLine()
				),
				'cause'   => $cause,
			);
		} finally {
			restore_error_handler();
		}

		foreach ( $captured as $notice ) {
			self::add_notice(
				$notice['type'],
				$notice['message'],
				isset( $notice['cause'] ) ? (string) $notice['cause'] : ''
			);
		}
	}

	/**
	 * Renders buffered lifecycle notices in wp-admin.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notices = get_option( self::OPTION_KEY, array() );

		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}

		delete_option( self::OPTION_KEY );

		foreach ( $notices as $notice ) {
			$type    = isset( $notice['type'] ) ? sanitize_key( (string) $notice['type'] ) : 'warning';
			$message = isset( $notice['message'] ) ? (string) $notice['message'] : '';
			$cause   = isset( $notice['cause'] ) ? (string) $notice['cause'] : '';

			if ( '' === $message ) {
				continue;
			}

			$allowed_types = array( 'error', 'warning', 'success', 'info' );
			if ( ! in_array( $type, $allowed_types, true ) ) {
				$type = 'warning';
			}

			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p>';
			if ( '' !== $cause ) {
				echo '<p><strong>' . esc_html__( 'Cause:', 'restaurant-food-services' ) . '</strong> ' . esc_html( $cause ) . '</p>';
			}
			echo '</div>';
		}
	}

	/**
	 * Adds a buffered lifecycle notice.
	 *
	 * @param string $type    Notice type.
	 * @param string $message Notice message.
	 * @param string $cause   Root cause text.
	 *
	 * @return void
	 */
	protected static function add_notice( $type, $message, $cause = '' ) {
		$notices = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $notices ) ) {
			$notices = array();
		}

		$notices[] = array(
			'type'    => $type,
			'message' => $message,
			'cause'   => $cause,
		);

		if ( count( $notices ) > 20 ) {
			$notices = array_slice( $notices, -20 );
		}

		update_option( self::OPTION_KEY, $notices, false );
	}

	/**
	 * Maps PHP error level/message to a cause label.
	 *
	 * @param int    $errno  PHP error number.
	 * @param string $errstr PHP error text.
	 *
	 * @return string
	 */
	protected static function map_error_cause( $errno, $errstr ) {
		switch ( $errno ) {
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				return 'Deprecated API usage: ' . $errstr;
			case E_NOTICE:
			case E_USER_NOTICE:
				return 'Runtime notice: ' . $errstr;
			case E_WARNING:
			case E_USER_WARNING:
				return 'Runtime warning: ' . $errstr;
			default:
				return 'Runtime error: ' . $errstr;
		}
	}

	/**
	 * Maps PHP error level to notice severity label.
	 *
	 * @param int $errno PHP error number.
	 *
	 * @return string
	 */
	protected static function map_error_label( $errno ) {
		switch ( $errno ) {
			case E_NOTICE:
			case E_USER_NOTICE:
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				return 'Notice';
			case E_WARNING:
			case E_USER_WARNING:
				return 'Warning';
			default:
				return 'Error';
		}
	}

	/**
	 * Maps PHP error level to WordPress admin notice type.
	 *
	 * @param int $errno PHP error number.
	 *
	 * @return string
	 */
	protected static function map_notice_type( $errno ) {
		switch ( $errno ) {
			case E_NOTICE:
			case E_USER_NOTICE:
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				return 'warning';
			case E_WARNING:
			case E_USER_WARNING:
				return 'warning';
			default:
				return 'error';
		}
	}
}

