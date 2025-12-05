<?php
declare(strict_types=1);
/**
 * Orders Jet - Dashboard Analytics Handler Class
 * Handles dashboard filter counts and analytics data
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Dashboard_Analytics_Handler {
    
    /**
     * Get filter counts for dashboard
     * 
     * @return array Filter counts data
     * @throws Exception On processing errors
     */
    public function get_filter_counts() {
        // Get counts for each filter
        $counts = array();
        
        // All orders (processing, pending, completed) - OPTIMIZED COUNT
        $all_orders = wc_get_orders(array(
            'status' => array('wc-processing', 'wc-pending', 'wc-completed'),
            'limit' => 1,
            'paginate' => true,
            'return' => 'ids'
        ));
        $counts['all'] = $all_orders->total;
        
        // Active orders (processing, pending) - OPTIMIZED COUNT
        $active_orders = wc_get_orders(array(
            'status' => array('wc-processing', 'wc-pending'),
            'limit' => 1,
            'paginate' => true,
            'return' => 'ids'
        ));
        $counts['active'] = $active_orders->total;
        
        // Processing orders - OPTIMIZED COUNT
        $processing_orders = wc_get_orders(array(
            'status' => 'wc-processing',
            'limit' => 1,
            'paginate' => true,
            'return' => 'ids'
        ));
        $counts['processing'] = $processing_orders->total;
        
        // Pending orders - OPTIMIZED COUNT
        $pending_orders = wc_get_orders(array(
            'status' => 'wc-pending',
            'limit' => 1,
            'paginate' => true,
            'return' => 'ids'
        ));
        $counts['pending'] = $pending_orders->total;
        
        // Completed orders - OPTIMIZED COUNT
        $completed_orders = wc_get_orders(array(
            'status' => 'wc-completed',
            'limit' => 1,
            'paginate' => true,
            'return' => 'ids'
        ));
        $counts['completed'] = $completed_orders->total;
        
        // Dine-in orders (processing, pending) - OPTIMIZED COUNT
        $dinein_orders = wc_get_orders(array(
            'status' => array('wc-processing', 'wc-pending'),
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'exwf_odmethod',
                    'value' => 'dinein',
                    'compare' => '='
                ),
                array(
                    'key' => '_oj_table_number',
                    'compare' => 'EXISTS'
                )
            ),
            'limit' => 1,
            'paginate' => true,
            'return' => 'ids'
        ));
        $counts['dinein'] = $dinein_orders->total;
        
        // Takeaway orders (processing, pending) - OPTIMIZED COUNT
        $takeaway_orders = wc_get_orders(array(
            'status' => array('wc-processing', 'wc-pending'),
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'exwf_odmethod',
                    'value' => 'takeaway',
                    'compare' => '='
                ),
                array(
                    'key' => '_oj_table_number',
                    'value' => '',
                    'compare' => 'NOT EXISTS'
                )
            ),
            'limit' => 1,
            'paginate' => true,
            'return' => 'ids'
        ));
        $counts['takeaway'] = $takeaway_orders->total;
        
        // Delivery orders (processing, pending) - OPTIMIZED COUNT
        $delivery_orders = wc_get_orders(array(
            'status' => array('wc-processing', 'wc-pending'),
            'meta_query' => array(
                array(
                    'key' => 'exwf_odmethod',
                    'value' => 'delivery',
                    'compare' => '='
                )
            ),
            'limit' => 1,
            'paginate' => true,
            'return' => 'ids'
        ));
        $counts['delivery'] = $delivery_orders->total;
        
        return $counts;
    }
    
    /**
     * Get order counts by status
     * 
     * @param array $statuses Order statuses to count
     * @return int Order count
     */
    private function get_order_count_by_status($statuses) {
        // OPTIMIZED COUNT - Use pagination to get total without fetching all records
        $orders = wc_get_orders(array(
            'status' => $statuses,
            'limit' => 1,
            'paginate' => true,
            'return' => 'ids'
        ));
        
        return $orders->total;
    }
    
    /**
     * Get order counts by order method
     * 
     * @param string $method Order method (dinein, takeaway, delivery)
     * @param array $statuses Order statuses to include
     * @return int Order count
     */
    private function get_order_count_by_method($method, $statuses = array('wc-processing', 'wc-pending')) {
        $meta_query = array();
        
        switch ($method) {
            case 'dinein':
                $meta_query = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'exwf_odmethod',
                        'value' => 'dinein',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_oj_table_number',
                        'compare' => 'EXISTS'
                    )
                );
                break;
                
            case 'takeaway':
                $meta_query = array(
                    'relation' => 'OR',
                    array(
                        'key' => 'exwf_odmethod',
                        'value' => 'takeaway',
                        'compare' => '='
                    ),
                    array(
                        'key' => '_oj_table_number',
                        'value' => '',
                        'compare' => 'NOT EXISTS'
                    )
                );
                break;
                
            case 'delivery':
                $meta_query = array(
                    array(
                        'key' => 'exwf_odmethod',
                        'value' => 'delivery',
                        'compare' => '='
                    )
                );
                break;
        }
        
        // OPTIMIZED COUNT - Use pagination to get total without fetching all records
        $orders = wc_get_orders(array(
            'status' => $statuses,
            'meta_query' => $meta_query,
            'limit' => 1,
            'paginate' => true,
            'return' => 'ids'
        ));
        
        return $orders->total;
    }
}
