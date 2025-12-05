<?php
/**
 * Waiter View - Orders-Focused Dashboard
 * Reuses Orders Express design system with waiter-specific filtering
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Security check - verify user permissions
if (!current_user_can('access_oj_waiter_dashboard') && !current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

// ============================================================================
// WAITER-SPECIFIC FILTERING
// ============================================================================

$current_user_id = get_current_user_id();

// Get waiter's assigned tables from user meta
$assigned_tables = get_user_meta($current_user_id, '_oj_assigned_tables', true);

// Fallback: if no assigned tables, show empty state
if (empty($assigned_tables) || !is_array($assigned_tables)) {
    $assigned_tables = array(); // Empty array = no orders will show
}

// ============================================================================
// ENQUEUE ASSETS (Reuse Express design system)
// ============================================================================

wp_enqueue_style('oj-manager-orders-cards', ORDERS_JET_PLUGIN_URL . 'assets/css/manager-orders-cards.css', array(), ORDERS_JET_VERSION);
wp_enqueue_style('oj-dashboard-express', ORDERS_JET_PLUGIN_URL . 'assets/css/dashboard-express.css', array('oj-manager-orders-cards'), ORDERS_JET_VERSION);

// Enqueue notification system with waiter-specific sounds
wp_enqueue_script('orders-jet-admin', ORDERS_JET_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), ORDERS_JET_VERSION, true);
wp_localize_script('orders-jet-admin', 'OrdersJetAdmin', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonces' => array(
        'dashboard' => wp_create_nonce('oj_dashboard_nonce')
    )
));

wp_enqueue_script('oj-dashboard-express', ORDERS_JET_PLUGIN_URL . 'assets/js/dashboard-express.js', array('jquery', 'orders-jet-admin'), ORDERS_JET_VERSION, true);
wp_localize_script('oj-dashboard-express', 'ojExpressData', array(
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'adminUrl' => admin_url('post.php'),
    'nonces' => array(
        'dashboard' => wp_create_nonce('oj_dashboard_nonce'),
        'table_order' => wp_create_nonce('oj_table_order'),
        'invoice' => wp_create_nonce('oj_get_invoice')
    ),
    'i18n' => array(
        'confirming' => __('Confirming...', 'orders-jet'),
        'paid' => __('Paid?', 'orders-jet'),
        'paymentConfirmed' => __('Payment confirmed', 'orders-jet'),
        'closing' => __('Closing...', 'orders-jet'),
        'closeTable' => __('Close Table', 'orders-jet'),
        'forceClosing' => __('Force Closing...', 'orders-jet'),
        'clickToContinue' => __('Click OK to continue anyway.', 'orders-jet'),
        'paymentMethod' => __('Payment Method', 'orders-jet'),
        'howPaid' => __('How was this order paid?', 'orders-jet'),
        'cash' => __('Cash', 'orders-jet'),
        'card' => __('Card', 'orders-jet'),
        'other' => __('Other', 'orders-jet'),
        'dinein' => __('Dine-in', 'orders-jet'),
        'combined' => __('Combined', 'orders-jet'),
        'ready' => __('Ready', 'orders-jet'),
        'viewOrderDetails' => __('View order details', 'orders-jet')
    )
));

// ============================================================================
// INITIALIZE SERVICES
// ============================================================================

$kitchen_service = new Orders_Jet_Kitchen_Service();
$order_method_service = new Orders_Jet_Order_Method_Service();

// ============================================================================
// HELPER FUNCTIONS (Reuse from Orders Express)
// ============================================================================

if (!function_exists('oj_express_prepare_order_data')) {
    function oj_express_prepare_order_data($order, $kitchen_service, $order_method_service) {
        $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
        
        // Pre-process items text for performance
        $items = $order->get_items();
        $items_text = array();
        foreach ($items as $item) {
            $product_name = $item->get_name();
            $quantity = $item->get_quantity();
            $items_text[] = esc_html($quantity) . 'x ' . esc_html($product_name);
        }
        $items_display = implode(' ', $items_text);
        
        // Check for guest invoice request
        $table_number = $order->get_meta('_oj_table_number');
        $guest_invoice_requested = false;
        $invoice_request_time = '';
        
        if (!empty($table_number)) {
            $table_posts = get_posts(array(
                'post_type' => 'oj_table',
                'meta_query' => array(
                    array(
                        'key' => '_oj_table_number',
                        'value' => $table_number,
                        'compare' => '='
                    )
                ),
                'posts_per_page' => 1
            ));
            
            if (!empty($table_posts)) {
                $table_id = $table_posts[0]->ID;
                $invoice_request_status = get_post_meta($table_id, '_oj_invoice_request_status', true);
                $guest_invoice_requested = ($invoice_request_status === 'pending');
                $invoice_request_time = get_post_meta($table_id, '_oj_guest_invoice_requested', true);
            }
        }

        return array(
            'id' => $order->get_id(),
            'number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'method' => $order_method_service->get_order_method($order),
            'table' => $table_number,
            'total' => $order->get_total(),
            'date' => $order->get_date_created(),
            'customer' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: $order->get_billing_email() ?: __('Guest', 'orders-jet'),
            'items' => $items,
            'items_display' => $items_display,
            'item_count' => count($items),
            'kitchen_type' => $kitchen_status['kitchen_type'],
            'kitchen_status' => $kitchen_status,
            'guest_invoice_requested' => $guest_invoice_requested,
            'invoice_request_time' => $invoice_request_time,
            'order_object' => $order
        );
    }
}

if (!function_exists('oj_express_get_optimized_badge_data')) {
    function oj_express_get_optimized_badge_data($order, $kitchen_service, $order_method_service) {
        $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
        $order_method = $order_method_service->get_order_method($order);
        $kitchen_type = $kitchen_status['kitchen_type'];
        $order_status = $order->get_status();
        
        // Status badge
        if ($order_status === 'pending') {
            $status_data = array('class' => 'ready', 'icon' => '✅', 'text' => __('Ready', 'orders-jet'));
        } elseif ($order_status === 'processing') {
            if ($kitchen_type === 'mixed') {
                if ($kitchen_status['food_ready'] && !$kitchen_status['beverage_ready']) {
                    $status_data = array('class' => 'partial', 'icon' => '🍕✅ 🥤⏳', 'text' => __('Waiting for Bev.', 'orders-jet'));
                } elseif (!$kitchen_status['food_ready'] && $kitchen_status['beverage_ready']) {
                    $status_data = array('class' => 'partial', 'icon' => '🍕⏳ 🥤✅', 'text' => __('Waiting for Food', 'orders-jet'));
                } else {
                    $status_data = array('class' => 'partial', 'icon' => '🍕⏳ 🥤⏳', 'text' => __('Both Kitchens', 'orders-jet'));
                }
            } elseif ($kitchen_type === 'food') {
                $status_data = array('class' => 'partial', 'icon' => '🍕⏳', 'text' => __('Waiting for Food', 'orders-jet'));
            } elseif ($kitchen_type === 'beverages') {
                $status_data = array('class' => 'partial', 'icon' => '🥤⏳', 'text' => __('Waiting for Bev.', 'orders-jet'));
            } else {
                $status_data = array('class' => 'kitchen', 'icon' => '👨‍🍳', 'text' => __('Kitchen', 'orders-jet'));
            }
        } elseif ($order_status === 'completed') {
            $status_data = array('class' => 'completed', 'icon' => '✅', 'text' => __('Completed', 'orders-jet'));
        } else {
            $status_data = array('class' => 'kitchen', 'icon' => '👨‍🍳', 'text' => __('Kitchen', 'orders-jet'));
        }
        
        // Type badge
        $type_icons = array('dinein' => '🍽️', 'takeaway' => '📦', 'delivery' => '🚚');
        $type_texts = array('dinein' => __('Dine-in', 'orders-jet'), 'takeaway' => __('Takeaway', 'orders-jet'), 'delivery' => __('Delivery', 'orders-jet'));
        $type_data = array(
            'class' => $order_method,
            'icon' => $type_icons[$order_method] ?? '📦',
            'text' => $type_texts[$order_method] ?? __('Takeaway', 'orders-jet')
        );
        
        // Kitchen badge
        $kitchen_icons = array('food' => '🍕', 'beverages' => '🥤', 'mixed' => '🍽️');
        $kitchen_texts = array('food' => __('Food', 'orders-jet'), 'beverages' => __('Beverages', 'orders-jet'), 'mixed' => __('Mixed', 'orders-jet'));
        $kitchen_data = array(
            'class' => $kitchen_type,
            'icon' => $kitchen_icons[$kitchen_type] ?? '🍕',
            'text' => $kitchen_texts[$kitchen_type] ?? __('Food', 'orders-jet')
        );
        
        return array(
            'status' => $status_data,
            'type' => $type_data,
            'kitchen' => $kitchen_data
        );
    }
}

if (!function_exists('oj_express_get_action_buttons')) {
    function oj_express_get_action_buttons($order_data, $kitchen_status) {
        $order_id = $order_data['id'];
        $status = $order_data['status'];
        $kitchen_type = $order_data['kitchen_type'];
        $table_number = $order_data['table'];
        $guest_invoice_requested = $order_data['guest_invoice_requested'] ?? false;
        
        $buttons = '';
        
        // Check if order has guest invoice request
        if ($guest_invoice_requested && !empty($table_number) && in_array($status, ['pending', 'completed', 'pending-payment'])) {
            $buttons .= '<div class="oj-guest-invoice-notice">🔔 Guest requested invoice</div>';
            
            if ($status === 'pending') {
                $buttons .= sprintf(
                    '<button class="oj-action-btn primary oj-close-table guest-request" data-order-id="%s" data-table-number="%s">🍽️ %s</button>',
                    esc_attr($order_id),
                    esc_attr($table_number),
                    __('Close Table', 'orders-jet')
                );
            }
            
            return $buttons;
        }
        
        if ($status === 'processing') {
            if ($kitchen_type === 'mixed') {
                if (!$kitchen_status['food_ready']) {
                    $buttons .= sprintf(
                        '<button class="oj-action-btn primary oj-mark-ready-food" data-order-id="%s" data-kitchen="food">🍕 %s</button>',
                        esc_attr($order_id),
                        __('Food Ready', 'orders-jet')
                    );
                }
                if (!$kitchen_status['beverage_ready']) {
                    $buttons .= sprintf(
                        '<button class="oj-action-btn primary oj-mark-ready-beverage" data-order-id="%s" data-kitchen="beverages">🥤 %s</button>',
                        esc_attr($order_id),
                        __('Bev. Ready', 'orders-jet')
                    );
                }
            } else {
                $icon = $kitchen_type === 'food' ? '🍕' : ($kitchen_type === 'beverages' ? '🥤' : '🔥');
                $text = $kitchen_type === 'food' ? __('Food Ready', 'orders-jet') : 
                       ($kitchen_type === 'beverages' ? __('Bev. Ready', 'orders-jet') : __('Mark Ready', 'orders-jet'));
                
                $buttons .= sprintf(
                    '<button class="oj-action-btn primary oj-mark-ready" data-order-id="%s" data-kitchen="%s">%s %s</button>',
                    esc_attr($order_id),
                    esc_attr($kitchen_type),
                    $icon,
                    $text
                );
            }
        } elseif ($status === 'pending') {
            if (!empty($table_number)) {
                $buttons .= sprintf(
                    '<button class="oj-action-btn primary oj-close-table" data-order-id="%s" data-table-number="%s">🍽️ %s</button>',
                    esc_attr($order_id),
                    esc_attr($table_number),
                    __('Close Table', 'orders-jet')
                );
            } else {
                $buttons .= sprintf(
                    '<button class="oj-action-btn primary oj-complete-order" data-order-id="%s">✅ %s</button>',
                    esc_attr($order_id),
                    __('Complete', 'orders-jet')
                );
            }
        }
        
        return $buttons;
    }
}

if (!function_exists('oj_express_update_filter_counts')) {
    function oj_express_update_filter_counts(&$counts, $order_data) {
        $status = $order_data['status'];
        
        // Count all active orders
        $counts['active']++;
        
        // Count by status
        if ($status === 'processing') {
            $counts['processing']++;
        } elseif ($status === 'pending') {
            $counts['pending']++;
        }
    }
}

// ============================================================================
// QUERY ALL ACTIVE ORDERS (Same as Orders Express - filter after)
// ============================================================================

// Get ALL active orders first (same query as Orders Express)
$all_active_orders = wc_get_orders(array(
    'status' => array('processing', 'pending'),
    'limit' => 100,
    'orderby' => 'modified',
    'order' => 'DESC',
    'return' => 'objects'
));

// Filter for waiter's assigned tables AND unassigned tables AFTER getting all orders
$active_orders = array();

// Helper function to check if a table is assigned to any waiter
if (!function_exists('oj_is_table_assigned_to_any_waiter')) {
    function oj_is_table_assigned_to_any_waiter($table_number) {
        // Get all users with waiter function
        $waiters = get_users(array(
            'meta_key' => '_oj_function',
            'meta_value' => 'waiter',
            'fields' => 'ID'
        ));
        
        // Check if any waiter has this table assigned
        foreach ($waiters as $waiter_id) {
            $assigned_tables = get_user_meta($waiter_id, '_oj_assigned_tables', true);
            if (is_array($assigned_tables) && in_array($table_number, $assigned_tables)) {
                return true; // Table is assigned to this waiter
            }
        }
        
        return false; // Table is not assigned to any waiter
    }
}

foreach ($all_active_orders as $order) {
    $order_table = $order->get_meta('_oj_table_number');
    
    if (empty($order_table)) {
        // Skip orders without table numbers (takeaway/delivery)
        continue;
    }
    
    // SIMPLIFIED LOGIC: Only show orders from assigned tables
    // Unassigned table orders are handled via notifications and claiming mechanism
    if (!empty($assigned_tables) && in_array($order_table, $assigned_tables)) {
        // Order from assigned table - show it
        $active_orders[] = $order;
    }
    // Don't show orders from unassigned tables or other waiters' tables
}

// ============================================================================
// DATA PREPARATION
// ============================================================================

$orders_data = array();
$filter_counts = array(
    'active' => 0,
    'processing' => 0,
    'pending' => 0
);

foreach ($active_orders as $order) {
    $order_data = oj_express_prepare_order_data($order, $kitchen_service, $order_method_service);
    
    // Update filter counts
    $filter_counts['active']++;
    
    if ($order_data['status'] === 'processing') {
        $filter_counts['processing']++;
    } elseif ($order_data['status'] === 'pending') {
        $filter_counts['pending']++;
    }
    
    $orders_data[] = $order_data;
}

?>

<div class="wrap oj-manager-orders oj-waiter-view">
    <!-- Page Header -->
    <div class="oj-page-header">
        <h1 class="oj-page-title">
            🍽️ <?php _e('Waiter View', 'orders-jet'); ?>
            <span class="oj-subtitle"><?php _e('Your Table Orders', 'orders-jet'); ?></span>
        </h1>
        
        <?php 
        // Render notification center component
        if (function_exists('oj_render_notification_center')) {
            oj_render_notification_center();
        }
        ?>
    </div>

    <!-- Waiter Controls -->
    <div class="oj-manager-controls" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 15px;">
        <!-- Search Box for Table Numbers -->
        <div class="oj-search-box" style="flex: 1; min-width: 250px;">
            <input type="text" 
                   id="oj-table-search" 
                   class="oj-table-search-input" 
                   placeholder="<?php _e('Search by table number...', 'orders-jet'); ?>" 
                   style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
        </div>
        
        <button class="oj-kitchen-refresh" onclick="refreshWaiterDashboard();" style="margin-left: auto;">
            <span class="dashicons dashicons-update"></span>
            <?php _e('Refresh Orders', 'orders-jet'); ?>
        </button>
    </div>
    
    <!-- Filter Tabs -->
    <div class="oj-filters">
        <button class="oj-filter-btn active" data-filter="active">
            🔥 <?php _e('All My Orders', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $filter_counts['active']; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="processing">
            👨‍🍳 <?php _e('In Kitchen', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $filter_counts['processing']; ?></span>
        </button>
        <button class="oj-filter-btn" data-filter="pending">
            ✅ <?php _e('Ready to Serve', 'orders-jet'); ?>
            <span class="oj-filter-count"><?php echo $filter_counts['pending']; ?></span>
        </button>
    </div>

    <?php if (empty($assigned_tables)): ?>
        <!-- No Assigned Tables Message -->
        <div class="oj-empty-state">
            <div class="oj-empty-icon">🍽️</div>
            <h2><?php _e('No Tables Assigned', 'orders-jet'); ?></h2>
            <p><?php _e('You don\'t have any assigned tables yet. Please contact your manager to get table assignments.', 'orders-jet'); ?></p>
            <p style="margin-top: 10px; color: #666; font-size: 14px;">
                <?php _e('Assigned tables:', 'orders-jet'); ?>
                <strong><?php _e('None', 'orders-jet'); ?></strong>
            </p>
        </div>
    <?php else: ?>
        <!-- Show assigned tables info -->
        <div style="background: #f0f9ff; border: 1px solid #bfdbfe; border-radius: 4px; padding: 12px; margin-bottom: 15px;">
            <strong><?php _e('Your assigned tables:', 'orders-jet'); ?></strong>
            <?php echo implode(', ', $assigned_tables); ?>
        </div>
        
        
        
        <!-- Orders Grid -->
        <div class="oj-orders-grid">
            <?php if (empty($orders_data)) : ?>
                <?php include __DIR__ . '/partials/empty-state.php'; ?>
            <?php else : ?>
                <?php foreach ($orders_data as $order_data) : ?>
                    <?php include __DIR__ . '/partials/order-card.php'; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Available Tables Floating Button -->
    <div class="oj-available-tables-floating-btn" id="oj-available-tables-btn">
        <button class="oj-floating-btn" title="<?php _e('View Available Tables to Claim', 'orders-jet'); ?>">
            <span class="oj-btn-icon">🏷️</span>
            <span class="oj-btn-text"><?php _e('Tables', 'orders-jet'); ?></span>
            <span class="oj-btn-count" id="oj-available-tables-count">0</span>
        </button>
    </div>
    
    <!-- Available Tables Slide Panel -->
    <div class="oj-available-tables-overlay" id="oj-available-tables-overlay">
        <div class="oj-available-tables-panel" id="oj-available-tables-panel">
            <!-- Panel Header -->
            <div class="oj-panel-header">
                <div class="oj-panel-title">
                    <span class="oj-panel-icon">🏷️</span>
                    <h3><?php _e('Available Tables to Claim', 'orders-jet'); ?></h3>
                </div>
                <button class="oj-panel-close" id="oj-available-tables-close" title="<?php _e('Close Panel', 'orders-jet'); ?>">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
            </div>
            
            <!-- Panel Content -->
            <div class="oj-panel-content">
                <div class="oj-available-tables-list" id="oj-available-tables-list">
                    <!-- Tables will be loaded here via AJAX -->
                    <div class="oj-loading-state">
                        <span class="oj-loading-spinner"></span>
                        <p><?php _e('Loading available tables...', 'orders-jet'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    'use strict';
    
    // ========================================================================
    // TABLE NUMBER SEARCH
    // ========================================================================
    
    $('#oj-table-search').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase().trim();
        const $cards = $('.oj-order-card');
        
        if (searchTerm === '') {
            // No search term - show all cards based on active filter
            const activeFilter = $('.oj-filter-btn.active').data('filter');
            $cards.each(function() {
                const $card = $(this);
                const status = $card.attr('data-status');
                let showCard = false;
                
                switch (activeFilter) {
                    case 'active':
                        showCard = true;
                        break;
                    case 'processing':
                        showCard = (status === 'processing');
                        break;
                    case 'pending':
                        showCard = (status === 'pending');
                        break;
                }
                
                if (showCard) {
                    $card.fadeIn(200);
                } else {
                    $card.fadeOut(200);
                }
            });
        } else {
            // Search by table number - use data attribute OR .oj-table-ref element
            $cards.each(function() {
                const $card = $(this);
                // Try data attribute first, fallback to .oj-table-ref element text
                const tableNumber = ($card.attr('data-table-number') || $card.find('.oj-table-ref').text()).toLowerCase();
                
                if (tableNumber.includes(searchTerm)) {
                    $card.fadeIn(200);
                } else {
                    $card.fadeOut(200);
                }
            });
        }
    });
    
    // Clear search when filter changes
    $('.oj-filter-btn').on('click', function() {
        $('#oj-table-search').val('');
    });
    
    // ========================================================================
    // AVAILABLE TABLES FLOATING BUTTON & SLIDE PANEL
    // ========================================================================
    
    // Load initial count on page load
    loadAvailableTablesCount();
    
    // Click handler for floating button - Open panel
    $('#oj-available-tables-btn .oj-floating-btn').on('click', function() {
        openAvailableTablesPanel();
    });
    
    // Click handler for close button - Close panel
    $('#oj-available-tables-close').on('click', function() {
        closeAvailableTablesPanel();
    });
    
    // Click handler for overlay - Close panel when clicking outside
    $('#oj-available-tables-overlay').on('click', function(e) {
        if (e.target === this) {
            closeAvailableTablesPanel();
        }
    });
    
    // ESC key handler - Close panel
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#oj-available-tables-overlay').hasClass('active')) {
            closeAvailableTablesPanel();
        }
    });
    
    // Open panel function
    function openAvailableTablesPanel() {
        $('#oj-available-tables-overlay').addClass('active');
        $('body').addClass('oj-panel-open'); // Prevent body scroll
        
        // Load available tables via AJAX
        loadAvailableTables();
    }
    
    // Close panel function
    function closeAvailableTablesPanel() {
        $('#oj-available-tables-overlay').removeClass('active');
        $('body').removeClass('oj-panel-open'); // Restore body scroll
    }
    
    // Load available tables count only (for initial button state)
    function loadAvailableTablesCount() {
        
        $.post(ajaxurl, {
            action: 'oj_get_available_tables',
            nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                const count = response.data.count;
                $('#oj-available-tables-count').text(count);
            }
        }).fail(function() {
            // Failed to load count
        });
    }
    
    // Load available tables via AJAX
    function loadAvailableTables() {
        const $tablesList = $('#oj-available-tables-list');
        
        // Show loading state
        $tablesList.html(`
            <div class="oj-loading-state">
                <span class="oj-loading-spinner"></span>
                <p><?php _e('Loading available tables...', 'orders-jet'); ?></p>
            </div>
        `);
        
        $.post(ajaxurl, {
            action: 'oj_get_available_tables',
            nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                const tables = response.data.tables;
                const count = response.data.count;
                
                // Update button count
                $('#oj-available-tables-count').text(count);
                
                if (tables.length === 0) {
                    // No available tables
                    $tablesList.html(`
                        <div class="oj-empty-state">
                            <span class="oj-empty-icon">🏷️</span>
                            <h4><?php _e('No Available Tables', 'orders-jet'); ?></h4>
                            <p><?php _e('All tables are currently assigned to waiters.', 'orders-jet'); ?></p>
                        </div>
                    `);
                } else {
                    // Render tables
                    let tablesHtml = '';
                    tables.forEach(function(table) {
                        tablesHtml += renderTableCard(table);
                    });
                    $tablesList.html(tablesHtml);
                }
            } else {
                console.error('Error loading tables:', response.data.message);
                $tablesList.html(`
                    <div class="oj-error-state">
                        <span class="oj-error-icon">❌</span>
                        <h4><?php _e('Error Loading Tables', 'orders-jet'); ?></h4>
                        <p>${response.data.message || '<?php _e('Please try again.', 'orders-jet'); ?>'}</p>
                        <button class="oj-retry-btn" onclick="loadAvailableTables()"><?php _e('Retry', 'orders-jet'); ?></button>
                    </div>
                `);
            }
        }).fail(function() {
            console.error('AJAX request failed');
            $tablesList.html(`
                <div class="oj-error-state">
                    <span class="oj-error-icon">❌</span>
                    <h4><?php _e('Connection Error', 'orders-jet'); ?></h4>
                    <p><?php _e('Failed to connect to server. Please check your connection and try again.', 'orders-jet'); ?></p>
                    <button class="oj-retry-btn" onclick="loadAvailableTables()"><?php _e('Retry', 'orders-jet'); ?></button>
                </div>
            `);
        });
    }
    
    // Render individual table card
    function renderTableCard(table) {
        const statusBadge = getTableStatusBadge(table.guest_status);
        const priorityClass = table.has_pending_orders ? 'priority-high' : (table.guest_waiting ? 'priority-medium' : 'priority-normal');
        const priorityIcon = table.has_pending_orders ? '📋' : (table.guest_waiting ? '🔔' : '');
        
        return `
            <div class="oj-table-claim-card ${priorityClass}" data-table-number="${table.number}">
                <div class="oj-table-header">
                    <div class="oj-table-info">
                        <span class="oj-table-title">🍽️ ${table.title}</span>
                        ${priorityIcon ? `<span class="oj-priority-icon">${priorityIcon}</span>` : ''}
                    </div>
                    <div class="oj-table-status">
                        <span class="oj-status-badge" style="background: ${statusBadge.bg}; color: ${statusBadge.color};">
                            ${statusBadge.icon} ${statusBadge.label}
                        </span>
                    </div>
                </div>
                <div class="oj-table-details">
                    <div class="oj-table-meta">
                        <span class="oj-table-number">T${table.number}</span>
                        ${table.capacity ? `<span class="oj-table-capacity">| ${table.capacity} Seats</span>` : ''}
                        ${table.location ? `<span class="oj-table-location">📍 ${table.location}</span>` : ''}
                    </div>
                    ${table.has_pending_orders ? `<div class="oj-table-alert">📋 Has pending orders - Needs immediate attention!</div>` : ''}
                    ${table.guest_waiting ? `<div class="oj-table-orders">🔔 Guest is requesting assistance</div>` : ''}
                </div>
                <div class="oj-table-actions">
                    <button class="oj-claim-btn" data-table-number="${table.number}">
                        <?php _e('Claim Table', 'orders-jet'); ?> ${table.number}
                    </button>
                </div>
            </div>
        `;
    }
    
    // Get table status badge styling
    function getTableStatusBadge(status) {
        const badges = {
            'available': { bg: '#e8f5e8', color: '#2e7d32', icon: '✅', label: '<?php _e('Available', 'orders-jet'); ?>' },
            'occupied': { bg: '#fff3e0', color: '#f57c00', icon: '👥', label: '<?php _e('Occupied', 'orders-jet'); ?>' },
            'reserved': { bg: '#e3f2fd', color: '#1976d2', icon: '📅', label: '<?php _e('Reserved', 'orders-jet'); ?>' },
            'maintenance': { bg: '#ffebee', color: '#d32f2f', icon: '🔧', label: '<?php _e('Maintenance', 'orders-jet'); ?>' }
        };
        return badges[status] || badges['available'];
    }
    
    // Handle table claiming
    $(document).on('click', '.oj-claim-btn', function() {
        const $btn = $(this);
        const tableNumber = $btn.data('table-number');
        const $card = $btn.closest('.oj-table-claim-card');
        
        // Show loading state
        $btn.prop('disabled', true).text('<?php _e('Claiming...', 'orders-jet'); ?>');
        
        $.post(ajaxurl, {
            action: 'oj_claim_table',
            table_number: tableNumber,
            nonce: '<?php echo wp_create_nonce('oj_dashboard_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                
                // Show success message
                $card.addClass('claimed').html(`
                    <div class="oj-claim-success">
                        <span class="oj-success-icon">✅</span>
                        <h4><?php _e('Table Claimed!', 'orders-jet'); ?></h4>
                        <p>${response.data.message}</p>
                    </div>
                `);
                
                // Remove card after 2 seconds and refresh dashboard
                setTimeout(function() {
                    $card.fadeOut(300, function() {
                        $(this).remove();
                        // Reload tables to update count
                        loadAvailableTables();
                        // Use the same refresh as the orders refresh button
                        refreshWaiterDashboard();
                    });
                }, 2000);
                
            } else {
                console.error('Error claiming table:', response.data.message);
                $btn.prop('disabled', false).text('<?php _e('Claim Table', 'orders-jet'); ?> ' + tableNumber);
                alert(response.data.message || '<?php _e('Error claiming table. Please try again.', 'orders-jet'); ?>');
            }
        }).fail(function() {
            console.error('AJAX request failed');
            $btn.prop('disabled', false).text('<?php _e('Claim Table', 'orders-jet'); ?> ' + tableNumber);
            alert('<?php _e('Connection error. Please try again.', 'orders-jet'); ?>');
        });
    });
    
    // Refresh waiter dashboard after claiming a table
    window.refreshWaiterDashboard = function refreshWaiterDashboard() {
        
        // Show loading indicator
        const $ordersGrid = $('.oj-orders-grid');
        const $assignedTablesBar = $('.oj-waiter-view > div').filter(function() {
            return $(this).text().includes('Your assigned tables:');
        });
        
        // Add loading overlay to orders grid
        if (!$ordersGrid.find('.oj-refresh-overlay').length) {
            $ordersGrid.append(`
                <div class="oj-refresh-overlay" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); display: flex; align-items: center; justify-content: center; z-index: 1000;">
                    <div class="oj-refresh-spinner" style="text-align: center;">
                        <span class="oj-loading-spinner" style="display: inline-block; width: 20px; height: 20px; border: 2px solid #f3f3f3; border-top: 2px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite;"></span>
                        <p style="margin-top: 10px; color: #666;"><?php _e('Refreshing dashboard...', 'orders-jet'); ?></p>
                    </div>
                </div>
            `);
        }
        
        // Add loading indicator to assigned tables bar
        if ($assignedTablesBar.length && !$assignedTablesBar.find('.oj-refresh-indicator').length) {
            $assignedTablesBar.append(`
                <span class="oj-refresh-indicator" style="margin-left: 10px; color: #666;">
                    <span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span>
                    <?php _e('Updating...', 'orders-jet'); ?>
                </span>
            `);
        }
        
        // Simple page reload after showing loading indicators
        setTimeout(function() {
            window.location.reload();
        }, 1000); // Small delay to show the loading state
    }
    
});
</script>

<style>
/* Refresh loading animations */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.oj-orders-grid {
    position: relative;
}

