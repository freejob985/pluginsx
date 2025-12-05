<?php
/**
 * Orders BI Query Builder
 * 
 * Extends Orders_Master_Query_Builder to add Business Intelligence capabilities.
 * Provides grouping, aggregation, and analytics methods for BI dashboard.
 * 
 * @package Orders_Jet
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Orders_BI_Query_Builder
 * 
 * Extends the master query builder with BI-specific functionality:
 * - Data grouping (by day, waiter, shift, table, discount status)
 * - Aggregation methods (count, sum, average)
 * - BI-specific filters and analytics
 */
class Orders_BI_Query_Builder extends Orders_Master_Query_Builder {
    
    /**
     * @var string BI mode (grouped, individual)
     */
    private $bi_mode;
    
    /**
     * @var string Group by field (day, waiter, shift, table, discount_status)
     */
    private $group_by;
    
    /**
     * @var string Shift filter (morning, afternoon, night)
     */
    private $shift_filter;
    
    /**
     * @var bool Discount filter (with_discount, without_discount)
     */
    private $discount_filter;
    
    /**
     * @var string Drill-down group filter (for individual mode)
     */
    private $drill_down_group;
    
    /**
     * @var int Current page for individual orders pagination
     */
    private $current_page;
    
    /**
     * @var int Orders per page for individual mode
     */
    private $per_page;
    
    /**
     * @var array Cached grouped data
     */
    private $grouped_data_cache;
    
    /**
     * Constructor - extends parent with BI parameters and conflict resolution
     * 
     * @param array $params URL parameters from $_GET
     */
    public function __construct($params = array()) {
        // Initialize parent query builder FIRST
        parent::__construct($params);
        
        // BI-specific parameters
        $this->bi_mode = isset($params['bi_mode']) ? sanitize_text_field($params['bi_mode']) : 'grouped';
        $this->group_by = isset($params['group_by']) ? sanitize_text_field($params['group_by']) : 'day';
        $this->drill_down_group = isset($params['drill_down_group']) ? sanitize_text_field($params['drill_down_group']) : '';
        
        // BI-specific filters
        $this->shift_filter = isset($params['shift_filter']) ? sanitize_text_field($params['shift_filter']) : '';
        $this->discount_filter = isset($params['discount_filter']) ? sanitize_text_field($params['discount_filter']) : '';
        
        // Pagination parameters (BI uses 24 per page, overriding parent's 20)
        $this->current_page = isset($params['paged']) ? max(1, intval($params['paged'])) : 1;
        $this->per_page = 24; // BI standard
        
        // CRITICAL: Resolve all filter conflicts after parameters are set
        $this->resolve_all_filter_conflicts();
        
        // CRITICAL: Rebuild base query after conflict resolution
        // This ensures date filters set by drill-down are properly applied
        $this->rebuild_base_query_after_conflicts();
        
        // Initialize cache
        $this->grouped_data_cache = null;
    }
    
    /**
     * Resolve all filter conflicts with proper priority hierarchy
     * 
     * Priority: Drill-Down > BI Mode > BI Filters > Date Filters > Inherited Filters
     */
    private function resolve_all_filter_conflicts() {
        // Phase 1: Handle drill-down conflicts (highest priority)
        if (!empty($this->drill_down_group)) {
            $this->resolve_drill_down_conflicts();
        }
        
        // Phase 2: Handle date conflicts
        $this->resolve_date_conflicts();
        
        // Phase 3: Handle pagination conflicts
        $this->resolve_pagination_conflicts();
        
        // Phase 4: Handle BI-specific conflicts
        $this->resolve_bi_filter_conflicts();
        
        // Log final resolved state for debugging
        $this->log_filter_resolution();
    }
    
    /**
     * Rebuild base query after conflict resolution
     * 
     * This is critical for date drill-downs because the parent class builds
     * the base query before our conflict resolution runs
     */
    private function rebuild_base_query_after_conflicts() {
        // Only rebuild if we have date-related conflicts that were resolved
        if (!empty($this->drill_down_group) && $this->group_by === 'day') {
            // Force rebuild of date-related query args
            $this->rebuild_date_query_args();
        }
        
        // Log the rebuild action
        error_log("BI Query Rebuild - Drill Down: '{$this->drill_down_group}', " .
                 "Group By: '{$this->group_by}', " .
                 "Date From: '{$this->date_from}', " .
                 "Date To: '{$this->date_to}'");
    }
    
    /**
     * Rebuild date-related query arguments using parent class approach
     */
    private function rebuild_date_query_args() {
        if (!empty($this->date_from) && !empty($this->date_to)) {
            // Use the SAME approach as parent class for consistency
            $site_timezone = wp_timezone();
            
            // Convert dates to DateTime objects with proper timezone
            $from_dt = new DateTime($this->date_from . ' 00:00:00', $site_timezone);
            $to_dt = new DateTime($this->date_to . ' 23:59:59', $site_timezone);
            
            // Convert to timestamps (this is what WooCommerce expects)
            $date_from_timestamp = $from_dt->getTimestamp();
            $date_to_timestamp = $to_dt->getTimestamp();
            
            // Set the date_created filter using timestamp range (parent class approach)
            $this->base_query_args['date_created'] = $date_from_timestamp . '...' . $date_to_timestamp;
            
            // Remove any conflicting date parameters
            unset($this->base_query_args['date_query']);
            unset($this->base_query_args['date_after']);
            unset($this->base_query_args['date_before']);
            
            error_log("BI Date Query Rebuilt - " .
                     "Date: '{$this->date_from}', " .
                     "From Timestamp: {$date_from_timestamp}, " .
                     "To Timestamp: {$date_to_timestamp}, " .
                     "Range: '{$date_from_timestamp}...{$date_to_timestamp}'");
        }
    }
    
