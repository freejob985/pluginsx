<?php
/**
 * BI Individual Data Display
 * 
 * Displays individual orders with BI context and filtering
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get individual orders from the BI query builder
$individual_orders = $display_data;
$drill_down_group = isset($_GET['drill_down_group']) ? sanitize_text_field($_GET['drill_down_group']) : '';

// Check if this is a drill-down from grouped view
$is_drill_down = !empty($drill_down_group);
?>

<div class="oj-bi-individual-data">
    <div class="oj-individual-header">
        <h3 class="oj-individual-title">
            <span class="dashicons dashicons-list-view"></span>
            <?php if ($is_drill_down): ?>
                <?php _e('Individual Orders - Filtered View', 'orders-jet'); ?>
            <?php else: ?>
                <?php _e('Individual Orders Analysis', 'orders-jet'); ?>
            <?php endif; ?>
        </h3>
        
        <?php if ($is_drill_down): ?>
        <div class="oj-drill-down-info">
            <span class="oj-drill-down-badge">
                <span class="dashicons dashicons-filter"></span>
                <?php printf(__('Filtered by: %s', 'orders-jet'), esc_html($drill_down_group)); ?>
            </span>
            <button class="oj-clear-filter-btn" onclick="clearDrillDownFilter()">
                <span class="dashicons dashicons-no-alt"></span>
                <?php _e('Clear Filter', 'orders-jet'); ?>
            </button>
        </div>
        <?php endif; ?>
        
        <div class="oj-individual-summary">
            <?php if (!empty($individual_orders)): ?>
                <?php if (!empty($pagination_info) && isset($pagination_info['total_orders'])): ?>
                <span class="oj-summary-item">
                    <strong><?php echo number_format($pagination_info['total_orders']); ?></strong> 
                    total orders
                </span>
                <span class="oj-summary-item">
                    <strong><?php echo count($individual_orders); ?></strong> 
                    on this page
                </span>
                <?php else: ?>
                <span class="oj-summary-item">
                    <strong><?php echo count($individual_orders); ?></strong> 
                    orders found
                </span>
                <?php endif; ?>
                <span class="oj-summary-item">
                    <strong><?php echo wp_kses_post(wc_price(array_sum(array_column($individual_orders, 'total')))); ?></strong> 
                    page total
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($individual_orders)): ?>
    <div class="oj-individual-orders">
        <?php foreach ($individual_orders as $order_data): ?>
            <?php 
            // Handle both WC_Order objects and BI processed arrays
            if (is_object($order_data) && method_exists($order_data, 'get_id')) {
                // Direct WC_Order object
                $order = $order_data;
                $order_id = $order->get_id();
                $order_total = $order->get_total();
                $order_status = $order->get_status();
                $order_date = $order->get_date_created();
                $customer_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
                
                // Get BI context from order meta
                $waiter_id = $order->get_meta('_oj_assigned_waiter');
                $waiter_name = $waiter_id ? get_user_meta($waiter_id, 'display_name', true) : __('Unassigned', 'orders-jet');
                $table_number = $order->get_meta('_oj_table_number');
                $has_discount = $order->get_discount_total() > 0;
                
                // Calculate shift
                $shift = __('Unknown', 'orders-jet');
                if ($order_date) {
                    $order_date->setTimezone(wp_timezone());
                    $hour = intval($order_date->format('H'));
                    if ($hour >= 6 && $hour < 14) {
                        $shift = __('Morning', 'orders-jet');
                    } elseif ($hour >= 14 && $hour < 20) {
                        $shift = __('Afternoon', 'orders-jet');
                    } else {
                        $shift = __('Night', 'orders-jet');
                    }
                }
            } else {
                // BI processed array format (from add_bi_context_to_order)
                $order_id = $order_data['id'] ?? 0;
                $order_total = $order_data['total'] ?? 0;
                $order_status = $order_data['status'] ?? 'unknown';
                $order_date = $order_data['date'] ?? null;
                $customer_name = $order_data['customer_name'] ?? __('Guest', 'orders-jet');
                
                // BI context is already processed and available
                $waiter_name = $order_data['waiter_name'] ?? __('Unassigned', 'orders-jet');
                $waiter_id = $order_data['waiter_id'] ?? '';
                $table_number = $order_data['table_number'] ?? '';
                $has_discount = $order_data['has_discount'] ?? false;
                $shift = $order_data['shift'] ?? __('Unknown', 'orders-jet');
                
                // Get full order object if needed for additional operations
                $order = isset($order_data['order_object']) ? $order_data['order_object'] : wc_get_order($order_id);
            }
            
            if (!$order_id || $order_id <= 0) continue;
            ?>
            
        <div class="oj-individual-order-card" data-order-id="<?php echo esc_attr($order_id); ?>">
            <div class="oj-order-header">
                <div class="oj-order-id">
                    <strong>#<?php echo esc_html($order_id); ?></strong>
                    <span class="oj-order-status oj-status-<?php echo esc_attr($order_status); ?>">
                        <?php echo esc_html(ucfirst($order_status)); ?>
                    </span>
                </div>
                <div class="oj-order-total">
                    <?php echo wp_kses_post(wc_price($order_total)); ?>
                </div>
            </div>
            
            <div class="oj-order-details">
                <div class="oj-detail-row">
                    <div class="oj-detail-item">
                        <span class="dashicons dashicons-admin-users"></span>
                        <span class="oj-detail-label"><?php _e('Customer:', 'orders-jet'); ?></span>
                        <span class="oj-detail-value"><?php echo esc_html($customer_name); ?></span>
                    </div>
                    
                    <div class="oj-detail-item">
                        <span class="dashicons dashicons-businessman"></span>
                        <span class="oj-detail-label"><?php _e('Waiter:', 'orders-jet'); ?></span>
                        <span class="oj-detail-value"><?php echo esc_html($waiter_name); ?></span>
                    </div>
                </div>
                
                <div class="oj-detail-row">
                    <?php if ($table_number): ?>
                    <div class="oj-detail-item">
                        <span class="dashicons dashicons-location"></span>
                        <span class="oj-detail-label"><?php _e('Table:', 'orders-jet'); ?></span>
                        <span class="oj-detail-value"><?php echo esc_html($table_number); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="oj-detail-item">
                        <span class="dashicons dashicons-clock"></span>
                        <span class="oj-detail-label"><?php _e('Shift:', 'orders-jet'); ?></span>
                        <span class="oj-detail-value"><?php echo esc_html($shift); ?></span>
                    </div>
                </div>
                
                <?php if ($order_date): ?>
                <div class="oj-detail-row">
                    <div class="oj-detail-item">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <span class="oj-detail-label"><?php _e('Date:', 'orders-jet'); ?></span>
                        <span class="oj-detail-value"><?php echo esc_html($order_date->format('M j, Y g:i A')); ?></span>
                    </div>
                    
                    <?php if ($has_discount): ?>
                    <div class="oj-detail-item oj-discount-item">
                        <span class="dashicons dashicons-tag"></span>
                        <span class="oj-detail-label"><?php _e('Discount:', 'orders-jet'); ?></span>
                        <span class="oj-detail-value">
                            <?php 
                            $discount_amount = is_array($order_data) && isset($order_data['discount_amount']) 
                                ? $order_data['discount_amount'] 
                                : ($order ? $order->get_discount_total() : 0);
                            echo wp_kses_post(wc_price($discount_amount)); 
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="oj-order-actions">
                <button class="oj-view-order-btn" data-order-id="<?php echo esc_attr($order_id); ?>">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e('View Details', 'orders-jet'); ?>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Pagination Controls -->
    <?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/bi/bi-pagination.php'; ?>
    
    <?php else: ?>
    <div class="oj-no-data">
        <div class="oj-no-data-icon">📋</div>
        <h3><?php _e('No Orders Found', 'orders-jet'); ?></h3>
        <?php if ($is_drill_down): ?>
            <p><?php _e('No individual orders found for the selected group filter.', 'orders-jet'); ?></p>
            <button class="button button-secondary" onclick="clearDrillDownFilter()">
                <?php _e('Clear Filter & View All Orders', 'orders-jet'); ?>
            </button>
        <?php else: ?>
            <p><?php _e('No orders found for the selected criteria.', 'orders-jet'); ?></p>
            <p><?php _e('Try adjusting your filters or date range.', 'orders-jet'); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Individual Orders JavaScript -->
<script>
jQuery(document).ready(function($) {
    // View order details functionality
    $('.oj-view-order-btn').on('click', function(e) {
        e.preventDefault();
        
        const orderId = $(this).data('order-id');
        
        // For now, show an alert - this can be enhanced to show a modal or navigate to order details
        alert('View Order #' + orderId + ' details\n\nThis will be enhanced to show detailed order information in a modal or navigate to the full order view.');
        
        // Future enhancement: Open order details modal or navigate to order page
        // window.open('/wp-admin/post.php?post=' + orderId + '&action=edit', '_blank');
    });
    
    // Card hover effects
    $('.oj-individual-order-card').hover(
        function() {
            $(this).addClass('oj-card-hover');
        },
        function() {
            $(this).removeClass('oj-card-hover');
        }
    );
    
    console.log('BI Individual Data loaded with <?php echo count($individual_orders); ?> orders');
});

// Clear drill-down filter function
function clearDrillDownFilter() {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.delete('drill_down_group');
    window.location.href = currentUrl.toString();
}
</script>
