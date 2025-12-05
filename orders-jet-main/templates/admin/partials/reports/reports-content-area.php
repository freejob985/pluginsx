<?php
/**
 * Orders Master V2 - Content Area Partial
 * 
 * Renders the dynamic content area that can be updated via AJAX:
 * - Orders count and total
 * - Sort links
 * - Orders grid or empty state
 * - Pagination
 * 
 * This partial is used by both:
 * - Main template (orders-master-v2.php) for full page loads
 * - AJAX endpoint (ajax_refresh_orders_content) for dynamic updates
 * 
 * Required variables (must be defined before including this partial):
 * - $orders (array) - WC_Order objects
 * - $orders_count (int) - Number of orders in current page
 * - $total_orders (int) - Total number of orders matching filters
 * - $filtered_total (float) - Total amount of filtered orders
 * - $orderby (string) - Current sort field
 * - $order (string) - Current sort direction (ASC/DESC)
 * - $total_pages (int) - Total number of pages
 * - $current_page (int) - Current page number
 * - $kitchen_service (Orders_Jet_Kitchen_Service) - Kitchen service instance
 * - $order_method_service (Orders_Jet_Order_Method_Service) - Order method service instance
 * 
 * @package Orders_Jet
 * @version 2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure required variables are available
if (!isset($orders) || !isset($kitchen_service) || !isset($order_method_service)) {
    echo '<div class="oj-error">Error: Required variables not available for content area.</div>';
    return;
}
?>

<!-- Orders Count & Sort Row -->
<div class="oj-orders-meta-row">
    <div class="oj-orders-count">
        <!-- Desktop: Show full details -->
        <span class="oj-count-text oj-count-desktop">
            <?php printf(__('Showing %d of %d orders', 'orders-jet'), $orders_count, $total_orders); ?>
        </span>
        <!-- Mobile: Show compact version -->
        <span class="oj-count-text oj-count-mobile">
            <?php printf(__('%d orders', 'orders-jet'), $total_orders); ?>
        </span>
        <?php if ($filtered_total > 0): ?>
            | <span class="oj-total-amount">
                <span class="oj-total-icon oj-total-icon-desktop">💰</span>
                <span class="oj-total-label-desktop"><?php _e('Total:', 'orders-jet'); ?> </span>
                <?php echo wc_price($filtered_total); ?>
            </span>
        <?php endif; ?>
    </div>
    <div class="oj-orders-sort">
        <span class="oj-sort-label"><?php _e('Sort by:', 'orders-jet'); ?></span>
        <?php
        // DEBUG: Check current sort values
        // error_log("SORT DEBUG: orderby = '$orderby', order = '$order'");
        
        // Build sort URLs - preserve ALL current parameters, just change orderby/order
        $base_params = array(
            'page' => 'orders-reports',
            'filter' => isset($_GET['filter']) ? $_GET['filter'] : 'all',
            'search' => isset($_GET['search']) ? $_GET['search'] : '',
            'date_preset' => isset($_GET['date_preset']) ? $_GET['date_preset'] : '',
            'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : '',
            'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : '',
            'order_type' => isset($_GET['order_type']) ? $_GET['order_type'] : '',
            'kitchen_type' => isset($_GET['kitchen_type']) ? $_GET['kitchen_type'] : '',
            'kitchen_status' => isset($_GET['kitchen_status']) ? $_GET['kitchen_status'] : '',
            'assigned_waiter' => isset($_GET['assigned_waiter']) ? $_GET['assigned_waiter'] : '',
            'unassigned_only' => isset($_GET['unassigned_only']) ? $_GET['unassigned_only'] : '',
            'payment_method' => isset($_GET['payment_method']) ? $_GET['payment_method'] : '',
            'amount_type' => isset($_GET['amount_type']) ? $_GET['amount_type'] : '',
            'amount_value' => isset($_GET['amount_value']) ? $_GET['amount_value'] : '',
            'amount_min' => isset($_GET['amount_min']) ? $_GET['amount_min'] : '',
            'amount_max' => isset($_GET['amount_max']) ? $_GET['amount_max'] : '',
            'customer_type' => isset($_GET['customer_type']) ? $_GET['customer_type'] : '', // MISSING PARAMETER ADDED!
        );
        
        // Remove empty parameters
        $base_params = array_filter($base_params, function($value) {
            return $value !== '';
        });
        
        // Date Created sort - SIMPLE: Read current, push opposite
        $date_created_params = $base_params;
        $date_created_params['orderby'] = 'date_created';
        
        // If currently Date Created DESC → link to ASC
        // If currently Date Created ASC → link to DESC  
        // If NOT Date Created → link to DESC (default)
        if ($orderby === 'date_created' && $order === 'DESC') {
            $date_created_params['order'] = 'ASC';
            $date_created_arrow = '↓'; // Show current
        } elseif ($orderby === 'date_created' && $order === 'ASC') {
            $date_created_params['order'] = 'DESC';
            $date_created_arrow = '↑'; // Show current
        } else {
            $date_created_params['order'] = 'DESC';
            $date_created_arrow = '↓'; // Show default
        }
        $date_created_url = add_query_arg($date_created_params, admin_url('admin.php'));
        $date_created_active = ($orderby === 'date_created') ? 'active' : '';
        
        // Date Modified sort - SIMPLE: Read current, push opposite
        $date_modified_params = $base_params;
        $date_modified_params['orderby'] = 'date_modified';
        
        if ($orderby === 'date_modified' && $order === 'DESC') {
            $date_modified_params['order'] = 'ASC';
            $date_modified_arrow = '↓'; // Show current
        } elseif ($orderby === 'date_modified' && $order === 'ASC') {
            $date_modified_params['order'] = 'DESC';
            $date_modified_arrow = '↑'; // Show current
        } else {
            $date_modified_params['order'] = 'ASC';
            $date_modified_arrow = '↑'; // Show default
        }
        $date_modified_url = add_query_arg($date_modified_params, admin_url('admin.php'));
        $date_modified_active = ($orderby === 'date_modified') ? 'active' : '';
        
        // Amount sort - SIMPLE: Read current, push opposite
        $amount_params = $base_params;
        $amount_params['orderby'] = 'total';
        
        if ($orderby === 'total' && $order === 'DESC') {
            $amount_params['order'] = 'ASC';
            $amount_arrow = '↓'; // Show current
        } elseif ($orderby === 'total' && $order === 'ASC') {
            $amount_params['order'] = 'DESC';
            $amount_arrow = '↑'; // Show current
        } else {
            $amount_params['order'] = 'DESC';
            $amount_arrow = '↓'; // Show default
        }
        $amount_url = add_query_arg($amount_params, admin_url('admin.php'));
        $amount_active = ($orderby === 'total') ? 'active' : '';
        ?>
        
        <a href="<?php echo esc_url($date_created_url); ?>" class="oj-sort-link <?php echo $date_created_active; ?>">
            <?php _e('Date Created', 'orders-jet'); ?> <?php echo $date_created_arrow; ?>
        </a>
        <span class="oj-sort-separator">-</span>
        <a href="<?php echo esc_url($date_modified_url); ?>" class="oj-sort-link <?php echo $date_modified_active; ?>">
            <?php _e('Date Modified', 'orders-jet'); ?> <?php echo $date_modified_arrow; ?>
        </a>
        <span class="oj-sort-separator">-</span>
        <a href="<?php echo esc_url($amount_url); ?>" class="oj-sort-link <?php echo $amount_active; ?>">
            <?php _e('Amount', 'orders-jet'); ?> <?php echo $amount_arrow; ?>
        </a>
    </div>
</div>

<?php if (!empty($orders)): ?>
    
    <!-- Single Order Actions Bar (Shown when exactly 1 order selected) -->
    <div class="oj-single-order-actions" style="display: none;">
        <div class="oj-single-order-left">
            <span class="oj-order-indicator">
                <strong class="oj-order-num"></strong>
                <span class="oj-order-badge-container"></span>
                <span class="oj-order-status-text"></span>
            </span>
        </div>
        
        <!-- Row 2: 4 Main Action Buttons (Mobile: Inline) -->
        <div class="oj-single-order-right">
            <button type="button" class="oj-action-btn" data-action="add_note">
                <span class="oj-action-icon">📝</span>
                <span class="oj-action-text"><?php _e('Note', 'orders-jet'); ?></span>
            </button>
            <button type="button" class="oj-action-btn" data-action="add_discount">
                <span class="oj-action-icon">🏷️</span>
                <span class="oj-action-text"><?php _e('Discount', 'orders-jet'); ?></span>
            </button>
            <button type="button" class="oj-action-btn" data-action="coupons">
                <span class="oj-action-icon">🎟️</span>
                <span class="oj-action-text"><?php _e('Coupons', 'orders-jet'); ?></span>
            </button>
            <button type="button" class="oj-action-btn" data-action="refund">
                <span class="oj-action-icon">💰</span>
                <span class="oj-action-text"><?php _e('Refund', 'orders-jet'); ?></span>
            </button>
        </div>
        
        <!-- Row 3: More Actions Dropdown (Mobile: Full Width) -->
        <div class="oj-gear-dropdown">
            <button type="button" class="oj-action-btn oj-gear-btn">
                <span class="oj-action-icon">⚙️</span>
                <span class="oj-action-text"><?php _e('More Actions', 'orders-jet'); ?></span>
                <span class="oj-dropdown-arrow">▼</span>
            </button>
            <ul class="oj-dropdown-menu">
                <li><a href="#" data-action="add_items">➕ <?php _e('Add Items', 'orders-jet'); ?></a></li>
                <li class="oj-dropdown-divider"></li>
                <li><a href="#" data-action="mark_pending">📊 <?php _e('Mark Pending', 'orders-jet'); ?></a></li>
                <li><a href="#" data-action="mark_on_hold">⏸️ <?php _e('Mark On-hold', 'orders-jet'); ?></a></li>
                <li><a href="#" data-action="mark_processing">👨‍🍳 <?php _e('Mark Processing', 'orders-jet'); ?></a></li>
                <li class="oj-dropdown-divider"></li>
                <li><a href="#" data-action="order_content">📋 <?php _e('Order Content', 'orders-jet'); ?></a></li>
                <li><a href="#" data-action="customer_info">👤 <?php _e('Customer Info', 'orders-jet'); ?></a></li>
                <li><a href="#" data-action="order_actions">🔧 <?php _e('Order Actions', 'orders-jet'); ?></a></li>
            </ul>
        </div>
    </div>
    
    <!-- Table Child Warning Banner (Shown for table child orders) -->
    <div class="oj-table-warning" style="display: none;">
        <span class="oj-warning-icon">⚠️</span>
        <span class="oj-warning-text">
            <?php _e('This is a table order. Use "Close Table" button to complete all items together.', 'orders-jet'); ?>
        </span>
    </div>
    
    <!-- Order Editor Modal Container -->
    <div class="oj-modal-overlay" style="display: none;">
        <div class="oj-modal-container">
            <div class="oj-modal-header">
                <h3 class="oj-modal-title"></h3>
                <button type="button" class="oj-modal-close">&times;</button>
            </div>
            <div class="oj-modal-body">
                <!-- Dynamic content will be inserted here -->
            </div>
            <div class="oj-modal-footer">
                <!-- Dynamic buttons will be inserted here -->
            </div>
        </div>
    </div>
    
    <!-- Orders Grid (Smart Cards) -->
    <div class="oj-orders-grid">
        <?php 
        // Check if customer grouping should be applied
        // Don't group when there's an active search - show all matching orders
        $customer_type = $customer_type ?? '';
        $has_search = !empty($_GET['search']);
        $should_group = function_exists('oj_should_apply_customer_grouping') 
            ? (oj_should_apply_customer_grouping($customer_type) && !$has_search) 
            : false;
        
        if ($should_group):
            // Customer grouping logic - show max 2 orders per customer + summary card
            $customer_order_tracking = array();
            $customer_orders_map = array(); // Map customer to their orders
            
            // First pass: Build customer tracking data and group orders by customer
            foreach ($orders as $wc_order): 
                $customer_key = function_exists('oj_get_customer_identifier') ? oj_get_customer_identifier($wc_order, $customer_type) : $wc_order->get_billing_email();
                
                // Initialize tracking for this customer
                if (!isset($customer_order_tracking[$customer_key])) {
                    $customer_order_tracking[$customer_key] = array(
                        'shown_count' => 0,
                        'total_orders' => function_exists('oj_count_customer_orders') ? oj_count_customer_orders($customer_key, $customer_type, $orders) : 1,
                        'total_value' => function_exists('oj_calculate_customer_total') ? oj_calculate_customer_total($customer_key, $customer_type, $orders) : $wc_order->get_total(),
                        'customer_name' => function_exists('oj_get_customer_display_name') ? oj_get_customer_display_name($wc_order) : $wc_order->get_billing_email(),
                        'summary_shown' => false
                    );
                    $customer_orders_map[$customer_key] = array();
                }
                
                // Add order to customer's order list
                $customer_orders_map[$customer_key][] = $wc_order;
            endforeach;
            
            // Sort customers by order count (highest first)
            uasort($customer_order_tracking, function($a, $b) {
                return $b['total_orders'] - $a['total_orders'];
            });
            
            // Second pass: Render orders in sorted customer order
            foreach ($customer_order_tracking as $customer_key => $tracking_data):
                $customer_orders = $customer_orders_map[$customer_key];
                $tracking = &$customer_order_tracking[$customer_key];
                
                foreach ($customer_orders as $wc_order):
                    if ($tracking['shown_count'] < 2) {
                    // Show individual order card
                    $order_data = oj_master_prepare_order_data($wc_order, $kitchen_service, $order_method_service);
                    $show_bulk_checkbox = false;
                    $use_reports_actions = true;
                    
                    // Check if this is the 2nd card and customer has more orders
                    $is_second_card_with_more = ($tracking['shown_count'] == 1 && $tracking['total_orders'] > 2);
                    
                    if ($is_second_card_with_more) {
                        // Add overlay data for the 2nd card
                        $overlay_data = array(
                            'customer_name' => $tracking['customer_name'],
                            'additional_orders' => $tracking['total_orders'] - 2,
                            'total_orders' => $tracking['total_orders'],
                            'total_value' => $tracking['total_value'],
                            'customer_key' => $customer_key
                        );
                    } else {
                        $overlay_data = null;
                    }
                    
                    include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/order-card.php';
                    $tracking['shown_count']++;
                    }
                    // Skip remaining orders (they're represented in summary card)
                endforeach; // End customer orders loop
            endforeach; // End sorted customers loop
            
        else:
            // No customer grouping - show all orders individually
            foreach ($orders as $wc_order): 
                $order_data = oj_master_prepare_order_data($wc_order, $kitchen_service, $order_method_service);
                $show_bulk_checkbox = false;
                $use_reports_actions = true;
                
                include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/order-card.php';
            endforeach;
        endif; ?>
    </div>
<?php else: ?>
    <!-- Empty State -->
    <div class="oj-empty-state">
        <div class="oj-empty-icon">📋</div>
        <h3><?php _e('No orders found', 'orders-jet'); ?></h3>
        <p><?php _e('Try adjusting your filters or search criteria.', 'orders-jet'); ?></p>
    </div>
<?php endif; ?>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
    <div class="oj-pagination-container">
        <div class="oj-pagination-info">
            <?php printf(__('Showing %d of %d | Page %d of %d', 'orders-jet'), $orders_count, $total_orders, $current_page, $total_pages); ?>
        </div>
        <div class="oj-pagination-controls">
            <?php
            // Build pagination base URL preserving all current parameters
            $current_params = $_GET;
            unset($current_params['paged']); // Remove paged to avoid duplication
            $base_url = add_query_arg($current_params, admin_url('admin.php'));
            
            $pagination_args = array(
                'base' => add_query_arg('paged', '%#%', $base_url),
                'format' => '',
                'current' => max(1, $current_page),
                'total' => $total_pages,
                'prev_text' => '‹ ' . __('Previous', 'orders-jet'),
                'next_text' => __('Next', 'orders-jet') . ' ›',
                'type' => 'plain',
                'end_size' => 1,
                'mid_size' => 2
            );
            echo paginate_links($pagination_args);
            ?>
        </div>
    </div>
<?php endif; ?>