    /**
     * Resolve drill-down conflicts (overrides conflicting inherited filters)
     */
    private function resolve_drill_down_conflicts() {
        switch ($this->group_by) {
            case 'day':
                // Day drill-down: override all date filters
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->drill_down_group)) {
                    // Clear conflicting date parameters
                    $this->date_preset = '';
                    $this->date_from_dt = null;
                    $this->date_to_dt = null;
                    
                    // Set specific date for drill-down
                    $this->date_from = $this->drill_down_group;
                    $this->date_to = $this->drill_down_group;
                    
                    error_log("BI Day Drill-Down Resolved - Date: '{$this->drill_down_group}', " .
                             "From: '{$this->date_from}', To: '{$this->date_to}'");
                }
                break;
                
            case 'waiter':
                // Waiter drill-down: override waiter filters
                if ($this->drill_down_group === 'unassigned') {
                    $this->assigned_waiter = 0;
                    $this->unassigned_only = true;
                } elseif (strpos($this->drill_down_group, 'waiter_') === 0) {
                    $waiter_id = intval(str_replace('waiter_', '', $this->drill_down_group));
                    $this->assigned_waiter = $waiter_id;
                    $this->unassigned_only = false;
                }
                break;
                
            case 'shift':
                // Shift drill-down: override shift filter
                $this->shift_filter = $this->drill_down_group;
                break;
                
            case 'discount_status':
                // Discount drill-down: override discount filter
                $this->discount_filter = $this->drill_down_group;
                break;
                
