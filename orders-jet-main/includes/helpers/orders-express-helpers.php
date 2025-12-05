<?php
declare(strict_types=1);
/**
 * Orders Express Helper Functions
 * Extracted from dashboard-manager-orders-express.php for reuse in AJAX handlers
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper function: Prepare clean order data using services (Phase 4A: Performance Critical)
 */
function oj_express_prepare_order_data($order, $kitchen_service, $order_method_service) {
    $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
    
    // Pre-process items text for performance (Phase 4A)
    $items = $order->get_items();
    $items_text = array();
    foreach ($items as $item) {
        $product_name = $item->get_name();
        $quantity = $item->get_quantity();
        $items_text[] = esc_html($quantity) . 'x ' . esc_html($product_name);
    }
    $items_display = implode(' ', $items_text);
    
    // Check for guest invoice request (simplified approach)
    $table_number = $order->get_meta('_oj_table_number');
    $guest_invoice_requested = false;
    $invoice_request_time = '';
    
    if (!empty($table_number)) {
        // Get the table post to check for invoice request
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
        
        if (!empty($table_posts)) {
            $table_id = $table_posts[0]->ID;
            $invoice_request_status = get_post_meta($table_id, '_oj_invoice_request_status', true);
            $guest_invoice_requested = ($invoice_request_status === 'pending');
            $invoice_request_time = get_post_meta($table_id, '_oj_guest_invoice_requested', true);
        }
    }

    return array(
        'id' => $order->get_id(),
        'number' => $order->get_order_number(),
        'status' => $order->get_status(),
        'method' => $order_method_service->get_order_method($order),
        'table' => $table_number,
        'total' => $order->get_total(),
        'date' => $order->get_date_created(),
        'customer' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: $order->get_billing_email() ?: __('Guest', 'orders-jet'),
        'items' => $items,
        'items_display' => $items_display, // Pre-processed for performance
        'item_count' => count($items),     // Pre-calculated for performance
        'kitchen_type' => $kitchen_status['kitchen_type'],
        'kitchen_status' => $kitchen_status,
        'guest_invoice_requested' => $guest_invoice_requested,
        'invoice_request_time' => $invoice_request_time,
        'order_object' => $order          // Pass order object to avoid re-querying (Phase 4A)
    );
}

/**
 * Helper function: Update filter counts based on order data
 */
function oj_express_update_filter_counts(&$counts, $order_data) {
    $status = $order_data['status'];
    $method = $order_data['method'];
    $kitchen_type = $order_data['kitchen_type'];
    
    // Count all active orders
    $counts['active']++;
    
    // Count by status
    if ($status === 'processing') {
        $counts['processing']++;
    } elseif ($status === 'pending') {
        $counts['pending']++;
    }
    
    // Count by method (all methods including fallback)
    if ($method === 'dinein') {
        $counts['dinein']++;
    } elseif ($method === 'takeaway') {
        $counts['takeaway']++;
    } elseif ($method === 'delivery') {
        $counts['delivery']++;
    }
    
    // Count by kitchen type - mixed orders count in both kitchens
    if ($kitchen_type === 'food' || $kitchen_type === 'mixed') {
        $counts['food_kitchen']++;
    }
    if ($kitchen_type === 'beverages' || $kitchen_type === 'mixed') {
        $counts['beverage_kitchen']++;
    }
}

/**
 * Helper function: Get optimized badge data directly from services (Phase 4A: Performance Critical)
 */
function oj_express_get_optimized_badge_data($order, $kitchen_service, $order_method_service) {
    // Get structured data directly instead of parsing HTML (Phase 4A Performance)
    $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
    $order_method = $order_method_service->get_order_method($order);
    $kitchen_type = $kitchen_status['kitchen_type'];
    $order_status = $order->get_status();
    
    // Status badge data (optimized logic)
    if ($order_status === 'pending') {
        $status_data = array(
            'class' => 'ready',
            'icon' => '✅',
            'text' => __('Ready', 'orders-jet')
        );
    } elseif ($order_status === 'processing') {
        if ($kitchen_type === 'mixed') {
            if ($kitchen_status['food_ready'] && !$kitchen_status['beverage_ready']) {
                $status_data = array('class' => 'partial', 'icon' => '🍕✅ 🥤⏳', 'text' => __('Waiting for Bev.', 'orders-jet'));
            } elseif (!$kitchen_status['food_ready'] && $kitchen_status['beverage_ready']) {
                $status_data = array('class' => 'partial', 'icon' => '🍕⏳ 🥤✅', 'text' => __('Waiting for Food', 'orders-jet'));
            } else {
                $status_data = array('class' => 'partial', 'icon' => '🍕⏳ 🥤⏳', 'text' => __('Both Kitchens', 'orders-jet'));
            }
        } elseif ($kitchen_type === 'food') {
            $status_data = array('class' => 'partial', 'icon' => '🍕⏳', 'text' => __('Waiting for Food', 'orders-jet'));
        } elseif ($kitchen_type === 'beverages') {
            $status_data = array('class' => 'partial', 'icon' => '🥤⏳', 'text' => __('Waiting for Bev.', 'orders-jet'));
        } else {
            $status_data = array('class' => 'kitchen', 'icon' => '👨‍🍳', 'text' => __('Kitchen', 'orders-jet'));
        }
    } elseif ($order_status === 'completed') {
        $status_data = array(
            'class' => 'completed',
            'icon' => '✅',
            'text' => __('Completed', 'orders-jet')
        );
    } else {
        $status_data = array(
            'class' => 'unknown',
            'icon' => '❓',
            'text' => ucfirst($order_status)
        );
    }
    
    // Method badge data
    $method_data = array(
        'class' => $order_method,
        'icon' => $order_method === 'dinein' ? '🍽️' : ($order_method === 'takeaway' ? '🥡' : '🚚'),
        'text' => ucfirst($order_method)
    );
    
    return array(
        'status' => $status_data,
        'method' => $method_data,
        'kitchen_type' => $kitchen_type,
        'kitchen_status' => $kitchen_status
    );
}
