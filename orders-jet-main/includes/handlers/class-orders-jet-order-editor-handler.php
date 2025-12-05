<?php
/**
 * Order Editor Handler
 * 
 * Handles all order editing AJAX operations:
 * - Add Notes
 * - Customer Info
 * - Refund (partial & full)
 * - Discount
 * - Add Items
 * - Status changes
 * 
 * @package Orders_Jet
 * @version 2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Order_Editor_Handler {
    
    /**
     * Kitchen service instance
     */
    private $kitchen_service;
    
    /**
     * Notification service instance
     */
    private $notification_service;
    
    /**
     * Tax service instance
     */
    private $tax_service;
    
    /**
     * Constructor
     */
    public function __construct($kitchen_service = null, $notification_service = null, $tax_service = null) {
        $this->kitchen_service = $kitchen_service;
        $this->notification_service = $notification_service;
        $this->tax_service = $tax_service;
        
        // Register AJAX endpoints
        $this->register_ajax_endpoints();
    }
    
    /**
     * Register AJAX endpoints
     */
    private function register_ajax_endpoints() {
        // Add Note
        add_action('wp_ajax_oj_add_order_note', array($this, 'ajax_add_note'));
        
        // Customer Info
        add_action('wp_ajax_oj_get_customer_info', array($this, 'ajax_get_customer_info'));
        add_action('wp_ajax_oj_update_customer_info', array($this, 'ajax_update_customer_info'));
        
        // Refund
        add_action('wp_ajax_oj_get_refund_data', array($this, 'ajax_get_refund_data'));
        add_action('wp_ajax_oj_refund_order', array($this, 'ajax_refund_order'));
        
        // Discount
        add_action('wp_ajax_oj_get_discount_data', array($this, 'ajax_get_discount_data'));
        add_action('wp_ajax_oj_apply_discount', array($this, 'ajax_apply_discount'));
        
        // Add Items
        add_action('wp_ajax_oj_add_order_items', array($this, 'ajax_add_items'));
        add_action('wp_ajax_oj_search_products', array($this, 'ajax_search_products'));
        
        // Status changes
        add_action('wp_ajax_oj_change_order_status', array($this, 'ajax_change_status'));
        
        // Order Actions (WooCommerce)
        add_action('wp_ajax_oj_execute_order_action', array($this, 'ajax_execute_order_action'));
        
        // Order Content (Phase 7)
        add_action('wp_ajax_oj_get_order_content', array($this, 'ajax_get_order_content'));
        add_action('wp_ajax_oj_save_order_content', array($this, 'ajax_save_order_content'));
        
        // Card Refresh (Phase 9)
        add_action('wp_ajax_oj_refresh_order_card', array($this, 'ajax_refresh_order_card'));
        
        // Coupons (New Feature)
        add_action('wp_ajax_oj_get_available_coupons', array($this, 'ajax_get_available_coupons'));
        add_action('wp_ajax_oj_get_order_coupons', array($this, 'ajax_get_order_coupons'));
        add_action('wp_ajax_oj_apply_coupon_to_order', array($this, 'ajax_apply_coupon_to_order'));
        add_action('wp_ajax_oj_remove_coupon_from_order', array($this, 'ajax_remove_coupon_from_order'));
        add_action('wp_ajax_oj_create_quick_coupon', array($this, 'ajax_create_quick_coupon'));
    }
    
    // ========================================================================
    // AJAX ENDPOINTS (To be implemented in phases)
    // ========================================================================
    
    /**
     * AJAX: Add note to order
     * Phase 2
     */
    public function ajax_add_note() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Add note: Nonce verification failed');
            wp_send_json_error(array(
                'message' => 'Security check failed'
            ));
        }
        
        // Check user capabilities
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Add note: User lacks permission');
            wp_send_json_error(array(
                'message' => 'You do not have permission to add notes'
            ));
        }
        
        // Get and validate inputs
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $note_type = isset($_POST['note_type']) ? sanitize_text_field($_POST['note_type']) : 'internal';
        $note_text = isset($_POST['note_text']) ? sanitize_textarea_field($_POST['note_text']) : '';
        
        // Validate order ID
        if (empty($order_id)) {
            oj_error_log('Add note: Missing order ID');
            wp_send_json_error(array(
                'message' => 'Invalid order ID'
            ));
        }
        
        // Validate note text
        if (empty($note_text)) {
            oj_error_log('Add note: Empty note text');
            wp_send_json_error(array(
                'message' => 'Note text is required'
            ));
        }
        
        // Validate note type
        if (!in_array($note_type, array('internal', 'customer'))) {
            $note_type = 'internal';
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Add note: Order not found - ID: ' . $order_id);
            wp_send_json_error(array(
                'message' => 'Order not found'
            ));
        }
        
        // Determine if note is for customer
        $is_customer_note = ($note_type === 'customer') ? 1 : 0;
        
        // Add note to order
        try {
            $note_id = $order->add_order_note(
                $note_text,
                $is_customer_note,
                true // Added by user (not system)
            );
            
            if ($note_id) {
                // Get current user info
                $current_user = wp_get_current_user();
                $user_name = $current_user->display_name;
                
                
                wp_send_json_success(array(
                    'message' => sprintf(
                        '%s note added successfully by %s',
                        ucfirst($note_type),
                        $user_name
                    ),
                    'note_id' => $note_id,
                    'note_type' => $note_type,
                    'order_id' => $order_id
                ));
            } else {
                throw new Exception('Failed to add note');
            }
            
        } catch (Exception $e) {
            oj_error_log('Add note: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to add note: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Get customer info
     * Phase 3
     */
    public function ajax_get_customer_info() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Get customer info: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Get customer info: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get order ID
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (empty($order_id)) {
            oj_error_log('Get customer info: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Get customer info: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        // Get customer data
        $customer_data = array(
            'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'phone' => $order->get_billing_phone(),
            'email' => $order->get_billing_email(),
            'notes' => $order->get_customer_note(),
            'is_table_child' => $order->get_parent_id() > 0
        );
        
        // Get shipping address
        $shipping_address = array(
            'address_1' => $order->get_shipping_address_1(),
            'address_2' => $order->get_shipping_address_2(),
            'city' => $order->get_shipping_city(),
            'state' => $order->get_shipping_state(),
            'postcode' => $order->get_shipping_postcode(),
            'country' => $order->get_shipping_country(),
            'google_maps_link' => $order->get_meta('_shipping_google_maps_link', true)
        );
        
        // Get billing address
        $billing_address = array(
            'address_1' => $order->get_billing_address_1(),
            'address_2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'postcode' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country(),
            'google_maps_link' => $order->get_meta('_billing_google_maps_link', true)
        );
        
        // Check if addresses are the same
        $same_as_billing = (
            $shipping_address['address_1'] === $billing_address['address_1'] &&
            $shipping_address['address_2'] === $billing_address['address_2'] &&
            $shipping_address['city'] === $billing_address['city'] &&
            $shipping_address['state'] === $billing_address['state'] &&
            $shipping_address['postcode'] === $billing_address['postcode'] &&
            $shipping_address['country'] === $billing_address['country']
        );
        
        // Get customer history stats (Phase 8 - Step 4)
        $customer_email = $order->get_billing_email();
        $customer_history = array(
            'total_orders' => 0,
            'total_revenue' => 0,
            'average_order_value' => 0,
            'first_order_date' => '',
            'last_order_date' => ''
        );
        
        if (!empty($customer_email)) {
            // Query all orders for this customer email
            $customer_orders = wc_get_orders(array(
                'billing_email' => $customer_email,
                'limit' => -1, // Get all orders
                'status' => array('wc-completed', 'wc-processing', 'wc-pending-payment')
            ));
            
            if (!empty($customer_orders)) {
                $total_revenue = 0;
                $order_dates = array();
                
                foreach ($customer_orders as $customer_order) {
                    $total_revenue += $customer_order->get_total();
                    $order_dates[] = $customer_order->get_date_created()->date('Y-m-d H:i:s');
                }
                
                $customer_history['total_orders'] = count($customer_orders);
                $customer_history['total_revenue'] = $total_revenue;
                $customer_history['average_order_value'] = $total_revenue / count($customer_orders);
                
                // Sort dates to get first and last
                sort($order_dates);
                $customer_history['first_order_date'] = reset($order_dates);
                $customer_history['last_order_date'] = end($order_dates);
            }
        }
        
        
        wp_send_json_success(array(
            'customer' => $customer_data,
            'shipping' => $shipping_address,
            'billing' => $billing_address,
            'same_as_billing' => $same_as_billing,
            'history' => $customer_history
        ));
    }
    
    /**
     * AJAX: Update customer info
     * Phase 3
     */
    public function ajax_update_customer_info() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Update customer info: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Update customer info: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate inputs
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $customer_name = isset($_POST['customer_name']) ? sanitize_text_field($_POST['customer_name']) : '';
        $customer_phone = isset($_POST['customer_phone']) ? sanitize_text_field($_POST['customer_phone']) : '';
        $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
        $customer_notes = isset($_POST['customer_notes']) ? sanitize_textarea_field($_POST['customer_notes']) : '';
        
        // Validate order ID
        if (empty($order_id)) {
            oj_error_log('Update customer info: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Validate customer name
        if (empty($customer_name)) {
            oj_error_log('Update customer info: Empty customer name');
            wp_send_json_error(array('message' => 'Customer name is required'));
        }
        
        // Validate email format if provided
        if (!empty($customer_email) && !is_email($customer_email)) {
            oj_error_log('Update customer info: Invalid email format');
            wp_send_json_error(array('message' => 'Invalid email format'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Update customer info: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            // Split name into first and last
            $name_parts = explode(' ', $customer_name, 2);
            $first_name = $name_parts[0];
            $last_name = isset($name_parts[1]) ? $name_parts[1] : '';
            
            // Update billing info
            $order->set_billing_first_name($first_name);
            $order->set_billing_last_name($last_name);
            $order->set_billing_phone($customer_phone);
            $order->set_billing_email($customer_email);
            $order->set_customer_note($customer_notes);
            
            // Update shipping address if provided (Phase 8)
            if (isset($_POST['shipping']) && is_array($_POST['shipping'])) {
                $shipping = $_POST['shipping'];
                
                if (isset($shipping['address_1'])) {
                    $order->set_shipping_address_1(sanitize_text_field($shipping['address_1']));
                }
                if (isset($shipping['address_2'])) {
                    $order->set_shipping_address_2(sanitize_text_field($shipping['address_2']));
                }
                if (isset($shipping['city'])) {
                    $order->set_shipping_city(sanitize_text_field($shipping['city']));
                }
                if (isset($shipping['state'])) {
                    $order->set_shipping_state(sanitize_text_field($shipping['state']));
                }
                if (isset($shipping['country'])) {
                    $order->set_shipping_country(sanitize_text_field($shipping['country']));
                }
                if (isset($shipping['google_maps_link'])) {
                    $order->update_meta_data('_shipping_google_maps_link', esc_url_raw($shipping['google_maps_link']));
                }
            }
            
            // Update billing address if provided (Phase 8)
            if (isset($_POST['billing']) && is_array($_POST['billing'])) {
                $billing = $_POST['billing'];
                
                if (isset($billing['address_1'])) {
                    $order->set_billing_address_1(sanitize_text_field($billing['address_1']));
                }
                if (isset($billing['address_2'])) {
                    $order->set_billing_address_2(sanitize_text_field($billing['address_2']));
                }
                if (isset($billing['city'])) {
                    $order->set_billing_city(sanitize_text_field($billing['city']));
                }
                if (isset($billing['state'])) {
                    $order->set_billing_state(sanitize_text_field($billing['state']));
                }
                if (isset($billing['country'])) {
                    $order->set_billing_country(sanitize_text_field($billing['country']));
                }
                if (isset($billing['google_maps_link'])) {
                    $order->update_meta_data('_billing_google_maps_link', esc_url_raw($billing['google_maps_link']));
                }
            }
            
            // Save order
            $order->save();
            
            // If table child order, update parent order too
            $parent_id = $order->get_parent_id();
            if ($parent_id > 0) {
                $parent_order = wc_get_order($parent_id);
                if ($parent_order) {
                    $parent_order->set_billing_first_name($first_name);
                    $parent_order->set_billing_last_name($last_name);
                    $parent_order->set_billing_phone($customer_phone);
                    $parent_order->set_billing_email($customer_email);
                    $parent_order->set_customer_note($customer_notes);
                    $parent_order->save();
                    
                }
            }
            
            
            wp_send_json_success(array(
                'message' => sprintf(
                    'Customer info updated for %s',
                    $customer_name
                ),
                'order_id' => $order_id,
                'parent_updated' => ($parent_id > 0)
            ));
            
        } catch (Exception $e) {
            oj_error_log('Update customer info: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to update customer info: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Get refund data (items, total, etc.)
     * Phase 4
     */
    public function ajax_get_refund_data() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Get refund data: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Get refund data: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate inputs
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        // Validate order ID
        if (empty($order_id)) {
            oj_error_log('Get refund data: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Get refund data: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            // Get order items
            $items = array();
            foreach ($order->get_items() as $item_id => $item) {
                $items[] = array(
                    'id' => $item_id,
                    'name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'total' => $item->get_total(),
                    'total_formatted' => wc_price($item->get_total())
                );
            }
            
            wp_send_json_success(array(
                'order_id' => $order_id,
                'total' => $order->get_total(),
                'total_formatted' => wc_price($order->get_total()),
                'items' => $items
            ));
            
        } catch (Exception $e) {
            oj_error_log('Get refund data: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to load refund data: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Refund order (partial or full)
     * Phase 4
     */
    public function ajax_refund_order() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Refund order: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Refund order: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate inputs
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $refund_type = isset($_POST['refund_type']) ? sanitize_text_field($_POST['refund_type']) : 'full';
        $refund_items = isset($_POST['refund_items']) ? array_map('intval', (array)$_POST['refund_items']) : array();
        $refund_reason = isset($_POST['refund_reason']) ? sanitize_textarea_field($_POST['refund_reason']) : '';
        
        // Validate order ID
        if (empty($order_id)) {
            oj_error_log('Refund order: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Validate reason
        if (empty($refund_reason)) {
            oj_error_log('Refund order: Missing refund reason');
            wp_send_json_error(array('message' => 'Refund reason is required'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Refund order: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        // CRITICAL: Block table child orders
        if ($order->get_parent_id() > 0) {
            oj_error_log('Refund order: BLOCKED - Table child order - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Table child orders cannot be refunded individually. Use the Close Table process.'));
        }
        
        try {
            $refund_amount = 0;
            $line_items = array();
            
            if ($refund_type === 'full') {
                // Full refund - use order total
                $refund_amount = $order->get_total();
                
                // Include all items
                foreach ($order->get_items() as $item_id => $item) {
                    $line_items[$item_id] = array(
                        'qty' => $item->get_quantity(),
                        'refund_total' => $item->get_total(),
                        'refund_tax' => $item->get_total_tax()
                    );
                }
                
            } else {
                // Partial refund - calculate from selected items
                if (empty($refund_items)) {
                    oj_error_log('Refund order: No items selected for partial refund');
                    wp_send_json_error(array('message' => 'Please select items to refund'));
                }
                
                foreach ($refund_items as $item_id) {
                    $item = $order->get_item($item_id);
                    if ($item) {
                        $line_items[$item_id] = array(
                            'qty' => $item->get_quantity(),
                            'refund_total' => $item->get_total(),
                            'refund_tax' => $item->get_total_tax()
                        );
                        $refund_amount += $item->get_total();
                    }
                }
            }
            
            // Validate refund amount
            if ($refund_amount <= 0) {
                oj_error_log('Refund order: Invalid refund amount - ' . $refund_amount);
                wp_send_json_error(array('message' => 'Invalid refund amount'));
            }
            
            // Create refund
            $refund = wc_create_refund(array(
                'order_id' => $order_id,
                'amount' => $refund_amount,
                'reason' => $refund_reason,
                'line_items' => $line_items
            ));
            
            if (is_wp_error($refund)) {
                oj_error_log('Refund order: WooCommerce refund failed - ' . $refund->get_error_message());
                wp_send_json_error(array(
                    'message' => 'Refund failed: ' . $refund->get_error_message()
                ));
            }
            
            
            wp_send_json_success(array(
                'message' => sprintf(
                    '%s refund of %s processed successfully',
                    ucfirst($refund_type),
                    wc_price($refund_amount)
                ),
                'order_id' => $order_id,
                'refund_id' => $refund->get_id(),
                'refund_amount' => $refund_amount,
                'refund_type' => $refund_type
            ));
            
        } catch (Exception $e) {
            oj_error_log('Refund order: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to process refund: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Get discount data (order totals)
     * Phase 5
     */
    public function ajax_get_discount_data() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Get discount data: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Get discount data: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate inputs
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        // Validate order ID
        if (empty($order_id)) {
            oj_error_log('Get discount data: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Get discount data: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            
            wp_send_json_success(array(
                'order_id' => $order_id,
                'total' => $order->get_total(),
                'total_formatted' => wc_price($order->get_total()),
                'subtotal' => $order->get_subtotal(),
                'subtotal_formatted' => wc_price($order->get_subtotal())
            ));
            
        } catch (Exception $e) {
            oj_error_log('Get discount data: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to load discount data: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Apply discount to order
     * Phase 5
     */
    public function ajax_apply_discount() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Apply discount: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Apply discount: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate inputs
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $discount_type = isset($_POST['discount_type']) ? sanitize_text_field($_POST['discount_type']) : 'fixed';
        $discount_value = isset($_POST['discount_value']) ? floatval($_POST['discount_value']) : 0;
        $discount_reason = isset($_POST['discount_reason']) ? sanitize_textarea_field($_POST['discount_reason']) : '';
        
        // Validate order ID
        if (empty($order_id)) {
            oj_error_log('Apply discount: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Validate discount value
        if ($discount_value <= 0) {
            oj_error_log('Apply discount: Invalid discount value - ' . $discount_value);
            wp_send_json_error(array('message' => 'Invalid discount value'));
        }
        
        // Validate percentage doesn't exceed 100%
        if ($discount_type === 'percentage' && $discount_value > 100) {
            oj_error_log('Apply discount: Percentage exceeds 100% - ' . $discount_value);
            wp_send_json_error(array('message' => 'Discount percentage cannot exceed 100%'));
        }
        
        // Validate reason
        if (empty($discount_reason)) {
            oj_error_log('Apply discount: Missing discount reason');
            wp_send_json_error(array('message' => 'Discount reason is required'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Apply discount: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            $order_total = $order->get_total();
            $order_subtotal = $order->get_subtotal();
            $discount_amount = 0;
            
            // Calculate discount amount
            if ($discount_type === 'fixed') {
                $discount_amount = $discount_value;
            } else {
                // Percentage - CRITICAL: Calculate on SUBTOTAL not TOTAL to avoid tax miscalculation
                $discount_amount = ($order_subtotal * $discount_value) / 100;
            }
            
            // Ensure discount doesn't exceed order total
            if ($discount_amount > $order_total) {
                $discount_amount = $order_total;
            }
            
            // Apply discount as a negative fee WITHOUT tax
            $fee = new WC_Order_Item_Fee();
            $fee->set_name('Discount: ' . $discount_reason);
            $fee->set_amount(-$discount_amount);
            $fee->set_total(-$discount_amount);
            $fee->set_tax_status('none'); // CRITICAL: No tax on discount
            $fee->set_order_id($order_id);
            $fee->save();
            
            $order->add_item($fee);
            
            // CRITICAL: Do NOT call calculate_totals() - it would recalculate tax
            // Instead, manually update the total: original total - discount
            $new_total = $order_total - $discount_amount;
            $order->set_total($new_total);
            $order->save();
            
            // Add order note
            $note_text = sprintf(
                'Discount applied: %s (%s). Reason: %s',
                wc_price($discount_amount),
                $discount_type === 'fixed' ? 'Fixed' : $discount_value . '%',
                $discount_reason
            );
            $order->add_order_note($note_text, 0, true);
            
            
            wp_send_json_success(array(
                'message' => sprintf(
                    'Discount of %s applied successfully',
                    wc_price($discount_amount)
                ),
                'order_id' => $order_id,
                'discount_amount' => $discount_amount,
                'discount_type' => $discount_type,
                'new_total' => $order->get_total()
            ));
            
        } catch (Exception $e) {
            oj_error_log('Apply discount: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to apply discount: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Add items to order
     * Phase 6
     */
    public function ajax_add_items() {
        
        wp_send_json_error(array(
            'message' => 'Add items functionality - Phase 6 (not yet implemented)'
        ));
    }
    
    /**
     * AJAX: Search products for adding to order
     * Phase 6
     */
    public function ajax_search_products() {
        
        wp_send_json_error(array(
            'message' => 'Product search - Phase 6 (not yet implemented)'
        ));
    }
    
    /**
     * AJAX: Change order status
     * Phase 4
     */
    public function ajax_change_status() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Change status: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Change status: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate inputs
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $new_status = isset($_POST['new_status']) ? sanitize_text_field($_POST['new_status']) : '';
        
        // Validate order ID
        if (empty($order_id)) {
            oj_error_log('Change status: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Validate status
        $valid_statuses = array('pending', 'on-hold', 'processing', 'completed', 'cancelled');
        if (!in_array($new_status, $valid_statuses)) {
            oj_error_log('Change status: Invalid status - ' . $new_status);
            wp_send_json_error(array('message' => 'Invalid status'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Change status: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            $old_status = $order->get_status();
            
            // Update order status
            $order->update_status($new_status, 'Status changed via Orders Master. ', true);
            
            
            // Get status labels
            $status_labels = array(
                'pending' => 'Pending Payment',
                'on-hold' => 'On Hold',
                'processing' => 'Processing',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled'
            );
            
            $new_status_label = isset($status_labels[$new_status]) ? $status_labels[$new_status] : ucfirst($new_status);
            
            wp_send_json_success(array(
                'message' => sprintf(
                    'Order status updated to "%s"',
                    $new_status_label
                ),
                'order_id' => $order_id,
                'old_status' => $old_status,
                'new_status' => $new_status
            ));
            
        } catch (Exception $e) {
            oj_error_log('Change status: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to change status: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Execute WooCommerce order action
     * Phase 7
     */
    public function ajax_execute_order_action() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Execute order action: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Execute order action: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate inputs
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order_action = isset($_POST['order_action']) ? sanitize_text_field($_POST['order_action']) : '';
        
        // Validate order ID
        if (empty($order_id)) {
            oj_error_log('Execute order action: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Validate action
        if (empty($order_action)) {
            oj_error_log('Execute order action: Missing action');
            wp_send_json_error(array('message' => 'Please select an action'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Execute order action: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            $message = '';
            
            switch ($order_action) {
                case 'send_order_details':
                    // Send "Customer processing order" email
                    WC()->mailer()->emails['WC_Email_Customer_Processing_Order']->trigger($order_id);
                    $message = 'Order details email sent to customer successfully';
                    break;
                    
                case 'send_order_details_admin':
                    // Send "New order" email to admin
                    WC()->mailer()->emails['WC_Email_New_Order']->trigger($order_id);
                    $message = 'Order details email sent to admin successfully';
                    break;
                    
                case 'regenerate_download_permissions':
                    // Regenerate download permissions
                    wc_downloadable_product_permissions($order_id, true);
                    $message = 'Download permissions regenerated successfully';
                    break;
                    
                case 'send_invoice':
                    // Send "Customer invoice" email
                    WC()->mailer()->emails['WC_Email_Customer_Invoice']->trigger($order_id);
                    $message = 'Invoice email sent to customer successfully';
                    break;
                    
                default:
                    oj_error_log('Execute order action: Unknown action - ' . $order_action);
                    wp_send_json_error(array('message' => 'Unknown action'));
                    return;
            }
            
            // Add order note
            $order->add_order_note(sprintf('Order action executed via Orders Master: %s', $order_action), 0, true);
            
            
            wp_send_json_success(array(
                'message' => $message,
                'order_id' => $order_id,
                'action' => $order_action
            ));
            
        } catch (Exception $e) {
            oj_error_log('Execute order action: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to execute action: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Get order content data
     * Phase 7 - Step 3
     */
    public function ajax_get_order_content() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Get order content: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Get order content: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate order ID
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (empty($order_id)) {
            oj_error_log('Get order content: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Get order content: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        // Get order type (exwf_odmethod)
        $order_type = $order->get_meta('exwf_odmethod', true);
        if (empty($order_type)) {
            $order_type = 'dinein'; // Default
        }
        
        // Get location/table
        $location = $order->get_meta('table_number', true);
        
        // Get order date (format for datetime-local input)
        $order_date = $order->get_date_created();
        $order_date_formatted = $order_date ? $order_date->format('Y-m-d\TH:i') : '';
        
        
        wp_send_json_success(array(
            'order_type' => $order_type,
            'location' => $location,
            'order_date' => $order_date_formatted
        ));
    }
    
    /**
     * AJAX: Save order content data
     * Phase 7 - Step 6
     */
    public function ajax_save_order_content() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Save order content: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Save order content: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate inputs
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $order_type = isset($_POST['order_type']) ? sanitize_text_field($_POST['order_type']) : '';
        $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';
        $order_date = isset($_POST['order_date']) ? sanitize_text_field($_POST['order_date']) : '';
        
        // Validate order ID
        if (empty($order_id)) {
            oj_error_log('Save order content: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Save order content: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            // Update order type (exwf_odmethod)
            if (!empty($order_type)) {
                $order->update_meta_data('exwf_odmethod', $order_type);
            }
            
            // Update location/table
            if (!empty($location)) {
                $order->update_meta_data('table_number', $location);
            } else {
                $order->delete_meta_data('table_number');
            }
            
            // Update order date (if provided)
            if (!empty($order_date)) {
                $date_obj = new DateTime($order_date);
                $order->set_date_created($date_obj->format('Y-m-d H:i:s'));
            }
            
            // Save order
            $order->save();
            
            // Add order note
            $order->add_order_note('Order content updated via Orders Master', 0, true);
            
            
            wp_send_json_success(array(
                'message' => 'Order content updated successfully'
            ));
            
        } catch (Exception $e) {
            oj_error_log('Save order content: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to save order content: ' . $e->getMessage()
            ));
        }
    }
    
    // ========================================================================
    // HELPER METHODS
    // ========================================================================
    
    /**
     * Validate order is editable
     * 
     * @param int $order_id Order ID
     * @param string $action_type Action type (add_items, refund, etc.)
     * @return bool|WP_Error
     */
    private function can_edit_order($order_id, $action_type) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', 'Order not found');
        }
        
        $is_table_child = $order->get_parent_id() > 0;
        $status = $order->get_status();
        
        // Action-specific rules
        switch ($action_type) {
            case 'complete':
            case 'refund':
                // CRITICAL: Table child orders cannot be completed/refunded individually
                if ($is_table_child) {
                    return new WP_Error(
                        'table_child_blocked',
                        'Table child orders must use "Close Table" to complete'
                    );
                }
                return true;
                
            case 'add_items':
            case 'add_discount':
                // Cannot add items/discounts to paid or completed orders
                if (in_array($status, array('pending-payment', 'completed'))) {
                    return new WP_Error(
                        'order_locked',
                        'Cannot modify paid or completed orders'
                    );
                }
                return true;
                
            case 'add_note':
            case 'customer_info':
            case 'status_change':
                // Always allowed
                return true;
                
            default:
                return new WP_Error('invalid_action', 'Invalid action type');
        }
    }
    
    // ========================================================================
    // PHASE 9: CARD REFRESH AFTER EDIT
    // ========================================================================
    
    /**
     * AJAX: Refresh single order card
     * Returns fresh HTML for a single order card after edit
     * 
     * @since 2.0.0
     */
    public function ajax_refresh_order_card() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Refresh card: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Refresh card: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate order ID
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (empty($order_id)) {
            oj_error_log('Refresh card: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Refresh card: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            // Load helper functions
            require_once ORDERS_JET_PLUGIN_DIR . 'includes/helpers/orders-master-helpers.php';
            
            // Initialize services if not already available
            if (!$this->kitchen_service) {
                require_once ORDERS_JET_PLUGIN_DIR . 'includes/services/class-orders-jet-kitchen-service.php';
                $kitchen_service = new Orders_Jet_Kitchen_Service();
            } else {
                $kitchen_service = $this->kitchen_service;
            }
            
            require_once ORDERS_JET_PLUGIN_DIR . 'includes/services/class-orders-jet-order-method-service.php';
            $order_method_service = new Orders_Jet_Order_Method_Service();
            
            // Prepare order data (same as template)
            $order_data = oj_master_prepare_order_data($order, $kitchen_service, $order_method_service);
            
            // Enable bulk checkbox for Orders Master
            $show_bulk_checkbox = true;
            
            // Capture order card HTML
            ob_start();
            include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/order-card.php';
            $card_html = ob_get_clean();
            
            
            wp_send_json_success(array(
                'order_id' => $order_id,
                'card_html' => $card_html
            ));
            
        } catch (Exception $e) {
            oj_error_log('Refresh card: Exception - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Failed to refresh card'));
        }
    }
    
    // ========================================================================
    // COUPON MANAGEMENT ENDPOINTS (New Feature)
    // ========================================================================
    
    /**
     * AJAX: Get available coupons
     * Returns recent active coupons for easy selection
     */
    public function ajax_get_available_coupons() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Get available coupons: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Get available coupons: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        try {
            // Get recent active coupons (last 20)
            $coupons = get_posts(array(
                'post_type' => 'shop_coupon',
                'post_status' => 'publish',
                'posts_per_page' => 20,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'expiry_date',
                        'value' => current_time('Y-m-d'),
                        'compare' => '>=',
                        'type' => 'DATE'
                    ),
                    array(
                        'key' => 'expiry_date',
                        'compare' => 'NOT EXISTS'
                    )
                )
            ));
            
            $available_coupons = array();
            
            foreach ($coupons as $coupon_post) {
                $coupon = new WC_Coupon($coupon_post->ID);
                
                // Skip if usage limit reached
                if ($coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit()) {
                    continue;
                }
                
                $available_coupons[] = array(
                    'code' => $coupon->get_code(),
                    'type' => $coupon->get_discount_type(),
                    'amount' => $coupon->get_amount(),
                    'description' => $coupon->get_description(),
                    'usage_limit' => $coupon->get_usage_limit(),
                    'usage_count' => $coupon->get_usage_count(),
                    'individual_use' => $coupon->get_individual_use(),
                    'formatted_amount' => $this->format_coupon_amount($coupon)
                );
            }
            
            wp_send_json_success(array(
                'coupons' => $available_coupons
            ));
            
        } catch (Exception $e) {
            oj_error_log('Get available coupons: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to load available coupons: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Get order's applied coupons
     * Returns coupons currently applied to the order
     */
    public function ajax_get_order_coupons() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Get order coupons: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Get order coupons: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate order ID
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (empty($order_id)) {
            oj_error_log('Get order coupons: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Get order coupons: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            $applied_coupons = array();
            $coupon_items = $order->get_items('coupon');
            
            foreach ($coupon_items as $coupon_item) {
                $coupon_code = $coupon_item->get_code();
                $coupon = new WC_Coupon($coupon_code);
                
                $applied_coupons[] = array(
                    'code' => $coupon_code,
                    'discount_amount' => abs($coupon_item->get_discount()),
                    'discount_amount_formatted' => wc_price(abs($coupon_item->get_discount())),
                    'type' => $coupon->get_discount_type(),
                    'can_remove' => true // Always allow removal in Orders Master
                );
            }
            
            wp_send_json_success(array(
                'order_id' => $order_id,
                'applied_coupons' => $applied_coupons,
                'order_total' => $order->get_total(),
                'order_total_formatted' => wc_price($order->get_total())
            ));
            
        } catch (Exception $e) {
            oj_error_log('Get order coupons: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to load order coupons: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Apply coupon to order
     * Pre-validates coupon before applying
     */
    public function ajax_apply_coupon_to_order() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Apply coupon: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Apply coupon: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate inputs
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';
        
        // Validate order ID
        if (empty($order_id)) {
            oj_error_log('Apply coupon: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Validate coupon code
        if (empty($coupon_code)) {
            oj_error_log('Apply coupon: Missing coupon code');
            wp_send_json_error(array('message' => 'Coupon code is required'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Apply coupon: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            // Check if coupon exists
            $coupon = new WC_Coupon($coupon_code);
            if (!$coupon->get_id()) {
                oj_error_log('Apply coupon: Coupon not found - Code: ' . $coupon_code);
                wp_send_json_error(array('message' => 'Coupon not found'));
            }
            
            // Pre-validate coupon
            $validation_result = $this->validate_coupon_for_order($coupon, $order);
            if (is_wp_error($validation_result)) {
                oj_error_log('Apply coupon: Validation failed - ' . $validation_result->get_error_message());
                wp_send_json_error(array('message' => $validation_result->get_error_message()));
            }
            
            // Check if coupon already applied
            $existing_coupons = $order->get_coupon_codes();
            if (in_array($coupon_code, $existing_coupons)) {
                oj_error_log('Apply coupon: Already applied - Code: ' . $coupon_code);
                wp_send_json_error(array('message' => 'Coupon is already applied to this order'));
            }
            
            // Apply coupon
            $result = $order->apply_coupon($coupon_code);
            if (is_wp_error($result)) {
                oj_error_log('Apply coupon: WooCommerce error - ' . $result->get_error_message());
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
            
            // Recalculate and save
            $order->calculate_totals();
            $order->save();
            
            // Add order note
            $order->add_order_note(sprintf('Coupon "%s" applied via Orders Master', $coupon_code), 0, true);
            
            
            wp_send_json_success(array(
                'message' => sprintf('Coupon "%s" applied successfully', $coupon_code),
                'order_id' => $order_id,
                'coupon_code' => $coupon_code,
                'new_total' => $order->get_total(),
                'new_total_formatted' => wc_price($order->get_total())
            ));
            
        } catch (Exception $e) {
            oj_error_log('Apply coupon: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to apply coupon: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Remove coupon from order
     */
    public function ajax_remove_coupon_from_order() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Remove coupon: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            oj_error_log('Remove coupon: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get and validate inputs
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';
        
        // Validate order ID
        if (empty($order_id)) {
            oj_error_log('Remove coupon: Missing order ID');
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        // Validate coupon code
        if (empty($coupon_code)) {
            oj_error_log('Remove coupon: Missing coupon code');
            wp_send_json_error(array('message' => 'Coupon code is required'));
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            oj_error_log('Remove coupon: Order not found - ID: ' . $order_id);
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            // Refresh order data to ensure we have latest coupon state
            $order = wc_get_order($order_id);
            
            // Check if coupon is applied
            $existing_coupons = $order->get_coupon_codes();
            
            // Check both exact match and case-insensitive match
            $coupon_found = false;
            $actual_coupon_code = '';
            
            foreach ($existing_coupons as $existing_code) {
                if (strtolower($existing_code) === strtolower($coupon_code)) {
                    $coupon_found = true;
                    $actual_coupon_code = $existing_code;
                    break;
                }
            }
            
            if (!$coupon_found) {
                oj_error_log('Remove coupon: Not applied - Code: ' . $coupon_code . ' - Existing: ' . implode(', ', $existing_coupons));
                wp_send_json_error(array('message' => 'Coupon is not applied to this order'));
            }
            
            // Use the actual coupon code from the order (in case of case differences)
            $coupon_code = $actual_coupon_code;
            
            // Remove coupon
            $result = $order->remove_coupon($coupon_code);
            if (!$result) {
                oj_error_log('Remove coupon: Failed to remove - Code: ' . $coupon_code);
                wp_send_json_error(array('message' => 'Failed to remove coupon'));
            }
            
            // Recalculate and save
            $order->calculate_totals();
            $order->save();
            
            // Add order note
            $order->add_order_note(sprintf('Coupon "%s" removed via Orders Master', $coupon_code), 0, true);
            
            
            wp_send_json_success(array(
                'message' => sprintf('Coupon "%s" removed successfully', $coupon_code),
                'order_id' => $order_id,
                'coupon_code' => $coupon_code,
                'new_total' => $order->get_total(),
                'new_total_formatted' => wc_price($order->get_total())
            ));
            
        } catch (Exception $e) {
            oj_error_log('Remove coupon: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to remove coupon: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Create quick coupon
     * Simple coupon creation with basic options
     */
    public function ajax_create_quick_coupon() {
        
        // Verify nonce
        if (!check_ajax_referer('oj_editor_nonce', 'nonce', false)) {
            oj_error_log('Create coupon: Nonce verification failed');
            wp_send_json_error(array('message' => 'Security check failed'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            oj_error_log('Create coupon: User lacks permission');
            wp_send_json_error(array('message' => 'Permission denied - requires WooCommerce management capability'));
        }
        
        // Get and validate inputs
        $coupon_code = isset($_POST['coupon_code']) ? strtoupper(sanitize_text_field($_POST['coupon_code'])) : '';
        $discount_type = isset($_POST['discount_type']) ? sanitize_text_field($_POST['discount_type']) : 'fixed_cart';
        $discount_amount = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        // Validate inputs
        if (empty($coupon_code)) {
            oj_error_log('Create coupon: Missing coupon code');
            wp_send_json_error(array('message' => 'Coupon code is required'));
        }
        
        if ($discount_amount <= 0) {
            oj_error_log('Create coupon: Invalid discount amount - ' . $discount_amount);
            wp_send_json_error(array('message' => 'Discount amount must be greater than 0'));
        }
        
        if (!in_array($discount_type, array('fixed_cart', 'percent'))) {
            oj_error_log('Create coupon: Invalid discount type - ' . $discount_type);
            wp_send_json_error(array('message' => 'Invalid discount type'));
        }
        
        try {
            // Check if coupon code already exists
            $existing_coupon = new WC_Coupon($coupon_code);
            if ($existing_coupon->get_id()) {
                oj_error_log('Create coupon: Code already exists - ' . $coupon_code);
                wp_send_json_error(array('message' => 'Coupon code already exists'));
            }
            
            // Create new coupon
            $coupon = new WC_Coupon();
            $coupon->set_code($coupon_code);
            $coupon->set_discount_type($discount_type);
            $coupon->set_amount($discount_amount);
            $coupon->set_description($description);
            $coupon->set_individual_use(false); // Allow with other coupons by default
            $coupon->set_usage_limit(0); // Unlimited usage by default
            $coupon->set_usage_limit_per_user(0); // Unlimited per user
            // CRITICAL: Use time() not current_time('timestamp') - WooCommerce uses UTC internally
            $coupon->set_date_created(time());
            
            // Save coupon
            $coupon_id = $coupon->save();
            
            if (!$coupon_id) {
                oj_error_log('Create coupon: Failed to save coupon');
                wp_send_json_error(array('message' => 'Failed to create coupon'));
            }
            
            
            wp_send_json_success(array(
                'message' => sprintf('Coupon "%s" created successfully', $coupon_code),
                'coupon' => array(
                    'code' => $coupon_code,
                    'type' => $discount_type,
                    'amount' => $discount_amount,
                    'description' => $description,
                    'formatted_amount' => $this->format_coupon_amount($coupon)
                )
            ));
            
        } catch (Exception $e) {
            oj_error_log('Create coupon: Exception - ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Failed to create coupon: ' . $e->getMessage()
            ));
        }
    }
    
    // ========================================================================
    // COUPON HELPER METHODS
    // ========================================================================
    
    /**
     * Validate coupon for order
     * Pre-validation before applying coupon
     */
    private function validate_coupon_for_order($coupon, $order) {
        // Check if coupon is valid
        if (!$coupon->get_id()) {
            return new WP_Error('invalid_coupon', 'Coupon does not exist');
        }
        
        // Check if coupon is published
        if (get_post_status($coupon->get_id()) !== 'publish') {
            return new WP_Error('inactive_coupon', 'Coupon is not active');
        }
        
        // Check expiry date
        $expiry_date = $coupon->get_date_expires();
        if ($expiry_date && $expiry_date->getTimestamp() < current_time('timestamp')) {
            return new WP_Error('expired_coupon', 'Coupon has expired');
        }
        
        // Check usage limit
        if ($coupon->get_usage_limit() > 0 && $coupon->get_usage_count() >= $coupon->get_usage_limit()) {
            return new WP_Error('usage_limit_reached', 'Coupon usage limit has been reached');
        }
        
        // Check individual use
        if ($coupon->get_individual_use()) {
            $existing_coupons = $order->get_coupon_codes();
            if (!empty($existing_coupons)) {
                return new WP_Error('individual_use_only', 'This coupon cannot be used with other coupons');
            }
        }
        
        // Check if other individual use coupons are applied
        $existing_coupons = $order->get_coupon_codes();
        foreach ($existing_coupons as $existing_code) {
            $existing_coupon = new WC_Coupon($existing_code);
            if ($existing_coupon->get_individual_use()) {
                return new WP_Error('individual_use_conflict', 'Cannot apply coupon - order has individual use coupon');
            }
        }
        
        return true;
    }
    
    /**
     * Format coupon amount for display
     */
    private function format_coupon_amount($coupon) {
        $type = $coupon->get_discount_type();
        $amount = $coupon->get_amount();
        
        switch ($type) {
            case 'fixed_cart':
                return wc_price($amount) . ' off';
            case 'percent':
                return $amount . '% off';
            case 'fixed_product':
                return wc_price($amount) . ' off each item';
            case 'percent_product':
                return $amount . '% off each item';
            default:
                return $amount;
        }
    }
}

