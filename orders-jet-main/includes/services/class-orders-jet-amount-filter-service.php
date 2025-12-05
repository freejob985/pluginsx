<?php
declare(strict_types=1);
/**
 * Orders Jet - Amount Filter Service
 * Handles order amount filtering logic with proper encapsulation
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Amount_Filter_Service {
    
    /**
     * Check if order matches amount filter criteria
     * 
     * @param WC_Order $order The order to check
     * @param string $filter_type Filter type (equals, less_than, greater_than, between)
     * @param float $filter_value Single value for equals/less_than/greater_than
     * @param float $filter_min Minimum value for between filter
     * @param float $filter_max Maximum value for between filter
     * @return bool True if order matches filter criteria
     */
    public function order_matches_amount_filter($order, $filter_type, $filter_value = 0.0, $filter_min = 0.0, $filter_max = 0.0) {
        // Validate order object
        if (!$order || !is_a($order, 'WC_Order')) {
            oj_error_log('Invalid order object passed to amount filter', 'AMOUNT_FILTER');
            return true; // Don't filter out invalid orders, let them pass through
        }
        
        if (empty($filter_type)) {
            return true; // No filter applied
        }
        
        try {
            $order_total = $order->get_total();
        } catch (Exception $e) {
            oj_error_log('Error getting order total: ' . $e->getMessage(), 'AMOUNT_FILTER');
            return true; // Don't filter out orders with errors
        }
        
        switch ($filter_type) {
            case 'equals':
                return $this->matches_equals($order_total, $filter_value);
                
            case 'less_than':
                return $this->matches_less_than($order_total, $filter_value);
                
            case 'greater_than':
                return $this->matches_greater_than($order_total, $filter_value);
                
            case 'between':
                return $this->matches_between($order_total, $filter_min, $filter_max);
                
            default:
                return true; // Unknown filter type, don't filter
        }
    }
    
    /**
     * Check if order total equals filter value (with float precision tolerance)
     * 
     * @param float $order_total Order total amount
     * @param float $filter_value Target amount
     * @return bool True if amounts match within tolerance
     */
    private function matches_equals($order_total, $filter_value) {
        if ($filter_value <= 0) {
            return true; // Invalid filter value
        }
        
        // Use 0.01 tolerance for float precision issues
        return abs($order_total - $filter_value) <= 0.01;
    }
    
    /**
     * Check if order total is less than filter value
     * 
     * @param float $order_total Order total amount
     * @param float $filter_value Maximum amount (exclusive)
     * @return bool True if order total is less than filter value
     */
    private function matches_less_than($order_total, $filter_value) {
        if ($filter_value <= 0) {
            return true; // Invalid filter value
        }
        
        return $order_total < $filter_value;
    }
    
    /**
     * Check if order total is greater than filter value
     * 
     * @param float $order_total Order total amount
     * @param float $filter_value Minimum amount (exclusive)
     * @return bool True if order total is greater than filter value
     */
    private function matches_greater_than($order_total, $filter_value) {
        if ($filter_value <= 0) {
            return true; // Invalid filter value
        }
        
        return $order_total > $filter_value;
    }
    
    /**
     * Check if order total is within range (inclusive)
     * 
     * @param float $order_total Order total amount
     * @param float $filter_min Minimum amount (inclusive)
     * @param float $filter_max Maximum amount (inclusive)
     * @return bool True if order total is within range
     */
    private function matches_between($order_total, $filter_min, $filter_max) {
        if ($filter_min <= 0 || $filter_max <= 0 || $filter_max < $filter_min) {
            return true; // Invalid filter range
        }
        
        return $order_total >= $filter_min && $order_total <= $filter_max;
    }
    
    /**
     * Validate amount filter parameters
     * 
     * @param array $params Filter parameters
     * @return array Validated and sanitized parameters
     */
    public function validate_amount_filter_params($params) {
        $validated = array(
            'amount_type' => '',
            'amount_value' => 0.0,
            'amount_min' => 0.0,
            'amount_max' => 0.0
        );
        
        // Validate amount type
        if (isset($params['amount_type'])) {
            $amount_type = sanitize_text_field($params['amount_type']);
            if (in_array($amount_type, array('equals', 'less_than', 'greater_than', 'between'))) {
                $validated['amount_type'] = $amount_type;
            }
        }
        
        // Validate numeric values
        if (isset($params['amount_value'])) {
            $validated['amount_value'] = max(0.0, floatval($params['amount_value']));
        }
        
        if (isset($params['amount_min'])) {
            $validated['amount_min'] = max(0.0, floatval($params['amount_min']));
        }
        
        if (isset($params['amount_max'])) {
            $validated['amount_max'] = max(0.0, floatval($params['amount_max']));
        }
        
        // Validate range logic
        if ($validated['amount_type'] === 'between' && $validated['amount_max'] < $validated['amount_min']) {
            // Swap values if max is less than min
            $temp = $validated['amount_min'];
            $validated['amount_min'] = $validated['amount_max'];
            $validated['amount_max'] = $temp;
        }
        
        return $validated;
    }
    
    /**
     * Get human-readable description of amount filter
     * 
     * @param string $filter_type Filter type
     * @param float $filter_value Single value
     * @param float $filter_min Minimum value
     * @param float $filter_max Maximum value
     * @return string Human-readable filter description
     */
    public function get_amount_filter_description($filter_type, $filter_value = 0.0, $filter_min = 0.0, $filter_max = 0.0) {
        switch ($filter_type) {
            case 'equals':
                return sprintf(__('Amount equals %s', 'orders-jet'), wc_price($filter_value));
                
            case 'less_than':
                return sprintf(__('Amount less than %s', 'orders-jet'), wc_price($filter_value));
                
            case 'greater_than':
                return sprintf(__('Amount greater than %s', 'orders-jet'), wc_price($filter_value));
                
            case 'between':
                return sprintf(__('Amount between %s and %s', 'orders-jet'), wc_price($filter_min), wc_price($filter_max));
                
            default:
                return __('Any amount', 'orders-jet');
        }
    }
}
