<?php
declare(strict_types=1);
/**
 * Orders Jet - Kitchen Management Handler Class
 * Handles kitchen operations: marking orders ready and confirming payments
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Kitchen_Management_Handler {
    
    private $kitchen_service;
    private $notification_service;
    
    public function __construct($kitchen_service, $notification_service) {
        $this->kitchen_service = $kitchen_service;
        $this->notification_service = $notification_service;
    }
    
    /**
     * Mark an order as ready from kitchen
     * 
     * @param array $post_data The $_POST data from AJAX request
     * @return array Success response data
     * @throws Exception On processing errors
     */
    public function mark_order_ready($post_data) {
        // Check user permissions
        if (!current_user_can('access_oj_kitchen_dashboard') && !current_user_can('manage_options')) {
            throw new Exception(__('You do not have permission to perform this action.', 'orders-jet'));
        }
        
        $order_id = intval($post_data['order_id']);
        $kitchen_type = sanitize_text_field($post_data['kitchen_type'] ?? 'food'); // Which kitchen is marking ready
        
        if (!$order_id) {
            throw new Exception(__('Order ID is required.', 'orders-jet'));
        }
        
        // Get the order
        $order = wc_get_order($order_id);
        
        if (!$order) {
            throw new Exception(__('Order not found.', 'orders-jet'));
        }
        
        // Validate order status
        $this->validate_order_status($order);
        
        // Process kitchen readiness
        return $this->process_kitchen_readiness($order, $kitchen_type);
    }
    
    /**
     * Confirm payment has been received for an order
     * 
     * @param array $post_data The $_POST data from AJAX request
     * @return array Success response data
     * @throws Exception On processing errors
     */
    public function confirm_payment_received($post_data) {
        // Check permissions (Manager, Waiter, or WooCommerce admin)
        if (!current_user_can('access_oj_manager_dashboard') 
            && !current_user_can('access_oj_waiter_dashboard')
            && !current_user_can('manage_woocommerce')) {
            throw new Exception(__('Permission denied', 'orders-jet'));
        }
        
        $order_id = intval($post_data['order_id'] ?? 0);
        
        if (!$order_id) {
            throw new Exception(__('Invalid order ID', 'orders-jet'));
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            throw new Exception(__('Order not found', 'orders-jet'));
        }
        
        // Process payment confirmation
        return $this->process_payment_confirmation($order);
    }
    
    /**
     * Validate that order can be marked as ready
     */
    private function validate_order_status($order) {
        $current_status = $order->get_status();
        if (!in_array($current_status, array('pending', 'processing'))) {
            throw new Exception(sprintf(__('Order cannot be marked ready from status: %s', 'orders-jet'), $current_status));
        }
    }
    
    /**
     * Clear Orders Master transient cache
     * Must be called after any order status change to ensure fresh data
     */
    private function clear_orders_master_cache() {
        global $wpdb;
        
        // Clear all Orders Master transients (data cache and filter counts cache)
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_oj_master_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_oj_master_%'");
        
        // Also clear Orders Master V2 filter counts cache
        delete_transient('oj_master_v2_filter_counts');
        
        oj_debug_log('Orders Master cache cleared after status change', 'CACHE_CLEAR');
    }
    
    /**
     * Process kitchen readiness logic
     */
    private function process_kitchen_readiness($order, $kitchen_type) {
        $order_id = $order->get_id();
        $table_number = $order->get_meta('_oj_table_number');
        $order_type = !empty($table_number) ? 'table' : 'pickup';
        
        // Get or determine kitchen type for this order
        $order_kitchen_type = $order->get_meta('_oj_kitchen_type');
        if (empty($order_kitchen_type)) {
            $order_kitchen_type = $this->kitchen_service->get_order_kitchen_type($order);
            $order->update_meta_data('_oj_kitchen_type', $order_kitchen_type);
        }
        
        // Handle dual kitchen logic
        if ($order_kitchen_type === 'mixed') {
            return $this->handle_mixed_kitchen_readiness($order, $kitchen_type, $table_number, $order_type);
        } else {
            return $this->handle_single_kitchen_readiness($order, $order_kitchen_type, $table_number, $order_type);
        }
    }
    
    /**
     * Handle readiness for mixed kitchen orders (both food and beverage)
     */
    private function handle_mixed_kitchen_readiness($order, $kitchen_type, $table_number, $order_type) {
        $order_id = $order->get_id();
        
        // Mark specific kitchen as ready
        if ($kitchen_type === 'food') {
            $order->update_meta_data('_oj_food_kitchen_ready', 'yes');
            $order->add_order_note(sprintf(
                __('Food items marked as ready by kitchen staff (%s)', 'orders-jet'), 
                wp_get_current_user()->display_name
            ));
        } else {
            $order->update_meta_data('_oj_beverage_kitchen_ready', 'yes');
            $order->add_order_note(sprintf(
                __('Beverage items marked as ready by kitchen staff (%s)', 'orders-jet'), 
                wp_get_current_user()->display_name
            ));
        }
        
        // Check if both kitchens are ready
        $food_ready = $order->get_meta('_oj_food_kitchen_ready') === 'yes';
        $beverage_ready = $order->get_meta('_oj_beverage_kitchen_ready') === 'yes';
        
        if ($food_ready && $beverage_ready) {
            // All kitchens ready - mark as pending (ready for completion)
            $order->set_status('pending');
            $order->add_order_note(__('All kitchen items ready - order ready for completion', 'orders-jet'));
            $button_text = !empty($table_number) ? 'Close Table' : 'Complete';
            $button_class = !empty($table_number) ? 'oj-close-table' : 'oj-complete-order';
            $success_message = sprintf(__('Order #%d fully ready! All kitchens complete.', 'orders-jet'), $order_id);
            $partial_ready = false;
        } else {
            // Partial ready - stay in processing
            $order->set_status('processing');
            $waiting_for = ($food_ready !== 'yes') ? __('Food Kitchen', 'orders-jet') : __('Beverage Kitchen', 'orders-jet');
            $button_text = sprintf(__('Waiting for %s', 'orders-jet'), $waiting_for);
            $button_class = 'oj-waiting-kitchen';
            $success_message = sprintf(__('%s ready! Waiting for %s.', 'orders-jet'), 
                ucfirst($kitchen_type), $waiting_for);
            $partial_ready = true;
        }
        
        return $this->finalize_kitchen_response($order, $order_type, $success_message, $button_text, $button_class, $table_number, $partial_ready, $kitchen_type);
    }
    
    /**
     * Handle readiness for single kitchen orders
     */
    private function handle_single_kitchen_readiness($order, $order_kitchen_type, $table_number, $order_type) {
        $order_id = $order->get_id();
        
        // Mark kitchen as ready
        if ($order_kitchen_type === 'food') {
            $order->update_meta_data('_oj_food_kitchen_ready', 'yes');
        } else {
            $order->update_meta_data('_oj_beverage_kitchen_ready', 'yes');
        }
        
        $order->set_status('pending');
        $order->add_order_note(sprintf(
            __('Order marked as ready by kitchen staff (%s) - %s order', 'orders-jet'), 
            wp_get_current_user()->display_name,
            ucfirst($order_type)
        ));
        
        $button_text = !empty($table_number) ? 'Close Table' : 'Complete';
        $button_class = !empty($table_number) ? 'oj-close-table' : 'oj-complete-order';
        $success_message = !empty($table_number) 
            ? sprintf(__('Table order #%d marked as ready!', 'orders-jet'), $order_id)
            : sprintf(__('Pickup order #%d marked as ready!', 'orders-jet'), $order_id);
        
        return $this->finalize_kitchen_response($order, $order_type, $success_message, $button_text, $button_class, $table_number, false, $order_kitchen_type);
    }
    
    /**
     * Finalize kitchen readiness response
     */
    private function finalize_kitchen_response($order, $order_type, $success_message, $button_text, $button_class, $table_number, $partial_ready, $kitchen_type = null) {
        $order_id = $order->get_id();
        $order_kitchen_type = $order->get_meta('_oj_kitchen_type');
        
        // Save the order
        $order->save();
        
        // CRITICAL: Clear Orders Master cache after status change
        $this->clear_orders_master_cache();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oj_debug_log('Order #' . $order_id . ' (' . $order_type . ') kitchen ready update by user #' . get_current_user_id(), 'KITCHEN_MGMT');
        }
        
        // Send notifications
        if ($order->get_status() === 'pending') {
            // Fully ready - send "order ready" notification
            $this->notification_service->send_ready_notifications($order, $table_number);
        } elseif ($partial_ready && $kitchen_type) {
            // Partial ready - send specific kitchen ready notification
            $this->send_kitchen_partial_ready_notification($order, $kitchen_type, $table_number);
        }
        
        // Get updated kitchen status for response
        $kitchen_status = $this->kitchen_service->get_kitchen_readiness_status($order);
        
        // Get status text and icon for yellow badge update
        $status_info = $this->get_status_badge_info($order, $order_kitchen_type);
        
        // Get updated counts for real-time updates
        $updated_counts = $this->get_updated_kitchen_counts();
        
        return array(
            'message' => $success_message,
            'order_id' => $order_id,
            'table_number' => $table_number,
            'order_type' => $order_type,
            'kitchen_type' => $order_kitchen_type,
            'kitchen_status' => $kitchen_status,
            'new_status' => $order->get_status(),
            'updated_counts' => $updated_counts, // NEW: Real-time count updates
            'card_updates' => array(
                'order_id' => $order_id,
                'new_status' => $order->get_status(),
                'status_badge_html' => $this->kitchen_service->get_kitchen_status_badge($order),
                'button_text' => $button_text,
                'button_class' => $button_class,
                'table_number' => $table_number,
                'partial_ready' => $partial_ready,
                // Add status info for yellow badge update
                'status_text' => $status_info['text'],
                'status_icon' => $status_info['icon'],
                'status_class' => $status_info['class']
            )
        );
    }
    
    /**
     * Get status badge info for yellow badge update
     * OPTIMIZED: Uses mapping instead of nested if/else statements for better performance and maintainability
     */
    private function get_status_badge_info($order, $order_kitchen_type) {
        $order_status = $order->get_status();
        
        // Define status mappings for better performance and maintainability
        $status_mappings = array(
            'pending' => array(
                'text' => __('Ready', 'orders-jet'),
                'class' => 'ready',
                'icon' => '✅'
            ),
            'processing' => $this->get_processing_status_mappings($order, $order_kitchen_type)
        );
        
        // Return mapped status or default fallback
        return $status_mappings[$order_status] ?? $this->get_default_status_info();
    }
    
    /**
     * Get processing status mappings based on kitchen type and readiness
     * 
     * @param WC_Order $order The order object
     * @param string $order_kitchen_type The kitchen type
     * @return array Status info array
     */
    private function get_processing_status_mappings($order, $order_kitchen_type) {
        // Handle mixed kitchen type with dynamic status
        if ($order_kitchen_type === 'mixed') {
            return $this->get_mixed_kitchen_status($order);
        }
        
        // Simple kitchen type mappings
        $kitchen_mappings = array(
            'food' => array(
                'text' => __('Waiting for Food', 'orders-jet'),
                'class' => 'partial',
                'icon' => '🍕⏳'
            ),
            'beverages' => array(
                'text' => __('Waiting for Bev.', 'orders-jet'),
                'class' => 'partial',
                'icon' => '🥤⏳'
            )
        );
        
        return $kitchen_mappings[$order_kitchen_type] ?? $this->get_default_status_info();
    }
    
    /**
     * Get mixed kitchen status based on readiness state
     * 
     * @param WC_Order $order The order object
     * @return array Status info array
     */
    private function get_mixed_kitchen_status($order) {
        $food_ready = $order->get_meta('_oj_food_kitchen_ready') === 'yes';
        $beverage_ready = $order->get_meta('_oj_beverage_kitchen_ready') === 'yes';
        
        // Create readiness key for mapping
        $readiness_key = ($food_ready ? 'food_ready' : 'food_waiting') . '_' . 
                        ($beverage_ready ? 'bev_ready' : 'bev_waiting');
        
        $mixed_mappings = array(
            'food_ready_bev_waiting' => array(
                'text' => __('Waiting for Bev.', 'orders-jet'),
                'class' => 'partial',
                'icon' => '🍕✅ 🥤⏳'
            ),
            'food_waiting_bev_ready' => array(
                'text' => __('Waiting for Food', 'orders-jet'),
                'class' => 'partial',
                'icon' => '🍕⏳ 🥤✅'
            ),
            'food_waiting_bev_waiting' => array(
                'text' => __('Both Kitchens', 'orders-jet'),
                'class' => 'partial',
                'icon' => '🍕⏳ 🥤⏳'
            )
        );
        
        return $mixed_mappings[$readiness_key] ?? $this->get_default_status_info();
    }
    
    /**
     * Get default status info fallback
     * 
     * @return array Default status info
     */
    private function get_default_status_info() {
        return array(
            'text' => __('Kitchen', 'orders-jet'),
            'class' => 'kitchen',
            'icon' => '👨‍🍳'
        );
    }
    
    /**
     * Process payment confirmation
     */
    private function process_payment_confirmation($order) {
        $order_id = $order->get_id();
        
        // Add order note for payment confirmation
        $order->add_order_note(__('Payment confirmed by manager', 'orders-jet'));
        
        // Update order status to completed (if not already)
        if ($order->get_status() !== 'completed') {
            $order->set_status('completed');
            $order->save();
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oj_debug_log('Payment confirmed for order #' . $order_id, 'PAYMENT');
        }
        
        return array(
            'message' => sprintf(__('Payment confirmed for order #%d', 'orders-jet'), $order_id),
            'order_id' => $order_id,
            'status' => 'payment_confirmed'
        );
    }
    
    /**
     * Send notification for partial kitchen readiness
     * 
     * @param WC_Order $order The order
     * @param string $kitchen_type Kitchen that just became ready (food/beverages)
     * @param string $table_number Table number if applicable
     */
    private function send_kitchen_partial_ready_notification($order, $kitchen_type, $table_number) {
        $notification_data = array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'table_number' => $table_number,
            'total' => $order->get_total(),
            'formatted_total' => wc_price($order->get_total()),
            'timestamp' => current_time('mysql'),
            'created_at' => current_time('mysql'),
            'type' => $kitchen_type === 'food' ? 'kitchen_food_ready' : 'kitchen_beverage_ready'
        );
        
        // Store notification for dashboard
        $this->notification_service->store_dashboard_notification($notification_data);
        
        oj_debug_log('Sent partial ready notification for order #' . $order->get_id() . ' - ' . $kitchen_type . ' kitchen', 'NOTIFICATIONS');
    }
    
    /**
     * Get updated kitchen counts for real-time updates
     * Uses same logic as working template for consistency
     */
    private function get_updated_kitchen_counts() {
        // Initialize services (same as template)
        $kitchen_filter_service = new Orders_Jet_Kitchen_Filter_Service();
        $order_method_service = new Orders_Jet_Order_Method_Service();
        
        // Load helper functions
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/helpers/orders-express-helpers.php';
        
        // Get current user info (same as template)
        $user_function = oj_get_user_function();
        $is_kitchen_user = ($user_function === 'kitchen');
        
        // Get appropriate order statuses (same as template)
        $order_statuses = $kitchen_filter_service->get_order_statuses_for_user($is_kitchen_user);
        
        // Get active orders (same query as template)
        $active_orders = wc_get_orders(array(
            'status' => $order_statuses,
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'ASC',
            'return' => 'objects'
        ));
        
        // Initialize counts (same as template)
        $filter_counts = array(
            'active' => 0,
            'processing' => 0,
            'pending' => 0,
            'dinein' => 0,
            'takeaway' => 0,
            'delivery' => 0,
            'food_kitchen' => 0,
            'beverage_kitchen' => 0
        );
        
        // Process orders and count (same logic as template)
        foreach ($active_orders as $order) {
            $order_data = oj_express_prepare_order_data($order, $this->kitchen_service, $order_method_service);
            oj_express_update_filter_counts($filter_counts, $order_data);
        }
        
        return $filter_counts;
    }
}
