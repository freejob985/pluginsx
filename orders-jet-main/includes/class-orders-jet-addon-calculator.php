<?php
declare(strict_types=1);
/**
 * Orders Jet - Addon Calculator
 * Pre-calculates addon prices outside loops for performance optimization
 * 
 * Performance Optimization: Solution 3 - Pre-calculate addons
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Addon_Calculator {
    
    private static $addon_cache = array();
    private static $cache_initialized = false;
    
    /**
     * Pre-calculate all addon totals for multiple orders
     * This replaces individual addon calculations in loops
     */
    public static function precalculate_addon_totals($order_ids) {
        global $wpdb;
        
        if (empty($order_ids)) {
            return;
        }
        
        $start_time = microtime(true);
        
        // Clear previous cache
        self::$addon_cache = array();
        
        $ids_placeholder = implode(',', array_fill(0, count($order_ids), '%d'));
        
        oj_debug_log("Pre-calculating addon totals for " . count($order_ids) . " orders", 'ADDON_CALC');
        
        // Single query to get all addon data
        $sql = "
            SELECT oi.order_id, 
                   oi.order_item_id,
                   oi.order_item_name,
                   addon.meta_value as addon_data,
                   qty.meta_value as quantity,
                   total.meta_value as line_total
            FROM {$wpdb->prefix}woocommerce_order_items oi
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta addon ON oi.order_item_id = addon.order_item_id AND addon.meta_key = '_oj_addons_data'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta qty ON oi.order_item_id = qty.order_item_id AND qty.meta_key = '_qty'  
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta total ON oi.order_item_id = total.order_item_id AND total.meta_key = '_line_total'
            WHERE oi.order_id IN ($ids_placeholder)
            AND oi.order_item_type = 'line_item'
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$order_ids));
        
        // Process and cache results
        foreach ($results as $row) {
            $order_id = $row->order_id;
            $item_id = $row->order_item_id;
            
            if (!isset(self::$addon_cache[$order_id])) {
                self::$addon_cache[$order_id] = array(
                    'total_addon_cost' => 0,
                    'items' => array()
                );
            }
            
            $addon_total = 0;
            $addon_details = array();
            
            if (!empty($row->addon_data)) {
                $addon_data = maybe_unserialize($row->addon_data);
                if (is_array($addon_data)) {
                    foreach ($addon_data as $addon) {
                        $addon_price = floatval($addon['price'] ?? 0);
                        $addon_quantity = intval($addon['quantity'] ?? 1);
                        $addon_name = sanitize_text_field($addon['name'] ?? 'Add-on');
                        
                        $addon_item_total = $addon_price * $addon_quantity;
                        $addon_total += $addon_item_total;
                        
                        $addon_details[] = array(
                            'name' => $addon_name,
                            'price' => $addon_price,
                            'quantity' => $addon_quantity,
                            'total' => $addon_item_total
                        );
                    }
                }
            }
            
            $item_quantity = intval($row->quantity);
            $total_addon_for_item = $addon_total * $item_quantity;
            $line_total = floatval($row->line_total);
            
            // Calculate base price by subtracting addon total from line total
            $base_price = $line_total > 0 ? ($line_total - $total_addon_for_item) / $item_quantity : 0;
            
            self::$addon_cache[$order_id]['items'][$item_id] = array(
                'item_name' => $row->order_item_name,
                'addon_total_per_item' => $addon_total,
                'total_with_quantity' => $total_addon_for_item,
                'base_price' => max(0, $base_price), // Ensure non-negative
                'addon_details' => $addon_details,
                'item_quantity' => $item_quantity,
                'line_total' => $line_total
            );
            
            self::$addon_cache[$order_id]['total_addon_cost'] += $total_addon_for_item;
        }
        
        self::$cache_initialized = true;
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        oj_perf_log("Addon pre-calculation completed for " . count(self::$addon_cache) . " orders", $execution_time, 'ADDON_CALC');
    }
    
    /**
     * Get cached addon total for an order
     */
    public static function get_order_addon_total($order_id) {
        return self::$addon_cache[$order_id]['total_addon_cost'] ?? 0;
    }
    
    /**
     * Get cached base price for an item
     */
    public static function get_item_base_price($order_id, $item_id) {
        return self::$addon_cache[$order_id]['items'][$item_id]['base_price'] ?? 0;
    }
    
    /**
     * Get cached addon details for an item
     */
    public static function get_item_addon_details($order_id, $item_id) {
        return self::$addon_cache[$order_id]['items'][$item_id]['addon_details'] ?? array();
    }
    
    /**
     * Get all cached data for an order
     */
    public static function get_order_addon_data($order_id) {
        return self::$addon_cache[$order_id] ?? array(
            'total_addon_cost' => 0,
            'items' => array()
        );
    }
    
    /**
     * Calculate addon total from addon string (legacy method for backward compatibility)
     * This is the old method that was called in loops - now optimized
     */
    public static function calculate_addon_total_legacy($addons, $quantity) {
        $addon_total = 0;
        if (!empty($addons)) {
            foreach ($addons as $addon_string) {
                // Extract price from add-on string like "Extra 2 (+100.00 EGP)"
                preg_match('/\(([^)]+)\)/', $addon_string, $matches);
                if (isset($matches[1])) {
                    $price_string = $matches[1];
                    preg_match('/[\d,]+\.?\d*/', $price_string, $price_matches);
                    if (isset($price_matches[0])) {
                        $addon_price = floatval(str_replace(',', '.', $price_matches[0]));
                        $addon_total += $addon_price * $quantity;
                    }
                }
            }
        }
        return $addon_total;
    }
    
    /**
     * Check if cache is initialized
     */
    public static function is_cache_initialized() {
        return self::$cache_initialized;
    }
    
    /**
     * Clear addon cache
     */
    public static function clear_cache() {
        self::$addon_cache = array();
        self::$cache_initialized = false;
        oj_debug_log("Addon cache cleared", 'ADDON_CALC');
    }
    
    /**
     * Get cache statistics for debugging
     */
    public static function get_cache_stats() {
        $total_orders = count(self::$addon_cache);
        $total_items = 0;
        $total_addons = 0;
        
        foreach (self::$addon_cache as $order_data) {
            $total_items += count($order_data['items']);
            foreach ($order_data['items'] as $item_data) {
                $total_addons += count($item_data['addon_details']);
            }
        }
        
        return array(
            'orders_cached' => $total_orders,
            'items_cached' => $total_items,
            'addons_cached' => $total_addons,
            'cache_initialized' => self::$cache_initialized
        );
    }
}
