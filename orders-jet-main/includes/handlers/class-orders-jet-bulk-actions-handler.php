<?php
declare(strict_types=1);
/**
 * Orders Jet - Bulk Actions Handler
 * Handles all bulk action AJAX operations for Orders Master
 * 
 * @package Orders_Jet
 * @version 2.0.0
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Bulk_Actions_Handler {
    
    /**
     * Handler factory instance for accessing other handlers
     * 
     * @var Orders_Jet_Handler_Factory
     */
    private $handler_factory;
    
    /**
     * Constructor
     * 
     * @param Orders_Jet_Handler_Factory $handler_factory Factory for accessing other handlers
     */
    public function __construct($handler_factory) {
        $this->handler_factory = $handler_factory;
    }
    
    /**
     * Handle bulk action AJAX request
     * 
     * Processes bulk actions on multiple orders:
     * - mark_ready: Changes orders from processing to pending
     * - complete: Completes orders (blocks table orders)
     * - cancel: Cancels orders
     * - close_table: Closes table with combined invoice
     * 
     * @since 2.0.0
     */
    public function handle_bulk_action() {
        
        // Verify nonce
        check_ajax_referer('oj_ajax_nonce', 'nonce');
        
        // Check user capabilities (Manager, Waiter, or Admin)
        if (!current_user_can('access_oj_manager_dashboard') 
            && !current_user_can('access_oj_waiter_dashboard')
            && !current_user_can('manage_options')) {
            oj_error_log('❌ BULK ACTION: Unauthorized access attempt', 'BULK_ACTION');
            wp_send_json_error(array('message' => __('Unauthorized access', 'orders-jet')));
            return;
        }
        
        // Sanitize and validate input
        $bulk_action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $order_ids = array_map('intval', $_POST['order_ids'] ?? array());
        
        
        if (empty($bulk_action) || empty($order_ids)) {
            oj_error_log('❌ BULK ACTION: Invalid request - missing action or order IDs', 'BULK_ACTION');
            wp_send_json_error(array('message' => __('Invalid request', 'orders-jet')));
            return;
        }
        
        try {
            $result = $this->process_bulk_action($bulk_action, $order_ids);
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => $result['message'],
                    'processed' => $result['processed']
                ));
            } else {
                oj_error_log('❌ BULK ACTION: Failed - ' . $result['message'], 'BULK_ACTION');
                wp_send_json_error(array('message' => $result['message']));
            }
            
        } catch (Exception $e) {
            oj_error_log('❌ BULK ACTION: Exception - ' . $e->getMessage(), 'BULK_ACTION');
            wp_send_json_error(array('message' => __('An error occurred while processing bulk action', 'orders-jet')));
        }
    }
    
    /**
     * Process bulk action on orders
     * 
     * @param string $action Bulk action to perform (mark_ready|complete|cancel|close_table)
     * @param array $order_ids Array of order IDs to process
     * @return array Result array with success, message, and processed count
     * @since 2.0.0
     */
    private function process_bulk_action($action, $order_ids) {
        $processed = 0;
        $failed = 0;
        $current_user = wp_get_current_user();
        
        
        // Action: Mark Ready (processing → pending)
        if ($action === 'mark_ready') {
            foreach ($order_ids as $order_id) {
                
                $order = wc_get_order($order_id);
                if (!$order) {
                    oj_error_log('❌ Order not found: ' . $order_id, 'BULK_ACTION');
                    $failed++;
                    continue;
                }
                
                $current_status = $order->get_status();
                
                if ($current_status === 'processing') {
                    $order->set_status('pending');
                    $order->add_order_note(sprintf(
                        __('Marked as ready via bulk action by %s', 'orders-jet'),
                        $current_user->display_name
                    ));
                    $order->save();
                    
                    $processed++;
                } else {
                    $failed++;
                }
            }
            
            $message = sprintf(__('%d order(s) marked as ready', 'orders-jet'), $processed);
            if ($failed > 0) {
                $message .= sprintf(__(' (%d skipped)', 'orders-jet'), $failed);
            }
            
            
            return array(
                'success' => true,
                'message' => $message,
                'processed' => $processed
            );
        }
        
        // Action: Complete Orders
        if ($action === 'complete') {
            foreach ($order_ids as $order_id) {
                
                $order = wc_get_order($order_id);
                if (!$order) {
                    oj_error_log('❌ Order not found: ' . $order_id, 'BULK_ACTION');
                    $failed++;
                    continue;
                }
                
                // CRITICAL: Prevent completing child table orders - they must be closed via "Close Table"
                $table_number = $order->get_meta('_oj_table_number');
                if (!empty($table_number)) {
                    $failed++;
                    continue;
                }
                
                $current_status = $order->get_status();
                
                // Can complete orders in pending, pending-payment, or processing status
                if (in_array($current_status, ['pending', 'pending-payment', 'processing'])) {
                    $order->set_status('completed');
                    $order->add_order_note(sprintf(
                        __('Completed via bulk action by %s', 'orders-jet'),
                        $current_user->display_name
                    ));
                    $order->save();
                    
                    $processed++;
                } else {
                    $failed++;
                }
            }
            
            $message = sprintf(__('%d order(s) completed', 'orders-jet'), $processed);
            if ($failed > 0) {
                $message .= sprintf(__(' (%d skipped - table orders must use "Close Table")', 'orders-jet'), $failed);
            }
            
            
            return array(
                'success' => true,
                'message' => $message,
                'processed' => $processed
            );
        }
        
        // Action: Cancel Orders
        if ($action === 'cancel') {
            foreach ($order_ids as $order_id) {
                
                $order = wc_get_order($order_id);
                if (!$order) {
                    oj_error_log('❌ Order not found: ' . $order_id, 'BULK_ACTION');
                    $failed++;
                    continue;
                }
                
                $current_status = $order->get_status();
                
                // Cannot cancel already completed, cancelled, or refunded orders
                if (!in_array($current_status, ['completed', 'cancelled', 'refunded'])) {
                    $order->set_status('cancelled');
                    $order->add_order_note(sprintf(
                        __('Cancelled via bulk action by %s', 'orders-jet'),
                        $current_user->display_name
                    ));
                    $order->save();
                    
                    $processed++;
                } else {
                    $failed++;
                }
            }
            
            $message = sprintf(__('%d order(s) cancelled', 'orders-jet'), $processed);
            if ($failed > 0) {
                $message .= sprintf(__(' (%d skipped)', 'orders-jet'), $failed);
            }
            
            
            return array(
                'success' => true,
                'message' => $message,
                'processed' => $processed
            );
        }
        
        // Action: Close Table (uses proper table closure handler)
        if ($action === 'close_table') {
            // Get table number from first order
            $first_order = wc_get_order($order_ids[0]);
            if (!$first_order) {
                oj_error_log('❌ First order not found', 'BULK_ACTION');
                return array('success' => false, 'message' => __('Invalid order', 'orders-jet'));
            }
            
            $table_number = $first_order->get_meta('_oj_table_number');
            if (empty($table_number)) {
                oj_error_log('❌ No table number found', 'BULK_ACTION');
                return array('success' => false, 'message' => __('No table number found', 'orders-jet'));
            }
            
            
            try {
                // Use the proper table closure handler
                $handler = $this->handler_factory->get_table_closure_handler();
                
                // Prepare post data for the handler
                $post_data = array(
                    'table_number' => $table_number,
                    'payment_method' => 'bulk_action', // Mark as bulk action
                    'guest_invoice_requested' => false
                );
                
                // Process the closure (marks ready, creates combined order, deletes child orders)
                $result = $handler->process_closure($post_data);
                
                $processed = count($order_ids);
                $message = sprintf(__('Table %s closed successfully. Combined order #%s created.', 'orders-jet'), 
                    $table_number, 
                    $result['order_number']
                );
                
                
                return array(
                    'success' => true,
                    'message' => $message,
                    'processed' => $processed
                );
                
            } catch (Exception $e) {
                oj_error_log('❌ Failed to close table: ' . $e->getMessage(), 'BULK_ACTION');
                return array(
                    'success' => false, 
                    'message' => __('Failed to close table: ', 'orders-jet') . $e->getMessage()
                );
            }
        }
        
        // Unknown action
        oj_error_log('❌ Unknown action: ' . $action, 'BULK_ACTION');
        return array(
            'success' => false,
            'message' => __('Unknown action', 'orders-jet')
        );
    }
}

