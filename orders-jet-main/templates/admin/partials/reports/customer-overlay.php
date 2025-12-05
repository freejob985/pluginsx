<?php
/**
 * Customer Overlay Template
 * Overlay shown on 2nd order card when customer has 3+ orders
 * 
 * @package Orders_Jet
 * @var array $overlay_data Overlay information
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!$overlay_data) {
    return;
}
?>

<div class="oj-customer-overlay">
    <div class="oj-overlay-backdrop"></div>
    <div class="oj-overlay-content">
        <div class="oj-overlay-header">
            <span class="oj-customer-icon">👤</span>
            <span class="oj-customer-name"><?php echo esc_html($overlay_data['customer_name']); ?></span>
        </div>
        
        <div class="oj-overlay-stats">
            <div class="oj-overlay-stat">
                <span class="oj-stat-number">+<?php echo $overlay_data['additional_orders']; ?></span>
                <span class="oj-stat-label"><?php _e('more orders', 'orders-jet'); ?></span>
            </div>
            <div class="oj-overlay-stat">
                <span class="oj-stat-number"><?php echo wc_price($overlay_data['total_value']); ?></span>
                <span class="oj-stat-label"><?php _e('total value', 'orders-jet'); ?></span>
            </div>
        </div>
        
        <div class="oj-overlay-action">
            <?php 
            $filter_url = function_exists('oj_build_customer_filter_url') 
                ? oj_build_customer_filter_url($overlay_data['customer_key'])
                : add_query_arg('search', $overlay_data['customer_key'], admin_url('admin.php?page=orders-reports'));
            ?>
            <a href="<?php echo esc_url($filter_url); ?>" 
               class="oj-overlay-btn">
                <?php _e('View all orders', 'orders-jet'); ?>
            </a>
        </div>
    </div>
</div>