/* Waiter-specific highlight for ready orders */
.oj-waiter-view .oj-order-card[data-status="pending"] {
    border-left: 4px solid #10b981;
    background: #f0fdf4;
}

.oj-waiter-view .oj-order-card[data-status="pending"] .oj-card-header {
    background: #10b981;
}

/* Search box styling */
.oj-table-search-input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Available Tables Floating Button */
.oj-available-tables-floating-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
}

.oj-floating-btn {
    background: #1d4ed8;
    color: white;
    border: none;
    border-radius: 50px;
    padding: 12px 20px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 15px rgba(29, 78, 216, 0.3);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 120px;
    justify-content: center;
}

.oj-floating-btn:hover {
    background: #1d4ed8;
    box-shadow: 0 4px 15px rgba(29, 78, 216, 0.3);
    transform: none;
}

.oj-floating-btn:active {
    background: #1d4ed8;
    transform: none;
    box-shadow: 0 4px 15px rgba(29, 78, 216, 0.3);
}

.oj-btn-icon {
    font-size: 16px;
}

.oj-btn-text {
    font-size: 13px;
}

.oj-btn-count {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 12px;
    font-weight: 700;
    min-width: 20px;
    text-align: center;
}

.oj-btn-count:empty {
    display: none;
}

/* Available Tables Slide Panel */
.oj-available-tables-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000000; /* Match Orders Master z-index */
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
}

