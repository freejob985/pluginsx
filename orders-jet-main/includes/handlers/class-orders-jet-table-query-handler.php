<?php
declare(strict_types=1);
/**
 * Orders Jet - Table Query Handler Class
 * Handles complex table order querying logic extracted from AJAX handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Table_Query_Handler {
    
    /**
     * Get orders for a specific table
     * 
     * @param array $post_data The $_POST data from AJAX request
     * @return array Success response data
     * @throws Exception On processing errors
     */
    public function get_orders($post_data) {
        $table_number = sanitize_text_field($post_data['table_number']);
        
        oj_debug_log("Getting orders for table: {$table_number}", 'TABLE_QUERY');
        
        if (empty($table_number)) {
            throw new Exception(__('Table number is required', 'orders-jet'));
        }
        
        // Get orders using multiple methods for reliability
        $orders = $this->fetch_table_orders($table_number);
        
        // Process orders and build response data
        $order_data = array();
        $total_amount = 0;
        
        oj_debug_log("Found " . count($orders) . " orders for table {$table_number}", 'TABLE_QUERY');
        
        foreach ($orders as $order_post) {
            $order = wc_get_order($order_post->ID);
            if (!$order) continue;
            
            $order_items = $this->process_order_items($order);
            
            $order_data[] = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status' => $order->get_status(),
                'total' => wc_price($order->get_total()),
                'items' => $order_items,
                'date' => $order->get_date_created()->format('Y-m-d H:i:s')
            );
            
            $total_amount += $order->get_total();
        }
        
        // Generate debug information
        $debug_info = $this->generate_debug_info($table_number);
        
        return array(
            'orders' => $order_data,
            'total' => $total_amount,
            'debug' => array(
                'searched_table' => $table_number,
                'recent_orders' => $debug_info
            )
        );
    }
    
    /**
     * Fetch table orders using centralized query service
     * OPTIMIZED: Uses centralized query service to eliminate duplicate logic
     * FIXED: Exclude completed orders from guest QR menu history (Issue #2)
     * FIXED: Reduce cache duration for faster new order display (Issue #1)
     */
    private function fetch_table_orders($table_number) {
        // Use centralized query service with active order statuses only
        // FIXED: Exclude completed orders from guest QR menu history
        // FIXED: Reduce cache duration to show new orders faster
        $options = array(
            'statuses' => array('wc-pending', 'wc-processing', 'wc-on-hold'), // Active orders only, no completed
            'limit' => 20, // Performance limit
            'orderby' => 'date',
            'order' => 'DESC',
            'cache' => true,
            'cache_duration' => 10 // Reduced from 30s to 10s for faster new order display
        );
        
        return oj_query_service()->get_table_orders($table_number, $options);
    }
    
    /**
     * Process order items and extract detailed information
     */
    private function process_order_items($order) {
        $order_items = array();
        
        foreach ($order->get_items() as $item) {
            // Get basic item info
            $item_data = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => wc_price($item->get_total()),
                'unit_price' => wc_price($item->get_total() / $item->get_quantity()),
                'base_price' => 0, // Will be set below for variant products
                'variations' => array(),
                'addons' => array(),
                'notes' => ''
            );
            
            // Process variations and metadata
            $this->process_item_variations($item, $item_data);
            $this->process_item_metadata($item, $item_data);
            $this->calculate_base_price($item, $item_data);
            
            oj_debug_log("Final base_price: {$item_data['base_price']}", 'PRICE_CALC');
            
            $order_items[] = $item_data;
        }
        
        return $order_items;
    }
    
    /**
     * Process item variations using WooCommerce native methods
     */
    private function process_item_variations($item, &$item_data) {
        $product = $item->get_product();
        if ($product && $product->is_type('variation')) {
            // For variation products, get variation attributes directly
            $variation_attributes = $product->get_variation_attributes();
            foreach ($variation_attributes as $attribute_name => $attribute_value) {
                if (!empty($attribute_value)) {
                    // Clean attribute name and get proper label
                    $clean_attribute_name = str_replace('attribute_', '', $attribute_name);
                    $attribute_label = wc_attribute_label($clean_attribute_name);
                    $item_data['variations'][$attribute_label] = $attribute_value;
                }
            }
        }
    }
    
    /**
     * Process item metadata for add-ons, notes, and custom variations
     */
    private function process_item_metadata($item, &$item_data) {
        $item_meta = $item->get_meta_data();
        foreach ($item_meta as $meta) {
            $meta_key = $meta->key;
            $meta_value = $meta->value;
            
            // Get add-ons (prefer structured data if available)
            if ($meta_key === '_oj_addons_data' && is_array($meta_value)) {
                $item_data['addons'] = array_map(function($addon) {
                    return $addon['name'] . ' (+' . wc_price($addon['price']) . ')';
                }, $meta_value);
            } elseif ($meta_key === '_oj_item_addons' && empty($item_data['addons'])) {
                $addons = explode(', ', $meta_value);
                $item_data['addons'] = array_map(function($addon) {
                    return strip_tags($addon);
                }, $addons);
            }
            
            // Get custom variations (for non-variation products)
            if ($meta_key === '_oj_variations_data' && is_array($meta_value) && empty($item_data['variations'])) {
                foreach ($meta_value as $variation) {
                    $item_data['variations'][$variation['name']] = $variation['value'] ?? $variation['name'];
                }
            } elseif ($meta_key === '_oj_item_variations' && empty($item_data['variations'])) {
                // Parse the old format as fallback
                $variations = explode(', ', $meta_value);
                foreach ($variations as $variation_string) {
                    if (preg_match('/^(.+?)\s*\(\+/', $variation_string, $matches)) {
                        $item_data['variations'][$matches[1]] = $matches[1];
                    }
                }
            }
            
            // Also check for standard WooCommerce variation attributes in meta (fallback)
            if (empty($item_data['variations']) && (strpos($meta_key, 'pa_') === 0 || strpos($meta_key, 'attribute_') === 0)) {
                $attribute_name = str_replace(array('pa_', 'attribute_'), '', $meta_key);
                $attribute_label = wc_attribute_label($attribute_name);
                $item_data['variations'][$attribute_label] = $meta_value;
            }
            
            // Get notes
            if ($meta_key === '_oj_item_notes') {
                $item_data['notes'] = $meta_value;
            }
        }
    }
    
    /**
     * Calculate base price for the item
     */
    private function calculate_base_price($item, &$item_data) {
        $base_price_found = false;
        
        // Check if we stored the original variant price in meta data
        $stored_base_price = $item->get_meta('_oj_base_price');
        if ($stored_base_price) {
            $item_data['base_price'] = floatval($stored_base_price);
            $base_price_found = true;
            oj_debug_log("Using stored base price: {$item_data['base_price']}", 'PRICE_CALC');
        }
        
        // If no stored price, try to calculate from current data
        if (!$base_price_found) {
            $product = $item->get_product();
            
            // Debug logging
            oj_debug_log("Product analysis", 'PRICE_CALC', array(
                'product_id' => $product ? $product->get_id() : null,
                'product_type' => $product ? $product->get_type() : null,
                'item_total' => $item->get_total(),
                'item_quantity' => $item->get_quantity()
            ));
            
            // Check if this is a variation product
            if ($product && $product->is_type('variation')) {
                $variation_price = $product->get_price();
                $item_data['base_price'] = $variation_price;
                $base_price_found = true;
                oj_debug_log("Variation product price: {$variation_price}", 'PRICE_CALC');
                
                // Get variation attributes
                $variation_attributes = $product->get_variation_attributes();
                foreach ($variation_attributes as $attribute_name => $attribute_value) {
                    if (!empty($attribute_value)) {
                        $attribute_label = wc_attribute_label($attribute_name);
                        $item_data['variations'][$attribute_label] = $attribute_value;
                    }
                }
            } else {
                // For non-variation products, calculate base price by subtracting add-ons
                // Use pre-calculated addon data if available, otherwise fallback to legacy method
                if (Orders_Jet_Addon_Calculator::is_cache_initialized()) {
                    $addon_total = Orders_Jet_Addon_Calculator::get_item_base_price($order->get_id(), $item->get_id());
                } else {
                    $addon_total = $this->calculate_addon_total($item_data['addons'], $item->get_quantity());
                }
                
                $item_total = $item->get_total();
                $base_price = ($item_total - $addon_total) / $item->get_quantity();
                $item_data['base_price'] = $base_price;
                $base_price_found = true;
                
                oj_debug_log("Base price calculation", 'PRICE_CALC', array(
                    'base_price' => $base_price,
                    'item_total' => $item_total,
                    'addon_total' => $addon_total
                ));
            }
        }
    }
    
    /**
     * Calculate total add-on cost (Legacy method - now uses optimized calculator)
     */
    private function calculate_addon_total($addons, $quantity) {
        // Use the optimized addon calculator for better performance
        return Orders_Jet_Addon_Calculator::calculate_addon_total_legacy($addons, $quantity);
    }
    
    /**
     * Generate debug information for troubleshooting
     */
    private function generate_debug_info($table_number) {
        // Get recent orders for debugging
        $recent_orders = get_posts(array(
            'post_type' => 'shop_order',
            'post_status' => array_keys(wc_get_order_statuses()),
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        oj_debug_log("Recent orders count: " . count($recent_orders), 'DEBUG_INFO');
        foreach ($recent_orders as $recent_order) {
            $order = wc_get_order($recent_order->ID);
            if ($order) {
                $table_meta = $order->get_meta('_oj_table_number');
                $total = $order->get_total();
                $billing_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                oj_debug_log("Recent order analysis", 'DEBUG_INFO', array(
                    'order_id' => $order->get_id(),
                    'table' => $table_meta,
                    'status' => $order->get_status(),
                    'total' => $total,
                    'billing' => $billing_name
                ));
            }
        }
        
        // Specifically check for order ID 214 (debug case)
        $test_order = wc_get_order(214);
        if ($test_order) {
            oj_debug_log("Test order 214 found", 'DEBUG_INFO', array(
                'status' => $test_order->get_status(),
                'table' => $test_order->get_meta('_oj_table_number'),
                'total' => $test_order->get_total()
            ));
        } else {
            oj_debug_log("Test order 214 NOT FOUND", 'DEBUG_INFO');
        }
        
        // Prepare debug information
        $debug_info = array();
        foreach ($recent_orders as $recent_order) {
            $order = wc_get_order($recent_order->ID);
            if ($order) {
                $debug_info[] = array(
                    'id' => $order->get_id(),
                    'table' => $order->get_meta('_oj_table_number'),
                    'status' => $order->get_status(),
                    'total' => $order->get_total(),
                    'billing' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()
                );
            }
        }
        
        return $debug_info;
    }
}
