<?php
declare(strict_types=1);
/**
 * Orders Jet - Dashboard Refresh Handler
 * Handles AJAX refresh for all dashboard types (Express & Master)
 * 
 * @package Orders_Jet
 * @version 2.0.0
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Dashboard_Refresh_Handler {
    
    /**
     * Kitchen service instance
     * 
     * @var Orders_Jet_Kitchen_Service
     */
    private $kitchen_service;
    
    /**
     * Order method service instance
     * 
     * @var Orders_Jet_Order_Method_Service
     */
    private $order_method_service;
    
    /**
     * Constructor
     * 
     * @param Orders_Jet_Kitchen_Service $kitchen_service Kitchen service
     * @param Orders_Jet_Order_Method_Service $order_method_service Order method service
     */
    public function __construct($kitchen_service, $order_method_service) {
        $this->kitchen_service = $kitchen_service;
        $this->order_method_service = $order_method_service;
        
        // Load customer grouping helpers for AJAX requests
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/helpers/customer-grouping-helpers.php';
    }
    
    /**
     * Handle dashboard refresh AJAX request (router)
     * 
     * Routes to appropriate dashboard type based on page parameter.
     * 
     * @since 2.0.0
     */
    public function handle_dashboard_refresh() {
        // Verify nonce
        check_ajax_referer('oj_dashboard_nonce', 'nonce');
        
        try {
            // Get current page to determine what to refresh
            $page = sanitize_text_field($_POST['page'] ?? '');
            
            if ($page === 'orders-jet-express') {
                // Refresh express dashboard
                $this->refresh_express_dashboard();
            } else {
                // Refresh regular dashboard (fallback)
                $this->refresh_regular_dashboard();
            }
            
        } catch (Exception $e) {
            oj_error_log('Dashboard refresh error: ' . $e->getMessage(), 'DASHBOARD');
            wp_send_json_error(array(
                'message' => __('Failed to refresh dashboard', 'orders-jet')
            ));
        }
    }
    
    /**
     * Refresh Express Dashboard via AJAX
     * 
     * Fetches and renders orders for the Orders Express dashboard.
     * 
     * @since 2.0.0
     */
    private function refresh_express_dashboard() {
        // Load helper functions
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/helpers/orders-express-helpers.php';
        
        // Get active orders (same query as template)
        $active_orders = wc_get_orders(array(
            'status' => array('wc-pending', 'wc-processing'),
            'limit' => 50,
            'orderby' => 'date',
            'order' => 'ASC',
            'return' => 'objects'
        ));
        
        // Prepare orders data (same as template)
        $orders_data = array();
        $filter_counts = array(
            'active' => 0,
            'processing' => 0,
            'pending' => 0,
            'dinein' => 0,
            'takeaway' => 0,
            'delivery' => 0,
            'food_kitchen' => 0,
            'beverage_kitchen' => 0
        );
        
        foreach ($active_orders as $order) {
            $order_data = oj_express_prepare_order_data($order, $this->kitchen_service, $this->order_method_service);
            $orders_data[] = $order_data;
            oj_express_update_filter_counts($filter_counts, $order_data);
        }
        
        // Generate orders HTML
        ob_start();
        if (empty($orders_data)) {
            include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/empty-state.php';
        } else {
            foreach ($orders_data as $order_data) {
                include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/order-card.php';
            }
        }
        $orders_html = ob_get_clean();
        
        wp_send_json_success(array(
            'orders_html' => $orders_html,
            'filter_counts' => $filter_counts,
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Refresh Regular Dashboard via AJAX (fallback)
     * 
     * Placeholder for future regular dashboard refresh.
     * 
     * @since 2.0.0
     */
    private function refresh_regular_dashboard() {
        // For now, just return success - can be expanded later
        wp_send_json_success(array(
            'message' => __('Dashboard refreshed', 'orders-jet'),
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Handle Orders Master content refresh AJAX request
     * 
     * Handles complete AJAX refresh for Orders Master including:
     * - Filtering, sorting, pagination
     * - Order grid rendering
     * - Filter counts
     * - Debug step-by-step mode for troubleshooting
     * 
     * @since 2.0.0
     */
    public function handle_orders_content_refresh() {
        oj_debug_log('🔵 Dashboard refresh handler called', 'DASHBOARD_REFRESH');
        
        // Verify nonce first
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'orders_jet_nonce')) {
            oj_error_log('❌ Nonce verification failed', 'DASHBOARD_REFRESH');
            wp_send_json_error(array('message' => __('Security check failed', 'orders-jet')));
            return;
        }
        
        oj_debug_log('✅ Nonce verified', 'DASHBOARD_REFRESH');
        
        // Check user permissions (Manager, Waiter, or Admin)
        if (!current_user_can('access_oj_manager_dashboard') 
            && !current_user_can('access_oj_waiter_dashboard')
            && !current_user_can('manage_options')) {
            oj_error_log('❌ Permission check failed', 'DASHBOARD_REFRESH');
            wp_send_json_error(array('message' => __('Insufficient permissions', 'orders-jet')));
            return;
        }
        
        oj_debug_log('✅ Permissions verified', 'DASHBOARD_REFRESH');
        
        // Test step by step to find the issue
        $step = intval($_POST['step'] ?? 0);
        oj_debug_log('📊 Processing step: ' . $step, 'DASHBOARD_REFRESH');
        
        try {
            // Step 1: Test basic parameter handling - Process ALL filter parameters
            $filter_params = $this->sanitize_filter_params($_POST);
            
            // Debug: Log received parameters
            oj_debug_log('AJAX Filter Parameters Received: ' . print_r($filter_params, true), 'AJAX_DEBUG');
            
            if ($step === 1) {
                wp_send_json_success(array(
                    'message' => 'Step 1: Basic parameter handling works',
                    'filter_params' => $filter_params,
                    'step' => 1
                ));
                return;
            }
            
            // Load dependencies (needed for all steps beyond 1)
            $this->load_dependencies();
            
            // Step 2: Test dependency loading
            if ($step === 2) {
                wp_send_json_success(array(
                    'message' => 'Step 2: Dependencies loaded successfully',
                    'loaded_files' => array(
                        'orders-master-helpers.php',
                        'class-orders-master-query-builder.php', 
                        'class-orders-jet-filter-url-builder.php',
                        'class-orders-jet-amount-filter-service.php'
                    ),
                    'step' => 2
                ));
                return;
            }
            
            // Step 3: Services already initialized in constructor - skip in debug mode
            if ($step === 3) {
                wp_send_json_success(array(
                    'message' => 'Step 3: Services initialized successfully',
                    'services' => array(
                        'kitchen_service' => get_class($this->kitchen_service),
                        'order_method_service' => get_class($this->order_method_service)
                    ),
                    'step' => 3
                ));
                return;
            }
            
            // Step 4: Test Query Builder creation
            $query_builder = new Orders_Master_Query_Builder($filter_params);
            
            if ($step === 4) {
                wp_send_json_success(array(
                    'message' => 'Step 4: Query Builder created successfully',
                    'query_builder_class' => get_class($query_builder),
                    'step' => 4
                ));
                return;
            }
            
            // Step 5: Test data fetching
            $orders = $query_builder->get_orders();
            $pagination = $query_builder->get_pagination_data();
            
            if ($step === 5) {
                wp_send_json_success(array(
                    'message' => 'Step 5: Data fetching successful',
                    'orders_count' => count($orders),
                    'pagination' => $pagination,
                    'step' => 5
                ));
                return;
            }
            
            // Step 6: Test filter counts and URL builder
            $filter_counts = $query_builder->get_filter_counts();
            $current_params = $query_builder->get_current_params();
            $has_filters = Orders_Jet_Filter_URL_Builder::has_active_filters($current_params);
            
            if ($step === 6) {
                wp_send_json_success(array(
                    'message' => 'Step 6: Filter counts and URL builder successful',
                    'filter_counts' => $filter_counts,
                    'has_filters' => $has_filters,
                    'step' => 6
                ));
                return;
            }
            
            // Step 7: Test HTML generation
            if ($step === 7) {
                $test_html = $this->generate_test_html($orders);
                wp_send_json_success(array(
                    'message' => 'Step 7: HTML generation successful',
                    'orders_html_length' => strlen($test_html),
                    'orders_html_preview' => substr($test_html, 0, 200) . '...',
                    'step' => 7
                ));
                return;
            }
            
            // Step 8 or 0: Full implementation
            // For step 0 (default), we need to create query builder since we skipped intermediate steps
            if ($step === 0 && !isset($query_builder)) {
                $query_builder = new Orders_Master_Query_Builder($filter_params);
                $orders = $query_builder->get_orders();
            }
            
            if ($step === 8 || $step === 0) {
                $response_data = $this->generate_full_response($query_builder, $filter_params, $step);
                wp_send_json_success($response_data);
                return;
            }
            
            // If no specific step requested, default to step 1
            wp_send_json_success(array(
                'message' => 'No step specified, defaulting to step 1',
                'available_steps' => '1-8, or 0 for full implementation',
                'filter_params' => $filter_params,
                'step' => 1
            ));
            
        } catch (Exception $e) {
            oj_error_log('Dashboard refresh error: ' . $e->getMessage(), 'DASHBOARD_REFRESH');
            oj_error_log('File: ' . $e->getFile() . ' Line: ' . $e->getLine(), 'DASHBOARD_REFRESH');
            oj_error_log('Trace: ' . $e->getTraceAsString(), 'DASHBOARD_REFRESH');
            
            wp_send_json_error(array(
                'message' => 'Error in step ' . $step . ': ' . $e->getMessage(),
                'step' => $step,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
        } catch (Error $e) {
            // Catch PHP 7+ Fatal Errors
            oj_error_log('Dashboard refresh FATAL error: ' . $e->getMessage(), 'DASHBOARD_REFRESH');
            oj_error_log('File: ' . $e->getFile() . ' Line: ' . $e->getLine(), 'DASHBOARD_REFRESH');
            oj_error_log('Trace: ' . $e->getTraceAsString(), 'DASHBOARD_REFRESH');
            
            wp_send_json_error(array(
                'message' => 'Fatal error: ' . $e->getMessage(),
                'step' => $step,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
    
    /**
     * Sanitize filter parameters from POST data
     * 
     * @param array $post_data POST data array
     * @return array Sanitized filter parameters
     * @since 2.0.0
     */
    private function sanitize_filter_params($post_data) {
        return array(
            'filter' => sanitize_text_field($post_data['filter'] ?? 'all'),
            'orderby' => sanitize_text_field($post_data['orderby'] ?? 'date_created'),
            'order' => sanitize_text_field($post_data['order'] ?? 'DESC'),
            'search' => sanitize_text_field($post_data['search'] ?? ''),
            'date_preset' => sanitize_text_field($post_data['date_preset'] ?? ''),
            'date_from' => sanitize_text_field($post_data['date_from'] ?? ''),
            'date_to' => sanitize_text_field($post_data['date_to'] ?? ''),
            'paged' => intval($post_data['paged'] ?? 1),
            'order_type' => sanitize_text_field($post_data['order_type'] ?? ''),
            'kitchen_type' => sanitize_text_field($post_data['kitchen_type'] ?? ''),
            'kitchen_status' => sanitize_text_field($post_data['kitchen_status'] ?? ''),
            'assigned_waiter' => intval($post_data['assigned_waiter'] ?? 0),
            'unassigned_only' => !empty($post_data['unassigned_only']),
            'payment_method' => sanitize_text_field($post_data['payment_method'] ?? ''),
            'amount_type' => sanitize_text_field($post_data['amount_type'] ?? ''),
            'amount_value' => floatval($post_data['amount_value'] ?? 0),
            'amount_min' => floatval($post_data['amount_min'] ?? 0),
            'amount_max' => floatval($post_data['amount_max'] ?? 0),
            'customer_type' => sanitize_text_field($post_data['customer_type'] ?? '')
        );
    }
    
    /**
     * Load required dependencies for Orders Master refresh
     * 
     * @since 2.0.0
     */
    private function load_dependencies() {
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/helpers/orders-master-helpers.php';
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-master-query-builder.php';
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/helpers/class-orders-jet-filter-url-builder.php';
        require_once ORDERS_JET_PLUGIN_DIR . 'includes/services/class-orders-jet-amount-filter-service.php';
    }
    
    /**
     * Generate test HTML for debugging
     * 
     * @param array $orders Array of WC_Order objects
     * @return string Generated HTML
     * @since 2.0.0
     */
    private function generate_test_html($orders) {
        // Make services available to template scope
        $kitchen_service = $this->kitchen_service;
        $order_method_service = $this->order_method_service;
        
        ob_start();
        if (!empty($orders)) {
            $first_order = $orders[0];
            $order_data = oj_master_prepare_order_data($first_order, $kitchen_service, $order_method_service);
            // Extract kitchen_status for template use
            $kitchen_status = $kitchen_service->get_kitchen_readiness_status($first_order);
            include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/order-card.php';
        } else {
            echo '<div class="oj-no-orders">No orders found</div>';
        }
        return ob_get_clean();
    }
    
    /**
     * Generate full response data for Orders Master refresh
     * 
     * @param Orders_Master_Query_Builder $query_builder Query builder instance
     * @param array $filter_params Filter parameters
     * @param int $step Debug step number
     * @return array Response data array
     * @since 2.0.0
     */
    private function generate_full_response($query_builder, $filter_params, $step) {
        // Get all required data
        $orders = $query_builder->get_orders();
        $pagination = $query_builder->get_pagination_data();
        $filter_counts = $query_builder->get_filter_counts();
        $has_filters = Orders_Jet_Filter_URL_Builder::has_active_filters($filter_params);
        
        // Extract pagination data
        $total_orders = $pagination['total_orders'];
        $total_pages = $pagination['total_pages'];
        $orders_count = count($orders);
        $filtered_total = $query_builder->get_filtered_orders_total();
        
        // Prepare variables for the content area partial
        $current_page = max(1, intval($_POST['paged'] ?? 1));
        $orderby = $query_builder->get_orderby();
        $order = $query_builder->get_order();
        
        // CRITICAL: Make services available to template scope
        $kitchen_service = $this->kitchen_service;
        $order_method_service = $this->order_method_service;
        
        // CRITICAL: Make filter params available as $_GET for template URL building
        $original_get = $_GET;
        $_GET = array_merge($_GET, $filter_params);
        
        // Generate complete content area HTML - Use appropriate template based on page
        ob_start();
        
        // Check if this is a reports page request
        $current_page_param = $_POST['current_page'] ?? $_GET['page'] ?? '';
        if ($current_page_param === 'orders-reports') {
            // Use reports-specific content area template
            // Make customer_type available to the template
            $customer_type = $filter_params['customer_type'] ?? '';
            include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/reports/reports-content-area.php';
        } else {
            // Use default orders master content area template
            include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/orders-content-area.php';
        }
        
        $complete_content_html = ob_get_clean();
        
        // Restore original $_GET
        $_GET = $original_get;
        
        // Generate individual sections for backward compatibility
        $orders_grid_html = $this->generate_orders_grid($orders, $filter_params);
        $count_html = $this->generate_count_html($orders_count, $total_orders, $filtered_total);
        $pagination_html = $this->generate_pagination_html($current_page, $total_pages);
        
        // Return complete success response
        return array(
            // New: Complete content area
            'content_html' => $complete_content_html,
            
            // Legacy: Individual sections
            'orders_html' => $orders_grid_html,
            'count_html' => $count_html,
            'pagination_html' => $pagination_html,
            
            // Metadata
            'filter_counts' => $filter_counts,
            'has_orders' => !empty($orders),
            'total_orders' => $total_orders,
            'orders_count' => $orders_count,
            'filtered_total' => $filtered_total,
            'current_page' => $current_page,
            'total_pages' => $total_pages,
            'has_filters' => $has_filters,
            
            // Debug info
            'debug_info' => array(
                'filter_params' => $filter_params,
                'step' => $step,
                'template_used' => 'orders-content-area.php',
                'query_summary' => array(
                    'orderby' => $orderby,
                    'order' => $order,
                    'total_found' => $total_orders,
                    'page_size' => $orders_count
                )
            )
        );
    }
    
    /**
     * Generate orders grid HTML
     * 
     * @param array $orders Array of WC_Order objects
     * @param array $filter_params Filter parameters
     * @return string Generated HTML
     * @since 2.0.0
     */
    private function generate_orders_grid($orders, $filter_params = array()) {
        // Make services available to template scope
        $kitchen_service = $this->kitchen_service;
        $order_method_service = $this->order_method_service;
        
        ob_start();
        if (!empty($orders)) {
            echo '<div class="oj-orders-grid">';
            
            // Get customer type directly from POST for grouping logic
            // Don't group when there's an active search - show all matching orders
            $customer_type = $_POST['customer_type'] ?? '';
            $has_search = !empty($_POST['search']);
            $should_group = function_exists('oj_should_apply_customer_grouping') 
                ? (oj_should_apply_customer_grouping($customer_type) && !$has_search) 
                : false;
            
            
            if ($should_group) {
                // Customer grouping logic - same as reports-content-area.php
                $customer_order_tracking = array();
                $customer_orders_map = array(); // Map customer to their orders
                
                // First pass: Build customer tracking data and group orders by customer
                foreach ($orders as $wc_order) {
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
                }
                
                // Sort customers by order count (highest first)
                uasort($customer_order_tracking, function($a, $b) {
                    return $b['total_orders'] - $a['total_orders'];
                });
                
                // Second pass: Render orders in sorted customer order
                foreach ($customer_order_tracking as $customer_key => $tracking_data) {
                    $customer_orders = $customer_orders_map[$customer_key];
                    $tracking = &$customer_order_tracking[$customer_key];
                    
                    foreach ($customer_orders as $wc_order) {
                        if ($tracking['shown_count'] < 2) {
                        // Show individual order card
                        $order_data = oj_master_prepare_order_data($wc_order, $kitchen_service, $order_method_service);
                        $kitchen_status = $kitchen_service->get_kitchen_readiness_status($wc_order);
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
                        // Skip remaining orders (they're represented in overlay)
                    } // End customer orders loop
                } // End sorted customers loop
            } else {
                // No customer grouping - show all orders individually
                foreach ($orders as $order) {
                    $order_data = oj_master_prepare_order_data($order, $kitchen_service, $order_method_service);
                    $kitchen_status = $kitchen_service->get_kitchen_readiness_status($order);
                    $show_bulk_checkbox = false;
                    $use_reports_actions = true;
                    $overlay_data = null;
                    
                    include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/order-card.php';
                }
            }
            
            echo '</div>';
        } else {
            echo '<div class="oj-empty-state">';
            echo '<div class="oj-empty-icon">📋</div>';
            echo '<h3>' . __('No orders found', 'orders-jet') . '</h3>';
            echo '<p>' . __('Try adjusting your filters or search criteria.', 'orders-jet') . '</p>';
            echo '</div>';
        }
        return ob_get_clean();
    }
    
    /**
     * Generate count HTML
     * 
     * @param int $orders_count Current page order count
     * @param int $total_orders Total orders count
     * @param float $filtered_total Filtered total amount
     * @return string Generated HTML
     * @since 2.0.0
     */
    private function generate_count_html($orders_count, $total_orders, $filtered_total) {
        ob_start();
        echo '<span class="oj-count-text">';
        printf(__('Showing %d of %d orders', 'orders-jet'), $orders_count, $total_orders);
        echo '</span>';
        if ($filtered_total > 0) {
            echo ' | <span class="oj-total-amount">';
            echo '<span class="oj-total-icon">💰</span>';
            printf(__('Total: %s', 'orders-jet'), wc_price($filtered_total));
            echo '</span>';
        }
        return ob_get_clean();
    }
    
    /**
     * Generate pagination HTML
     * 
     * @param int $current_page Current page number
     * @param int $total_pages Total pages count
     * @return string Generated HTML
     * @since 2.0.0
     */
    private function generate_pagination_html($current_page, $total_pages) {
        ob_start();
        if ($total_pages > 1) {
            $pagination_args = array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'current' => $current_page,
                'total' => $total_pages,
                'prev_text' => '‹ ' . __('Previous', 'orders-jet'),
                'next_text' => __('Next', 'orders-jet') . ' ›',
                'type' => 'plain',
                'end_size' => 1,
                'mid_size' => 2
            );
            echo '<div class="oj-pagination">';
            echo paginate_links($pagination_args);
            echo '</div>';
        }
        return ob_get_clean();
    }
}

