<?php
declare(strict_types=1);
/**
 * Orders Jet - Notification Service Class
 * Handles order notifications and staff alerts
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Notification_Service {
    
    /**
     * Send order notification to staff
     * 
     * @param WC_Order $order The order to send notification for
     * @return bool Success status
     */
    public function send_order_notification($order) {
        if (!$order) {
            oj_error_log('No order provided for notification', 'NOTIFICATIONS');
            return false;
        }
        
        // Skip notifications for consolidated orders (created during table closure)
        if ($order->get_meta('_oj_consolidated_order') === 'yes') {
            oj_debug_log('Skipping notification for consolidated order #' . $order->get_id(), 'NOTIFICATIONS');
            return true; // Return true as this is expected behavior, not an error
        }
        
        $table_number = $order->get_meta('_oj_table_number');
        $order_id = $order->get_id();
        $order_total = $order->get_total();
        
        // Determine notification type
        $notification_type = !empty($table_number) ? 'table_order' : 'pickup_order';
        
        // Prepare notification data
        $notification_data = array(
            'order_id' => $order_id,
            'order_number' => $order->get_order_number(),
            'table_number' => $table_number,
            'total' => $order_total,
            'formatted_total' => wc_price($order_total),
            'items_count' => count($order->get_items()),
            'timestamp' => current_time('mysql'),
            'type' => $notification_type,
            // Add required fields for waiter-specific notifications
            'message' => sprintf(__('New order #%s for table %s - %s', 'orders-jet'), $order->get_order_number(), $table_number, wc_price($order_total)),
            'priority' => 'high',
            'status' => 'pending',
            'title' => sprintf(__('New Order - Table %s', 'orders-jet'), $table_number),
            'description' => sprintf(__('Order #%s received for table %s', 'orders-jet'), $order->get_order_number(), $table_number)
        );
        
        // Get order items for notification
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'notes' => $item->get_meta('_oj_item_notes')
            );
        }
        $notification_data['items'] = $items;
        
        // Send notification via multiple channels
        $success = true;
        
        // 1. WordPress admin notification
        $success &= $this->send_admin_notification($notification_data);
        
        // 2. Send to waiters based on table assignment (NEW LOGIC)
        if (!empty($table_number)) {
            $assigned_waiter_id = $this->get_assigned_waiter_for_table($table_number);
            
            if ($assigned_waiter_id) {
                // Table is assigned - send to assigned waiter only
                $waiter_result = $this->send_waiter_specific_notification($notification_data, $assigned_waiter_id);
                $success &= $waiter_result;
                oj_debug_log("Sent new order notification to assigned waiter ID: {$assigned_waiter_id} for table {$table_number}", 'ORDER_NOTIFICATIONS');
            } else {
                // Table is unassigned - send to ALL waiters so one can claim it
                $all_waiters_result = $this->send_order_to_all_waiters($notification_data);
                $success &= $all_waiters_result;
                oj_debug_log("Sent new order notification to all waiters for unassigned table {$table_number}", 'ORDER_NOTIFICATIONS');
            }
        }
        
        // 3. Email notification (if enabled)
        if (get_option('oj_email_notifications', 'yes') === 'yes') {
            $success &= $this->send_email_notification($notification_data);
        }
        
        // 4. Browser notification (stored for dashboard polling)
        $success &= $this->store_dashboard_notification($notification_data);
        
        // Log notification attempt
        if ($success) {
            oj_debug_log('Successfully sent notifications for order #' . $order_id, 'NOTIFICATIONS');
        } else {
            oj_error_log('Failed to send some notifications for order #' . $order_id, 'NOTIFICATIONS');
        }
        
        return $success;
    }
    
    /**
     * Send ready notifications when order is marked ready
     * 
     * @param WC_Order $order The order that's ready
     * @param string $table_number Table number for the order
     * @return bool Success status
     */
    public function send_ready_notifications($order, $table_number) {
        if (!$order) {
            oj_error_log('No order provided for ready notification', 'NOTIFICATIONS');
            return false;
        }
        
        $notification_data = array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'table_number' => $table_number,
            'total' => $order->get_total(),
            'formatted_total' => wc_price($order->get_total()),
            'timestamp' => current_time('mysql'),
            'type' => 'order_ready'
        );
        
        // Send ready notification
        $success = true;
        
        // Admin notification
        $success &= $this->send_admin_notification($notification_data);
        
        // Dashboard notification for waiters/managers
        $success &= $this->store_dashboard_notification($notification_data);
        
        // Optional: SMS or push notification to customer (if implemented)
        if (get_option('oj_customer_ready_notifications', 'no') === 'yes') {
            $success &= $this->send_customer_ready_notification($notification_data);
        }
        
        oj_debug_log('Order #' . $order->get_id() . ' ready notifications sent', 'NOTIFICATIONS');
        return $success;
    }
    
    /**
     * Send waiter call notification to staff
     * 
     * @param array $notification_data Waiter call data
     * @return bool Success status
     */
    public function send_waiter_call_notification($notification_data) {
        try {
            $table_number = $notification_data['table_number'];
            $table_id = $notification_data['table_id'];
            $message = $notification_data['message'];
            
            oj_debug_log("Sending waiter call notification for table {$table_number}", 'WAITER_CALL');
            
            // Prepare notification data
            $formatted_data = array(
                'type' => 'waiter_call',
                'table_number' => $table_number,
                'table_id' => $table_id,
                'message' => sprintf(__('Table %s: %s', 'orders-jet'), $table_number, $message),
                'timestamp' => $notification_data['timestamp'],
                'priority' => 'high',
                'status' => 'pending',
                'title' => sprintf(__('Waiter Call - Table %s', 'orders-jet'), $table_number),
                'description' => sprintf(__('Table %s: %s', 'orders-jet'), $table_number, $message)
            );
            
            $success = true;
            
            // Check if table is assigned to a waiter
            $assigned_waiter_id = $this->get_assigned_waiter_for_table($table_number);
            
            if ($assigned_waiter_id) {
                // Table is assigned - send to assigned waiter only
                $waiter_notification_result = $this->send_waiter_specific_notification($formatted_data, $assigned_waiter_id);
                $success &= $waiter_notification_result;
                oj_debug_log("Sent waiter call to assigned waiter ID: {$assigned_waiter_id}", 'WAITER_CALL');
            } else {
                // Table is unassigned - send to ALL waiters so one can claim it
                $all_waiters_result = $this->send_waiter_call_to_all_waiters($formatted_data);
                $success &= $all_waiters_result;
                oj_debug_log("Sent waiter call to all waiters for unassigned table {$table_number}", 'WAITER_CALL');
            }
            
            // Always send to managers as backup
            $success &= $this->send_manager_notification($formatted_data);
            
            // Store dashboard notification for all staff
            $success &= $this->store_dashboard_notification($formatted_data);
            
            // Optional: Send push notification or SMS (if implemented)
            if (get_option('oj_waiter_call_push_notifications', 'no') === 'yes') {
                $success &= $this->send_push_notification($formatted_data);
            }
            
            oj_debug_log("Waiter call notification sent for table {$table_number}, success: " . ($success ? 'true' : 'false'), 'WAITER_CALL');
            return $success;
            
        } catch (Exception $e) {
            oj_error_log('Waiter call notification failed: ' . $e->getMessage(), 'WAITER_CALL_ERROR');
            return false;
        }
    }
    
    /**
     * Get assigned waiter for a table
     * 
     * @param string $table_number
     * @return int|null Waiter user ID or null if not assigned
     */
    private function get_assigned_waiter_for_table($table_number) {
        // Get all users with waiter function
        $waiters = get_users(array(
            'meta_key' => '_oj_function',
            'meta_value' => 'waiter',
            'fields' => 'ID'
        ));
        
        foreach ($waiters as $waiter_id) {
            $assigned_tables = get_user_meta($waiter_id, WooJet_Meta_Keys::ASSIGNED_TABLES, true);
            
            if (is_array($assigned_tables) && in_array($table_number, $assigned_tables)) {
                return $waiter_id;
            }
        }
        
        return null;
    }
    
    /**
     * Send waiter call notification to all waiters (for unassigned tables)
     * 
     * @param array $data Notification data
     * @return bool Success status
     */
    private function send_waiter_call_to_all_waiters($data) {
        try {
            // Get all waiters
            $waiters = get_users(array(
                'meta_key' => '_oj_function',
                'meta_value' => 'waiter',
                'fields' => 'ID'
            ));
            
            $success = true;
            
            foreach ($waiters as $waiter_id) {
                $waiter_notification_result = $this->send_waiter_specific_notification($data, $waiter_id);
                $success &= $waiter_notification_result;
            }
            
            oj_debug_log("Sent waiter call to " . count($waiters) . " waiters for unassigned table", 'WAITER_CALL');
            return $success;
            
        } catch (Exception $e) {
            oj_error_log('Send waiter call to all waiters failed: ' . $e->getMessage(), 'WAITER_CALL_ERROR');
            return false;
        }
    }
    
    /**
     * Send new order notification to all waiters (for unassigned tables)
     * 
     * @param array $data Notification data
     * @return bool Success status
     */
    private function send_order_to_all_waiters($data) {
        try {
            // Get all waiters
            $waiters = get_users(array(
                'meta_key' => '_oj_function',
                'meta_value' => 'waiter',
                'fields' => 'ID'
            ));
            
            oj_debug_log("Found " . count($waiters) . " waiters to notify for new order on unassigned table", 'ORDER_NOTIFICATIONS');
            
            $success = true;
            
            foreach ($waiters as $waiter_id) {
                $waiter_notification_result = $this->send_waiter_specific_notification($data, $waiter_id);
                $success &= $waiter_notification_result;
            }
            
            oj_debug_log("Sent new order notification to " . count($waiters) . " waiters for unassigned table", 'ORDER_NOTIFICATIONS');
            return $success;
            
        } catch (Exception $e) {
            oj_error_log('Send order to all waiters failed: ' . $e->getMessage(), 'ORDER_NOTIFICATIONS_ERROR');
            return false;
        }
    }
    
    /**
     * Send order notification to specific waiter
     * 
     * @param array $data Order notification data
     * @param int $waiter_id Waiter user ID
     * @return bool Success status
     */
    private function send_order_notification_to_waiter($data, $waiter_id) {
        try {
            // For assigned table orders, we store the notification in the global system
            // The existing oj_should_waiter_see_order() function will filter it correctly
            
            // Format the data for the main notification system
            $formatted_notification = array(
                'type' => 'new_order',
                'order_id' => $data['order_id'],
                'order_number' => $data['order_number'],
                'table_number' => $data['table_number'],
                'total' => $data['formatted_total'],
                'items_count' => $data['items_count'],
                'timestamp' => $data['timestamp'],
                'title' => sprintf(__('New Order - Table %s', 'orders-jet'), $data['table_number']),
                'message' => sprintf(__('Order #%s - %s (%d items)', 'orders-jet'), 
                    $data['order_number'], 
                    $data['formatted_total'], 
                    $data['items_count']
                )
            );
            
            // Store in the main notification system (this triggers bell icon, sounds, etc.)
            $notifications = get_option('oj_dashboard_notifications', array());
            $notifications[] = array_merge($formatted_notification, array(
                'id' => uniqid('order_'),
                'read' => false,
                'created_at' => current_time('mysql'),
                'timestamp_unix' => current_time('timestamp')
            ));
            
            // Keep only last 50 notifications
            if (count($notifications) > 50) {
                $notifications = array_slice($notifications, -50);
            }
            
            update_option('oj_dashboard_notifications', $notifications);
            
            oj_debug_log("Sent order notification for table {$data['table_number']}", 'ORDER_NOTIFICATIONS');
            return true;
            
        } catch (Exception $e) {
            oj_error_log('Send order notification to waiter failed: ' . $e->getMessage(), 'ORDER_NOTIFICATIONS_ERROR');
            return false;
        }
    }
    
    /**
     * Send notification to specific waiter
     * 
     * @param array $data Notification data
     * @param int $waiter_id Waiter user ID
     * @return bool Success status
     */
    private function send_waiter_specific_notification($data, $waiter_id) {
        try {
            // Store waiter-specific notification
            $waiter_notifications = get_user_meta($waiter_id, '_oj_waiter_notifications', true) ?: array();
            
            $notification = array(
                'id' => uniqid('wc_'),
                'type' => $data['type'],
                'table_number' => $data['table_number'],
                'message' => $data['message'],
                'timestamp' => $data['timestamp'],
                'priority' => $data['priority'],
                'status' => 'unread',
                'created_at' => current_time('mysql')
            );
            
            $waiter_notifications[] = $notification;
            
            // Keep only last 50 notifications per waiter
            if (count($waiter_notifications) > 50) {
                $waiter_notifications = array_slice($waiter_notifications, -50);
            }
            
            update_user_meta($waiter_id, '_oj_waiter_notifications', $waiter_notifications);
            
            oj_debug_log("Waiter-specific notification stored for user {$waiter_id}", 'WAITER_CALL');
            return true;
            
        } catch (Exception $e) {
            oj_error_log('Waiter-specific notification failed: ' . $e->getMessage(), 'WAITER_CALL_ERROR');
            return false;
        }
    }
    
    /**
     * Send notification to managers
     * 
     * @param array $data Notification data
     * @return bool Success status
     */
    private function send_manager_notification($data) {
        try {
            // Get all managers
            $managers = get_users(array(
                'capability' => 'access_oj_manager_dashboard',
                'fields' => 'ID'
            ));
            
            foreach ($managers as $manager_id) {
                $manager_notifications = get_user_meta($manager_id, '_oj_manager_notifications', true) ?: array();
                
                $notification = array(
                    'id' => uniqid('mc_'),
                    'type' => $data['type'],
                    'table_number' => $data['table_number'],
                    'message' => $data['message'],
                    'timestamp' => $data['timestamp'],
                    'priority' => $data['priority'],
                    'status' => 'unread',
                    'created_at' => current_time('mysql')
                );
                
                $manager_notifications[] = $notification;
                
                // Keep only last 100 notifications per manager
                if (count($manager_notifications) > 100) {
                    $manager_notifications = array_slice($manager_notifications, -100);
                }
                
                update_user_meta($manager_id, '_oj_manager_notifications', $manager_notifications);
            }
            
            oj_debug_log("Manager notifications sent to " . count($managers) . " managers", 'WAITER_CALL');
            return true;
            
        } catch (Exception $e) {
            oj_error_log('Manager notification failed: ' . $e->getMessage(), 'WAITER_CALL_ERROR');
            return false;
        }
    }
    
    /**
     * Send WordPress admin notification
     * 
     * @param array $data Notification data
     * @return bool Success status
     */
    private function send_admin_notification($data) {
        try {
            // Create admin notice that will be displayed on next page load
            $message = $this->format_admin_message($data);
            
            // Store as transient for admin notices
            $notices = get_transient('oj_admin_notifications') ?: array();
            $notices[] = array(
                'message' => $message,
                'type' => 'info',
                'timestamp' => time()
            );
            
            // Keep only last 10 notifications
            if (count($notices) > 10) {
                $notices = array_slice($notices, -10);
            }
            
            set_transient('oj_admin_notifications', $notices, HOUR_IN_SECONDS);
            
            return true;
        } catch (Exception $e) {
            oj_error_log('Admin notification failed: ' . $e->getMessage(), 'NOTIFICATIONS');
            return false;
        }
    }
    
    /**
     * Send email notification to staff
     * 
     * @param array $data Notification data
     * @return bool Success status
     */
    private function send_email_notification($data) {
        try {
            $to = get_option('oj_notification_email', get_option('admin_email'));
            $subject = $this->get_email_subject($data);
            $message = $this->format_email_message($data);
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            return wp_mail($to, $subject, $message, $headers);
        } catch (Exception $e) {
            oj_error_log('Email notification failed: ' . $e->getMessage(), 'NOTIFICATIONS');
            return false;
        }
    }
    
    /**
     * Store notification for dashboard polling and publish to Pusher
     * 
     * @param array $data Notification data
     * @return bool Success status
     */
    public function store_dashboard_notification($data) {
        try {
            // Prepare notification with all required fields
            $notification = array_merge($data, array(
                'id' => uniqid(),
                'read' => false,
                'read_by' => array(), // Track which users have read this
                'created_at' => current_time('mysql'),
                'timestamp_unix' => current_time('timestamp') // Add Unix timestamp for easier JS parsing
            ));
            
            // Store in database for real-time dashboard updates (and fallback)
            $notifications = get_option('oj_dashboard_notifications', array());
            $notifications[] = $notification;
            
            // Keep only last 50 notifications
            if (count($notifications) > 50) {
                $notifications = array_slice($notifications, -50);
            }
            
            $db_success = update_option('oj_dashboard_notifications', $notifications);
            
            // Publish to Pusher for real-time delivery
            $realtime_service = Orders_Jet_Realtime_Service::instance();
            if ($realtime_service->is_enabled()) {
                // Add time_ago for frontend display
                if (!isset($notification['time_ago'])) {
                    $notification['time_ago'] = oj_get_time_ago($notification['created_at']);
                }
                
                $pusher_success = $realtime_service->publish_notification($notification);
                
                // Log Pusher result (but don't fail if it doesn't work - DB is primary)
                if (!$pusher_success) {
                    oj_debug_log('Pusher publish failed for notification, but stored in DB', 'NOTIFICATIONS');
                }
            }
            
            return $db_success;
        } catch (Exception $e) {
            oj_error_log('Dashboard notification storage failed: ' . $e->getMessage(), 'NOTIFICATIONS');
            return false;
        }
    }
    
    /**
     * Send customer ready notification (placeholder for future implementation)
     * 
     * @param array $data Notification data
     * @return bool Success status
     */
    private function send_customer_ready_notification($data) {
        // Placeholder for SMS/push notification implementation
        oj_debug_log('Customer ready notification (not implemented): Order #' . $data['order_id'], 'NOTIFICATIONS');
        return true;
    }
    
    /**
     * Format admin message
     * 
     * @param array $data Notification data
     * @return string Formatted message
     */
    private function format_admin_message($data) {
        switch ($data['type']) {
            case 'table_order':
                return sprintf(
                    __('New table order #%s for Table %s - %s (%d items)', 'orders-jet'),
                    $data['order_number'],
                    $data['table_number'],
                    $data['formatted_total'],
                    $data['items_count']
                );
                
            case 'pickup_order':
                return sprintf(
                    __('New pickup order #%s - %s (%d items)', 'orders-jet'),
                    $data['order_number'],
                    $data['formatted_total'],
                    $data['items_count']
                );
                
            case 'order_ready':
                return sprintf(
                    __('Order #%s is ready for Table %s - %s', 'orders-jet'),
                    $data['order_number'],
                    $data['table_number'],
                    $data['formatted_total']
                );
                
            default:
                return sprintf(
                    __('Order notification #%s', 'orders-jet'),
                    $data['order_number']
                );
        }
    }
    
    /**
     * Get email subject
     * 
     * @param array $data Notification data
     * @return string Email subject
     */
    private function get_email_subject($data) {
        $site_name = get_bloginfo('name');
        
        switch ($data['type']) {
            case 'table_order':
                return sprintf('[%s] New Table Order #%s', $site_name, $data['order_number']);
            case 'pickup_order':
                return sprintf('[%s] New Pickup Order #%s', $site_name, $data['order_number']);
            case 'order_ready':
                return sprintf('[%s] Order Ready #%s', $site_name, $data['order_number']);
            default:
                return sprintf('[%s] Order Notification #%s', $site_name, $data['order_number']);
        }
    }
    
    /**
     * Format email message
     * 
     * @param array $data Notification data
     * @return string Formatted HTML email message
     */
    private function format_email_message($data) {
        $html = '<html><body>';
        $html .= '<h2>' . $this->format_admin_message($data) . '</h2>';
        
        if (!empty($data['items'])) {
            $html .= '<h3>' . __('Order Items:', 'orders-jet') . '</h3>';
            $html .= '<ul>';
            foreach ($data['items'] as $item) {
                $html .= '<li>';
                $html .= $item['quantity'] . 'x ' . esc_html($item['name']);
                if (!empty($item['notes'])) {
                    $html .= ' <em>(' . esc_html($item['notes']) . ')</em>';
                }
                $html .= '</li>';
            }
            $html .= '</ul>';
        }
        
        $html .= '<p><strong>' . __('Total:', 'orders-jet') . '</strong> ' . $data['formatted_total'] . '</p>';
        $html .= '<p><strong>' . __('Time:', 'orders-jet') . '</strong> ' . $data['timestamp'] . '</p>';
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    /**
     * Get unread dashboard notifications
     * 
     * @return array Unread notifications
     */
    public function get_unread_notifications() {
        $notifications = get_option('oj_dashboard_notifications', array());
        return array_filter($notifications, function($notification) {
            return !$notification['read'];
        });
    }
    
    /**
     * Mark notification as read
     * 
     * @param string $notification_id Notification ID
     * @return bool Success status
     */
    public function mark_notification_read($notification_id) {
        $notifications = get_option('oj_dashboard_notifications', array());
        
        foreach ($notifications as &$notification) {
            if ($notification['id'] === $notification_id) {
                $notification['read'] = true;
                break;
            }
        }
        
        return update_option('oj_dashboard_notifications', $notifications);
    }
    
    /**
     * Clear old notifications
     * 
     * @param int $days_old Days old to clear (default 7)
     * @return bool Success status
     */
    public function clear_old_notifications($days_old = 7) {
        $notifications = get_option('oj_dashboard_notifications', array());
        $cutoff_time = strtotime('-' . $days_old . ' days');
        
        $notifications = array_filter($notifications, function($notification) use ($cutoff_time) {
            $notification_time = strtotime($notification['created_at']);
            return $notification_time > $cutoff_time;
        });
        
        return update_option('oj_dashboard_notifications', array_values($notifications));
    }
    
    /**
     * Create test notifications (for development/testing)
     * 
     * @return bool Success status
     */
    public function create_test_notifications() {
        $test_notifications = array(
            array(
                'id' => 'test_' . uniqid(),
                'type' => 'new_order',
                'order_id' => 123,
                'order_number' => '123',
                'table_number' => '5',
                'total' => 45.50,
                'formatted_total' => '$45.50',
                'items_count' => 3,
                'timestamp' => current_time('mysql'),
                'created_at' => current_time('mysql'),
                'read_by' => array(),
                'message' => 'New order #123 for Table 5'
            ),
            array(
                'id' => 'test_' . uniqid(),
                'type' => 'order_ready',
                'order_id' => 122,
                'order_number' => '122',
                'table_number' => '3',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                'read_by' => array(),
                'message' => 'Order #122 is ready for Table 3'
            ),
            array(
                'id' => 'test_' . uniqid(),
                'type' => 'invoice_request',
                'order_id' => 121,
                'order_number' => '121',
                'table_number' => '7',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
                'created_at' => date('Y-m-d H:i:s', strtotime('-10 minutes')),
                'read_by' => array(),
                'message' => 'Invoice requested for Table 7'
            )
        );
        
        $existing_notifications = get_option('oj_dashboard_notifications', array());
        $all_notifications = array_merge($test_notifications, $existing_notifications);
        
        // Keep only last 50
        if (count($all_notifications) > 50) {
            $all_notifications = array_slice($all_notifications, 0, 50);
        }
        
        return update_option('oj_dashboard_notifications', $all_notifications);
    }
}