.oj-available-tables-overlay.active {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.oj-available-tables-panel {
    position: absolute;
    top: 0;
    right: 0;
    width: 400px;
    height: 100vh; /* Use viewport height instead of 100% */
    height: 100dvh; /* Dynamic viewport height for mobile browsers */
    background: white;
    box-shadow: -4px 0 20px rgba(0, 0, 0, 0.15);
    transform: translateX(100%);
    transition: transform 0.3s ease;
    display: flex;
    flex-direction: column;
    overflow: hidden; /* Prevent double scrollbar - let body scroll instead */
}

.oj-available-tables-overlay.active .oj-available-tables-panel {
    transform: translateX(0);
}

.oj-available-tables-overlay:not(.active) .oj-available-tables-panel {
    transform: translateX(100%);
    visibility: hidden;
}

/* Panel Header */
.oj-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid #e1e5e9;
    background: #f8f9fa;
}

.oj-panel-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.oj-panel-icon {
    font-size: 20px;
}

.oj-panel-title h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.oj-panel-close {
    background: none;
    border: none;
    padding: 8px;
    cursor: pointer;
    border-radius: 4px;
    color: #646970;
    transition: all 0.2s ease;
}

.oj-panel-close:hover {
    background: #e1e5e9;
    color: #1d2327;
}

