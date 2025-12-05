<?php
declare(strict_types=1);
/**
 * Orders Jet - Admin Dashboard Class
 * Main dashboard controller with role-based views
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Admin_Dashboard {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_assets'));
        
        // AJAX handlers for dashboard
        add_action('wp_ajax_oj_get_dashboard_data', array($this, 'get_dashboard_data'));
        add_action('wp_ajax_oj_get_table_updates', array($this, 'get_table_updates'));
        add_action('wp_ajax_oj_get_order_updates', array($this, 'get_order_updates'));
        add_action('wp_ajax_oj_get_notifications', array($this, 'get_notifications'));
        
        // AJAX handlers for Orders Master (Task 1.2.3.4)
        add_action('wp_ajax_oj_get_orders_master_filtered', array($this, 'ajax_get_orders_master_filtered'));
        
        // Development tools (admin only)
        add_action('wp_ajax_oj_generate_test_orders', array($this, 'ajax_generate_test_orders'));
        add_action('wp_ajax_oj_clear_all_orders', array($this, 'ajax_clear_all_orders'));
        
        // Batch operations for development tools
        add_action('wp_ajax_oj_get_all_order_ids', array($this, 'ajax_get_all_order_ids'));
        add_action('wp_ajax_oj_clear_orders_batch', array($this, 'ajax_clear_orders_batch'));
        add_action('wp_ajax_oj_generate_orders_batch', array($this, 'ajax_generate_orders_batch'));
        
        // Orders Master V2 - Mark order as paid
        add_action('wp_ajax_oj_mark_order_paid', array($this, 'ajax_mark_order_paid'));
        
        // Table Assignment AJAX handlers
        add_action('wp_ajax_oj_assign_table', array($this, 'ajax_assign_table'));
        add_action('wp_ajax_oj_unassign_table', array($this, 'ajax_unassign_table'));
        add_action('wp_ajax_oj_bulk_assign_tables', array($this, 'ajax_bulk_assign_tables'));
        add_action('wp_ajax_oj_bulk_unassign_tables', array($this, 'ajax_bulk_unassign_tables'));
        
        // Waiter Table Claiming AJAX handlers
        add_action('wp_ajax_oj_get_available_tables', array($this, 'ajax_get_available_tables'));
        add_action('wp_ajax_oj_claim_table', array($this, 'ajax_claim_table'));
        
        // Orders Overview AJAX handlers
        add_action('wp_ajax_oj_get_overview_data', array($this, 'ajax_get_overview_data'));
        
        // Orders Reports AJAX handlers
        add_action('wp_ajax_oj_reports_get_data', array($this, 'ajax_reports_get_data'));
        add_action('wp_ajax_oj_reports_drill_down', array($this, 'ajax_reports_drill_down'));
        add_action('wp_ajax_oj_reports_export', array($this, 'ajax_reports_export'));
    }
    
    /**
     * Register admin menu pages based on user role
     */
    public function register_admin_pages() {
        // Add Orders (Top Level Menu) - New architecture following Phase 1
        if (current_user_can('access_oj_manager_dashboard') || current_user_can('access_oj_waiter_dashboard') || current_user_can('access_oj_kitchen_dashboard') || current_user_can('manage_options')) {
            add_menu_page(
                __('Orders', 'orders-jet'),
                __('Orders', 'orders-jet'),
                'read', // Minimal capability - all roles can access
                'orders-overview', // Point to overview as default
                array($this, 'render_orders_overview'),
                'dashicons-clipboard',
                3
            );
            
            // Orders submenu pages
            add_submenu_page(
                'orders-overview',
                __('Overview', 'orders-jet'),
                __('Overview', 'orders-jet'),
                'read',
                'orders-overview',
                array($this, 'render_orders_overview')
            );
            
            add_submenu_page(
                'orders-overview',
                __('Orders Express', 'orders-jet'),
                __('⚡ Orders Express', 'orders-jet'),
                'read',
                'orders-express',
                array($this, 'render_orders_express')
            );
            
            // Orders Master (Current Implementation - formerly V2)
            add_submenu_page(
                'orders-overview',
                __('Orders Master', 'orders-jet'),
                __('📋 Orders Master', 'orders-jet'),
                'access_oj_manager_dashboard',
                'orders-master-v2',
                array($this, 'render_orders_master_v2')
            );
            
            // Phase 1.3: Orders Reports
            add_submenu_page(
                'orders-overview',
                __('Orders Reports', 'orders-jet'),
                __('📊 Orders Reports', 'orders-jet'),
                'read',
                'orders-reports',
                array($this, 'render_orders_reports')
            );
            
            // NEW: Business Intelligence (Manager only)
            if (current_user_can('access_oj_manager_dashboard') || current_user_can('manage_options')) {
                add_submenu_page(
                    'orders-overview',
                    __('Business Intelligence', 'orders-jet'),
                    __('📈 Business Intelligence', 'orders-jet'),
                    'access_oj_manager_dashboard',
                    'business-intelligence',
                    array($this, 'render_business_intelligence')
                );
            }
            
            // Dev Tools (Admin only - Always visible)
            if (current_user_can('manage_options')) {
                add_submenu_page(
                    'orders-overview',
                    __('Dev Tools', 'orders-jet'),
                    __('🛠️ Dev Tools', 'orders-jet'),
                    'manage_options',
                    'orders-dev-tools',
                    array($this, 'render_dev_tools')
                );
            }
        }
        
        // Invoice page (hidden from menu) - Keep this functional page
        add_submenu_page(
            null, // No parent menu (hidden)
            __('Order Invoice', 'orders-jet'),
            __('Order Invoice', 'orders-jet'),
            'read', // Minimal capability - anyone who can read
            'orders-jet-invoice',
            array($this, 'render_invoice_page')
        );
        
        // Future Phase Menus (Commented out - will be restored in later phases)
        
        // Phase 4: Kitchen Dashboard (available to kitchen staff and admins)
        // if (current_user_can('access_oj_kitchen_dashboard') || current_user_can('manage_options')) {
        //     add_menu_page(
        //         __('Kitchen Display', 'orders-jet'),
        //         __('Kitchen Display', 'orders-jet'),
        //         'manage_options',
        //         'orders-jet-kitchen',
        //         array($this, 'render_kitchen_dashboard'),
        //         'dashicons-food',
        //         3
        //     );
        // }
        
        // Phase 3: Waiter View (available to waiters and admins)
        if (current_user_can('access_oj_waiter_dashboard') || current_user_can('manage_options')) {
            add_submenu_page(
                'orders-overview',
                __('Waiter View', 'orders-jet'),
                __('🍽️ Waiter View', 'orders-jet'),
                'access_oj_waiter_dashboard',
                'waiter-orders',
                array($this, 'render_waiter_orders')
            );
        }
        
        // Table Assignment (available to managers and admins)
        if (current_user_can('access_oj_manager_dashboard') || current_user_can('manage_options')) {
            add_submenu_page(
                'orders-overview',
                __('Assign Tables', 'orders-jet'),
                __('🏷️ Assign Tables', 'orders-jet'),
                'access_oj_manager_dashboard',
                'table-assignment',
                array($this, 'render_table_assignment')
            );
        }
    }
    
    /**
     * Enqueue dashboard assets
     */
    public function enqueue_dashboard_assets($hook) {
        // Orders pages (Phase 1 - Current Focus)
        $orders_pages = array(
            'toplevel_page_orders-overview',
            'orders_page_orders-express',
            'orders_page_orders-master-v2', // Current Orders Master (formerly V2)
            'orders_page_orders-reports', // Phase 1.3: Orders Reports
            'orders_page_business-intelligence', // NEW: Business Intelligence
            'orders_page_orders-dev-tools',
            'orders_page_waiter-orders', // Waiter View (Phase 3)
            'orders_page_table-assignment' // Table Assignment
        );
        
        // Future Phase Pages (Commented out - will be restored later)
        // $manager_pages = array(...);  // Removed - duplicate functionality
        // $kitchen_pages = array(...);  // Phase 4
        // $waiter_pages = array(...);   // Phase 3
        
        // Only load on our current dashboard pages
        if (!in_array($hook, $orders_pages)) {
            return;
        }
        
        // Enqueue admin CSS and JS for classic WordPress admin pages
        wp_enqueue_style(
            'orders-jet-admin',
            ORDERS_JET_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            ORDERS_JET_VERSION
        );
        
        // Orders Overview - Landing page with summary cards
        if ($hook === 'toplevel_page_orders-overview') {
            // Enqueue Overview CSS
            wp_enqueue_style(
                'oj-orders-overview',
                ORDERS_JET_PLUGIN_URL . 'assets/css/orders-overview.css',
                array('orders-jet-admin'),
                ORDERS_JET_VERSION
            );
            
            // Enqueue Overview JavaScript
            wp_enqueue_script(
                'oj-orders-overview',
                ORDERS_JET_PLUGIN_URL . 'assets/js/orders-overview.js',
                array('jquery'),
                ORDERS_JET_VERSION,
                true
            );
            
            // Localize script with AJAX data
            wp_localize_script('oj-orders-overview', 'ojOverviewData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oj_overview_nonce'),
                'i18n' => array(
                    'next' => __('Next', 'orders-jet'),
                    'finish' => __('Finish', 'orders-jet'),
                    'previous' => __('Previous', 'orders-jet')
                )
            ));
        }
        
        // Reuse Orders Express design system for Orders Master (Task 1.2.2 - Extract Reusable Components)
        if ($hook === 'orders_page_orders-master') {
            wp_enqueue_style(
                'oj-manager-orders-cards',
                ORDERS_JET_PLUGIN_URL . 'assets/css/manager-orders-cards.css',
                array('orders-jet-admin'),
                ORDERS_JET_VERSION
            );
            wp_enqueue_style(
                'oj-dashboard-express',
                ORDERS_JET_PLUGIN_URL . 'assets/css/dashboard-express.css',
                array('oj-manager-orders-cards'),
                ORDERS_JET_VERSION
            );
            
            // Enqueue Orders Master JavaScript (Task 1.2.10 - Read-only display)
            // Note: dashboard-express.js NOT enqueued - will be added in Task 1.2.11 for action buttons
            wp_enqueue_script(
                'oj-orders-master',
                ORDERS_JET_PLUGIN_URL . 'assets/js/orders-master.js',
                array('jquery', 'orders-jet-admin'),
                ORDERS_JET_VERSION,
                true
            );
            
            // Localize Orders Master script with pagination data (Task 1.2.3.4)
            wp_localize_script('oj-orders-master', 'OrdersJetMaster', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oj_dashboard_nonce'),
                'pagination' => array(
                    'perPage' => 24,
                    'currentPage' => isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1,
                    'currentFilter' => isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all'
                ),
                'strings' => array(
                    'loading' => __('Loading orders...', 'orders-jet'),
                    'error' => __('Failed to load orders', 'orders-jet'),
                    'noOrders' => __('No orders found', 'orders-jet'),
                    'connectionError' => __('Connection error. Please try again.', 'orders-jet'),
                    'retry' => __('Retry', 'orders-jet')
                )
            ));
        }
        
        // Orders Master V2 - Enqueue CSS and JavaScript (Task 1.2.11)
        if ($hook === 'orders_page_orders-master-v2') {
            // Enqueue base CSS files first
            wp_enqueue_style(
                'oj-manager-orders-cards',
                ORDERS_JET_PLUGIN_URL . 'assets/css/manager-orders-cards.css',
                array('orders-jet-admin'),
                ORDERS_JET_VERSION
            );
            wp_enqueue_style(
                'oj-dashboard-express',
                ORDERS_JET_PLUGIN_URL . 'assets/css/dashboard-express.css',
                array('oj-manager-orders-cards'),
                ORDERS_JET_VERSION
            );
            
            // Enqueue Orders Master V2 Toolbar CSS
            wp_enqueue_style(
                'oj-orders-master-toolbar',
                ORDERS_JET_PLUGIN_URL . 'assets/css/orders-master-toolbar.css',
                array('oj-dashboard-express'),
                ORDERS_JET_VERSION
            );
            
            // Enqueue Filters Slide Panel CSS
            wp_enqueue_style(
                'oj-filters-slide-panel',
                ORDERS_JET_PLUGIN_URL . 'assets/css/filters-slide-panel.css',
                array('oj-orders-master-toolbar'),
                ORDERS_JET_VERSION
            );
            
            // Enqueue utility classes first
            wp_enqueue_script('oj-url-parameter-manager', 
                ORDERS_JET_PLUGIN_URL . 'assets/js/url-parameter-manager.js', 
                array('jquery'), 
                ORDERS_JET_VERSION, 
                true
            );
            
            wp_enqueue_script('oj-ajax-request-manager', 
                ORDERS_JET_PLUGIN_URL . 'assets/js/ajax-request-manager.js', 
                array('jquery'), 
                ORDERS_JET_VERSION, 
                true
            );
            
            // Enqueue Saved Views JavaScript (dependency for filters panel)
            wp_enqueue_script('oj-saved-views', 
                ORDERS_JET_PLUGIN_URL . 'assets/js/oj-saved-views.js', 
                array('jquery'), 
                ORDERS_JET_VERSION, 
                true
            );
            
            // Enqueue Filters Slide Panel JavaScript FIRST
            // This must load before orders-master-v2 because sort links need window.ojFiltersPanel
            wp_enqueue_script('oj-filters-slide-panel', 
                ORDERS_JET_PLUGIN_URL . 'assets/js/filters-slide-panel.js', 
                array('jquery', 'oj-saved-views', 'oj-url-parameter-manager', 'oj-ajax-request-manager'), 
                ORDERS_JET_VERSION, 
                true
            );
            
            // Enqueue Orders Master V2 specific JavaScript
            // Depends on filters-slide-panel to ensure window.ojFiltersPanel exists for sort links
            wp_enqueue_script('oj-orders-master-v2', 
                ORDERS_JET_PLUGIN_URL . 'assets/js/orders-master-v2.js', 
                array('jquery', 'oj-filters-slide-panel', 'oj-url-parameter-manager', 'oj-ajax-request-manager'), 
                ORDERS_JET_VERSION, 
                true
            );
            
            // Also enqueue dashboard-express.js for other action buttons (Mark Ready, Complete, Close Table)
            wp_enqueue_script('oj-dashboard-express', 
                ORDERS_JET_PLUGIN_URL . 'assets/js/dashboard-express.js', 
                array('jquery', 'oj-orders-master-v2'), 
                ORDERS_JET_VERSION, 
                true
            );
            
            wp_localize_script('oj-orders-master-v2', 'ojExpressData', array(
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
                    'closing' => __('Closing...', 'orders-jet'),
                    'forceClosing' => __('Force Closing...', 'orders-jet'),
                    'closeTable' => __('Close Table', 'orders-jet'),
                    'paymentMethod' => __('Payment Method', 'orders-jet'),
                    'howPaid' => __('How was this order paid?', 'orders-jet'),
                    'cash' => __('Cash', 'orders-jet'),
                    'card' => __('Card', 'orders-jet'),
                    'other' => __('Other', 'orders-jet'),
                    'viewOrderDetails' => __('View Order Details', 'orders-jet'),
                    'dinein' => __('Dine-in', 'orders-jet'),
                    'combined' => __('Combined', 'orders-jet'),
                    'ready' => __('Ready', 'orders-jet'),
                    'clickToContinue' => __('Click OK to continue or Cancel to keep the table open.', 'orders-jet'),
                    'printFailed' => __('Print failed:', 'orders-jet'),
                    'failedToLoadInvoice' => __('Failed to load invoice', 'orders-jet'),
                    'paymentConfirmed' => __('Payment confirmed! Order completed.', 'orders-jet'),
                    'failedToConfirmPayment' => __('Failed to confirm payment', 'orders-jet'),
                    'connectionError' => __('Connection error', 'orders-jet'),
                    'tableClosed' => __('Table closed! Combined order created.', 'orders-jet'),
                    'failedToForceClose' => __('Failed to force close table', 'orders-jet'),
                    'connectionErrorForceClose' => __('Connection error during force close', 'orders-jet'),
                    'tableForceClose' => __('Table force closed! Combined order created.', 'orders-jet'),
                    'failedToCloseTable' => __('Failed to close table', 'orders-jet')
                )
            ));
            
            // Localize Filters Slide Panel script with AJAX data
            wp_localize_script('oj-filters-slide-panel', 'ordersJetAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('orders_jet_nonce')
            ));
        }
        
        // Orders Reports - Enqueue same CSS and JavaScript as Orders Master V2 (Phase 1.3)
        if ($hook === 'orders_page_orders-reports') {
            // Enqueue base CSS files first
            wp_enqueue_style(
                'oj-manager-orders-cards',
                ORDERS_JET_PLUGIN_URL . 'assets/css/manager-orders-cards.css',
                array('orders-jet-admin'),
                ORDERS_JET_VERSION
            );
            wp_enqueue_style(
                'oj-dashboard-express',
                ORDERS_JET_PLUGIN_URL . 'assets/css/dashboard-express.css',
                array('oj-manager-orders-cards'),
                ORDERS_JET_VERSION
            );
            
            // Enqueue Orders Master V2 Toolbar CSS (reused for reports)
            wp_enqueue_style(
                'oj-orders-master-toolbar',
                ORDERS_JET_PLUGIN_URL . 'assets/css/orders-master-toolbar.css',
                array('oj-dashboard-express'),
                ORDERS_JET_VERSION
            );
            
            // Enqueue Filters Slide Panel CSS (reused for reports)
            wp_enqueue_style(
                'oj-filters-slide-panel',
                ORDERS_JET_PLUGIN_URL . 'assets/css/filters-slide-panel.css',
                array('oj-orders-master-toolbar'),
                ORDERS_JET_VERSION
            );
            
            // Enqueue utility classes first
            wp_enqueue_script('oj-url-parameter-manager', 
                ORDERS_JET_PLUGIN_URL . 'assets/js/url-parameter-manager.js', 
                array('jquery'), 
                ORDERS_JET_VERSION, 
                true
            );
            
            wp_enqueue_script('oj-ajax-request-manager', 
                ORDERS_JET_PLUGIN_URL . 'assets/js/ajax-request-manager.js', 
                array('jquery'), 
                ORDERS_JET_VERSION, 
                true
            );
            
            // Enqueue Saved Views JavaScript (dependency for filters panel)
            wp_enqueue_script('oj-saved-views', 
                ORDERS_JET_PLUGIN_URL . 'assets/js/oj-saved-views.js', 
                array('jquery'), 
                ORDERS_JET_VERSION, 
                true
            );
            
            // Enqueue Filters Slide Panel JavaScript FIRST
            wp_enqueue_script('oj-filters-slide-panel', 
                ORDERS_JET_PLUGIN_URL . 'assets/js/filters-slide-panel.js', 
                array('jquery', 'oj-saved-views', 'oj-url-parameter-manager', 'oj-ajax-request-manager'), 
                ORDERS_JET_VERSION, 
                true
            );
            
            // Enqueue Orders Master V2 JavaScript (reused for reports - will be renamed later)
            wp_enqueue_script('oj-orders-master-v2', 
                ORDERS_JET_PLUGIN_URL . 'assets/js/orders-master-v2.js', 
                array('jquery', 'oj-filters-slide-panel', 'oj-url-parameter-manager', 'oj-ajax-request-manager'), 
                ORDERS_JET_VERSION, 
                true
            );
            
            // Also enqueue dashboard-express.js for other action buttons (Mark Ready, Complete, Close Table)
            wp_enqueue_script('oj-dashboard-express', 
                ORDERS_JET_PLUGIN_URL . 'assets/js/dashboard-express.js', 
                array('jquery', 'oj-orders-master-v2'), 
                ORDERS_JET_VERSION, 
                true
            );
            
            wp_localize_script('oj-orders-master-v2', 'ojExpressData', array(
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
                    'closing' => __('Closing...', 'orders-jet'),
                    'forceClosing' => __('Force Closing...', 'orders-jet'),
                    'closeTable' => __('Close Table', 'orders-jet'),
                    'paymentMethod' => __('Payment Method', 'orders-jet'),
                    'howPaid' => __('How was this order paid?', 'orders-jet'),
                    'cash' => __('Cash', 'orders-jet'),
                    'card' => __('Card', 'orders-jet'),
                    'other' => __('Other', 'orders-jet'),
                    'viewOrderDetails' => __('View Order Details', 'orders-jet'),
                    'dinein' => __('Dine-in', 'orders-jet'),
                    'combined' => __('Combined', 'orders-jet'),
                    'ready' => __('Ready', 'orders-jet'),
                    'clickToContinue' => __('Click OK to continue or Cancel to keep the table open.', 'orders-jet'),
                    'printFailed' => __('Print failed:', 'orders-jet'),
                    'failedToLoadInvoice' => __('Failed to load invoice', 'orders-jet'),
                    'paymentConfirmed' => __('Payment confirmed! Order completed.', 'orders-jet'),
                    'failedToConfirmPayment' => __('Failed to confirm payment', 'orders-jet'),
                    'connectionError' => __('Connection error', 'orders-jet'),
                    'tableClosed' => __('Table closed! Combined order created.', 'orders-jet'),
                    'failedToForceClose' => __('Failed to force close table', 'orders-jet'),
                    'connectionErrorForceClose' => __('Connection error during force close', 'orders-jet'),
                    'tableForceClose' => __('Table force closed! Combined order created.', 'orders-jet'),
                    'failedToCloseTable' => __('Failed to close table', 'orders-jet')
                )
            ));
            
            // Localize Filters Slide Panel script with AJAX data
            wp_localize_script('oj-filters-slide-panel', 'ordersJetAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('orders_jet_nonce')
            ));
        }
        
        // Business Intelligence - NEW BI Page with dedicated styles
        if ($hook === 'orders_page_business-intelligence') {
            // Enqueue base design system (reuse existing)
            wp_enqueue_style(
                'oj-manager-orders-cards',
                ORDERS_JET_PLUGIN_URL . 'assets/css/manager-orders-cards.css',
                array('orders-jet-admin'),
                ORDERS_JET_VERSION
            );
            wp_enqueue_style(
                'oj-dashboard-express',
                ORDERS_JET_PLUGIN_URL . 'assets/css/dashboard-express.css',
                array('oj-manager-orders-cards'),
                ORDERS_JET_VERSION
            );
            
            // Enqueue BI-specific styles
            wp_enqueue_style(
                'oj-business-intelligence',
                ORDERS_JET_PLUGIN_URL . 'assets/css/business-intelligence.css',
                array('oj-dashboard-express'),
                ORDERS_JET_VERSION
            );
        }
        
        // Orders Reports - Comprehensive Reporting Dashboard
        if ($hook === 'orders_page_orders-reports') {
            // Enqueue Orders Reports CSS
            wp_enqueue_style(
                'oj-orders-reports',
                ORDERS_JET_PLUGIN_URL . 'assets/css/orders-reports.css',
                array('orders-jet-admin'),
                ORDERS_JET_VERSION
            );
            
            // Enqueue Orders Reports JavaScript
            wp_enqueue_script(
                'oj-orders-reports',
                ORDERS_JET_PLUGIN_URL . 'assets/js/orders-reports.js',
                array('jquery'),
                ORDERS_JET_VERSION,
                true
            );
            
            // Localize script with AJAX data
            wp_localize_script('oj-orders-reports', 'ojReportsData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('oj_reports_nonce'),
                'i18n' => array(
                    'loading' => __('Loading...', 'orders-jet'),
                    'error' => __('An error occurred', 'orders-jet'),
                    'success' => __('Success!', 'orders-jet'),
                    'noData' => __('No data available', 'orders-jet'),
                )
            ));
        }
        
        // Orders Management page uses inline JavaScript (server-side filtering approach)
        // No external assets needed - all handled within the template file
        
        wp_enqueue_script(
            'orders-jet-admin',
            ORDERS_JET_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ORDERS_JET_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('orders-jet-admin', 'OrdersJetAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oj_dashboard_nonce'),
            'userRole' => oj_get_user_role() ?: (current_user_can('manage_options') ? 'oj_manager' : ''),
            'userId' => get_current_user_id(),
            'userName' => wp_get_current_user()->display_name,
            'isRTL' => is_rtl(), // Add RTL detection for JavaScript
            'language' => get_user_locale(), // Add current language
        ));
    }
    
    /**
     * Render Orders Overview (Phase 1.4)
     */
    public function render_orders_overview() {
        // Check basic read permission
        if (!current_user_can('read')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the orders overview template (placeholder for now)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/orders-overview.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_orders_placeholder('Orders Overview', 'Comprehensive dashboard showing orders performance, quick actions, and key metrics.');
        }
    }
    
    /**
     * Render Orders Express (Phase 1.1 - Already implemented)
     */
    public function render_orders_express() {
        // Check basic read permission
        if (!current_user_can('read')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the existing Orders Express template
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-manager-orders-express.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            wp_die(__('Orders Express template not found.', 'orders-jet'));
        }
    }
    
    /**
     * Render Orders Master (Phase 1.2 - Current Focus)
     */
    public function render_orders_master() {
        // Check basic read permission
        if (!current_user_can('read')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the Orders Master template
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/orders-master.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // For now, show placeholder until we create the template
            $this->render_orders_placeholder('Orders Master', 'Advanced orders management with comprehensive filtering, search, and role-based views. Currently in development - Phase 1.2.1.');
        }
    }
    
    /**
     * Render Orders Master V2 (New Implementation)
     */
    public function render_orders_master_v2() {
        // Check manager permission
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the Orders Master V2 template
        include ORDERS_JET_PLUGIN_DIR . 'templates/admin/orders-master-v2.php';
    }
    
    /**
     * Render Dev Tools Page
     */
    public function render_dev_tools() {
        // Check admin permission
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the Dev Tools template
        include ORDERS_JET_PLUGIN_DIR . 'templates/admin/dev-tools.php';
    }
    
    /**
     * Render Orders Reports (Phase 1.3)
     */
    public function render_orders_reports() {
        // Check basic read permission
        if (!current_user_can('read')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the orders reports template (placeholder for now)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/orders-reports.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_orders_placeholder('Orders Reports', 'Detailed analytics and reports for orders performance, trends, and insights.');
        }
    }
    
    /**
     * Render Business Intelligence page
     */
    public function render_business_intelligence() {
        // Check manager permissions
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the Business Intelligence template
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/business-intelligence.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_orders_placeholder('Business Intelligence', 'Transform your orders data into actionable business insights with grouped analytics, staff performance tracking, and intelligent reporting.');
        }
    }
    
    /**
     * Render placeholder page for Orders sections
     */
    private function render_orders_placeholder($title, $description) {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-clipboard" style="font-size: 28px; vertical-align: middle; margin-right: 10px;"></span>
                <?php echo esc_html($title); ?>
            </h1>
            <hr class="wp-header-end">
            
            <div class="notice notice-info">
                <p><strong><?php _e('Phase 1 - Orders Module', 'orders-jet'); ?></strong></p>
                <p><?php echo esc_html($description); ?></p>
                <p><?php _e('Following our step-by-step development plan with proper testing for each task.', 'orders-jet'); ?></p>
            </div>
            
            <div class="orders-placeholder" style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; margin-top: 20px;">
                <div style="font-size: 64px; color: #ddd; margin-bottom: 20px;">
                    <span class="dashicons dashicons-clipboard"></span>
                </div>
                <h2 style="color: #666; margin-bottom: 10px;"><?php echo esc_html($title); ?></h2>
                <p style="color: #999; font-size: 16px; max-width: 500px; margin: 0 auto;"><?php echo esc_html($description); ?></p>
                
                <div style="margin-top: 30px;">
                    <a href="?page=orders-express" class="button button-primary" style="margin-right: 10px;">
                        <?php _e('⚡ Go to Orders Express', 'orders-jet'); ?>
                    </a>
                    <a href="?page=orders-overview" class="button">
                        <?php _e('Back to Orders Overview', 'orders-jet'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render manager overview (main dashboard)
     */
    public function render_manager_overview() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the overview template (placeholder for now)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-overview.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Create a simple placeholder
            $this->render_manager_placeholder('Overview', 'A comprehensive dashboard showing restaurant performance, quick actions, and key metrics.');
        }
    }
    
    /**
     * Render manager orders (current orders management)
     */
    public function render_manager_orders() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the orders management template (our current manager dashboard)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-manager-orders.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            wp_die(__('Orders Management template not found.', 'orders-jet'));
        }
    }
    
    /**
     * Render Orders Express page (clean architecture - active orders only)
     */
    public function render_manager_orders_express() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the orders express template
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-manager-orders-express.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            wp_die(__('Orders Express template not found.', 'orders-jet'));
        }
    }
    
    /**
     * Render manager tables
     */
    public function render_manager_tables() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the tables management template (placeholder for now)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-tables.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_manager_placeholder('Tables Management', 'Manage restaurant tables, assignments, and seating arrangements.');
        }
    }
    
    /**
     * Render manager staff
     */
    public function render_manager_staff() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the staff management template (placeholder for now)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-staff.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_manager_placeholder('Staff Management', 'Manage restaurant staff, roles, schedules, and performance.');
        }
    }
    
    /**
     * Render manager reports
     */
    public function render_manager_reports() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the reports template (placeholder for now)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-reports.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_manager_placeholder('Reports', 'View detailed analytics, sales reports, and business insights.');
        }
    }
    
    /**
     * Render manager settings
     */
    public function render_manager_settings() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the settings template (placeholder for now)
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/manager-settings.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            $this->render_manager_placeholder('Settings', 'Configure restaurant settings, preferences, and system options.');
        }
    }
    
    /**
     * Render invoice page
     */
    public function render_invoice_page() {
        // Get order ID from URL
        $order_id = intval($_GET['order_id'] ?? 0);
        
        if (empty($order_id)) {
            wp_die(__('Order ID is required', 'orders-jet'));
        }
        
        // Get the order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_die(__('Order not found', 'orders-jet'));
        }
        
        // Check if it's a table order or individual order
        $table_number = $order->get_meta('_oj_table_number');
        
        if (!empty($table_number)) {
            // Table order - redirect to table invoice
            $invoice_url = site_url('/wp-content/plugins/orders-jet-integration/table-invoice.php?table=' . urlencode($table_number) . '&payment_method=cash');
        } else {
            // Individual order - redirect to individual order invoice
            $invoice_url = site_url('/wp-content/plugins/orders-jet-integration/table-invoice.php?order_id=' . $order_id . '&payment_method=cash');
        }
        
        // Check if print parameter is set
        if (isset($_GET['print']) && $_GET['print'] == '1') {
            $invoice_url .= '&print=1';
        }
        
        // Redirect to the invoice
        wp_redirect($invoice_url);
        exit;
    }
    
    /**
     * Render placeholder page for manager sections
     */
    private function render_manager_placeholder($title, $description) {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-businessman" style="font-size: 28px; vertical-align: middle; margin-right: 10px;"></span>
                <?php echo esc_html($title); ?>
            </h1>
            <hr class="wp-header-end">
            
            <div class="notice notice-info">
                <p><strong><?php _e('Coming Soon!', 'orders-jet'); ?></strong></p>
                <p><?php echo esc_html($description); ?></p>
                <p><?php _e('This section will be developed in the upcoming phases of the Manager Screen system.', 'orders-jet'); ?></p>
            </div>
            
            <div class="manager-placeholder" style="background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; margin-top: 20px;">
                <div style="font-size: 64px; color: #ddd; margin-bottom: 20px;">
                    <span class="dashicons dashicons-businessman"></span>
                </div>
                <h2 style="color: #666; margin-bottom: 10px;"><?php echo esc_html($title); ?></h2>
                <p style="color: #999; font-size: 16px; max-width: 500px; margin: 0 auto;"><?php echo esc_html($description); ?></p>
                
                <div style="margin-top: 30px;">
                    <a href="?page=manager-orders" class="button button-primary" style="margin-right: 10px;">
                        <?php _e('Go to Orders Management', 'orders-jet'); ?>
                    </a>
                    <a href="?page=manager-screen" class="button">
                        <?php _e('Back to Overview', 'orders-jet'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render kitchen dashboard
     */
    public function render_kitchen_dashboard() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_kitchen_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the simple dashboard template
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-kitchen.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            wp_die(__('Dashboard template not found.', 'orders-jet'));
        }
    }
    
    /**
     * Render waiter dashboard (old table-focused view)
     */
    public function render_waiter_dashboard() {
        // Check permissions with fallback to admin
        if (!current_user_can('access_oj_waiter_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the simple dashboard template
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-waiter.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            wp_die(__('Dashboard template not found.', 'orders-jet'));
        }
    }
    
    /**
     * Render waiter orders view (new orders-focused view)
     */
    public function render_waiter_orders() {
        // Check permissions
        if (!current_user_can('access_oj_waiter_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the waiter orders template
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-waiter-orders.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            wp_die(__('Waiter view template not found.', 'orders-jet'));
        }
    }
    
    /**
     * Render table assignment page
     */
    public function render_table_assignment() {
        // Check permissions
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'orders-jet'));
        }
        
        // Load the table assignment template
        $template_path = ORDERS_JET_PLUGIN_DIR . 'templates/admin/dashboard-table-assignment.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            wp_die(__('Table assignment template not found.', 'orders-jet'));
        }
    }
    
    // React dashboard function removed - using PHP templates instead
    
    /**
     * Get dashboard data (AJAX)
     */
    public function get_dashboard_data() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $user_role = oj_get_user_role();
        $user_id = get_current_user_id();
        
        $data = array(
            'timestamp' => current_time('timestamp'),
            'tables' => $this->get_tables_data($user_role, $user_id),
            'orders' => $this->get_orders_data($user_role, $user_id),
            'notifications' => $this->get_notifications_data($user_role, $user_id),
        );
        
        // Add role-specific data
        if ($user_role === 'oj_manager') {
            $data['staff'] = $this->get_staff_activity();
            $data['metrics'] = $this->get_performance_metrics();
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * Get tables data using centralized query service
     * OPTIMIZED: Uses centralized query service to eliminate duplicate logic
     */
    private function get_tables_data($user_role, $user_id) {
        $options = array(
            'limit' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'cache' => true,
            'cache_duration' => 300 // 5 minutes cache for tables
        );
        
        // Waiters only see their assigned tables
        if ($user_role === 'oj_waiter') {
            $options['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => '_oj_assigned_waiter',
                    'value' => $user_id,
                    'compare' => '=',
                ),
                array(
                    'key' => '_oj_assigned_waiter',
                    'compare' => 'NOT EXISTS',
                ),
            );
        }
        
        $tables = oj_query_service()->get_tables($options);
        $table_data = array();
        
        foreach ($tables as $table) {
            $table_number = get_post_meta($table->ID, '_oj_table_number', true);
            $table_status = get_post_meta($table->ID, '_oj_table_status', true);
            $assigned_waiter_id = get_post_meta($table->ID, '_oj_assigned_waiter', true);
            $session_start = get_post_meta($table->ID, '_oj_session_start', true);
            $session_orders = get_post_meta($table->ID, '_oj_session_orders', true);
            $session_total = get_post_meta($table->ID, '_oj_session_total', true);
            
            $waiter_name = '';
            if ($assigned_waiter_id) {
                $waiter = get_userdata($assigned_waiter_id);
                $waiter_name = $waiter ? $waiter->display_name : '';
            }
            
            $table_data[] = array(
                'id' => $table->ID,
                'number' => $table_number,
                'name' => $table->post_title,
                'status' => $table_status ?: 'available',
                'assigned_waiter_id' => $assigned_waiter_id,
                'assigned_waiter_name' => $waiter_name,
                'session_start' => $session_start,
                'session_orders_count' => is_array($session_orders) ? count($session_orders) : 0,
                'session_total' => $session_total ? floatval($session_total) : 0,
                'can_claim' => $user_role === 'oj_waiter' && !$assigned_waiter_id,
                'is_mine' => $assigned_waiter_id == $user_id,
            );
        }
        
        return $table_data;
    }
    
    /**
     * Get orders data
     */
    private function get_orders_data($user_role, $user_id) {
        $args = array(
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        // Kitchen sees only processing orders (simplified)
        if ($user_role === 'oj_kitchen') {
            $args['status'] = array('processing');
        }
        
        // Waiters see orders assigned to them
        if ($user_role === 'oj_waiter') {
            $args['meta_query'] = array(
                array(
                    'key' => '_oj_assigned_waiter',
                    'value' => $user_id,
                    'compare' => '=',
                ),
            );
        }
        
        // Manager sees all active orders
        if ($user_role === 'oj_manager') {
            $args['status'] = array('pending', 'processing', 'on-hold');
        }
        
        $orders = wc_get_orders($args);
        $order_data = array();
        
        foreach ($orders as $order) {
            $order_status = $order->get_meta('_oj_order_status') ?: 'placed';
            $table_number = $order->get_meta('_oj_table_number');
            $assigned_waiter_id = $order->get_meta('_oj_assigned_waiter');
            $received_time = $order->get_meta('_oj_received_time');
            $preparing_time = $order->get_meta('_oj_preparing_time');
            $order_type = oj_get_order_type($order);
            
            $waiter_name = '';
            if ($assigned_waiter_id) {
                $waiter = get_userdata($assigned_waiter_id);
                $waiter_name = $waiter ? $waiter->display_name : '';
            }
            
            // Calculate preparation time
            $prep_minutes = 0;
            if ($preparing_time) {
                $prep_minutes = floor((current_time('timestamp') - intval($preparing_time)) / 60);
            } elseif ($received_time) {
                $prep_minutes = floor((current_time('timestamp') - intval($received_time)) / 60);
            }
            
            $order_data[] = array(
                'id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'table_number' => $table_number,
                'order_type' => $order_type,
                'order_type_label' => oj_get_order_type_label($order_type),
                'delivery_address' => ($order_type === 'delivery') ? $order->get_meta('_oj_delivery_address') ?: $order->get_meta('_exwf_delivery_address') : '',
                'status' => $order_status,
                'wc_status' => $order->get_status(),
                'assigned_waiter_id' => $assigned_waiter_id,
                'assigned_waiter_name' => $waiter_name,
                'total' => $order->get_total(),
                'total_formatted' => wc_price($order->get_total()),
                'date' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'date_formatted' => $order->get_date_created()->format('M d, H:i'),
                'prep_minutes' => $prep_minutes,
                'items' => $this->get_order_items_data($order),
                'special_instructions' => $order->get_customer_note(),
            );
        }
        
        return $order_data;
    }
    
    /**
     * Get order items data
     */
    private function get_order_items_data($order) {
        $items_data = array();
        
        foreach ($order->get_items() as $item) {
            $items_data[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
                'total_formatted' => wc_price($item->get_total()),
                'addons' => $item->get_meta('_oj_item_addons'),
                'notes' => $item->get_meta('_oj_item_notes'),
            );
        }
        
        return $items_data;
    }
    
    /**
     * Get notifications data
     */
    private function get_notifications_data($user_role, $user_id) {
        $notifications = array();
        
        // This will be populated by real-time checks
        // For now, return empty array
        
        return $notifications;
    }
    
    /**
     * Get staff activity (for managers)
     */
    private function get_staff_activity() {
        $staff = oj_get_staff_users();
        $activity = array();
        
        foreach ($staff as $member) {
            $assigned_tables = get_user_meta($member->ID, '_oj_assigned_tables', true);
            
            $activity[] = array(
                'id' => $member->ID,
                'name' => $member->display_name,
                'role' => $member->oj_role_name,
                'assigned_tables_count' => is_array($assigned_tables) ? count($assigned_tables) : 0,
                'active' => true, // TODO: Implement activity tracking
            );
        }
        
        return $activity;
    }
    
    /**
     * Get performance metrics (for managers)
     */
    private function get_performance_metrics() {
        // Get today's metrics
        $today_start = strtotime('today');
        
        // OPTIMIZED COUNT - Use pagination for performance
        $args = array(
            'limit' => 1,
            'paginate' => true,
            'date_created' => '>=' . $today_start,
        );
        
        $orders_result = wc_get_orders($args);
        $orders = $orders_result->orders; // Get actual orders for processing
        $total_orders = $orders_result->total; // Use total from pagination
        
        // Total already calculated from pagination above
        $total_revenue = 0;
        $avg_prep_time = 0;
        $completed_orders = 0;
        
        foreach ($orders as $order) {
            $total_revenue += $order->get_total();
            
            if ($order->get_status() === 'completed') {
                $completed_orders++;
            }
        }
        
        return array(
            'today_orders' => $total_orders,
            'today_revenue' => wc_price($total_revenue),
            'completed_orders' => $completed_orders,
            'pending_orders' => $total_orders - $completed_orders,
            'avg_prep_time' => $avg_prep_time, // TODO: Calculate from timestamps
        );
    }
    
    /**
     * Get table updates (AJAX)
     */
    public function get_table_updates() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $last_check = isset($_POST['last_check']) ? intval($_POST['last_check']) : 0;
        $user_role = oj_get_user_role();
        $user_id = get_current_user_id();
        
        $tables = $this->get_tables_data($user_role, $user_id);
        
        wp_send_json_success(array(
            'tables' => $tables,
            'timestamp' => current_time('timestamp'),
        ));
    }
    
    /**
     * Get order updates (AJAX)
     */
    public function get_order_updates() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $last_check = isset($_POST['last_check']) ? intval($_POST['last_check']) : 0;
        $user_role = oj_get_user_role();
        $user_id = get_current_user_id();
        
        $orders = $this->get_orders_data($user_role, $user_id);
        
        // Find new orders since last check
        $new_orders = array();
        foreach ($orders as $order) {
            $order_time = strtotime($order['date']);
            if ($order_time > $last_check) {
                $new_orders[] = $order;
            }
        }
        
        wp_send_json_success(array(
            'orders' => $orders,
            'new_orders' => $new_orders,
            'new_count' => count($new_orders),
            'timestamp' => current_time('timestamp'),
        ));
    }
    
    /**
     * Get notifications (AJAX)
     */
    public function get_notifications() {
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        $user_role = oj_get_user_role();
        $user_id = get_current_user_id();
        
        $notifications = $this->get_notifications_data($user_role, $user_id);
        
        wp_send_json_success(array(
            'notifications' => $notifications,
            'count' => count($notifications),
        ));
    }
    
    /**
     * Get Orders Master filtered data (AJAX)
     * Task 1.2.3.4 - AJAX Filters Preparation
     */
    public function ajax_get_orders_master_filtered() {
        $start_time = microtime(true); // Performance monitoring
        
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        // Get parameters from AJAX request - use same approach as BI system
        $params = array(
            'filter' => sanitize_text_field($_POST['filter'] ?? 'all'),
            'page' => max(1, intval($_POST['page'] ?? 1)),
            'per_page' => 24,
            'search' => sanitize_text_field($_POST['search'] ?? '')
        );
        
        // Add waiter assignment parameters if present
        if (!empty($_POST['assigned_waiter'])) {
            $params['assigned_waiter'] = intval($_POST['assigned_waiter']);
        }
        if (($_POST['unassigned_only'] ?? '') === '1') {
            $params['unassigned_only'] = '1';
        }
        if (($_POST['assigned_only'] ?? '') === '1') {
            $params['assigned_only'] = '1';
        }
        
        // Get current user info
        $user_role = oj_get_user_role() ?: (current_user_can('manage_options') ? 'oj_manager' : '');
        $user_id = get_current_user_id();
        
        try {
            // Use query builder directly (same approach as BI system)
            $query_builder = new Orders_Master_Query_Builder($params);
            $orders = $query_builder->get_orders();
            $total_orders = $query_builder->get_total_orders();
            
            // Process orders for display
            $orders_data = $this->prepare_orders_master_data_bulk($orders);
            
            // Get filter counts for badges
            $base_query_args = array(
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'ids',
                'limit' => -1,
                'paginate' => false
            );
            $filter_counts = $this->get_optimized_filter_counts($base_query_args, $user_role, $user_id);
            
            $master_data = array(
                'orders' => $orders_data,
                'counts' => $filter_counts,
                'total' => count($orders_data),
                'user_role' => $user_role,
                'timestamp' => current_time('timestamp'),
                'pagination' => array(
                    'current_page' => $params['page'],
                    'per_page' => $params['per_page'],
                    'total_orders' => $total_orders,
                    'total_pages' => ceil($total_orders / $params['per_page'])
                )
            );
            
            // Add performance info
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            $master_data['debug'] = array(
                'execution_time_ms' => $execution_time,
                'search_term' => $params['search'],
                'filter' => $params['filter'],
                'page' => $params['page'],
                'orders_count' => count($master_data['orders'])
            );
            
            // Return success response
            wp_send_json_success($master_data);
            
        } catch (Exception $e) {
            // Log the error and return failure
            oj_error_log('Orders Master AJAX Error: ' . $e->getMessage(), 'AJAX_ERROR');
            wp_send_json_error(array(
                'message' => 'Search failed: ' . $e->getMessage(),
                'execution_time_ms' => round((microtime(true) - $start_time) * 1000, 2)
            ));
        }
    }
    
    /**
     * Get Orders Master data - Comprehensive order query for all statuses
     * Task 1.2.3.1 - Create Order Data Query Function
     * Task 1.2.3.4 - Enhanced with filter support
     */
    public function get_orders_master_data($user_role = null, $user_id = null, $filter = 'all', $page = 1, $per_page = 24, $search = '', $assigned_waiter = 0, $unassigned_only = false) {
        // Get current user info if not provided
        if ($user_role === null) {
            $user_role = oj_get_user_role() ?: (current_user_can('manage_options') ? 'oj_manager' : '');
        }
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }
        
        // PERFORMANCE OPTIMIZATION: Intelligent result caching
        return $this->get_cached_orders_master_data($user_role, $user_id, $filter, $page, $per_page, $search, $assigned_waiter, $unassigned_only);
    }
    
    /**
     * Get cached orders master data with intelligent cache management
     * Performance Optimization: Solution 4 - Add result caching
     */
    private function get_cached_orders_master_data($user_role, $user_id, $filter, $page, $per_page, $search, $assigned_waiter = 0, $unassigned_only = false) {
        // Create cache key based on all parameters
        $cache_params = array(
            'user_role' => $user_role,
            'user_id' => $user_id,
            'filter' => $filter,
            'page' => $page,
            'per_page' => $per_page,
            'search' => $search,
            'assigned_waiter' => $assigned_waiter,
            'unassigned_only' => $unassigned_only
        );
        $cache_key = 'oj_master_data_' . md5(serialize($cache_params));
        
        // Try to get from cache first
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            oj_debug_log("Orders Master data served from cache", 'CACHE', array(
                'cache_key' => substr($cache_key, -8),
                'orders_count' => count($cached_data['orders'])
            ));
            return $cached_data;
        }
        
        // Cache miss - generate fresh data
        $start_time = microtime(true);
        $fresh_data = $this->get_orders_master_data_fresh($user_role, $user_id, $filter, $page, $per_page, $search, $assigned_waiter, $unassigned_only);
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        
        // Determine cache duration based on data characteristics
        $cache_duration = $this->determine_cache_duration($filter, $search, count($fresh_data['orders']));
        
        // Cache the result
        set_transient($cache_key, $fresh_data, $cache_duration);
        
        oj_perf_log("Orders Master data generated and cached", $execution_time, 'CACHE');
        oj_debug_log("Cache set with duration: {$cache_duration}s", 'CACHE');
        
        return $fresh_data;
    }
    
    /**
     * Generate fresh orders master data (the actual data retrieval logic)
     */
    private function get_orders_master_data_fresh($user_role, $user_id, $filter, $page, $per_page, $search, $assigned_waiter = 0, $unassigned_only = false) {
        
        // Calculate pagination offset
        $offset = ($page - 1) * $per_page;
        
        // Define base query args - OPTIMIZED for filter counts
        $base_query_args = array(
            'orderby' => 'date',
            'order' => 'DESC', // Newest first for management overview
            'return' => 'objects'
            // Note: limit will be set per query type (pagination vs count)
        );
        
        // Apply role-based filtering to base query
        if ($user_role === 'oj_waiter') {
            // Waiters see only orders assigned to them
            $base_query_args['meta_query'] = array(
                array(
                    'key' => '_oj_assigned_waiter',
                    'value' => $user_id,
                    'compare' => '='
                )
            );
        } elseif ($user_role === 'oj_kitchen') {
            // Kitchen sees only processing orders (cooking)
            $base_query_args['status'] = array('wc-processing');
        }
        // Managers and admins see all orders (no additional filtering)
        
        // Get optimized filter counts (Performance optimization - no heavy processing)
        $filter_counts = $this->get_optimized_filter_counts($base_query_args, $user_role, $user_id);
        
        // Now get paginated orders for display
        $paginated_query_args = $base_query_args;
        $paginated_query_args['limit'] = $per_page;
        $paginated_query_args['offset'] = $offset;
        
        // Apply filter-specific status filtering to paginated query
        $paginated_query_args = $this->apply_status_filter($paginated_query_args, $filter);
        
        // Apply search filtering if search term provided (Task 1.2.3.5.2)
        if (!empty($search)) {
            $paginated_query_args = $this->apply_search_filter($paginated_query_args, $search);
        }
        
        // Use query builder for waiter assignment filters if needed
        if (!empty($assigned_waiter) || $unassigned_only) {
            // Build query parameters for the query builder
            $query_params = array(
                'filter' => $filter,
                'page' => $page,
                'per_page' => $per_page,
                'search' => $search
            );
            
            // Add waiter assignment parameters
            if (!empty($assigned_waiter)) {
                $query_params['assigned_waiter'] = $assigned_waiter;
            }
            if ($unassigned_only) {
                $query_params['unassigned_only'] = '1';
            }
            
            // Use query builder for complex filtering
            $query_builder = new Orders_Master_Query_Builder($query_params);
            $orders = $query_builder->get_orders();
            
            // Update total count from query builder
            $total_orders = $query_builder->get_total_orders();
        } else {
            // Execute normal paginated query for display
            $orders = wc_get_orders($paginated_query_args);
            $total_orders = count($orders);
        }
        
        $orders_data = array();
        
        // Process paginated orders for display using BULK QUERIES (Performance Optimization)
        $orders_data = $this->prepare_orders_master_data_bulk($orders);
        
        // Get total count for pagination - OPTIMIZED COUNT (only if not using query builder)
        if (empty($assigned_waiter) && !$unassigned_only) {
            $count_args = $paginated_query_args;
            $count_args['limit'] = 1;
            $count_args['paginate'] = true;
            $count_args['return'] = 'ids';
            unset($count_args['offset']);
            $count_result = wc_get_orders($count_args);
            $total_orders = $count_result->total;
        }
        // If using query builder, $total_orders is already set above
        
        return array(
            'orders' => $orders_data,
            'counts' => $filter_counts,
            'total' => count($orders_data),
            'user_role' => $user_role,
            'timestamp' => current_time('timestamp'),
            'pagination' => array(
                'current_page' => $page,
                'per_page' => $per_page,
                'total_orders' => $total_orders,
                'total_pages' => ceil($total_orders / $per_page),
                'has_next' => $page < ceil($total_orders / $per_page),
                'has_prev' => $page > 1,
                'filter' => $filter
            )
        );
    }
    
    /**
     * Prepare individual order data for Orders Master
     * Task 1.2.3.2 - Create Order Data Processing Function
     */
    private function prepare_orders_master_order_data($order) {
        // Get order basic info
        $order_id = $order->get_id();
        $order_number = $order->get_order_number();
        $wc_status = $order->get_status();
        $total = $order->get_total();
        $date_created = $order->get_date_created();
        
        // Get Orders Jet specific meta
        $oj_status = $order->get_meta('_oj_order_status') ?: 'placed';
        $table_number = $order->get_meta('_oj_table_number');
        $assigned_waiter_id = $order->get_meta('_oj_assigned_waiter');
        $order_type = oj_get_order_type($order);
        
        // Get customer info
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        if (empty(trim($customer_name))) {
            $customer_name = __('Guest', 'orders-jet');
        }
        
        // Get waiter name
        $waiter_name = '';
        if ($assigned_waiter_id) {
            $waiter = get_userdata($assigned_waiter_id);
            $waiter_name = $waiter ? $waiter->display_name : '';
        }
        
        // Get items info
        $items = $order->get_items();
        $item_count = count($items);
        $items_text = array();
        foreach ($items as $item) {
            $quantity = $item->get_quantity();
            $name = $item->get_name();
            $items_text[] = $quantity . 'x ' . $name;
        }
        $items_display = implode(', ', $items_text);
        
        // Determine kitchen type and status
        $kitchen_service = new Orders_Jet_Kitchen_Service();
        $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
        $kitchen_type = $this->determine_kitchen_type($items);
        
        // Map WooCommerce status to Orders Master status
        $master_status = $this->map_to_master_status($wc_status, $oj_status);
        
        return array(
            'id' => $order_id,
            'number' => $order_number,
            'wc_status' => $wc_status,
            'oj_status' => $oj_status,
            'master_status' => $master_status,
            'table_number' => $table_number,
            'order_type' => $order_type,
            'customer_name' => $customer_name,
            'total' => $total,
            'total_formatted' => wc_price($total),
            'date_created' => $date_created,
            'date_formatted' => $date_created->format('M d, H:i'),
            'time_ago' => human_time_diff($date_created->getTimestamp(), time()),
            'assigned_waiter_id' => $assigned_waiter_id,
            'waiter_name' => $waiter_name,
            'item_count' => $item_count,
            'items_display' => $items_display,
            'kitchen_type' => $kitchen_type,
            'kitchen_status' => $kitchen_status,
            'order_object' => $order // Keep reference for advanced operations
        );
    }
    
    /**
     * Prepare Orders Master data using BULK QUERIES (Performance Optimization)
     * Replaces N+1 queries with bulk operations for 80-90% performance improvement
     */
    private function prepare_orders_master_data_bulk($orders) {
        if (empty($orders)) {
            return array();
        }
        
        $start_time = microtime(true);
        
        // Extract all order IDs for bulk queries
        $order_ids = array_map(function($order) {
            return $order->get_id();
        }, $orders);
        
        oj_debug_log("Starting bulk data preparation for " . count($order_ids) . " orders", 'BULK_QUERY');
        
        // BULK QUERY 1: Get all meta data in one query
        $meta_data = $this->get_orders_meta_bulk($order_ids);
        
        // BULK QUERY 2: Get all order items in one query  
        $items_data = $this->get_orders_items_bulk($order_ids);
        
        // BULK QUERY 3: Get all user data in one query
        $users_data = $this->get_users_data_bulk($meta_data);
        
        // BULK QUERY 4: Pre-calculate all addon totals (Performance Optimization)
        Orders_Jet_Addon_Calculator::precalculate_addon_totals($order_ids);
        
        // Process orders using cached data
        $orders_data = array();
        foreach ($orders as $order) {
            // Skip refunds - they don't have order numbers and shouldn't be displayed
            if ($order instanceof WC_Order_Refund) {
                continue;
            }
            
            $order_id = $order->get_id();
            $order_data = $this->prepare_single_order_data_optimized(
                $order, 
                $meta_data[$order_id] ?? array(),
                $items_data[$order_id] ?? array(),
                $users_data
            );
            $orders_data[] = $order_data;
        }
        
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);
        oj_perf_log("Bulk data preparation completed", $execution_time, 'BULK_QUERY');
        
        return $orders_data;
    }
    
    /**
     * Determine intelligent cache duration based on data characteristics
     */
    private function determine_cache_duration($filter, $search, $orders_count) {
        // No search queries cache longer (more stable)
        if (empty($search)) {
            switch ($filter) {
                case 'completed':
                    return 300; // 5 minutes - completed orders change less frequently
                case 'active':
                    return 60;  // 1 minute - active orders change frequently
                case 'ready':
                    return 90;  // 1.5 minutes - ready orders change moderately
                case 'all':
                default:
                    return 120; // 2 minutes - mixed data
            }
        } else {
            // Search queries cache for shorter duration
            return 60; // 1 minute for search results
        }
    }
    
    /**
     * Clear Orders Master cache when orders are updated
     * Call this method when orders change to ensure real-time accuracy
     */
    public function clear_orders_master_cache($user_role = null, $user_id = null) {
        if ($user_role === null && $user_id === null) {
            // Clear all cached data for all users
            $this->clear_transients_by_pattern('oj_master_data_*');
            oj_debug_log("Cleared all Orders Master cache", 'CACHE');
        } else {
            // Clear specific user's cache (more targeted)
            $pattern = "oj_master_data_*";
            $this->clear_transients_by_pattern($pattern);
            oj_debug_log("Cleared Orders Master cache for user {$user_id}", 'CACHE');
        }
    }
    
    /**
     * Clear addon calculator cache when orders are updated
     */
    public function clear_addon_cache() {
        Orders_Jet_Addon_Calculator::clear_cache();
    }
    
    /**
     * Master cache clearing method - call when any order is updated
     */
    public function clear_all_performance_caches() {
        // Clear Orders Master data cache
        $this->clear_orders_master_cache();
        
        // Clear filter counts cache
        $this->clear_filter_counts_cache();
        
        // Clear addon calculator cache
        $this->clear_addon_cache();
        
        oj_debug_log("Cleared all performance caches", 'CACHE');
    }
    
    /**
     * Get all order meta data in single query
     */
    private function get_orders_meta_bulk($order_ids) {
        global $wpdb;
        
        if (empty($order_ids)) {
            return array();
        }
        
        $ids_placeholder = implode(',', array_fill(0, count($order_ids), '%d'));
        
        // Check if HPOS is enabled
        if ($this->is_hpos_enabled()) {
            $sql = "
                SELECT order_id, meta_key, meta_value 
                FROM {$wpdb->prefix}wc_orders_meta 
                WHERE order_id IN ($ids_placeholder)
                AND meta_key IN ('_oj_order_status', '_oj_table_number', '_oj_assigned_waiter', '_oj_order_type')
            ";
        } else {
            $sql = "
                SELECT post_id as order_id, meta_key, meta_value 
                FROM {$wpdb->postmeta} 
                WHERE post_id IN ($ids_placeholder)
                AND meta_key IN ('_oj_order_status', '_oj_table_number', '_oj_assigned_waiter', '_oj_order_type')
            ";
        }
        
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$order_ids));
        
        // Organize by order_id
        $meta_data = array();
        foreach ($results as $row) {
            $meta_data[$row->order_id][$row->meta_key] = $row->meta_value;
        }
        
        oj_debug_log("Retrieved meta data for " . count($meta_data) . " orders", 'BULK_QUERY');
        
        return $meta_data;
    }
    
    /**
     * Get all order items in single query
     */
    private function get_orders_items_bulk($order_ids) {
        global $wpdb;
        
        if (empty($order_ids)) {
            return array();
        }
        
        $ids_placeholder = implode(',', array_fill(0, count($order_ids), '%d'));
        
        $sql = "
            SELECT oi.order_id, 
                   COUNT(*) as item_count,
                   GROUP_CONCAT(
                       CONCAT(oim_qty.meta_value, 'x ', oi.order_item_name) 
                       ORDER BY oi.order_item_id 
                       SEPARATOR ', '
                   ) as items_display
            FROM {$wpdb->prefix}woocommerce_order_items oi
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim_qty ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
            WHERE oi.order_id IN ($ids_placeholder)
            AND oi.order_item_type = 'line_item'
            GROUP BY oi.order_id
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$order_ids));
        
        // Organize by order_id
        $items_data = array();
        foreach ($results as $row) {
            $items_data[$row->order_id] = array(
                'item_count' => intval($row->item_count),
                'items_display' => $row->items_display ?: ''
            );
        }
        
        oj_debug_log("Retrieved items data for " . count($items_data) . " orders", 'BULK_QUERY');
        
        return $items_data;
    }
    
    /**
     * Get all user data in single query
     */
    private function get_users_data_bulk($meta_data) {
        $user_ids = array();
        
        // Extract unique user IDs from meta data
        foreach ($meta_data as $order_meta) {
            if (!empty($order_meta['_oj_assigned_waiter'])) {
                $user_ids[] = intval($order_meta['_oj_assigned_waiter']);
            }
        }
        
        $user_ids = array_unique($user_ids);
        
        if (empty($user_ids)) {
            return array();
        }
        
        // Get all users in one query
        $users = get_users(array(
            'include' => $user_ids,
            'fields' => array('ID', 'display_name')
        ));
        
        // Organize by user ID
        $users_data = array();
        foreach ($users as $user) {
            $users_data[$user->ID] = $user->display_name;
        }
        
        oj_debug_log("Retrieved user data for " . count($users_data) . " users", 'BULK_QUERY');
        
        return $users_data;
    }
    
    /**
     * Prepare single order data using pre-fetched bulk data
     */
    private function prepare_single_order_data_optimized($order, $meta_data, $items_data, $users_data) {
        // Get order basic info
        $order_id = $order->get_id();
        $order_number = $order->get_order_number();
        $wc_status = $order->get_status();
        $total = $order->get_total();
        $date_created = $order->get_date_created();
        
        // Get Orders Jet specific meta from bulk data
        $oj_status = $meta_data['_oj_order_status'] ?? 'placed';
        $table_number = $meta_data['_oj_table_number'] ?? '';
        $assigned_waiter_id = $meta_data['_oj_assigned_waiter'] ?? '';
        $order_type = $meta_data['_oj_order_type'] ?? oj_get_order_type($order);
        
        // Get customer info
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        if (empty(trim($customer_name))) {
            $customer_name = __('Guest', 'orders-jet');
        }
        
        // Get waiter name from bulk data
        $waiter_name = '';
        if ($assigned_waiter_id && isset($users_data[$assigned_waiter_id])) {
            $waiter_name = $users_data[$assigned_waiter_id];
        }
        
        // Get items info from bulk data
        $item_count = $items_data['item_count'] ?? 0;
        $items_display = $items_data['items_display'] ?? '';
        
        // Get kitchen type and status using Kitchen Service (matching non-bulk version)
        $kitchen_service = new Orders_Jet_Kitchen_Service();
        $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
        $kitchen_type = $kitchen_status['kitchen_type'];
        
        // Map WooCommerce status to Orders Master status
        $master_status = $this->map_to_master_status($wc_status, $oj_status);
        
        return array(
            'id' => $order_id,
            'number' => $order_number,
            'wc_status' => $wc_status,
            'oj_status' => $oj_status,
            'master_status' => $master_status,
            'table_number' => $table_number,
            'order_type' => $order_type,
            'customer_name' => $customer_name,
            'total' => $total,
            'total_formatted' => wc_price($total),
            'date_created' => $date_created,
            'date_formatted' => $date_created->format('M d, H:i'),
            'time_ago' => human_time_diff($date_created->getTimestamp(), time()),
            'assigned_waiter_id' => $assigned_waiter_id,
            'waiter_name' => $waiter_name,
            'item_count' => $item_count,
            'items_display' => $items_display,
            'kitchen_type' => $kitchen_type,
            'kitchen_status' => $kitchen_status, // Full kitchen status with food_ready and beverage_ready flags
            'order_object' => $order // Keep reference for advanced operations
        );
    }
    
    /**
     * Simplified kitchen type determination for bulk processing
     */
    private function determine_kitchen_type_bulk($order_type) {
        // For bulk processing, use simplified logic based on order type
        // This avoids the need to analyze individual items
        switch ($order_type) {
            case 'delivery':
            case 'takeaway':
                return 'mixed'; // Assume mixed for takeaway/delivery
            case 'dinein':
            default:
                return 'food'; // Assume food for dine-in
        }
    }
    
    /**
     * Get optimized filter counts without heavy order processing
     * Performance optimization: Use direct status queries instead of processing all orders
     */
    private function get_optimized_filter_counts($base_query_args, $user_role, $user_id) {
        // Cache key for performance
        $cache_key = "oj_master_counts_{$user_role}_{$user_id}_" . md5(serialize($base_query_args));
        $cached_counts = get_transient($cache_key);
        
        if ($cached_counts !== false) {
            return $cached_counts;
        }
        
        // Initialize counters
        $counts = array(
            'all' => 0,
            'active' => 0,      // pending + processing
            'ready' => 0,       // pending (ready for pickup/delivery)
            'completed' => 0,   // completed
            'dinein' => 0,
            'takeaway' => 0,
            'delivery' => 0,
            'food_kitchen' => 0,
            'beverage_kitchen' => 0
        );
        
        // Get counts by status using OPTIMIZED queries
        $count_args = $base_query_args;
        $count_args['return'] = 'ids';
        $count_args['limit'] = 1;
        $count_args['paginate'] = true;
        
        // Count by WooCommerce status - OPTIMIZED
        $status_queries = array(
            'pending' => array_merge($count_args, array('status' => array('wc-pending'))),
            'processing' => array_merge($count_args, array('status' => array('wc-processing'))),
            'completed' => array_merge($count_args, array('status' => array('wc-completed', 'wc-refunded', 'wc-cancelled')))
        );
        
        $status_counts = array();
        foreach ($status_queries as $status => $query_args) {
            $result = wc_get_orders($query_args);
            $status_counts[$status] = $result->total;
        }
        
        // Map to our filter categories (FIXED: No double-counting)
        $counts['active'] = $status_counts['processing']; // Only processing orders are "active"
        $counts['ready'] = $status_counts['pending']; // Only pending orders are "ready"
        $counts['completed'] = $status_counts['completed'];
        $counts['all'] = array_sum($status_counts);
        
        // For order type counts (dinein/takeaway/delivery), we need a bit more work but still optimized
        if ($counts['all'] > 0) {
            $type_counts = $this->get_order_type_counts($base_query_args);
            $counts = array_merge($counts, $type_counts);
        }
        
        // Cache for 60 seconds
        set_transient($cache_key, $counts, 60);
        
        return $counts;
    }
    
    /**
     * Get order type counts (dinein/takeaway/delivery) optimized
     */
    private function get_order_type_counts($base_query_args) {
        $type_counts = array(
            'dinein' => 0,
            'takeaway' => 0,
            'delivery' => 0,
            'food_kitchen' => 0,
            'beverage_kitchen' => 0
        );
        
        // Get order count first - OPTIMIZED
        $count_args = $base_query_args;
        $count_args['return'] = 'ids';
        $count_args['limit'] = 1;
        $count_args['paginate'] = true;
        $count_result = wc_get_orders($count_args);
        
        if ($count_result->total === 0) {
            return $type_counts;
        }
        
        // Now get actual order IDs for processing (limit to reasonable number)
        $count_args['limit'] = min(1000, $count_result->total); // Limit to 1000 for performance
        $count_args['paginate'] = false;
        $order_ids = wc_get_orders($count_args);
        
        if (empty($order_ids)) {
            return $type_counts;
        }
        
        // Use direct meta query for order types (much faster than loading full orders)
        global $wpdb;
        
        $ids_placeholder = implode(',', array_fill(0, count($order_ids), '%d'));
        $sql = $wpdb->prepare("
            SELECT meta_value, COUNT(*) as count 
            FROM {$wpdb->postmeta} 
            WHERE post_id IN ($ids_placeholder) 
            AND meta_key = '_oj_order_type' 
            GROUP BY meta_value
        ", $order_ids);
        
        $results = $wpdb->get_results($sql);
        
        foreach ($results as $row) {
            $type = $row->meta_value;
            $count = intval($row->count);
            
            if (isset($type_counts[$type])) {
                $type_counts[$type] = $count;
            }
        }
        
        // For kitchen types, we'd need item analysis, but for now skip to maintain performance
        // These can be calculated on-demand if needed
        
        return $type_counts;
    }
    
    /**
     * Clear filter counts cache when orders are updated
     * Call this when orders change to ensure real-time accuracy
     */
    public function clear_filter_counts_cache($user_role = null, $user_id = null) {
        if ($user_role === null) {
            // Clear all cached counts for all roles
            $roles = array('oj_manager', 'oj_waiter', 'oj_kitchen');
            foreach ($roles as $role) {
                $pattern = "oj_master_counts_{$role}_*";
                $this->clear_transients_by_pattern($pattern);
            }
        } else {
            // Clear specific user's cache
            $pattern = "oj_master_counts_{$user_role}_{$user_id}_*";
            $this->clear_transients_by_pattern($pattern);
        }
    }
    
    /**
     * Helper function to clear transients by pattern
     */
    private function clear_transients_by_pattern($pattern) {
        global $wpdb;
        
        $pattern = str_replace('*', '%', $pattern);
        $sql = $wpdb->prepare("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            AND option_name LIKE '_transient_%'
        ", "_transient_{$pattern}");
        
        $wpdb->query($sql);
        
        // Also clear timeout entries
        $timeout_sql = $wpdb->prepare("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE %s 
            AND option_name LIKE '_transient_timeout_%'
        ", "_transient_timeout_{$pattern}");
        
        $wpdb->query($timeout_sql);
    }

    /**
     * Get overview statistics for the Orders Overview page
     * 
     * @param string $user_role User's role
     * @param int $user_id User's ID
     * @return array Statistics array with all metrics
     */
    public function get_overview_statistics($user_role, $user_id) {
        // Cache key for performance
        $cache_key = "oj_overview_stats_{$user_role}_{$user_id}";
        $cached_stats = get_transient($cache_key);
        
        if ($cached_stats !== false) {
            return $cached_stats;
        }
        
        // Get today's date range (from midnight to now)
        $today_start = strtotime('today midnight');
        $today_end = strtotime('tomorrow midnight') - 1;
        $yesterday_start = strtotime('yesterday midnight');
        $yesterday_end = strtotime('today midnight') - 1;
        
        // Base query args
        $base_args = array(
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
            'paginate' => false,
            'limit' => -1
        );
        
        // Today's orders
        $today_args = array_merge($base_args, array(
            'date_created' => $today_start . '...' . $today_end
        ));
        $today_orders = wc_get_orders($today_args);
        $today_count = count($today_orders);
        $today_revenue = 0;
        
        foreach ($today_orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $today_revenue += (float) $order->get_total();
            }
        }
        
        // Yesterday's orders for comparison
        $yesterday_args = array_merge($base_args, array(
            'date_created' => $yesterday_start . '...' . $yesterday_end
        ));
        $yesterday_orders = wc_get_orders($yesterday_args);
        $yesterday_count = count($yesterday_orders);
        
        // Calculate percentage change
        $today_change = null;
        if ($yesterday_count > 0) {
            $today_change = round((($today_count - $yesterday_count) / $yesterday_count) * 100);
        }
        
        // In Progress orders (processing status)
        $in_progress_args = array_merge($base_args, array(
            'status' => array('wc-processing', 'wc-pending')
        ));
        $in_progress_orders = wc_get_orders($in_progress_args);
        $in_progress_count = count($in_progress_orders);
        
        // Completed orders today
        $completed_args = array_merge($base_args, array(
            'status' => array('wc-completed'),
            'date_created' => $today_start . '...' . $today_end
        ));
        $completed_orders = wc_get_orders($completed_args);
        $completed_count = count($completed_orders);
        $completed_revenue = 0;
        
        foreach ($completed_orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $completed_revenue += (float) $order->get_total();
            }
        }
        
        // Cancelled orders today
        $cancelled_args = array_merge($base_args, array(
            'status' => array('wc-cancelled', 'wc-failed'),
            'date_created' => $today_start . '...' . $today_end
        ));
        $cancelled_orders = wc_get_orders($cancelled_args);
        $cancelled_count = count($cancelled_orders);
        
        // Count refunded orders
        $refunded_count = 0;
        foreach ($cancelled_orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && $order->get_total_refunded() > 0) {
                $refunded_count++;
            }
        }
        
        // Refund requests (orders with refund meta or refunded status)
        $refund_requests_args = array_merge($base_args, array(
            'status' => array('wc-refunded')
        ));
        $refund_requests = wc_get_orders($refund_requests_args);
        $refund_requests_count = count($refund_requests);
        
        // Unfulfilled orders (pending + processing)
        $unfulfilled_count = $in_progress_count;
        
        // Ready orders (on-hold status or ready meta)
        $ready_args = array_merge($base_args, array(
            'status' => array('wc-on-hold')
        ));
        $ready_orders = wc_get_orders($ready_args);
        $ready_count = count($ready_orders);
        
        // Quick stats - weekly orders (all statuses)
        $week_start = strtotime('monday this week midnight');
        $weekly_args = array_merge($base_args, array(
            'date_created' => $week_start . '...' . $today_end,
            'status' => array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed')
        ));
        $weekly_orders = wc_get_orders($weekly_args);
        $weekly_count = count($weekly_orders);
        
        // Calculate weekly revenue
        $weekly_revenue = 0;
        foreach ($weekly_orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && in_array($order->get_status(), array('completed', 'processing'))) {
                $weekly_revenue += (float) $order->get_total();
            }
        }
        
        // Average order value (based on all valid orders today - excluding cancelled/failed)
        $valid_today_orders = array();
        $valid_today_revenue = 0;
        foreach ($today_orders as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && !in_array($order->get_status(), array('cancelled', 'failed', 'refunded'))) {
                $valid_today_orders[] = $order_id;
                $valid_today_revenue += (float) $order->get_total();
            }
        }
        $valid_today_count = count($valid_today_orders);
        $avg_order_value = $valid_today_count > 0 ? $valid_today_revenue / $valid_today_count : 0;
        
        // Completion rate (completed orders / all orders today excluding cancelled/failed)
        $completion_rate = $valid_today_count > 0 ? round(($completed_count / $valid_today_count) * 100, 1) : 0;
        
        // Build statistics array
        $stats = array(
            'today' => array(
                'count' => $today_count,
                'revenue' => $today_revenue,
                'change' => $today_change
            ),
            'in_progress' => array(
                'count' => $in_progress_count
            ),
            'completed' => array(
                'count' => $completed_count,
                'revenue' => $completed_revenue
            ),
            'cancelled' => array(
                'count' => $cancelled_count,
                'refunded' => $refunded_count
            ),
            'unfulfilled' => array(
                'count' => $unfulfilled_count
            ),
            'ready' => array(
                'count' => $ready_count
            ),
            'refund_requests' => array(
                'count' => $refund_requests_count
            ),
            'quick_stats' => array(
                'avg_order_value' => round($avg_order_value, 2),
                'weekly_orders' => $weekly_count,
                'weekly_revenue' => round($weekly_revenue, 2),
                'completion_rate' => $completion_rate,
                'valid_orders_today' => $valid_today_count
            )
        );
        
        // Cache for 2 minutes (120 seconds)
        set_transient($cache_key, $stats, 120);
        
        return $stats;
    }
    
    /**
     * AJAX handler for getting overview data (for auto-refresh)
     */
    public function ajax_get_overview_data() {
        // Verify nonce
        check_ajax_referer('oj_overview_nonce', 'nonce');
        
        // Check permissions
        if (!current_user_can('read')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'orders-jet')));
        }
        
        // Get user info
        $user_role = oj_get_user_role() ?: (current_user_can('manage_options') ? 'oj_manager' : '');
        $user_id = get_current_user_id();
        
        // Get fresh statistics
        $stats = $this->get_overview_statistics($user_role, $user_id);
        
        // Add timestamp
        $stats['timestamp'] = current_time('H:i:s');
        
        // Return success with data
        wp_send_json_success($stats);
    }

    /**
     * Update filter counts for Orders Master
     */
    private function update_orders_master_filter_counts(&$counts, $order_data) {
        $status = $order_data['master_status'];
        $order_type = $order_data['order_type'];
        $kitchen_type = $order_data['kitchen_type'];
        
        // Count all orders
        $counts['all']++;
        
        // Count by master status
        if ($status === 'active') {
            $counts['active']++;
        } elseif ($status === 'ready') {
            $counts['ready']++;
        } elseif ($status === 'completed') {
            $counts['completed']++;
        }
        
        // Count by order type
        if ($order_type === 'dinein') {
            $counts['dinein']++;
        } elseif ($order_type === 'takeaway') {
            $counts['takeaway']++;
        } elseif ($order_type === 'delivery') {
            $counts['delivery']++;
        }
        
        // Count by kitchen type
        if ($kitchen_type === 'food' || $kitchen_type === 'mixed') {
            $counts['food_kitchen']++;
        }
        if ($kitchen_type === 'beverages' || $kitchen_type === 'mixed') {
            $counts['beverage_kitchen']++;
        }
    }
    
    /**
     * Map WooCommerce status to Orders Master status
     */
    private function map_to_master_status($wc_status, $oj_status) {
        // Orders Master uses simplified status categories
        if ($wc_status === 'completed') {
            return 'completed';
        } elseif ($wc_status === 'processing') {
            return 'active'; // Currently being prepared
        } elseif ($wc_status === 'pending') {
            return 'ready'; // Ready for pickup/delivery/serving
        }
        
        // Fallback based on OJ status
        if ($oj_status === 'completed') {
            return 'completed';
        } elseif ($oj_status === 'processing' || $oj_status === 'cooking') {
            return 'active';
        } else {
            return 'ready';
        }
    }
    
    /**
     * Apply status filter to query arguments
     * Task 1.2.3.4 - AJAX Filters Preparation
     */
    private function apply_status_filter($query_args, $filter) {
        switch ($filter) {
            case 'active':
                // Active orders are currently being processed
                $query_args['status'] = array('wc-processing');
                break;
            case 'ready':
                // Ready orders are pending (ready for pickup/delivery/serving)
                $query_args['status'] = array('wc-pending');
                break;
            case 'completed':
                // Completed orders
                $query_args['status'] = array('wc-completed');
                break;
            case 'all':
            default:
                // All orders (default behavior)
                $query_args['status'] = array('wc-pending', 'wc-processing', 'wc-completed');
                break;
        }
        
        return $query_args;
    }
    
    /**
     * Apply search filter to query arguments (Task 1.2.3.5.6)
     * FIXED: Handle both HPOS and Legacy WooCommerce storage + Two post types (orders + tables)
     */
    private function apply_search_filter($query_args, $search) {
        $search = trim($search);
        if (empty($search)) {
            return $query_args;
        }
        
        global $wpdb;
        $search_like = '%' . $wpdb->esc_like($search) . '%';
        
        // Check if WooCommerce HPOS is enabled
        if ($this->is_hpos_enabled()) {
            $matching_ids = $this->search_hpos_orders($search, $search_like);
        } else {
            $matching_ids = $this->search_legacy_orders($search, $search_like);
        }
        
        // Also search for orders linked to tables (both HPOS and Legacy)
        $table_linked_ids = $this->search_table_linked_orders($search, $search_like);
        
        // Combine all matching IDs
        $all_matching_ids = array_unique(array_merge($matching_ids, $table_linked_ids));
        
        // Log search results for debugging if needed
        if (defined('WP_DEBUG') && WP_DEBUG) {
            oj_debug_log("Orders Search: '{$search}' found " . count($all_matching_ids) . " matches", 'SEARCH');
        }
        
        if (empty($all_matching_ids)) {
            // No matches found, return empty result set
            $query_args['post__in'] = array(0);
        } else {
            // Limit to matching IDs
            $query_args['post__in'] = $all_matching_ids;
        }
        
        return $query_args;
    }
    
    /**
     * Check if WooCommerce HPOS (High-Performance Order Storage) is enabled
     */
    private function is_hpos_enabled() {
        if (!class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            return false;
        }
        
        try {
            $controller = wc_get_container()->get('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController');
            return $controller->custom_orders_table_usage_is_enabled();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Search orders in HPOS storage (wp_wc_orders + wp_wc_orders_meta)
     */
    private function search_hpos_orders($search, $search_like) {
        global $wpdb;
        
        // For numeric searches, try exact ID match first
        if (is_numeric($search)) {
            $exact_match = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}wc_orders WHERE id = %d",
                intval($search)
            ));
            if ($exact_match) {
                return array($exact_match);
            }
        }
        
        // Search in HPOS tables
        $sql = "
            SELECT DISTINCT o.id 
            FROM {$wpdb->prefix}wc_orders o
            LEFT JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id
            WHERE o.type = 'shop_order'
            AND (
                o.id = %s
                OR o.billing_first_name LIKE %s
                OR o.billing_last_name LIKE %s
                OR o.billing_email LIKE %s
                OR om.meta_key = '_order_number' AND om.meta_value LIKE %s
                OR om.meta_key = '_order_key' AND om.meta_value LIKE %s
                OR om.meta_key = '_oj_table_number' AND om.meta_value LIKE %s
                OR om.meta_key = '_table_number' AND om.meta_value LIKE %s
            )
            LIMIT 1000
        ";
        
        $matching_ids = $wpdb->get_col($wpdb->prepare($sql, 
            $search, $search_like, $search_like, $search_like, 
            $search_like, $search_like, $search_like, $search_like
        ));
        
        return $matching_ids;
    }
    
    /**
     * Search orders in Legacy storage (wp_posts + wp_postmeta)
     */
    private function search_legacy_orders($search, $search_like) {
        global $wpdb;
        
        // For numeric searches, try exact ID match first
        if (is_numeric($search)) {
            $exact_match = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND ID = %d",
                intval($search)
            ));
            if ($exact_match) {
                return array($exact_match);
            }
        }
        
        // Search in Legacy tables
        $sql = "
            SELECT DISTINCT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND (
                p.ID = %s
                OR p.post_title LIKE %s
                OR p.post_excerpt LIKE %s
                OR p.post_content LIKE %s
                OR pm.meta_key = '_order_number' AND pm.meta_value LIKE %s
                OR pm.meta_key = '_order_key' AND pm.meta_value LIKE %s
                OR pm.meta_key = '_oj_table_number' AND pm.meta_value LIKE %s
                OR pm.meta_key = '_table_number' AND pm.meta_value LIKE %s
                OR pm.meta_key = '_billing_first_name' AND pm.meta_value LIKE %s  
                OR pm.meta_key = '_billing_last_name' AND pm.meta_value LIKE %s
                OR pm.meta_key = '_billing_email' AND pm.meta_value LIKE %s
            )
            LIMIT 1000
        ";
        
        $matching_ids = $wpdb->get_col($wpdb->prepare($sql, 
            $search, $search_like, $search_like, $search_like, $search_like, 
            $search_like, $search_like, $search_like, $search_like, $search_like, $search_like
        ));
        
        return $matching_ids;
    }
    
    /**
     * Search for orders linked to tables (handles table searches like "T25")
     */
    private function search_table_linked_orders($search, $search_like) {
        global $wpdb;
        
        // First, find matching table posts
        $table_sql = "
            SELECT p.ID, pm.meta_value as table_number
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_oj_table_number'
            WHERE p.post_type = 'oj_table'
            AND (
                p.post_title LIKE %s
                OR pm.meta_value LIKE %s
            )
        ";
        
        $matching_tables = $wpdb->get_results($wpdb->prepare($table_sql, $search_like, $search_like));
        
        if (empty($matching_tables)) {
            return array();
        }
        
        // Now find orders linked to these tables
        $table_numbers = array();
        foreach ($matching_tables as $table) {
            if (!empty($table->table_number)) {
                $table_numbers[] = $table->table_number;
            }
        }
        
        if (empty($table_numbers)) {
            return array();
        }
        
        // Search for orders with these table numbers
        $table_numbers_placeholders = implode(',', array_fill(0, count($table_numbers), '%s'));
        
        if ($this->is_hpos_enabled()) {
            // HPOS: Search in wp_wc_orders_meta
            $order_sql = "
                SELECT DISTINCT om.order_id
                FROM {$wpdb->prefix}wc_orders_meta om
                JOIN {$wpdb->prefix}wc_orders o ON om.order_id = o.id
                WHERE o.type = 'shop_order'
                AND om.meta_key = '_oj_table_number'
                AND om.meta_value IN ($table_numbers_placeholders)
            ";
        } else {
            // Legacy: Search in wp_postmeta
            $order_sql = "
                SELECT DISTINCT pm.post_id
                FROM {$wpdb->postmeta} pm
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.post_type = 'shop_order'
                AND pm.meta_key = '_oj_table_number'
                AND pm.meta_value IN ($table_numbers_placeholders)
            ";
        }
        
        $linked_order_ids = $wpdb->get_col($wpdb->prepare($order_sql, ...$table_numbers));
        
        return $linked_order_ids;
    }
    
    /**
     * Determine kitchen type from order items
     */
    private function determine_kitchen_type($items) {
        $has_food = false;
        $has_beverages = false;
        
        foreach ($items as $item) {
            $product_id = $item->get_product_id();
            $product = wc_get_product($product_id);
            
            if ($product) {
                // Check product categories or meta to determine type
                $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'slugs'));
                
                foreach ($categories as $category_slug) {
                    if (strpos($category_slug, 'beverage') !== false || 
                        strpos($category_slug, 'drink') !== false ||
                        strpos($category_slug, 'coffee') !== false) {
                        $has_beverages = true;
                    } else {
                        $has_food = true;
                    }
                }
            }
        }
        
        if ($has_food && $has_beverages) {
            return 'mixed';
        } elseif ($has_beverages) {
            return 'beverages';
        } else {
            return 'food';
        }
    }
    
    /**
     * Generate test orders for development
     * Now supports batch generation with 'count' parameter
     */
    public function ajax_generate_test_orders() {
        // Security checks
        check_ajax_referer('oj_dev_tools', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get count from request, default to 20 for backward compatibility
        $count = isset($_POST['count']) ? intval($_POST['count']) : 20;
        $count = max(1, min($count, 20)); // Limit between 1 and 20
        
        $generated = 0;
        $errors = array();
        
        // Sample customer data
        $customers = array(
            array('first_name' => 'Ahmed', 'last_name' => 'Hassan', 'phone' => '0100123456'),
            array('first_name' => 'Sara', 'last_name' => 'Ali', 'phone' => '0101234567'),
            array('first_name' => 'Mohamed', 'last_name' => 'Ibrahim', 'phone' => '0102345678'),
            array('first_name' => 'Fatma', 'last_name' => 'Mahmoud', 'phone' => '0103456789'),
            array('first_name' => 'Khaled', 'last_name' => 'Omar', 'phone' => '0104567890'),
        );
        
        // Get existing products
        $product_ids = wc_get_products(array('limit' => 10, 'return' => 'ids'));
        if (empty($product_ids)) {
            wp_send_json_error(array('message' => 'No products found. Please create some products first.'));
        }
        
        try {
            // Generate 5 Processing Orders (Active/Kitchen)
            for ($i = 0; $i < 5; $i++) {
                $order = wc_create_order();
                if (is_wp_error($order)) {
                    $errors[] = 'Failed to create order: ' . $order->get_error_message();
                    continue;
                }
                
                $customer = $customers[array_rand($customers)];
                
                // Set customer info
                $order->set_billing_first_name($customer['first_name']);
                $order->set_billing_last_name($customer['last_name']);
                $order->set_billing_phone($customer['phone']);
                
                // Add random products
                $num_items = rand(1, 4);
                for ($j = 0; $j < $num_items; $j++) {
                    $product_id = $product_ids[array_rand($product_ids)];
                    $order->add_product(wc_get_product($product_id), rand(1, 3));
                }
                
                // Set status
                $order->set_status('processing');
                
                // Add table number (50% chance)
                if (rand(0, 1)) {
                    $table_num = 'T' . str_pad((string)rand(1, 20), 2, '0', STR_PAD_LEFT);
                    $order->update_meta_data('_oj_table_number', $table_num);
                }
                
                // Add kitchen type
                $kitchen_types = array('food_only', 'beverages_only', 'mixed');
                $order->update_meta_data('_oj_kitchen_type', $kitchen_types[array_rand($kitchen_types)]);
                
                // Set random time today
                $time_offset = rand(1, 8) * 3600; // 1-8 hours ago
                $order->set_date_created(time() - $time_offset);
                
                $order->calculate_totals();
                $order->save();
                $generated++;
            }
        
            // Generate 3 Pending Payment Orders (Ready)
            for ($i = 0; $i < 3; $i++) {
                $order = wc_create_order();
                if (is_wp_error($order)) {
                    $errors[] = 'Failed to create ready order: ' . $order->get_error_message();
                    continue;
                }
                
                $customer = $customers[array_rand($customers)];
                
                $order->set_billing_first_name($customer['first_name']);
                $order->set_billing_last_name($customer['last_name']);
                $order->set_billing_phone($customer['phone']);
                
                $num_items = rand(2, 5);
                for ($j = 0; $j < $num_items; $j++) {
                    $product_id = $product_ids[array_rand($product_ids)];
                    $order->add_product(wc_get_product($product_id), rand(1, 2));
                }
                
                $order->set_status('pending-payment');
                
                // Add table (70% chance for ready orders)
                if (rand(0, 9) < 7) {
                    $table_num = 'T' . str_pad((string)rand(1, 20), 2, '0', STR_PAD_LEFT);
                    $order->update_meta_data('_oj_table_number', $table_num);
                }
                
                $time_offset = rand(1, 4) * 3600;
                $order->set_date_created(time() - $time_offset);
                
                $order->calculate_totals();
                $order->save();
                $generated++;
            }
            
            // Generate 10 Completed Orders
            for ($i = 0; $i < 10; $i++) {
                $order = wc_create_order();
                if (is_wp_error($order)) {
                    $errors[] = 'Failed to create completed order: ' . $order->get_error_message();
                    continue;
                }
                
                $customer = $customers[array_rand($customers)];
                
                $order->set_billing_first_name($customer['first_name']);
                $order->set_billing_last_name($customer['last_name']);
                $order->set_billing_phone($customer['phone']);
                
                $num_items = rand(1, 6);
                for ($j = 0; $j < $num_items; $j++) {
                    $product_id = $product_ids[array_rand($product_ids)];
                    $order->add_product(wc_get_product($product_id), rand(1, 4));
                }
                
                $order->set_status('completed');
                
                // Add table (40% chance for completed)
                if (rand(0, 9) < 4) {
                    $table_num = 'T' . str_pad((string)rand(1, 20), 2, '0', STR_PAD_LEFT);
                    $order->update_meta_data('_oj_table_number', $table_num);
                }
                
                // Payment method
                $payment_methods = array('cash', 'card', 'bacs');
                $order->set_payment_method($payment_methods[array_rand($payment_methods)]);
                
                $time_offset = rand(4, 24) * 3600; // 4-24 hours ago
                $order->set_date_created(time() - $time_offset);
                
                $order->calculate_totals();
                $order->save();
                $generated++;
            }
            
            // Generate 2 Mixed Kitchen Orders with partial ready states
            for ($i = 0; $i < 2; $i++) {
                $order = wc_create_order();
                if (is_wp_error($order)) {
                    $errors[] = 'Failed to create mixed kitchen order: ' . $order->get_error_message();
                    continue;
                }
                
                $customer = $customers[array_rand($customers)];
                
                $order->set_billing_first_name($customer['first_name']);
                $order->set_billing_last_name($customer['last_name']);
                
                $order->add_product(wc_get_product($product_ids[0]), 2);
                $order->add_product(wc_get_product($product_ids[1]), 1);
                
                $order->set_status('processing');
                $order->update_meta_data('_oj_kitchen_type', 'mixed');
                
                // One kitchen ready, one not
                if ($i === 0) {
                    $order->update_meta_data('_oj_food_kitchen_ready', 'yes');
                    $order->update_meta_data('_oj_beverage_kitchen_ready', 'no');
                } else {
                    $order->update_meta_data('_oj_food_kitchen_ready', 'no');
                    $order->update_meta_data('_oj_beverage_kitchen_ready', 'yes');
                }
                
                $table_num = 'T' . str_pad((string)rand(1, 20), 2, '0', STR_PAD_LEFT);
                $order->update_meta_data('_oj_table_number', $table_num);
                
                $order->calculate_totals();
                $order->save();
                $generated++;
            }
            
            $message = "Successfully generated {$generated} test orders!";
            if (!empty($errors)) {
                $message .= "\nWarnings: " . implode(', ', $errors);
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'generated' => $generated,
                'errors' => $errors
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error generating orders: ' . $e->getMessage(),
                'generated' => $generated
            ));
        }
    }
    
    /**
     * Clear all orders - SIMPLE AND DIRECT
     * Works with both old (wp_posts) and new (HPOS) WooCommerce storage
     */
    public function ajax_clear_all_orders() {
        check_ajax_referer('oj_dev_tools', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Use WooCommerce function to get ALL orders (works with both storage systems)
        $order_ids = wc_get_orders(array(
            'limit' => -1,
            'status' => 'any',
            'return' => 'ids'
        ));
        
        if (empty($order_ids)) {
            wp_send_json_success(array('message' => 'No orders found!'));
            return;
        }
        
        $total = count($order_ids);
        
        // Delete ALL orders using WooCommerce
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->delete(true); // Force delete
            }
        }
        
        wp_send_json_success(array(
            'message' => "Successfully deleted ALL {$total} orders!"
        ));
    }
    
    /**
     * Get all order IDs for batch processing
     * Returns array of all order IDs to be processed in batches on frontend
     */
    public function ajax_get_all_order_ids() {
        check_ajax_referer('oj_dev_tools', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get ALL order IDs
        $order_ids = wc_get_orders(array(
            'limit' => -1,
            'status' => 'any',
            'return' => 'ids'
        ));
        
        wp_send_json_success(array(
            'order_ids' => $order_ids,
            'total' => count($order_ids)
        ));
    }
    
    /**
     * Delete a batch of orders
     * Accepts array of order IDs and deletes them
     */
    public function ajax_clear_orders_batch() {
        check_ajax_referer('oj_dev_tools', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get order IDs from POST
        $order_ids = isset($_POST['order_ids']) ? array_map('intval', $_POST['order_ids']) : array();
        
        if (empty($order_ids)) {
            wp_send_json_error(array('message' => 'No order IDs provided'));
        }
        
        $deleted = 0;
        
        // Delete each order in the batch
        foreach ($order_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order->delete(true); // Force delete
                $deleted++;
            }
        }
        
        // Clear cache after deleting orders
        $this->clear_orders_master_v2_cache();
        
        wp_send_json_success(array(
            'deleted' => $deleted,
            'message' => "Deleted {$deleted} orders in this batch"
        ));
    }
    
    /**
     * Generate a batch of test orders
     * Creates orders based on proportional distribution
     */
    public function ajax_generate_orders_batch() {
        check_ajax_referer('oj_dev_tools', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get count from request, default to 5
        $count = isset($_POST['count']) ? intval($_POST['count']) : 5;
        $count = max(1, min($count, 10)); // Limit between 1 and 10 per batch
        
        $generated = 0;
        $errors = array();
        
        // Sample customer data
        $customers = array(
            array('first_name' => 'Ahmed', 'last_name' => 'Hassan', 'phone' => '0100123456'),
            array('first_name' => 'Sara', 'last_name' => 'Ali', 'phone' => '0101234567'),
            array('first_name' => 'Mohamed', 'last_name' => 'Ibrahim', 'phone' => '0102345678'),
            array('first_name' => 'Fatma', 'last_name' => 'Mahmoud', 'phone' => '0103456789'),
            array('first_name' => 'Khaled', 'last_name' => 'Omar', 'phone' => '0104567890'),
        );
        
        // Get existing products
        $product_ids = wc_get_products(array('limit' => 10, 'return' => 'ids'));
        
        if (empty($product_ids)) {
            wp_send_json_error(array('message' => 'No products found. Please create some products first.'));
        }
        
        // Order distribution: 25% processing, 15% pending-payment, 50% completed, 10% mixed special
        $statuses = array('processing', 'pending-payment', 'completed');
        
        try {
            for ($i = 0; $i < $count; $i++) {
                $order = wc_create_order();
                if (is_wp_error($order)) {
                    $errors[] = 'Failed to create order: ' . $order->get_error_message();
                    continue;
                }
                
                $customer = $customers[array_rand($customers)];
                
                // Set customer info
                $order->set_billing_first_name($customer['first_name']);
                $order->set_billing_last_name($customer['last_name']);
                $order->set_billing_phone($customer['phone']);
                
                // Add random products
                $num_items = rand(1, 4);
                for ($j = 0; $j < $num_items; $j++) {
                    $product_id = $product_ids[array_rand($product_ids)];
                    $order->add_product(wc_get_product($product_id), rand(1, 3));
                }
                
                // Set order type with proper distribution and meta fields
                $order_type_rand = rand(1, 100);
                if ($order_type_rand <= 40) {
                    $order_method = 'dinein';
                    // Dine-in orders get table numbers
                    $table_num = 'T' . str_pad((string)rand(1, 20), 2, '0', STR_PAD_LEFT);
                    $order->update_meta_data('_oj_table_number', $table_num);
                } elseif ($order_type_rand <= 75) {
                    $order_method = 'takeaway';
                    // Takeaway orders don't get table numbers
                } else {
                    $order_method = 'delivery';
                    // Delivery orders get delivery addresses
                    $delivery_addresses = array(
                        'شارع التحرير، المعادي، القاهرة',
                        'شارع الهرم، الجيزة',
                        'كورنيش النيل، الزمالك، القاهرة',
                        'شارع مصر الجديدة، هليوبوليس',
                        'شارع الجامعة، المهندسين، الجيزة'
                    );
                    $order->update_meta_data('_oj_delivery_address', $delivery_addresses[array_rand($delivery_addresses)]);
                }
                
                // Set the order method meta field (used by our filters)
                $order->update_meta_data('exwf_odmethod', $order_method);
                
                // Set status with distribution
                $status_rand = rand(1, 100);
                if ($status_rand <= 25) {
                    $status = 'processing';
                } elseif ($status_rand <= 40) {
                    $status = 'pending-payment';
                } else {
                    $status = 'completed';
                }
                $order->set_status($status);
                
                // Add kitchen type (using correct values for our filters)
                $kitchen_types = array('food', 'beverages', 'mixed');
                $order->update_meta_data('_oj_kitchen_type', $kitchen_types[array_rand($kitchen_types)]);
                
                // Set date with multi-day distribution for date range testing
                $date_rand = rand(1, 100);
                if ($date_rand <= 40) {
                    // 40% today (1-8 hours ago)
                    $hours_ago = rand(1, 8);
                    $time_offset = $hours_ago * 3600;
                } elseif ($date_rand <= 65) {
                    // 25% yesterday (24-32 hours ago)
                    $hours_ago = rand(24, 32);
                    $time_offset = $hours_ago * 3600;
                } elseif ($date_rand <= 85) {
                    // 20% this week (2-7 days ago)
                    $days_ago = rand(2, 7);
                    $time_offset = $days_ago * 24 * 3600;
                } elseif ($date_rand <= 95) {
                    // 10% last week (7-14 days ago)
                    $days_ago = rand(7, 14);
                    $time_offset = $days_ago * 24 * 3600;
                } else {
                    // 5% last month (15-30 days ago)
                    $days_ago = rand(15, 30);
                    $time_offset = $days_ago * 24 * 3600;
                }
                
                $order->set_date_created(time() - $time_offset);
                
                $order->calculate_totals();
                $order->save();
                $generated++;
            }
            
            // Clear cache after generating orders
            $this->clear_orders_master_v2_cache();
            
            wp_send_json_success(array(
                'message' => "Generated {$generated} orders in this batch",
                'generated' => $generated,
                'errors' => $errors
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Error generating orders: ' . $e->getMessage(),
                'generated' => $generated
            ));
        }
    }
    
    /**
     * AJAX handler: Mark order as paid (Orders Master V2)
     */
    public function ajax_mark_order_paid() {
        // Security check
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if (!$order_id) {
            wp_send_json_error(array('message' => 'Invalid order ID'));
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(array('message' => 'Order not found'));
        }
        
        try {
            // Mark order as paid by updating meta
            $order->update_meta_data('_oj_payment_confirmed', 'yes');
            $order->update_meta_data('_oj_payment_confirmed_date', current_time('mysql'));
            $order->save();
            
            // Clear Orders Master V2 filter counts cache
            $this->clear_orders_master_v2_cache();
            
            wp_send_json_success(array(
                'message' => 'Order marked as paid',
                'order_id' => $order_id
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error: ' . $e->getMessage()));
        }
    }
    
    /**
     * Clear Orders Master V2 filter counts cache
     * Called after any order status change to ensure fresh counts
     */
    private function clear_orders_master_v2_cache() {
        delete_transient('oj_master_v2_filter_counts');
    }
    
    /**
     * AJAX handler: Assign table to waiter
     */
    public function ajax_assign_table() {
        // Security check
        check_ajax_referer('oj_table_assignment', 'nonce');
        
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'orders-jet')));
        }
        
        $table_number = isset($_POST['table_number']) ? sanitize_text_field($_POST['table_number']) : '';
        $waiter_id = isset($_POST['waiter_id']) ? intval($_POST['waiter_id']) : 0;
        
        if (empty($table_number)) {
            wp_send_json_error(array('message' => __('Table number is required', 'orders-jet')));
        }
        
        if (empty($waiter_id)) {
            wp_send_json_error(array('message' => __('Waiter is required', 'orders-jet')));
        }
        
        try {
            // Get handler instance
            $handler_factory = new Orders_Jet_Handler_Factory(
                new Orders_Jet_Tax_Service(),
                new Orders_Jet_Kitchen_Service(),
                new Orders_Jet_Notification_Service()
            );
            $assignment_handler = $handler_factory->get_table_assignment_handler();
            
            // Assign table
            $result = $assignment_handler->assign_table($table_number, $waiter_id);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error: ', 'orders-jet') . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler: Unassign table from waiter
     */
    public function ajax_unassign_table() {
        // Security check
        check_ajax_referer('oj_table_assignment', 'nonce');
        
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'orders-jet')));
        }
        
        $table_number = isset($_POST['table_number']) ? sanitize_text_field($_POST['table_number']) : '';
        
        if (empty($table_number)) {
            wp_send_json_error(array('message' => __('Table number is required', 'orders-jet')));
        }
        
        try {
            // Get handler instance
            $handler_factory = new Orders_Jet_Handler_Factory(
                new Orders_Jet_Tax_Service(),
                new Orders_Jet_Kitchen_Service(),
                new Orders_Jet_Notification_Service()
            );
            $assignment_handler = $handler_factory->get_table_assignment_handler();
            
            // Unassign table
            $result = $assignment_handler->unassign_table($table_number);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error: ', 'orders-jet') . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler: Bulk assign tables to waiter
     */
    public function ajax_bulk_assign_tables() {
        // Security check
        check_ajax_referer('oj_table_assignment', 'nonce');
        
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'orders-jet')));
        }
        
        $table_numbers = isset($_POST['table_numbers']) ? $_POST['table_numbers'] : array();
        $waiter_id = isset($_POST['waiter_id']) ? intval($_POST['waiter_id']) : 0;
        
        if (empty($table_numbers) || !is_array($table_numbers)) {
            wp_send_json_error(array('message' => __('Table numbers are required', 'orders-jet')));
        }
        
        if (empty($waiter_id)) {
            wp_send_json_error(array('message' => __('Waiter is required', 'orders-jet')));
        }
        
        // Sanitize table numbers
        $table_numbers = array_map('sanitize_text_field', $table_numbers);
        
        try {
            // Get handler instance
            $handler_factory = new Orders_Jet_Handler_Factory(
                new Orders_Jet_Tax_Service(),
                new Orders_Jet_Kitchen_Service(),
                new Orders_Jet_Notification_Service()
            );
            $assignment_handler = $handler_factory->get_table_assignment_handler();
            
            // Bulk assign tables
            $result = $assignment_handler->bulk_assign_tables($table_numbers, $waiter_id);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error: ', 'orders-jet') . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler: Bulk unassign tables
     */
    public function ajax_bulk_unassign_tables() {
        // Security check
        check_ajax_referer('oj_table_assignment', 'nonce');
        
        if (!current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'orders-jet')));
        }
        
        $table_numbers = isset($_POST['table_numbers']) ? $_POST['table_numbers'] : array();
        
        if (empty($table_numbers) || !is_array($table_numbers)) {
            wp_send_json_error(array('message' => __('Table numbers are required', 'orders-jet')));
        }
        
        // Sanitize table numbers
        $table_numbers = array_map('sanitize_text_field', $table_numbers);
        
        try {
            // Get handler instance
            $handler_factory = new Orders_Jet_Handler_Factory(
                new Orders_Jet_Tax_Service(),
                new Orders_Jet_Kitchen_Service(),
                new Orders_Jet_Notification_Service()
            );
            $assignment_handler = $handler_factory->get_table_assignment_handler();
            
            // Bulk unassign tables
            $result = $assignment_handler->bulk_unassign_tables($table_numbers);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error: ', 'orders-jet') . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler to get available tables for claiming
     */
    public function ajax_get_available_tables() {
        // Security check
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        if (!current_user_can('access_oj_waiter_dashboard') && !current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'orders-jet')));
        }
        
        try {
            // Get handler instance
            $handler_factory = new Orders_Jet_Handler_Factory(
                new Orders_Jet_Tax_Service(),
                new Orders_Jet_Kitchen_Service(),
                new Orders_Jet_Notification_Service()
            );
            $assignment_handler = $handler_factory->get_table_assignment_handler();
            
            // Get all tables with assignment status
            $all_tables = $assignment_handler->get_tables_with_assignments();
            
            // Filter for unassigned tables only
            $available_tables = array_filter($all_tables, function($table) {
                return !$table['assigned_waiter']; // Only unassigned tables
            });
            
            // Format tables for the slide panel
            $formatted_tables = array();
            foreach ($available_tables as $table) {
                $has_pending_orders = $this->table_has_pending_orders($table['number']);
                $guest_waiting = $this->table_has_waiter_calls($table['number']);
                
                $formatted_tables[] = array(
                    'number' => $table['number'],
                    'title' => $table['title'],
                    'capacity' => $table['capacity'],
                    'location' => $table['location'],
                    'status' => $table['status'],
                    'guest_status' => $table['guest_status'],
                    'has_pending_orders' => $has_pending_orders,
                    'guest_waiting' => $guest_waiting,
                    // Add priority for sorting (1 = highest priority)
                    'priority' => $has_pending_orders ? 1 : ($guest_waiting ? 2 : 3)
                );
            }
            
            // Sort tables by priority (1 = highest priority first)
            usort($formatted_tables, function($a, $b) {
                return $a['priority'] - $b['priority'];
            });
            
            wp_send_json_success(array(
                'tables' => $formatted_tables,
                'count' => count($formatted_tables)
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error loading tables: ', 'orders-jet') . $e->getMessage()));
        }
    }
    
    /**
     * AJAX handler to claim a table for the current waiter
     */
    public function ajax_claim_table() {
        // Security check
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        if (!current_user_can('access_oj_waiter_dashboard') && !current_user_can('access_oj_manager_dashboard') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'orders-jet')));
        }
        
        $table_number = isset($_POST['table_number']) ? sanitize_text_field($_POST['table_number']) : '';
        
        if (empty($table_number)) {
            wp_send_json_error(array('message' => __('Table number is required', 'orders-jet')));
        }
        
        try {
            // Get handler instance
            $handler_factory = new Orders_Jet_Handler_Factory(
                new Orders_Jet_Tax_Service(),
                new Orders_Jet_Kitchen_Service(),
                new Orders_Jet_Notification_Service()
            );
            $assignment_handler = $handler_factory->get_table_assignment_handler();
            
            // Get current user ID
            $current_user_id = get_current_user_id();
            
            oj_debug_log("WAITER CLAIM START - Table: {$table_number}, Waiter ID: {$current_user_id}", 'TABLE_CLAIM');
            
            // Assign table to current waiter (this should now set reverse relationship)
            $result = $assignment_handler->assign_table($table_number, $current_user_id);
            
            oj_debug_log("WAITER CLAIM RESULT - " . json_encode($result), 'TABLE_CLAIM');
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => sprintf(__('Table %s claimed successfully!', 'orders-jet'), $table_number),
                    'table_number' => $table_number,
                    'waiter_id' => $current_user_id
                ));
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => __('Error claiming table: ', 'orders-jet') . $e->getMessage()));
        }
    }
    
    /**
     * Check if table has pending orders
     */
    private function table_has_pending_orders($table_number) {
        $orders = wc_get_orders(array(
            'status' => array('processing', 'pending'),
            'meta_key' => '_oj_table_number',
            'meta_value' => $table_number,
            'limit' => 1
        ));
        
        return !empty($orders);
    }
    
    /**
     * Check if table has unread waiter calls
     */
    private function table_has_waiter_calls($table_number) {
        // Check dashboard notifications for waiter calls
        $notifications = get_option('oj_dashboard_notifications', array());
        
        foreach ($notifications as $notification) {
            if ($notification['type'] === 'waiter_call' && 
                $notification['table_number'] === $table_number &&
                (!isset($notification['read_by']) || empty($notification['read_by']))) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * AJAX: Get reports data with filters
     */
    public function ajax_reports_get_data() {
        check_ajax_referer('oj_reports_nonce', 'nonce');
        
        // Load dependencies
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-master-query-builder.php';
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-reports-query-builder.php';
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-reports-data.php';
        
        // Initialize query builder with $_POST params
        $query_builder = new Orders_Reports_Query_Builder($_POST);
        $reports_data = new Orders_Reports_Data($query_builder);
        
        // Get all report data
        $data = array(
            'success' => true,
            'kpis' => $reports_data->format_kpis($reports_data->get_kpis()),
            'summary_table' => $reports_data->get_summary_table(),
            'category_table' => $reports_data->get_category_table(),
            'payment_breakdown' => $reports_data->get_payment_breakdown(),
            'status_breakdown' => $reports_data->get_status_breakdown(),
        );
        
        wp_send_json($data);
    }
    
    /**
     * AJAX: Get drill-down data for specific date
     */
    public function ajax_reports_drill_down() {
        check_ajax_referer('oj_reports_nonce', 'nonce');
        
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
        
        if (empty($date)) {
            wp_send_json_error(array('message' => __('Date is required', 'orders-jet')));
            return;
        }
        
        try {
            // Load dependencies
            require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-master-query-builder.php';
            require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-reports-query-builder.php';
            require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-reports-data.php';
            
            // Copy current filters from POST
            $params = $_POST;
            $params['drill_down_date'] = $date;
            
            // Ensure we pass kitchen_type and order_type correctly
            if (isset($_POST['product_type']) && !isset($params['kitchen_type'])) {
                $params['kitchen_type'] = $_POST['product_type'];
            }
            if (isset($_POST['order_source']) && !isset($params['order_type'])) {
                $params['order_type'] = $_POST['order_source'];
            }
            
            // Log parameters for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('=== DRILL-DOWN AJAX HANDLER ===');
                error_log('Date/Period: ' . $date);
                error_log('Group by: ' . (isset($params['group_by']) ? $params['group_by'] : 'N/A'));
                error_log('Product type: ' . (isset($params['product_type']) ? $params['product_type'] : 'N/A'));
                error_log('Order source: ' . (isset($params['order_source']) ? $params['order_source'] : 'N/A'));
            }
            
            $query_builder = new Orders_Reports_Query_Builder($params);
            $reports_data = new Orders_Reports_Data($query_builder);
            
            // Get drill-down data
            $drill_data = $reports_data->get_drill_down_data($date);
            
            // Log results for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Orders count: ' . count($drill_data['orders']));
                error_log('KPIs total_orders: ' . $drill_data['kpis']['total_orders']);
            }
            
            // Format response - use wp_send_json_success for proper structure
            wp_send_json_success(array(
                'date' => $date,
                'kpis' => $reports_data->format_kpis($drill_data['kpis']),
                'orders' => $drill_data['orders'],
                'debug' => array(
                    'orders_count' => count($drill_data['orders']),
                    'date' => $date,
                )
            ));
        } catch (Exception $e) {
            // Log error
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Drill-down error: ' . $e->getMessage());
                error_log($e->getTraceAsString());
            }
            
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'trace' => WP_DEBUG ? $e->getTraceAsString() : null,
            ));
        }
    }
    
    /**
     * AJAX: Export reports data
     */
    public function ajax_reports_export() {
        check_ajax_referer('oj_reports_nonce', 'nonce');
        
        $export_type = isset($_POST['export_type']) ? sanitize_text_field($_POST['export_type']) : 'csv';
        $report_type = isset($_POST['report_type']) ? sanitize_text_field($_POST['report_type']) : 'summary';
        
        // Load dependencies
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-master-query-builder.php';
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-reports-query-builder.php';
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-reports-data.php';
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-reports-export.php';
        
        // Initialize query builder and data layer
        $query_builder = new Orders_Reports_Query_Builder($_POST);
        $reports_data = new Orders_Reports_Data($query_builder);
        
        // Initialize export handler
        $exporter = new Orders_Reports_Export($reports_data, $query_builder);
        
        // Perform export
        try {
            $result = $exporter->export($export_type, $report_type);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
            ));
        }
    }
}


