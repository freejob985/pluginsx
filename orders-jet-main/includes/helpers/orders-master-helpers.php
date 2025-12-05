<?php
/**
 * Orders Master V2 - Helper Functions
 * 
 * Extracted from orders-master-v2.php for better code organization
 * and reusability across the plugin.
 * 
 * @package Orders_Jet
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prepare clean order data using services
 * 
 * @param WC_Order $order The WooCommerce order object
 * @param Orders_Jet_Kitchen_Service $kitchen_service Kitchen service instance
 * @param Orders_Jet_Order_Method_Service $order_method_service Order method service instance
 * @return array Prepared order data array
 */
function oj_master_prepare_order_data($order, $kitchen_service, $order_method_service) {
    $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
    
    // Pre-process items text for performance
    $items = $order->get_items();
    $items_text = array();
    foreach ($items as $item) {
        $product_name = $item->get_name();
        $quantity = $item->get_quantity();
        $items_text[] = esc_html($quantity) . 'x ' . esc_html($product_name);
    }
    $items_display = implode(' ', $items_text);
    
    // Get table number
    $table_number = $order->get_meta(WooJet_Meta_Keys::TABLE_NUMBER);

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
        'items_display' => $items_display,
        'item_count' => count($items),
        'kitchen_type' => $kitchen_status['kitchen_type'],
        'kitchen_status' => $kitchen_status,
        'guest_invoice_requested' => false, // Not used in Orders Master
        'invoice_request_time' => '',
        'order_object' => $order
    );
}

/**
 * Get optimized badge data directly from services
 * 
 * @param WC_Order $order The WooCommerce order object
 * @param Orders_Jet_Kitchen_Service $kitchen_service Kitchen service instance
 * @param Orders_Jet_Order_Method_Service $order_method_service Order method service instance
 * @return array Badge data with status, type, and kitchen information
 */
