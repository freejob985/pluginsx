<?php
declare(strict_types=1);
/**
 * Orders Jet - Realtime Service (Pusher)
 *
 * @package Orders_Jet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Pusher\Pusher;

class Orders_Jet_Realtime_Service {

	/**
	 * Singleton instance.
	 *
	 * @var Orders_Jet_Realtime_Service|null
	 */
	private static $instance = null;

	/**
	 * Pusher client.
	 *
	 * @var Pusher|null
	 */
	private $pusher;

	/**
	 * Whether realtime is enabled.
	 *
	 * @var bool
	 */
	private $enabled = false;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->enabled = ORDERS_JET_PUSHER_ENABLED && class_exists( Pusher::class );

		if ( $this->enabled ) {
			// Only set cluster if it's not empty (some apps don't need cluster specified)
			$options = array(
				'useTLS' => ORDERS_JET_PUSHER_USE_TLS,
			);
			
			// Add cluster only if specified and not empty
			if ( ! empty( ORDERS_JET_PUSHER_CLUSTER ) ) {
				$options['cluster'] = ORDERS_JET_PUSHER_CLUSTER;
			}

			try {
				$this->pusher = new Pusher(
					ORDERS_JET_PUSHER_KEY,
					ORDERS_JET_PUSHER_SECRET,
					ORDERS_JET_PUSHER_APP_ID,
					$options
				);
			} catch ( \Exception $e ) {
				$this->enabled = false;
				$error_msg = 'Pusher initialization failed: ' . $e->getMessage();
				oj_error_log( $error_msg, 'REALTIME' );
				
				// If cluster error, provide helpful message
				if ( strpos( $e->getMessage(), 'not in this cluster' ) !== false ) {
					oj_error_log( 'Pusher cluster mismatch. Check your Pusher dashboard for the correct cluster (eu, us2, us3, ap1, etc.)', 'REALTIME' );
				}
			}
		}

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Get singleton instance.
	 */
	public static function instance(): Orders_Jet_Realtime_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Whether realtime delivery is enabled.
	 */
	public function is_enabled(): bool {
		return $this->enabled && $this->pusher instanceof Pusher;
	}

	/**
	 * Publish a notification payload to Pusher.
	 *
	 * @param array $notification Notification data.
	 */
	public function publish_notification( array $notification ): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		$channels = $this->get_channels_for_notification( $notification );
		$channels = array_unique( array_filter( $channels ) );

		if ( empty( $channels ) ) {
			return false;
		}

		$payload = array(
			'notification' => $this->normalize_notification_payload( $notification ),
		);

		try {
			foreach ( $channels as $channel ) {
				$this->pusher->trigger( $channel, 'oj.notification', $payload );
			}
			return true;
		} catch ( \Exception $e ) {
			oj_error_log( 'Pusher trigger failed: ' . $e->getMessage(), 'REALTIME' );
			return false;
		}
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'orders-jet/v1',
			'/pusher/auth',
			array(
				'methods'             => 'POST',
				'permission_callback' => array( $this, 'user_can_access_notifications' ),
				'callback'            => array( $this, 'handle_pusher_auth' ),
			)
		);
	}

	/**
	 * REST callback used by Pusher JS to authenticate channels.
	 */
	public function handle_pusher_auth( \WP_REST_Request $request ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'oj_pusher_disabled', __( 'Realtime service disabled', 'orders-jet' ), array( 'status' => 500 ) );
		}

		// Pusher sends data as form-encoded POST, try multiple sources
		$socket_id    = sanitize_text_field( $request->get_param( 'socket_id' ) ?: ( $_POST['socket_id'] ?? '' ) );
		$channel_name = sanitize_text_field( $request->get_param( 'channel_name' ) ?: ( $_POST['channel_name'] ?? '' ) );

		if ( empty( $socket_id ) || empty( $channel_name ) ) {
			return new \WP_Error( 'oj_pusher_invalid_params', __( 'Missing Pusher parameters', 'orders-jet' ), array( 'status' => 400 ) );
		}

		$user_id       = get_current_user_id();
		$user_function = oj_get_user_function();

		$allowed_channels = $this->get_channels_for_user( $user_id, $user_function );

		if ( ! in_array( $channel_name, $allowed_channels, true ) ) {
			return new \WP_Error( 'oj_pusher_forbidden', __( 'Not allowed to join channel', 'orders-jet' ), array( 'status' => 403 ) );
		}

		try {
			$custom_data = wp_json_encode( array(
				'user_id' => $user_id,
			) );

			$auth_response = $this->pusher->authorizeChannel(
				$channel_name,
				$socket_id,
				$custom_data
			);

			// authorizeChannel returns a JSON string, decode it for REST response
			$auth_data = json_decode( $auth_response, true );
			return rest_ensure_response( $auth_data );
		} catch ( \Exception $e ) {
			oj_error_log( 'Pusher auth failed: ' . $e->getMessage(), 'REALTIME' );
			return new \WP_Error( 'oj_pusher_auth_error', __( 'Realtime auth failed', 'orders-jet' ), array( 'status' => 500 ) );
		}
	}

	/**
	 * Check if current user can access notifications.
	 */
	public function user_can_access_notifications(): bool {
		return current_user_can( 'access_oj_manager_dashboard' )
			|| current_user_can( 'access_oj_kitchen_dashboard' )
			|| current_user_can( 'access_oj_waiter_dashboard' )
			|| current_user_can( 'manage_options' );
	}

	/**
	 * Build realtime config for JS bootstrap.
	 *
	 * @param int    $user_id User ID.
	 * @param string $user_function User function (manager/kitchen/waiter).
	 */
	public function get_client_bootstrap_config( int $user_id, string $user_function = '' ): array {
		if ( ! $this->is_enabled() ) {
			return array(
				'enabled' => false,
			);
		}

		return array(
			'enabled'      => true,
			'key'          => ORDERS_JET_PUSHER_KEY,
			'cluster'      => ORDERS_JET_PUSHER_CLUSTER,
			'authEndpoint' => rest_url( 'orders-jet/v1/pusher/auth' ),
			'authNonce'    => wp_create_nonce( 'wp_rest' ),
			'channels'     => $this->get_channels_for_user( $user_id, $user_function ),
			'options'      => array(
				'forceTLS' => ORDERS_JET_PUSHER_USE_TLS,
			),
		);
	}

	/**
	 * Determine which channels a notification should broadcast to.
	 */
	private function get_channels_for_notification( array $notification ): array {
		$channels = array( 'private-oj-role-manager' );
		$type     = $notification['type'] ?? '';

		switch ( $type ) {
			case 'new_order':
			case 'table_order':
			case 'pickup_order':
				$channels[] = 'private-oj-role-kitchen';
				$channels[] = 'private-oj-role-waiter';
				break;
			case 'order_ready':
			case 'kitchen_food_ready':
			case 'kitchen_beverage_ready':
			case 'invoice_request':
				$channels[] = 'private-oj-role-waiter';
				break;
			case 'order_cancelled':
				$channels[] = 'private-oj-role-kitchen';
				$channels[] = 'private-oj-role-waiter';
				break;
			default:
				break;
		}

		if ( ! empty( $notification['table_number'] ) ) {
			$channels[] = $this->build_table_channel( $notification['table_number'] );
		}

		if ( ! empty( $notification['target_user_id'] ) ) {
			$channels[] = $this->build_user_channel( (int) $notification['target_user_id'] );
		}

		return $channels;
	}

	/**
	 * Get channel list for a user.
	 */
	public function get_channels_for_user( int $user_id, string $user_function = '' ): array {
		if ( $user_id === 0 ) {
			return array();
		}

		$channels = array(
			$this->build_user_channel( $user_id ),
		);

		if ( ! empty( $user_function ) ) {
			$channels[] = 'private-oj-role-' . sanitize_key( $user_function );
		}

		if ( $user_function === 'waiter' ) {
			$assigned_tables = get_user_meta( $user_id, '_oj_assigned_tables', true );
			if ( is_array( $assigned_tables ) ) {
				foreach ( $assigned_tables as $table_number ) {
					$channels[] = $this->build_table_channel( $table_number );
				}
			}
		}

		if ( $user_function === 'kitchen' ) {
			$kitchen_type = oj_get_kitchen_specialization();
			if ( ! empty( $kitchen_type ) ) {
				$channels[] = 'private-oj-kitchen-' . sanitize_key( $kitchen_type );
			}
		}

		return array_values( array_unique( array_filter( $channels ) ) );
	}

	/**
	 * Normalize notification payload for the frontend.
	 */
	private function normalize_notification_payload( array $notification ): array {
		if ( ! isset( $notification['time_ago'] ) ) {
			$notification['time_ago'] = __( 'Just now', 'orders-jet' );
		}

		if ( ! isset( $notification['created_at'] ) ) {
			$notification['created_at'] = current_time( 'mysql' );
		}

		if ( ! isset( $notification['timestamp_unix'] ) ) {
			$notification['timestamp_unix'] = current_time( 'timestamp' );
		}

		return $notification;
	}

	/**
	 * Build user-specific channel.
	 */
	private function build_user_channel( int $user_id ): string {
		return 'private-oj-user-' . $user_id;
	}

	/**
	 * Build table channel name.
	 */
	private function build_table_channel( string $table_number ): string {
		return 'private-oj-table-' . sanitize_key( strtolower( $table_number ) );
	}
}

Orders_Jet_Realtime_Service::instance();
