<?php
/**
 * Orders Reports Query Builder
 * 
 * Encapsulates all query logic for Orders Reports page.
 * Handles filters, search, sort, pagination, date ranges, and reports-specific aggregations.
 * 
 * @package Orders_Jet
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Orders_Reports_Query_Builder
 * 
 * Builds and executes WooCommerce order queries with filters, search, sort, pagination, and reports aggregations.
 */
class Orders_Reports_Query_Builder {
    
    /**
     * @var string Current filter (all, active, kitchen, ready, completed)
     */
    private $filter;
    
    /**
     * @var int Current page number
     */
    private $page;
    
    /**
     * @var string Search term
     */
    private $search;
    
    /**
     * @var string Sort field (date_created, date_modified)
     */
    private $orderby;
    
    /**
     * @var string Sort direction (ASC, DESC)
     */
    private $order;
    
    /**
     * @var int Items per page
     */
    private $per_page;
    
    /**
     * @var string Date preset (today, yesterday, etc.)
     */
    private $date_preset;
    
    /**
     * @var string Date from (Y-m-d)
     */
    private $date_from;
    
    /**
     * @var string Date to (Y-m-d)
     */
    private $date_to;
    
    /**
     * @var DateTime Date from DateTime object (for hour-based presets)
     */
    private $date_from_dt;
    
    /**
     * @var DateTime Date to DateTime object (for hour-based presets)
     */
    private $date_to_dt;
    
    /**
     * @var string Order type filter (dinein, takeaway, delivery)
     */
    private $order_type;
    
    /**
     * @var string Kitchen type filter (food, beverages, mixed)
     */
    private $kitchen_type;
    
    /**
     * @var string Kitchen status filter (waiting_food, waiting_beverages, all_ready)
     */
    private $kitchen_status;
    
    /**
     * @var int Assigned waiter filter
     */
    private $assigned_waiter;
    
    /**
     * @var bool Show only unassigned orders
     */
    private $unassigned_only;
    
    /**
     * @var string Payment method filter (cash, card, bacs, cod)
     */
    private $payment_method;
    
    /**
     * @var string Customer type filter (table_guest, registered_customer, repeat_visitor, new_session, continuing_session)
     */
    private $customer_type;
    
    /**
     * @var string Order status filter (pending, processing, completed, cancelled, refunded, failed)
     */
    private $order_status;
    
    /**
     * @var string Amount filter type (equals, less_than, greater_than, between)
     */
    private $amount_type;
    
    /**
     * @var float Amount value for equals, less_than, greater_than filters
     */
    private $amount_value;
    
    /**
     * @var float Minimum amount for between filter
     */
    private $amount_min;
    
    /**
     * @var float Maximum amount for between filter
     */
    private $amount_max;
    
    /**
     * @var string Group by field for reports (date, order_type, kitchen_type, waiter, payment_method)
     */
    private $group_by;
    
    /**
     * @var array Base query arguments
     */
    private $base_query_args;
    
    /**
     * @var int Total orders count
     */
    private $total_orders;
    
    /**
     * @var int Total pages
     */
    private $total_pages;
    
    /**
     * Constructor
     * 
     * @param array $params URL parameters from $_GET
     */
    public function __construct($params = array()) {
        // Sanitize and set parameters
        $this->filter = isset($params['filter']) ? sanitize_text_field($params['filter']) : 'all';
        $this->page = isset($params['paged']) ? max(1, intval($params['paged'])) : 1;
        $this->search = isset($params['search']) ? sanitize_text_field($params['search']) : '';
        $this->orderby = !empty($params['orderby']) ? sanitize_text_field($params['orderby']) : 'date_created';
        $this->order = !empty($params['order']) ? sanitize_text_field($params['order']) : 'DESC';
        $this->per_page = 20;
        
        // Date range parameters
        $this->date_preset = isset($params['date_preset']) ? sanitize_text_field($params['date_preset']) : '';
        $this->date_from = isset($params['date_from']) ? sanitize_text_field($params['date_from']) : '';
        $this->date_to = isset($params['date_to']) ? sanitize_text_field($params['date_to']) : '';
        
        // Check for drill-down date (overrides other date settings)
        if (isset($params['drill_down_date']) && !empty($params['drill_down_date'])) {
            $drill_date = sanitize_text_field($params['drill_down_date']);
            
            // Convert period key to date range based on format
            if (preg_match('/(\d{4})-W(\d{2})/', $drill_date, $matches)) {
                // Week format: 2025-W49
                $year = $matches[1];
                $week = $matches[2];
                
                // Calculate first and last day of week
                $dto = new DateTime();
                $dto->setISODate($year, $week);
                $this->date_from = $dto->format('Y-m-d');
                
                $dto->modify('+6 days');
                $this->date_to = $dto->format('Y-m-d');
                
            } elseif (preg_match('/^\d{4}-\d{2}$/', $drill_date)) {
                // Month format: 2024-12
                $this->date_from = $drill_date . '-01';
                $this->date_to = date('Y-m-t', strtotime($drill_date . '-01'));
                
            } else {
                // Day format: 2024-12-03 (use as is)
                $this->date_from = $drill_date;
                $this->date_to = $drill_date;
            }
            
            $this->date_preset = ''; // Clear preset when drilling down
        }
        
        // Advanced filter parameters
        $this->order_type = isset($params['order_type']) ? sanitize_text_field($params['order_type']) : '';
        $this->kitchen_type = isset($params['kitchen_type']) ? sanitize_text_field($params['kitchen_type']) : '';
        $this->kitchen_status = isset($params['kitchen_status']) ? sanitize_text_field($params['kitchen_status']) : '';
        $this->assigned_waiter = isset($params['assigned_waiter']) ? intval($params['assigned_waiter']) : 0;
        $this->unassigned_only = isset($params['unassigned_only']) && $params['unassigned_only'] === '1';
        $this->payment_method = isset($params['payment_method']) ? sanitize_text_field($params['payment_method']) : '';
        $this->customer_type = isset($params['customer_type']) ? sanitize_text_field($params['customer_type']) : '';
        $this->order_status = isset($params['order_status']) ? sanitize_text_field($params['order_status']) : '';
        
        // Amount filter parameters (validated by service)
        try {
            $amount_filter_service = new Orders_Jet_Amount_Filter_Service();
            $validated_amount_params = $amount_filter_service->validate_amount_filter_params($params);
            $this->amount_type = $validated_amount_params['amount_type'];
            $this->amount_value = $validated_amount_params['amount_value'];
            $this->amount_min = $validated_amount_params['amount_min'];
            $this->amount_max = $validated_amount_params['amount_max'];
        } catch (Exception $e) {
            // Fallback to safe defaults if amount filter service not available
            $this->amount_type = '';
            $this->amount_value = 0.0;
            $this->amount_min = 0.0;
            $this->amount_max = 0.0;
        }
        
        // Reports-specific parameters
        $this->group_by = isset($params['group_by']) ? sanitize_text_field($params['group_by']) : 'day';
        
        // Process date preset if set (and not overridden by drill-down)
        if (!empty($this->date_preset) && !isset($params['drill_down_date'])) {
            if (function_exists('oj_calculate_date_range')) {
                $date_range = oj_calculate_date_range($this->date_preset);
                if ($date_range) {
                    // For hour-based presets, store DateTime objects directly
                    if (in_array($this->date_preset, ['last_2_hours', 'last_4_hours'])) {
                        $this->date_from_dt = $date_range['from'];
                        $this->date_to_dt = $date_range['to'];
                    } else {
                        // For day-based presets, use date strings as before
                        $this->date_from = $date_range['from']->format('Y-m-d');
                        $this->date_to = $date_range['to']->format('Y-m-d');
                    }
                }
            }
        }
        
        // Build base query arguments
        $this->build_base_query();
    }
    