            case 'table':
                // Table drill-down: set table context
                if ($this->drill_down_group === 'no_table') {
                    // Filtering for orders without table assignment
                } elseif (strpos($this->drill_down_group, 'table_') === 0) {
                    // Extract table number for potential future table-specific filtering
                    $table_number = str_replace('table_', '', $this->drill_down_group);
                }
                break;
        }
    }
    
    /**
     * Resolve date filter conflicts
     */
    private function resolve_date_conflicts() {
        // Date preset overrides manual date selection (unless drill-down is active for day)
        if (!empty($this->date_preset) && 
            (empty($this->drill_down_group) || $this->group_by !== 'day')) {
            // Clear manual dates when preset is used
            $this->date_from = '';
            $this->date_to = '';
        }
    }
    
    /**
     * Resolve pagination conflicts
     */
    private function resolve_pagination_conflicts() {
        // Reset pagination for grouped mode (no pagination needed)
        if ($this->bi_mode === 'grouped') {
            $this->current_page = 1;
        }
        
        // Ensure BI pagination settings override parent
        $this->per_page = 24;
    }
    
    /**
     * Resolve BI-specific filter conflicts
     */
    private function resolve_bi_filter_conflicts() {
        // Currently no BI-specific conflicts, but placeholder for future filters
        // Example: if we add table_filter, it might conflict with drill_down_group
    }
    
    /**
     * Log filter resolution for debugging
     */
    private function log_filter_resolution() {
        error_log("BI Filter Resolution Complete - " .
            "Mode: {$this->bi_mode}, " .
            "Group By: {$this->group_by}, " .
            "Drill Down: '{$this->drill_down_group}', " .
            "Date Preset: '{$this->date_preset}', " .
            "Date From: '{$this->date_from}', " .
            "Date To: '{$this->date_to}', " .
            "Assigned Waiter: {$this->assigned_waiter}, " .
            "Unassigned Only: " . ($this->unassigned_only ? 'true' : 'false') . ", " .
            "Shift Filter: '{$this->shift_filter}', " .
            "Discount Filter: '{$this->discount_filter}', " .
            "Page: {$this->current_page}"
        );
    }
    
    /**
     * Get BI data based on current mode
     * 
     * @return array BI data (grouped or individual)
     */
    public function get_bi_data() {
        if ($this->bi_mode === 'grouped') {
            return $this->get_grouped_data();
        } else {
            // Individual mode: use parent's get_orders() with BI filters applied
            return $this->get_individual_orders_with_bi_filters();
        }
    }
    
    /**
     * Get grouped data for BI dashboard
     * 
     * @return array Grouped data with aggregations
     */
    public function get_grouped_data() {
        // Use cache if available
        if ($this->grouped_data_cache !== null) {
            return $this->grouped_data_cache;
        }
        
        // Get all orders for grouping (no pagination for grouping)
        $all_orders = $this->get_all_orders_for_grouping();
        
        // Apply BI-specific filters
        $filtered_orders = $this->apply_bi_filters($all_orders);
        
        // Group the data
        $grouped_data = $this->group_orders_by_field($filtered_orders);
        
        // Cache the result
        $this->grouped_data_cache = $grouped_data;
        
        return $grouped_data;
    }
    
    /**
     * Get individual orders with BI filters applied
     * 
     * @return array Individual orders with BI context
     */
    private function get_individual_orders_with_bi_filters() {
        // Get paginated orders for individual mode (already filtered if needed)
        $orders = $this->get_paginated_orders_for_individual();
        
        // Apply BI filters only if NOT already applied in post-filtering
        if (!$this->needs_post_query_filtering()) {
            $orders = $this->apply_bi_filters($orders);
        }
        
        // Add BI context to each order
        $orders_with_context = array();
        foreach ($orders as $order) {
            $order_with_context = $this->add_bi_context_to_order($order);
            if ($order_with_context !== null) { // Skip null returns (non-orders)
                $orders_with_context[] = $order_with_context;
            }
        }
        
        // Debug logging
        error_log("BI Individual Orders Final - " .
                 "Orders Retrieved: " . count($orders) . ", " .
                 "With Context: " . count($orders_with_context) . ", " .
                 "Page: " . $this->current_page . 
                 ", Per Page: " . $this->per_page .
                 ", Post-Query Filtering: " . ($this->needs_post_query_filtering() ? 'YES' : 'NO'));
        
        return $orders_with_context;
    }
    
    /**
     * Get paginated orders for individual mode
     * 
     * @return array Paginated orders for current page
     */
    private function get_paginated_orders_for_individual() {
        // CRITICAL: For BI filters that can't be applied at WooCommerce query level,
        // we need to get ALL matching orders first, then paginate the filtered results
        
        if ($this->needs_post_query_filtering()) {
            return $this->get_paginated_orders_with_post_filtering();
        }
        
        // For filters that can be applied at query level, use direct pagination
        $query_args = $this->base_query_args;
        $query_args['limit'] = $this->per_page;
        $query_args['offset'] = ($this->current_page - 1) * $this->per_page;
        $query_args['return'] = 'objects';
        $query_args['type'] = 'shop_order'; // Exclude refunds and other types
        
        // Debug logging for query args
        error_log("BI Paginated Query Args (Direct) - " . json_encode(array(
            'limit' => $query_args['limit'],
            'offset' => $query_args['offset'],
            'date_created' => $query_args['date_created'] ?? 'none',
            'drill_down_group' => $this->drill_down_group,
            'group_by' => $this->group_by
        )));
        
        return wc_get_orders($query_args);
    }
    
    /**
     * Check if we need post-query filtering (can't be done at WooCommerce level)
     * 
     * @return bool True if post-query filtering is needed
     */
    private function needs_post_query_filtering() {
        // Shift filter requires post-query filtering (based on order time)
        if (!empty($this->shift_filter)) {
            return true;
        }
        
        // Drill-down filters that require post-query filtering
        if (!empty($this->drill_down_group)) {
            switch ($this->group_by) {
                case 'shift':
                case 'table':
                case 'waiter':
                case 'discount_status':
                    return true; // These require examining order details
                case 'day':
                    return false; // Day filtering is handled at query level
            }
        }
        
        return false;
    }
    
    /**
     * Get paginated orders with post-query filtering
     * 
     * @return array Paginated and filtered orders
     */
    private function get_paginated_orders_with_post_filtering() {
        // Get ALL orders that match base query (no pagination yet)
        $query_args = $this->base_query_args;
        $query_args['limit'] = -1; // Get all matching orders
        $query_args['return'] = 'objects';
        $query_args['type'] = 'shop_order';
        
        error_log("BI Post-Filter Query - Getting all orders for filtering");
        
        $all_orders = wc_get_orders($query_args);
        
        // Apply BI filters to get the filtered set
        $filtered_orders = $this->apply_bi_filters($all_orders);
        
        // Now apply pagination to the filtered results
        $total_filtered = count($filtered_orders);
        $offset = ($this->current_page - 1) * $this->per_page;
        $paginated_orders = array_slice($filtered_orders, $offset, $this->per_page);
        
        error_log("BI Post-Filter Results - " .
                 "Total Orders: " . count($all_orders) . ", " .
                 "After Filters: {$total_filtered}, " .
                 "Page {$this->current_page}: " . count($paginated_orders) . " orders");
        
        return $paginated_orders;
    }
    
    /**
     * Get all orders for grouping (bypasses pagination)
     * 
     * @return array All orders matching current filters
     */
    public function get_all_orders_for_grouping() {
        // Build query args without pagination
        $query_args = $this->base_query_args;
        $query_args['limit'] = -1; // Get all orders
        $query_args['return'] = 'objects';
        $query_args['type'] = 'shop_order'; // Exclude refunds and other types
        
        return wc_get_orders($query_args);
    }
    
    /**
     * Apply layered BI filtering system with proper priority
     * 
     * @param array $orders Array of WC_Order objects
     * @return array Filtered orders
     */
    private function apply_bi_filters($orders) {
        $filtered_orders = array();
        $total_orders = count($orders);
        $filter_stats = array(
            'non_orders' => 0,
            'inherited_filtered' => 0,
            'bi_filtered' => 0,
            'drill_down_filtered' => 0,
            'passed' => 0
        );
        
        foreach ($orders as $order) {
            // Layer 0: Skip non-order objects
            if (!$order instanceof WC_Order) {
                $filter_stats['non_orders']++;
                error_log("BI Filter Debug - Skipping non-order object: " . get_class($order) . " ID: " . $order->get_id());
                continue;
            }
            
            // Layer 1: Inherited filters (from parent class - already applied in query)
            // Note: Parent class filters are applied at query level, so we trust they're already filtered
            
            // Layer 2: BI-specific filters
            if (!$this->passes_bi_filters($order)) {
                $filter_stats['bi_filtered']++;
                continue;
            }
            
            // Layer 3: Drill-down filter (highest priority)
            if (!$this->passes_drill_down_filter($order)) {
                $filter_stats['drill_down_filtered']++;
                continue;
            }
            
            $filter_stats['passed']++;
            $filtered_orders[] = $order;
        }
        
        // Debug logging with filter statistics
        error_log("BI Filter Stats - Total: {$total_orders}, " .
                 "Non-Orders: {$filter_stats['non_orders']}, " .
                 "BI Filtered: {$filter_stats['bi_filtered']}, " .
                 "Drill-Down Filtered: {$filter_stats['drill_down_filtered']}, " .
                 "Passed: {$filter_stats['passed']}");
        
        return $filtered_orders;
    }
    
    /**
     * Check if order passes BI-specific filters
     * 
     * @param WC_Order $order Order object
     * @return bool True if passes all BI filters
     */
    private function passes_bi_filters($order) {
        // Shift filter
        if (!empty($this->shift_filter) && !$this->matches_shift_filter($order)) {
            return false;
        }
        
        // Discount filter
        if (!empty($this->discount_filter) && !$this->matches_discount_filter($order)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if order passes drill-down filter
     * 
     * @param WC_Order $order Order object
     * @return bool True if passes drill-down filter
     */
    private function passes_drill_down_filter($order) {
        if (empty($this->drill_down_group)) {
            return true; // No drill-down filter active
        }
        
        $order_group_key = $this->get_group_key_for_order($order);
        $matches = ($order_group_key === $this->drill_down_group);
        
        // Debug logging for drill-down
        if (!$matches) {
            error_log("BI Drill-Down Filter - Order ID: {$order->get_id()}, " .
                     "Group Key: '{$order_group_key}', " .
                     "Filter: '{$this->drill_down_group}', " .
                     "Match: NO");
        }
        
        return $matches;
    }
    
    /**
     * Check if order matches shift filter
     * 
     * @param WC_Order $order Order object
     * @return bool True if matches
     */
    private function matches_shift_filter($order) {
        $order_time = $order->get_date_created();
        if (!$order_time) {
            return false;
        }
        
        // Convert to site timezone for shift calculation
        $site_timezone = wp_timezone();
        $order_time->setTimezone($site_timezone);
        $hour = intval($order_time->format('H'));
        
        switch ($this->shift_filter) {
            case 'morning':
                return $hour >= 6 && $hour < 14; // 6 AM - 2 PM
            case 'afternoon':
                return $hour >= 14 && $hour < 20; // 2 PM - 8 PM
            case 'night':
                return $hour >= 20 || $hour < 6; // 8 PM - 6 AM
            default:
                return true;
        }
    }
    
    /**
     * Check if order matches discount filter
     * 
     * @param WC_Order $order Order object
     * @return bool True if matches
     */
    private function matches_discount_filter($order) {
        $has_discount = $this->order_has_discount($order);
        
        switch ($this->discount_filter) {
            case 'with_discount':
                return $has_discount;
            case 'without_discount':
                return !$has_discount;
            default:
                return true;
        }
    }
    
    /**
     * Check if order has any discounts/coupons
     * 
     * @param WC_Order $order Order object
     * @return bool True if has discount
     */
    private function order_has_discount($order) {
        // Check for coupons
        $coupons = $order->get_coupon_codes();
        if (!empty($coupons)) {
            return true;
        }
        
        // Check for discount amount
        $discount_total = $order->get_discount_total();
        if ($discount_total > 0) {
            return true;
        }
        
        // Check for manual discounts in line items
        foreach ($order->get_items() as $item) {
            if ($item->get_subtotal() > $item->get_total()) {
                return true;
            }
        }
        
        return false;
    }
    
    
    /**
     * Standardize table number format for consistent grouping and filtering
     * 
     * @param string $table_number Raw table number from order meta
     * @return string Standardized table number
     */
    private function standardize_table_number($table_number) {
        if (empty($table_number)) {
            return '';
        }
        
        // Remove spaces, special characters, convert to uppercase
        $clean = preg_replace('/[^A-Za-z0-9]/', '', trim($table_number));
        return strtoupper($clean);
    }
    
    /**
     * Get standardized shift for an order
     * 
     * @param WC_Order $order Order object
     * @return string Standardized shift key
     */
    private function get_standardized_shift_for_order($order) {
        $order_time = $order->get_date_created();
        if (!$order_time) {
            return 'unknown_shift';
        }
        
        $order_time->setTimezone(wp_timezone());
        $hour = intval($order_time->format('H'));
        
        // Standardized shift definitions
        if ($hour >= 6 && $hour < 14) {
            return 'morning';
        } elseif ($hour >= 14 && $hour < 20) {
            return 'afternoon';
        } else {
            return 'night';
        }
    }
    
    /**
     * Group orders by the specified field
     * 
     * @param array $orders Array of WC_Order objects
     * @return array Grouped data with aggregations
     */
    private function group_orders_by_field($orders) {
        $groups = array();
        
        foreach ($orders as $order) {
            // Skip non-order objects (like OrderRefund)
            if (!$order instanceof WC_Order) {
                error_log("BI Grouping Debug - Skipping non-order object: " . get_class($order) . " ID: " . $order->get_id());
                continue;
            }
            
            $group_key = $this->get_group_key_for_order($order);
            
            if (!isset($groups[$group_key])) {
                $groups[$group_key] = array(
                    'group_key' => $group_key,
                    'group_label' => $this->get_group_label($group_key),
                    'orders' => array(),
                    'count' => 0,
                    'total_revenue' => 0,
                    'avg_order_value' => 0,
                    'discount_count' => 0,
                    'discount_amount' => 0,
                    'completion_rate' => 0,
                    'metadata' => array()
                );
            }
            
            // Add order to group
            $groups[$group_key]['orders'][] = $order;
            $groups[$group_key]['count']++;
            $groups[$group_key]['total_revenue'] += $order->get_total();
            
            // Track discounts
            if ($this->order_has_discount($order)) {
                $groups[$group_key]['discount_count']++;
                $groups[$group_key]['discount_amount'] += $order->get_discount_total();
            }
            
            // Add group-specific metadata
            $this->add_group_metadata($groups[$group_key], $order);
        }
        
        // Calculate derived metrics for each group
        foreach ($groups as &$group) {
            if ($group['count'] > 0) {
                $group['avg_order_value'] = $group['total_revenue'] / $group['count'];
                $group['completion_rate'] = $this->calculate_completion_rate($group['orders']);
            }
        }
        
        // Sort groups
        $groups = $this->sort_grouped_data($groups);
        
        return array_values($groups); // Return indexed array
    }
    
    /**
     * Get group key for an order based on current group_by setting
     * 
     * @param WC_Order $order Order object
     * @return string Group key
     */
    private function get_group_key_for_order($order) {
        switch ($this->group_by) {
            case 'day':
                $date = $order->get_date_created();
                if ($date) {
                    $date->setTimezone(wp_timezone());
                    return $date->format('Y-m-d');
                }
                return 'unknown';
                
            case 'waiter':
                $waiter_id = $order->get_meta('_oj_assigned_waiter');
                if ($waiter_id) {
                    return 'waiter_' . $waiter_id;
                }
                return 'unassigned';
                
            case 'shift':
                return $this->get_standardized_shift_for_order($order);
                
            case 'table':
                $table_number = $order->get_meta('_oj_table_number');
                if ($table_number) {
                    // CRITICAL: Standardize table format for consistent grouping
                    $clean_table = $this->standardize_table_number($table_number);
                    $group_key = 'table_' . $clean_table;
                    // Debug logging
                    error_log("BI Table Group Debug - Order ID: " . $order->get_id() . 
                             ", Raw Table Meta: '" . $table_number . "'" . 
                             ", Standardized: '" . $clean_table . "'" .
                             ", Generated Key: '" . $group_key . "'");
                    return $group_key;
                }
                return 'no_table';
                
            case 'discount_status':
                return $this->order_has_discount($order) ? 'with_discount' : 'without_discount';
                
            default:
                return 'all';
        }
    }
    
    /**
     * Get shift for an order
     * 
     * @param WC_Order $order Order object
     * @return string Shift key
     */
    private function get_shift_for_order($order) {
        $order_time = $order->get_date_created();
        if (!$order_time) {
            return 'unknown';
        }
        
        $site_timezone = wp_timezone();
        $order_time->setTimezone($site_timezone);
        $hour = intval($order_time->format('H'));
        
        if ($hour >= 6 && $hour < 14) {
            return 'morning';
        } elseif ($hour >= 14 && $hour < 20) {
            return 'afternoon';
        } else {
            return 'night';
        }
    }
    
    /**
     * Get human-readable label for group key
     * 
     * @param string $group_key Group key
     * @return string Human-readable label
     */
    private function get_group_label($group_key) {
        switch ($this->group_by) {
            case 'day':
                if ($group_key === 'unknown') {
                    return __('Unknown Date', 'orders-jet');
                }
                return date_i18n('F j, Y', strtotime($group_key));
                
            case 'waiter':
                if ($group_key === 'unassigned') {
                    return __('Unassigned Orders', 'orders-jet');
                }
                $waiter_id = str_replace('waiter_', '', $group_key);
                $waiter = get_userdata($waiter_id);
                return $waiter ? $waiter->display_name : __('Unknown Waiter', 'orders-jet');
                
            case 'shift':
                switch ($group_key) {
                    case 'morning':
                        return __('Morning Shift (6 AM - 2 PM)', 'orders-jet');
                    case 'afternoon':
                        return __('Afternoon Shift (2 PM - 8 PM)', 'orders-jet');
                    case 'night':
                        return __('Night Shift (8 PM - 6 AM)', 'orders-jet');
                    default:
                        return __('Unknown Shift', 'orders-jet');
                }
                
            case 'table':
                if ($group_key === 'no_table') {
                    return __('Takeaway/Delivery Orders', 'orders-jet');
                }
                $table_number = str_replace('table_', '', $group_key);
                return sprintf(__('Table %s', 'orders-jet'), $table_number);
                
            case 'discount_status':
                switch ($group_key) {
                    case 'with_discount':
                        return __('Orders with Discounts', 'orders-jet');
                    case 'without_discount':
                        return __('Orders without Discounts', 'orders-jet');
                    default:
                        return __('Unknown Discount Status', 'orders-jet');
                }
                
            default:
                return $group_key;
        }
    }
    
    /**
     * Add group-specific metadata
     * 
     * @param array &$group Group data (passed by reference)
     * @param WC_Order $order Order object
     */
    private function add_group_metadata(&$group, $order) {
        switch ($this->group_by) {
            case 'waiter':
                // Track waiter performance metrics
                if (!isset($group['metadata']['total_tables'])) {
                    $group['metadata']['total_tables'] = array();
                }
                $table_number = $order->get_meta('_oj_table_number');
                if ($table_number && !in_array($table_number, $group['metadata']['total_tables'])) {
                    $group['metadata']['total_tables'][] = $table_number;
                }
                break;
                
            case 'table':
                // Track table session data
                if (!isset($group['metadata']['session_count'])) {
                    $group['metadata']['session_count'] = 0;
                    $group['metadata']['sessions'] = array();
                }
                $session_id = $order->get_meta('_oj_session_id');
                if ($session_id && !in_array($session_id, $group['metadata']['sessions'])) {
                    $group['metadata']['sessions'][] = $session_id;
                    $group['metadata']['session_count']++;
                }
                break;
                
            case 'discount_status':
                // Track discount details
                if (!isset($group['metadata']['coupon_codes'])) {
                    $group['metadata']['coupon_codes'] = array();
                }
                $coupons = $order->get_coupon_codes();
                foreach ($coupons as $coupon) {
                    if (!in_array($coupon, $group['metadata']['coupon_codes'])) {
                        $group['metadata']['coupon_codes'][] = $coupon;
                    }
                }
                break;
        }
    }
    
    /**
     * Calculate completion rate for a group of orders
     * 
     * @param array $orders Array of WC_Order objects
     * @return float Completion rate (0-100)
     */
    private function calculate_completion_rate($orders) {
        if (empty($orders)) {
            return 0;
        }
        
        $completed_count = 0;
        foreach ($orders as $order) {
            if ($order->get_status() === 'completed') {
                $completed_count++;
            }
        }
        
        return ($completed_count / count($orders)) * 100;
    }
    
    /**
     * Sort grouped data based on group_by field
     * 
     * @param array $groups Grouped data
     * @return array Sorted grouped data
     */
    private function sort_grouped_data($groups) {
        switch ($this->group_by) {
            case 'day':
                // Sort by date (newest first)
                uasort($groups, function($a, $b) {
                    return strcmp($b['group_key'], $a['group_key']);
                });
                break;
                
            case 'waiter':
                // Sort by total revenue (highest first)
                uasort($groups, function($a, $b) {
                    return $b['total_revenue'] <=> $a['total_revenue'];
                });
                break;
                
            case 'shift':
                // Sort by shift order (morning, afternoon, night)
                $shift_order = array('morning' => 1, 'afternoon' => 2, 'night' => 3);
                uasort($groups, function($a, $b) use ($shift_order) {
                    $order_a = $shift_order[$a['group_key']] ?? 999;
                    $order_b = $shift_order[$b['group_key']] ?? 999;
                    return $order_a <=> $order_b;
                });
                break;
                
            case 'table':
                // Sort by table number
                uasort($groups, function($a, $b) {
                    if ($a['group_key'] === 'no_table') return 1;
                    if ($b['group_key'] === 'no_table') return -1;
                    
                    $table_a = intval(str_replace('table_T', '', $a['group_key']));
                    $table_b = intval(str_replace('table_T', '', $b['group_key']));
                    return $table_a <=> $table_b;
                });
                break;
                
            case 'discount_status':
                // Sort by discount amount (highest first)
                uasort($groups, function($a, $b) {
                    return $b['discount_amount'] <=> $a['discount_amount'];
                });
                break;
                
            default:
                // Sort by total revenue (highest first)
                uasort($groups, function($a, $b) {
                    return $b['total_revenue'] <=> $a['total_revenue'];
                });
                break;
        }
        
        return $groups;
    }
    
    /**
     * Add BI context to individual order
     * 
     * @param WC_Order $order Order object
     * @return array Order data with BI context
     */
    private function add_bi_context_to_order($order) {
        // Skip non-order objects (like OrderRefund)
        if (!$order instanceof WC_Order) {
            error_log("BI Context Debug - Skipping non-order object: " . get_class($order) . " ID: " . $order->get_id());
            return null; // Return null for non-orders
        }
        
        // Get customer name safely
        $customer_name = __('Guest', 'orders-jet');
        if (method_exists($order, 'get_billing_first_name') && method_exists($order, 'get_billing_last_name')) {
            $first_name = $order->get_billing_first_name();
            $last_name = $order->get_billing_last_name();
            if (!empty($first_name) || !empty($last_name)) {
                $customer_name = trim($first_name . ' ' . $last_name);
            }
        }
        
        // Create flat structure that's compatible with the individual data template
        return array(
            'id' => $order->get_id(),
            'total' => $order->get_total(),
            'status' => $order->get_status(),
            'date' => $order->get_date_created(),
            'customer_name' => $customer_name,
            'order_object' => $order, // Keep full order object for additional data access
            
            // BI Context (flat for easy access)
            'shift' => $this->get_shift_for_order($order),
            'has_discount' => $this->order_has_discount($order),
            'discount_amount' => $order->get_discount_total(),
            'coupon_codes' => $order->get_coupon_codes(),
            'waiter_name' => $this->get_waiter_name_for_order($order),
            'waiter_id' => $order->get_meta('_oj_assigned_waiter'),
            'table_number' => $order->get_meta('_oj_table_number'),
            'session_id' => $order->get_meta('_oj_session_id')
        );
    }
    
    /**
     * Get waiter name for an order
     * 
     * @param WC_Order $order Order object
     * @return string Waiter name or 'Unassigned'
     */
    private function get_waiter_name_for_order($order) {
        $waiter_id = $order->get_meta('_oj_assigned_waiter');
        if ($waiter_id) {
            $waiter = get_userdata($waiter_id);
            return $waiter ? $waiter->display_name : __('Unknown Waiter', 'orders-jet');
        }
        return __('Unassigned', 'orders-jet');
    }
    
    /**
     * Get ALL filtered orders for BI analysis (no pagination)
     * This is used by the 4-card status breakdown to show complete totals
     * 
     * @return array All WC_Order objects matching current BI filters
     */
    public function get_all_filtered_orders() {
        if ($this->bi_mode !== 'individual') {
            // For grouped mode, return all orders in date range
            return $this->get_all_orders_for_grouping();
        }
        
        // For individual mode, get all orders and apply BI filters
        $all_orders_args = $this->base_query_args;
        $all_orders_args['return'] = 'objects';
        $all_orders_args['limit'] = -1; // Get ALL orders
        $all_orders_args['type'] = 'shop_order';
        
        // Get all orders
        $all_orders = wc_get_orders($all_orders_args);
        
        // Apply BI filters to get the complete filtered set
        $filtered_orders = $this->apply_bi_filters($all_orders);
        
        error_log("BI All Filtered Orders - Mode: {$this->bi_mode}, Group: {$this->group_by}, Raw: " . count($all_orders) . ", Filtered: " . count($filtered_orders));
        
        return $filtered_orders;
    }
    
    /**
     * Get total count of orders for pagination (individual mode only)
     * 
     * @return int Total number of orders
     */
    public function get_total_orders_count() {
        if ($this->bi_mode !== 'individual') {
            return 0; // Pagination only applies to individual mode
        }
        
        // Get count query args
        $count_args = $this->base_query_args;
        $count_args['return'] = 'objects'; // Need objects for BI filtering
        $count_args['limit'] = -1;
        $count_args['type'] = 'shop_order';
        
        // Debug logging for count query
        error_log("BI Total Count Query Args - " . json_encode(array(
            'date_created' => $count_args['date_created'] ?? 'none',
            'drill_down_group' => $this->drill_down_group,
            'group_by' => $this->group_by,
            'needs_post_filtering' => $this->needs_post_query_filtering()
        )));
        
        // Get all orders
        $all_orders = wc_get_orders($count_args);
        
        // Apply BI filters to get accurate count
        $filtered_orders = $this->apply_bi_filters($all_orders);
        
        error_log("BI Total Count Result - Raw Orders: " . count($all_orders) . ", After Filters: " . count($filtered_orders));
        
        return count($filtered_orders);
    }
    
    /**
     * Get pagination info for individual mode
     * 
     * @return array Pagination information
     */
    public function get_pagination_info() {
        if ($this->bi_mode !== 'individual') {
            return array(); // No pagination for grouped mode
        }
        
        $total_orders = $this->get_total_orders_count();
        $total_pages = ceil($total_orders / $this->per_page);
        
        return array(
            'current_page' => $this->current_page,
            'per_page' => $this->per_page,
            'total_orders' => $total_orders,
            'total_pages' => $total_pages,
            'has_prev' => $this->current_page > 1,
            'has_next' => $this->current_page < $total_pages,
            'prev_page' => max(1, $this->current_page - 1),
            'next_page' => min($total_pages, $this->current_page + 1),
            'start_order' => (($this->current_page - 1) * $this->per_page) + 1,
            'end_order' => min($this->current_page * $this->per_page, $total_orders)
        );
    }
    
    /**
     * Build clean BI URL with proper parameter handling and conflict resolution
     * 
     * @param array $additional_params Additional parameters to include
     * @return string Complete BI URL
     */
    public function build_clean_bi_url($additional_params = array()) {
        $params = array('page' => 'business-intelligence');
        
        // Core BI parameters
        $params['bi_mode'] = $this->bi_mode;
        
        // Group by (for grouped mode or when drill-down is active)
        if ($this->bi_mode === 'grouped' || !empty($this->drill_down_group)) {
            $params['group_by'] = $this->group_by;
        }
        
        // Drill-down (highest priority - when active, skip conflicting filters)
        if (!empty($this->drill_down_group)) {
            $params['drill_down_group'] = $this->drill_down_group;
            
            // For drill-down, only add non-conflicting parameters
            $this->add_non_conflicting_params($params, $additional_params);
            return add_query_arg($params, admin_url('admin.php'));
        }
        
        // Date filters (if no conflicting drill-down)
        if (!empty($this->date_preset)) {
            $params['date_preset'] = $this->date_preset;
        } else {
            if (!empty($this->date_from)) $params['date_from'] = $this->date_from;
            if (!empty($this->date_to)) $params['date_to'] = $this->date_to;
        }
        
        // BI-specific filters
        if (!empty($this->shift_filter)) $params['shift_filter'] = $this->shift_filter;
        if (!empty($this->discount_filter)) $params['discount_filter'] = $this->discount_filter;
        
        // Inherited filters (from parent class)
        if (!empty($this->filter) && $this->filter !== 'all') $params['filter'] = $this->filter;
        if (!empty($this->order_type)) $params['order_type'] = $this->order_type;
        if (!empty($this->kitchen_status)) $params['kitchen_status'] = $this->kitchen_status;
        if (!empty($this->kitchen_type)) $params['kitchen_type'] = $this->kitchen_type;
        if (!empty($this->payment_method)) $params['payment_method'] = $this->payment_method;
        if (!empty($this->customer_type)) $params['customer_type'] = $this->customer_type;
        
        // Staff filters
        if ($this->assigned_waiter > 0) $params['assigned_waiter'] = $this->assigned_waiter;
        if ($this->unassigned_only) $params['unassigned_only'] = '1';
        
        // Amount filters
        if (!empty($this->amount_type)) {
            $params['amount_type'] = $this->amount_type;
            if ($this->amount_type === 'between') {
                if ($this->amount_min > 0) $params['amount_min'] = $this->amount_min;
                if ($this->amount_max > 0) $params['amount_max'] = $this->amount_max;
            } else {
                if ($this->amount_value > 0) $params['amount_value'] = $this->amount_value;
            }
        }
        
        // Search and sort
        if (!empty($this->search)) $params['search'] = $this->search;
        if (!empty($this->orderby) && $this->orderby !== 'date_created') $params['orderby'] = $this->orderby;
        if (!empty($this->order) && $this->order !== 'DESC') $params['order'] = $this->order;
        
        // Pagination (individual mode only)
        if ($this->bi_mode === 'individual' && $this->current_page > 1) {
            $params['paged'] = $this->current_page;
        }
        
        // Merge with additional parameters
        $params = array_merge($params, $additional_params);
        
        return add_query_arg($params, admin_url('admin.php'));
    }
    
    /**
     * Add non-conflicting parameters when drill-down is active
     * 
     * @param array &$params Parameters array to modify
     * @param array $additional_params Additional parameters
     */
    private function add_non_conflicting_params(&$params, $additional_params) {
        // When drill-down is active, only add parameters that don't conflict
        
        // Always safe: search, sort, pagination
        if (!empty($this->search)) $params['search'] = $this->search;
        if (!empty($this->orderby) && $this->orderby !== 'date_created') $params['orderby'] = $this->orderby;
        if (!empty($this->order) && $this->order !== 'DESC') $params['order'] = $this->order;
        
        if ($this->bi_mode === 'individual' && $this->current_page > 1) {
            $params['paged'] = $this->current_page;
        }
        
        // Conditionally safe based on drill-down type
        switch ($this->group_by) {
            case 'day':
                // For day drill-down, don't add date filters (they conflict)
                // But other filters are safe
                $this->add_safe_non_date_params($params);
                break;
                
            case 'waiter':
                // For waiter drill-down, don't add waiter filters (they conflict)
                // But other filters are safe
                $this->add_safe_non_waiter_params($params);
                break;
                
            case 'shift':
                // For shift drill-down, don't add shift filters (they conflict)
                // But other filters are safe
                $this->add_safe_non_shift_params($params);
                break;
                
            case 'discount_status':
                // For discount drill-down, don't add discount filters (they conflict)
                // But other filters are safe
                $this->add_safe_non_discount_params($params);
                break;
                
            default:
                // For other drill-downs, most filters are safe
                $this->add_safe_general_params($params);
                break;
        }
        
        // Add additional parameters
        $params = array_merge($params, $additional_params);
    }
    
    /**
     * Add safe parameters (excluding date-related ones)
     */
    private function add_safe_non_date_params(&$params) {
        if (!empty($this->shift_filter)) $params['shift_filter'] = $this->shift_filter;
        if (!empty($this->discount_filter)) $params['discount_filter'] = $this->discount_filter;
        if ($this->assigned_waiter > 0) $params['assigned_waiter'] = $this->assigned_waiter;
        if ($this->unassigned_only) $params['unassigned_only'] = '1';
        $this->add_common_safe_params($params);
    }
    
    /**
     * Add safe parameters (excluding waiter-related ones)
     */
    private function add_safe_non_waiter_params(&$params) {
        if (!empty($this->shift_filter)) $params['shift_filter'] = $this->shift_filter;
        if (!empty($this->discount_filter)) $params['discount_filter'] = $this->discount_filter;
        $this->add_date_params($params);
        $this->add_common_safe_params($params);
    }
    
    /**
     * Add safe parameters (excluding shift-related ones)
     */
    private function add_safe_non_shift_params(&$params) {
        if (!empty($this->discount_filter)) $params['discount_filter'] = $this->discount_filter;
        if ($this->assigned_waiter > 0) $params['assigned_waiter'] = $this->assigned_waiter;
        if ($this->unassigned_only) $params['unassigned_only'] = '1';
        $this->add_date_params($params);
        $this->add_common_safe_params($params);
    }
    
    /**
     * Add safe parameters (excluding discount-related ones)
     */
    private function add_safe_non_discount_params(&$params) {
        if (!empty($this->shift_filter)) $params['shift_filter'] = $this->shift_filter;
        if ($this->assigned_waiter > 0) $params['assigned_waiter'] = $this->assigned_waiter;
        if ($this->unassigned_only) $params['unassigned_only'] = '1';
        $this->add_date_params($params);
        $this->add_common_safe_params($params);
    }
    
    /**
     * Add safe general parameters
     */
    private function add_safe_general_params(&$params) {
        if (!empty($this->shift_filter)) $params['shift_filter'] = $this->shift_filter;
        if (!empty($this->discount_filter)) $params['discount_filter'] = $this->discount_filter;
        if ($this->assigned_waiter > 0) $params['assigned_waiter'] = $this->assigned_waiter;
        if ($this->unassigned_only) $params['unassigned_only'] = '1';
        $this->add_date_params($params);
        $this->add_common_safe_params($params);
    }
    
    /**
     * Add date parameters
     */
    private function add_date_params(&$params) {
        if (!empty($this->date_preset)) {
            $params['date_preset'] = $this->date_preset;
        } else {
            if (!empty($this->date_from)) $params['date_from'] = $this->date_from;
            if (!empty($this->date_to)) $params['date_to'] = $this->date_to;
        }
    }
    
    /**
     * Add commonly safe parameters (order type, payment, etc.)
     */
    private function add_common_safe_params(&$params) {
        if (!empty($this->filter) && $this->filter !== 'all') $params['filter'] = $this->filter;
        if (!empty($this->order_type)) $params['order_type'] = $this->order_type;
        if (!empty($this->kitchen_status)) $params['kitchen_status'] = $this->kitchen_status;
        if (!empty($this->kitchen_type)) $params['kitchen_type'] = $this->kitchen_type;
        if (!empty($this->payment_method)) $params['payment_method'] = $this->payment_method;
        if (!empty($this->customer_type)) $params['customer_type'] = $this->customer_type;
        
        // Amount filters
        if (!empty($this->amount_type)) {
            $params['amount_type'] = $this->amount_type;
            if ($this->amount_type === 'between') {
                if ($this->amount_min > 0) $params['amount_min'] = $this->amount_min;
                if ($this->amount_max > 0) $params['amount_max'] = $this->amount_max;
            } else {
                if ($this->amount_value > 0) $params['amount_value'] = $this->amount_value;
            }
        }
    }
    
    /**
     * Clear specific filter and return clean URL
     * 
     * @param string $filter_name Filter to clear
     * @return string URL with filter cleared
     */
    public function clear_filter_url($filter_name) {
        $params_to_remove = array($filter_name);
        
        // If clearing drill-down, also clear related params
        if ($filter_name === 'drill_down_group') {
            $params_to_remove[] = 'paged'; // Reset to page 1
        }
        
        // If clearing mode, reset pagination
        if ($filter_name === 'bi_mode') {
            $params_to_remove[] = 'paged';
        }
        
        return remove_query_arg($params_to_remove, $this->build_clean_bi_url());
    }
    
    /**
     * Get BI mode
     * 
     * @return string BI mode
     */
    public function get_bi_mode() {
        return $this->bi_mode;
    }
    
    /**
     * Get group by field
     * 
     * @return string Group by field
     */
    public function get_group_by() {
        return $this->group_by;
    }
    
    /**
     * Get shift filter
     * 
     * @return string Shift filter
     */
    public function get_shift_filter() {
        return $this->shift_filter;
    }
    
    /**
     * Get discount filter
     * 
     * @return string Discount filter
     */
    public function get_discount_filter() {
        return $this->discount_filter;
    }
    
    /**
     * Get summary statistics for current filtered data
     * 
     * @return array Summary statistics
     */
    public function get_summary_statistics() {
        $data = $this->get_bi_data();
        
        if ($this->bi_mode === 'grouped') {
            return $this->calculate_grouped_summary($data);
        } else {
            return $this->calculate_individual_summary($data);
        }
    }
    
    /**
     * Calculate summary statistics for grouped data
     * 
     * @param array $grouped_data Grouped data
     * @return array Summary statistics
     */
    private function calculate_grouped_summary($grouped_data) {
        $total_groups = count($grouped_data);
        $total_orders = 0;
        $total_revenue = 0;
        $total_discount_amount = 0;
        $orders_with_discount = 0;
        
        foreach ($grouped_data as $group) {
            $total_orders += $group['count'];
            $total_revenue += $group['total_revenue'];
            $total_discount_amount += $group['discount_amount'];
            $orders_with_discount += $group['discount_count'];
        }
        
        return array(
            'total_groups' => $total_groups,
            'total_orders' => $total_orders,
            'total_revenue' => $total_revenue,
            'avg_order_value' => $total_orders > 0 ? $total_revenue / $total_orders : 0,
            'total_discount_amount' => $total_discount_amount,
            'orders_with_discount' => $orders_with_discount,
            'discount_rate' => $total_orders > 0 ? ($orders_with_discount / $total_orders) * 100 : 0
        );
    }
    
    /**
     * Calculate summary statistics for individual data
     * 
     * @param array $individual_data Individual orders with BI context
     * @return array Summary statistics
     */
    private function calculate_individual_summary($individual_data) {
        $total_orders = count($individual_data);
        $total_revenue = 0;
        $total_discount_amount = 0;
        $orders_with_discount = 0;
        
        foreach ($individual_data as $order_data) {
            // Handle both WC_Order objects and flat BI arrays
            if (is_object($order_data) && method_exists($order_data, 'get_total')) {
                // Direct WC_Order object
                $total_revenue += $order_data->get_total();
                
                if ($order_data->get_discount_total() > 0) {
                    $orders_with_discount++;
                    $total_discount_amount += $order_data->get_discount_total();
                }
            } elseif (is_array($order_data)) {
                // Flat BI array structure
                $total_revenue += $order_data['total'] ?? 0;
                
                if (isset($order_data['has_discount']) && $order_data['has_discount']) {
                    $orders_with_discount++;
                    $total_discount_amount += $order_data['discount_amount'] ?? 0;
                }
            }
        }
        
        return array(
            'total_orders' => $total_orders,
            'total_revenue' => $total_revenue,
            'avg_order_value' => $total_orders > 0 ? $total_revenue / $total_orders : 0,
            'total_discount_amount' => $total_discount_amount,
            'orders_with_discount' => $orders_with_discount,
            'discount_rate' => $total_orders > 0 ? ($orders_with_discount / $total_orders) * 100 : 0
        );
    }
}
