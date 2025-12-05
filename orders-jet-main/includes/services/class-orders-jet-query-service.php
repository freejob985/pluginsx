<?php
declare(strict_types=1);
/**
 * Orders Jet - Centralized Query Service
 * Eliminates duplicate query logic across the system
 * 
 * PERFORMANCE OPTIMIZATION: Centralized query handling with caching
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Query_Service {
    
    private static $instance = null;
    private $cache_duration = 30; // 30 seconds default cache
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Centralized table orders query with caching and flexible parameters
     * 
     * @param string $table_number The table number to query
     * @param array $options Query options
     * @return array Array of order post objects
     */
    public function get_table_orders($table_number, $options = array()) {
        // Validate table number
        if (empty($table_number)) {
            return array();
        }
        
        // Set default options
        $defaults = array(
            'statuses' => array('wc-pending', 'wc-processing'), // Default to active orders
            'limit' => 20, // Performance limit
            'orderby' => 'date',
            'order' => 'DESC',
            'cache' => true,
            'cache_duration' => $this->cache_duration
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Generate cache key based on parameters
        $cache_key = $this->generate_cache_key('table_orders', $table_number, $options);
        
        // Try cache first if enabled
        if ($options['cache']) {
            $cached_orders = get_transient($cache_key);
            if ($cached_orders !== false) {
                oj_debug_log("Cache hit for table {$table_number}", 'QUERY_SERVICE');
                return $cached_orders;
            }
        }
        
        // Build query arguments
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => $options['statuses'],
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            ),
            'posts_per_page' => $options['limit'],
            'orderby' => $options['orderby'],
            'order' => $options['order']
        );
        
        // Execute query
        $orders = get_posts($args);
        
        // Fallback to WooCommerce native query if no results and function exists
        if (empty($orders) && function_exists('wc_get_orders')) {
            $orders = $this->fallback_wc_query($table_number, $options);
        }
        
        // Cache results if caching is enabled
        if ($options['cache']) {
            set_transient($cache_key, $orders, $options['cache_duration']);
            oj_debug_log("Cached results for table {$table_number}", 'QUERY_SERVICE');
        }
        
        oj_debug_log("Found " . count($orders) . " orders for table {$table_number}", 'QUERY_SERVICE');
        
        return $orders;
    }
    
    /**
     * Get all orders (not table-specific) with flexible parameters
     * 
     * @param array $options Query options
     * @return array Array of order post objects
     */
    public function get_orders($options = array()) {
        // Set default options
        $defaults = array(
            'statuses' => array('wc-pending', 'wc-processing'),
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
            'cache' => true,
            'cache_duration' => $this->cache_duration,
            'meta_query' => array()
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Generate cache key
        $cache_key = $this->generate_cache_key('orders', '', $options);
        
        // Try cache first if enabled
        if ($options['cache']) {
            $cached_orders = get_transient($cache_key);
            if ($cached_orders !== false) {
                return $cached_orders;
            }
        }
        
        // Build query arguments
        $args = array(
            'post_type' => 'shop_order',
            'post_status' => $options['statuses'],
            'posts_per_page' => $options['limit'],
            'orderby' => $options['orderby'],
            'order' => $options['order']
        );
        
        // Add meta query if provided
        if (!empty($options['meta_query'])) {
            $args['meta_query'] = $options['meta_query'];
        }
        
        // Execute query
        $orders = get_posts($args);
        
        // Cache results if caching is enabled
        if ($options['cache']) {
            set_transient($cache_key, $orders, $options['cache_duration']);
        }
        
        return $orders;
    }
    
    /**
     * Get tables with flexible parameters
     * 
     * @param array $options Query options
     * @return array Array of table post objects
     */
    public function get_tables($options = array()) {
        // Set default options
        $defaults = array(
            'limit' => -1, // Get all tables by default
            'orderby' => 'menu_order',
            'order' => 'ASC',
            'cache' => true,
            'cache_duration' => 300, // 5 minutes for tables (less frequent changes)
            'meta_query' => array()
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Generate cache key
        $cache_key = $this->generate_cache_key('tables', '', $options);
        
        // Try cache first if enabled
        if ($options['cache']) {
            $cached_tables = get_transient($cache_key);
            if ($cached_tables !== false) {
                return $cached_tables;
            }
        }
        
        // Build query arguments
        $args = array(
            'post_type' => 'oj_table',
            'post_status' => 'publish',
            'posts_per_page' => $options['limit'],
            'orderby' => $options['orderby'],
            'order' => $options['order']
        );
        
        // Add meta query if provided
        if (!empty($options['meta_query'])) {
            $args['meta_query'] = $options['meta_query'];
        }
        
        // Add tax query if provided
        if (!empty($options['tax_query'])) {
            $args['tax_query'] = $options['tax_query'];
        }
        
        // Add meta_key if orderby is meta_value
        if ($options['orderby'] === 'meta_value' && !isset($args['meta_key'])) {
            $args['meta_key'] = '_oj_table_number'; // Default meta key for table ordering
        }
        
        // Execute query
        $tables = get_posts($args);
        
        // Cache results if caching is enabled
        if ($options['cache']) {
            set_transient($cache_key, $tables, $options['cache_duration']);
        }
        
        return $tables;
    }
    
    /**
     * Clear cache for specific table or all caches
     * 
     * @param string $table_number Optional table number to clear specific cache
     */
    public function clear_cache($table_number = '') {
        if (!empty($table_number)) {
            // Clear specific table cache
            $cache_patterns = array(
                'oj_query_table_orders_' . sanitize_key($table_number),
                'oj_table_orders_' . sanitize_key($table_number) // Legacy cache key
            );
            
            foreach ($cache_patterns as $pattern) {
                delete_transient($pattern);
            }
            
            oj_debug_log("Cleared cache for table {$table_number}", 'QUERY_SERVICE');
        } else {
            // Clear all query caches (use with caution)
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_oj_query_%'");
            oj_debug_log("Cleared all query caches", 'QUERY_SERVICE');
        }
    }
    
    /**
     * Fallback WooCommerce native query
     * 
     * @param string $table_number Table number
     * @param array $options Query options
     * @return array Array of post objects converted from WC_Order objects
     */
    private function fallback_wc_query($table_number, $options) {
        // Convert post statuses to WC statuses
        $wc_statuses = array();
        foreach ($options['statuses'] as $status) {
            $wc_statuses[] = str_replace('wc-', '', $status);
        }
        
        $wc_orders = wc_get_orders(array(
            'status' => $wc_statuses,
            'meta_key' => '_oj_table_number',
            'meta_value' => $table_number,
            'limit' => $options['limit'],
            'orderby' => $options['orderby'],
            'order' => $options['order']
        ));
        
        // Convert WC_Order objects to post objects for consistency
        $orders = array();
        foreach ($wc_orders as $wc_order) {
            $post = get_post($wc_order->get_id());
            if ($post) {
                $orders[] = $post;
            }
        }
        
        oj_debug_log("Fallback query found " . count($orders) . " orders for table {$table_number}", 'QUERY_SERVICE');
        
        return $orders;
    }
    
    /**
     * Generate cache key based on query parameters
     * 
     * @param string $type Query type (table_orders, orders, tables)
     * @param string $identifier Specific identifier (table number, etc.)
     * @param array $options Query options
     * @return string Cache key
     */
    private function generate_cache_key($type, $identifier, $options) {
        // Create a hash of the options for cache key
        $options_hash = md5(serialize($options));
        $identifier_key = !empty($identifier) ? sanitize_key($identifier) : 'all';
        
        return "oj_query_{$type}_{$identifier_key}_{$options_hash}";
    }
}

/**
 * Global helper function for easy access
 */
function oj_query_service() {
    return Orders_Jet_Query_Service::getInstance();
}
