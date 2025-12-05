<?php
declare(strict_types=1);
/**
 * Orders Jet - Filter URL Builder Helper
 * Handles URL parameter building and manipulation for filters
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Filter_URL_Builder {
    
    /**
     * Build filter URL with preserved parameters
     * 
     * @param array $new_params New parameters to add/update
     * @param array $preserve_params Parameters to preserve from current URL
     * @param string $base_url Base URL (defaults to current page)
     * @return string Complete URL with parameters
     */
    public static function build_filter_url($new_params = array(), $preserve_params = array(), $base_url = '') {
        try {
            if (empty($base_url)) {
                $base_url = admin_url('admin.php');
            }
            
            // Validate input parameters
            if (!is_array($new_params)) {
                oj_error_log('Invalid new_params passed to build_filter_url', 'URL_BUILDER');
                $new_params = array();
            }
            
            if (!is_array($preserve_params)) {
                oj_error_log('Invalid preserve_params passed to build_filter_url', 'URL_BUILDER');
                $preserve_params = array();
            }
            
            // Start with current URL parameters
            $current_params = $_GET;
        
        // Ensure page parameter is always set - use current page or default to orders-master-v2
        $current_params['page'] = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'orders-master-v2';
        
        // Preserve specified parameters
        if (!empty($preserve_params)) {
            foreach ($preserve_params as $param) {
                if (isset($_GET[$param])) {
                    $current_params[$param] = $_GET[$param];
                }
            }
        }
        
        // Add/update new parameters
        foreach ($new_params as $key => $value) {
            if ($value !== '' && $value !== null) {
                $current_params[$key] = $value;
            } else {
                // Remove parameter if value is empty
                unset($current_params[$key]);
            }
        }
        
        // Reset pagination when filters change (unless explicitly preserved)
        if (!empty($new_params) && !isset($new_params['paged']) && !in_array('paged', $preserve_params)) {
            unset($current_params['paged']);
        }
        
        return add_query_arg($current_params, $base_url);
        
        } catch (Exception $e) {
            oj_error_log('Error building filter URL: ' . $e->getMessage(), 'URL_BUILDER');
            // Return safe fallback URL - use current page or default
            $fallback_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'orders-master-v2';
            return admin_url('admin.php?page=' . $fallback_page);
        }
    }
    
    /**
     * Build reset URL with only essential parameters
     * 
     * @param array $keep_params Parameters to keep (e.g., debug)
     * @return string Reset URL
     */
    public static function build_reset_url($keep_params = array()) {
        $reset_params = array(
            'page' => isset($_GET['page']) ? sanitize_text_field($_GET['page']) : 'orders-master-v2',
            'filter' => 'all',
            'orderby' => 'date_created',
            'order' => 'DESC'
        );
        
        // Preserve specified parameters
        foreach ($keep_params as $param) {
            if (isset($_GET[$param])) {
                $reset_params[$param] = $_GET[$param];
            }
        }
        
        return add_query_arg($reset_params, admin_url('admin.php'));
    }
    
    /**
     * Get current filter parameters as clean array
     * 
     * @param array $filter_keys List of filter parameter keys to extract
     * @return array Clean filter parameters
     */
    public static function get_current_filter_params($filter_keys = array()) {
        if (empty($filter_keys)) {
            $filter_keys = array(
                'filter', 'date_preset', 'date_from', 'date_to', 
                'search', 'order_type', 'kitchen_type', 'kitchen_status',
                'assigned_waiter', 'unassigned_only', 'payment_method',
                'amount_type', 'amount_value', 'amount_min', 'amount_max',
                'orderby', 'order'
            );
        }
        
        $params = array();
        foreach ($filter_keys as $key) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
                $params[$key] = sanitize_text_field($_GET[$key]);
            }
        }
        
        return $params;
    }
    
    /**
     * Check if any filters are active (excluding defaults)
     * 
     * @param array $current_params Current filter parameters
     * @return bool True if filters are active
     */
    public static function has_active_filters($current_params = array()) {
        if (empty($current_params)) {
            $current_params = self::get_current_filter_params();
        }
        
        $defaults = array(
            'filter' => 'all',
            'orderby' => 'date_created',
            'order' => 'DESC'
        );
        
        foreach ($current_params as $key => $value) {
            // Skip default values
            if (isset($defaults[$key]) && $value === $defaults[$key]) {
                continue;
            }
            
            // Skip empty values
            if (empty($value)) {
                continue;
            }
            
            // If we reach here, we have an active filter
            return true;
        }
        
        return false;
    }
    
    /**
     * Build sort URL for clickable sort links
     * 
     * @param string $orderby Field to sort by
     * @param string $current_orderby Current sort field
     * @param string $current_order Current sort direction
     * @return string Sort URL
     */
    public static function build_sort_url($orderby, $current_orderby = '', $current_order = 'DESC') {
        // Determine new sort direction
        $new_order = 'DESC';
        if ($orderby === $current_orderby && $current_order === 'DESC') {
            $new_order = 'ASC';
        }
        
        return self::build_filter_url(array(
            'orderby' => $orderby,
            'order' => $new_order
        ));
    }
    
    /**
     * Get sort arrow for display
     * 
     * @param string $orderby Field to check
     * @param string $current_orderby Current sort field
     * @param string $current_order Current sort direction
     * @return string Arrow character (↓ or ↑)
     */
    public static function get_sort_arrow($orderby, $current_orderby = '', $current_order = 'DESC') {
        if ($orderby === $current_orderby) {
            return $current_order === 'DESC' ? '↓' : '↑';
        }
        
        // Default arrow for inactive sorts
        return '↓';
    }
    
    /**
     * Get CSS class for active sort links
     * 
     * @param string $orderby Field to check
     * @param string $current_orderby Current sort field
     * @return string CSS class ('active' or '')
     */
    public static function get_sort_class($orderby, $current_orderby = '') {
        return $orderby === $current_orderby ? 'active' : '';
    }
}
