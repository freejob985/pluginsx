<?php
declare(strict_types=1);
/**
 * Orders Jet - Helpers Class
 * Helper functions for the Orders Jet system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Helpers {
    
    /**
     * Send order notification to staff
     */
    public static function send_order_notification($order) {
        // Get restaurant email (you can customize this)
        $restaurant_email = get_option('admin_email');
        $table_number = $order->get_meta('_oj_table_number');
        
        $subject = sprintf(__('New Order from Table %s', 'orders-jet'), $table_number);
        $message = sprintf(__('A new order has been placed from Table %s. Order #%s', 'orders-jet'), $table_number, $order->get_order_number());
        
        // Add order details
        $message .= "\n\n" . __('Order Details:', 'orders-jet') . "\n";
        $message .= __('Order Number:', 'orders-jet') . ' ' . $order->get_order_number() . "\n";
        $message .= __('Table:', 'orders-jet') . ' ' . $table_number . "\n";
        $message .= __('Total:', 'orders-jet') . ' ' . $order->get_formatted_order_total() . "\n";
        
        if ($order->get_customer_note()) {
            $message .= __('Special Requests:', 'orders-jet') . ' ' . $order->get_customer_note() . "\n";
        }
        
        $message .= "\n" . __('View Order:', 'orders-jet') . ' ' . admin_url('post.php?post=' . $order->get_id() . '&action=edit');
        
        wp_mail($restaurant_email, $subject, $message);
    }
    
    /**
     * Get table ID by number
     */
    public static function get_table_id_by_number($table_number) {
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
     * Get current order for table
     */
    public static function get_current_table_order($table_number) {
        $orders = wc_get_orders(array(
            'status' => array('processing', 'on-hold'),
            'limit' => 1,
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            )
        ));
        
        return !empty($orders) ? $orders[0] : false;
    }
    
    
    /**
     * Get table status options
     */
    public static function get_table_status_options() {
        return array(
            'available' => __('Available', 'orders-jet'),
            'occupied' => __('Occupied', 'orders-jet'),
            'reserved' => __('Reserved', 'orders-jet'),
            'maintenance' => __('Maintenance', 'orders-jet')
        );
    }
    
    /**
     * Check if table is available for ordering
     */
    public static function is_table_available($table_id) {
        $status = get_post_meta($table_id, '_oj_table_status', true);
        return in_array($status, array('available', 'occupied'));
    }
    
    /**
     * Get table orders for admin
     */
    public static function get_table_orders_for_admin($table_number, $limit = 10) {
        $orders = wc_get_orders(array(
            'status' => array('processing', 'on-hold', 'completed'),
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            )
        ));
        
        return $orders;
    }
    
    /**
     * Update table status
     */
    public static function update_table_status($table_id, $status) {
        $valid_statuses = array_keys(self::get_table_status_options());
        
        if (in_array($status, $valid_statuses)) {
            update_post_meta($table_id, '_oj_table_status', $status);
            return true;
        }
        
        return false;
    }
    
    
    
    
}

// Global helper functions for backward compatibility
function oj_send_order_notification($order) {
    return Orders_Jet_Helpers::send_order_notification($order);
}

function oj_get_table_id_by_number($table_number) {
    return Orders_Jet_Helpers::get_table_id_by_number($table_number);
}

function oj_get_current_table_order($table_number) {
    return Orders_Jet_Helpers::get_current_table_order($table_number);
}

function oj_get_table_orders_for_admin($table_number, $limit = 10) {
    return Orders_Jet_Helpers::get_table_orders_for_admin($table_number, $limit);
}

/**
 * WooFood Location Integration Helper Functions
 */

/**
 * Get tables by WooFood location ID
 * OPTIMIZED: Fixed numberposts -1 performance issue + added caching
 */
