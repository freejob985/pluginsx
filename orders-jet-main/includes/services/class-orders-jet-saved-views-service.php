<?php
declare(strict_types=1);
/**
 * Orders Jet - Saved Views Service
 * Handles saving, loading, and managing user filter views
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Saved_Views_Service {
    
    /**
     * User meta key for storing saved views
     */
    const USER_META_KEY = '_oj_saved_views';
    
    /**
     * Maximum number of views per user
     */
    const MAX_VIEWS_PER_USER = 20;
    
    /**
     * Save a new filter view for a user
     * 
     * @param int $user_id User ID
     * @param string $view_name Name of the view
     * @param array $filter_params Filter parameters to save
     * @return array Result array with success/error status
     */
    public function save_view($user_id, $view_name, $filter_params) {
        try {
            // Validate inputs
            $validation = $this->validate_save_inputs($user_id, $view_name, $filter_params);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Get existing views
            $existing_views = $this->get_user_views($user_id);
            
            // Check view limit
            if (count($existing_views) >= self::MAX_VIEWS_PER_USER) {
                return array(
                    'success' => false,
                    'message' => sprintf(__('Maximum of %d saved views allowed per user', 'orders-jet'), self::MAX_VIEWS_PER_USER)
                );
            }
            
            // Check for duplicate names
            foreach ($existing_views as $view) {
                if (strcasecmp($view['name'], $view_name) === 0) {
                    return array(
                        'success' => false,
                        'message' => __('A view with this name already exists', 'orders-jet')
                    );
                }
            }
            
            // Create new view
            $view_id = $this->generate_view_id();
            $new_view = array(
                'id' => $view_id,
                'name' => sanitize_text_field($view_name),
                'created' => current_time('mysql'),
                'last_used' => current_time('mysql'),
                'use_count' => 0,
                'filters' => $this->sanitize_filter_params($filter_params)
            );
            
            // Add to existing views
            $existing_views[$view_id] = $new_view;
            
            // Save to user meta
            $saved = update_user_meta($user_id, self::USER_META_KEY, $existing_views);
            
            if ($saved) {
                return array(
                    'success' => true,
                    'message' => __('View saved successfully', 'orders-jet'),
                    'view_id' => $view_id,
                    'view' => $new_view
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Failed to save view', 'orders-jet')
                );
            }
            
        } catch (Exception $e) {
            oj_error_log('Error saving view: ' . $e->getMessage(), 'SAVED_VIEWS');
            return array(
                'success' => false,
                'message' => __('An error occurred while saving the view', 'orders-jet')
            );
        }
    }
    
    /**
     * Get all saved views for a user
     * 
     * @param int $user_id User ID
     * @return array Array of saved views
     */
    public function get_user_views($user_id) {
        try {
            if (!$user_id || $user_id <= 0) {
                return array();
            }
            
            $views = get_user_meta($user_id, self::USER_META_KEY, true);
            
            if (!is_array($views)) {
                return array();
            }
            
            // Sort by last used (most recent first)
            uasort($views, function($a, $b) {
                return strtotime($b['last_used']) - strtotime($a['last_used']);
            });
            
            return $views;
            
        } catch (Exception $e) {
            oj_error_log('Error getting user views: ' . $e->getMessage(), 'SAVED_VIEWS');
            return array();
        }
    }
    
    /**
     * Load a specific view for a user
     * 
     * @param int $user_id User ID
     * @param string $view_id View ID
     * @return array Result array with view data or error
     */
    public function load_view($user_id, $view_id) {
        try {
            if (!$user_id || !$view_id) {
                return array(
                    'success' => false,
                    'message' => __('Invalid user or view ID', 'orders-jet')
                );
            }
            
            $views = $this->get_user_views($user_id);
            
            if (!isset($views[$view_id])) {
                return array(
                    'success' => false,
                    'message' => __('View not found', 'orders-jet')
                );
            }
            
            $view = $views[$view_id];
            
            // Update usage statistics
            $view['last_used'] = current_time('mysql');
            $view['use_count'] = intval($view['use_count']) + 1;
            $views[$view_id] = $view;
            
            // Save updated statistics
            update_user_meta($user_id, self::USER_META_KEY, $views);
            
            return array(
                'success' => true,
                'view' => $view,
                'filters' => $view['filters']
            );
            
        } catch (Exception $e) {
            oj_error_log('Error loading view: ' . $e->getMessage(), 'SAVED_VIEWS');
            return array(
                'success' => false,
                'message' => __('An error occurred while loading the view', 'orders-jet')
            );
        }
    }
    
    /**
     * Delete a saved view
     * 
     * @param int $user_id User ID
     * @param string $view_id View ID
     * @return array Result array with success/error status
     */
    public function delete_view($user_id, $view_id) {
        try {
            if (!$user_id || !$view_id) {
                return array(
                    'success' => false,
                    'message' => __('Invalid user or view ID', 'orders-jet')
                );
            }
            
            $views = $this->get_user_views($user_id);
            
            if (!isset($views[$view_id])) {
                return array(
                    'success' => false,
                    'message' => __('View not found', 'orders-jet')
                );
            }
            
            $view_name = $views[$view_id]['name'];
            unset($views[$view_id]);
            
            // Save updated views
            $saved = update_user_meta($user_id, self::USER_META_KEY, $views);
            
            if ($saved) {
                return array(
                    'success' => true,
                    'message' => sprintf(__('View "%s" deleted successfully', 'orders-jet'), $view_name)
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Failed to delete view', 'orders-jet')
                );
            }
            
        } catch (Exception $e) {
            oj_error_log('Error deleting view: ' . $e->getMessage(), 'SAVED_VIEWS');
            return array(
                'success' => false,
                'message' => __('An error occurred while deleting the view', 'orders-jet')
            );
        }
    }
    
    /**
     * Rename a saved view
     * 
     * @param int $user_id User ID
     * @param string $view_id View ID
     * @param string $new_name New view name
     * @return array Result array with success/error status
     */
    public function rename_view($user_id, $view_id, $new_name) {
        try {
            if (!$user_id || !$view_id || empty($new_name)) {
                return array(
                    'success' => false,
                    'message' => __('Invalid parameters', 'orders-jet')
                );
            }
            
            $views = $this->get_user_views($user_id);
            
            if (!isset($views[$view_id])) {
                return array(
                    'success' => false,
                    'message' => __('View not found', 'orders-jet')
                );
            }
            
            // Check for duplicate names (excluding current view)
            foreach ($views as $id => $view) {
                if ($id !== $view_id && strcasecmp($view['name'], $new_name) === 0) {
                    return array(
                        'success' => false,
                        'message' => __('A view with this name already exists', 'orders-jet')
                    );
                }
            }
            
            $old_name = $views[$view_id]['name'];
            $views[$view_id]['name'] = sanitize_text_field($new_name);
            
            // Save updated views
            $saved = update_user_meta($user_id, self::USER_META_KEY, $views);
            
            if ($saved) {
                return array(
                    'success' => true,
                    'message' => sprintf(__('View renamed from "%s" to "%s"', 'orders-jet'), $old_name, $new_name),
                    'view' => $views[$view_id]
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Failed to rename view', 'orders-jet')
                );
            }
            
        } catch (Exception $e) {
            oj_error_log('Error renaming view: ' . $e->getMessage(), 'SAVED_VIEWS');
            return array(
                'success' => false,
                'message' => __('An error occurred while renaming the view', 'orders-jet')
            );
        }
    }
    
    /**
     * Generate a unique view ID
     * 
     * @return string Unique view ID
     */
    private function generate_view_id() {
        return 'view_' . uniqid() . '_' . time();
    }
    
    /**
     * Validate inputs for saving a view
     * 
     * @param int $user_id User ID
     * @param string $view_name View name
     * @param array $filter_params Filter parameters
     * @return array Validation result
     */
    private function validate_save_inputs($user_id, $view_name, $filter_params) {
        if (!$user_id || $user_id <= 0) {
            return array(
                'success' => false,
                'message' => __('Invalid user ID', 'orders-jet')
            );
        }
        
        if (empty($view_name) || strlen(trim($view_name)) === 0) {
            return array(
                'success' => false,
                'message' => __('View name is required', 'orders-jet')
            );
        }
        
        if (strlen($view_name) > 100) {
            return array(
                'success' => false,
                'message' => __('View name is too long (maximum 100 characters)', 'orders-jet')
            );
        }
        
        if (!is_array($filter_params)) {
            return array(
                'success' => false,
                'message' => __('Invalid filter parameters', 'orders-jet')
            );
        }
        
        return array('success' => true);
    }
    
    /**
     * Sanitize filter parameters
     * 
     * @param array $params Filter parameters
     * @return array Sanitized parameters
     */
    private function sanitize_filter_params($params) {
        $sanitized = array();
        
        $allowed_params = array(
            'filter', 'date_preset', 'date_from', 'date_to', 
            'search', 'order_type', 'kitchen_type', 'kitchen_status',
            'assigned_waiter', 'unassigned_only', 'payment_method',
            'amount_type', 'amount_value', 'amount_min', 'amount_max',
            'orderby', 'order'
        );
        
        foreach ($allowed_params as $param) {
            if (isset($params[$param]) && $params[$param] !== '') {
                if (in_array($param, array('amount_value', 'amount_min', 'amount_max'))) {
                    $sanitized[$param] = floatval($params[$param]);
                } elseif ($param === 'assigned_waiter') {
                    $sanitized[$param] = intval($params[$param]);
                } elseif ($param === 'unassigned_only') {
                    $sanitized[$param] = $params[$param] === '1' || $params[$param] === true;
                } else {
                    $sanitized[$param] = sanitize_text_field($params[$param]);
                }
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Get view statistics for a user
     * 
     * @param int $user_id User ID
     * @return array View statistics
     */
    public function get_view_statistics($user_id) {
        try {
            $views = $this->get_user_views($user_id);
            
            $stats = array(
                'total_views' => count($views),
                'most_used' => null,
                'recently_created' => null,
                'total_usage' => 0
            );
            
            if (empty($views)) {
                return $stats;
            }
            
            $most_used_count = 0;
            $most_recent_created = '';
            
            foreach ($views as $view) {
                $stats['total_usage'] += intval($view['use_count']);
                
                if (intval($view['use_count']) > $most_used_count) {
                    $most_used_count = intval($view['use_count']);
                    $stats['most_used'] = $view;
                }
                
                if ($view['created'] > $most_recent_created) {
                    $most_recent_created = $view['created'];
                    $stats['recently_created'] = $view;
                }
            }
            
            return $stats;
            
        } catch (Exception $e) {
            oj_error_log('Error getting view statistics: ' . $e->getMessage(), 'SAVED_VIEWS');
            return array(
                'total_views' => 0,
                'most_used' => null,
                'recently_created' => null,
                'total_usage' => 0
            );
        }
    }
}
