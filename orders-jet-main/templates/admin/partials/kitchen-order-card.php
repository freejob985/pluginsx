<?php
/**
 * Kitchen Order Card - Invoice-Style Display
 * Clean, price-free interface for kitchen staff
 * 
 * @package Orders_Jet
 * @version 2.0.0
 * 
 * Expected variables:
 * @var array $order_data - Order data array
 * @var string $user_kitchen_type - Kitchen specialization (food/beverages/both)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Extract order data
$order_id = $order_data['id'];
$order_number = $order_data['number'];
$status = $order_data['status'];
$method = $order_data['method'];
$table_number = $order_data['table'];
$date_created = $order_data['date'];
$customer_name = $order_data['customer'];
$items = $order_data['items'];
$kitchen_type = $order_data['kitchen_type'];
$kitchen_status = $order_data['kitchen_status'];
$order = $order_data['order_object'];

$time_ago = human_time_diff($date_created->getTimestamp(), time());
?>

<div class="oj-kitchen-card" data-order-id="<?php echo esc_attr($order_id); ?>">
    
    <!-- Header: Order Number + Table + Time -->
    <div class="oj-kitchen-card-header">
        <div class="oj-kitchen-order-ref">
            <span class="oj-kitchen-order-number">#<?php echo esc_html($order_number); ?></span>
            <?php if (!empty($table_number)): ?>
                <span class="oj-kitchen-table">Table <?php echo esc_html($table_number); ?></span>
            <?php endif; ?>
        </div>
        <div class="oj-kitchen-meta">
            <span class="oj-kitchen-method <?php echo esc_attr($method); ?>">
                <?php 
                $method_icons = array('dinein' => '🍽️', 'takeaway' => '📦', 'delivery' => '🚚');
                echo isset($method_icons[$method]) ? $method_icons[$method] : '📋';
                ?>
            </span>
            <span class="oj-kitchen-time"><?php echo esc_html($time_ago); ?></span>
        </div>
    </div>
    
    <!-- Customer Name -->
    <?php if (!empty($customer_name)): ?>
    <div class="oj-kitchen-customer">
        <?php echo esc_html($customer_name); ?>
    </div>
    <?php endif; ?>
    
    <!-- Items List - Invoice Style -->
    <div class="oj-kitchen-items">
        <?php foreach ($order->get_items() as $item_id => $item): ?>
            <?php
            // PROVEN PRODUCT VALIDATION - Skip draft/private products
            $product = $item->get_product();
            if (!$product || $product->get_status() !== 'publish') {
                continue; // Skip draft/private products (using proven pattern)
            }
            
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // Determine item's kitchen type
            $item_kitchen = '';
            if ($variation_id > 0) {
                $item_kitchen = get_post_meta($variation_id, 'Kitchen', true);
            }
            if (empty($item_kitchen)) {
                $item_kitchen = get_post_meta($product_id, 'Kitchen', true);
            }
            $item_kitchen = strtolower(trim($item_kitchen));
            
            // Determine if this item should be highlighted
            $highlight = false;
            if ($user_kitchen_type === 'food' && $item_kitchen === 'food') {
                $highlight = true;
            } elseif ($user_kitchen_type === 'beverages' && $item_kitchen === 'beverages') {
                $highlight = true;
            } elseif ($user_kitchen_type === 'both') {
                $highlight = true;
            }
            
            $item_class = $highlight ? 'highlighted' : 'dimmed';
            ?>
            
            <div class="oj-kitchen-item <?php echo esc_attr($item_class); ?>">
                <div class="oj-kitchen-item-main">
                    <span class="oj-kitchen-qty"><?php echo $item->get_quantity(); ?>×</span>
                    <span class="oj-kitchen-name"><?php echo esc_html($item->get_name()); ?></span>
                </div>
                
                <?php
                // PROVEN VARIATIONS PROCESSING (handles multiple formats like table-query-handler.php)
                $item_variations = array();
                
                // Format 1: WooCommerce native variations
                if ($product && $product->is_type('variation')) {
                    $attributes = $product->get_variation_attributes();
                    foreach ($attributes as $attr_name => $attr_value) {
                        if (!empty($attr_value)) {
                            $label = wc_attribute_label($attr_name);
                            $item_variations[$label] = $attr_value;
                        }
                    }
                }
                
                // Format 2: Custom variations from meta (Orders Jet format)
                $item_meta = $item->get_meta_data();
                foreach ($item_meta as $meta) {
                    $meta_key = $meta->key;
                    $meta_value = $meta->value;
                    
                    // Custom variations data (structured)
                    if ($meta_key === '_oj_variations_data' && is_array($meta_value) && empty($item_variations)) {
                        foreach ($meta_value as $variation) {
                            $item_variations[$variation['name']] = $variation['value'] ?? $variation['name'];
                        }
                    }
                    // Standard WooCommerce attributes in meta (fallback)
                    elseif (empty($item_variations) && (strpos($meta_key, 'pa_') === 0 || strpos($meta_key, 'attribute_') === 0)) {
                        $attribute_name = str_replace(array('pa_', 'attribute_'), '', $meta_key);
                        $attribute_label = wc_attribute_label($attribute_name);
                        $item_variations[$attribute_label] = $meta_value;
                    }
                }
                
                // Display variations if any found (skip redundant attributes already in product name)
                if (!empty($item_variations)):
                    // Filter out common attributes that are typically already in product name
                    $filtered_variations = array();
                    foreach ($item_variations as $var_name => $var_value) {
                        // Skip size attributes if they're likely already in the product name
                        $var_name_lower = strtolower($var_name);
                        if (in_array($var_name_lower, ['size', 'pa_size', 'attribute_pa_size']) && 
                            stripos($item->get_name(), $var_value) !== false) {
                            continue; // Skip if size is already in product name
                        }
                        $filtered_variations[$var_name] = $var_value;
                    }
                    
                    if (!empty($filtered_variations)):
                ?>
                <div class="oj-kitchen-variations">
                    <?php foreach ($filtered_variations as $var_name => $var_value): ?>
                        <?php if (!empty($var_value)): ?>
                            <span class="oj-kitchen-variation">
                                <?php echo esc_html($var_name); ?>: <?php echo esc_html($var_value); ?>
                            </span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <?php endif; endif; ?>
                
                <?php
                // PROVEN ADD-ONS PROCESSING (handles 3 formats like ajax-handlers.php process_item_addons_for_details)
                $item_addons = array();
                
                // Format 1: _oj_addons_data (structured array) - PRIORITY
                $oj_addons_data = $item->get_meta('_oj_addons_data');
                if ($oj_addons_data && is_array($oj_addons_data)) {
                    foreach ($oj_addons_data as $addon) {
                        $addon_name = sanitize_text_field($addon['name'] ?? 'Add-on');
                        // Remove price information for kitchen display
                        $addon_name = preg_replace('/\s*\([^)]*\)$/', '', $addon_name); // Remove (+XX EGP)
                        $addon_name = preg_replace('/\s*\+\s*\d+[.,]?\d*\s*EGP/', '', $addon_name); // Remove + XX EGP
                        $addon_name = preg_replace('/\s*\+\s*\d+[.,]?\d*/', '', $addon_name); // Remove + XX (no currency)
                        $addon_name = trim($addon_name);
                        if (!empty($addon_name)) {
                            $item_addons[] = $addon_name;
                        }
                    }
                }
                // Format 2: WooCommerce Product Add-ons (_wc_pao_addon_value)
                elseif ($addon_data = $item->get_meta('_wc_pao_addon_value')) {
                    if (is_array($addon_data)) {
                        foreach ($addon_data as $addon) {
                            if (isset($addon['name']) && isset($addon['value'])) {
                                $addon_name = $addon['name'];
                                if (!empty($addon['value'])) {
                                    $addon_name .= ': ' . $addon['value'];
                                }
                                // Remove price information for kitchen display
                                $addon_name = preg_replace('/\s*\([^)]*\)$/', '', $addon_name); // Remove (+XX EGP)
                                $addon_name = preg_replace('/\s*\+\s*\d+[.,]?\d*\s*EGP/', '', $addon_name); // Remove + XX EGP
                                $addon_name = preg_replace('/\s*\+\s*\d+[.,]?\d*/', '', $addon_name); // Remove + XX (no currency)
                                $addon_name = trim($addon_name);
                                if (!empty($addon_name)) {
                                    $item_addons[] = $addon_name;
                                }
                            }
                        }
                    }
                }
                // Format 3: String format (_oj_item_addons) - FALLBACK
                elseif ($addons_string = $item->get_meta('_oj_item_addons')) {
                    $addon_parts = explode(', ', $addons_string);
                    foreach ($addon_parts as $addon_part) {
                        // Remove ALL price information for clean kitchen display
                        $addon_name = preg_replace('/\s*\([^)]*\)$/', '', trim($addon_part)); // Remove (+XX EGP)
                        $addon_name = preg_replace('/\s*\+\s*\d+[.,]?\d*\s*EGP/', '', $addon_name); // Remove + XX EGP
                        $addon_name = preg_replace('/\s*\+\s*\d+[.,]?\d*/', '', $addon_name); // Remove + XX (no currency)
                        $addon_name = strip_tags($addon_name);
                        if (!empty(trim($addon_name))) {
                            $item_addons[] = trim($addon_name);
                        }
                    }
                }
                
                // Display add-ons if any found
                if (!empty($item_addons)):
                ?>
                <div class="oj-kitchen-addons">
                    <?php foreach ($item_addons as $addon): ?>
                        <span class="oj-kitchen-addon">+ <?php echo esc_html($addon); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <?php
                // Get notes
                $notes = $item->get_meta('_oj_item_notes');
                if (!empty($notes)):
                ?>
                <div class="oj-kitchen-notes">
                    📝 <?php echo esc_html($notes); ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Order Notes (Customer Instructions) -->
    <?php 
    $customer_note = $order->get_customer_note();
    if (!empty($customer_note)): 
    ?>
    <div class="oj-kitchen-order-notes">
        <div class="oj-kitchen-notes-header">
            📋 <?php _e('Special Instructions:', 'orders-jet'); ?>
        </div>
        <div class="oj-kitchen-notes-content">
            <?php echo esc_html($customer_note); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Single Action Button -->
    <div class="oj-kitchen-action">
        <?php if ($status === 'processing'): ?>
            <?php if ($kitchen_type === 'mixed'): ?>
                <!-- Mixed order - show button for this kitchen's items -->
                <?php if ($user_kitchen_type === 'food' && !$kitchen_status['food_ready']): ?>
                    <button class="oj-kitchen-ready-btn oj-mark-ready-food" data-order-id="<?php echo esc_attr($order_id); ?>" data-kitchen="food">
                        🍕 <?php _e('Food Ready', 'orders-jet'); ?>
                    </button>
                <?php elseif ($user_kitchen_type === 'beverages' && !$kitchen_status['beverage_ready']): ?>
                    <button class="oj-kitchen-ready-btn oj-mark-ready-beverage" data-order-id="<?php echo esc_attr($order_id); ?>" data-kitchen="beverages">
                        🥤 <?php _e('Beverages Ready', 'orders-jet'); ?>
                    </button>
                <?php else: ?>
                    <div class="oj-kitchen-waiting">
                        ⏳ <?php _e('Waiting for other kitchen', 'orders-jet'); ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Single kitchen order -->
                <?php
                $button_icon = $user_kitchen_type === 'food' ? '🍕' : ($user_kitchen_type === 'beverages' ? '🥤' : '✅');
                $button_text = $user_kitchen_type === 'food' ? __('Food Ready', 'orders-jet') : 
                              ($user_kitchen_type === 'beverages' ? __('Beverages Ready', 'orders-jet') : __('Ready', 'orders-jet'));
                ?>
                <button class="oj-kitchen-ready-btn oj-mark-ready" data-order-id="<?php echo esc_attr($order_id); ?>" data-kitchen="<?php echo esc_attr($kitchen_type); ?>">
                    <?php echo $button_icon; ?> <?php echo $button_text; ?>
                </button>
            <?php endif; ?>
        <?php else: ?>
            <!-- Already ready -->
            <div class="oj-kitchen-complete">
                ✅ <?php _e('Ready to Serve', 'orders-jet'); ?>
            </div>
        <?php endif; ?>
    </div>
    
</div>

