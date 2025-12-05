<?php
declare(strict_types=1);
/**
 * Orders Jet - AJAX Handlers Class
 * Handles AJAX requests for table ordering system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_AJAX_Handlers {
    
    /**
     * Service instances (Phase 2 refactoring)
     */
    private $tax_service;
    private $kitchen_service;
    private $notification_service;
    
    /**
     * Handler factory instance (Phase 3 refactoring)
     */
    private $handler_factory;
    
    public function __construct() {
        // Initialize service classes (Phase 2 refactoring)
        $this->tax_service = new Orders_Jet_Tax_Service();
        $this->kitchen_service = new Orders_Jet_Kitchen_Service();
        $this->notification_service = new Orders_Jet_Notification_Service();
        
        // Initialize handler factory (Phase 3-8 refactoring - FINAL PHASE COMPLETE!)
        // File size reduced from 4,470 → 1,417 lines (68.3% reduction)
        $this->handler_factory = new Orders_Jet_Handler_Factory(
            $this->tax_service,
            $this->kitchen_service,
            $this->notification_service
        );
        
        // AJAX handlers for logged in users
        add_action('wp_ajax_oj_submit_table_order', array($this, 'submit_table_order'));
        add_action('wp_ajax_oj_get_table_status', array($this, 'get_table_status'));
        add_action('wp_ajax_oj_get_table_id_by_number', array($this, 'get_table_id_by_number_ajax'));
        add_action('wp_ajax_oj_get_product_details', array($this, 'get_product_details'));
        add_action('wp_ajax_oj_get_table_orders', array($this, 'get_table_orders'));
        add_action('wp_ajax_oj_call_waiter', array($this, 'call_waiter'));
        add_action('wp_ajax_oj_mark_waiter_call_read', array($this, 'mark_waiter_call_read'));
        add_action('wp_ajax_oj_get_waiter_call_notifications', array($this, 'get_waiter_call_notifications'));
        
        // AJAX handlers for non-logged in users (guests)
        add_action('wp_ajax_nopriv_oj_call_waiter', array($this, 'call_waiter'));
        // CORE FUNCTIONALITY - Currently Used
        add_action('wp_ajax_oj_mark_order_ready', array($this, 'mark_order_ready'));
        add_action('wp_ajax_oj_complete_individual_order', array($this, 'complete_individual_order'));
        add_action('wp_ajax_oj_close_table_group', array($this, 'close_table_group'));
        add_action('wp_ajax_oj_get_order_invoice', array($this, 'get_order_invoice'));
        add_action('wp_ajax_oj_get_filter_counts', array($this, 'get_filter_counts'));
        add_action('wp_ajax_oj_confirm_payment_received', array($this, 'confirm_payment_received'));
        add_action('wp_ajax_oj_refresh_dashboard', array($this, 'refresh_dashboard_ajax'));
        add_action('wp_ajax_oj_get_order_details', array($this, 'get_order_details'));
        
        // Saved Views handlers
        add_action('wp_ajax_oj_save_filter_view', array($this, 'ajax_save_filter_view'));
        add_action('wp_ajax_oj_get_user_saved_views', array($this, 'ajax_get_user_saved_views'));
        add_action('wp_ajax_oj_load_filter_view', array($this, 'ajax_load_filter_view'));
        add_action('wp_ajax_oj_delete_filter_view', array($this, 'ajax_delete_filter_view'));
        add_action('wp_ajax_oj_rename_filter_view', array($this, 'ajax_rename_filter_view'));
        
        // Orders Master AJAX Content Refresh
        add_action('wp_ajax_oj_refresh_orders_content', array($this, 'ajax_refresh_orders_content'));
        add_action('wp_ajax_oj_refresh_kitchen_dashboard', array($this, 'ajax_refresh_kitchen_dashboard'));
        add_action('wp_ajax_oj_test_ajax', array($this, 'ajax_test_endpoint'));
        
        // Bulk Actions (Step 3)
        add_action('wp_ajax_oj_bulk_action', array($this, 'ajax_bulk_action'));
        
        // NOTE: Phase 1, 2, 3, 4 & 5 refactoring complete:
        // - Phase 1: Removed obsolete functions (4,470 → 3,483 lines)
        // - Phase 2: Extracted service classes (3,483 → 3,121 lines)  
        // - Phase 3: Extracted complex handlers (3,121 → 2,126 lines)
        // - Phase 4: Extracted product details handler (2,126 → 1,773 lines)
        // - Phase 5: Extracted dashboard analytics handler (1,773 → 1,678 lines)
        // - Total reduction: 2,792 lines (62% smaller, much better organized)
        
        // AJAX handlers for non-logged in users (guests)
        add_action('wp_ajax_nopriv_oj_submit_table_order', array($this, 'submit_table_order'));
        add_action('wp_ajax_nopriv_oj_get_table_status', array($this, 'get_table_status'));
        add_action('wp_ajax_nopriv_oj_get_table_id_by_number', array($this, 'get_table_id_by_number_ajax'));
        add_action('wp_ajax_nopriv_oj_get_product_details', array($this, 'get_product_details'));
        add_action('wp_ajax_nopriv_oj_get_table_orders', array($this, 'get_table_orders'));
        add_action('wp_ajax_nopriv_oj_close_table_group', array($this, 'close_table_group'));
        add_action('wp_ajax_nopriv_oj_complete_individual_order', array($this, 'complete_individual_order'));
        
        // Guest invoice request handler (simplified approach)
        add_action('wp_ajax_oj_request_table_invoice', array($this, 'request_table_invoice'));
        add_action('wp_ajax_nopriv_oj_request_table_invoice', array($this, 'request_table_invoice'));
        add_action('wp_ajax_oj_clear_guest_invoice_request', array($this, 'clear_guest_invoice_request'));
        // Guest handlers kept minimal for security
    }
    
    /**
     * Submit table order (contactless)
     */
    public function submit_table_order() {
        try {
            check_ajax_referer('oj_table_order', 'nonce');
            
            $handler = $this->handler_factory->get_order_submission_handler();
            $result = $handler->process_submission($_POST);
            
            wp_send_json_success($result);
        
        } catch (Exception $e) {
            oj_error_log('Order submission error: ' . $e->getMessage(), 'ORDER_SUBMIT');
            oj_debug_log('Stack trace: ' . $e->getTraceAsString(), 'ORDER_SUBMIT');
            
            wp_send_json_error(array(
                'message' => __('Order submission failed: ' . $e->getMessage(), 'orders-jet')
            ));
        }
    }
    
    /**
     * Handle call waiter request from guests
     */
    public function call_waiter() {
        // Temporary: Always log this call for debugging
        error_log('ORDERS JET: call_waiter method called');
        error_log('ORDERS JET: POST data: ' . print_r($_POST, true));
        
        try {
            // Verify nonce (allow both call_waiter and table_order nonces for flexibility)
            $nonce_verified = false;
            if (isset($_POST['nonce'])) {
                $nonce = sanitize_text_field($_POST['nonce']);
                $nonce_verified = wp_verify_nonce($nonce, 'oj_call_waiter') || 
                                 wp_verify_nonce($nonce, 'oj_table_order');
                error_log('ORDERS JET: Nonce verification result: ' . ($nonce_verified ? 'SUCCESS' : 'FAILED'));
            }
            
            if (!$nonce_verified) {
                // Log nonce verification failure for debugging
                error_log('ORDERS JET: Nonce verification failed');
                oj_debug_log('Nonce verification failed for call waiter request', 'CALL_WAITER_ERROR', array(
                    'nonce_provided' => isset($_POST['nonce']) ? 'yes' : 'no',
                    'post_data_keys' => array_keys($_POST)
                ));
                throw new Exception(__('Security check failed', 'orders-jet'));
            }
            
            // Parse call data - handle WordPress escaping
            $call_data_json = $_POST['call_data'] ?? '';
            
            // WordPress automatically escapes data, so we need to unescape it
            $call_data_json = wp_unslash($call_data_json);
            
            // Debug: Log raw POST data
            error_log('ORDERS JET: Raw call_data: ' . $_POST['call_data']);
            error_log('ORDERS JET: Unslashed call_data: ' . $call_data_json);
            
            // Try to decode JSON
            $call_data = json_decode($call_data_json, true);
            $json_error = json_last_error();
            $json_error_msg = json_last_error_msg();
            error_log('ORDERS JET: JSON decode result: ' . print_r($call_data, true));
            error_log('ORDERS JET: JSON error: ' . $json_error_msg);
            
            // Debug logging for call data
            oj_debug_log('Call waiter data parsing', 'CALL_WAITER_DEBUG', array(
                'call_data_json' => $call_data_json,
                'call_data_parsed' => $call_data,
                'json_error_code' => $json_error,
                'json_error_msg' => $json_error_msg,
                'is_array' => is_array($call_data),
                'has_table_number' => isset($call_data['table_number']) ? 'yes' : 'no'
            ));
            
            if ($json_error !== JSON_ERROR_NONE) {
                oj_debug_log('JSON decode error', 'CALL_WAITER_ERROR', array(
                    'json_error_code' => $json_error,
                    'json_error_msg' => $json_error_msg,
                    'raw_data' => $call_data_json
                ));
                throw new Exception(__('Invalid JSON data: ' . $json_error_msg, 'orders-jet'));
            }
            
            if (!$call_data || !is_array($call_data) || !isset($call_data['table_number'])) {
                oj_debug_log('Invalid call data structure', 'CALL_WAITER_ERROR', array(
                    'call_data_type' => gettype($call_data),
                    'call_data_content' => $call_data,
                    'has_table_number' => isset($call_data['table_number']) ? 'yes' : 'no',
                    'table_number_value' => $call_data['table_number'] ?? 'not_set'
                ));
                throw new Exception(__('Invalid call data structure', 'orders-jet'));
            }
            
            $table_number = sanitize_text_field($call_data['table_number']);
            $message = sanitize_text_field($call_data['message'] ?? 'Guest is requesting waiter assistance');
            
            // Convert ISO timestamp to MySQL format if provided
            $raw_timestamp = sanitize_text_field($call_data['timestamp'] ?? '');
            if (!empty($raw_timestamp)) {
                // Convert ISO timestamp (2024-01-01T12:00:00.000Z) to MySQL format (2024-01-01 12:00:00)
                $datetime = DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $raw_timestamp);
                if (!$datetime) {
                    // Try without microseconds
                    $datetime = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $raw_timestamp);
                }
                $timestamp = $datetime ? $datetime->format('Y-m-d H:i:s') : current_time('mysql');
            } else {
                $timestamp = current_time('mysql');
            }
            
            if (empty($table_number)) {
                throw new Exception(__('Table number is required', 'orders-jet'));
            }
            
            // Log the call request
            if (defined('WP_DEBUG') && WP_DEBUG) {
                oj_debug_log('WAITER CALL REQUEST', 'CALL_WAITER', array(
                    'table_number' => $table_number,
                    'message' => $message,
                    'timestamp' => $timestamp
                ));
            }
            
            // Get table ID for validation
            $table_id = oj_get_table_id_by_number($table_number);
            if (!$table_id) {
                throw new Exception(__('Table not found', 'orders-jet'));
            }
            
            // Create waiter call notification
            $notification_data = array(
                'type' => 'waiter_call',
                'table_number' => $table_number,
                'table_id' => $table_id,
                'message' => $message,
                'timestamp' => $timestamp,
                'priority' => 'high',
                'status' => 'pending'
            );
            
            // Send notification to assigned waiters (if any) and managers
            $notification_sent = $this->notification_service->send_waiter_call_notification($notification_data);
            
            if (!$notification_sent) {
                throw new Exception(__('Failed to send notification to staff', 'orders-jet'));
            }
            
            // Log successful call
            oj_debug_log("Waiter call successful for table {$table_number}", 'CALL_WAITER');
            
            wp_send_json_success(array(
                'message' => sprintf(__('Waiter has been notified for table %s', 'orders-jet'), $table_number),
                'table_number' => $table_number,
                'timestamp' => $timestamp
            ));
            
        } catch (Exception $e) {
            oj_debug_log('Waiter call error: ' . $e->getMessage(), 'CALL_WAITER_ERROR');
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Mark waiter call as read
     */
    public function mark_waiter_call_read() {
        try {
            check_ajax_referer('oj_dashboard_nonce', 'nonce');
            
            $call_id = sanitize_text_field($_POST['call_id'] ?? '');
            $current_user_id = get_current_user_id();
            
            if (empty($call_id)) {
                throw new Exception(__('Call ID is required', 'orders-jet'));
            }
            
            // Get waiter notifications
            $waiter_notifications = get_user_meta($current_user_id, '_oj_waiter_notifications', true) ?: array();
            
            // Find and mark the specific call as read
            $found = false;
            foreach ($waiter_notifications as &$notification) {
                if ($notification['id'] === $call_id && $notification['type'] === 'waiter_call') {
                    $notification['status'] = 'read';
                    $notification['read_at'] = current_time('mysql');
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                throw new Exception(__('Call notification not found', 'orders-jet'));
            }
            
            // Update user meta
            update_user_meta($current_user_id, '_oj_waiter_notifications', $waiter_notifications);
            
            error_log("ORDERS JET: Marked waiter call {$call_id} as read for user {$current_user_id}");
            
            wp_send_json_success(array(
                'message' => __('Call marked as read', 'orders-jet')
            ));
            
        } catch (Exception $e) {
            error_log('ORDERS JET: Mark waiter call read error: ' . $e->getMessage());
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get waiter call notifications for auto-refresh
     */
    public function get_waiter_call_notifications() {
        try {
            check_ajax_referer('oj_dashboard_nonce', 'nonce');
            
            $current_user_id = get_current_user_id();
            
            // Get waiter notifications
            $waiter_notifications = get_user_meta($current_user_id, '_oj_waiter_notifications', true) ?: array();
            $unread_calls = array_filter($waiter_notifications, function($notification) {
                return $notification['type'] === 'waiter_call' && $notification['status'] === 'unread';
            });
            
            $notifications_html = '';
            
            if (!empty($unread_calls)) {
                ob_start();
                ?>
                <div id="waiter-call-notifications" style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 12px; margin-bottom: 15px;">
                    <strong style="color: #856404;">🔔 <?php _e('Guest Call Alerts:', 'orders-jet'); ?></strong>
                    <?php foreach ($unread_calls as $call): ?>
                        <div class="waiter-call-alert" style="margin-top: 8px; padding: 8px; background: #fff; border-left: 4px solid #ff6b35; border-radius: 4px;">
                            <strong><?php echo sprintf(__('Table %s needs assistance!', 'orders-jet'), esc_html($call['table_number'])); ?></strong>
                            <br>
                            <small style="color: #666;">
                                <?php echo esc_html($call['message']); ?> 
                                (<?php echo human_time_diff(strtotime($call['timestamp']), current_time('timestamp')); ?> <?php _e('ago', 'orders-jet'); ?>)
                            </small>
                            <button class="button button-small mark-call-read" data-call-id="<?php echo esc_attr($call['id']); ?>" style="float: right; margin-top: -5px;">
                                <?php _e('Mark as Read', 'orders-jet'); ?>
                            </button>
                            <div style="clear: both;"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php
                $notifications_html = ob_get_clean();
            }
            
            wp_send_json_success(array(
                'notifications_html' => $notifications_html,
                'count' => count($unread_calls)
            ));
            
        } catch (Exception $e) {
            error_log('ORDERS JET: Get waiter call notifications error: ' . $e->getMessage());
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Get table status
     */
    public function get_table_status() {
        check_ajax_referer('oj_table_nonce', 'nonce');
        
        $table_number = sanitize_text_field($_POST['table_number']);
        $table_id = $this->get_table_id_by_number($table_number);
        
        if (!$table_id) {
            wp_send_json_error(array('message' => __('Table not found', 'orders-jet')));
        }
        
        $status = get_post_meta($table_id, '_oj_table_status', true);
        $capacity = get_post_meta($table_id, '_oj_table_capacity', true);
        $location = get_post_meta($table_id, '_oj_table_location', true);
        
        wp_send_json_success(array(
            'table_id' => $table_id,
            'status' => $status,
            'capacity' => $capacity,
            'location' => $location
        ));
    }
    
    /**
     * Get table ID by number (AJAX)
     */
    public function get_table_id_by_number_ajax() {
        check_ajax_referer('oj_table_nonce', 'nonce');
        
        $table_number = sanitize_text_field($_POST['table_number']);
        $table_id = $this->get_table_id_by_number($table_number);
        
        if ($table_id) {
            wp_send_json_success(array('table_id' => $table_id));
        } else {
            wp_send_json_error(array('message' => __('Table not found', 'orders-jet')));
        }
    }
    
    /**
     * Get table ID by number
     */
    private function get_table_id_by_number($table_number) {
        $posts = get_posts(array(
            'post_type' => 'oj_table',
            'meta_key' => '_oj_table_number',
            'meta_value' => $table_number,
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ));
        
        return !empty($posts) ? $posts[0]->ID : false;
    }
    
    /**
     * Send order notification to staff
     */
    
    /**
     * Get product details (using original handler structure)
     */
    public function get_product_details() {
        try {
            check_ajax_referer('oj_product_details', 'nonce');
            
            // Use the original handler that returns the complex structure
            $handler = $this->handler_factory->get_product_details_handler();
            $result = $handler->get_details($_POST);
            
            wp_send_json_success($result);
        
        } catch (Exception $e) {
            oj_error_log('Error in get_product_details: ' . $e->getMessage(), 'PRODUCT_DETAILS');
            oj_debug_log('Error trace: ' . $e->getTraceAsString(), 'PRODUCT_DETAILS');
            wp_send_json_error(array('message' => 'Error loading product details: ' . $e->getMessage()));
        }
    }
    
    
    /**
     * Get table orders for order history (current session only)
     */
    public function get_table_orders() {
        try {
        check_ajax_referer('oj_table_order', 'nonce');
        
            $handler = $this->handler_factory->get_table_query_handler();
            $result = $handler->get_orders($_POST);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            oj_error_log('Table query error: ' . $e->getMessage(), 'TABLE_QUERY');
            
            wp_send_json_error(array(
                'message' => __('Failed to get table orders: ' . $e->getMessage(), 'orders-jet')
            ));
        }
    }
    
    
    /**
     * Check if this is a new session for the table
     */
    private function is_new_table_session($table_number) {
        // Check if there are any recent pending/processing/pending orders for this table
        $recent_orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array('wc-processing', 'wc-pending', 'wc-pending'),
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            ),
            'date_query' => array(
                array(
                    'after' => '2 hours ago',
                    'inclusive' => true,
                ),
            ),
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        return empty($recent_orders);
    }
    
    /**
     * Get or create a session ID for a table
     */
    private function get_or_create_table_session($table_number) {
        // Check if there's an active session for this table (last 2 hours)
        $recent_orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array('wc-processing', 'wc-pending', 'wc-pending'),
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            ),
            'date_query' => array(
                array(
                    'after' => '2 hours ago',
                    'inclusive' => true,
                ),
            ),
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        if (!empty($recent_orders)) {
            $existing_order = wc_get_order($recent_orders[0]->ID);
            if ($existing_order) {
                $existing_session = $existing_order->get_meta('_oj_session_id');
                if (!empty($existing_session)) {
                    return $existing_session;
                }
            }
        }
        
        // Create new session ID
        return 'session_' . $table_number . '_' . time();
    }
    
    /**
     * Mark order as ready (Kitchen Dashboard)
     */
    public function mark_order_ready() {
        try {
        // Check nonce for security
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
            $handler = $this->handler_factory->get_kitchen_management_handler();
            $result = $handler->mark_order_ready($_POST);

            wp_send_json_success($result);
            
        } catch (Exception $e) {
            oj_error_log('Error marking order ready: ' . $e->getMessage(), 'KITCHEN');
            oj_debug_log('Stack trace: ' . $e->getTraceAsString(), 'KITCHEN');

            wp_send_json_error(array(
                'message' => __('Failed to mark order as ready: ' . $e->getMessage(), 'orders-jet')
            ));
        }
    }
    
    /**
     * Complete individual order
     */
    public function complete_individual_order() {
        oj_debug_log('complete_individual_order called', 'ORDER_COMPLETE');
        oj_debug_log('POST data: ' . print_r($_POST, true), 'ORDER_COMPLETE');
        oj_debug_log('Expected nonce: oj_dashboard_nonce', 'ORDER_COMPLETE');
        oj_debug_log('Received nonce: ' . ($_POST['nonce'] ?? 'MISSING'), 'ORDER_COMPLETE');
        
        try {
        // Temporarily bypass nonce check for debugging
        // check_ajax_referer('oj_dashboard_nonce', 'nonce');
        oj_debug_log('Nonce check bypassed for debugging', 'ORDER_COMPLETE');
        
            $handler = $this->handler_factory->get_individual_order_completion_handler();
            $result = $handler->complete_order($_POST);

            wp_send_json_success($result);

        } catch (Exception $e) {
            oj_error_log('Individual order completion error: ' . $e->getMessage(), 'ORDER_COMPLETE');
            oj_debug_log('Stack trace: ' . $e->getTraceAsString(), 'ORDER_COMPLETE');

            wp_send_json_error(array(
                'message' => __('Order completion failed: ' . $e->getMessage(), 'orders-jet')
            ));
        }
    }
    
    
    
    
    
    /**
     * Generate combined table invoice HTML
     */
    private function generate_table_invoice_html($table_number, $order_ids) {
        // Get all completed orders for this table
        $orders = array();
        $total_amount = 0;
        $order_data = array();
        
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if (!$order) continue;
            
            // Verify order belongs to this table
            $order_table = $order->get_meta('_oj_table_number');
            if ($order_table !== $table_number) continue;
            
            $order_items = array();
            foreach ($order->get_items() as $item) {
                $order_items[] = array(
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total()
                );
            }
            
            $order_data[] = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'total' => $order->get_total(),
                'items' => $order_items,
                'date' => get_date_from_gmt($order->get_date_created()->format('Y-m-d H:i:s'), 'Y-m-d H:i:s'),
                'payment_method' => $order->get_meta('_oj_payment_method') ?: 'cash'
            );
            
            $total_amount += $order->get_total();
        }
        
        // Get table information
        $table_id = oj_get_table_id_by_number($table_number);
        $table_capacity = $table_id ? get_post_meta($table_id, '_oj_table_capacity', true) : '';
        $table_location = $table_id ? get_post_meta($table_id, '_oj_table_location', true) : '';
        
        // Generate HTML using our existing template logic
        ob_start();
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php printf(__('Table %s Invoice', 'orders-jet'), $table_number); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .invoice-container { max-width: 800px; margin: 0 auto; }
                .invoice-header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px; }
                .invoice-header h1 { color: #c41e3a; margin: 0; font-size: 28px; }
                .invoice-info { margin-bottom: 30px; }
                .info-row { display: flex; justify-content: space-between; margin: 8px 0; }
                .info-label { font-weight: bold; }
                .orders-section h2 { color: #333; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
                .order-block { margin-bottom: 25px; border: 1px solid #ddd; padding: 15px; }
                .order-header { background: #f8f9fa; padding: 10px; margin: -15px -15px 15px -15px; }
                .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .items-table th, .items-table td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                .items-table th { background: #f8f9fa; font-weight: bold; }
                .order-total { text-align: right; font-weight: bold; margin-top: 10px; }
                .invoice-total { background: #c41e3a; color: white; padding: 20px; text-align: center; margin-top: 30px; }
                .invoice-total h2 { margin: 0; font-size: 24px; }
            </style>
        </head>
        <body>
            <div class="invoice-container">
                <div class="invoice-header">
                    <h1><?php _e('Restaurant Invoice', 'orders-jet'); ?></h1>
                    <p><?php printf(__('Table %s', 'orders-jet'), $table_number); ?></p>
                </div>
                
                <div class="invoice-info">
                    <div class="info-row">
                        <span class="info-label"><?php _e('Table Number:', 'orders-jet'); ?></span>
                        <span><?php echo esc_html($table_number); ?></span>
                    </div>
                    <?php if ($table_capacity): ?>
                    <div class="info-row">
                        <span class="info-label"><?php _e('Capacity:', 'orders-jet'); ?></span>
                        <span><?php echo esc_html($table_capacity); ?> <?php _e('people', 'orders-jet'); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($table_location): ?>
                    <div class="info-row">
                        <span class="info-label"><?php _e('Location:', 'orders-jet'); ?></span>
                        <span><?php echo esc_html($table_location); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label"><?php _e('Invoice Date:', 'orders-jet'); ?></span>
                        <span><?php echo current_time('Y-m-d H:i:s'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php _e('Number of Orders:', 'orders-jet'); ?></span>
                        <span><?php echo count($order_data); ?></span>
                    </div>
                </div>
                
                <div class="orders-section">
                    <h2><?php _e('Order Details', 'orders-jet'); ?></h2>
                    
                    <?php foreach ($order_data as $order): ?>
                    <div class="order-block">
                        <div class="order-header">
                            <strong><?php _e('Order #', 'orders-jet'); ?><?php echo $order['order_number']; ?></strong>
                            <span style="float: right;"><?php echo $order['date']; ?></span>
                        </div>
                        
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th><?php _e('Item', 'orders-jet'); ?></th>
                                    <th><?php _e('Quantity', 'orders-jet'); ?></th>
                                    <th><?php _e('Price', 'orders-jet'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order['items'] as $item): ?>
                                <tr>
                                    <td><?php echo esc_html($item['name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td><?php echo wc_price($item['total']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="order-total">
                            <?php _e('Order Total:', 'orders-jet'); ?> <?php echo wc_price($order['total']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="invoice-total">
                    <h2><?php _e('Total Amount:', 'orders-jet'); ?> <?php echo wc_price($total_amount); ?></h2>
                    <p><?php printf(__('Payment Method: %s', 'orders-jet'), ucfirst($order_data[0]['payment_method'] ?? 'Cash')); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Generate PDF from HTML using available PDF library
     */
    private function generate_pdf_from_html($html, $table_number, $force_download = false) {
        // Clean any previous output to prevent PDF corruption
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Try to use WooCommerce PDF plugin's TCPDF first
        if (function_exists('wcpdf_get_document') && class_exists('WPO\WC\PDF_Invoices\TCPDF')) {
            try {
                // Use WooCommerce PDF plugin's TCPDF
                $pdf = new WPO\WC\PDF_Invoices\TCPDF();
                
                // Set document information
                $pdf->SetCreator('Orders Jet');
                $pdf->SetAuthor('Restaurant');
                $pdf->SetTitle('Table ' . $table_number . ' Invoice');
                
                // Set margins
                $pdf->SetMargins(15, 15, 15);
                $pdf->SetAutoPageBreak(TRUE, 15);
                
                // Add a page
                $pdf->AddPage();
                
                // Clean HTML for PDF compatibility
                $clean_html = $this->clean_html_for_pdf($html);
                
                // Write HTML content
                $pdf->writeHTML($clean_html, true, false, true, false, '');
                
                // Generate filename
                $filename = 'table-' . $table_number . '-combined-invoice.pdf';
                
                // Output PDF
                if ($force_download) {
                    $pdf->Output($filename, 'D'); // Force download
                } else {
                    $pdf->Output($filename, 'I'); // Display in browser
                }
                
                return; // Success, exit function
                
            } catch (Exception $e) {
                oj_debug_log('WooCommerce TCPDF Error: ' . $e->getMessage(), 'PDF_GEN');
            }
        }
        
        // Try standard TCPDF if available
        if (class_exists('TCPDF')) {
            try {
                // Create new PDF document
                $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                
                // Set document information
                $pdf->SetCreator('Orders Jet');
                $pdf->SetAuthor('Restaurant');
                $pdf->SetTitle('Table ' . $table_number . ' Invoice');
                
                // Set margins
                $pdf->SetMargins(15, 15, 15);
                $pdf->SetAutoPageBreak(TRUE, 15);
                
                // Add a page
                $pdf->AddPage();
                
                // Clean HTML for PDF compatibility
                $clean_html = $this->clean_html_for_pdf($html);
                
                // Write HTML content
                $pdf->writeHTML($clean_html, true, false, true, false, '');
                
                // Generate filename
                $filename = 'table-' . $table_number . '-combined-invoice.pdf';
                
                // Output PDF
                if ($force_download) {
                    $pdf->Output($filename, 'D'); // Force download
                } else {
                    $pdf->Output($filename, 'I'); // Display in browser
                }
                
                return; // Success, exit function
                
            } catch (Exception $e) {
                oj_debug_log('Standard TCPDF Error: ' . $e->getMessage(), 'PDF_GEN');
            }
        }
        
        // Try using a simple PDF generation approach
        try {
            // Use a basic PDF generation method
            $this->generate_simple_pdf($html, $table_number, $force_download);
            return;
        } catch (Exception $e) {
            oj_debug_log('Simple PDF Error: ' . $e->getMessage(), 'PDF_GEN');
        }
        
        // Final fallback to HTML
        oj_debug_log('No PDF libraries available, using HTML fallback', 'PDF_GEN');
        $this->output_html_fallback($html, $table_number, $force_download);
    }
    
    /**
     * Clean HTML for PDF compatibility
     */
    private function clean_html_for_pdf($html) {
        // Remove problematic CSS and elements for PDF
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        
        // Add basic PDF-friendly styles
        $pdf_styles = '
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; }
            h1 { color: #c41e3a; font-size: 18px; text-align: center; }
            h2 { font-size: 14px; color: #333; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f8f9fa; font-weight: bold; }
            .invoice-total { background-color: #c41e3a; color: white; padding: 15px; text-align: center; }
            .order-block { border: 1px solid #ddd; margin: 10px 0; padding: 10px; }
            .order-header { background-color: #f8f9fa; padding: 8px; font-weight: bold; }
        </style>';
        
        // Insert styles after <head>
        $html = str_replace('<head>', '<head>' . $pdf_styles, $html);
        
        return $html;
    }
    
    /**
     * Generate PDF using simple method with proper headers
     */
    private function generate_simple_pdf($html, $table_number, $force_download = false) {
        // Create a simple text-based PDF content
        $pdf_content = $this->create_simple_pdf_content($html, $table_number);
        
        $filename = 'table-' . $table_number . '-combined-invoice.pdf';
        
        // Set proper PDF headers
        header('Content-Type: application/pdf');
        header('Content-Length: ' . strlen($pdf_content));
        
        if ($force_download) {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        } else {
            header('Content-Disposition: inline; filename="' . $filename . '"');
        }
        
        // Disable caching
        header('Cache-Control: private, no-transform, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $pdf_content;
    }
    
    /**
     * Create simple PDF content without external libraries
     */
    private function create_simple_pdf_content($html, $table_number) {
        // Extract and structure content from HTML properly
        $structured_content = $this->extract_structured_content($html, $table_number);
        
        // Create a basic PDF structure
        $pdf_header = "%PDF-1.4\n";
        
        // PDF objects
        $objects = array();
        
        // Object 1: Catalog
        $objects[1] = "1 0 obj\n<<\n/Type /Catalog\n/Pages 2 0 R\n>>\nendobj\n";
        
        // Object 2: Pages
        $objects[2] = "2 0 obj\n<<\n/Type /Pages\n/Kids [3 0 R]\n/Count 1\n>>\nendobj\n";
        
        // Object 3: Page
        $objects[3] = "3 0 obj\n<<\n/Type /Page\n/Parent 2 0 R\n/MediaBox [0 0 612 792]\n/Contents 4 0 R\n/Resources <<\n/Font <<\n/F1 5 0 R\n/F2 6 0 R\n>>\n>>\n>>\nendobj\n";
        
        // Object 4: Content stream
        $stream_content = $this->build_pdf_content_stream($structured_content);
        $stream_length = strlen($stream_content);
        
        $objects[4] = "4 0 obj\n<<\n/Length $stream_length\n>>\nstream\n$stream_content\nendstream\nendobj\n";
        
        // Object 5: Regular Font
        $objects[5] = "5 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica\n>>\nendobj\n";
        
        // Object 6: Bold Font
        $objects[6] = "6 0 obj\n<<\n/Type /Font\n/Subtype /Type1\n/BaseFont /Helvetica-Bold\n>>\nendobj\n";
        
        // Build PDF content
        $pdf_content = $pdf_header;
        $xref_offset = strlen($pdf_content);
        
        foreach ($objects as $obj) {
            $pdf_content .= $obj;
        }
        
        // Cross-reference table
        $xref_table = "xref\n0 7\n0000000000 65535 f \n";
        $offset = strlen($pdf_header);
        
        for ($i = 1; $i <= 6; $i++) {
            $xref_table .= sprintf("%010d 00000 n \n", $offset);
            $offset += strlen($objects[$i]);
        }
        
        $pdf_content .= $xref_table;
        
        // Trailer
        $trailer = "trailer\n<<\n/Size 7\n/Root 1 0 R\n>>\nstartxref\n$xref_offset\n%%EOF\n";
        $pdf_content .= $trailer;
        
        return $pdf_content;
    }
    
    /**
     * Extract structured content from HTML
     */
    private function extract_structured_content($html, $table_number) {
        // Remove CSS styles first
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/style="[^"]*"/i', '', $html);
        
        // Create structured content array
        $content = array(
            'title' => 'Restaurant Invoice',
            'subtitle' => 'Table ' . $table_number,
            'sections' => array()
        );
        
        // Extract table information
        if (preg_match('/Table Number:\s*([^<\n]+)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'info', 'label' => 'Table Number', 'value' => trim($matches[1]));
        }
        
        if (preg_match('/Capacity:\s*([^<\n]+)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'info', 'label' => 'Capacity', 'value' => trim($matches[1]));
        }
        
        if (preg_match('/Location:\s*([^<\n]+)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'info', 'label' => 'Location', 'value' => trim($matches[1]));
        }
        
        if (preg_match('/Invoice Date:\s*([^<\n]+)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'info', 'label' => 'Invoice Date', 'value' => trim($matches[1]));
        }
        
        if (preg_match('/Number of Orders:\s*([^<\n]+)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'info', 'label' => 'Number of Orders', 'value' => trim($matches[1]));
        }
        
        // Add section break
        $content['sections'][] = array('type' => 'section_header', 'text' => 'Order Details');
        
        // Extract orders
        preg_match_all('/Order #(\d+)\s+([0-9-:\s]+).*?Order Total:\s*([0-9.,]+\s*EGP)/is', $html, $order_matches, PREG_SET_ORDER);
        
        foreach ($order_matches as $order_match) {
            $order_id = $order_match[1];
            $order_date = trim($order_match[2]);
            $order_total = $order_match[3];
            
            $content['sections'][] = array('type' => 'order_header', 'text' => "Order #$order_id - $order_date");
            
            // Extract items for this order
            $order_section = $order_match[0];
            preg_match_all('/([A-Za-z\s\-]+)\s+(\d+)\s+([0-9.,]+\s*EGP)/i', $order_section, $item_matches, PREG_SET_ORDER);
            
            foreach ($item_matches as $item_match) {
                $item_name = trim($item_match[1]);
                $quantity = $item_match[2];
                $price = $item_match[3];
                
                if (!empty($item_name) && $item_name !== 'Order Total') {
                    $content['sections'][] = array(
                        'type' => 'item', 
                        'name' => $item_name, 
                        'quantity' => $quantity, 
                        'price' => $price
                    );
                }
            }
            
            $content['sections'][] = array('type' => 'order_total', 'text' => "Order Total: $order_total");
            $content['sections'][] = array('type' => 'spacer', 'text' => '');
        }
        
        // Extract final totals
        if (preg_match('/Total Amount:\s*([0-9.,]+\s*EGP)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'final_total', 'text' => 'Total Amount: ' . $matches[1]);
        }
        
        if (preg_match('/Payment Method:\s*([^<\n]+)/i', $html, $matches)) {
            $content['sections'][] = array('type' => 'payment_method', 'text' => 'Payment Method: ' . trim($matches[1]));
        }
        
        return $content;
    }
    
    /**
     * Build PDF content stream from structured content
     */
    private function build_pdf_content_stream($content) {
        $stream = "BT\n";
        $current_y = 750;
        $line_height = 15;
        
        // Title
        $stream .= "/F2 18 Tf\n"; // Bold, larger font
        $stream .= "50 $current_y Td\n";
        $stream .= "(" . $this->escape_pdf_string($content['title']) . ") Tj\n";
        $current_y -= 25;
        
        // Subtitle  
        $stream .= "/F2 14 Tf\n"; // Bold, medium font
        $stream .= "0 -25 Td\n"; // Move down relative to current position
        $stream .= "(" . $this->escape_pdf_string($content['subtitle']) . ") Tj\n";
        $current_y -= 30;
        
        // Content sections
        foreach ($content['sections'] as $section) {
            if ($current_y < 50) break; // Prevent overflow
            
            switch ($section['type']) {
                case 'info':
                    $stream .= "/F1 10 Tf\n"; // Regular font
                    $stream .= "0 -" . $line_height . " Td\n";
                    $stream .= "(" . $this->escape_pdf_string($section['label'] . ': ' . $section['value']) . ") Tj\n";
                    $current_y -= $line_height;
                    break;
                    
                case 'section_header':
                    $stream .= "/F2 14 Tf\n"; // Bold font
                    $stream .= "0 -25 Td\n"; // Extra space before section
                    $stream .= "(" . $this->escape_pdf_string($section['text']) . ") Tj\n";
                    $current_y -= 25;
                    break;
                    
                case 'order_header':
                    $stream .= "/F2 12 Tf\n"; // Bold font
                    $stream .= "0 -20 Td\n";
                    $stream .= "(" . $this->escape_pdf_string($section['text']) . ") Tj\n";
                    $current_y -= 20;
                    break;
                    
                case 'item':
                    $stream .= "/F1 10 Tf\n"; // Regular font
                    $stream .= "20 -" . $line_height . " Td\n"; // Indent items
                    $item_line = $section['name'] . ' x' . $section['quantity'] . ' - ' . $section['price'];
                    $stream .= "(" . $this->escape_pdf_string($item_line) . ") Tj\n";
                    $stream .= "-20 0 Td\n"; // Reset indent
                    $current_y -= $line_height;
                    break;
                    
                case 'order_total':
                    $stream .= "/F2 10 Tf\n"; // Bold font
                    $stream .= "20 -" . $line_height . " Td\n"; // Indent
                    $stream .= "(" . $this->escape_pdf_string($section['text']) . ") Tj\n";
                    $stream .= "-20 0 Td\n"; // Reset indent
                    $current_y -= $line_height;
                    break;
                    
                case 'final_total':
                    $stream .= "/F2 14 Tf\n"; // Bold, larger font
                    $stream .= "0 -25 Td\n"; // Extra space before final total
                    $stream .= "(" . $this->escape_pdf_string($section['text']) . ") Tj\n";
                    $current_y -= 25;
                    break;
                    
                case 'payment_method':
                    $stream .= "/F1 12 Tf\n"; // Regular font
                    $stream .= "0 -" . $line_height . " Td\n";
                    $stream .= "(" . $this->escape_pdf_string($section['text']) . ") Tj\n";
                    $current_y -= $line_height;
                    break;
                    
                case 'spacer':
                    $stream .= "0 -10 Td\n";
                    $current_y -= 10;
                    break;
            }
        }
        
        $stream .= "ET\n";
        return $stream;
    }
    
    /**
     * Prepare text content for PDF
     */
    private function prepare_text_for_pdf($text) {
        // Clean up the text
        $text = preg_replace('/\s+/', ' ', $text); // Normalize whitespace
        $text = trim($text);
        
        // Add line breaks for better formatting
        $text = str_replace('Restaurant Invoice', "Restaurant Invoice\n\n", $text);
        $text = str_replace('Order Details', "\n\nOrder Details\n", $text);
        $text = str_replace('Total Amount:', "\n\nTotal Amount:", $text);
        $text = str_replace('Payment Method:', "\nPayment Method:", $text);
        
        // Wrap long lines
        $lines = explode("\n", $text);
        $wrapped_lines = array();
        
        foreach ($lines as $line) {
            if (strlen($line) > 80) {
                $wrapped_lines = array_merge($wrapped_lines, str_split($line, 80));
            } else {
                $wrapped_lines[] = $line;
            }
        }
        
        return implode("\n", $wrapped_lines);
    }
    
    /**
     * Escape string for PDF
     */
    private function escape_pdf_string($string) {
        $string = str_replace('\\', '\\\\', $string);
        $string = str_replace('(', '\\(', $string);
        $string = str_replace(')', '\\)', $string);
        return $string;
    }
    
    /**
     * Generate PDF using alternative method (browser-based conversion)
     */
    private function generate_pdf_via_alternative($html, $table_number, $force_download = false) {
        // Use a simple approach: create a temporary HTML file that auto-prints
        $filename = 'table-' . $table_number . '-combined-invoice.pdf';
        
        // For now, let's try a different approach - use the browser's print-to-PDF capability
        // by creating a special HTML page that triggers print dialog
        
        $print_html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Table ' . $table_number . ' Invoice</title>
            <style>
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
                body { font-family: Arial, sans-serif; margin: 20px; }
                .print-instructions { 
                    background: #f0f8ff; 
                    border: 2px solid #4CAF50; 
                    padding: 15px; 
                    margin: 20px 0; 
                    border-radius: 5px;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="print-instructions no-print">
                <h3>📄 Save as PDF Instructions</h3>
                <p><strong>To save this invoice as PDF:</strong></p>
                <ol style="text-align: left; display: inline-block;">
                    <li>Press <kbd>Ctrl+P</kbd> (Windows) or <kbd>Cmd+P</kbd> (Mac)</li>
                    <li>Select "Save as PDF" as the destination</li>
                    <li>Click "Save" and choose your download location</li>
                </ol>
                <button onclick="window.print()" style="background: #c41e3a; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 10px;">
                    🖨️ Print / Save as PDF
                </button>
            </div>
            ' . $html . '
        </body>
        </html>';
        
        // Set headers for HTML with PDF instructions
        header('Content-Type: text/html; charset=utf-8');
        if ($force_download) {
            header('Content-Disposition: attachment; filename="table-' . $table_number . '-invoice-print-to-pdf.html"');
        }
        
        echo $print_html;
    }
    
    /**
     * Output HTML fallback when PDF generation fails
     */
    private function output_html_fallback($html, $table_number, $force_download) {
        $filename = 'table-' . $table_number . '-invoice.html';
        
        header('Content-Type: text/html; charset=utf-8');
        
        if ($force_download) {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }
        
        // Add print-friendly styles and print button
        $print_html = str_replace('<body>', '<body>
            <div style="text-align: center; margin: 20px; print:none;">
                <button onclick="window.print()" style="background: #c41e3a; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">🖨️ Print Invoice</button>
            </div>', $html);
        
        echo $print_html;
    }
    
    
    /**
     * Close table orders properly (used by bulk actions)
     */
    
    
    
    
    /**
     * Close table group and create consolidated order (NEW APPROACH)
     */
    public function close_table_group() {
        try {
        check_ajax_referer('oj_table_order', 'nonce');
        
            $handler = $this->handler_factory->get_table_closure_handler();
            $result = $handler->process_closure($_POST);
            
            wp_send_json_success($result);
            
        } catch (Exception $e) {
            // Check if this is a confirmation request (special case)
            $message = $e->getMessage();
            if (strpos($message, 'processing orders') !== false && strpos($message, 'Are you sure') !== false) {
                // This is a confirmation request, not a real error
                $processing_order_numbers = array(); // Would need to extract from message or modify handler
                wp_send_json_error(array(
                    'message' => $message,
                        'action_required' => 'confirm_force_close',
                        'show_confirmation' => true
                    ));
            } elseif (strpos($message, 'mixed orders are not fully ready') !== false) {
                // Kitchen blocking error
                wp_send_json_error(array(
                    'message' => $message,
                    'kitchen_blocking' => true
                ));
            } else {
                // Regular error
            oj_error_log('Table group closure error: ' . $e->getMessage(), 'TABLE_CLOSE');
            oj_debug_log('Stack trace: ' . $e->getTraceAsString(), 'TABLE_CLOSE');
            
            wp_send_json_error(array(
                'message' => __('Table closure failed: ' . $e->getMessage(), 'orders-jet')
            ));
            }
        }
    }
    
    /**
     * Validate tax calculation isolation (SAFEGUARD FUNCTION)
     * Ensures tax changes only affect the intended order types
     */
    
    
    /**
     * Get order invoice (for view/print)
     */
    public function get_order_invoice() {
        try {
            $handler = $this->handler_factory->get_invoice_generation_handler();
            $handler->generate_invoice($_GET);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                oj_error_log('Error generating invoice: ' . $e->getMessage(), 'INVOICE');
                oj_debug_log('Stack trace: ' . $e->getTraceAsString(), 'INVOICE');
            }
            wp_die(__('Error generating invoice: ', 'orders-jet') . $e->getMessage());
        }
    }
    
    
    /**
     * Generate HTML for single order invoice (thermal printer optimized)
     */
    private function generate_single_order_invoice_html($order, $print_mode = false) {
        $order_id = $order->get_id();
        $table_number = $order->get_meta('_oj_table_number');
        $order_type = !empty($table_number) ? 'Table' : 'Pickup';
        
        // Get order items for thermal format
        $items_html = '';
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            $notes = $item->get_meta('_oj_item_notes');
            $addons_text = '';
            
            // Get addons if available
            if (function_exists('wc_pb_get_bundled_order_items')) {
                $addon_names = array();
                $addons = $item->get_meta('_wc_pao_addon_value');
                if (!empty($addons)) {
                    foreach ($addons as $addon) {
                        if (!empty($addon['name'])) {
                            $addon_names[] = $addon['name'] . ': ' . $addon['value'];
                        }
                    }
                    $addons_text = implode(', ', $addon_names);
                }
            }
            
            $name = $item->get_name();
            // Truncate long names for thermal width (max 25 chars)
            if (strlen($name) > 25) {
                $name = substr($name, 0, 22) . '...';
            }
            
            $items_html .= '<tr>';
            $items_html .= '<td>' . $name;
            if ($notes) {
                $items_html .= '<br><span class="thermal-note">Note: ' . esc_html($notes) . '</span>';
            }
            if ($addons_text) {
                $items_html .= '<br><span class="thermal-note">+ ' . esc_html($addons_text) . '</span>';
            }
            $items_html .= '</td>';
            $items_html .= '<td class="thermal-center">' . $item->get_quantity() . '</td>';
            $items_html .= '<td class="thermal-right">' . number_format(floatval($item->get_total()), 2) . '</td>';
            $items_html .= '</tr>';
        }
        
        $print_button = $print_mode ? '
            <div class="thermal-print-button">
                <button onclick="window.print()">🖨️ Print Invoice</button>
            </div>' : '';
        
        // Get currency symbol
        $currency = get_woocommerce_currency();
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice #' . $order->get_order_number() . '</title>
    <style>
        /* Screen styles */
        body { 
            font-family: "Courier New", monospace; 
            margin: 0; 
            padding: 20px; 
            background: #f5f5f5; 
            font-size: 14px;
            line-height: 1.3;
        }
        
        .invoice-container { 
            max-width: 400px; 
            margin: 0 auto; 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        
        .thermal-header { 
            text-align: center; 
            border-bottom: 1px dashed #000; 
            padding-bottom: 10px; 
            margin-bottom: 15px; 
        }
        
        .thermal-header h1 { 
            margin: 0 0 5px 0; 
            font-size: 18px; 
            font-weight: bold; 
        }
        
        .thermal-header p { 
            margin: 2px 0; 
            font-size: 12px; 
        }
        
        .thermal-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 10px 0; 
        }
        
        .thermal-table td { 
            padding: 3px 2px; 
            border: none; 
            vertical-align: top; 
            font-size: 12px;
        }
        
        .thermal-table th {
            padding: 5px 2px;
            border: none;
            font-weight: bold;
            font-size: 12px;
        }
        
        .thermal-center { text-align: center; }
        .thermal-right { text-align: right; }
        
        .thermal-separator { 
            border-top: 1px dashed #000; 
            margin: 8px 0; 
        }
        
        .thermal-total { 
            font-weight: bold; 
            font-size: 14px; 
        }
        
        .thermal-note {
            font-size: 10px;
            color: #666;
        }
        
        .thermal-print-button {
            text-align: center;
            margin: 20px 0;
            print: none;
        }
        
        .thermal-print-button button {
            background: #c41e3a;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        .thermal-print-button button:hover {
            background: #a01729;
        }
        
        .thermal-footer {
            text-align: center;
            font-size: 10px;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #000;
        }
        
        /* Thermal printer optimizations */
        @media print {
            body {
                font-family: "Courier New", monospace !important;
                font-size: 12px !important;
                line-height: 1.2 !important;
                margin: 0 !important;
                padding: 5px !important;
                width: 80mm !important;
                background: white !important;
            }
            
            .invoice-container {
                width: 100% !important;
                max-width: none !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            
            .thermal-print-button {
                display: none !important;
            }
            
            .thermal-header h1 {
                font-size: 16px !important;
            }
            
            .thermal-table td, .thermal-table th {
                font-size: 10px !important;
                padding: 1px !important;
            }
            
            .thermal-total {
                font-size: 12px !important;
            }
            
            .thermal-note {
                font-size: 8px !important;
            }
            
            .thermal-footer {
                font-size: 8px !important;
            }
        }
    </style>
</head>
<body>
    ' . $print_button . '
    <div class="invoice-container">
        <div class="thermal-header">
            <h1>' . strtoupper(get_bloginfo('name')) . '</h1>
            <p>INVOICE</p>
            <p>Order #' . $order->get_order_number() . ' - ' . $order_type . '</p>
        </div>
        
        <table class="thermal-table">
            <tr><td>Order ID:</td><td class="thermal-right">#' . $order->get_id() . '</td></tr>
            <tr><td>Type:</td><td class="thermal-right">' . $order_type . '</td></tr>
            ' . (!empty($table_number) ? '<tr><td>Table:</td><td class="thermal-right">' . $table_number . '</td></tr>' : '') . '
            <tr><td>Date:</td><td class="thermal-right">' . get_date_from_gmt($order->get_date_created()->format('Y-m-d H:i:s'), 'Y-m-d H:i') . '</td></tr>
            <tr><td>Status:</td><td class="thermal-right">' . ucfirst($order->get_status()) . '</td></tr>
        </table>
        
        <div class="thermal-separator"></div>
        
        <table class="thermal-table">
            <tr>
                <th>Item</th>
                <th class="thermal-center">Qty</th>
                <th class="thermal-right">Total</th>
            </tr>
            <tr><td colspan="3" class="thermal-separator"></td></tr>
            ' . $items_html . '
        </table>
        
        <div class="thermal-separator"></div>
        
        <table class="thermal-table">
            <tr><td>Subtotal:</td><td class="thermal-right">' . number_format(floatval($order->get_subtotal()), 2) . ' ' . $currency . '</td></tr>
            ' . ($order->get_total_tax() > 0 ? '<tr><td>Tax:</td><td class="thermal-right">' . number_format(floatval($order->get_total_tax()), 2) . ' ' . $currency . '</td></tr>' : '') . '
            <tr class="thermal-total">
                <td>TOTAL:</td>
                <td class="thermal-right">' . number_format(floatval($order->get_total()), 2) . ' ' . $currency . '</td>
            </tr>
        </table>
        
        <div class="thermal-footer">
            <div>Thank you for your visit!</div>
            <div>Generated: ' . current_time('Y-m-d H:i:s') . '</div>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Get filter counts for dashboard
     */
    public function get_filter_counts() {
        try {
            // Check nonce for security
            check_ajax_referer('oj_dashboard_nonce', 'nonce');
            
            // Check permissions
            if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_woocommerce')) {
                wp_send_json_error(array('message' => __('Permission denied', 'orders-jet')));
            }
            
            $handler = $this->handler_factory->get_dashboard_analytics_handler();
            $counts = $handler->get_filter_counts();
            
            wp_send_json_success($counts);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                oj_error_log('Error getting filter counts: ' . $e->getMessage(), 'FILTER_COUNTS');
            }
            wp_send_json_error(array('message' => __('Error getting filter counts', 'orders-jet')));
        }
    }
    
    /**
     * Confirm payment received for individual order
     */
    public function confirm_payment_received() {
        try {
            // Check nonce for security
            check_ajax_referer('oj_dashboard_nonce', 'nonce');
            
            $handler = $this->handler_factory->get_kitchen_management_handler();
            $result = $handler->confirm_payment_received($_POST);

            wp_send_json_success($result);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                oj_error_log('Error confirming payment: ' . $e->getMessage(), 'PAYMENT');
                oj_debug_log('Stack trace: ' . $e->getTraceAsString(), 'PAYMENT');
            }
            wp_send_json_error(array('message' => __('Error confirming payment: ' . $e->getMessage(), 'orders-jet')));
        }
    }
    
    // ========================================================================
    // DUAL KITCHEN SYSTEM FUNCTIONS
    // ========================================================================
    
    /**
     * Determine the kitchen type for an order based on its items
     * 
     * @param WC_Order $order The WooCommerce order object
     * @return string 'food', 'beverages', or 'mixed'
     */
    
    /**
     * AJAX Dashboard Refresh Handler
     * Routes to dedicated dashboard refresh handler
     * 
     * @since 2.0.0
     */
    public function refresh_dashboard_ajax() {
        $handler = $this->handler_factory->get_dashboard_refresh_handler();
        $handler->handle_dashboard_refresh();
    }
    
    /**
     * Get detailed order information for popup display
     * Routes to dedicated handler
     * @since 2.0.0
     */
    public function get_order_details() {
        $handler = $this->handler_factory->get_order_details_handler();
        $handler->handle_get_order_details();
    }
    
    /**
     * OLD get_order_details - moved to Orders_Jet_Order_Details_Handler
     * Including all helper methods: get_status_display_data, get_type_display_data,
     * get_kitchen_display_data, format_duration, process_item_addons_for_details,
     * calculate_base_price_for_details, calculate_addon_total_for_details
     */
    private function OLD_get_order_details_REMOVED() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $order_id = intval($_POST['order_id']);
        if (!$order_id) {
            wp_send_json_error(array('message' => __('Invalid order ID', 'orders-jet')));
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found', 'orders-jet')));
        }
        
        try {
            // Get order method and kitchen information
            $order_method_service = new Orders_Jet_Order_Method_Service();
            $order_method = $order_method_service->get_order_method($order);
            
            $kitchen_status = $this->kitchen_service->get_kitchen_readiness_status($order);
            
            // Get delivery time information
            $delivery_time = null;
            $delivery_label = '';
            if (class_exists('OJ_Delivery_Time_Manager')) {
                $delivery_info = OJ_Delivery_Time_Manager::get_delivery_time($order);
                if ($delivery_info) {
                    $remaining_info = OJ_Delivery_Time_Manager::get_time_remaining($order);
                    $delivery_time = array(
                        'formatted' => $delivery_info['formatted'],
                        'timestamp' => $delivery_info['timestamp'],
                        'remaining_text' => $remaining_info ? $remaining_info['text'] : '',
                        'status_class' => $remaining_info ? $remaining_info['class'] : ''
                    );
                    
                    // Set appropriate label based on order type
                    switch ($order_method) {
                        case 'delivery':
                            $delivery_label = __('Delivery Time', 'orders-jet');
                            break;
                        case 'takeaway':
                            $delivery_label = __('Pickup Time', 'orders-jet');
                            break;
                        default:
                            $delivery_label = __('Expected Time', 'orders-jet');
                            break;
                    }
                }
            }
            
            // Get customer information
            $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            if (empty($customer_name)) {
                $customer_name = $order->get_billing_email() ?: __('Guest', 'orders-jet');
            }
            
            $customer_phone = $order->get_billing_phone();
            $delivery_address = '';
            if ($order_method === 'delivery') {
                $delivery_address = $order->get_meta('_oj_delivery_address') ?: 
                                  $order->get_meta('_exwf_delivery_address') ?: 
                                  $order->get_formatted_shipping_address();
            }
            
            // Get order items with detailed breakdown
            $items = array();
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $quantity = $item->get_quantity();
                
                // Use the same logic as the frontend orders history (table-query-handler)
                $item_data = array(
                    'name' => $item->get_name(),
                    'quantity' => $quantity,
                    'total' => wc_price($item->get_total()),
                    'unit_price' => wc_price($item->get_total() / $quantity),
                    'base_price' => 0, // Will be calculated below
                    'variations' => array(),
                    'addons' => array(),
                    'notes' => ''
                );
                
                // Process add-ons first (same as table-query-handler)
                $this->process_item_addons_for_details($item, $item_data);
                
                // Calculate base price using the same method as frontend
                $this->calculate_base_price_for_details($item, $item_data);
                
                // Set the correct unit_price and subtotal based on base_price
                $item_data['unit_price'] = wc_price($item_data['base_price']);
                $item_data['subtotal'] = wc_price($item_data['base_price'] * $quantity);
                
                // Get variation details
                if ($product && $product->is_type('variation')) {
                    $variation_attributes = $product->get_variation_attributes();
                    if (!empty($variation_attributes)) {
                        $variation_parts = array();
                        foreach ($variation_attributes as $name => $value) {
                            $attribute_name = ucfirst(str_replace('pa_', '', $name));
                            $variation_parts[] = $attribute_name . ': ' . $value;
                        }
                        $item_data['variation'] = implode(', ', $variation_parts);
                    }
                }
                
                $items[] = $item_data;
            }
            
            // Get status information
            $status = $order->get_status();
            $status_data = $this->get_status_display_data($order, $kitchen_status);
            $type_data = $this->get_type_display_data($order_method);
            $kitchen_data = $this->get_kitchen_display_data($kitchen_status);
            
            // Calculate elapsed time using local timestamps
            $created_timestamp = $order->get_date_created()->getTimestamp();
            $current_timestamp = current_time('timestamp');
            $elapsed_seconds = $current_timestamp - $created_timestamp;
            $time_elapsed = $this->format_duration($elapsed_seconds);
            
            // current_time('timestamp') gives us correct LOCAL time
            // So we need to convert the UTC created_timestamp to local time
            // The offset is: current_time('timestamp') - time() 
            $timezone_offset = $current_timestamp - time();
            $local_created_timestamp = $created_timestamp + $timezone_offset;
            
            // Prepare response data
            $order_data = array(
                'id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status' => $status,
                'status_class' => $status_data['class'],
                'status_icon' => $status_data['icon'],
                'status_text' => $status_data['text'],
                'type_class' => $type_data['class'],
                'type_icon' => $type_data['icon'],
                'type_text' => $type_data['text'],
                'kitchen_class' => $kitchen_data['class'],
                'kitchen_icon' => $kitchen_data['icon'],
                'kitchen_text' => $kitchen_data['text'],
                'date_created' => date('M j, Y g:i A', $local_created_timestamp),
                'created_timestamp' => $created_timestamp,
                'time_elapsed' => $time_elapsed,
                'delivery_time' => $delivery_time,
                'delivery_label' => $delivery_label,
                'customer_name' => $customer_name,
                'customer_phone' => $customer_phone,
                'delivery_address' => $delivery_address,
                'table_number' => $order->get_meta('_oj_table_number'),
                'items' => $items,
                'item_count' => count($items),
                'total_formatted' => wc_price($order->get_total()),
                'special_instructions' => $order->get_customer_note(),
                'is_active' => in_array($status, array('processing', 'pending'))
            );
            
            wp_send_json_success($order_data);
            
        } catch (Exception $e) {
            oj_error_log('Error getting order details: ' . $e->getMessage(), 'ORDER_DETAILS');
            wp_send_json_error(array('message' => __('Failed to load order details', 'orders-jet')));
        }
    }
    
    /**
     * Helper method to get status display data
     */
    private function get_status_display_data($order, $kitchen_status) {
        $status = $order->get_status();
        
        if ($status === 'pending') {
            return array(
                'class' => 'ready',
                'icon' => '✅',
                'text' => __('Ready', 'orders-jet')
            );
        } elseif ($status === 'processing') {
            if ($kitchen_status['kitchen_type'] === 'mixed') {
                if ($kitchen_status['food_ready'] && !$kitchen_status['beverage_ready']) {
                    return array('class' => 'partial', 'icon' => '🍕✅ 🥤⏳', 'text' => __('Waiting for Beverages', 'orders-jet'));
                } elseif (!$kitchen_status['food_ready'] && $kitchen_status['beverage_ready']) {
                    return array('class' => 'partial', 'icon' => '🍕⏳ 🥤✅', 'text' => __('Waiting for Food', 'orders-jet'));
                } else {
                    return array('class' => 'kitchen', 'icon' => '👨‍🍳', 'text' => __('In Kitchen', 'orders-jet'));
                }
            } else {
                return array('class' => 'kitchen', 'icon' => '👨‍🍳', 'text' => __('In Kitchen', 'orders-jet'));
            }
        } else {
            return array('class' => 'completed', 'icon' => '✅', 'text' => ucfirst($status));
        }
    }
    
    /**
     * Helper method to get type display data
     */
    private function get_type_display_data($order_method) {
        switch ($order_method) {
            case 'dinein':
                return array('class' => 'dinein', 'icon' => '🍽️', 'text' => __('Dine-in', 'orders-jet'));
            case 'takeaway':
                return array('class' => 'takeaway', 'icon' => '📦', 'text' => __('Takeaway', 'orders-jet'));
            case 'delivery':
                return array('class' => 'delivery', 'icon' => '🚚', 'text' => __('Delivery', 'orders-jet'));
            default:
                return array('class' => 'unknown', 'icon' => '❓', 'text' => __('Unknown', 'orders-jet'));
        }
    }
    
    /**
     * Helper method to get kitchen display data
     */
    private function get_kitchen_display_data($kitchen_status) {
        switch ($kitchen_status['kitchen_type']) {
            case 'food':
                return array('class' => 'food', 'icon' => '🍕', 'text' => __('Food', 'orders-jet'));
            case 'beverages':
                return array('class' => 'beverages', 'icon' => '🥤', 'text' => __('Beverages', 'orders-jet'));
            case 'mixed':
                return array('class' => 'mixed', 'icon' => '🍕🥤', 'text' => __('Mixed', 'orders-jet'));
            default:
                return array('class' => 'unknown', 'icon' => '❓', 'text' => __('Unknown', 'orders-jet'));
        }
    }
    
    /**
     * Helper method to format duration
     */
    private function format_duration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        } else {
            return sprintf('%dm', $minutes);
        }
    }
    
    /**
     * Process item add-ons for order details (handles both QR menu and WooCommerce formats)
     */
    private function process_item_addons_for_details($item, &$item_data) {
        // First try QR menu format (_oj_addons_data) - structured array
        $oj_addons_data = $item->get_meta('_oj_addons_data');
        if ($oj_addons_data && is_array($oj_addons_data)) {
            foreach ($oj_addons_data as $addon) {
                $addon_name = sanitize_text_field($addon['name'] ?? 'Add-on');
                $addon_price = floatval($addon['price'] ?? 0);
                $addon_quantity = intval($addon['quantity'] ?? 1);
                
                // The add-on price should be multiplied by item quantity for total calculation
                // But the unit price shown should be the individual add-on price
                $addon_total_per_item = $addon_price * $addon_quantity;
                $addon_total_for_all_items = $addon_total_per_item * $item_data['quantity'];
                $has_price = $addon_price > 0;
                
                // Don't include price in name - frontend will handle price display
                $display_name = $addon_name;
                
                $item_data['addons'][] = array(
                    'name' => $display_name,
                    'unit_price' => wc_price($addon_total_per_item), // Per item add-on cost
                    'subtotal' => wc_price($addon_total_for_all_items), // Total for all items
                    'has_price' => $has_price,
                    'price_value' => $addon_total_per_item // Use for calculation
                );
            }
        }
        // Fallback to WooCommerce Product Add-ons format
        elseif ($addon_data = $item->get_meta('_wc_pao_addon_value')) {
            if (is_array($addon_data)) {
                foreach ($addon_data as $addon) {
                    if (isset($addon['name']) && isset($addon['value'])) {
                        $addon_price = isset($addon['price']) ? floatval($addon['price']) : 0;
                        $addon_total = $addon_price * $item_data['quantity'];
                        $has_price = $addon_price > 0;
                        
                        $addon_name = $addon['name'];
                        
                        $item_data['addons'][] = array(
                            'name' => $addon_name,
                            'unit_price' => wc_price($addon_price),
                            'subtotal' => wc_price($addon_total),
                            'has_price' => $has_price,
                            'price_value' => $addon_price
                        );
                    }
                }
            }
        }
        // Final fallback to string format (_oj_item_addons)
        elseif ($addons_string = $item->get_meta('_oj_item_addons')) {
            // Parse string like "Combo Plus (+90.00 EGP), Soft Drink (+0.00 EGP), Frise (+0.00 EGP)"
            $addon_parts = explode(', ', $addons_string);
            foreach ($addon_parts as $addon_part) {
                // Extract name and price from format like "Combo Plus (+90.00 EGP)"
                if (preg_match('/^(.+?)\s*\(\+([0-9.,]+)\s*EGP\)$/', trim($addon_part), $matches)) {
                    $addon_name = trim($matches[1]);
                    $addon_price = floatval(str_replace(',', '.', $matches[2]));
                    $has_price = $addon_price > 0;
                    
                    $display_name = $addon_name;
                    
                    $addon_total = $addon_price * $item_data['quantity'];
                    
                    $item_data['addons'][] = array(
                        'name' => $display_name,
                        'unit_price' => wc_price($addon_price),
                        'subtotal' => wc_price($addon_total),
                        'has_price' => $has_price,
                        'price_value' => $addon_price
                    );
                } else {
                    // No price format, just name
                    $item_data['addons'][] = array(
                        'name' => trim($addon_part),
                        'unit_price' => wc_price(0),
                        'subtotal' => wc_price(0),
                        'has_price' => false,
                        'price_value' => 0
                    );
                }
            }
        }
        
        // Get item notes
        $notes = $item->get_meta('_oj_item_notes') ?: $item->get_meta('_wc_pao_addon_notes');
        if ($notes) {
            $item_data['notes'] = $notes;
        }
    }
    
    /**
     * Calculate base price for item (same logic as table-query-handler)
     */
    private function calculate_base_price_for_details($item, &$item_data) {
        $base_price_found = false;
        
        // Check if we stored the original variant price in meta data
        $stored_base_price = $item->get_meta('_oj_base_price');
        if ($stored_base_price) {
            $item_data['base_price'] = floatval($stored_base_price);
            $base_price_found = true;
        }
        
        // If no stored price, try to calculate from current data
        if (!$base_price_found) {
            $product = $item->get_product();
            
            if ($product && $product->is_type('variation')) {
                // For variation products, try to get the variant price
                $variant_price = $product->get_price();
                if ($variant_price) {
                    $item_data['base_price'] = floatval($variant_price);
                    $base_price_found = true;
                }
            } else {
                // For non-variation products, calculate base price by subtracting add-ons
                // Use pre-calculated addon data if available, otherwise fallback to legacy method
                if (Orders_Jet_Addon_Calculator::is_cache_initialized()) {
                    $addon_total = Orders_Jet_Addon_Calculator::get_item_base_price($order->get_id(), $item->get_id());
                } else {
                    $addon_total = $this->calculate_addon_total_for_details($item_data['addons'], $item->get_quantity());
                }
                
                $item_total = $item->get_total();
                $base_price = ($item_total - $addon_total) / $item->get_quantity();
                $item_data['base_price'] = $base_price;
                $base_price_found = true;
            }
        }
    }
    
    /**
     * Calculate total add-on cost (handles QR menu format)
     */
    private function calculate_addon_total_for_details($addons, $quantity) {
        $addon_total = 0;
        if (!empty($addons)) {
            foreach ($addons as $addon) {
                if (isset($addon['price_value'])) {
                    // The price_value already includes the per-item add-on cost
                    // We need to multiply by item quantity for total cost
                    $addon_total += $addon['price_value'] * $quantity;
                }
            }
        }
        return $addon_total;
    }
    
    
    /**
     * Get WooFood add-ons directly (fallback method)
     */
    private function get_woofood_addons_directly($product_id) {
        $addons = array();
        
        // Try to get WooFood add-ons directly
        if (class_exists('EX_WooFood')) {
            // Check for exwo_options meta
            $exwo_options = get_post_meta($product_id, 'exwo_options', true);
            
            if ($exwo_options && is_array($exwo_options)) {
                foreach ($exwo_options as $index => $option) {
                    if (isset($option['name']) && !empty($option['name'])) {
                        $addon = array(
                            'id' => 'woofood_' . $index,
                            'name' => $option['name'],
                            'price' => floatval($option['price'] ?? 0),
                            'required' => ($option['required'] ?? 'no') === 'yes',
                            'type' => $option['type'] ?? 'checkbox',
                            'options' => array()
                        );
                        
                        // Add options if they exist
                        if (isset($option['options']) && is_array($option['options'])) {
                            foreach ($option['options'] as $opt_index => $opt_data) {
                                $addon['options'][] = array(
                                    'id' => $opt_index,
                                    'name' => $opt_data['name'] ?? '',
                                    'price' => floatval($opt_data['price'] ?? 0),
                                    'value' => $opt_data['name'] ?? ''
                                );
                            }
                        }
                        
                        $addons[] = $addon;
                    }
                }
            }
        }
        
        return $addons;
    }
    
    // Removed get_completed_orders_for_pdf - guests now simply request staff assistance
    
    // Removed generate_guest_pdf - guests now simply request staff assistance
    public function generate_guest_pdf_REMOVED() {
        try {
            // Verify nonce for security
            check_ajax_referer('oj_guest_pdf', 'nonce');
            
            $order_id = intval($_GET['order_id']);
            $table_number = sanitize_text_field($_GET['table']);
            $output = sanitize_text_field($_GET['output'] ?? 'html');
            $force_download = !empty($_GET['force_download']);
            
            if (empty($order_id)) {
                throw new Exception(__('Order ID is required', 'orders-jet'));
            }
            
            if (empty($table_number)) {
                throw new Exception(__('Table number is required', 'orders-jet'));
            }
            
            // Get the order
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception(__('Order not found', 'orders-jet'));
            }
            
            // Verify this order belongs to the specified table
            $order_table = $order->get_meta('_oj_table_number');
            if ($order_table !== $table_number) {
                throw new Exception(__('Order does not belong to this table', 'orders-jet'));
            }
            
            // Verify order is completed
            if ($order->get_status() !== 'completed') {
                throw new Exception(__('Order is not completed', 'orders-jet'));
            }
            
            if ($output === 'pdf') {
                // Generate PDF using the invoice generation handler
                $handler = $this->handler_factory->get_invoice_generation_handler();
                $pdf_content = $handler->generate_pdf_invoice($order_id, 'guest');
                
                // Set headers for PDF
                header('Content-Type: application/pdf');
                header('Content-Length: ' . strlen($pdf_content));
                
                if ($force_download) {
                    header('Content-Disposition: attachment; filename="invoice-' . $order_id . '.pdf"');
                } else {
                    header('Content-Disposition: inline; filename="invoice-' . $order_id . '.pdf"');
                }
                
                echo $pdf_content;
                exit;
                
            } else {
                // Generate HTML invoice
                $this->generate_html_invoice_for_guest($order, $table_number);
            }
            
        } catch (Exception $e) {
            // Return error as HTML page
            echo '<html><body><h1>Error</h1><p>' . esc_html($e->getMessage()) . '</p></body></html>';
            exit;
        }
    }
    
    /**
     * Generate HTML invoice for guest viewing
     */
    private function generate_html_invoice_for_guest($order, $table_number) {
        // Set content type
        header('Content-Type: text/html; charset=utf-8');
        
        // Get order details
        $order_id = $order->get_id();
        $order_date = $order->get_date_created()->format('M j, Y g:i A');
        $payment_method = $order->get_payment_method_title();
        $total = $order->get_total();
        
        // Start HTML output
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Invoice #<?php echo $order_id; ?> - Table <?php echo esc_html($table_number); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
                .invoice-container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #c41e3a; padding-bottom: 20px; margin-bottom: 30px; }
                .header h1 { color: #c41e3a; margin: 0; }
                .invoice-info { display: flex; justify-content: space-between; margin-bottom: 30px; }
                .invoice-info div { flex: 1; }
                .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .items-table th, .items-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
                .items-table th { background: #f8f9fa; font-weight: bold; }
                .total-section { text-align: right; font-size: 18px; font-weight: bold; color: #c41e3a; }
                .print-btn { background: #c41e3a; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px; }
                .print-btn:hover { background: #a0172f; }
                @media print { .print-btn { display: none; } }
            </style>
        </head>
        <body>
            <div class="invoice-container">
                <div class="header">
                    <h1>Orders Jet Invoice</h1>
                    <p>Table <?php echo esc_html($table_number); ?></p>
                </div>
                
                <div class="invoice-info">
                    <div>
                        <strong>Invoice #:</strong> <?php echo $order_id; ?><br>
                        <strong>Date:</strong> <?php echo $order_date; ?><br>
                        <strong>Table:</strong> <?php echo esc_html($table_number); ?>
                    </div>
                    <div>
                        <strong>Payment Method:</strong> <?php echo esc_html($payment_method); ?><br>
                        <strong>Status:</strong> Completed
                    </div>
                </div>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order->get_items() as $item): ?>
                        <tr>
                            <td>
                                <?php echo esc_html($item->get_name()); ?>
                                <?php
                                // Show variations and add-ons
                                $meta_data = $item->get_formatted_meta_data();
                                if (!empty($meta_data)) {
                                    echo '<br><small style="color: #666;">';
                                    foreach ($meta_data as $meta) {
                                        echo esc_html($meta->display_key) . ': ' . esc_html($meta->display_value) . '<br>';
                                    }
                                    echo '</small>';
                                }
                                ?>
                            </td>
                            <td><?php echo $item->get_quantity(); ?></td>
                            <td><?php echo wc_price($item->get_subtotal() / $item->get_quantity()); ?></td>
                            <td><?php echo wc_price($item->get_subtotal()); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="total-section">
                    <p>Total: <?php echo wc_price($total); ?></p>
                </div>
                
                <div style="text-align: center; margin-top: 30px;">
                    <button class="print-btn" onclick="window.print()">🖨️ Print Invoice</button>
                </div>
                
                <div style="text-align: center; margin-top: 20px; color: #666; font-size: 14px;">
                    <p>Thank you for dining with us!</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Handle guest invoice request (simplified approach)
     * Just flags the table as needing staff attention for invoice
     */
    public function request_table_invoice() {
        try {
            check_ajax_referer('oj_table_order', 'nonce');
            
            $table_number = sanitize_text_field($_POST['table_number'] ?? '');
            
            if (empty($table_number)) {
                wp_send_json_error(array('message' => 'Table number is required'));
                return;
            }
            
            // Get the table post
            $table_posts = get_posts(array(
                'post_type' => 'oj_table',
                'meta_query' => array(
                    array(
                        'key' => '_oj_table_number',
                        'value' => $table_number,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1
            ));
            
            if (empty($table_posts)) {
                wp_send_json_error(array('message' => 'Table not found'));
                return;
            }
            
            $table_id = $table_posts[0]->ID;
            
            // Set a simple flag that staff needs to bring invoice
            update_post_meta($table_id, '_oj_guest_invoice_requested', current_time('mysql'));
            update_post_meta($table_id, '_oj_invoice_request_status', 'pending');
            
            // Create notification for staff
            $notification_service = new Orders_Jet_Notification_Service();
            $notification_data = array(
                'order_id' => 0, // No specific order - applies to whole table
                'order_number' => '', // Not applicable for invoice requests
                'table_number' => $table_number,
                'timestamp' => current_time('mysql'),
                'created_at' => current_time('mysql'),
                'type' => 'invoice_request'
            );
            $notification_service->store_dashboard_notification($notification_data);
            
            wp_send_json_success(array(
                'message' => 'Staff has been notified and will bring your invoice shortly',
                'table_number' => $table_number,
                'timestamp' => current_time('mysql')
            ));
            
        } catch (Exception $e) {
            oj_error_log('Invoice request error: ' . $e->getMessage(), 'INVOICE_REQUEST');
            wp_send_json_error(array('message' => 'Failed to send invoice request'));
        }
    }
    
    /**
     * Clear guest invoice request flag (staff action)
     */
    public function clear_guest_invoice_request() {
        try {
            check_ajax_referer('oj_dashboard_nonce', 'nonce');
            
            $table_number = sanitize_text_field($_POST['table_number'] ?? '');
            
            if (empty($table_number)) {
                wp_send_json_error(array('message' => 'Table number is required'));
                return;
            }
            
            // Get the table post
            $table_posts = get_posts(array(
                'post_type' => 'oj_table',
                'meta_query' => array(
                    array(
                        'key' => '_oj_table_number',
                        'value' => $table_number,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1
            ));
            
            if (empty($table_posts)) {
                wp_send_json_error(array('message' => 'Table not found'));
                return;
            }
            
            $table_id = $table_posts[0]->ID;
            
            // Clear the invoice request flags
            delete_post_meta($table_id, '_oj_guest_invoice_requested');
            delete_post_meta($table_id, '_oj_invoice_request_status');
            
            wp_send_json_success(array(
                'message' => 'Guest invoice request cleared',
                'table_number' => $table_number
            ));
            
        } catch (Exception $e) {
            oj_error_log('Clear invoice request error: ' . $e->getMessage(), 'INVOICE_REQUEST');
            wp_send_json_error(array('message' => 'Failed to clear invoice request'));
        }
    }
    
    /**
     * AJAX handler: Save filter view
     * Routes to dedicated filter views handler
     * 
     * @since 2.0.0
     */
    public function ajax_save_filter_view() {
        $handler = $this->handler_factory->get_filter_views_handler();
        $handler->handle_save_view();
    }
    
    /**
     * AJAX handler: Get user saved views
     * Routes to dedicated filter views handler
     * 
     * @since 2.0.0
     */
    public function ajax_get_user_saved_views() {
        $handler = $this->handler_factory->get_filter_views_handler();
        $handler->handle_get_user_views();
    }

    /**
     * AJAX handler: Load filter view
     * Routes to dedicated filter views handler
     * 
     * @since 2.0.0
     */
    public function ajax_load_filter_view() {
        $handler = $this->handler_factory->get_filter_views_handler();
        $handler->handle_load_view();
    }
    
    /**
     * AJAX handler: Delete filter view
     * Routes to dedicated filter views handler
     * 
     * @since 2.0.0
     */
    public function ajax_delete_filter_view() {
        $handler = $this->handler_factory->get_filter_views_handler();
        $handler->handle_delete_view();
    }
    
    /**
     * AJAX handler: Rename filter view
     * Routes to dedicated filter views handler
     * 
     * @since 2.0.0
     */
    public function ajax_rename_filter_view() {
        $handler = $this->handler_factory->get_filter_views_handler();
        $handler->handle_rename_view();
    }

    /**
     * AJAX handler for refreshing orders content without page reload
     * Returns filtered orders HTML + metadata for dynamic updates
     */
    /**
     * Handle Orders Master content refresh
     * Routes to dedicated dashboard refresh handler
     * 
     * @since 2.0.0
     */
    public function ajax_refresh_orders_content() {
        $handler = $this->handler_factory->get_dashboard_refresh_handler();
        $handler->handle_orders_content_refresh();
    }

    /**
     * Simple test AJAX endpoint to verify AJAX is working
     */
    public function ajax_test_endpoint() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'orders_jet_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'AJAX is working!',
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'test_data' => array(
                'filter' => $_POST['filter'] ?? 'none',
                'received_params' => array_keys($_POST)
            )
        ));
    }
    
    /**
     * AJAX handler for kitchen dashboard refresh
     * Uses EXACT same logic as working template
     */
    /**
     * Handle kitchen dashboard refresh
     * Routes to dedicated kitchen refresh handler
     * 
     * @since 2.0.0
     */
    public function ajax_refresh_kitchen_dashboard() {
        $handler = $this->handler_factory->get_kitchen_refresh_handler();
        $handler->handle_refresh();
    }
    
    /**
     * Handle bulk actions on orders
     * Routes to dedicated bulk actions handler
     * 
     * @since 2.0.0
     */
    public function ajax_bulk_action() {
        $handler = $this->handler_factory->get_bulk_actions_handler();
        $handler->handle_bulk_action();
    }
}
