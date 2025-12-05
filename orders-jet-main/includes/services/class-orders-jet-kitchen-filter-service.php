<?php
declare(strict_types=1);
/**
 * Orders Jet - Kitchen Filter Service
 * Handles kitchen-specific order and item filtering logic
 * 
 * @package Orders_Jet
 * @since 2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Kitchen_Filter_Service {
    
    /**
     * Filter orders for kitchen users based on their specialization
     * 
     * @param array $orders Array of WC_Order objects
     * @param string $user_kitchen_type Kitchen specialization ('food', 'beverages', 'both')
     * @return array Filtered orders array
     */
    public function filter_orders_for_kitchen($orders, $user_kitchen_type) {
        if ($user_kitchen_type === 'both') {
            return $orders; // No filtering for 'both' specialization
        }
        
        $filtered_orders = array();
        
        foreach ($orders as $order) {
            if ($this->order_has_relevant_items($order, $user_kitchen_type)) {
                $filtered_orders[] = $order;
            }
        }
        
        return $filtered_orders;
    }
    
    /**
     * Check if an order has items relevant to the kitchen specialization
     * 
     * @param WC_Order $order The order to check
     * @param string $user_kitchen_type Kitchen specialization ('food', 'beverages')
     * @return bool True if order has relevant items
     */
    public function order_has_relevant_items($order, $user_kitchen_type) {
        foreach ($order->get_items() as $item) {
            $item_kitchen_type = $this->get_item_kitchen_type($item);
            
            if ($this->item_matches_kitchen($item_kitchen_type, $user_kitchen_type)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Determine the kitchen type for an order item
     * 
     * @param WC_Order_Item_Product $item The order item
     * @return string Kitchen type ('food', 'beverages', 'mixed')
     */
    public function get_item_kitchen_type($item) {
        $product_id = $item->get_product_id();
        $variation_id = $item->get_variation_id();
        
        // Check item's kitchen meta field
        $item_kitchen = '';
        if ($variation_id > 0) {
            $item_kitchen = get_post_meta($variation_id, 'Kitchen', true);
        }
        if (empty($item_kitchen)) {
            $item_kitchen = get_post_meta($product_id, 'Kitchen', true);
        }
        $item_kitchen = strtolower(trim($item_kitchen));
        
        // If no Kitchen field, use smart name-based detection
        if (empty($item_kitchen)) {
            $item_kitchen = $this->detect_kitchen_type_by_name($item->get_name());
        }
        
        return $item_kitchen;
    }
    
    /**
     * Detect kitchen type based on product name patterns
     * 
     * @param string $item_name Product name
     * @return string Kitchen type ('food' or 'beverages')
     */
    private function detect_kitchen_type_by_name($item_name) {
        $item_name_lower = strtolower($item_name);
        
        // Beverage keywords
        $beverage_keywords = array(
            'tea', 'coffee', 'juice', 'fayrouz', 'drink', 'soda', 
            'water', 'milk', 'latte', 'cappuccino', 'espresso',
            'smoothie', 'shake', 'cocktail', 'beer', 'wine'
        );
        
        foreach ($beverage_keywords as $keyword) {
            if (strpos($item_name_lower, $keyword) !== false) {
                return 'beverages';
            }
        }
        
        // Default to food for everything else
        return 'food';
    }
    
    /**
     * Check if an item kitchen type matches user's kitchen specialization
     * 
     * @param string $item_kitchen_type Item's kitchen type
     * @param string $user_kitchen_type User's kitchen specialization
     * @return bool True if item is relevant to user's kitchen
     */
    private function item_matches_kitchen($item_kitchen_type, $user_kitchen_type) {
        if ($user_kitchen_type === 'food') {
            return in_array($item_kitchen_type, array('food', 'mixed'));
        } elseif ($user_kitchen_type === 'beverages') {
            return in_array($item_kitchen_type, array('beverages', 'mixed'));
        }
        
        return false;
    }
    
    /**
     * Get appropriate order statuses for kitchen vs manager users
     * 
     * @param bool $is_kitchen_user Whether the user is a kitchen user
     * @return array Array of WooCommerce order status strings
     */
    public function get_order_statuses_for_user($is_kitchen_user) {
        if ($is_kitchen_user) {
            // Kitchen users only see orders that need preparation (processing status)
            return array('wc-processing');
        } else {
            // Manager users see both processing and ready orders
            return array('wc-pending', 'wc-processing');
        }
    }
    
    /**
     * Check if an order should be displayed in a specific kitchen
     * 
     * @param WC_Order $order The order to check
     * @param string $order_kitchen_type The order's kitchen type (from order data)
     * @param string $user_kitchen_type User's kitchen specialization ('food', 'beverages', 'both')
     * @return bool True if order should be shown in this kitchen
     */
    public function should_show_order_in_kitchen($order, $order_kitchen_type, $user_kitchen_type) {
        // 'both' kitchen type shows all orders (no filtering)
        if ($user_kitchen_type === 'both') {
            return true;
        }
        
        // Check if order type matches kitchen specialization
        if (!$this->order_type_matches_kitchen($order_kitchen_type, $user_kitchen_type)) {
            return false;
        }
        
        // Check if this kitchen's part is already completed (clean view)
        if ($this->is_kitchen_part_completed($order, $user_kitchen_type)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if order type matches kitchen specialization
     * 
     * @param string $order_kitchen_type Order's kitchen type
     * @param string $user_kitchen_type User's kitchen specialization
     * @return bool True if order type matches kitchen
     */
    private function order_type_matches_kitchen($order_kitchen_type, $user_kitchen_type) {
        if ($user_kitchen_type === 'food') {
            return in_array($order_kitchen_type, ['food', 'mixed']);
        } elseif ($user_kitchen_type === 'beverages') {
            return in_array($order_kitchen_type, ['beverages', 'mixed']);
        }
        
        return false;
    }
    
    /**
     * Check if this kitchen's part of the order is already completed
     * 
     * @param WC_Order $order The order to check
     * @param string $user_kitchen_type User's kitchen specialization
     * @return bool True if this kitchen's part is already done
     */
    private function is_kitchen_part_completed($order, $user_kitchen_type) {
        if ($user_kitchen_type === 'food') {
            return $order->get_meta('_oj_food_kitchen_ready') === 'yes';
        } elseif ($user_kitchen_type === 'beverages') {
            return $order->get_meta('_oj_beverage_kitchen_ready') === 'yes';
        }
        
        return false;
    }
}
