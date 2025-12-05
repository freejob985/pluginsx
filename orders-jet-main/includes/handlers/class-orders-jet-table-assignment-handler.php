<?php
declare(strict_types=1);
/**
 * Orders Jet - Table Assignment Handler Class
 * Handles table assignment operations with proper encapsulation
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Table_Assignment_Handler {
    
    private $notification_service;
    
    public function __construct($notification_service = null) {
        $this->notification_service = $notification_service ?: new Orders_Jet_Notification_Service();
    }
    
    /**
     * Get all tables with assignment data
     * 
     * @return array Array of table data with assignments
     */
    public function get_tables_with_assignments() {
        // Get all tables using meta keys constants
        $tables = get_posts(array(
            'post_type' => 'oj_table',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ));
        
        // Get all current assignments
        $assignments = $this->get_all_assignments();
        
        $table_data = array();
        foreach ($tables as $table) {
            $table_number = get_post_meta($table->ID, WooJet_Meta_Keys::TABLE_POST_NUMBER, true);
            
            // Skip tables without proper table number
            if (empty($table_number)) continue;
            
            $table_data[] = array(
                'id' => $table->ID,
                'title' => $table->post_title,
                'number' => $table_number,
                'capacity' => get_post_meta($table->ID, WooJet_Meta_Keys::TABLE_CAPACITY, true),
                'status' => get_post_meta($table->ID, WooJet_Meta_Keys::TABLE_STATUS, true) ?: 'available',
                'location' => get_post_meta($table->ID, WooJet_Meta_Keys::TABLE_LOCATION, true),
                'assigned_waiter' => $assignments[$table_number] ?? null
            );
        }
        
        return $table_data;
    }
    
    /**
     * Get all staff members who can be assigned tables
     * 
     * @return array Array of staff members
     */
    public function get_assignable_staff() {
        // Get waiters
        $waiters = get_users(array(
            'meta_key' => WooJet_Meta_Keys::USER_FUNCTION,
            'meta_value' => 'waiter',
            'fields' => array('ID', 'display_name')
        ));
        
        // Get managers
        $managers = get_users(array(
            'meta_key' => WooJet_Meta_Keys::USER_FUNCTION,
            'meta_value' => 'manager',
            'fields' => array('ID', 'display_name')
        ));
        
        return array_merge($waiters, $managers);
    }
    
    /**
     * Get all current table assignments
     * 
     * @return array Table assignments indexed by table number
     */
    public function get_all_assignments() {
        global $wpdb;
        
        // Optimized query to get all assignments at once
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT u.ID, u.display_name, um.meta_value as assigned_tables
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
            WHERE um.meta_key = %s
            AND um.meta_value != ''
        ", WooJet_Meta_Keys::ASSIGNED_TABLES));
        
        $assignments = array();
        foreach ($results as $result) {
            $assigned_tables = maybe_unserialize($result->assigned_tables);
            if (is_array($assigned_tables)) {
                foreach ($assigned_tables as $table_number) {
                    $assignments[$table_number] = (object) array(
                        'ID' => $result->ID,
                        'display_name' => $result->display_name
                    );
                }
            }
        }
        
        return $assignments;
    }
    
    /**
     * Assign table to waiter
     * 
     * @param string $table_number Table number to assign
     * @param int $waiter_id Waiter user ID
     * @return array Result array
     */
    public function assign_table($table_number, $waiter_id) {
        if (empty($table_number) || empty($waiter_id)) {
            return array('success' => false, 'message' => __('Table number and waiter are required', 'orders-jet'));
        }
        
        // Validate waiter exists and has proper function
        $waiter = get_user_by('id', $waiter_id);
        if (!$waiter) {
            return array('success' => false, 'message' => __('Waiter not found', 'orders-jet'));
        }
        
        $waiter_function = get_user_meta($waiter_id, WooJet_Meta_Keys::USER_FUNCTION, true);
        if (!in_array($waiter_function, array('waiter', 'manager'))) {
            return array('success' => false, 'message' => __('User cannot be assigned tables', 'orders-jet'));
        }
        
        // Remove table from any existing assignments
        $this->unassign_table_from_all($table_number);
        
        // Add table to waiter's assignments
        $assigned_tables = get_user_meta($waiter_id, WooJet_Meta_Keys::ASSIGNED_TABLES, true);
        if (!is_array($assigned_tables)) {
            $assigned_tables = array();
        }
        
        if (!in_array($table_number, $assigned_tables)) {
            $assigned_tables[] = $table_number;
            update_user_meta($waiter_id, WooJet_Meta_Keys::ASSIGNED_TABLES, $assigned_tables);
        }
        
        // CRITICAL: Also set reverse relationship on table post
        $table_id = $this->get_table_id_by_number($table_number);
        if ($table_id) {
            oj_debug_log("Setting post meta for table {$table_number} (ID: {$table_id}) - Key: " . WooJet_Meta_Keys::ASSIGNED_WAITER . ", Value: {$waiter_id}", 'TABLE_ASSIGNMENT');
            
            update_post_meta($table_id, WooJet_Meta_Keys::ASSIGNED_WAITER, $waiter_id);
            
            // Verify the meta was saved
            $saved_waiter = get_post_meta($table_id, WooJet_Meta_Keys::ASSIGNED_WAITER, true);
            oj_debug_log("✅ Table {$table_number} (ID: {$table_id}) assigned to waiter {$waiter_id} - Verification: " . var_export($saved_waiter, true), 'TABLE_ASSIGNMENT');
        } else {
            oj_debug_log("❌ Could not find table ID for table number: {$table_number}", 'TABLE_ASSIGNMENT');
        }
        
        // Log assignment
        oj_debug_log("Table {$table_number} assigned to waiter {$waiter->display_name} (ID: {$waiter_id})", 'TABLE_ASSIGNMENT');
        
        // Send notification (optional)
        $this->notification_service->store_dashboard_notification(array(
            'type' => 'table_assigned',
            'table_number' => $table_number,
            'waiter_id' => $waiter_id,
            'message' => sprintf(__('Table %s assigned to %s', 'orders-jet'), $table_number, $waiter->display_name)
        ));
        
        return array(
            'success' => true, 
            'message' => sprintf(__('Table %s assigned to %s', 'orders-jet'), $table_number, $waiter->display_name)
        );
    }
    
    /**
     * Get table post ID by table number
     * 
     * @param string $table_number Table number
     * @return int|null Table post ID or null
     */
    private function get_table_id_by_number($table_number) {
        global $wpdb;
        
        // Use meta field lookup instead of post_title
        $table_post = $wpdb->get_row($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'oj_table' 
             AND p.post_status = 'publish'
             AND pm.meta_key = %s
             AND pm.meta_value = %s",
            WooJet_Meta_Keys::TABLE_POST_NUMBER,
            $table_number
        ));
        
        return $table_post ? $table_post->ID : null;
    }
    
    /**
     * Unassign table from waiter
     * 
     * @param string $table_number Table number to unassign
     * @return array Result array
     */
    public function unassign_table($table_number) {
        if (empty($table_number)) {
            return array('success' => false, 'message' => __('Table number is required', 'orders-jet'));
        }
        
        $unassigned = $this->unassign_table_from_all($table_number);
        
        if ($unassigned) {
            oj_debug_log("Table {$table_number} unassigned", 'TABLE_ASSIGNMENT');
            return array('success' => true, 'message' => sprintf(__('Table %s unassigned', 'orders-jet'), $table_number));
        } else {
            return array('success' => false, 'message' => sprintf(__('Table %s was not assigned', 'orders-jet'), $table_number));
        }
    }
    
    /**
     * Bulk assign tables to waiter
     * 
     * @param array $table_numbers Array of table numbers
     * @param int $waiter_id Waiter user ID
     * @return array Result array
     */
    public function bulk_assign_tables($table_numbers, $waiter_id) {
        if (empty($table_numbers) || !is_array($table_numbers) || empty($waiter_id)) {
            return array('success' => false, 'message' => __('Table numbers and waiter are required', 'orders-jet'));
        }
        
        $waiter = get_user_by('id', $waiter_id);
        if (!$waiter) {
            return array('success' => false, 'message' => __('Waiter not found', 'orders-jet'));
        }
        
        $assigned_count = 0;
        foreach ($table_numbers as $table_number) {
            $result = $this->assign_table($table_number, $waiter_id);
            if ($result['success']) {
                $assigned_count++;
            }
        }
        
        return array(
            'success' => true,
            'message' => sprintf(__('%d tables assigned to %s', 'orders-jet'), $assigned_count, $waiter->display_name),
            'assigned_count' => $assigned_count
        );
    }
    
    /**
     * Bulk unassign tables
     * 
     * @param array $table_numbers Array of table numbers
     * @return array Result array
     */
    public function bulk_unassign_tables($table_numbers) {
        if (empty($table_numbers) || !is_array($table_numbers)) {
            return array('success' => false, 'message' => __('Table numbers are required', 'orders-jet'));
        }
        
        $unassigned_count = 0;
        foreach ($table_numbers as $table_number) {
            $result = $this->unassign_table($table_number);
            if ($result['success']) {
                $unassigned_count++;
            }
        }
        
        return array(
            'success' => true,
            'message' => sprintf(__('%d tables unassigned', 'orders-jet'), $unassigned_count),
            'unassigned_count' => $unassigned_count
        );
    }
    
    /**
     * Remove table from all waiter assignments
     * 
     * @param string $table_number Table number to remove
     * @return bool True if table was found and removed
     */
    private function unassign_table_from_all($table_number) {
        global $wpdb;
        
        // Find all users with this table assigned
        $users_with_table = $wpdb->get_results($wpdb->prepare("
            SELECT user_id, meta_value
            FROM {$wpdb->usermeta}
            WHERE meta_key = %s
            AND meta_value LIKE %s
        ", WooJet_Meta_Keys::ASSIGNED_TABLES, '%' . $wpdb->esc_like($table_number) . '%'));
        
        $found = false;
        foreach ($users_with_table as $user_meta) {
            $assigned_tables = maybe_unserialize($user_meta->meta_value);
            if (is_array($assigned_tables) && in_array($table_number, $assigned_tables)) {
                $assigned_tables = array_diff($assigned_tables, array($table_number));
                update_user_meta($user_meta->user_id, WooJet_Meta_Keys::ASSIGNED_TABLES, $assigned_tables);
                $found = true;
            }
        }
        
        // CRITICAL: Also remove reverse relationship from table post
        if ($found) {
            $table_id = $this->get_table_id_by_number($table_number);
            if ($table_id) {
                delete_post_meta($table_id, WooJet_Meta_Keys::ASSIGNED_WAITER);
                oj_debug_log("Table {$table_number} (ID: {$table_id}) unassigned from waiter", 'TABLE_ASSIGNMENT');
            }
        }
        
        return $found;
    }
    
    /**
     * Get available tables (not assigned to anyone)
     * 
     * @return array Array of available table numbers
     */
    public function get_available_tables() {
        $all_tables = $this->get_tables_with_assignments();
        $available = array();
        
        foreach ($all_tables as $table) {
            if (empty($table['assigned_waiter'])) {
                $available[] = $table;
            }
        }
        
        return $available;
    }
    
    /**
     * Find waiter assigned to a specific table
     * 
     * @param string $table_number Table number to check
     * @return int|null Waiter user ID or null if not assigned
     */
    public function find_assigned_waiter($table_number) {
        $assignments = $this->get_all_assignments();
        return $assignments[$table_number]->ID ?? null;
    }
}