.oj-panel-close .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

/* Panel Content */
.oj-panel-content {
    flex: 1;
    overflow-y: auto;
    padding: 20px 24px;
}

.oj-available-tables-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Loading State */
.oj-loading-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    text-align: center;
    color: #646970;
}

.oj-loading-spinner {
    width: 24px;
    height: 24px;
    border: 2px solid #e1e5e9;
    border-top: 2px solid #3b82f6;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 12px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.oj-loading-state p {
    margin: 0;
    font-size: 14px;
}

/* Prevent body scroll when panel is open */
body.oj-panel-open {
    overflow: hidden;
}

/* Responsive */
@media (max-width: 768px) {
    .oj-available-tables-panel {
        width: 100%;
        max-width: 100vw;
        height: 100vh;
        height: 100dvh; /* Dynamic viewport height for mobile */
    }
    
    /* Panel content adjustments for mobile */
    .oj-panel-content {
        padding: 16px;
        padding-bottom: calc(20px + env(safe-area-inset-bottom)); /* Account for iPhone notch */
        -webkit-overflow-scrolling: touch; /* Smooth scrolling on iOS */
    }
    
    /* Panel header adjustments for mobile */
    .oj-panel-header {
        padding: 16px 20px;
        padding-top: calc(16px + env(safe-area-inset-top)); /* Account for iPhone notch */
    }
}

