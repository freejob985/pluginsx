<?php
declare(strict_types=1);
/**
 * Orders Jet - Order Submission Handler Class
 * Handles complex order submission logic extracted from AJAX handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Order_Submission_Handler {
    
    private $tax_service;
    private $notification_service;
    
    public function __construct($tax_service, $notification_service) {
        $this->tax_service = $tax_service;
        $this->notification_service = $notification_service;
    }
    
    /**
     * Process table order submission
     * 
     * @param array $post_data The $_POST data from AJAX request
     * @return array Success response data
     * @throws Exception On processing errors
     */
    public function process_submission($post_data) {
        // REMOVED: Error reporting configuration should be handled by WordPress/server config
        // Removed error_reporting(E_ALL) and ini_set('display_errors', 0) for production
        
        // Log the incoming request (debug mode only)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oj_debug_log('ORDER SUBMISSION START', 'ORDER_SUBMIT', array(
                'user_logged_in' => is_user_logged_in(),
                'user_id' => get_current_user_id(),
                'post_data_keys' => array_keys($post_data)
            ));
        }
        
        // Parse and validate input data
        $order_data = $this->parse_order_data($post_data);
        
        // Create WooCommerce order
        $order = $this->create_woocommerce_order($order_data);
        
        // Add items to order
        $this->add_items_to_order($order, $order_data['items']);
        
        // Set order metadata and customer info
        $this->set_order_metadata($order, $order_data);
        
        // Handle tax calculation
        $final_total = $this->handle_tax_calculation($order, $order_data);
        
        // Update table status
        $this->update_table_status($order_data['table_number'], $order_data['table_id']);
        
        // Send notifications
        $this->send_notifications($order);
        
        // Verify order was saved correctly
        $this->verify_order_saved($order);
        
        oj_debug_log('ORDER SUBMISSION COMPLETE', 'ORDER_SUBMIT');
        
        // Clear order history cache for this table to ensure fresh data
        oj_query_service()->clear_cache($order_data['table_number']);
        
        return array(
            'message' => __('Order placed successfully', 'orders-jet'),
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'total' => $final_total
        );
    }
    
    /**
     * Parse and validate order data from POST request
     */
    private function parse_order_data($post_data) {
        $table_id = 0; // Default value
        
        if (isset($post_data['order_data'])) {
            // New format - JSON data
            $order_data = json_decode(stripslashes($post_data['order_data']), true);
            $table_number = sanitize_text_field($order_data['table_number']);
            $items = $order_data['items'];
            $total = floatval($order_data['total']);
            
            oj_debug_log('Order data received', 'ORDER_SUBMIT', array(
                'total_from_frontend' => $total,
                'items_count' => count($items)
            ));
        } else {
            // Old format - individual fields (backward compatibility)
            $table_number = sanitize_text_field($post_data['table_number']);
            $table_id = intval($post_data['table_id'] ?? 0);
            $special_requests = sanitize_textarea_field($post_data['special_requests'] ?? '');
            $cart_items = $post_data['cart_items'] ?? array();
            
            // Convert old format to new format with backward compatibility
            $items = array();
            foreach ($cart_items as $item) {
                $converted_item = array(
                    'product_id' => intval($item['product_id'] ?? $item['id'] ?? 0),
                    'variation_id' => intval($item['variation_id'] ?? 0),
                    'name' => sanitize_text_field($item['name'] ?? ''),
                    'quantity' => intval($item['quantity'] ?? 1),
                    'notes' => sanitize_text_field($item['notes'] ?? ''),
                    'add_ons' => array()
                );
                
                // Handle add-ons (support both old 'addons' and new 'add_ons' format)
                if (!empty($item['add_ons'])) {
                    $converted_item['add_ons'] = $item['add_ons'];
                } elseif (!empty($item['addons'])) {
                    // Convert old addons format to new format
                    foreach ($item['addons'] as $addon) {
                        $converted_item['add_ons'][] = array(
                            'id' => $addon['id'] ?? uniqid(),
                            'name' => $addon['name'] ?? 'Add-on',
                            'price' => floatval($addon['price'] ?? 0),
                            'quantity' => intval($addon['quantity'] ?? 1)
                        );
                    }
                }
                
                // Handle old variations format (complex object structure)
                if (!empty($item['variations']) && $converted_item['variation_id'] == 0) {
                    foreach ($item['variations'] as $variation_data) {
                        if (isset($variation_data['variation_id']) && $variation_data['variation_id'] > 0) {
                            $converted_item['variation_id'] = intval($variation_data['variation_id']);
                            break;
                        }
                    }
                }
                
                $items[] = $converted_item;
            }
            
            $total = 0; // Will be calculated from items
        }
        
        // Get table ID from table number if needed
        if (empty($table_id) || $table_id == 0) {
            $table_id = oj_get_table_id_by_number($table_number);
            oj_debug_log("Retrieved table ID: {$table_id}", 'TABLE_LOOKUP');
        }
        
        // Validate required fields
        if (empty($table_number) || empty($items)) {
            throw new Exception(__('Table number and cart items are required', 'orders-jet'));
        }
        
        return array(
            'table_number' => $table_number,
            'table_id' => $table_id,
            'items' => $items,
            'total' => $total
        );
    }
    
    /**
     * Create WooCommerce order
     */
    private function create_woocommerce_order($order_data) {
        $order = wc_create_order();
        
        if (is_wp_error($order)) {
            oj_error_log('Failed to create WooCommerce order: ' . $order->get_error_message(), 'ORDER_CREATION');
            throw new Exception(__('Failed to create order: ' . $order->get_error_message(), 'orders-jet'));
        }
        
        if (!$order) {
            oj_error_log('Order creation returned null', 'ORDER_CREATION');
            throw new Exception(__('Failed to create order: Unknown error', 'orders-jet'));
        }
        
        return $order;
    }
    
    /**
     * Add items to the WooCommerce order
     */
    private function add_items_to_order($order, $items) {
        foreach ($items as $item) {
            $product_id = intval($item['product_id']);
            $variation_id = intval($item['variation_id'] ?? 0);
            $quantity = intval($item['quantity']);
            $notes = sanitize_text_field($item['notes'] ?? '');
            $add_ons = $item['add_ons'] ?? array();
            
            // Get product
            if ($variation_id > 0) {
                $product = wc_get_product($variation_id);
            } else {
                $product = wc_get_product($product_id);
            }
            
            if (!$product) {
                oj_error_log("Product not found for ID: {$product_id}", 'PRODUCT_LOOKUP');
                continue;
            }
            
            // Calculate price using WooCommerce native methods
            $base_price = $product->get_price();
            $addon_total = 0;
            
            // Calculate add-ons total
            if (!empty($add_ons)) {
                foreach ($add_ons as $addon) {
                    $addon_price = floatval($addon['price'] ?? 0);
                    $addon_quantity = intval($addon['quantity'] ?? 1);
                    $addon_total += $addon_price * $addon_quantity;
                }
            }
            
            $total_price = $base_price + $addon_total;
            
            // Add product to order using WooCommerce native method
            $totals_array = array(
                'subtotal' => $total_price * $quantity,
                'total' => $total_price * $quantity,
                'subtotal_tax' => 0,
                'total_tax' => 0
            );
            
            $item_id = $order->add_product($product, $quantity, array(
                'variation' => ($variation_id > 0) ? $product->get_variation_attributes() : array(),
                'totals' => $totals_array
            ));
            
            if ($item_id) {
                $this->add_item_metadata($order->get_item($item_id), $notes, $add_ons, $base_price);
            } else {
                oj_error_log("Failed to add product to order: {$product_id}", 'ORDER_ITEMS');
            }
        }
    }
    
    /**
     * Add metadata to order item
     */
    private function add_item_metadata($order_item, $notes, $add_ons, $base_price) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oj_debug_log("Added item: {$order_item->get_name()}", 'ORDER_ITEMS');
        }
        
        // Add item notes if any
        if (!empty($notes)) {
            $order_item->add_meta_data('_oj_item_notes', $notes);
        }
        
        // Store add-ons in WooCommerce-compatible format
        if (!empty($add_ons)) {
            $addon_names = array();
            foreach ($add_ons as $addon) {
                $addon_name = sanitize_text_field($addon['name'] ?? 'Add-on');
                $addon_price = floatval($addon['price'] ?? 0);
                $addon_quantity = intval($addon['quantity'] ?? 1);
                $addon_value = sanitize_text_field($addon['value'] ?? '');
                
                if ($addon_quantity > 1) {
                    $addon_names[] = $addon_name . ' × ' . $addon_quantity . ' (+' . wc_price($addon_price * $addon_quantity) . ')';
                } elseif (!empty($addon_value)) {
                    $addon_names[] = $addon_name . ': ' . $addon_value;
                } else {
                    $addon_names[] = $addon_name . ' (+' . wc_price($addon_price) . ')';
                }
            }
            
            $order_item->add_meta_data('_oj_item_addons', implode(', ', $addon_names));
            $order_item->add_meta_data('_oj_addons_data', $add_ons);
        }
        
        // Store base price for order history display
        $order_item->add_meta_data('_oj_base_price', $base_price);
        
        // Save item meta data
        $order_item->save();
    }
    
    /**
     * Set order metadata and customer information
     */
    private function set_order_metadata($order, $order_data) {
        $table_number = $order_data['table_number'];
        $table_id = $order_data['table_id'];
        
        // Set order meta data (contactless - no customer details)
        $order->set_billing_first_name('Table ' . $table_number);
        $order->set_billing_last_name('Guest');
        $order->set_billing_phone('N/A');
        $order->set_billing_email('table' . $table_number . '@restaurant.local');
        
        // Check if this is the first order for this table in this session
        $is_new_session = $this->is_new_table_session($table_number);
        $session_id = $this->get_or_create_table_session($table_number);
        
        $order->update_meta_data('_oj_table_number', $table_number);
        $order->update_meta_data('_oj_table_id', $table_id ?? 0);
        $order->update_meta_data('_oj_order_method', 'dinein');
        $order->update_meta_data('_oj_contactless_order', 'yes');
        $order->update_meta_data('_oj_order_total', $order_data['total']);
        $order->update_meta_data('_oj_order_timestamp', current_time('mysql'));
        $order->update_meta_data('_oj_session_id', $session_id);
        $order->update_meta_data('_oj_session_start', $is_new_session ? 'yes' : 'no');
        
        // CRITICAL: Set waiter assignment during order creation
        $assigned_waiter = $this->get_table_assigned_waiter($table_number, $table_id);
        if ($assigned_waiter) {
            $order->update_meta_data('_oj_assigned_waiter', $assigned_waiter);
            oj_debug_log("Order assigned to waiter: {$assigned_waiter}", 'ORDER_SUBMIT');
        } else {
            oj_debug_log("No waiter assignment found for table {$table_number}", 'ORDER_SUBMIT');
        }
        
        // Set WooFood compatible order method meta
        $order->update_meta_data('exwf_odmethod', 'dinein');
        $order->update_meta_data('_oj_order_type', 'dine_in');
        
        // Set order status
        $order->set_status('processing');
        
        // Save order first to ensure all items are saved
        $order_id = $order->save();
        oj_debug_log("Order saved with ID: {$order_id}", 'ORDER_SAVE');
        
        // Trigger WooFood integration for dine-in order
        if (class_exists('Orders_Jet_WooFood_Integration')) {
            do_action('exwf_order_created', $order_id, 'dine_in');
        }
    }
    
    /**
     * Handle tax calculation for the order
     */
    private function handle_tax_calculation($order, $order_data) {
        $table_number = $order_data['table_number'];
        $total = $order_data['total'];
        
        // Calculate the actual sum of line items to verify
        $calculated_total = 0;
        foreach ($order->get_items() as $item) {
            $line_total = $item->get_total();
            $calculated_total += $line_total;
            oj_debug_log("Line item: {$item->get_name()}, total: {$line_total}", 'TOTAL_CALC');
        }
        oj_debug_log("Calculated total from line items: {$calculated_total}", 'TOTAL_CALC');
        
        // Use the calculated total if it's correct, otherwise use the frontend total
        $final_total = ($calculated_total > 0) ? $calculated_total : $total;
        
        // Set order subtotal and let WooCommerce calculate taxes naturally
        $order->update_meta_data('_oj_original_total', $final_total);
        
        // Set basic order data without forcing tax to zero
        $order->update_meta_data('_order_shipping', 0);
        $order->update_meta_data('_order_shipping_tax', 0);
        $order->update_meta_data('_order_discount', 0);
        $order->update_meta_data('_order_discount_tax', 0);
        
        // For table orders, don't calculate taxes (they will be calculated on consolidated order)
        if (!empty($table_number)) {
            // Table order - set totals manually without tax calculation
            $order->set_total($final_total);
            $order->update_meta_data('_order_tax', 0);
            $order->update_meta_data('_order_total_tax', 0);
            $order->update_meta_data('_oj_tax_deferred', 'yes'); // Mark that tax will be calculated later
            oj_debug_log("Table order #{$order->get_id()} - Tax calculation skipped (deferred to consolidation)", 'TAX_CALC');
        } else {
            // Pickup order - calculate taxes normally
            $order->calculate_totals();
            oj_debug_log("Pickup order #{$order->get_id()} - Tax calculated normally", 'TAX_CALC');
        }
        
        // Save order with WooCommerce-calculated totals
        $order->save();
        
        // Log final totals with tax information
        oj_debug_log("Final order totals", 'ORDER_TOTALS', array(
            'order_id' => $order->get_id(),
            'subtotal' => $order->get_subtotal(),
            'tax' => $order->get_total_tax(),
            'total' => $order->get_total(),
            'order_type' => !empty($table_number) ? 'Table Order (Tax Deferred)' : 'Pickup Order (Tax Calculated)'
        ));
        
        // SAFEGUARD: Validate tax isolation
        $expected_behavior = !empty($table_number) ? 'deferred' : 'calculated';
        $this->tax_service->validate_tax_isolation($order, $expected_behavior);
        
        return $final_total;
    }
    
    /**
     * Update table status to occupied
     */
    private function update_table_status($table_number, $table_id) {
        if ($table_id > 0) {
            update_post_meta($table_id, '_oj_table_status', 'occupied');
            oj_debug_log("Table {$table_number} (ID: {$table_id}) status updated to occupied", 'TABLE_STATUS');
        } else {
            // If we still don't have table_id, try to get it again and create if needed
            oj_error_log("Table ID still 0, attempting to find/create table: {$table_number}", 'TABLE_LOOKUP');
            $table_id = oj_get_table_id_by_number($table_number);
            if ($table_id > 0) {
                update_post_meta($table_id, '_oj_table_status', 'occupied');
                oj_debug_log("Table {$table_number} (ID: {$table_id}) status updated to occupied (second attempt)", 'TABLE_STATUS');
            } else {
                oj_error_log("Could not find table with number: {$table_number}", 'TABLE_LOOKUP');
            }
        }
    }
    
    /**
     * Get waiter assigned to table
     * 
     * @param string $table_number Table number
     * @param int $table_id Table post ID
     * @return int|null Waiter user ID or null
     */
    private function get_table_assigned_waiter($table_number, $table_id) {
        oj_debug_log("WAITER LOOKUP START - Table: {$table_number}, Table ID: {$table_id}", 'ORDER_SUBMIT');
        
        // Try using table_id first (more efficient)
        if ($table_id > 0) {
            $assigned_waiter = get_post_meta($table_id, WooJet_Meta_Keys::ASSIGNED_WAITER, true);
            oj_debug_log("Table ID {$table_id} meta lookup result: " . var_export($assigned_waiter, true), 'ORDER_SUBMIT');
            
            if ($assigned_waiter) {
                oj_debug_log("✅ Waiter assignment found via table ID {$table_id}: {$assigned_waiter}", 'ORDER_SUBMIT');
                return intval($assigned_waiter);
            }
        }
        
        // Fallback: Find table by number using meta field (not post_title)
        global $wpdb;
        oj_debug_log("Fallback: Looking up table by number '{$table_number}' using meta field", 'ORDER_SUBMIT');
        
        $table_post = $wpdb->get_row($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'oj_table' 
             AND p.post_status = 'publish'
             AND pm.meta_key = %s
             AND pm.meta_value = %s",
            WooJet_Meta_Keys::TABLE_POST_NUMBER,
            $table_number
        ));
        
        if ($table_post) {
            oj_debug_log("Found table post ID: {$table_post->ID} for table number '{$table_number}'", 'ORDER_SUBMIT');
            
            $assigned_waiter = get_post_meta($table_post->ID, WooJet_Meta_Keys::ASSIGNED_WAITER, true);
            oj_debug_log("Table post {$table_post->ID} meta lookup result: " . var_export($assigned_waiter, true), 'ORDER_SUBMIT');
            
            if ($assigned_waiter) {
                oj_debug_log("✅ Waiter assignment found via table lookup for {$table_number}: {$assigned_waiter}", 'ORDER_SUBMIT');
                return intval($assigned_waiter);
            }
        } else {
            oj_debug_log("❌ No table post found for table number '{$table_number}'", 'ORDER_SUBMIT');
        }
        
        oj_debug_log("❌ No waiter assignment found for table {$table_number} (ID: {$table_id})", 'ORDER_SUBMIT');
        return null;
    }
    
    /**
     * Send notifications to staff
     */
    private function send_notifications($order) {
        $this->notification_service->send_order_notification($order);
        
        // Clear any WooCommerce cache for this order
        wp_cache_delete($order->get_id(), 'posts');
        wp_cache_delete($order->get_id(), 'post_meta');
    }
    
    /**
     * Verify order was saved correctly
     */
    private function verify_order_saved($order) {
        // Final verification - check if order was actually saved
        oj_debug_log('ORDER SAVED VERIFICATION', 'ORDER_VERIFY', array(
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'total' => $order->get_total(),
            'table_number' => $order->get_meta('_oj_table_number'),
            'table_id' => $order->get_meta('_oj_table_id'),
            'order_method' => $order->get_meta('_oj_order_method'),
            'contactless' => $order->get_meta('_oj_contactless_order')
        ));
        
        // Verify order exists in database
        $saved_order = wc_get_order($order->get_id());
        if ($saved_order) {
            oj_debug_log('Order verification successful', 'ORDER_VERIFY', array(
                'saved_total' => $saved_order->get_total(),
                'saved_table_number' => $saved_order->get_meta('_oj_table_number')
            ));
        } else {
            oj_error_log('Order verification failed - order NOT found in database', 'ORDER_VERIFY');
            throw new Exception(__('Order verification failed - order not found in database', 'orders-jet'));
        }
    }
    
    /**
     * Check if this is a new session for the table
     * Note: This method needs to be implemented or moved from the main AJAX class
     */
    private function is_new_table_session($table_number) {
        // Check if there are any recent pending/processing orders for this table
        $recent_orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array('wc-processing', 'wc-pending'),
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
            'posts_per_page' => 1
        ));
        
        return empty($recent_orders);
    }
    
    /**
     * Get or create table session ID
     * Note: This method needs to be implemented or moved from the main AJAX class
     */
    private function get_or_create_table_session($table_number) {
        // Check for existing session
        $existing_session = get_transient('oj_table_session_' . $table_number);
        
        if ($existing_session) {
            return $existing_session;
        }
        
        // Create new session
        $session_id = 'session_' . $table_number . '_' . time();
        set_transient('oj_table_session_' . $table_number, $session_id, 4 * HOUR_IN_SECONDS);
        
        return $session_id;
    }
}
