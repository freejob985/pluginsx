<?php
declare(strict_types=1);
/**
 * Orders Jet - Order Details Handler
 * Handles AJAX requests for order details modal display
 * 
 * @package Orders_Jet
 * @version 2.0.0
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Order_Details_Handler {
    
    /**
     * Kitchen service instance
     * 
     * @var Orders_Jet_Kitchen_Service
     */
    private $kitchen_service;
    
    /**
     * Tax service instance
     * 
     * @var Orders_Jet_Tax_Service
     */
    private $tax_service;
    
    /**
     * Constructor
     * 
     * @param Orders_Jet_Kitchen_Service $kitchen_service Kitchen service
     * @param Orders_Jet_Tax_Service $tax_service Tax service
     */
    public function __construct($kitchen_service, $tax_service) {
        $this->kitchen_service = $kitchen_service;
        $this->tax_service = $tax_service;
    }
    
    /**
     * Handle get order details AJAX request
     * 
     * Retrieves comprehensive order information for modal display including:
     * - Order status and type badges
     * - Customer information
     * - Item details with addons and pricing
     * - Kitchen status
     * - Delivery/pickup time
     * 
     * @since 2.0.0
     */
    public function handle_get_order_details() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $order_id = intval($_POST['order_id']);
        if (!$order_id) {
            wp_send_json_error(array('message' => __('Invalid order ID', 'orders-jet')));
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => __('Order not found', 'orders-jet')));
        }
        
        try {
            // Get order method and kitchen information
            $order_method_service = new Orders_Jet_Order_Method_Service();
            $order_method = $order_method_service->get_order_method($order);
            
            $kitchen_status = $this->kitchen_service->get_kitchen_readiness_status($order);
            
            // Get delivery time information
            $delivery_time = null;
            $delivery_label = '';
            if (class_exists('OJ_Delivery_Time_Manager')) {
                $delivery_info = OJ_Delivery_Time_Manager::get_delivery_time($order);
                if ($delivery_info) {
                    $remaining_info = OJ_Delivery_Time_Manager::get_time_remaining($order);
                    $delivery_time = array(
                        'formatted' => $delivery_info['formatted'],
                        'timestamp' => $delivery_info['timestamp'],
                        'remaining_text' => $remaining_info ? $remaining_info['text'] : '',
                        'status_class' => $remaining_info ? $remaining_info['class'] : ''
                    );
                    
                    // Set appropriate label based on order type
                    switch ($order_method) {
                        case 'delivery':
                            $delivery_label = __('Delivery Time', 'orders-jet');
                            break;
                        case 'takeaway':
                            $delivery_label = __('Pickup Time', 'orders-jet');
                            break;
                        default:
                            $delivery_label = __('Expected Time', 'orders-jet');
                            break;
                    }
                }
            }
            
            // Get customer information
            $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
            if (empty($customer_name)) {
                $customer_name = $order->get_billing_email() ?: __('Guest', 'orders-jet');
            }
            
            $customer_phone = $order->get_billing_phone();
            $delivery_address = '';
            if ($order_method === 'delivery') {
                $delivery_address = $order->get_meta('_oj_delivery_address') ?: 
                                  $order->get_meta('_exwf_delivery_address') ?: 
                                  $order->get_formatted_shipping_address();
            }
            
            // Get order items with detailed breakdown
            $items = array();
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                $quantity = $item->get_quantity();
                
                // Use the same logic as the frontend orders history (table-query-handler)
                $item_data = array(
                    'name' => $item->get_name(),
                    'quantity' => $quantity,
                    'total' => wc_price($item->get_total()),
                    'unit_price' => wc_price($item->get_total() / $quantity),
                    'base_price' => 0, // Will be calculated below
                    'variations' => array(),
                    'addons' => array(),
                    'notes' => ''
                );
                
                // Process addons (QR menu format or WooCommerce Product Add-ons)
                $this->process_item_addons($item, $item_data);
                
                // Calculate base price using the same method as frontend
                $this->calculate_base_price($item, $item_data);
                
                // Set the correct unit_price and subtotal based on base_price
                $item_data['unit_price'] = wc_price($item_data['base_price']);
                $item_data['subtotal'] = wc_price($item_data['base_price'] * $quantity);
                
                // Get variation details
                if ($product && $product->is_type('variation')) {
                    $variation_attributes = $product->get_variation_attributes();
                    if (!empty($variation_attributes)) {
                        $variation_parts = array();
                        foreach ($variation_attributes as $name => $value) {
                            $attribute_name = ucfirst(str_replace('pa_', '', $name));
                            $variation_parts[] = $attribute_name . ': ' . $value;
                        }
                        $item_data['variation'] = implode(', ', $variation_parts);
                    }
                }
                
                $items[] = $item_data;
            }
            
            // Get status information
            $status = $order->get_status();
            $status_data = $this->get_status_display_data($order, $kitchen_status);
            $type_data = $this->get_type_display_data($order_method);
            $kitchen_data = $this->get_kitchen_display_data($kitchen_status);
            
            // Calculate elapsed time using local timestamps
            $created_timestamp = $order->get_date_created()->getTimestamp();
            $current_timestamp = current_time('timestamp');
            $elapsed_seconds = $current_timestamp - $created_timestamp;
            $time_elapsed = $this->format_duration($elapsed_seconds);
            
            // current_time('timestamp') gives us correct LOCAL time
            // So we need to convert the UTC created_timestamp to local time
            // The offset is: current_time('timestamp') - time() 
            $timezone_offset = $current_timestamp - time();
            $local_created_timestamp = $created_timestamp + $timezone_offset;
            
            // Prepare response data
            $order_data = array(
                'id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'status' => $status,
                'status_class' => $status_data['class'],
                'status_icon' => $status_data['icon'],
                'status_text' => $status_data['text'],
                'type_class' => $type_data['class'],
                'type_icon' => $type_data['icon'],
                'type_text' => $type_data['text'],
                'kitchen_class' => $kitchen_data['class'],
                'kitchen_icon' => $kitchen_data['icon'],
                'kitchen_text' => $kitchen_data['text'],
                'date_created' => date('M j, Y g:i A', $local_created_timestamp),
                'created_timestamp' => $created_timestamp,
                'time_elapsed' => $time_elapsed,
                'delivery_time' => $delivery_time,
                'delivery_label' => $delivery_label,
                'customer_name' => $customer_name,
                'customer_phone' => $customer_phone,
                'delivery_address' => $delivery_address,
                'table_number' => $order->get_meta('_oj_table_number'),
                'items' => $items,
                'item_count' => count($items),
                'total_formatted' => wc_price($order->get_total()),
                'special_instructions' => $order->get_customer_note(),
                'is_active' => in_array($status, array('processing', 'pending'))
            );
            
            wp_send_json_success($order_data);
            
        } catch (Exception $e) {
            oj_error_log('Error getting order details: ' . $e->getMessage(), 'ORDER_DETAILS');
            wp_send_json_error(array('message' => __('Failed to load order details', 'orders-jet')));
        }
    }
    
    /**
     * Get status display data
     * 
     * @param WC_Order $order Order object
     * @param array $kitchen_status Kitchen status data
     * @return array Status display data with class, icon, and text
     * @since 2.0.0
     */
    private function get_status_display_data($order, $kitchen_status) {
        $status = $order->get_status();
        
        if ($status === 'pending') {
            return array(
                'class' => 'ready',
                'icon' => '✅',
                'text' => __('Ready', 'orders-jet')
            );
        } elseif ($status === 'processing') {
            if ($kitchen_status['kitchen_type'] === 'mixed') {
                if ($kitchen_status['food_ready'] && !$kitchen_status['beverage_ready']) {
                    return array('class' => 'partial', 'icon' => '🍕✅ 🥤⏳', 'text' => __('Waiting for Beverages', 'orders-jet'));
                } elseif (!$kitchen_status['food_ready'] && $kitchen_status['beverage_ready']) {
                    return array('class' => 'partial', 'icon' => '🍕⏳ 🥤✅', 'text' => __('Waiting for Food', 'orders-jet'));
                } else {
                    return array('class' => 'kitchen', 'icon' => '👨‍🍳', 'text' => __('In Kitchen', 'orders-jet'));
                }
            } else {
                return array('class' => 'kitchen', 'icon' => '👨‍🍳', 'text' => __('In Kitchen', 'orders-jet'));
            }
        } else {
            return array('class' => 'completed', 'icon' => '✅', 'text' => ucfirst($status));
        }
    }
    
    /**
     * Get type display data
     * 
     * @param string $order_method Order method (dinein/takeaway/delivery)
     * @return array Type display data with class, icon, and text
     * @since 2.0.0
     */
    private function get_type_display_data($order_method) {
        switch ($order_method) {
            case 'dinein':
                return array('class' => 'dinein', 'icon' => '🍽️', 'text' => __('Dine-in', 'orders-jet'));
            case 'takeaway':
                return array('class' => 'takeaway', 'icon' => '📦', 'text' => __('Takeaway', 'orders-jet'));
            case 'delivery':
                return array('class' => 'delivery', 'icon' => '🚚', 'text' => __('Delivery', 'orders-jet'));
            default:
                return array('class' => 'unknown', 'icon' => '❓', 'text' => __('Unknown', 'orders-jet'));
        }
    }
    
    /**
     * Get kitchen display data
     * 
     * @param array $kitchen_status Kitchen status data
     * @return array Kitchen display data with class, icon, and text
     * @since 2.0.0
     */
    private function get_kitchen_display_data($kitchen_status) {
        switch ($kitchen_status['kitchen_type']) {
            case 'food':
                return array('class' => 'food', 'icon' => '🍕', 'text' => __('Food', 'orders-jet'));
            case 'beverages':
                return array('class' => 'beverages', 'icon' => '🥤', 'text' => __('Beverages', 'orders-jet'));
            case 'mixed':
                return array('class' => 'mixed', 'icon' => '🍕🥤', 'text' => __('Mixed', 'orders-jet'));
            default:
                return array('class' => 'unknown', 'icon' => '❓', 'text' => __('Unknown', 'orders-jet'));
        }
    }
    
    /**
     * Format duration in human-readable format
     * 
     * @param int $seconds Duration in seconds
     * @return string Formatted duration (e.g., "2h 30m" or "45m")
     * @since 2.0.0
     */
    private function format_duration($seconds) {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        } else {
            return sprintf('%dm', $minutes);
        }
    }
    
    /**
     * Process item addons for order details
     * Handles both QR menu and WooCommerce Product Add-ons formats
     * 
     * @param WC_Order_Item_Product $item Order item
     * @param array $item_data Item data array (passed by reference)
     * @since 2.0.0
     */
    private function process_item_addons($item, &$item_data) {
        // First try QR menu format (_oj_addons_data) - structured array
        $oj_addons_data = $item->get_meta('_oj_addons_data');
        if ($oj_addons_data && is_array($oj_addons_data)) {
            foreach ($oj_addons_data as $addon) {
                $addon_name = sanitize_text_field($addon['name'] ?? 'Add-on');
                $addon_price = floatval($addon['price'] ?? 0);
                $addon_quantity = intval($addon['quantity'] ?? 1);
                
                // The add-on price should be multiplied by item quantity for total calculation
                // But the unit price shown should be the individual add-on price
                $addon_total_per_item = $addon_price * $addon_quantity;
                $addon_total_for_all_items = $addon_total_per_item * $item_data['quantity'];
                $has_price = $addon_price > 0;
                
                // Don't include price in name - frontend will handle price display
                $display_name = $addon_name;
                
                $item_data['addons'][] = array(
                    'name' => $display_name,
                    'unit_price' => wc_price($addon_total_per_item), // Per item add-on cost
                    'subtotal' => wc_price($addon_total_for_all_items), // Total for all items
                    'has_price' => $has_price,
                    'price_value' => $addon_total_per_item // Use for calculation
                );
            }
        }
        // Fallback to WooCommerce Product Add-ons format
        elseif ($addon_data = $item->get_meta('_wc_pao_addon_value')) {
            if (is_array($addon_data)) {
                foreach ($addon_data as $addon) {
                    if (isset($addon['name']) && isset($addon['value'])) {
                        $addon_price = isset($addon['price']) ? floatval($addon['price']) : 0;
                        $addon_total = $addon_price * $item_data['quantity'];
                        $has_price = $addon_price > 0;
                        
                        $addon_name = $addon['name'];
                        
                        $item_data['addons'][] = array(
                            'name' => $addon_name,
                            'unit_price' => wc_price($addon_price),
                            'subtotal' => wc_price($addon_total),
                            'has_price' => $has_price,
                            'price_value' => $addon_price
                        );
                    }
                }
            }
        }
        // Final fallback to string format (_oj_item_addons)
        elseif ($addons_string = $item->get_meta('_oj_item_addons')) {
            // Parse string like "Combo Plus (+90.00 EGP), Soft Drink (+0.00 EGP), Frise (+0.00 EGP)"
            $addon_parts = explode(', ', $addons_string);
            foreach ($addon_parts as $addon_part) {
                // Extract name and price from format like "Combo Plus (+90.00 EGP)"
                if (preg_match('/^(.+?)\s*\(\+([0-9.,]+)\s*EGP\)$/', trim($addon_part), $matches)) {
                    $addon_name = trim($matches[1]);
                    $addon_price = floatval(str_replace(',', '.', $matches[2]));
                    $has_price = $addon_price > 0;
                    
                    $display_name = $addon_name;
                    
                    $addon_total = $addon_price * $item_data['quantity'];
                    
                    $item_data['addons'][] = array(
                        'name' => $display_name,
                        'unit_price' => wc_price($addon_price),
                        'subtotal' => wc_price($addon_total),
                        'has_price' => $has_price,
                        'price_value' => $addon_price
                    );
                } else {
                    // No price format, just name
                    $item_data['addons'][] = array(
                        'name' => trim($addon_part),
                        'unit_price' => wc_price(0),
                        'subtotal' => wc_price(0),
                        'has_price' => false,
                        'price_value' => 0
                    );
                }
            }
        }
        
        // Get item notes
        $notes = $item->get_meta('_oj_item_notes') ?: $item->get_meta('_wc_pao_addon_notes');
        if ($notes) {
            $item_data['notes'] = $notes;
        }
    }
    
    /**
     * Calculate base price for item (same logic as table-query-handler)
     * 
     * @param WC_Order_Item_Product $item Order item
     * @param array $item_data Item data array (passed by reference)
     * @since 2.0.0
     */
    private function calculate_base_price($item, &$item_data) {
        $base_price_found = false;
        
        // Check if we stored the original variant price in meta data
        $stored_base_price = $item->get_meta('_oj_base_price');
        if ($stored_base_price) {
            $item_data['base_price'] = floatval($stored_base_price);
            $base_price_found = true;
        }
        
        // If no stored price, try to calculate from current data
        if (!$base_price_found) {
            $product = $item->get_product();
            
            if ($product && $product->is_type('variation')) {
                // For variation products, try to get the variant price
                $variant_price = $product->get_price();
                if ($variant_price) {
                    $item_data['base_price'] = floatval($variant_price);
                    $base_price_found = true;
                }
            } else {
                // For non-variation products, calculate base price by subtracting add-ons
                // Use pre-calculated addon data if available, otherwise fallback to legacy method
                if (class_exists('Orders_Jet_Addon_Calculator') && Orders_Jet_Addon_Calculator::is_cache_initialized()) {
                    $order = $item->get_order();
                    $addon_total = Orders_Jet_Addon_Calculator::get_item_base_price($order->get_id(), $item->get_id());
                } else {
                    $addon_total = $this->calculate_addon_total($item_data['addons'], $item->get_quantity());
                }
                
                $item_total = $item->get_total();
                $base_price = ($item_total - $addon_total) / $item->get_quantity();
                $item_data['base_price'] = $base_price;
                $base_price_found = true;
            }
        }
    }
    
    /**
     * Calculate total addon cost (handles QR menu format)
     * 
     * @param array $addons Addons array
     * @param int $quantity Item quantity
     * @return float Total addon cost
     * @since 2.0.0
     */
    private function calculate_addon_total($addons, $quantity) {
        $addon_total = 0;
        if (!empty($addons)) {
            foreach ($addons as $addon) {
                if (isset($addon['price_value'])) {
                    // The price_value already includes the per-item add-on cost
                    // We need to multiply by item quantity for total cost
                    $addon_total += $addon['price_value'] * $quantity;
                }
            }
        }
        return $addon_total;
    }
}