/* Table Claim Cards */
.oj-table-claim-card {
    background: white;
    border: 1px solid #e1e5e9;
    border-radius: 8px;
    padding: 16px;
    transition: all 0.2s ease;
    cursor: default;
}

.oj-table-claim-card:hover {
    border-color: #3b82f6;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.1);
}

.oj-table-claim-card.priority-high {
    border-left: 4px solid #dc2626;
    background: #fef2f2;
}

.oj-table-claim-card.priority-medium {
    border-left: 4px solid #ff6b35;
    background: #fff8f5;
}

.oj-table-claim-card.priority-normal {
    border-left: 4px solid #10b981;
}

/* Table Card Header */
.oj-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.oj-table-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.oj-table-title {
    font-size: 16px;
    font-weight: 600;
    color: #1d2327;
}

.oj-priority-icon {
    font-size: 14px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.oj-status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

/* Table Details */
.oj-table-details {
    margin-bottom: 16px;
}

.oj-table-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #646970;
    margin-bottom: 8px;
}

.oj-table-number {
    font-weight: 600;
    color: #1d2327;
}

.oj-table-alert {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 13px;
    color: #856404;
    margin-bottom: 4px;
    animation: pulse 2s infinite;
}

.oj-table-orders {
    background: #e3f2fd;
    border: 1px solid #bbdefb;
    border-radius: 4px;
    padding: 8px 12px;
    font-size: 13px;
    color: #1565c0;
}

