<?php
declare(strict_types=1);
/**
 * Orders Jet - Filter Views Handler
 * Handles AJAX requests for saved filter views management
 * 
 * @package Orders_Jet
 * @version 2.0.0
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Filter_Views_Handler {
    
    /**
     * Handle save filter view AJAX request
     * 
     * Saves a new filter view with specified parameters for the current user.
     * The view can later be loaded to quickly apply the same filters.
     * 
     * @since 2.0.0
     */
    public function handle_save_view() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'orders_jet_nonce')) {
                wp_send_json_error(array('message' => __('Security check failed', 'orders-jet')));
                return;
            }
            
            // Check user permissions
            if (!current_user_can('read')) {
                wp_send_json_error(array('message' => __('Insufficient permissions', 'orders-jet')));
                return;
            }
            
            $user_id = get_current_user_id();
            $view_name = sanitize_text_field($_POST['view_name'] ?? '');
            $filter_params = $_POST['filter_params'] ?? array();
            
            if (!is_array($filter_params)) {
                wp_send_json_error(array('message' => __('Invalid filter parameters', 'orders-jet')));
                return;
            }
            
            $saved_views_service = new Orders_Jet_Saved_Views_Service();
            $result = $saved_views_service->save_view($user_id, $view_name, $filter_params);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            oj_error_log('Save filter view error: ' . $e->getMessage(), 'SAVED_VIEWS');
            wp_send_json_error(array('message' => __('An error occurred while saving the view', 'orders-jet')));
        }
    }
    
    /**
     * Handle get user saved views AJAX request
     * 
     * Retrieves all saved filter views for the current user along with
     * usage statistics.
     * 
     * @since 2.0.0
     */
    public function handle_get_user_views() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'orders_jet_nonce')) {
                wp_send_json_error(array('message' => __('Security check failed', 'orders-jet')));
                return;
            }
            
            // Check user permissions
            if (!current_user_can('read')) {
                wp_send_json_error(array('message' => __('Insufficient permissions', 'orders-jet')));
                return;
            }
            
            $user_id = get_current_user_id();
            
            $saved_views_service = new Orders_Jet_Saved_Views_Service();
            $views = $saved_views_service->get_user_views($user_id);
            $stats = $saved_views_service->get_view_statistics($user_id);
            
            wp_send_json_success(array(
                'views' => $views,
                'statistics' => $stats
            ));
            
        } catch (Exception $e) {
            oj_error_log('Get user saved views error: ' . $e->getMessage(), 'SAVED_VIEWS');
            wp_send_json_error(array('message' => __('An error occurred while loading saved views', 'orders-jet')));
        }
    }

    /**
     * Handle load filter view AJAX request
     * 
     * Loads a saved filter view and builds a URL with the filter parameters.
     * The frontend can then redirect to this URL to apply the filters.
     * 
     * @since 2.0.0
     */
    public function handle_load_view() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'orders_jet_nonce')) {
                wp_send_json_error(array('message' => __('Security check failed', 'orders-jet')));
                return;
            }
            
            // Check user permissions
            if (!current_user_can('read')) {
                wp_send_json_error(array('message' => __('Insufficient permissions', 'orders-jet')));
                return;
            }
            
            $user_id = get_current_user_id();
            $view_id = sanitize_text_field($_POST['view_id'] ?? '');
            $current_page = sanitize_text_field($_POST['current_page'] ?? 'orders-master-v2');
            
            $saved_views_service = new Orders_Jet_Saved_Views_Service();
            $result = $saved_views_service->load_view($user_id, $view_id);
            
            if ($result['success']) {
                // Build URL with filter parameters, preserving current page context
                $filters_with_page = $result['filters'];
                $filters_with_page['page'] = $current_page;
                $filter_url = Orders_Jet_Filter_URL_Builder::build_filter_url($filters_with_page);
                $result['redirect_url'] = $filter_url;
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            oj_error_log('Load filter view error: ' . $e->getMessage(), 'SAVED_VIEWS');
            wp_send_json_error(array('message' => __('An error occurred while loading the view', 'orders-jet')));
        }
    }
    
    /**
     * Handle delete filter view AJAX request
     * 
     * Deletes a saved filter view for the current user.
     * 
     * @since 2.0.0
     */
    public function handle_delete_view() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'orders_jet_nonce')) {
                wp_send_json_error(array('message' => __('Security check failed', 'orders-jet')));
                return;
            }
            
            // Check user permissions
            if (!current_user_can('read')) {
                wp_send_json_error(array('message' => __('Insufficient permissions', 'orders-jet')));
                return;
            }
            
            $user_id = get_current_user_id();
            $view_id = sanitize_text_field($_POST['view_id'] ?? '');
            
            $saved_views_service = new Orders_Jet_Saved_Views_Service();
            $result = $saved_views_service->delete_view($user_id, $view_id);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            oj_error_log('Delete filter view error: ' . $e->getMessage(), 'SAVED_VIEWS');
            wp_send_json_error(array('message' => __('An error occurred while deleting the view', 'orders-jet')));
        }
    }
    
    /**
     * Handle rename filter view AJAX request
     * 
     * Renames an existing saved filter view for the current user.
     * 
     * @since 2.0.0
     */
    public function handle_rename_view() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'orders_jet_nonce')) {
                wp_send_json_error(array('message' => __('Security check failed', 'orders-jet')));
                return;
            }
            
            // Check user permissions
            if (!current_user_can('read')) {
                wp_send_json_error(array('message' => __('Insufficient permissions', 'orders-jet')));
                return;
            }
            
            $user_id = get_current_user_id();
            $view_id = sanitize_text_field($_POST['view_id'] ?? '');
            $new_name = sanitize_text_field($_POST['new_name'] ?? '');
            
            $saved_views_service = new Orders_Jet_Saved_Views_Service();
            $result = $saved_views_service->rename_view($user_id, $view_id, $new_name);
            
            if ($result['success']) {
                wp_send_json_success($result);
            } else {
                wp_send_json_error($result);
            }
            
        } catch (Exception $e) {
            oj_error_log('Rename filter view error: ' . $e->getMessage(), 'SAVED_VIEWS');
            wp_send_json_error(array('message' => __('An error occurred while renaming the view', 'orders-jet')));
        }
    }
}

