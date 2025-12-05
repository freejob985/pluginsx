<?php
declare(strict_types=1);
/**
 * Orders Jet - Notification Center Component
 * Reusable notification bell + dropdown panel
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render notification center component
 * Can be called from any template
 */
function oj_render_notification_center() {
    // Check if user has access
    if (!current_user_can('access_oj_manager_dashboard') &&
        !current_user_can('access_oj_kitchen_dashboard') &&
        !current_user_can('access_oj_waiter_dashboard') &&
        !current_user_can('manage_options')) {
        return;
    }
    
    // Enqueue notification assets
    oj_enqueue_notification_assets();
    
    // Get initial notification count
    $user_id = get_current_user_id();
    $user_function = oj_get_user_function();
    
    // Get kitchen specialization if user is kitchen staff
    $kitchen_type = null;
    if ($user_function === 'kitchen') {
        $kitchen_type = oj_get_kitchen_specialization();
    }
    
    $all_notifications = get_option('oj_dashboard_notifications', array());
    
    // Add waiter-specific notifications for waiters
    if ($user_function === 'waiter') {
        $waiter_notifications = get_user_meta($user_id, '_oj_waiter_notifications', true) ?: array();
        foreach ($waiter_notifications as $waiter_notification) {
            // Convert waiter notification format to general notification format
            $all_notifications[] = array(
                'id' => $waiter_notification['id'],
                'type' => $waiter_notification['type'],
                'table_number' => $waiter_notification['table_number'],
                'message' => $waiter_notification['message'],
                'timestamp' => $waiter_notification['timestamp'],
                'priority' => $waiter_notification['priority'] ?? 'normal',
                'status' => $waiter_notification['status'] ?? 'unread',
                'created_at' => $waiter_notification['created_at'],
                'read_by' => $waiter_notification['status'] === 'read' ? array($user_id) : array(),
                'waiter_specific' => true // Flag to identify waiter-specific notifications
            );
        }
    }
    
    // Filter and count unread notifications
    $unread_count = 0;
    foreach ($all_notifications as $notification) {
        if (isset($notification['waiter_specific']) && $notification['waiter_specific']) {
            // Waiter-specific notification - always show to the waiter
            if ($notification['status'] === 'unread') {
                $unread_count++;
            }
        } elseif (oj_is_notification_for_user($notification, $user_function, $kitchen_type)) {
            // General notification - use existing filter logic
            $read_by = $notification['read_by'] ?? array();
            if (!in_array($user_id, $read_by)) {
                $unread_count++;
            }
        }
    }
    
    ?>
    <!-- Notification Center -->
    <div class="oj-notification-center">
        <button class="oj-notification-bell" id="ojNotificationBell" 
                title="<?php _e('Notifications', 'orders-jet'); ?>"
                aria-label="<?php _e('Notifications', 'orders-jet'); ?>">
            <span class="dashicons dashicons-bell"></span>
            <span class="oj-notification-badge" id="ojNotificationBadge" 
                  style="display: <?php echo $unread_count > 0 ? 'flex' : 'none'; ?>;">
                <?php echo $unread_count; ?>
            </span>
        </button>
        
        <!-- Notification Dropdown Panel -->
        <div class="oj-notification-panel" id="ojNotificationPanel" style="display: none;">
            <div class="oj-notification-panel-header">
                <h3><?php _e('Notifications', 'orders-jet'); ?></h3>
                <button class="oj-mark-all-read" id="ojMarkAllRead" 
                        title="<?php _e('Mark all as read', 'orders-jet'); ?>">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Mark all read', 'orders-jet'); ?>
                </button>
            </div>
            <div class="oj-notification-list" id="ojNotificationList">
                <!-- Notifications will be loaded here via AJAX -->
                <div class="oj-notification-loading">
                    <span class="dashicons dashicons-update spin"></span>
                    <p><?php _e('Loading notifications...', 'orders-jet'); ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Enqueue notification center assets
 */
function oj_enqueue_notification_assets() {
    $version = ORDERS_JET_VERSION;
    
    // Enqueue CSS
    wp_enqueue_style(
        'oj-notifications',
        ORDERS_JET_PLUGIN_URL . 'assets/css/oj-notifications.css',
        array(),
        $version
    );
    
    // Get Pusher realtime config
    $realtime_service = Orders_Jet_Realtime_Service::instance();
    $user_id = get_current_user_id();
    $user_function = oj_get_user_function();
    $realtime_config = $realtime_service->get_client_bootstrap_config($user_id, $user_function);
    
    // Enqueue Pusher JS if realtime is enabled
    if ($realtime_config['enabled']) {
        wp_enqueue_script(
            'pusher',
            'https://js.pusher.com/8.2.0/pusher.min.js',
            array(),
            '8.2.0',
            true
        );
    }
    
    // Enqueue JavaScript (after Pusher if enabled)
    wp_enqueue_script(
        'oj-notifications',
        ORDERS_JET_PLUGIN_URL . 'assets/js/oj-notifications.js',
        $realtime_config['enabled'] ? array('jquery', 'pusher') : array('jquery'),
        $version,
        true
    );
    
    // Localize script
    wp_localize_script('oj-notifications', 'ojNotificationsData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('oj_notifications_nonce'),
        'dashboardNonce' => wp_create_nonce('oj_dashboard_nonce'), // For dashboard AJAX actions
        'userId' => $user_id, // Pass user ID from PHP
        'userFunction' => $user_function, // Pass user function (waiter, manager, etc.)
        'soundsUrl' => ORDERS_JET_PLUGIN_URL . 'assets/sounds/',
        'soundEnabled' => get_user_meta($user_id, 'oj_notification_sounds', true) !== 'disabled',
        'soundVolume' => (float) get_user_meta($user_id, 'oj_notification_sound_volume', true) ?: 0.7,
        'autoRefresh' => 30, // seconds (fallback when Pusher disabled)
        'realtime' => $realtime_config, // Pusher configuration
        'i18n' => array(
            'newNotification' => __('New notification', 'orders-jet'),
            'markAsRead' => __('Mark as read', 'orders-jet'),
            'noNotifications' => __('No new notifications', 'orders-jet'),
            'loadingError' => __('Failed to load notifications', 'orders-jet'),
            'markedAllRead' => __('All notifications marked as read', 'orders-jet'),
            'connectionError' => __('Connection error', 'orders-jet'),
            'justNow' => __('Just now', 'orders-jet'),
            'minutesAgo' => __('%s minutes ago', 'orders-jet'),
            'hoursAgo' => __('%s hours ago', 'orders-jet'),
            'daysAgo' => __('%s days ago', 'orders-jet'),
        )
    ));
}