function oj_express_get_optimized_badge_data($order, $kitchen_service, $order_method_service) {
    $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
    $order_method = $order_method_service->get_order_method($order);
    $kitchen_type = $kitchen_status['kitchen_type'];
    $order_status = $order->get_status();
    
    // Status badge data
    if ($order_status === 'pending') {
        $status_data = array(
            'class' => 'ready',
            'icon' => '✅',
            'text' => __('Ready', 'orders-jet')
        );
    } elseif ($order_status === 'pending-payment') {
        $status_data = array(
            'class' => 'pending-payment',
            'icon' => '💳',
            'text' => __('Pending Payment', 'orders-jet')
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
    } elseif ($order_status === 'on-hold') {
        $status_data = array(
            'class' => 'on-hold',
            'icon' => '⏸️',
            'text' => __('On Hold', 'orders-jet')
        );
    } elseif ($order_status === 'cancelled') {
        $status_data = array(
            'class' => 'cancelled',
            'icon' => '❌',
            'text' => __('Cancelled', 'orders-jet')
        );
    } elseif ($order_status === 'refunded') {
        $status_data = array(
            'class' => 'refunded',
            'icon' => '💰',
            'text' => __('Refunded', 'orders-jet')
        );
    } elseif ($order_status === 'failed') {
        $status_data = array(
            'class' => 'failed',
            'icon' => '⚠️',
            'text' => __('Failed', 'orders-jet')
        );
    } else {
        // Fallback for any unknown status
        $status_data = array(
            'class' => 'unknown',
            'icon' => '❓',
            'text' => ucfirst(str_replace('-', ' ', $order_status))
        );
    }
    
    // Type badge data
    $type_icons = array('dinein' => '🍽️', 'takeaway' => '📦', 'delivery' => '🚚');
    $type_texts = array('dinein' => __('Dine-in', 'orders-jet'), 'takeaway' => __('Takeaway', 'orders-jet'), 'delivery' => __('Delivery', 'orders-jet'));
    $type_data = array(
        'class' => $order_method,
        'icon' => $type_icons[$order_method] ?? '📦',
        'text' => $type_texts[$order_method] ?? __('Takeaway', 'orders-jet')
    );
    
    // Kitchen badge data
    $kitchen_icons = array('food' => '🍕', 'beverages' => '🥤', 'mixed' => '🍽️');
    $kitchen_texts = array('food' => __('Food', 'orders-jet'), 'beverages' => __('Beverages', 'orders-jet'), 'mixed' => __('Mixed', 'orders-jet'));
    $kitchen_data = array(
        'class' => $kitchen_type,
        'icon' => $kitchen_icons[$kitchen_type] ?? '🍕',
        'text' => $kitchen_texts[$kitchen_type] ?? __('Food', 'orders-jet')
    );
    
    return array(
        'status' => $status_data,
        'type' => $type_data,
        'kitchen' => $kitchen_data
    );
}

/**
 * Generate action buttons HTML based on order status and type
 * 
 * @param array $order_data Prepared order data
 * @param array $kitchen_status Kitchen readiness status
 * @return string HTML for action buttons
 */
function oj_express_get_action_buttons($order_data, $kitchen_status) {
    $order_id = $order_data['id'];
    $status = $order_data['status'];
    $kitchen_type = $order_data['kitchen_type'];
    $table_number = $order_data['table'];
    $guest_invoice_requested = $order_data['guest_invoice_requested'] ?? false;
    
    $buttons = '';
    
    // Check if this order has a guest invoice request
    if ($guest_invoice_requested && !empty($table_number)) {
        $buttons .= sprintf(
            '<div class="oj-guest-invoice-notice">🔔 Guest requested invoice</div>'
        );
        
        if ($status === 'pending') {
            $buttons .= sprintf(
                '<button class="oj-action-btn primary oj-close-table guest-request" data-order-id="%s" data-table-number="%s">🍽️ %s</button>',
                esc_attr($order_id),
                esc_attr($table_number),
                __('Close Table', 'orders-jet')
            );
        }
        
        return $buttons;
    }
    
    if ($status === 'processing') {
        if ($kitchen_type === 'mixed') {
            // Mixed kitchen - show individual buttons for unready kitchens
            if (!$kitchen_status['food_ready']) {
                $buttons .= sprintf(
                    '<button class="oj-action-btn primary oj-mark-ready-food" data-order-id="%s" data-kitchen="food">🍕 %s</button>',
                    esc_attr($order_id),
                    __('Food Ready', 'orders-jet')
                );
            }
            if (!$kitchen_status['beverage_ready']) {
                $buttons .= sprintf(
                    '<button class="oj-action-btn primary oj-mark-ready-beverage" data-order-id="%s" data-kitchen="beverages">🥤 %s</button>',
                    esc_attr($order_id),
                    __('Bev. Ready', 'orders-jet')
                );
            }
        } else {
            // Single kitchen - show appropriate button
            $icon = $kitchen_type === 'food' ? '🍕' : ($kitchen_type === 'beverages' ? '🥤' : '🔥');
            $text = $kitchen_type === 'food' ? __('Food Ready', 'orders-jet') : 
                   ($kitchen_type === 'beverages' ? __('Bev. Ready', 'orders-jet') : __('Mark Ready', 'orders-jet'));
            
            $buttons .= sprintf(
                '<button class="oj-action-btn primary oj-mark-ready" data-order-id="%s" data-kitchen="%s">%s %s</button>',
                esc_attr($order_id),
                esc_attr($kitchen_type),
                $icon,
                $text
            );
        }
    } elseif ($status === 'pending') {
        if (!empty($table_number)) {
            // Table order - show close table button
            $buttons .= sprintf(
                '<button class="oj-action-btn primary oj-close-table" data-order-id="%s" data-table-number="%s">🍽️ %s</button>',
                esc_attr($order_id),
                esc_attr($table_number),
                __('Close Table', 'orders-jet')
            );
        } else {
            // Individual order - show complete button
            $buttons .= sprintf(
                '<button class="oj-action-btn primary oj-complete-order" data-order-id="%s">✅ %s</button>',
                esc_attr($order_id),
                __('Complete', 'orders-jet')
            );
        }
    } elseif ($status === 'completed') {
        // Completed order - show print invoice button
        $buttons .= sprintf(
            '<button class="oj-action-btn secondary oj-print-invoice" data-order-id="%s">🖨️ %s</button>',
            esc_attr($order_id),
            __('Print Invoice', 'orders-jet')
        );
    }
    
    return $buttons;
}

/**
 * Build filter URL with all current parameters preserved
 * 
 * @param string $filter The filter to apply
 * @param array $current_params Current URL parameters
 * @return string URL query string
 */
function oj_build_filter_url($filter, $current_params = array()) {
    $params = array('page' => 'orders-master-v2', 'filter' => $filter);
    
    // Preserve date range
    if (!empty($current_params['date_preset'])) {
        $params['date_preset'] = $current_params['date_preset'];
    }
    if (!empty($current_params['date_from'])) {
        $params['date_from'] = $current_params['date_from'];
    }
    if (!empty($current_params['date_to'])) {
        $params['date_to'] = $current_params['date_to'];
    }
    
    // Preserve search
    if (!empty($current_params['search'])) {
        $params['search'] = $current_params['search'];
    }
    
    // Preserve sort
    if (!empty($current_params['orderby']) && $current_params['orderby'] !== 'date_created') {
        $params['orderby'] = $current_params['orderby'];
    }
    if (!empty($current_params['order']) && $current_params['order'] !== 'DESC') {
        $params['order'] = $current_params['order'];
    }
    
    return '?' . http_build_query($params);
}

/**
 * Calculate date range from preset string
 * 
 * @param string $preset Preset identifier (today, yesterday, week_to_date, etc.)
 * @return array|null Array with 'from', 'to' DateTime objects and 'label', or null if invalid
 */
function oj_calculate_date_range($preset) {
    $timezone = wp_timezone();
    $now = new DateTime('now', $timezone);
    
    switch ($preset) {
        case 'today':
            $from = clone $now;
            $from->setTime(0, 0, 0);
            $to = clone $now;
            $to->setTime(23, 59, 59);
            return array('from' => $from, 'to' => $to, 'label' => 'Today');
            
        case 'yesterday':
            $from = clone $now;
            $from->modify('-1 day')->setTime(0, 0, 0);
            $to = clone $from;
            $to->setTime(23, 59, 59);
            return array('from' => $from, 'to' => $to, 'label' => 'Yesterday');
            
        case 'week_to_date':
            $from = clone $now;
            $from->modify('monday this week')->setTime(0, 0, 0);
            $to = clone $now;
            $to->setTime(23, 59, 59);
            return array('from' => $from, 'to' => $to, 'label' => 'Week to date');
            
        case 'last_week':
            $from = clone $now;
            $from->modify('monday last week')->setTime(0, 0, 0);
            $to = clone $from;
            $to->modify('+6 days')->setTime(23, 59, 59);
            return array('from' => $from, 'to' => $to, 'label' => 'Last week');
            
        case 'month_to_date':
            $from = clone $now;
            $from->modify('first day of this month')->setTime(0, 0, 0);
            $to = clone $now;
            $to->setTime(23, 59, 59);
            return array('from' => $from, 'to' => $to, 'label' => 'Month to date');
            
        case 'last_month':
            $from = clone $now;
            $from->modify('first day of last month')->setTime(0, 0, 0);
            $to = clone $from;
            $to->modify('last day of this month')->setTime(23, 59, 59);
            return array('from' => $from, 'to' => $to, 'label' => 'Last month');
            
        case 'quarter_to_date':
            $current_month = (int)$now->format('n');
            $quarter_start_month = (floor(($current_month - 1) / 3) * 3) + 1;
            $from = clone $now;
            $from->setDate((int)$now->format('Y'), $quarter_start_month, 1)->setTime(0, 0, 0);
            $to = clone $now;
            $to->setTime(23, 59, 59);
            return array('from' => $from, 'to' => $to, 'label' => 'Quarter to date');
            
        case 'last_quarter':
            $current_month = (int)$now->format('n');
            $last_quarter_start_month = (floor(($current_month - 1) / 3) * 3) - 2;
            if ($last_quarter_start_month < 1) {
                $last_quarter_start_month += 12;
                $year = (int)$now->format('Y') - 1;
            } else {
                $year = (int)$now->format('Y');
            }
            $from = new DateTime("$year-$last_quarter_start_month-01", $timezone);
            $from->setTime(0, 0, 0);
            $to = clone $from;
            $to->modify('+2 months')->modify('last day of this month')->setTime(23, 59, 59);
            return array('from' => $from, 'to' => $to, 'label' => 'Last quarter');
            
        case 'year_to_date':
            $from = clone $now;
            $from->setDate((int)$now->format('Y'), 1, 1)->setTime(0, 0, 0);
            $to = clone $now;
            $to->setTime(23, 59, 59);
            return array('from' => $from, 'to' => $to, 'label' => 'Year to date');
            
        case 'last_year':
            $from = clone $now;
            $from->setDate((int)$now->format('Y') - 1, 1, 1)->setTime(0, 0, 0);
            $to = clone $from;
            $to->setDate((int)$from->format('Y'), 12, 31)->setTime(23, 59, 59);
            return array('from' => $from, 'to' => $to, 'label' => 'Last year');
            
        case 'last_2_hours':
            $from = clone $now;
            $from->modify('-2 hours');
            $to = clone $now;
            return array('from' => $from, 'to' => $to, 'label' => 'Last 2 hours');
            
        case 'last_4_hours':
            $from = clone $now;
            $from->modify('-4 hours');
            $to = clone $now;
            return array('from' => $from, 'to' => $to, 'label' => 'Last 4 hours');
            
        case 'this_week':
            $from = clone $now;
            $from->modify('monday this week')->setTime(0, 0, 0);
            $to = clone $now;
            $to->modify('sunday this week')->setTime(23, 59, 59);
            return array('from' => $from, 'to' => $to, 'label' => 'This week');
            
        default:
            return null;
    }
}

/**
 * Sort order IDs by date created or modified
 * 
 * @param array $order_ids Array of order IDs to sort
 * @param string $orderby Field to sort by ('date_created' or 'date_modified')
 * @param string $order_direction Sort direction ('ASC' or 'DESC')
 * @return array Sorted array of order IDs
 */
function oj_sort_order_ids($order_ids, $orderby, $order_direction) {
    if (empty($order_ids)) {
        return $order_ids;
    }
    
    // Fetch orders and sort them
    $orders_with_dates = array();
    foreach ($order_ids as $order_id) {
        $wc_order = wc_get_order($order_id);
        if (!$wc_order) continue;
        
        $orders_with_dates[] = array(
            'id' => $order_id,
            'date_created' => $wc_order->get_date_created() ? $wc_order->get_date_created()->getTimestamp() : 0,
            'date_modified' => $wc_order->get_date_modified() ? $wc_order->get_date_modified()->getTimestamp() : 0,
        );
    }
    
    // Sort by the selected field
    usort($orders_with_dates, function($a, $b) use ($orderby, $order_direction) {
        $field = ($orderby === 'date_modified') ? 'date_modified' : 'date_created';
        
        if ($order_direction === 'ASC') {
            return $a[$field] - $b[$field];
        } else {
            return $b[$field] - $a[$field];
        }
    });
    
    // Extract sorted IDs
    return array_map(function($item) {
        return $item['id'];
    }, $orders_with_dates);
}

/**
 * Get action buttons for Orders Reports (view-only mode)
 * 
 * @param array $order_data Order data array
 * @return string HTML for action buttons
 */
function oj_reports_get_action_buttons($order_data) {
    $order_id = $order_data['id'];
    $status = $order_data['status'];
    
    $buttons = '';
    
    // Only show "View Details" button for reports
    $buttons .= sprintf(
        '<button class="oj-action-btn secondary oj-view-details" data-order-id="%s">👁️ %s</button>',
        esc_attr($order_id),
        __('View Details', 'orders-jet')
    );
    
    // For completed orders, also show invoice link if it's a table order
    if ($status === 'completed' && !empty($order_data['table'])) {
        $buttons .= sprintf(
            '<button class="oj-action-btn secondary oj-view-invoice" data-order-id="%s" data-table-number="%s">📄 %s</button>',
            esc_attr($order_id),
            esc_attr($order_data['table']),
            __('Invoice', 'orders-jet')
        );
    }
    
    return $buttons;
}