    /**
     * Build base query arguments
     */
    private function build_base_query() {
        // Map orderby to WooCommerce field
        switch ($this->orderby) {
            case 'date_modified':
                $wc_orderby = 'modified';
                break;
            case 'total':
                $wc_orderby = 'total';
                break;
            case 'order_number':
                $wc_orderby = 'ID';
                break;
            case 'customer_name':
                // Customer name requires custom sorting - use date as base then sort manually
                $wc_orderby = 'date';
                break;
            case 'date_created':
            default:
                $wc_orderby = 'date';
                break;
        }
        
        // Validate order direction
        $wc_order = strtoupper($this->order) === 'ASC' ? 'ASC' : 'DESC';
        
        // Build base query
        $this->base_query_args = array(
            'type' => 'shop_order', // Exclude refunds
            'orderby' => $wc_orderby,
            'order' => $wc_order,
            'return' => 'objects',
        );
        
        // Apply status filter
        $this->apply_status_filter();
        
        // Apply date range filter
        $this->apply_date_range_filter();
        
        // Apply advanced filters
        $this->apply_advanced_filters();
    }
    
    /**
     * Apply status filter to base query
     */
    private function apply_status_filter() {
        // If order_status is specified (for reports), use it instead of filter
        if (!empty($this->order_status)) {
            switch ($this->order_status) {
                case 'pending':
                    $this->base_query_args['status'] = array('pending', 'pending-payment');
                    break;
                case 'processing':
                    $this->base_query_args['status'] = 'processing';
                    break;
                case 'completed':
                    $this->base_query_args['status'] = 'completed';
                    break;
                case 'cancelled':
                    $this->base_query_args['status'] = 'wc-cancelled';
                    break;
                case 'refunded':
                    $this->base_query_args['status'] = 'wc-refunded';
                    break;
                case 'failed':
                    $this->base_query_args['status'] = 'wc-failed';
                    break;
                case 'on-hold':
                    $this->base_query_args['status'] = 'wc-on-hold';
                    break;
                default:
                    $this->base_query_args['status'] = array('processing', 'pending', 'pending-payment', 'completed', 'wc-on-hold', 'wc-cancelled', 'wc-refunded', 'wc-failed');
                    break;
            }
            return;
        }
        
        // Otherwise, use the filter parameter (for orders list pages)
        switch ($this->filter) {
            case 'active':
                $this->base_query_args['status'] = array('processing', 'pending');
                break;
                
            case 'kitchen':
                $this->base_query_args['status'] = 'processing';
                break;
                
            case 'ready':
                $this->base_query_args['status'] = array('pending', 'pending-payment');
                break;
                
            case 'completed':
                $this->base_query_args['status'] = 'completed';
                break;
                
            case 'on-hold':
                $this->base_query_args['status'] = 'wc-on-hold';
                break;
                
            case 'cancelled':
                $this->base_query_args['status'] = 'wc-cancelled';
                break;
                
            case 'refunded':
                $this->base_query_args['status'] = 'wc-refunded';
                break;
                
            case 'failed':
                $this->base_query_args['status'] = 'wc-failed';
                break;
                
            case 'pending-payment':
                $this->base_query_args['status'] = 'wc-pending';
                break;
                
            default: // 'all'
                $this->base_query_args['status'] = array('processing', 'pending', 'pending-payment', 'completed', 'wc-on-hold', 'wc-cancelled', 'wc-refunded', 'wc-failed');
                break;
        }
    }
    
    /**
     * Apply date range filter to base query (HPOS-compatible)
     */
    private function apply_date_range_filter() {
        // Check if we have DateTime objects (hour-based presets) or date strings (day-based presets)
        if (!empty($this->date_from_dt) && !empty($this->date_to_dt)) {
            // Hour-based presets: use DateTime objects directly
            $from_dt = clone $this->date_from_dt;
            $to_dt = clone $this->date_to_dt;
            
            // Convert to UTC (WooCommerce stores dates in UTC)
            $from_dt->setTimezone(new DateTimeZone('UTC'));
            $to_dt->setTimezone(new DateTimeZone('UTC'));
            
            $date_from_timestamp = $from_dt->getTimestamp();
            $date_to_timestamp = $to_dt->getTimestamp();
            
            $this->base_query_args['date_created'] = $date_from_timestamp . '...' . $date_to_timestamp;
            
        } elseif (!empty($this->date_from) || !empty($this->date_to)) {
            // Day-based presets: use existing logic with date strings
            $site_timezone = wp_timezone();
            
            if (!empty($this->date_from)) {
                $from_dt = new DateTime($this->date_from . ' 00:00:00', $site_timezone);
                $from_dt->setTimezone(new DateTimeZone('UTC'));
                $date_from_timestamp = $from_dt->getTimestamp();
                $this->base_query_args['date_created'] = '>=' . $date_from_timestamp;
            }
            
            if (!empty($this->date_to)) {
                $to_dt = new DateTime($this->date_to . ' 23:59:59', $site_timezone);
                $to_dt->setTimezone(new DateTimeZone('UTC'));
                $date_to_timestamp = $to_dt->getTimestamp();
                
                if (!empty($this->date_from)) {
                    $this->base_query_args['date_created'] = $date_from_timestamp . '...' . $date_to_timestamp;
                } else {
                    $this->base_query_args['date_created'] = '<=' . $date_to_timestamp;
                }
            }
        }
    }
    