/**
 * Check if notification is for specific user function
 * 
 * @param array $notification Notification data
 * @param string $user_function User function (manager/kitchen/waiter)
 * @param string|null $kitchen_type Kitchen specialization (food/beverages) if applicable
 * @return bool
 */
function oj_is_notification_for_user($notification, $user_function, $kitchen_type = null) {
    $type = $notification['type'] ?? '';
    
    switch ($type) {
        case 'new_order':
        case 'table_order':
        case 'pickup_order':
            // Kitchen users need special filtering by kitchen type
            if ($user_function === 'kitchen') {
                return oj_should_kitchen_see_order($notification, $kitchen_type);
            }
            
            // Waiter gets only notifications for their assigned tables
            if ($user_function === 'waiter') {
                return oj_should_waiter_see_order($notification);
            }
            
            // Manager gets all new order notifications
            return $user_function === 'manager';
            
        case 'order_ready':
        case 'kitchen_food_ready':
        case 'kitchen_beverage_ready':
            // Waiter gets only for their assigned tables
            if ($user_function === 'waiter') {
                return oj_should_waiter_see_order($notification);
            }
            // Manager gets all ready notifications
            return $user_function === 'manager';
            
        case 'invoice_request':
            // Waiter gets only for their assigned tables
            if ($user_function === 'waiter') {
                return oj_should_waiter_see_order($notification);
            }
            // Manager gets all invoice requests
            return $user_function === 'manager';
            
        case 'order_completed':
            // Only Manager gets completion notifications
            return $user_function === 'manager';
            
        case 'order_cancelled':
            // Everyone gets cancellation notifications
            return true;
            
        default:
            // Unknown type - show to managers only
            return $user_function === 'manager';
    }
}

/**
 * Check if kitchen user should see this order notification
 * 
 * @param array $notification Notification data
 * @param string|null $kitchen_type Kitchen specialization (food/beverages)
 * @return bool
 */
function oj_should_kitchen_see_order($notification, $kitchen_type) {
    // If no kitchen type specified, show all (shouldn't happen)
    if (!$kitchen_type) {
        return true;
    }
    
    // Get order to check its kitchen type
    $order_id = $notification['order_id'] ?? 0;
    if (!$order_id) {
        return false;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        return false;
    }
    
    // Use kitchen service to determine order's kitchen type
    $kitchen_service = new Orders_Jet_Kitchen_Service();
    $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
    $order_kitchen_type = $kitchen_status['kitchen_type'] ?? 'food';
    
    // Show notification based on match
    if ($order_kitchen_type === 'mixed') {
        // Mixed orders go to both kitchens
        return true;
    }
    
    // Show only if kitchen type matches
    return $order_kitchen_type === $kitchen_type;
}

