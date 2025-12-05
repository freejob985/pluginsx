<?php
/**
 * Orders Master V2 - WooCommerce Style Management Dashboard
 * 
 * Server-side filters, search, sort, pagination
 * AJAX-only for order cycle actions (mark ready, complete, close table)
 * 
 * Features:
 * - Server-side filtering (All | Active | Kitchen | Ready | Completed)
 * - Server-side search (Order #, Table, Customer)
 * - Server-side sort (Date Created/Modified, ASC/DESC)
 * - Server-side pagination (20 per page)
 * - Date range filtering (WooCommerce-style)
 * - AJAX order cycle (cards update without page reload)
 * - Completed orders stay visible with "Print Invoice" button
 * 
 * @package Orders_Jet
 * @version 2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load dependencies
require_once ORDERS_JET_PLUGIN_DIR . 'includes/helpers/orders-master-helpers.php';
require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-master-query-builder.php';

// Enqueue existing CSS (reuse Orders Express design system)
wp_enqueue_style(
    'oj-manager-orders-cards',
    ORDERS_JET_PLUGIN_URL . 'assets/css/manager-orders-cards.css',
    array(),
    ORDERS_JET_VERSION
);

wp_enqueue_style(
    'oj-dashboard-express',
    ORDERS_JET_PLUGIN_URL . 'assets/css/dashboard-express.css',
    array('oj-manager-orders-cards'),
    ORDERS_JET_VERSION
);

// Enqueue Orders Master main JavaScript (AJAX filtering, search, pagination, grid refresh)
wp_enqueue_script(
    'oj-orders-master',
    ORDERS_JET_PLUGIN_URL . 'assets/js/orders-master.js',
    array('jquery'),
    ORDERS_JET_VERSION,
    true
);

// Localize Orders Master script
wp_localize_script(
    'oj-orders-master',
    'OrdersJetMaster',
    array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('oj_dashboard_nonce')
    )
);

// Enqueue bulk actions JavaScript (Step 2)
wp_enqueue_script(
    'oj-orders-master-bulk-actions',
    ORDERS_JET_PLUGIN_URL . 'assets/js/orders-master-bulk-actions.js',
    array('jquery', 'oj-orders-master'),
    ORDERS_JET_VERSION,
    true
);

// Localize script with AJAX data (Step 3)
wp_localize_script(
    'oj-orders-master-bulk-actions',
    'oj_ajax_data',
    array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('oj_ajax_nonce')
    )
);

// Enqueue Order Editor module (Phase 2: Add Notes)
wp_enqueue_style(
    'oj-order-editor',
    ORDERS_JET_PLUGIN_URL . 'assets/css/order-editor.css',
    array('oj-manager-orders-cards'),
    ORDERS_JET_VERSION
);

wp_enqueue_script(
    'oj-order-editor',
    ORDERS_JET_PLUGIN_URL . 'assets/js/order-editor.js',
    array('jquery', 'oj-orders-master-bulk-actions'),
    ORDERS_JET_VERSION,
    true
);

wp_localize_script(
    'oj-order-editor',
    'oj_editor_data',
    array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('oj_editor_nonce')
    )
);

// Initialize services for card display
$kitchen_service = new Orders_Jet_Kitchen_Service();
$order_method_service = new Orders_Jet_Order_Method_Service();

// ============================================
// STEP 1: Initialize Query Builder
// ============================================

// Create query builder with URL parameters
$query_builder = new Orders_Master_Query_Builder($_GET);

// Get orders and counts
$orders = $query_builder->get_orders();
$filter_counts = $query_builder->get_filter_counts();
$pagination = $query_builder->get_pagination_data();

// Extract parameters for template use
$current_filter = $query_builder->get_filter();
$current_page = $query_builder->get_current_page();
$search = $query_builder->get_search();
$orderby = $query_builder->get_orderby();
$order = $query_builder->get_order();
$order_direction = $order; // Preserve for display
$date_preset = $query_builder->get_date_preset();
$date_from = $query_builder->get_date_from();
$date_to = $query_builder->get_date_to();
$date_range_label = $query_builder->get_date_range_label();

// Advanced filter parameters
$order_type = $query_builder->get_order_type();
$kitchen_type = $query_builder->get_kitchen_type();
$kitchen_status = $query_builder->get_kitchen_status();
$assigned_waiter = $query_builder->get_assigned_waiter();
$unassigned_only = $query_builder->get_unassigned_only();
$payment_method = $query_builder->get_payment_method();

// Amount filter variables
$amount_type = $query_builder->get_amount_type();
$amount_value = $query_builder->get_amount_value();
$amount_min = $query_builder->get_amount_min();
$amount_max = $query_builder->get_amount_max();

$per_page = $query_builder->get_per_page();
$total_orders = $pagination['total_orders'];
$total_pages = $pagination['total_pages'];
$orders_count = count($orders);

// Calculate total amount of filtered orders
$filtered_total = $query_builder->get_filtered_orders_total();

// Current params for URL building
$current_params = $query_builder->get_current_params();

// Check if we have active filters for reset button
$has_filters = Orders_Jet_Filter_URL_Builder::has_active_filters($current_params);

?>

<div class="wrap oj-dashboard">
    <div class="oj-dashboard-header">
        <h1><?php _e('Orders Master', 'orders-jet'); ?></h1>
        <p class="description">
            <?php _e('Comprehensive order management with advanced filtering, search, and sorting', 'orders-jet'); ?>
        </p>
    </div>
    
    <!-- Debug Panel (Admin Only) -->
    <?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/filters-debug-panel.php'; ?>
    
    <?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/orders-master-toolbar.php'; ?>
    
    <!-- Dynamic Content Area (Orders, Count, Pagination) -->
    <div id="oj-dynamic-content">
        <?php 
        // Prepare variables for the content area partial
        $current_page = max(1, intval($_GET['paged'] ?? 1));
        
        // Include the reusable content area partial
        include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/orders-content-area.php'; 
        ?>
    </div>
</div>

<!-- Advanced Filters Slide Panel -->
<?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/filters-slide-panel.php'; ?>

