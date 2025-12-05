<?php
declare(strict_types=1);
/**
 * Orders Jet - Waiter Tables Admin UI
 * Manages table assignments for waiters in user edit screen
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Waiter_Tables_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add meta box to user edit screen
        add_action('show_user_profile', array($this, 'render_table_assignment_section'));
        add_action('edit_user_profile', array($this, 'render_table_assignment_section'));
        
        // Save table assignments
        add_action('personal_options_update', array($this, 'save_table_assignments'));
        add_action('edit_user_profile_update', array($this, 'save_table_assignments'));
    }
    
    /**
     * Render table assignment section in user profile
     */
    public function render_table_assignment_section($user) {
        // Only show for admins editing users
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get user object and roles
        $user_obj = new WP_User($user->ID);
        $user_roles = $user_obj->roles;
        
        // Get Orders Jet function using the standard helper
        // This returns: 'kitchen', 'waiter', 'manager', or false
        $user_function = oj_get_user_function($user->ID);
        
        // Check if user can be assigned tables
        // Waiter function OR manager function (admins/shop managers get 'manager')
        $is_waiter = in_array($user_function, array('waiter', 'manager'));
        
        // Always show section for admins, but warn if user doesn't have right role
        if (!$is_waiter) {
            ?>
            <h2><?php _e('🍽️ Table Assignments', 'orders-jet'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php _e('Assigned Tables', 'orders-jet'); ?></th>
                    <td>
                        <div style="padding: 12px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px;">
                            <p style="margin: 0;">
                                <strong>⚠️ <?php _e('Note:', 'orders-jet'); ?></strong>
                                <?php _e('This user cannot be assigned tables. Table assignments are only available for Waiters and Managers.', 'orders-jet'); ?>
                            </p>
                            <p style="margin: 10px 0 0 0;">
                                <strong><?php _e('WordPress Role:', 'orders-jet'); ?></strong>
                                <?php echo !empty($user_roles) ? implode(', ', $user_roles) : __('None', 'orders-jet'); ?>
                            </p>
                            <p style="margin: 10px 0 0 0;">
                                <strong><?php _e('Restaurant Function:', 'orders-jet'); ?></strong>
                                <?php echo $user_function ? esc_html(ucfirst($user_function)) : '<em>' . __('Not set', 'orders-jet') . '</em>'; ?>
                            </p>
                            <p style="margin: 10px 0 0 0; padding-top: 10px; border-top: 1px solid #e0e0e0;">
                                <strong><?php _e('How to enable:', 'orders-jet'); ?></strong><br>
                                1. Set WordPress Role to <strong>Editor</strong> (gives admin access)<br>
                                2. Set Restaurant Function to <strong>Waiter</strong> in "Orders Jet Settings" section below
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
            <?php
            return; // Don't show table assignment for non-waiter users
        }
        
        // Get all available tables
        $tables = get_posts(array(
            'post_type' => 'oj_table',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => 'publish'
        ));
        
        // Get currently assigned tables
        $assigned_tables = get_user_meta($user->ID, '_oj_assigned_tables', true);
        if (!is_array($assigned_tables)) {
            $assigned_tables = array();
        }
        
        ?>
        <h2><?php _e('🍽️ Table Assignments', 'orders-jet'); ?></h2>
        
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php _e('Assigned Tables', 'orders-jet'); ?></th>
                <td>
                    <?php if (empty($tables)): ?>
                        <div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0;">
                            <p style="margin: 0 0 10px 0; color: #721c24;">
                                <strong>❌ <?php _e('No tables found!', 'orders-jet'); ?></strong>
                            </p>
                            <p style="margin: 0 0 10px 0; color: #721c24;">
                                <?php _e('You need to create tables first before you can assign them to waiters.', 'orders-jet'); ?>
                            </p>
                            <a href="<?php echo admin_url('edit.php?post_type=oj_table'); ?>" class="button button-primary">
                                <?php _e('Create Tables Now', 'orders-jet'); ?>
                            </a>
                            <a href="<?php echo admin_url('post-new.php?post_type=oj_table'); ?>" class="button">
                                <?php _e('Add Single Table', 'orders-jet'); ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span><?php _e('Select tables to assign', 'orders-jet'); ?></span>
                            </legend>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; max-width: 600px;">
                                <?php foreach ($tables as $table): ?>
                                    <?php 
                                    // CRITICAL: Use _oj_table_number meta (matches QR code and orders) NOT post_title
                                    $table_number = get_post_meta($table->ID, '_oj_table_number', true);
                                    if (empty($table_number)) {
                                        continue; // Skip tables without proper table number meta
                                    }
                                    $table_display = $table->post_title; // For display only
                                    $is_checked = in_array($table_number, $assigned_tables);
                                    ?>
                                    <label style="display: flex; align-items: center; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: <?php echo $is_checked ? '#e0f2fe' : '#fff'; ?>; cursor: pointer;">
                                        <input 
                                            type="checkbox" 
                                            name="oj_assigned_tables[]" 
                                            value="<?php echo esc_attr($table_number); ?>"
                                            <?php checked($is_checked); ?>
                                            style="margin: 0 8px 0 0;"
                                        >
                                        <span style="font-weight: 500;"><?php echo esc_html($table_display); ?></span>
                                        <span style="font-size: 11px; color: #666; margin-left: 4px;">(<?php echo esc_html($table_number); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            
                            <p class="description" style="margin-top: 10px;">
                                <?php _e('Select which tables this waiter is responsible for. They will only see orders from these tables in their Waiter View.', 'orders-jet'); ?>
                            </p>
                            
                            <?php if (!empty($assigned_tables)): ?>
                                <p style="margin-top: 10px; padding: 10px; background: #f0f9ff; border-left: 3px solid #3b82f6; border-radius: 4px;">
                                    <strong><?php _e('Currently assigned:', 'orders-jet'); ?></strong>
                                    <?php echo implode(', ', $assigned_tables); ?>
                                </p>
                            <?php endif; ?>
                        </fieldset>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <style>
            .oj-table-checkbox:hover {
                background: #f0f9ff !important;
                border-color: #3b82f6 !important;
            }
        </style>
        <?php
    }
    
    /**
     * Save table assignments when user profile is updated
     */
    public function save_table_assignments($user_id) {
        // Check permissions
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }
        
        // Get submitted tables (will be empty array if none checked)
        $assigned_tables = isset($_POST['oj_assigned_tables']) && is_array($_POST['oj_assigned_tables']) 
            ? array_map('sanitize_text_field', $_POST['oj_assigned_tables'])
            : array();
        
        // Update user meta
        update_user_meta($user_id, '_oj_assigned_tables', $assigned_tables);
    }
}

// Initialize the admin UI
new Orders_Jet_Waiter_Tables_Admin();

