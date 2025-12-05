<?php
declare(strict_types=1);
/**
 * Orders Jet - Table Closure Handler Class
 * Handles complex table closure logic extracted from AJAX handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Table_Closure_Handler {
    
    private $tax_service;
    private $kitchen_service;
    private $notification_service;
    
    public function __construct($tax_service, $kitchen_service, $notification_service = null) {
        $this->tax_service = $tax_service;
        $this->kitchen_service = $kitchen_service;
        $this->notification_service = $notification_service ?: new Orders_Jet_Notification_Service();
    }
    
    /**
     * Process table closure and consolidation
     * 
     * @param array $post_data The $_POST data from AJAX request
     * @return array Success response data
     * @throws Exception On processing errors
     */
    public function process_closure($post_data) {
        $table_number = sanitize_text_field($post_data['table_number']);
        $payment_method = sanitize_text_field($post_data['payment_method']);
        
        if (empty($table_number)) {
            throw new Exception(__('Table number is required', 'orders-jet'));
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oj_debug_log('TABLE GROUP CLOSURE START', 'TABLE_CLOSURE', array(
                'table_number' => $table_number,
                'payment_method' => $payment_method
            ));
        }
        
        // 1. Get all table orders for this table
        $table_orders = $this->get_table_orders($table_number);
        
        // 2. Validate and handle order statuses
        $this->validate_and_handle_order_statuses($table_orders, $table_number, $post_data);
        
        // 3. Create consolidated order
        $consolidated_order = $this->create_consolidated_order($table_orders, $table_number, $payment_method);
        
        // 4. Clean up child orders and update table status
        $this->cleanup_after_consolidation($table_orders, $table_number, $consolidated_order, $payment_method);
        
        // 5. Generate response data
        return $this->generate_closure_response($consolidated_order, $table_number, $payment_method, $table_orders);
    }
    
    /**
     * Get all active orders for a table
     */
    private function get_table_orders($table_number) {
        // OPTIMIZED: Reasonable limit for table orders
        $table_orders = wc_get_orders(array(
            'status' => array('processing', 'pending'),
            'meta_key' => '_oj_table_number',
            'meta_value' => $table_number,
            'limit' => 50, // Reasonable limit for table orders
            'orderby' => 'date',
            'order' => 'ASC'
        ));
        
        if (empty($table_orders)) {
            throw new Exception(__('No active orders found for this table', 'orders-jet'));
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oj_debug_log("Found " . count($table_orders) . " orders for table {$table_number}", 'TABLE_CLOSURE');
        }
        
        return $table_orders;
    }
    
    /**
     * Validate order statuses and handle processing orders
     */
    private function validate_and_handle_order_statuses($table_orders, $table_number, $post_data) {
        // Analyze order statuses
        $processing_orders = array();
        $pending_orders = array();
        $other_orders = array();
        
        foreach ($table_orders as $order) {
            $status = $order->get_status();
            if ($status === 'processing') {
                $processing_orders[] = $order;
            } elseif ($status === 'pending') {
                $pending_orders[] = $order;
            } else {
                $other_orders[] = array('id' => $order->get_id(), 'status' => $status);
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oj_debug_log('Order status analysis', 'TABLE_CLOSURE', array(
                'processing' => count($processing_orders),
                'pending' => count($pending_orders),
                'other' => count($other_orders)
            ));
        }
        
        // Check for mixed orders that aren't fully ready (dual kitchen validation)
        $this->validate_kitchen_readiness($table_orders);
        
        // Handle processing orders with confirmation
        if (!empty($processing_orders)) {
            $this->handle_processing_orders($processing_orders, $post_data);
        }
        
        // Check for any remaining non-pending orders
        $this->validate_final_order_statuses($table_orders);
    }
    
    /**
     * Validate kitchen readiness for mixed orders
     */
    private function validate_kitchen_readiness($table_orders) {
        $kitchen_blocking_orders = array();
        
        foreach ($table_orders as $order) {
            $kitchen_type = $order->get_meta('_oj_kitchen_type');
            if (empty($kitchen_type)) {
                $kitchen_type = $this->kitchen_service->get_order_kitchen_type($order);
                $order->update_meta_data('_oj_kitchen_type', $kitchen_type);
                $order->save();
            }
            
            if ($kitchen_type === 'mixed' && $order->get_status() === 'processing') {
                $food_ready = $order->get_meta('_oj_food_kitchen_ready') === 'yes';
                $beverage_ready = $order->get_meta('_oj_beverage_kitchen_ready') === 'yes';
                
                if (!$food_ready || !$beverage_ready) {
                    $pending_kitchens = array();
                    if (!$food_ready) $pending_kitchens[] = __('Food Kitchen', 'orders-jet');
                    if (!$beverage_ready) $pending_kitchens[] = __('Beverage Kitchen', 'orders-jet');
                    
                    $kitchen_blocking_orders[] = array(
                        'id' => $order->get_id(),
                        'pending_kitchens' => $pending_kitchens
                    );
                }
            }
        }
        
        // Block table closure if mixed orders aren't fully ready
        if (!empty($kitchen_blocking_orders)) {
            $error_messages = array();
            foreach ($kitchen_blocking_orders as $blocking_order) {
                $error_messages[] = sprintf(
                    __('Order #%d is waiting for: %s', 'orders-jet'),
                    $blocking_order['id'],
                    implode(', ', $blocking_order['pending_kitchens'])
                );
            }
            
            throw new Exception(__('Cannot close table. Some mixed orders are not fully ready:', 'orders-jet') . "\n\n" . implode("\n", $error_messages));
        }
    }
    
    /**
     * Handle processing orders with confirmation logic
     */
    private function handle_processing_orders($processing_orders, $post_data) {
        $force_close = isset($post_data['force_close']) && $post_data['force_close'] === 'true';
        
        if (!$force_close) {
            // First request - ask for confirmation
            $processing_order_numbers = array_map(function($order) {
                return '#' . $order->get_id();
            }, $processing_orders);
            
            $message = sprintf(__('There are %d processing orders in this table (%s) that are not ready yet. Closing the table will automatically mark them as ready. Are you sure you want to continue?', 'orders-jet'), 
                count($processing_orders),
                implode(', ', $processing_order_numbers));
            
            // This will be caught by the AJAX handler and converted to wp_send_json_error
            throw new Exception($message);
        } else {
            // User confirmed - auto-mark processing orders as ready
            $this->auto_mark_orders_ready($processing_orders);
        }
    }
    
    /**
     * Auto-mark processing orders as ready
     */
    private function auto_mark_orders_ready($processing_orders) {
        foreach ($processing_orders as $order) {
            $kitchen_type = $order->get_meta('_oj_kitchen_type');
            if ($kitchen_type === 'mixed') {
                // For mixed orders, mark both kitchens as ready
                $order->update_meta_data('_oj_food_kitchen_ready', 'yes');
                $order->update_meta_data('_oj_beverage_kitchen_ready', 'yes');
            } elseif ($kitchen_type === 'food') {
                $order->update_meta_data('_oj_food_kitchen_ready', 'yes');
            } else {
                $order->update_meta_data('_oj_beverage_kitchen_ready', 'yes');
            }
            
            $order->set_status('pending');
            $order->add_order_note(__('Automatically marked as ready during table closure', 'orders-jet'));
            $order->save();
            oj_debug_log("Auto-marked order #{$order->get_id()} as ready", 'ORDER_STATUS');
        }
        
        // CRITICAL: Clear Orders Master cache after status changes
        if (!empty($processing_orders)) {
            $this->clear_orders_master_cache();
        }
        
        oj_debug_log("Auto-marked " . count($processing_orders) . " processing orders as ready", 'ORDER_STATUS');
    }
    
    /**
     * Clear Orders Master transient cache
     */
    private function clear_orders_master_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_oj_master_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_oj_master_%'");
        
        // Also clear Orders Master V2 filter counts cache
        delete_transient('oj_master_v2_filter_counts');
        
        oj_debug_log('Orders Master cache cleared after table closure', 'CACHE_CLEAR');
    }
    
    /**
     * Validate final order statuses before consolidation
     */
    private function validate_final_order_statuses($table_orders) {
        $non_pending_orders = array();
        foreach ($table_orders as $order) {
            if ($order->get_status() !== 'pending') {
                $non_pending_orders[] = '#' . $order->get_id() . ' (' . $order->get_status() . ')';
            }
        }
        
        if (!empty($non_pending_orders)) {
            throw new Exception(sprintf(__('Some orders have unexpected statuses and cannot be processed: %s', 'orders-jet'), 
                implode(', ', $non_pending_orders)));
        }
        
        oj_debug_log('All orders are ready, proceeding with consolidation', 'TABLE_CLOSURE');
    }
    
    /**
     * Create consolidated order from table orders
     */
    private function create_consolidated_order($table_orders, $table_number, $payment_method) {
        $consolidated_order = wc_create_order();
        
        if (is_wp_error($consolidated_order)) {
            oj_error_log('Failed to create consolidated order: ' . $consolidated_order->get_error_message(), 'ORDER_CREATION');
            throw new Exception(__('Failed to create consolidated order', 'orders-jet'));
        }
        
        // Add all items from child orders
        $total_items = 0;
        $child_order_ids = array();
        
        foreach ($table_orders as $child_order) {
            $child_order_ids[] = $child_order->get_id();
            
            foreach ($child_order->get_items() as $item) {
                $product = $item->get_product();
                if ($product) {
                    // Use the original subtotal from child order (without tax) for consolidated order
                    $consolidated_order->add_product(
                        $product,
                        $item->get_quantity(),
                        array(
                            'totals' => array(
                                'subtotal' => $item->get_subtotal(),
                                'total' => $item->get_subtotal(), // Use subtotal as total (no tax from child)
                            )
                        )
                    );
                    $total_items += $item->get_quantity();
                    
                    // Copy over any item meta data (notes, add-ons, etc.)
                    $this->copy_item_metadata($consolidated_order, $item);
                }
            }
        }
        
        oj_debug_log("Added {$total_items} items to consolidated order", 'ORDER_CONSOLIDATION');
        
        // Set consolidated order properties
        $this->set_consolidated_order_properties($consolidated_order, $table_number, $child_order_ids, $payment_method);
        
        // Calculate totals and complete the order
        $this->finalize_consolidated_order($consolidated_order, $table_number, $child_order_ids, $payment_method);
        
        return $consolidated_order;
    }
    
    /**
     * Copy item metadata from child order item to consolidated order
     */
    private function copy_item_metadata($consolidated_order, $source_item) {
        $new_items = $consolidated_order->get_items();
        $new_item = end($new_items); // Get the last added item
        
        if ($new_item) {
            // Copy item notes
            $notes = $source_item->get_meta('_oj_item_notes');
            if ($notes) {
                $new_item->add_meta_data('_oj_item_notes', $notes);
            }
            
            // Copy add-ons data
            $addons = $source_item->get_meta('_oj_item_addons');
            if ($addons) {
                $new_item->add_meta_data('_oj_item_addons', $addons);
            }
            
            $addons_data = $source_item->get_meta('_oj_addons_data');
            if ($addons_data) {
                $new_item->add_meta_data('_oj_addons_data', $addons_data);
            }
            
            $new_item->save();
        }
    }
    
    /**
     * Set consolidated order properties
     */
    private function set_consolidated_order_properties($consolidated_order, $table_number, $child_order_ids, $payment_method) {
        $consolidated_order->set_billing_first_name('Table ' . $table_number);
        $consolidated_order->set_billing_last_name('Combined Invoice');
        $consolidated_order->set_billing_phone('N/A');
        $consolidated_order->set_billing_email('table' . $table_number . '@restaurant.local');
        
        // Set consolidated order meta
        $consolidated_order->update_meta_data('_oj_table_number', $table_number);
        $consolidated_order->update_meta_data('_oj_consolidated_order', 'yes');
        $consolidated_order->update_meta_data('_oj_child_order_ids', $child_order_ids);
        $consolidated_order->update_meta_data('_oj_payment_method', $payment_method);
        $consolidated_order->update_meta_data('_oj_order_method', 'dinein');
        $consolidated_order->update_meta_data('_oj_table_closed', current_time('mysql'));
        
        // CRITICAL: Preserve waiter assignment in combined order
        $assigned_waiter = $this->determine_assigned_waiter($table_number, $child_order_ids);
        if ($assigned_waiter) {
            $consolidated_order->update_meta_data('_oj_assigned_waiter', $assigned_waiter);
            oj_debug_log("Combined order assigned to waiter: {$assigned_waiter}", 'TABLE_CLOSURE');
        } else {
            oj_debug_log("No waiter assignment found for combined order", 'TABLE_CLOSURE');
        }
    }
    
    /**
     * Determine the assigned waiter for the combined order
     * 
     * Priority: 1) Table assignment, 2) Child orders consensus, 3) Most recent child order
     * 
     * @param string $table_number Table number
     * @param array $child_order_ids Array of child order IDs
     * @return int|null Waiter user ID or null if no assignment
     */
    private function determine_assigned_waiter($table_number, $child_order_ids) {
        // Priority 1: Check table assignment (most authoritative)
        $table_waiter = $this->get_table_assigned_waiter($table_number);
        if ($table_waiter) {
            oj_debug_log("Waiter assignment from table: {$table_waiter}", 'TABLE_CLOSURE');
            return $table_waiter;
        }
        
        // Priority 2: Check child orders for waiter assignment
        $child_waiters = $this->get_child_orders_waiters($child_order_ids);
        
        if (!empty($child_waiters)) {
            // If all child orders have the same waiter, use that
            $unique_waiters = array_unique($child_waiters);
            if (count($unique_waiters) === 1) {
                $consensus_waiter = $unique_waiters[0];
                oj_debug_log("Waiter assignment from child orders consensus: {$consensus_waiter}", 'TABLE_CLOSURE');
                return $consensus_waiter;
            }
            
            // If multiple waiters, use the most common one
            $waiter_counts = array_count_values($child_waiters);
            arsort($waiter_counts);
            $most_common_waiter = key($waiter_counts);
            oj_debug_log("Waiter assignment from most common in child orders: {$most_common_waiter} (appeared {$waiter_counts[$most_common_waiter]} times)", 'TABLE_CLOSURE');
            return $most_common_waiter;
        }
        
        oj_debug_log("No waiter assignment found for table {$table_number}", 'TABLE_CLOSURE');
        return null;
    }
    
    /**
     * Get waiter assigned to table
     * 
     * @param string $table_number Table number
     * @return int|null Waiter user ID or null
     */
    private function get_table_assigned_waiter($table_number) {
        global $wpdb;
        
        // Find table post by title (table number)
        $table_post = $wpdb->get_row($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'oj_table' 
             AND post_title = %s 
             AND post_status = 'publish'",
            $table_number
        ));
        
        if ($table_post) {
            $assigned_waiter = get_post_meta($table_post->ID, '_oj_assigned_waiter', true);
            return $assigned_waiter ? intval($assigned_waiter) : null;
        }
        
        return null;
    }
    
    /**
     * Get waiters assigned to child orders
     * 
     * @param array $child_order_ids Array of child order IDs
     * @return array Array of waiter user IDs (may contain duplicates)
     */
    private function get_child_orders_waiters($child_order_ids) {
        $waiters = array();
        
        foreach ($child_order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $assigned_waiter = $order->get_meta('_oj_assigned_waiter');
                if ($assigned_waiter) {
                    $waiters[] = intval($assigned_waiter);
                }
            }
        }
        
        return $waiters;
    }
    
    /**
     * Finalize consolidated order with totals and completion
     */
    private function finalize_consolidated_order($consolidated_order, $table_number, $child_order_ids, $payment_method) {
        // Calculate totals efficiently
        if (wc_tax_enabled()) {
            $consolidated_order->calculate_totals();
        } else {
            // Skip tax calculation if taxes disabled
            $consolidated_order->set_total($consolidated_order->get_subtotal());
        }
        
        // Complete consolidated order using proper WooCommerce method
        $consolidated_order->set_status('completed');
        
        // Add completion note with waiter information
        $assigned_waiter = $consolidated_order->get_meta('_oj_assigned_waiter');
        $waiter_info = '';
        if ($assigned_waiter) {
            $waiter_user = get_userdata($assigned_waiter);
            $waiter_info = $waiter_user ? sprintf(' - Waiter: %s', $waiter_user->display_name) : '';
        }
        
        $consolidated_order->add_order_note(sprintf(
            __('Table %s closed - Consolidated order from %d child orders - Payment: %s%s (Subtotal: %s, Tax: %s, Total: %s)', 'orders-jet'),
            $table_number,
            count($child_order_ids),
            $payment_method,
            $waiter_info,
            wc_price($consolidated_order->get_subtotal()),
            wc_price($consolidated_order->get_total_tax()),
            wc_price($consolidated_order->get_total())
        ));
        
        $consolidated_order->save();
        
        // Send table closed notification
        $this->notification_service->store_dashboard_notification(array(
            'type' => 'table_closed',
            'order_id' => $consolidated_order->get_id(),
            'order_number' => $consolidated_order->get_order_number(),
            'table_number' => $table_number,
            'message' => sprintf(__('Table %s closed - %d orders consolidated', 'orders-jet'), $table_number, count($child_order_ids))
        ));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oj_debug_log('Consolidated order created and completed', 'ORDER_CONSOLIDATION', array(
                'order_id' => $consolidated_order->get_id(),
                'subtotal' => $consolidated_order->get_subtotal(),
                'tax' => $consolidated_order->get_total_tax(),
                'total' => $consolidated_order->get_total(),
                'child_orders_to_delete' => count($child_order_ids)
            ));
        }
        
        // Validate consolidated order tax calculation (debug only)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->tax_service->validate_tax_isolation($consolidated_order, 'consolidated');
        }
    }
    
    /**
     * Clean up after consolidation - delete child orders and update table status
     */
    private function cleanup_after_consolidation($table_orders, $table_number, $consolidated_order, $payment_method) {
        // Delete child orders efficiently
        foreach ($table_orders as $child_order) {
            $this->delete_child_order($child_order);
        }
        
        oj_debug_log('Child order deletion process completed', 'ORDER_CLEANUP');
        
        // Update table status to available
        $table_id = oj_get_table_id_by_number($table_number);
        if ($table_id) {
            update_post_meta($table_id, '_oj_table_status', 'available');
            oj_debug_log("Table {$table_number} (ID: {$table_id}) status updated to available", 'TABLE_STATUS');
        }
        
        // Unassign table from waiter
        $this->unassign_table_from_waiter($table_number);
        
        // Log table closure
        $child_order_ids = array_map(function($order) { return $order->get_id(); }, $table_orders);
        update_option('oj_table_closed_' . $table_number . '_' . time(), array(
            'table_number' => $table_number,
            'consolidated_order_id' => $consolidated_order->get_id(),
            'child_order_ids' => $child_order_ids,
            'closed_at' => current_time('mysql'),
            'payment_method' => $payment_method,
            'total_amount' => $consolidated_order->get_total()
        ));
    }
    
    /**
     * Delete a child order safely
     */
    private function delete_child_order($child_order) {
        $child_order_id = $child_order->get_id();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oj_debug_log("Attempting to delete child order #{$child_order_id}", 'ORDER_CLEANUP');
        }
        
        try {
            // Check if order exists before deletion
            $order_exists = wc_get_order($child_order_id);
            if (!$order_exists) {
                oj_debug_log("Child order #{$child_order_id} does not exist, skipping deletion", 'ORDER_CLEANUP');
                return;
            }
            
            // Use WooCommerce's native delete method (handles items, meta, and post deletion)
            $deletion_result = $child_order->delete(true); // Force delete permanently
            
            if ($deletion_result) {
                oj_debug_log("Child order #{$child_order_id} deleted successfully using WC native method", 'ORDER_CLEANUP');
            } else {
                oj_debug_log("WC delete method failed for order #{$child_order_id}, trying wp_delete_post", 'ORDER_CLEANUP');
                
                // Fallback to WordPress method
                $wp_result = wp_delete_post($child_order_id, true);
                if ($wp_result) {
                    oj_debug_log("Child order #{$child_order_id} deleted using wp_delete_post fallback", 'ORDER_CLEANUP');
                } else {
                    oj_error_log("Both deletion methods failed for order #{$child_order_id}", 'ORDER_CLEANUP');
                }
            }
            
            // Verify deletion
            $verification = wc_get_order($child_order_id);
            if (!$verification) {
                oj_debug_log("Deletion verified - Order #{$child_order_id} no longer exists", 'ORDER_CLEANUP');
            } else {
                oj_error_log("Deletion verification failed - Order #{$child_order_id} still exists", 'ORDER_CLEANUP');
            }
            
        } catch (Exception $e) {
            oj_error_log("Error deleting child order #{$child_order_id}: {$e->getMessage()}", 'ORDER_CLEANUP');
        }
    }
    
    /**
     * Generate closure response data
     */
    private function generate_closure_response($consolidated_order, $table_number, $payment_method, $table_orders) {
        $child_order_ids = array_map(function($order) { return $order->get_id(); }, $table_orders);
        
        // Generate invoice URL
        $invoice_url = add_query_arg(array(
            'order_id' => $consolidated_order->get_id(),
            'table' => $table_number,
            'payment_method' => $payment_method
        ), admin_url('admin.php?page=manager-invoice'));
        
        // Generate thermal invoice URL for consolidated order
        $thermal_invoice_url = add_query_arg(array(
            'action' => 'oj_get_order_invoice',
            'order_id' => $consolidated_order->get_id(),
            'print' => '1',
            'nonce' => wp_create_nonce('oj_get_invoice')
        ), admin_url('admin-ajax.php'));

        // Get combined order items for display
        $combined_items = array();
        foreach ($consolidated_order->get_items() as $item) {
            $combined_items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total()
            );
        }
        
        oj_debug_log('TABLE GROUP CLOSURE COMPLETE', 'TABLE_CLOSURE');
        
        return array(
            'message' => __('Table closed and invoice generated', 'orders-jet'),
            'consolidated_order_id' => $consolidated_order->get_id(),
            'subtotal' => $consolidated_order->get_subtotal(),
            'total_tax' => $consolidated_order->get_total_tax(),
            'grand_total' => $consolidated_order->get_total(),
            'payment_method' => $payment_method,
            'invoice_url' => $invoice_url,
            'thermal_invoice_url' => $thermal_invoice_url,
            'child_order_ids' => $child_order_ids,
            'tax_method' => 'consolidated_woocommerce',
            'combined_order' => array(
                'order_id' => $consolidated_order->get_id(),
                'order_number' => $consolidated_order->get_order_number(),
                'table_number' => $table_number,
                'total' => $consolidated_order->get_total(),
                'subtotal' => $consolidated_order->get_subtotal(),
                'tax' => $consolidated_order->get_total_tax(),
                'items' => $combined_items,
                'item_count' => count($combined_items),
                'date' => $consolidated_order->get_date_created()->date('g:i A'),
                'status' => 'completed',
                'order_type' => 'dinein',
                'invoice_url' => $thermal_invoice_url
            ),
            'card_updates' => array(
                'action' => 'replace_with_combined_order',
                'child_order_ids' => $child_order_ids,
                'table_number' => $table_number
            )
        );
    }
    
    /**
     * Unassign table from waiter when table is closed
     * 
     * @param string $table_number Table number to unassign
     */
    private function unassign_table_from_waiter($table_number) {
        // Get all users with waiter function
        $waiters = get_users(array(
            'meta_key' => '_oj_function',
            'meta_value' => 'waiter',
            'fields' => 'ID'
        ));
        
        $unassigned_from_waiter = null;
        
        // Find and remove table from assigned waiters
        foreach ($waiters as $waiter_id) {
            $assigned_tables = get_user_meta($waiter_id, '_oj_assigned_tables', true);
            
            if (is_array($assigned_tables) && in_array($table_number, $assigned_tables)) {
                // Remove table from assigned tables
                $assigned_tables = array_diff($assigned_tables, array($table_number));
                $assigned_tables = array_values($assigned_tables); // Re-index array
                
                // Update user meta
                update_user_meta($waiter_id, '_oj_assigned_tables', $assigned_tables);
                
                $unassigned_from_waiter = $waiter_id;
                
                break; // Table should only be assigned to one waiter
            }
        }
        
        if ($unassigned_from_waiter) {
            // Send notification about table becoming available
            $this->notification_service->store_dashboard_notification(array(
                'type' => 'table_available',
                'table_number' => $table_number,
                'message' => sprintf(__('Table %s is now available for assignment', 'orders-jet'), $table_number),
                'priority' => 'normal'
            ));
            
        }
    }
}
