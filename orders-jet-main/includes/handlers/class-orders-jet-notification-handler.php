<?php
declare(strict_types=1);
/**
 * Orders Jet - Notification Handler
 * Handles AJAX requests for notification center
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Notification_Handler {
    
    /**
     * Notification service instance
     */
    private $notification_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->notification_service = new Orders_Jet_Notification_Service();
        $this->register_ajax_handlers();
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Get notifications
        add_action('wp_ajax_oj_get_notifications', array($this, 'ajax_get_notifications'));
        
        // Get notification count
        add_action('wp_ajax_oj_get_notification_count', array($this, 'ajax_get_notification_count'));
        
        // Mark notification as read
        add_action('wp_ajax_oj_mark_notification_read', array($this, 'ajax_mark_notification_read'));
        
        // Mark all notifications as read
        add_action('wp_ajax_oj_mark_all_notifications_read', array($this, 'ajax_mark_all_notifications_read'));
        
        // Clear old notifications
        add_action('wp_ajax_oj_clear_notifications', array($this, 'ajax_clear_notifications'));
    }
    
    /**
     * Get notifications for current user
     */
    public function ajax_get_notifications() {
        check_ajax_referer('oj_notifications_nonce', 'nonce');
        
        try {
            // Check permissions
            if (!$this->user_can_access_notifications()) {
                wp_send_json_error(array(
                    'message' => __('Unauthorized access', 'orders-jet')
                ));
                return;
            }
            
            $user_id = get_current_user_id();
            $user_function = oj_get_user_function();
            
            // Get kitchen specialization if user is kitchen staff
            $kitchen_type = null;
            if ($user_function === 'kitchen') {
                $kitchen_type = oj_get_kitchen_specialization();
            }
            
            // Get all notifications
            $all_notifications = get_option('oj_dashboard_notifications', array());
            
            // Filter notifications for current user based on their role
            $user_notifications = $this->filter_notifications_by_user($all_notifications, $user_function, $kitchen_type);
            
            // Add waiter-specific notifications for waiters (calls AND orders)
            if ($user_function === 'waiter') {
                $waiter_specific_notifications = $this->get_waiter_specific_notifications($user_id);
                $user_notifications = array_merge($user_notifications, $waiter_specific_notifications);
            }
            
            // Sort by timestamp (newest first)
            usort($user_notifications, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            // Limit to last 20 notifications
            $user_notifications = array_slice($user_notifications, 0, 20);
            
            // Add pre-calculated time_ago to each notification (CENTRALIZED TIME CALCULATION)
            foreach ($user_notifications as &$notification) {
                $notification['time_ago'] = oj_get_time_ago($notification['created_at']);
            }
            
            // Count unread
            $unread_count = count(array_filter($user_notifications, function($n) use ($user_id) {
                $read_by = $n['read_by'] ?? array();
                return !in_array($user_id, $read_by);
            }));
            
            wp_send_json_success(array(
                'notifications' => $user_notifications,
                'unread_count' => $unread_count,
                'total_count' => count($user_notifications)
            ));
            
        } catch (Exception $e) {
            oj_error_log('Get notifications error: ' . $e->getMessage(), 'NOTIFICATIONS');
            wp_send_json_error(array(
                'message' => __('Failed to load notifications', 'orders-jet')
            ));
        }
    }
    
    /**
     * Get all waiter-specific notifications for a specific waiter
     * Converts waiter-specific notifications to main notification system format
     * Handles both waiter calls AND new order notifications
     * 
     * @param int $user_id Waiter user ID
     * @return array Formatted notifications
     */
    private function get_waiter_specific_notifications($user_id) {
        $waiter_notifications = get_user_meta($user_id, '_oj_waiter_notifications', true) ?: array();
        $formatted_notifications = array();
        
        foreach ($waiter_notifications as $notification) {
            // Check if table is still unassigned (for claim button logic)
            $table_claimed = false;
            if (!empty($notification['table_number'])) {
                $table_claimed = $this->is_table_assigned($notification['table_number']);
            }
            
            $formatted_notification = array(
                'id' => 'ws_' . $notification['id'], // Prefix to avoid conflicts
                'type' => $notification['type'],
                'table_number' => $notification['table_number'] ?? '',
                'table_claimed' => $table_claimed, // Add table assignment status
                'priority' => $notification['priority'] ?? 'normal',
                'status' => $notification['status'] === 'read' ? 'read' : 'unread',
                'created_at' => $notification['created_at'] ?? $notification['timestamp'],
                'read_by' => $notification['status'] === 'read' ? array($user_id) : array()
            );
            
            // Customize based on notification type
            switch ($notification['type']) {
                case 'waiter_call':
                    $formatted_notification['title'] = sprintf(__('Guest Call - Table %s', 'orders-jet'), $notification['table_number']);
                    $formatted_notification['message'] = sprintf(__('Table %s: %s', 'orders-jet'), $notification['table_number'], $notification['message']);
                    $formatted_notification['sound_file'] = 'call-waiter.mp3';
                    $formatted_notification['icon'] = '🙋‍♂️';
                    $formatted_notification['color'] = '#ff6b35';
                    break;
                    
                case 'table_order':
                case 'pickup_order':
                    $formatted_notification['title'] = sprintf(__('New Order - Table %s', 'orders-jet'), $notification['table_number']);
                    $formatted_notification['message'] = $notification['message'] ?? sprintf(__('New order received for table %s', 'orders-jet'), $notification['table_number']);
                    $formatted_notification['sound_file'] = 'new-order.mp3';
                    $formatted_notification['icon'] = '🛎️';
                    $formatted_notification['color'] = '#10b981';
                    break;
                    
                default:
                    $formatted_notification['title'] = $notification['message'] ?? __('Notification', 'orders-jet');
                    $formatted_notification['message'] = $notification['message'] ?? '';
                    $formatted_notification['sound_file'] = 'new-order.mp3';
                    $formatted_notification['icon'] = '🔔';
                    $formatted_notification['color'] = '#3b82f6';
                    break;
            }
            
            $formatted_notifications[] = $formatted_notification;
        }
        
        return $formatted_notifications;
    }
    
    /**
     * Get waiter call notifications for a specific waiter
     * 
     * @param int $user_id Waiter user ID
     * @return array Formatted notifications for the main notification system
     */
    private function get_waiter_call_notifications($user_id) {
        $waiter_notifications = get_user_meta($user_id, '_oj_waiter_notifications', true) ?: array();
        $formatted_notifications = array();
        
        foreach ($waiter_notifications as $notification) {
            if ($notification['type'] === 'waiter_call') {
                // Convert waiter call notification to main notification system format
                $formatted_notifications[] = array(
                    'id' => 'wc_' . $notification['id'], // Prefix to avoid conflicts
                    'type' => 'waiter_call',
                    'title' => sprintf(__('Guest Call - Table %s', 'orders-jet'), $notification['table_number']),
                    'message' => sprintf(__('Table %s: %s', 'orders-jet'), $notification['table_number'], $notification['message']),
                    'table_number' => $notification['table_number'],
                    'priority' => $notification['priority'] ?? 'high',
                    'status' => $notification['status'] === 'read' ? 'read' : 'unread',
                    'created_at' => $notification['created_at'] ?? $notification['timestamp'],
                    'read_by' => $notification['status'] === 'read' ? array($user_id) : array(),
                    'sound_file' => 'call-waiter.mp3', // Custom sound for waiter calls
                    'icon' => '🙋‍♂️',
                    'color' => '#ff6b35'
                );
            }
        }
        
        return $formatted_notifications;
    }
    
    /**
     * Check if a table is assigned to any waiter
     * 
     * @param string $table_number Table number to check
     * @return bool True if table is assigned, false otherwise
     */
    private function is_table_assigned($table_number) {
        // Get all users with waiter function
        $waiters = get_users(array(
            'meta_key' => '_oj_function',
            'meta_value' => 'waiter',
            'fields' => 'ID'
        ));
        
        // Check if any waiter has this table assigned
        foreach ($waiters as $waiter_id) {
            $assigned_tables = get_user_meta($waiter_id, WooJet_Meta_Keys::ASSIGNED_TABLES, true);
            if (is_array($assigned_tables) && in_array($table_number, $assigned_tables)) {
                return true; // Table is assigned
            }
        }
        
        return false; // Table is not assigned to any waiter
    }
    
    /**
     * Get notification count for badge
     */
    public function ajax_get_notification_count() {
        check_ajax_referer('oj_notifications_nonce', 'nonce');
        
        try {
            if (!$this->user_can_access_notifications()) {
                wp_send_json_error(array('message' => __('Unauthorized', 'orders-jet')));
                return;
            }
            
            $user_id = get_current_user_id();
            $user_function = oj_get_user_function();
            
            // Get kitchen specialization if user is kitchen staff
            $kitchen_type = null;
            if ($user_function === 'kitchen') {
                $kitchen_type = oj_get_kitchen_specialization();
            }
            
            $all_notifications = get_option('oj_dashboard_notifications', array());
            $user_notifications = $this->filter_notifications_by_user($all_notifications, $user_function, $kitchen_type);
            
            // Count unread for this user
            $unread_count = count(array_filter($user_notifications, function($n) use ($user_id) {
                $read_by = $n['read_by'] ?? array();
                return !in_array($user_id, $read_by);
            }));
            
            wp_send_json_success(array(
                'unread_count' => $unread_count
            ));
            
        } catch (Exception $e) {
            oj_error_log('Get notification count error: ' . $e->getMessage(), 'NOTIFICATIONS');
            wp_send_json_error(array('message' => __('Failed to get count', 'orders-jet')));
        }
    }
    
    /**
     * Mark notification as read
     */
    public function ajax_mark_notification_read() {
        check_ajax_referer('oj_notifications_nonce', 'nonce');
        
        try {
            if (!$this->user_can_access_notifications()) {
                wp_send_json_error(array('message' => __('Unauthorized', 'orders-jet')));
                return;
            }
            
            $notification_id = isset($_POST['notification_id']) ? sanitize_text_field($_POST['notification_id']) : '';
            
            if (empty($notification_id)) {
                wp_send_json_error(array('message' => __('Invalid notification ID', 'orders-jet')));
                return;
            }
            
            $user_id = get_current_user_id();
            
            // Check if this is a waiter-specific notification (prefixed with 'ws_')
            if (strpos($notification_id, 'ws_') === 0) {
                // Handle waiter-specific notification (calls and orders)
                $original_id = substr($notification_id, 3); // Remove 'ws_' prefix
                $waiter_notifications = get_user_meta($user_id, '_oj_waiter_notifications', true) ?: array();
                
                foreach ($waiter_notifications as &$notification) {
                    if ($notification['id'] === $original_id) {
                        $notification['status'] = 'read';
                        $notification['read_at'] = current_time('mysql');
                        break;
                    }
                }
                
                update_user_meta($user_id, '_oj_waiter_notifications', $waiter_notifications);
                
            } else {
                // Handle regular dashboard notification
                $all_notifications = get_option('oj_dashboard_notifications', array());
                
                // Find and mark notification as read by this user
                foreach ($all_notifications as &$notification) {
                    if ($notification['id'] === $notification_id) {
                        if (!isset($notification['read_by'])) {
                            $notification['read_by'] = array();
                        }
                        if (!in_array($user_id, $notification['read_by'])) {
                            $notification['read_by'][] = $user_id;
                        }
                        break;
                    }
                }
                
                update_option('oj_dashboard_notifications', $all_notifications);
            }
            
            wp_send_json_success(array(
                'message' => __('Notification marked as read', 'orders-jet')
            ));
            
        } catch (Exception $e) {
            oj_error_log('Mark notification read error: ' . $e->getMessage(), 'NOTIFICATIONS');
            wp_send_json_error(array('message' => __('Failed to mark as read', 'orders-jet')));
        }
    }
    
    /**
     * Mark all notifications as read for current user
     */
    public function ajax_mark_all_notifications_read() {
        check_ajax_referer('oj_notifications_nonce', 'nonce');
        
        try {
            if (!$this->user_can_access_notifications()) {
                wp_send_json_error(array('message' => __('Unauthorized', 'orders-jet')));
                return;
            }
            
            $user_id = get_current_user_id();
            $user_function = oj_get_user_function();
            
            // Get kitchen specialization if user is kitchen staff
            $kitchen_type = null;
            if ($user_function === 'kitchen') {
                $kitchen_type = oj_get_kitchen_specialization();
            }
            
            $all_notifications = get_option('oj_dashboard_notifications', array());
            
            // Mark all user's notifications as read
            foreach ($all_notifications as &$notification) {
                // Check if this notification is relevant to user
                if ($this->is_notification_for_user($notification, $user_function, $kitchen_type)) {
                    if (!isset($notification['read_by'])) {
                        $notification['read_by'] = array();
                    }
                    if (!in_array($user_id, $notification['read_by'])) {
                        $notification['read_by'][] = $user_id;
                    }
                }
            }
            
            update_option('oj_dashboard_notifications', $all_notifications);
            
            // Also mark waiter-specific notifications as read
            if ($user_function === 'waiter') {
                $waiter_notifications = get_user_meta($user_id, '_oj_waiter_notifications', true) ?: array();
                
                // Mark all waiter notifications as read
                foreach ($waiter_notifications as &$notification) {
                    $notification['status'] = 'read';
                }
                
                update_user_meta($user_id, '_oj_waiter_notifications', $waiter_notifications);
                
            }
            
            wp_send_json_success(array(
                'message' => __('All notifications marked as read', 'orders-jet')
            ));
            
        } catch (Exception $e) {
            oj_error_log('Mark all read error: ' . $e->getMessage(), 'NOTIFICATIONS');
            wp_send_json_error(array('message' => __('Failed to mark all as read', 'orders-jet')));
        }
    }
    
    /**
     * Clear old notifications (older than 7 days)
     */
    public function ajax_clear_notifications() {
        check_ajax_referer('oj_notifications_nonce', 'nonce');
        
        try {
            // Only managers can clear notifications
            if (!current_user_can('manage_options') && !current_user_can('access_oj_manager_dashboard')) {
                wp_send_json_error(array('message' => __('Unauthorized', 'orders-jet')));
                return;
            }
            
            $all_notifications = get_option('oj_dashboard_notifications', array());
            $seven_days_ago = strtotime('-7 days');
            
            // Keep only notifications from last 7 days
            $filtered_notifications = array_filter($all_notifications, function($notification) use ($seven_days_ago) {
                $created_time = strtotime($notification['created_at']);
                return $created_time > $seven_days_ago;
            });
            
            update_option('oj_dashboard_notifications', array_values($filtered_notifications));
            
            wp_send_json_success(array(
                'message' => __('Old notifications cleared', 'orders-jet'),
                'removed_count' => count($all_notifications) - count($filtered_notifications)
            ));
            
        } catch (Exception $e) {
            oj_error_log('Clear notifications error: ' . $e->getMessage(), 'NOTIFICATIONS');
            wp_send_json_error(array('message' => __('Failed to clear notifications', 'orders-jet')));
        }
    }
    
    /**
     * Filter notifications by user role
     * 
     * @param array $notifications All notifications
     * @param string $user_function User function (manager/kitchen/waiter)
     * @param string|null $kitchen_type Kitchen specialization (food/beverages) if applicable
     * @return array Filtered notifications
     */
    private function filter_notifications_by_user($notifications, $user_function, $kitchen_type = null) {
        return array_filter($notifications, function($notification) use ($user_function, $kitchen_type) {
            return $this->is_notification_for_user($notification, $user_function, $kitchen_type);
        });
    }
    
    /**
     * Check if notification is relevant for user
     * 
     * @param array $notification Notification data
     * @param string $user_function User function
     * @param string|null $kitchen_type Kitchen specialization (food/beverages) if applicable
     * @return bool
     */
    private function is_notification_for_user($notification, $user_function, $kitchen_type = null) {
        $type = $notification['type'] ?? '';
        
        switch ($type) {
            case 'new_order':
            case 'table_order':
            case 'pickup_order':
                // Kitchen users need special filtering by kitchen type
                if ($user_function === 'kitchen') {
                    return $this->should_kitchen_see_order($notification, $kitchen_type);
                }
                
                // Waiter gets only notifications for their assigned tables
                if ($user_function === 'waiter') {
                    return $this->should_waiter_see_order($notification);
                }
                
                // Manager gets all new order notifications
                return $user_function === 'manager';
                
            case 'order_ready':
            case 'kitchen_food_ready':
            case 'kitchen_beverage_ready':
                // Waiter gets only for their assigned tables
                if ($user_function === 'waiter') {
                    return $this->should_waiter_see_order($notification);
                }
                // Manager gets all ready notifications
                return $user_function === 'manager';
                
            case 'invoice_request':
                // Waiter gets only for their assigned tables
                if ($user_function === 'waiter') {
                    return $this->should_waiter_see_order($notification);
                }
                // Manager gets all invoice requests
                return $user_function === 'manager';
                
            case 'order_completed':
            case 'table_closed':
                // Only Manager gets completion and table closure notifications
                return $user_function === 'manager';
                
            case 'table_available':
                // Managers and waiters get table availability notifications
                return in_array($user_function, ['manager', 'waiter']);
                
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
    private function should_kitchen_see_order($notification, $kitchen_type) {
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
    private function should_waiter_see_order($notification) {
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
        $is_match = in_array($order_table, $assigned_tables);
        
        return $is_match;
    }
    
    /**
     * Check if user can access notifications
     * 
     * @return bool
     */
    private function user_can_access_notifications() {
        return current_user_can('access_oj_manager_dashboard') ||
               current_user_can('access_oj_kitchen_dashboard') ||
               current_user_can('access_oj_waiter_dashboard') ||
               current_user_can('manage_options');
    }
}

// Initialize handler
new Orders_Jet_Notification_Handler();