/**
 * Check if waiter user should see this order notification
 * 
 * @param array $notification Notification data
 * @return bool
 */
function oj_should_waiter_see_order($notification) {
    // Get current user's assigned tables
    $user_id = get_current_user_id();
    $assigned_tables = get_user_meta($user_id, '_oj_assigned_tables', true);
    
    // If no tables assigned, waiter sees nothing
    if (empty($assigned_tables) || !is_array($assigned_tables)) {
        return false;
    }
    
    // Check if notification has table_number directly (for table-based notifications like invoice_request)
    $table_number = $notification['table_number'] ?? '';
    if (!empty($table_number)) {
        // Direct table number - check if it's in assigned tables
        return in_array($table_number, $assigned_tables);
    }
    
    // Otherwise, get order to check its table number (for order-based notifications)
    $order_id = $notification['order_id'] ?? 0;
    if (!$order_id) {
        return false;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        return false;
    }
    
    // Get order's table number
    $order_table = $order->get_meta('_oj_table_number');
    
    // If order has no table (pickup), waiter doesn't see it
    if (empty($order_table)) {
        return false;
    }
    
    // Check if order's table is in waiter's assigned tables
    return in_array($order_table, $assigned_tables);
}

/**
 * Get notification icon based on type
 * 
 * @param string $type Notification type
 * @return string Icon HTML
 */
function oj_get_notification_icon($type) {
    $icons = array(
        'new_order' => '🆕',
        'table_order' => '🍽️',
        'pickup_order' => '📦',
        'order_ready' => '✅',
        'kitchen_food_ready' => '🍕',
        'kitchen_beverage_ready' => '🥤',
        'invoice_request' => '🔔',
        'order_completed' => '💰',
        'table_closed' => '🏁',
        'table_available' => '🆓',
        'order_cancelled' => '❌',
    );
    
    return $icons[$type] ?? '📋';
}

/**
 * Format notification message
 * 
 * @param array $notification Notification data
 * @return string Formatted message
 */
function oj_format_notification_message($notification) {
    $type = $notification['type'] ?? '';
    $order_number = $notification['order_number'] ?? '';
    $table_number = $notification['table_number'] ?? '';
    
    switch ($type) {
        case 'new_order':
        case 'table_order':
            if ($table_number) {
                return sprintf(
                    __('New order #%s for Table %s', 'orders-jet'),
                    $order_number,
                    $table_number
                );
            }
            return sprintf(__('New order #%s', 'orders-jet'), $order_number);
            
        case 'pickup_order':
            return sprintf(__('New pickup order #%s', 'orders-jet'), $order_number);
            
        case 'order_ready':
            return sprintf(__('Order #%s is ready', 'orders-jet'), $order_number);
            
        case 'kitchen_food_ready':
            return sprintf(__('Food ready for order #%s', 'orders-jet'), $order_number);
            
        case 'kitchen_beverage_ready':
            return sprintf(__('Beverages ready for order #%s', 'orders-jet'), $order_number);
            
        case 'invoice_request':
            return sprintf(
                __('Invoice requested for Table %s', 'orders-jet'),
                $table_number
            );
            
        case 'order_completed':
            return sprintf(__('Order #%s completed', 'orders-jet'), $order_number);
            
        case 'table_closed':
            if ($table_number) {
                return sprintf(__('Table %s closed', 'orders-jet'), $table_number);
            }
            return sprintf(__('Table closed - Order #%s', 'orders-jet'), $order_number);
            
        case 'table_available':
            if ($table_number) {
                return sprintf(__('Table %s is now available', 'orders-jet'), $table_number);
            }
            return __('Table is now available for assignment', 'orders-jet');

        case 'order_cancelled':
            return sprintf(__('Order #%s cancelled', 'orders-jet'), $order_number);
            
        default:
            return $notification['message'] ?? __('New notification', 'orders-jet');
    }
}

/**
 * Get time ago string
 * DEPRECATED: Use oj_get_time_ago() from time-helpers.php instead
 * Kept for backward compatibility
 * 
 * @param string $datetime MySQL datetime string
 * @return string Time ago string
 */
function oj_time_ago($datetime) {
    return oj_get_time_ago($datetime);
}