    /**
     * Apply advanced filters (order type, kitchen type, staff assignment)
     */
    private function apply_advanced_filters() {
        $meta_query = array();
        
        // Order Type Filter (dinein, takeaway, delivery)
        if (!empty($this->order_type)) {
            $meta_query[] = array(
                'key' => 'exwf_odmethod',
                'value' => $this->order_type,
                'compare' => '='
            );
        }
        
        // Kitchen Type Filter (food, beverages, mixed)
        if (!empty($this->kitchen_type)) {
            // Handle both current values and legacy test data values
            $kitchen_values = array($this->kitchen_type);
            
            // Map current values to legacy test values for compatibility
            if ($this->kitchen_type === 'food') {
                $kitchen_values[] = 'food_only';
            } elseif ($this->kitchen_type === 'beverages') {
                $kitchen_values[] = 'beverages_only';
            }
            
            $meta_query[] = array(
                'key' => '_oj_kitchen_type',
                'value' => $kitchen_values,
                'compare' => 'IN'
            );
        }
        
        // Kitchen Status Filter (waiting_food, waiting_beverages, all_ready)
        if (!empty($this->kitchen_status)) {
            switch ($this->kitchen_status) {
                case 'waiting_food':
                    $meta_query[] = array(
                        'relation' => 'OR',
                        array(
                            'key' => '_oj_food_kitchen_ready',
                            'value' => 'no',
                            'compare' => '='
                        ),
                        array(
                            'key' => '_oj_food_kitchen_ready',
                            'compare' => 'NOT EXISTS'
                        )
                    );
                    break;
                    
                case 'waiting_beverages':
                    $meta_query[] = array(
                        'relation' => 'OR',
                        array(
                            'key' => '_oj_beverage_kitchen_ready',
                            'value' => 'no',
                            'compare' => '='
                        ),
                        array(
                            'key' => '_oj_beverage_kitchen_ready',
                            'compare' => 'NOT EXISTS'
                        )
                    );
                    break;
                    
                case 'all_ready':
                    $meta_query[] = array(
                        'relation' => 'AND',
                        array(
                            'key' => '_oj_food_kitchen_ready',
                            'value' => 'yes',
                            'compare' => '='
                        ),
                        array(
                            'key' => '_oj_beverage_kitchen_ready',
                            'value' => 'yes',
                            'compare' => '='
                        )
                    );
                    break;
            }
        }
        
        // Assigned Waiter Filter
        if (!empty($this->assigned_waiter)) {
            $meta_query[] = array(
                'key' => '_oj_assigned_waiter',
                'value' => $this->assigned_waiter,
                'compare' => '='
            );
        }
        
        // Unassigned Orders Filter
        if ($this->unassigned_only) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => '_oj_assigned_waiter',
                    'value' => '',
                    'compare' => '='
                ),
                array(
                    'key' => '_oj_assigned_waiter',
                    'compare' => 'NOT EXISTS'
                )
            );
        }
        
        // Payment Method Filter (cash, card, other, online, etc.)
        if (!empty($this->payment_method)) {
            if ($this->payment_method === 'cash') {
                // Cash orders: either have _oj_payment_method = 'cash' or no payment method set (defaults to cash)
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_oj_payment_method',
                        'value' => 'cash',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_payment_method',
                        'compare' => 'NOT EXISTS'
                    ),
                    array(
                        'key' => '_payment_method',
                        'value' => '',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_payment_method',
                        'value' => 'cash',
                        'compare' => '='
                    )
                );
            } elseif ($this->payment_method === 'other' || $this->payment_method === 'online') {
                // Other/Online payments: check for both 'other' and 'online' values
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_oj_payment_method',
                        'value' => array('other', 'online'),
                        'compare' => 'IN'
                    ),
                    array(
                        'key' => '_payment_method',
                        'value' => array('other', 'online'),
                        'compare' => 'IN'
                    )
                );
            } else {
                // Specific payment methods: check both custom and WooCommerce fields
                $meta_query[] = array(
                    'relation' => 'OR',
                    array(
                        'key' => '_oj_payment_method',
                        'value' => $this->payment_method,
                        'compare' => '='
                    ),
                    array(
                        'key' => '_payment_method',
                        'value' => $this->payment_method,
                        'compare' => '='
                    )
                );
            }
        }
        
        // Amount Filter - handled in post-query processing for HPOS compatibility
        
        // Add meta query to base args if we have filters
        if (!empty($meta_query)) {
            if (count($meta_query) > 1) {
                $meta_query['relation'] = 'AND';
            }
            $this->base_query_args['meta_query'] = $meta_query;
        }
    }
    
    /**
     * Get orders based on current parameters
     * 
     * @return array Array of WC_Order objects
     */
    public function get_orders() {
        $offset = ($this->page - 1) * $this->per_page;
        
        // Handle search with advanced filters
        if (!empty($this->search)) {
            // If we also have advanced filters, use combined approach
            if ($this->needs_post_query_filtering()) {
                $orders = $this->get_search_with_advanced_filters($offset);
                return $this->apply_custom_sorting($orders);
            } else {
                $orders = $this->search_orders($offset);
                return $this->apply_custom_sorting($orders);
            }
        }
        
        // Check if we need post-query filtering (when meta data doesn't exist)
        if ($this->needs_post_query_filtering()) {
            $orders = $this->get_orders_with_helper_filtering($offset);
            return $this->apply_custom_sorting($orders);
        }
        
        // Normal mode: Two-query approach for performance
        $orders = $this->get_paginated_orders($offset);
        
        // Apply custom sorting if needed
        return $this->apply_custom_sorting($orders);
    }
    
    /**
     * Search orders by order number, table, or customer name
     * 
     * @param int $offset Pagination offset
     * @return array Array of WC_Order objects
     */
    private function search_orders($offset) {
        // Order number search (numeric)
        if (is_numeric($this->search)) {
            return $this->search_by_order_number();
        }
        
        // Table number search (starts with T)
        if (strtoupper(substr($this->search, 0, 1)) === 'T') {
            return $this->search_by_table_number($offset);
        }
        
        // Customer name search
        return $this->search_by_customer_name($offset);
    }
    
    /**
     * Search by order number (direct lookup)
     * 
     * @return array Array with single order or empty
     */
    private function search_by_order_number() {
        $wc_order = wc_get_order(intval($this->search));
        
        if (!$wc_order || $wc_order->get_type() !== 'shop_order') {
            $this->total_orders = 0;
            $this->total_pages = 0;
            return array();
        }
        
        // Check if order matches current filter
        if (!$this->order_matches_filter($wc_order)) {
            $this->total_orders = 0;
            $this->total_pages = 0;
            return array();
        }
        
        $this->total_orders = 1;
        $this->total_pages = 1;
        return array($wc_order);
    }
    
    /**
     * Search by table number
     * 
     * @param int $offset Pagination offset
     * @return array Array of WC_Order objects
     */
    private function search_by_table_number($offset) {
        $table_number = strtoupper($this->search);
        
        // Get all orders with this table number
        $search_args = array_merge($this->base_query_args, array(
            'limit' => -1,
            'return' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            )
        ));
        
        $matching_order_ids = wc_get_orders($search_args);
        
        // Sort the order IDs
        $matching_order_ids = oj_sort_order_ids($matching_order_ids, $this->orderby, $this->order);
        
        $this->total_orders = count($matching_order_ids);
        $this->total_pages = ceil($this->total_orders / $this->per_page);
        
        // Paginate and fetch orders
        return $this->fetch_orders_by_ids($matching_order_ids, $offset);
    }
    
    /**
     * Search by customer name (HPOS-compatible)
     * 
     * @param int $offset Pagination offset
     * @return array Array of WC_Order objects
     */
    private function search_by_customer_name($offset) {
        global $wpdb;
        
        $search_like = '%' . $wpdb->esc_like($this->search) . '%';
        
        // Search in HPOS addresses table
        $customer_order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT order_id 
            FROM {$wpdb->prefix}wc_order_addresses 
            WHERE address_type = 'billing' 
            AND (first_name LIKE %s OR last_name LIKE %s OR CONCAT(first_name, ' ', last_name) LIKE %s)
            ORDER BY order_id DESC",
            $search_like,
            $search_like,
            $search_like
        ));
        
        // Filter by status (manual check due to HPOS limitations)
        $matching_order_ids = array();
        foreach ($customer_order_ids as $order_id) {
            $wc_order = wc_get_order($order_id);
            if ($wc_order && $this->order_matches_filter($wc_order)) {
                $matching_order_ids[] = $order_id;
            }
        }
        
        // Sort the order IDs
        $matching_order_ids = oj_sort_order_ids($matching_order_ids, $this->orderby, $this->order);
        
        $this->total_orders = count($matching_order_ids);
        $this->total_pages = ceil($this->total_orders / $this->per_page);
        
        // Paginate and fetch orders
        return $this->fetch_orders_by_ids($matching_order_ids, $offset);
    }
    
    /**
     * Check if order matches current filter
     * 
     * @param WC_Order $order Order object
     * @return bool True if matches
     */
    private function order_matches_filter($order) {
        $order_status = $order->get_status();
        
        // Check status filter
        $status_matches = false;
        switch ($this->filter) {
            case 'active':
                $status_matches = in_array($order_status, array('processing', 'pending'));
                break;
            case 'kitchen':
                $status_matches = $order_status === 'processing';
                break;
            case 'ready':
                $status_matches = in_array($order_status, array('pending', 'pending-payment'));
                break;
            case 'completed':
                $status_matches = $order_status === 'completed';
                break;
            default: // 'all'
                $status_matches = in_array($order_status, array('processing', 'pending', 'pending-payment', 'completed'));
                break;
        }
        
        if (!$status_matches) {
            return false;
        }
        
        // Check order type filter
        if (!empty($this->order_type)) {
            $order_method = $order->get_meta('exwf_odmethod');
            if ($order_method !== $this->order_type) {
                return false;
            }
        }
        
        // Check payment method filter
        if (!empty($this->payment_method)) {
            $order_payment_method = $order->get_meta('_oj_payment_method');
            if (empty($order_payment_method)) {
                $order_payment_method = $order->get_payment_method();
            }
            if (empty($order_payment_method)) {
                $order_payment_method = 'cash'; // Default to cash
            }
            
            // Check if payment method matches
            if ($this->payment_method === 'cash') {
                if (!in_array($order_payment_method, array('cash', '', 'cod'))) {
                    return false;
                }
            } elseif ($this->payment_method === 'other' || $this->payment_method === 'online') {
                if (!in_array($order_payment_method, array('other', 'online'))) {
                    return false;
                }
            } else {
                if ($order_payment_method !== $this->payment_method) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    /**
     * Fetch orders by IDs with pagination
     * 
     * @param array $order_ids Array of order IDs
     * @param int $offset Pagination offset
     * @return array Array of WC_Order objects
     */
    private function fetch_orders_by_ids($order_ids, $offset) {
        $paginated_ids = array_slice($order_ids, $offset, $this->per_page);
        
        $orders = array();
        foreach ($paginated_ids as $order_id) {
            $wc_order = wc_get_order($order_id);
            if ($wc_order) {
                $orders[] = $wc_order;
            }
        }
        
        return $orders;
    }
    
    /**
     * Get paginated orders (normal mode, no search)
     * 
     * @param int $offset Pagination offset
     * @return array Array of WC_Order objects
     */
    private function get_paginated_orders($offset) {
        // QUERY 1: Get total count (fast, IDs only)
        $count_args = array_merge($this->base_query_args, array(
            'limit' => -1,
            'return' => 'ids',
        ));
        $this->total_orders = count(wc_get_orders($count_args));
        $this->total_pages = ceil($this->total_orders / $this->per_page);
        
        // QUERY 2: Get current page orders
        $data_args = array_merge($this->base_query_args, array(
            'limit' => $this->per_page,
            'offset' => $offset,
        ));
        
        return wc_get_orders($data_args);
    }
    
    /**
     * Check if we need post-query filtering (for fields that might not have consistent meta data)
     */
    private function needs_post_query_filtering() {
        return !empty($this->kitchen_type) || !empty($this->amount_type); // Kitchen type and amount filtering need post-query processing
    }
    
    /**
     * Get orders with helper function filtering (when meta data doesn't exist)
     */
    private function get_orders_with_helper_filtering($offset) {
        // Get more orders to account for filtering
        $fetch_limit = max(100, $this->per_page * 5); // Get enough orders to filter
        
        // Remove advanced filters from meta query for base fetch
        $original_meta_query = $this->base_query_args['meta_query'] ?? array();
        $this->base_query_args['meta_query'] = $this->remove_advanced_meta_filters($original_meta_query);
        
        $fetch_args = array_merge($this->base_query_args, array(
            'limit' => $fetch_limit,
            'offset' => 0
        ));
        
        $all_orders = wc_get_orders($fetch_args);
        
        // Apply helper function filtering
        $filtered_orders = array();
        foreach ($all_orders as $order) {
            if ($this->matches_with_helpers($order)) {
                $filtered_orders[] = $order;
            }
        }
        
        // Apply pagination
        $paginated_orders = array_slice($filtered_orders, $offset, $this->per_page);
        
        // Update counts
        $this->total_orders = count($filtered_orders);
        $this->total_pages = ceil($this->total_orders / $this->per_page);
        
        // Restore original meta query
        $this->base_query_args['meta_query'] = $original_meta_query;
        
        return $paginated_orders;
    }
    
    /**
     * Remove advanced filter meta queries
     */
    private function remove_advanced_meta_filters($meta_query) {
        if (empty($meta_query)) {
            return array();
        }
        
        $filtered = array();
        foreach ($meta_query as $key => $query_part) {
            if ($key === 'relation') {
                $filtered[$key] = $query_part;
                continue;
            }
            
            // Skip meta queries that need post-query filtering
            if (is_array($query_part) && isset($query_part['key'])) {
                $meta_key = $query_part['key'];
                if (in_array($meta_key, array('_oj_kitchen_type', '_oj_assigned_waiter'))) {
                    continue;
                }
                // Keep exwf_odmethod (order type) and payment method - they work with database filtering
            }
            
            $filtered[] = $query_part;
        }
        
        return $filtered;
    }
    
    /**
     * Check if order matches using helper functions (for kitchen type only)
     */
    private function matches_with_helpers($order) {
        // Kitchen Type Filter (order type now uses meta queries)
        if (!empty($this->kitchen_type)) {
            // Use same approach as Orders Express: get kitchen_type from readiness status
            $kitchen_service = new Orders_Jet_Kitchen_Service();
            $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
            $kitchen_type = $kitchen_status['kitchen_type'];
            
            // Match Orders Express behavior: mixed orders appear in both food and beverages
            if ($this->kitchen_type === 'food') {
                // Food filter matches: food orders OR mixed orders
                if (!($kitchen_type === 'food' || $kitchen_type === 'mixed' || $kitchen_type === 'food_only')) {
                    return false;
                }
            } elseif ($this->kitchen_type === 'beverages') {
                // Beverages filter matches: beverages orders OR mixed orders
                if (!($kitchen_type === 'beverages' || $kitchen_type === 'mixed' || $kitchen_type === 'beverages_only')) {
                    return false;
                }
            } elseif ($this->kitchen_type === 'mixed') {
                // Mixed Only filter matches: only mixed orders
                if (!($kitchen_type === 'mixed')) {
                    return false;
                }
            }
        }
        
        // Amount Filter (using dedicated service)
        if (!empty($this->amount_type)) {
            try {
                $amount_filter_service = new Orders_Jet_Amount_Filter_Service();
                if (!$amount_filter_service->order_matches_amount_filter(
                    $order, 
                    $this->amount_type, 
                    $this->amount_value, 
                    $this->amount_min, 
                    $this->amount_max
                )) {
                    return false;
                }
            } catch (Exception $e) {
                oj_error_log('Error in amount filter matching: ' . $e->getMessage(), 'QUERY_BUILDER');
                // On error, don't filter out the order (fail-safe approach)
            }
        }
        
        // Customer Type Filter
        if (!empty($this->customer_type)) {
            if (!$this->matches_customer_type($order)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if order matches customer type filter
     * 
     * @param WC_Order $order The order to check
     * @return bool True if matches, false otherwise
     */
    private function matches_customer_type($order) {
        switch ($this->customer_type) {
            case 'table_guest':
                // Table guests: Orders Jet contactless orders with table numbers
                $is_contactless = $order->get_meta('_oj_contactless_order') === 'yes';
                $has_table = !empty($order->get_meta('_oj_table_number'));
                // Also check for table email pattern as backup
                $email = $order->get_billing_email();
                $has_table_email = preg_match('/^table\d+@restaurant\.local$/', $email);
                return ($is_contactless && $has_table) || $has_table_email;
                
            case 'registered_customer':
                // Registered customers: Real customers with proper emails (not table guests)
                $email = $order->get_billing_email();
                $is_table_guest = $order->get_meta('_oj_contactless_order') === 'yes' || 
                                 preg_match('/^table\d+@restaurant\.local$/', $email);
                // Must have real email and NOT be a table guest
                return !empty($email) && !$is_table_guest && 
                       !in_array($email, ['N/A', 'noreply@restaurant.local', 'guest@restaurant.local']);
                
            case 'repeat_visitor':
                // Repeat visitors: Tables with multiple orders today (Orders Jet only)
                $table_number = $order->get_meta('_oj_table_number');
                if (empty($table_number)) return false;
                
                // Count orders for this table today
                $today_start = date('Y-m-d 00:00:00');
                $today_end = date('Y-m-d 23:59:59');
                
                $table_orders_today = wc_get_orders(array(
                    'limit' => -1,
                    'date_created' => $today_start . '...' . $today_end,
                    'meta_query' => array(
                        array(
                            'key' => '_oj_table_number',
                            'value' => $table_number,
                            'compare' => '='
                        )
                    ),
                    'return' => 'ids'
                ));
                
                return count($table_orders_today) > 1;
                
            case 'new_session':
                // New table sessions: First order for a table (Orders Jet only)
                return $order->get_meta('_oj_session_start') === 'yes';
                
            case 'continuing_session':
                // Continuing sessions: Additional orders in existing session (Orders Jet only)
                $session_start = $order->get_meta('_oj_session_start');
                $session_id = $order->get_meta('_oj_session_id');
                return $session_start === 'no' && !empty($session_id);
                
            default:
                return true; // No filter or unknown filter type
        }
    }
    
    /**
     * Get search results with advanced filters applied
     */
    private function get_search_with_advanced_filters($offset) {
        // Use the same smart search logic as search_orders() but with advanced filtering
        $search_limit = max(100, $this->per_page * 5);
        
        // Remove advanced filters from meta query for search
        $original_meta_query = $this->base_query_args['meta_query'] ?? array();
        $this->base_query_args['meta_query'] = $this->remove_advanced_meta_filters($original_meta_query);
        
        // Get search results using smart search logic
        $search_orders = array();
        
        // Order number search (highest priority)
        if (is_numeric($this->search)) {
            $search_orders = $this->get_search_orders_by_number($search_limit);
        }
        // Table number search (if starts with T)
        elseif (strtoupper(substr($this->search, 0, 1)) === 'T') {
            $search_orders = $this->get_search_orders_by_table($search_limit);
        }
        // Customer name search
        else {
            $search_orders = $this->get_search_orders_by_customer($search_limit);
        }
        
        // Apply advanced filters to search results
        $filtered_orders = array();
        foreach ($search_orders as $order) {
            if ($this->matches_with_helpers($order)) {
                $filtered_orders[] = $order;
            }
        }
        
        // Apply pagination
        $paginated_orders = array_slice($filtered_orders, $offset, $this->per_page);
        
        // Update counts
        $this->total_orders = count($filtered_orders);
        $this->total_pages = ceil($this->total_orders / $this->per_page);
        
        // Restore original meta query
        $this->base_query_args['meta_query'] = $original_meta_query;
        
        return $paginated_orders;
    }
    
    /**
     * Get search orders by number (helper for combined search)
     */
    private function get_search_orders_by_number($limit) {
        $search_args = array_merge($this->base_query_args, array(
            'limit' => $limit,
            'return' => 'objects',
            's' => $this->search
        ));
        return wc_get_orders($search_args);
    }
    
    /**
     * Get search orders by table (helper for combined search)
     */
    private function get_search_orders_by_table($limit) {
        $table_number = strtoupper($this->search);
        $search_args = array_merge($this->base_query_args, array(
            'limit' => $limit,
            'return' => 'objects',
            'meta_query' => array(
                array(
                    'key' => '_oj_table_number',
                    'value' => $table_number,
                    'compare' => '='
                )
            )
        ));
        return wc_get_orders($search_args);
    }
    
    /**
     * Get search orders by customer (helper for combined search)
     */
    private function get_search_orders_by_customer($limit) {
        global $wpdb;
        
        $search_like = '%' . $wpdb->esc_like($this->search) . '%';
        
        // Search in HPOS addresses table
        $customer_order_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT order_id 
            FROM {$wpdb->prefix}wc_order_addresses 
            WHERE address_type = 'billing' 
            AND (first_name LIKE %s OR last_name LIKE %s OR CONCAT(first_name, ' ', last_name) LIKE %s)
            ORDER BY order_id DESC
            LIMIT %d",
            $search_like,
            $search_like,
            $search_like,
            $limit * 3  // Get more to account for filtering
        ));
        
        if (empty($customer_order_ids)) {
            return array();
        }
        
        // Get the order objects and manually filter them
        $matching_orders = array();
        foreach ($customer_order_ids as $order_id) {
            $wc_order = wc_get_order($order_id);
            if ($wc_order && $this->order_matches_filter($wc_order)) {
                $matching_orders[] = $wc_order;
                if (count($matching_orders) >= $limit) {
                    break;
                }
            }
        }
        
        return $matching_orders;
    }
    
    /**
     * Get filter counts with caching
     * 
     * @return array Array of counts for each filter
     */
    public function get_filter_counts() {
        $cache_key = 'oj_master_v2_filter_counts';
        if (!empty($this->date_from) || !empty($this->date_to)) {
            $cache_key .= '_' . md5($this->date_from . '_' . $this->date_to);
        }
        
        $filter_counts = get_transient($cache_key);
        
        if (false !== $filter_counts) {
            return $filter_counts;
        }
        
        // Calculate fresh counts
        $filter_counts = array(
            'all' => 0,
            'active' => 0,
            'kitchen' => 0,
            'ready' => 0,
            'completed' => 0,
            'on-hold' => 0,
            'cancelled' => 0,
            'refunded' => 0,
            'failed' => 0,
            'pending-payment' => 0,
            'food_kitchen' => 0,
            'beverage_kitchen' => 0,
            'mixed_kitchen' => 0
        );
        
        // Build base count query
        $count_query_base = array(
            'limit' => -1,
            'return' => 'ids'
        );
        
        // Add date range if set (handle both DateTime objects and date strings)
        if (!empty($this->date_from_dt) && !empty($this->date_to_dt)) {
            // Hour-based presets: use DateTime objects directly
            $from_dt = clone $this->date_from_dt;
            $to_dt = clone $this->date_to_dt;
            
            // Convert to UTC (WooCommerce stores dates in UTC)
            $from_dt->setTimezone(new DateTimeZone('UTC'));
            $to_dt->setTimezone(new DateTimeZone('UTC'));
            
            $date_from_timestamp = $from_dt->getTimestamp();
            $date_to_timestamp = $to_dt->getTimestamp();
            
            $count_query_base['date_created'] = $date_from_timestamp . '...' . $date_to_timestamp;
            
        } elseif (!empty($this->date_from) || !empty($this->date_to)) {
            // Day-based presets: use existing logic with date strings
            $site_timezone = wp_timezone();
            
            if (!empty($this->date_from)) {
                $from_dt = new DateTime($this->date_from . ' 00:00:00', $site_timezone);
                $from_dt->setTimezone(new DateTimeZone('UTC'));
                $date_from_timestamp = $from_dt->getTimestamp();
                $count_query_base['date_created'] = '>=' . $date_from_timestamp;
            }
            
            if (!empty($this->date_to)) {
                $to_dt = new DateTime($this->date_to . ' 23:59:59', $site_timezone);
                $to_dt->setTimezone(new DateTimeZone('UTC'));
                $date_to_timestamp = $to_dt->getTimestamp();
                
                if (!empty($this->date_from)) {
                    $count_query_base['date_created'] = $date_from_timestamp . '...' . $date_to_timestamp;
                } else {
                    $count_query_base['date_created'] = '<=' . $date_to_timestamp;
                }
            }
        }
        
        // Count for each filter
        $filter_counts['all'] = count(wc_get_orders(array_merge($count_query_base, array(
            'status' => array('processing', 'pending', 'pending-payment', 'completed', 'wc-on-hold', 'wc-cancelled', 'wc-refunded', 'wc-failed')
        ))));
        
        $filter_counts['active'] = count(wc_get_orders(array_merge($count_query_base, array(
            'status' => array('processing', 'pending')
        ))));
        
        $filter_counts['kitchen'] = count(wc_get_orders(array_merge($count_query_base, array(
            'status' => 'processing'
        ))));
        
        $filter_counts['ready'] = count(wc_get_orders(array_merge($count_query_base, array(
            'status' => array('pending', 'pending-payment')
        ))));
        
        $filter_counts['completed'] = count(wc_get_orders(array_merge($count_query_base, array(
            'status' => 'completed'
        ))));
        
        $filter_counts['on-hold'] = count(wc_get_orders(array_merge($count_query_base, array(
            'status' => 'wc-on-hold'
        ))));
        
        $filter_counts['cancelled'] = count(wc_get_orders(array_merge($count_query_base, array(
            'status' => 'wc-cancelled'
        ))));
        
        $filter_counts['refunded'] = count(wc_get_orders(array_merge($count_query_base, array(
            'status' => 'wc-refunded'
        ))));
        
        $filter_counts['failed'] = count(wc_get_orders(array_merge($count_query_base, array(
            'status' => 'wc-failed'
        ))));
        
        $filter_counts['pending-payment'] = count(wc_get_orders(array_merge($count_query_base, array(
            'status' => 'wc-pending'
        ))));
        
        // Calculate kitchen type counts (matching Orders Express behavior)
        $this->calculate_kitchen_type_counts($filter_counts, $count_query_base);
        
        // Cache for 30 seconds
        set_transient($cache_key, $filter_counts, 30);
        
        return $filter_counts;
    }
    
    /**
     * Calculate kitchen type counts (matching Orders Express behavior)
     * Mixed orders count in both food and beverages
     */
    private function calculate_kitchen_type_counts(&$filter_counts, $count_query_base) {
        // Get all active orders for kitchen type analysis (matching Orders Express)
        $active_orders = wc_get_orders(array_merge($count_query_base, array(
            'status' => array('processing', 'pending'),
            'return' => 'objects'
        )));
        
        $kitchen_service = new Orders_Jet_Kitchen_Service();
        
        foreach ($active_orders as $order) {
            // Use same approach as Orders Express: get kitchen_type from readiness status
            $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
            $kitchen_type = $kitchen_status['kitchen_type'];
            
            // Count like Orders Express: mixed orders count in both food and beverages
            if ($kitchen_type === 'food' || $kitchen_type === 'mixed' || $kitchen_type === 'food_only') {
                $filter_counts['food_kitchen']++;
            }
            if ($kitchen_type === 'beverages' || $kitchen_type === 'mixed' || $kitchen_type === 'beverages_only') {
                $filter_counts['beverage_kitchen']++;
            }
            if ($kitchen_type === 'mixed') {
                $filter_counts['mixed_kitchen']++;
            }
        }
    }
    
    /**
     * Get total orders count
     * 
     * @return int Total orders
     */
    public function get_total_count() {
        return $this->total_orders ?? 0;
    }
    
    /**
     * Get total pages
     * 
     * @return int Total pages
     */
    public function get_total_pages() {
        return $this->total_pages ?? 0;
    }
    
    /**
     * Get current page
     * 
     * @return int Current page
     */
    public function get_current_page() {
        return $this->page;
    }
    
    /**
     * Get per page count
     * 
     * @return int Items per page
     */
    public function get_per_page() {
        return $this->per_page;
    }
    
    /**
     * Get current filter
     * 
     * @return string Current filter
     */
    public function get_filter() {
        return $this->filter;
    }
    
    /**
     * Get search term
     * 
     * @return string Search term
     */
    public function get_search() {
        return $this->search;
    }
    
    /**
     * Get orderby field
     * 
     * @return string Orderby field
     */
    public function get_orderby() {
        return $this->orderby;
    }
    
    /**
     * Get order direction
     * 
     * @return string Order direction
     */
    public function get_order() {
        return $this->order;
    }
    
    /**
     * Get date preset
     * 
     * @return string Date preset
     */
    public function get_date_preset() {
        return $this->date_preset;
    }
    
    /**
     * Get date from
     * 
     * @return string Date from (Y-m-d)
     */
    public function get_date_from() {
        return $this->date_from;
    }
    
    /**
     * Get date to
     * 
     * @return string Date to (Y-m-d)
     */
    public function get_date_to() {
        return $this->date_to;
    }
    
    /**
     * Get date range label
     * 
     * @return string Date range label
     */
    public function get_date_range_label() {
        if (!empty($this->date_preset)) {
            $date_range = oj_calculate_date_range($this->date_preset);
            return $date_range ? $date_range['label'] : '';
        }
        
        if (!empty($this->date_from) || !empty($this->date_to)) {
            if (!empty($this->date_from) && !empty($this->date_to)) {
                return date('M j, Y', strtotime($this->date_from)) . ' - ' . date('M j, Y', strtotime($this->date_to));
            } elseif (!empty($this->date_from)) {
                return 'From ' . date('M j, Y', strtotime($this->date_from));
            } elseif (!empty($this->date_to)) {
                return 'Until ' . date('M j, Y', strtotime($this->date_to));
            }
        }
        
        return '';
    }
    
    /**
     * Get order type filter
     * 
     * @return string Order type filter
     */
    public function get_order_type() {
        return $this->order_type;
    }
    
    /**
     * Get kitchen type filter
     * 
     * @return string Kitchen type filter
     */
    public function get_kitchen_type() {
        return $this->kitchen_type;
    }
    
    /**
     * Get kitchen status filter
     * 
     * @return string Kitchen status filter
     */
    public function get_kitchen_status() {
        return $this->kitchen_status;
    }
    
    /**
     * Get assigned waiter filter
     * 
     * @return int Assigned waiter filter
     */
    public function get_assigned_waiter() {
        return $this->assigned_waiter;
    }
    
    /**
     * Get unassigned only filter
     * 
     * @return bool Unassigned only filter
     */
    public function get_unassigned_only() {
        return $this->unassigned_only;
    }
    
    /**
     * Get payment method filter
     * 
     * @return string Payment method filter
     */
    public function get_payment_method() {
        return $this->payment_method;
    }
    
    /**
     * Get amount filter type
     * 
     * @return string Amount filter type
     */
    public function get_amount_type() {
        return $this->amount_type;
    }
    
    /**
     * Get amount filter value
     * 
     * @return float Amount filter value
     */
    public function get_amount_value() {
        return $this->amount_value;
    }
    
    /**
     * Get amount minimum value
     * 
     * @return float Amount minimum value
     */
    public function get_amount_min() {
        return $this->amount_min;
    }
    
    /**
     * Get amount maximum value
     * 
     * @return float Amount maximum value
     */
    public function get_amount_max() {
        return $this->amount_max;
    }
    
    /**
     * Get current URL parameters as array
     * 
     * @return array Current parameters
     */
    public function get_current_params() {
        return array(
            'filter' => $this->filter,
            'search' => $this->search,
            'orderby' => $this->orderby,
            'order' => $this->order,
            'date_preset' => $this->date_preset,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'order_type' => $this->order_type,
            'kitchen_type' => $this->kitchen_type,
            'kitchen_status' => $this->kitchen_status,
            'assigned_waiter' => $this->assigned_waiter,
            'unassigned_only' => $this->unassigned_only,
            'payment_method' => $this->payment_method,
            'customer_type' => $this->customer_type,
            'order_status' => $this->order_status,
            'amount_type' => $this->amount_type,
            'amount_value' => $this->amount_value,
            'amount_min' => $this->amount_min,
            'amount_max' => $this->amount_max,
            'group_by' => $this->group_by
        );
    }
    
    /**
     * Get pagination data
     * 
     * @return array Pagination information
     */
    public function get_pagination_data() {
        $start = (($this->page - 1) * $this->per_page) + 1;
        $end = min($this->page * $this->per_page, $this->total_orders);
        
        return array(
            'current_page' => $this->page,
            'total_pages' => $this->total_pages,
            'per_page' => $this->per_page,
            'total_orders' => $this->total_orders,
            'start' => $start,
            'end' => $end,
            'has_prev' => $this->page > 1,
            'has_next' => $this->page < $this->total_pages
        );
    }
    
    /**
     * Apply custom sorting to orders (for fields not supported by WooCommerce)
     * 
     * @param array $orders Array of WC_Order objects
     * @return array Sorted array of WC_Order objects
     */
    private function apply_custom_sorting($orders) {
        // Only apply custom sorting for customer_name
        if ($this->orderby !== 'customer_name') {
            return $orders;
        }
        
        // Sort orders by customer name
        usort($orders, function($a, $b) {
            $name_a = trim($a->get_billing_first_name() . ' ' . $a->get_billing_last_name());
            $name_b = trim($b->get_billing_first_name() . ' ' . $b->get_billing_last_name());
            
            // Handle empty names
            if (empty($name_a)) $name_a = 'Guest';
            if (empty($name_b)) $name_b = 'Guest';
            
            $comparison = strcasecmp($name_a, $name_b);
            
            // Apply sort direction
            return ($this->order === 'ASC') ? $comparison : -$comparison;
        });
        
        return $orders;
    }
    
    /**
     * Calculate total amount of filtered orders
     * 
     * @return float Total amount
     */
    public function get_filtered_orders_total() {
        // Use same query args but get all orders (no pagination)
        $total_query_args = $this->base_query_args;
        $total_query_args['limit'] = -1;
        $total_query_args['return'] = 'objects';
        
        // If we need post-query filtering, remove advanced meta filters from the query
        if ($this->needs_post_query_filtering()) {
            $original_meta_query = $total_query_args['meta_query'] ?? array();
            $total_query_args['meta_query'] = $this->remove_advanced_meta_filters($original_meta_query);
        }
        
        $all_orders = wc_get_orders($total_query_args);
        
        // Apply post-query filtering if needed (for kitchen type, payment method, etc.)
        if ($this->needs_post_query_filtering()) {
            $filtered_orders = array();
            foreach ($all_orders as $order) {
                if ($this->matches_with_helpers($order)) {
                    $filtered_orders[] = $order;
                }
            }
            $all_orders = $filtered_orders;
        }
        
        // Apply custom sorting if needed (doesn't affect total, but for consistency)
        $all_orders = $this->apply_custom_sorting($all_orders);
        
        // Calculate total
        $total = 0;
        foreach ($all_orders as $order) {
            $total += $order->get_total();
        }
        
        return $total;
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
     * Get sales summary data for reports
     * 
     * @return array Sales summary with totals, counts, averages
     */
    public function get_sales_summary() {
        // Get all filtered orders (no pagination)
        $summary_query_args = $this->base_query_args;
        $summary_query_args['limit'] = -1;
        $summary_query_args['return'] = 'objects';
        
        // If we need post-query filtering, remove advanced meta filters from the query
        if ($this->needs_post_query_filtering()) {
            $original_meta_query = $summary_query_args['meta_query'] ?? array();
            $summary_query_args['meta_query'] = $this->remove_advanced_meta_filters($original_meta_query);
        }
        
        $all_orders = wc_get_orders($summary_query_args);
        
        // Apply post-query filtering if needed
        if ($this->needs_post_query_filtering()) {
            $filtered_orders = array();
            foreach ($all_orders as $order) {
                if ($this->matches_with_helpers($order)) {
                    $filtered_orders[] = $order;
                }
            }
            $all_orders = $filtered_orders;
        }
        
        // Calculate summary data
        $total_revenue = 0;
        $total_orders = count($all_orders);
        $order_types = array();
        $payment_methods = array();
        
        foreach ($all_orders as $order) {
            $total_revenue += $order->get_total();
            
            // Count order types
            $order_type = oj_get_order_type($order);
            if (!isset($order_types[$order_type])) {
                $order_types[$order_type] = 0;
            }
            $order_types[$order_type]++;
            
            // Count payment methods
            $payment_method = $order->get_payment_method();
            if (!isset($payment_methods[$payment_method])) {
                $payment_methods[$payment_method] = 0;
            }
            $payment_methods[$payment_method]++;
        }
        
        $average_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
        
        return array(
            'total_revenue' => $total_revenue,
            'total_revenue_formatted' => wc_price($total_revenue),
            'total_orders' => $total_orders,
            'average_order_value' => $average_order_value,
            'average_order_value_formatted' => wc_price($average_order_value),
            'order_types' => $order_types,
            'payment_methods' => $payment_methods
        );
    }
    
    /**
     * Get grouped orders data for reports
     * 
     * @return array Grouped data based on group_by field
     */
    public function get_grouped_orders_data() {
        // Get all filtered orders (no pagination)
        $group_query_args = $this->base_query_args;
        $group_query_args['limit'] = -1;
        $group_query_args['return'] = 'objects';
        
        // If we need post-query filtering, remove advanced meta filters from the query
        if ($this->needs_post_query_filtering()) {
            $original_meta_query = $group_query_args['meta_query'] ?? array();
            $group_query_args['meta_query'] = $this->remove_advanced_meta_filters($original_meta_query);
        }
        
        $all_orders = wc_get_orders($group_query_args);
        
        // Apply post-query filtering if needed
        if ($this->needs_post_query_filtering()) {
            $filtered_orders = array();
            foreach ($all_orders as $order) {
                if ($this->matches_with_helpers($order)) {
                    $filtered_orders[] = $order;
                }
            }
            $all_orders = $filtered_orders;
        }
        
        // Group orders based on group_by field
        $grouped_data = array();
        
        foreach ($all_orders as $order) {
            $group_key = $this->get_group_key($order);
            
            if (!isset($grouped_data[$group_key])) {
                $grouped_data[$group_key] = array(
                    'label' => $this->get_group_label($group_key, $order),
                    'count' => 0,
                    'total' => 0,
                    'orders' => array()
                );
            }
            
            $grouped_data[$group_key]['count']++;
            $grouped_data[$group_key]['total'] += $order->get_total();
            $grouped_data[$group_key]['orders'][] = $order;
        }
        
        // Sort grouped data
        ksort($grouped_data);
        
        return $grouped_data;
    }
    
    /**
     * Get group key for an order based on group_by field
     * 
     * @param WC_Order $order
     * @return string Group key
     */
    private function get_group_key($order) {
        switch ($this->group_by) {
            case 'date':
                return $order->get_date_created()->format('Y-m-d');
            case 'order_type':
                return oj_get_order_type($order);
            case 'kitchen_type':
                return oj_get_kitchen_type($order);
            case 'waiter':
                $waiter_id = $order->get_meta('_oj_assigned_waiter');
                return $waiter_id ? $waiter_id : 'unassigned';
            case 'payment_method':
                return $order->get_payment_method();
            default:
                return 'unknown';
        }
    }
    
    /**
     * Get group label for display
     * 
     * @param string $group_key
     * @param WC_Order $order
     * @return string Group label
     */
    private function get_group_label($group_key, $order) {
        switch ($this->group_by) {
            case 'date':
                return date('M d, Y', strtotime($group_key));
            case 'order_type':
                return oj_get_order_type_label($group_key);
            case 'kitchen_type':
                return ucfirst($group_key);
            case 'waiter':
                if ($group_key === 'unassigned') {
                    return __('Unassigned', 'orders-jet');
                }
                $waiter = get_userdata($group_key);
                return $waiter ? $waiter->display_name : __('Unknown Waiter', 'orders-jet');
            case 'payment_method':
                $payment_gateways = WC()->payment_gateways->payment_gateways();
                return isset($payment_gateways[$group_key]) ? $payment_gateways[$group_key]->get_title() : ucfirst($group_key);
            default:
                return $group_key;
        }
    }
    
    /**
     * Get customer type filter
     * 
     * @return string Customer type filter
     */
    public function get_customer_type() {
        return $this->customer_type;
    }
    
    /**
     * Get product type filter (alias for kitchen_type)
     * 
     * @return string Product type filter
     */
    public function get_product_type() {
        return $this->kitchen_type;
    }
    
    /**
     * Get order source filter (alias for order_type)
     * 
     * @return string Order source filter
     */
    public function get_order_source() {
        return $this->order_type;
    }
    
    /**
     * Get summary data grouped by period (day/week/month)
     * 
     * @return array Summary data grouped by period
     */
    public function get_summary_data() {
        global $wpdb;
        
        // Get all filtered orders
        $summary_query_args = $this->base_query_args;
        $summary_query_args['limit'] = -1;
        $summary_query_args['return'] = 'objects';
        
        // If we need post-query filtering, remove advanced meta filters
        if ($this->needs_post_query_filtering()) {
            $original_meta_query = $summary_query_args['meta_query'] ?? array();
            $summary_query_args['meta_query'] = $this->remove_advanced_meta_filters($original_meta_query);
        }
        
        $all_orders = wc_get_orders($summary_query_args);
        
        // Apply post-query filtering if needed
        if ($this->needs_post_query_filtering()) {
            $filtered_orders = array();
            foreach ($all_orders as $order) {
                if ($this->matches_with_helpers($order)) {
                    $filtered_orders[] = $order;
                }
            }
            $all_orders = $filtered_orders;
        }
        
        // Group orders by period
        $summary_data = array();
        
        foreach ($all_orders as $order) {
            $period_key = $this->get_period_key($order);
            
            if (!isset($summary_data[$period_key])) {
                $summary_data[$period_key] = array(
                    'total_orders' => 0,
                    'completed_orders' => 0,
                    'cancelled_orders' => 0,
                    'refunded_orders' => 0,
                    'pending_orders' => 0,
                    'processing_orders' => 0,
                    'on_hold_orders' => 0,
                    'total_revenue' => 0,
                    'completed_revenue' => 0,
                    'cash_orders' => 0,
                    'online_orders' => 0,
                    'cash_revenue' => 0,
                    'online_revenue' => 0,
                );
            }
            
            $status = $order->get_status();
            $total = $order->get_total();
            $payment_method = $order->get_payment_method();
            
            // Count orders by status
            $summary_data[$period_key]['total_orders']++;
            $summary_data[$period_key]['total_revenue'] += $total;
            
            if ($status === 'completed') {
                $summary_data[$period_key]['completed_orders']++;
                $summary_data[$period_key]['completed_revenue'] += $total;
            } elseif ($status === 'cancelled') {
                $summary_data[$period_key]['cancelled_orders']++;
            } elseif ($status === 'refunded') {
                $summary_data[$period_key]['refunded_orders']++;
            } elseif ($status === 'processing') {
                $summary_data[$period_key]['processing_orders']++;
            } elseif ($status === 'on-hold') {
                $summary_data[$period_key]['on_hold_orders']++;
            } elseif (in_array($status, array('pending', 'pending-payment'))) {
                $summary_data[$period_key]['pending_orders']++;
            }
            
            // Count payment methods (simplified: cash vs others)
            if (in_array($payment_method, array('cod', 'cash', ''))) {
                $summary_data[$period_key]['cash_orders']++;
                $summary_data[$period_key]['cash_revenue'] += $total;
            } else {
                $summary_data[$period_key]['online_orders']++;
                $summary_data[$period_key]['online_revenue'] += $total;
            }
        }
        
        // Sort by period key
        ksort($summary_data);
        
        return $summary_data;
    }
    
    /**
     * Get period key for an order based on grouping
     * 
     * @param WC_Order $order Order object
     * @return string Period key
     */
    private function get_period_key($order) {
        $date = $order->get_date_created();
        
        switch ($this->group_by) {
            case 'week':
                // Format: 2024-W52
                return $date->format('o-\WW');
            case 'month':
                // Format: 2024-12
                return $date->format('Y-m');
            case 'day':
            default:
                // Format: 2024-12-25
                return $date->format('Y-m-d');
        }
    }
    
    /**
     * Get period label for display
     * 
     * @param string $period_key Period key
     * @return string Formatted label
     */
    public function get_period_label($period_key) {
        switch ($this->group_by) {
            case 'week':
                // Convert 2024-W52 to "Week 52, 2024"
                if (preg_match('/(\d{4})-W(\d{2})/', $period_key, $matches)) {
                    return sprintf(__('Week %s, %s', 'orders-jet'), $matches[2], $matches[1]);
                }
                return $period_key;
            case 'month':
                // Convert 2024-12 to "December 2024"
                $timestamp = strtotime($period_key . '-01');
                return date('F Y', $timestamp);
            case 'day':
            default:
                // Convert 2024-12-25 to "Dec 25, 2024"
                $timestamp = strtotime($period_key);
                return date('M j, Y', $timestamp);
        }
    }
    
    /**
     * Get category data for category report
     * 
     * @return array Category data with order counts and revenue
     */
    public function get_category_data() {
        global $wpdb;
        
        // Get all filtered orders
        $category_query_args = $this->base_query_args;
        $category_query_args['limit'] = -1;
        $category_query_args['return'] = 'objects';
        
        // If we need post-query filtering, remove advanced meta filters
        if ($this->needs_post_query_filtering()) {
            $original_meta_query = $category_query_args['meta_query'] ?? array();
            $category_query_args['meta_query'] = $this->remove_advanced_meta_filters($original_meta_query);
        }
        
        $all_orders = wc_get_orders($category_query_args);
        
        // Apply post-query filtering if needed
        if ($this->needs_post_query_filtering()) {
            $filtered_orders = array();
            foreach ($all_orders as $order) {
                if ($this->matches_with_helpers($order)) {
                    $filtered_orders[] = $order;
                }
            }
            $all_orders = $filtered_orders;
        }
        
        // Group by category
        $category_data = array();
        $order_ids_by_category = array(); // Track which orders have been counted for each category
        
        foreach ($all_orders as $order) {
            $order_id = $order->get_id();
            $items = $order->get_items();
            
            foreach ($items as $item) {
                $product = $item->get_product();
                if (!$product) continue;
                
                // Get product categories
                $category_ids = $product->get_category_ids();
                
                if (empty($category_ids)) {
                    // Uncategorized
                    $category_key = 'uncategorized';
                    $category_name = __('Uncategorized', 'orders-jet');
                    
                    if (!isset($category_data[$category_key])) {
                        $category_data[$category_key] = array(
                            'name' => $category_name,
                            'order_count' => 0,
                            'revenue' => 0,
                            'orders' => array(),
                        );
                    }
                    
                    // Count each order only once per category
                    if (!in_array($order_id, $category_data[$category_key]['orders'])) {
                        $category_data[$category_key]['order_count']++;
                        $category_data[$category_key]['orders'][] = $order_id;
                    }
                    
                    // Add item line total to revenue
                    $category_data[$category_key]['revenue'] += $item->get_total();
                } else {
                    foreach ($category_ids as $category_id) {
                        $category = get_term($category_id, 'product_cat');
                        if (!$category || is_wp_error($category)) continue;
                        
                        $category_key = 'cat_' . $category_id;
                        $category_name = $category->name;
                        
                        if (!isset($category_data[$category_key])) {
                            $category_data[$category_key] = array(
                                'name' => $category_name,
                                'order_count' => 0,
                                'revenue' => 0,
                                'orders' => array(),
                            );
                        }
                        
                        // Count each order only once per category
                        if (!in_array($order_id, $category_data[$category_key]['orders'])) {
                            $category_data[$category_key]['order_count']++;
                            $category_data[$category_key]['orders'][] = $order_id;
                        }
                        
                        // Add item line total to revenue
                        $category_data[$category_key]['revenue'] += $item->get_total();
                    }
                }
            }
        }
        
        // Remove orders array (used only for tracking)
        foreach ($category_data as $key => $data) {
            unset($category_data[$key]['orders']);
        }
        
        // Sort by revenue descending
        uasort($category_data, function($a, $b) {
            return $b['revenue'] - $a['revenue'];
        });
        
        return $category_data;
    }
    
    /**
     * Get drill-down orders for a specific date
     * 
     * @return array Array of order details
     */
    public function get_drill_down_orders() {
        // Get all filtered orders for drill-down date
        $drill_query_args = $this->base_query_args;
        $drill_query_args['limit'] = -1;
        $drill_query_args['return'] = 'objects';
        
        $all_orders = wc_get_orders($drill_query_args);
        
        // Format order details
        $orders_data = array();
        foreach ($all_orders as $order) {
            $orders_data[] = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'customer_name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: __('Guest', 'orders-jet'),
                'status' => wc_get_order_status_name($order->get_status()),
                'status_raw' => $order->get_status(),
                'total' => $order->get_total(),
                'total_formatted' => wc_price($order->get_total()),
                'payment_method' => $order->get_payment_method_title() ?: __('N/A', 'orders-jet'),
                'date_created' => $order->get_date_created()->date_i18n('M j, Y g:i a'),
                'order_url' => admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
            );
        }
        
        return $orders_data;
    }
    
    /**
     * Get grouping mode (alias for group_by)
     * 
     * @return string Grouping mode
     */
    public function get_grouping() {
        return $this->group_by;
    }
    
    /**
     * Get order status filter
     * 
     * @return string Order status filter
     */
    public function get_order_status() {
        return $this->order_status;
    }
}