function oj_get_tables_by_woofood_location($location_id, $use_cache = true) {
    // PERFORMANCE: Add caching for location-based table queries
    $cache_key = 'oj_woofood_tables_' . $location_id;
    
    if ($use_cache) {
        $cached_tables = get_transient($cache_key);
        if ($cached_tables !== false) {
            oj_debug_log('Serving WooFood tables from cache for location: ' . $location_id, 'HELPERS');
            return $cached_tables;
        }
    }
    
    // PERFORMANCE FIX: Use reasonable limit instead of -1
    $tables = get_posts(array(
        'post_type' => 'oj_table',
        'post_status' => 'publish',
        'meta_query' => array(
            array(
                'key' => '_oj_woofood_location_id',
                'value' => $location_id,
                'compare' => '='
            )
        ),
        'numberposts' => 200, // Reasonable limit for location tables
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    // Cache for 5 minutes (table assignments don't change frequently)
    if ($use_cache) {
        set_transient($cache_key, $tables, 300);
    }
    
    return $tables;
}


/**
 * Get all available WooFood locations
 */
function oj_get_available_woofood_locations() {
    if (!class_exists('EX_WooFood')) {
        return array();
    }
    
    return get_terms(array(
        'taxonomy' => 'exwoofood_loc',
        'hide_empty' => false
    ));
}



/**
 * Filter products by WooFood location
 * OPTIMIZED: Fixed N+1 query problem by bulk-fetching product locations
 */
function oj_filter_products_by_woofood_location($products, $location_id) {
    if (!$location_id || !class_exists('EX_WooFood') || empty($products)) {
        return $products;
    }
    
    // PERFORMANCE FIX: Bulk fetch all product locations in one query
    $product_ids = array();
    foreach ($products as $product) {
        $product_ids[] = $product->get_id();
    }
    
    $all_product_locations = oj_get_products_woofood_locations_bulk($product_ids);
    
    $filtered_products = array();
    
    foreach ($products as $product) {
        $product_id = $product->get_id();
        $product_location_ids = $all_product_locations[$product_id] ?? array();
        
        if (empty($product_location_ids) || in_array($location_id, $product_location_ids)) {
            $filtered_products[] = $product;
        }
    }
    
    return $filtered_products;
}

/**
 * PERFORMANCE OPTIMIZATION: Bulk fetch product WooFood locations for multiple products
 * This replaces N+1 wp_get_post_terms() calls with a single optimized query
 */
function oj_get_products_woofood_locations_bulk($product_ids) {
    if (empty($product_ids)) {
        return array();
    }
    
    global $wpdb;
    
    // Sanitize product IDs
    $product_ids = array_map('intval', $product_ids);
    $product_ids_str = implode(',', $product_ids);
    
    // Single optimized query to get all product-location relationships
    $query = "
        SELECT p.ID as product_id, t.term_id as location_id
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
        WHERE p.ID IN ({$product_ids_str})
        AND tt.taxonomy = 'exwoofood_loc'
        ORDER BY p.ID, t.term_id
    ";
    
    $results = $wpdb->get_results($query);
    
    // Group results by product ID
    $product_locations = array();
    
    // Initialize all product IDs with empty arrays
    foreach ($product_ids as $product_id) {
        $product_locations[$product_id] = array();
    }
    
    // Populate with actual location data
    foreach ($results as $row) {
        $product_locations[$row->product_id][] = intval($row->location_id);
    }
    
    oj_debug_log('Bulk fetched WooFood locations for ' . count($product_ids) . ' products in single query', 'HELPERS');
    
    return $product_locations;
}

/**
 * Get table count by WooFood location
 * OPTIMIZED: Uses cached table data
 */
function oj_get_table_count_by_woofood_location($location_id) {
    $tables = oj_get_tables_by_woofood_location($location_id, true);
    return count($tables);
}

/**
 * Get active orders count by WooFood location
 * OPTIMIZED: Uses direct SQL query instead of nested loops
 */
function oj_get_active_orders_count_by_woofood_location($location_id) {
    // PERFORMANCE: Use direct SQL query instead of nested loops
    global $wpdb;
    
    $query = "
        SELECT COUNT(DISTINCT o.ID) as order_count
        FROM {$wpdb->posts} o
        INNER JOIN {$wpdb->postmeta} om ON o.ID = om.post_id
        INNER JOIN {$wpdb->posts} t ON om.meta_value = t.post_title
        INNER JOIN {$wpdb->postmeta} tm ON t.ID = tm.post_id
        WHERE o.post_type = 'shop_order'
        AND o.post_status IN ('wc-processing', 'wc-on-hold')
        AND om.meta_key = '_oj_table_number'
        AND t.post_type = 'oj_table'
        AND tm.meta_key = '_oj_woofood_location_id'
        AND tm.meta_value = %s
    ";
    
    $count = $wpdb->get_var($wpdb->prepare($query, $location_id));
    
    oj_debug_log('Active orders count for location ' . $location_id . ': ' . $count, 'HELPERS');
    
    return intval($count);
}

/**
 * Get WooFood location statistics
 * OPTIMIZED: Uses bulk operations and caching to avoid nested loops
 */
function oj_get_woofood_location_stats($location_id) {
    // PERFORMANCE: Add caching for expensive statistics
    $cache_key = 'oj_woofood_stats_' . $location_id;
    $cached_stats = get_transient($cache_key);
    
    if ($cached_stats !== false) {
        oj_debug_log('Serving WooFood location stats from cache for location: ' . $location_id, 'HELPERS');
        return $cached_stats;
    }
    
    $tables = oj_get_tables_by_woofood_location($location_id, true);
    $stats = array(
        'total_tables' => count($tables),
        'occupied_tables' => 0,
        'available_tables' => 0,
        'active_orders' => 0
    );
    
    if (empty($tables)) {
        // Cache empty results for 1 minute
        set_transient($cache_key, $stats, 60);
        return $stats;
    }
    
    // PERFORMANCE: Bulk fetch all table metadata
    $table_ids = array_map(function($table) {
        return $table->ID;
    }, $tables);
    
    $table_meta = oj_get_tables_meta_bulk($table_ids, array('_oj_table_status', '_oj_table_number'));
    
    // PERFORMANCE: Get active orders count using optimized function
    $stats['active_orders'] = oj_get_active_orders_count_by_woofood_location($location_id);
    
    foreach ($tables as $table) {
        $table_id = $table->ID;
        $status = $table_meta[$table_id]['_oj_table_status'] ?? 'available';
        $table_number = $table_meta[$table_id]['_oj_table_number'] ?? '';
        
        // Simple status-based counting (active orders already calculated above)
        if ($status === 'occupied' || ($table_number && $stats['active_orders'] > 0)) {
            $stats['occupied_tables']++;
        } else {
            $stats['available_tables']++;
        }
    }
    
    // Cache for 2 minutes (stats change frequently)
    set_transient($cache_key, $stats, 120);
    
    return $stats;
}

/**
 * PERFORMANCE OPTIMIZATION: Bulk fetch table metadata for multiple tables
 * This replaces N get_post_meta() calls with a single optimized query
 */
function oj_get_tables_meta_bulk($table_ids, $meta_keys) {
    if (empty($table_ids) || empty($meta_keys)) {
        return array();
    }
    
    global $wpdb;
    
    // Sanitize inputs
    $table_ids = array_map('intval', $table_ids);
    $meta_keys = array_map('sanitize_key', $meta_keys);
    
    $table_ids_str = implode(',', $table_ids);
    $meta_keys_str = "'" . implode("','", $meta_keys) . "'";
    
    // Single optimized query to get all table metadata
    $query = "
        SELECT post_id, meta_key, meta_value
        FROM {$wpdb->postmeta}
        WHERE post_id IN ({$table_ids_str})
        AND meta_key IN ({$meta_keys_str})
        ORDER BY post_id, meta_key
    ";
    
    $results = $wpdb->get_results($query);
    
    // Group results by table ID and meta key
    $table_meta = array();
    
    // Initialize all table IDs with empty arrays
    foreach ($table_ids as $table_id) {
        $table_meta[$table_id] = array();
        foreach ($meta_keys as $meta_key) {
            $table_meta[$table_id][$meta_key] = '';
        }
    }
    
    // Populate with actual metadata
    foreach ($results as $row) {
        $table_meta[$row->post_id][$row->meta_key] = $row->meta_value;
    }
    
    oj_debug_log('Bulk fetched metadata for ' . count($table_ids) . ' tables', 'HELPERS');
    
    return $table_meta;
}

/**
 * Clear WooFood location caches (call when tables/locations are updated)
 */
function oj_clear_woofood_location_cache($location_id = null) {
    if ($location_id) {
        delete_transient('oj_woofood_tables_' . $location_id);
        delete_transient('oj_woofood_stats_' . $location_id);
        oj_debug_log('Cleared WooFood cache for location: ' . $location_id, 'HELPERS');
    } else {
        // Clear all WooFood-related caches
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_oj_woofood_%' OR option_name LIKE '_transient_timeout_oj_woofood_%'");
        oj_debug_log('Cleared all WooFood location caches', 'HELPERS');
    }
}

/**
 * Order Type Detection Functions
 */

/**
 * Get order type from WooCommerce order
 */
function oj_get_order_type($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        return 'unknown';
    }
    
    // Check Orders Jet order method first (most reliable)
    $oj_method = $order->get_meta('_oj_order_method');
    if ($oj_method) {
        return sanitize_text_field($oj_method);
    }
    
    // Check WooFood order method
    $woofood_method = $order->get_meta('exwf_order_method');
    if ($woofood_method) {
        return sanitize_text_field($woofood_method);
    }
    
    // Check for WooFood delivery/pickup meta
    $woofood_delivery = $order->get_meta('exwf_delivery_date');
    $woofood_pickup = $order->get_meta('exwf_pickup_date');
    
    if ($woofood_delivery) {
        return 'delivery';
    }
    
    if ($woofood_pickup) {
        return 'takeaway';
    }
    
    // Check shipping method as fallback
    $shipping_methods = $order->get_shipping_methods();
    if (!empty($shipping_methods)) {
        $shipping_method = reset($shipping_methods);
        $method_id = $shipping_method->get_method_id();
        $method_title = strtolower($shipping_method->get_method_title());
        
        if (strpos($method_id, 'delivery') !== false || strpos($method_title, 'delivery') !== false) {
            return 'delivery';
        }
        
        if (strpos($method_id, 'pickup') !== false || strpos($method_title, 'pickup') !== false || 
            strpos($method_title, 'takeaway') !== false || strpos($method_title, 'take away') !== false) {
            return 'takeaway';
        }
    }
    
    // Check if it's a table order (Orders Jet dine-in)
    $table_number = $order->get_meta('_oj_table_number');
    if ($table_number) {
        return 'dinein';
    }
    
    // Check billing details for clues
    $billing_first_name = $order->get_billing_first_name();
    if (strpos($billing_first_name, 'Table') === 0) {
        return 'dinein';
    }
    
    // Default fallback - check if there's any shipping
    if ($order->needs_shipping()) {
        return 'delivery';
    }
    
    return 'unknown';
}

/**
 * Get order type display label
 */
function oj_get_order_type_label($type) {
    $labels = array(
        'dinein' => __('Dine In', 'orders-jet'),
        'delivery' => __('Delivery', 'orders-jet'),
        'takeaway' => __('Takeaway', 'orders-jet'),
        'pickup' => __('Pickup', 'orders-jet'),
        'unknown' => __('Standard', 'orders-jet')
    );
    
    return isset($labels[$type]) ? $labels[$type] : $labels['unknown'];
}

/**
 * Get order type icon
 */
function oj_get_order_type_icon($type) {
    $icons = array(
        'dinein' => '🍽️',
        'delivery' => '🚚',
        'takeaway' => '🥡',
        'pickup' => '👜',
        'unknown' => '📋'
    );
    
    return isset($icons[$type]) ? $icons[$type] : $icons['unknown'];
}

/**
 * Get order type CSS class
 */
function oj_get_order_type_class($type) {
    return 'oj-order-type-' . sanitize_html_class($type);
}

/**
 * Get order type color scheme
 */
function oj_get_order_type_colors($type) {
    $colors = array(
        'dinein' => array(
            'bg' => '#4CAF50',
            'bg_light' => '#E8F5E8',
            'text' => '#ffffff',
            'border' => '#45a049'
        ),
        'delivery' => array(
            'bg' => '#2196F3',
            'bg_light' => '#E3F2FD',
            'text' => '#ffffff',
            'border' => '#1976D2'
        ),
        'takeaway' => array(
            'bg' => '#FF9800',
            'bg_light' => '#FFF3E0',
            'text' => '#ffffff',
            'border' => '#F57C00'
        ),
        'pickup' => array(
            'bg' => '#9C27B0',
            'bg_light' => '#F3E5F5',
            'text' => '#ffffff',
            'border' => '#7B1FA2'
        ),
        'unknown' => array(
            'bg' => '#757575',
            'bg_light' => '#F5F5F5',
            'text' => '#ffffff',
            'border' => '#616161'
        )
    );
    
    return isset($colors[$type]) ? $colors[$type] : $colors['unknown'];
}

