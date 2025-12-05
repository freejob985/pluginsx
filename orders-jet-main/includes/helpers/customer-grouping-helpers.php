<?php
/**
 * Customer Grouping Helpers
 * Helper functions for grouping orders by customer in reports
 * 
 * @package Orders_Jet
 * @version 1.3.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get customer identifier for grouping orders
 * 
 * @param WC_Order $order The order object
 * @param string $customer_type The customer type filter
 * @return string Customer identifier
 */
function oj_get_customer_identifier($order, $customer_type) {
    switch ($customer_type) {
        case 'repeat_visitor':
        case 'registered_customer':
            // Use customer name or email
            $first_name = trim($order->get_billing_first_name());
            $last_name = trim($order->get_billing_last_name());
            $name = trim($first_name . ' ' . $last_name);
            return !empty($name) ? $name : $order->get_billing_email();
            
        case 'table_guest':
            // Use table number
            $table_number = $order->get_meta('_oj_table_number');
            return !empty($table_number) ? 'Table ' . $table_number : 'Unknown Table';
            
        default:
            // Fallback to email
            return $order->get_billing_email() ?: 'Guest Customer';
    }
}

/**
 * Get customer display name for summary cards
 * 
 * @param WC_Order $order The order object
 * @return string Display name
 */
function oj_get_customer_display_name($order) {
    $first_name = trim($order->get_billing_first_name());
    $last_name = trim($order->get_billing_last_name());
    $name = trim($first_name . ' ' . $last_name);
    
    if (!empty($name)) {
        return $name;
    }
    
    $email = $order->get_billing_email();
    if (!empty($email)) {
        return $email;
    }
    
    return 'Guest Customer';
}

/**
 * Count total orders for a customer
 * 
 * @param string $customer_key Customer identifier
 * @param string $customer_type Customer type
 * @param array $all_orders All orders in current result set
 * @return int Order count
 */
function oj_count_customer_orders($customer_key, $customer_type, $all_orders) {
    $count = 0;
    foreach ($all_orders as $order) {
        if (oj_get_customer_identifier($order, $customer_type) === $customer_key) {
            $count++;
        }
    }
    return $count;
}

/**
 * Calculate total value for a customer
 * 
 * @param string $customer_key Customer identifier
 * @param string $customer_type Customer type
 * @param array $all_orders All orders in current result set
 * @return float Total value
 */
function oj_calculate_customer_total($customer_key, $customer_type, $all_orders) {
    $total = 0;
    foreach ($all_orders as $order) {
        if (oj_get_customer_identifier($order, $customer_type) === $customer_key) {
            $total += $order->get_total();
        }
    }
    return $total;
}

/**
 * Build customer filter URL preserving all current parameters
 * 
 * @param string $customer_key Customer identifier to search for
 * @return string Filter URL
 */
function oj_build_customer_filter_url($customer_key) {
    $current_params = $_GET; // Preserve ALL current parameters
    
    // Add customer search while keeping everything else
    $current_params['search'] = $customer_key;
    
    // Reset pagination (new search results)
    unset($current_params['paged']);
    
    return add_query_arg($current_params, admin_url('admin.php'));
}

/**
 * Check if customer grouping should be applied
 * 
 * @param string $customer_type Current customer type filter
 * @return bool True if grouping should be applied
 */
function oj_should_apply_customer_grouping($customer_type) {
    return in_array($customer_type, ['repeat_visitor', 'registered_customer', 'table_guest']);
}