/* Table Actions */
.oj-table-actions {
    display: flex;
    gap: 8px;
}

.oj-claim-btn {
    flex: 1;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    border: none;
    border-radius: 6px;
    padding: 10px 16px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.oj-claim-btn:hover {
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.oj-claim-btn:active {
    transform: translateY(0);
}

.oj-claim-btn:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* Success State */
.oj-claim-success {
    text-align: center;
    padding: 20px;
}

.oj-success-icon {
    font-size: 32px;
    display: block;
    margin-bottom: 12px;
}

.oj-claim-success h4 {
    margin: 0 0 8px 0;
    color: #10b981;
    font-size: 16px;
}

.oj-claim-success p {
    margin: 0;
    color: #646970;
    font-size: 14px;
}

/* Empty State */
.oj-empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #646970;
}

.oj-empty-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 16px;
    opacity: 0.5;
}

.oj-empty-state h4 {
    margin: 0 0 8px 0;
    font-size: 18px;
    color: #1d2327;
}

.oj-empty-state p {
    margin: 0;
    font-size: 14px;
}

/* Error State */
.oj-error-state {
    text-align: center;
    padding: 40px 20px;
    color: #dc3545;
}

.oj-error-icon {
    font-size: 32px;
    display: block;
    margin-bottom: 16px;
}

.oj-error-state h4 {
    margin: 0 0 8px 0;
    font-size: 16px;
    color: #dc3545;
}

.oj-error-state p {
    margin: 0 0 16px 0;
    font-size: 14px;
    color: #646970;
}

.oj-retry-btn {
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 4px;
    padding: 8px 16px;
    font-size: 14px;
    cursor: pointer;
    transition: background 0.2s ease;
}

.oj-retry-btn:hover {
    background: #2563eb;
}

/* Dashboard Refresh Overlay */
.oj-orders-grid {
    position: relative; /* Needed for absolute positioning of overlay */
}

.oj-refresh-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 100;
    border-radius: 8px;
}

.oj-refresh-spinner {
    text-align: center;
    color: #646970;
}

.oj-refresh-spinner .oj-loading-spinner {
    margin-bottom: 12px;
}

.oj-refresh-spinner p {
    margin: 0;
    font-size: 14px;
    font-weight: 500;
}
</style>

