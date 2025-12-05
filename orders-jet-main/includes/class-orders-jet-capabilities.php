<?php
/**
 * Orders Jet - Capabilities Management
 * WordPress-native approach: Add capabilities to existing roles + user meta for specialization
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Capabilities {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add capabilities to WordPress roles on init
        add_action('init', array($this, 'add_capabilities_to_roles'), 1);
        
        // Add user profile fields
        add_action('show_user_profile', array($this, 'add_user_profile_fields'));
        add_action('edit_user_profile', array($this, 'add_user_profile_fields'));
        
        // Save user profile fields
        add_action('personal_options_update', array($this, 'save_user_profile_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_profile_fields'));
    }
    
    /**
     * Add Orders Jet capabilities to existing WordPress roles
     */
    public function add_capabilities_to_roles() {
        // Get roles
        $administrator = get_role('administrator');
        $shop_manager = get_role('shop_manager');
        $editor = get_role('editor');
        
        // Define Orders Jet capabilities
        $manager_caps = array(
            'access_oj_manager_dashboard' => true,
            'manage_oj_orders' => true,
            'manage_oj_staff' => true,
            'view_oj_reports' => true,
            'manage_oj_tables' => true,
            'close_oj_tables' => true,
            'view_oj_financials' => true,
        );
        
        $kitchen_caps = array(
            'access_oj_kitchen_dashboard' => true,
            'view_oj_kitchen_orders' => true,
            'update_oj_order_status' => true,
            'mark_oj_order_ready' => true,
        );
        
        $waiter_caps = array(
            'access_oj_waiter_dashboard' => true,
            'manage_oj_tables' => true,
            'close_oj_tables' => true,
            'view_oj_assigned_tables' => true,
        );
        
        // Administrator gets everything
        if ($administrator) {
            foreach (array_merge($manager_caps, $kitchen_caps, $waiter_caps) as $cap => $grant) {
                $administrator->add_cap($cap, $grant);
            }
        }
        
        // Shop Manager gets manager capabilities
        if ($shop_manager) {
            foreach ($manager_caps as $cap => $grant) {
                $shop_manager->add_cap($cap, $grant);
            }
        }
        
        // Editor can be assigned kitchen or waiter function via meta
        // We add all capabilities, then check user meta to determine actual access
        if ($editor) {
            foreach (array_merge($kitchen_caps, $waiter_caps) as $cap => $grant) {
                $editor->add_cap($cap, $grant);
            }
        }
    }
    
    /**
     * Get user's Orders Jet function from meta
     * 
     * @param int $user_id User ID (null for current user)
     * @return string|false 'manager', 'kitchen', 'waiter', or false
     */
    public function get_user_function($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // Check user's role first
        if (in_array('administrator', $user->roles)) {
            return 'manager'; // Admins are managers
        }
        
        if (in_array('shop_manager', $user->roles)) {
            return 'manager'; // Shop managers are restaurant managers
        }
        
        // For editors, check meta field
        if (in_array('editor', $user->roles)) {
            $function = get_user_meta($user_id, '_oj_function', true);
            return $function ?: false;
        }
        
        return false;
    }
    
    /**
     * Get user's kitchen specialization
     * 
     * @param int $user_id User ID (null for current user)
     * @return string|false 'food', 'beverages', 'both', or false
     */
    public function get_kitchen_specialization($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if ($this->get_user_function($user_id) !== 'kitchen') {
            return false;
        }
        
        $specialization = get_user_meta($user_id, '_oj_kitchen_type', true);
        return $specialization ?: 'both'; // Default to both
    }
    
    /**
     * Set user's Orders Jet function
     * 
     * @param int $user_id User ID
     * @param string $function 'kitchen' or 'waiter'
     * @param string $kitchen_type 'food', 'beverages', or 'both' (if kitchen)
     * @return bool Success
     */
    public function set_user_function($user_id, $function, $kitchen_type = 'both') {
        if (!in_array($function, array('kitchen', 'waiter'))) {
            return false;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }
        
        // User must be an editor to have OJ function
        if (!in_array('editor', $user->roles)) {
            // Assign editor role
            $user->add_role('editor');
        }
        
        // Store function in meta
        update_user_meta($user_id, '_oj_function', $function);
        
        // Store kitchen specialization if applicable
        if ($function === 'kitchen') {
            update_user_meta($user_id, '_oj_kitchen_type', $kitchen_type);
        }
        
        oj_debug_log("Set user {$user_id} function to {$function}", 'OJ_CAPABILITIES');
        
        return true;
    }
    
    /**
     * Add user profile fields
     */
    public function add_user_profile_fields($user) {
        // Only show for users who can manage staff or editing themselves
        if (!current_user_can('manage_oj_staff') && get_current_user_id() !== $user->ID) {
            return;
        }
        
        $function = get_user_meta($user->ID, '_oj_function', true);
        $kitchen_type = get_user_meta($user->ID, '_oj_kitchen_type', true);
        ?>
        <h2><?php _e('Orders Jet Settings', 'orders-jet'); ?></h2>
        <table class="form-table">
            <tr>
                <th>
                    <label for="oj_function"><?php _e('Restaurant Function', 'orders-jet'); ?></label>
                </th>
                <td>
                    <select name="oj_function" id="oj_function">
                        <option value=""><?php _e('-- None --', 'orders-jet'); ?></option>
                        <option value="kitchen" <?php selected($function, 'kitchen'); ?>><?php _e('Kitchen Staff', 'orders-jet'); ?></option>
                        <option value="waiter" <?php selected($function, 'waiter'); ?>><?php _e('Waiter', 'orders-jet'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('Assign this user a restaurant function. Administrator and Shop Manager roles are automatically Restaurant Managers.', 'orders-jet'); ?>
                    </p>
                </td>
            </tr>
            <tr id="oj_kitchen_type_row" style="<?php echo $function !== 'kitchen' ? 'display:none;' : ''; ?>">
                <th>
                    <label for="oj_kitchen_type"><?php _e('Kitchen Specialization', 'orders-jet'); ?></label>
                </th>
                <td>
                    <select name="oj_kitchen_type" id="oj_kitchen_type">
                        <option value="both" <?php selected($kitchen_type, 'both'); ?>><?php _e('Both (Food & Beverages)', 'orders-jet'); ?></option>
                        <option value="food" <?php selected($kitchen_type, 'food'); ?>><?php _e('Food Kitchen', 'orders-jet'); ?></option>
                        <option value="beverages" <?php selected($kitchen_type, 'beverages'); ?>><?php _e('Beverage Station', 'orders-jet'); ?></option>
                    </select>
                    <p class="description">
                        <?php _e('For kitchen staff, specify their specialization.', 'orders-jet'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#oj_function').on('change', function() {
                if ($(this).val() === 'kitchen') {
                    $('#oj_kitchen_type_row').show();
                } else {
                    $('#oj_kitchen_type_row').hide();
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save user profile fields
     */
    public function save_user_profile_fields($user_id) {
        // Check permissions
        if (!current_user_can('manage_oj_staff') && get_current_user_id() !== $user_id) {
            return;
        }
        
        // Save function
        if (isset($_POST['oj_function'])) {
            $function = sanitize_text_field($_POST['oj_function']);
            
            if (empty($function)) {
                delete_user_meta($user_id, '_oj_function');
                delete_user_meta($user_id, '_oj_kitchen_type');
            } else {
                update_user_meta($user_id, '_oj_function', $function);
                
                // Save kitchen type if applicable
                if ($function === 'kitchen' && isset($_POST['oj_kitchen_type'])) {
                    $kitchen_type = sanitize_text_field($_POST['oj_kitchen_type']);
                    update_user_meta($user_id, '_oj_kitchen_type', $kitchen_type);
                }
            }
        }
    }
    
    /**
     * Remove capabilities on plugin deactivation
     */
    public static function remove_capabilities() {
        $roles = array('administrator', 'shop_manager', 'editor');
        $caps = array(
            'access_oj_manager_dashboard',
            'manage_oj_orders',
            'manage_oj_staff',
            'view_oj_reports',
            'manage_oj_tables',
            'close_oj_tables',
            'view_oj_financials',
            'access_oj_kitchen_dashboard',
            'view_oj_kitchen_orders',
            'update_oj_order_status',
            'mark_oj_order_ready',
            'access_oj_waiter_dashboard',
            'view_oj_assigned_tables',
        );
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                foreach ($caps as $cap) {
                    $role->remove_cap($cap);
                }
            }
        }
        
        oj_debug_log('Orders Jet capabilities removed from roles', 'OJ_CAPABILITIES');
    }
}

// Global helper function
if (!function_exists('oj_get_user_function')) {
    function oj_get_user_function($user_id = null) {
        static $capabilities = null;
        if ($capabilities === null) {
            $capabilities = new Orders_Jet_Capabilities();
        }
        return $capabilities->get_user_function($user_id);
    }
}

if (!function_exists('oj_get_kitchen_specialization')) {
    function oj_get_kitchen_specialization($user_id = null) {
        static $capabilities = null;
        if ($capabilities === null) {
            $capabilities = new Orders_Jet_Capabilities();
        }
        return $capabilities->get_kitchen_specialization($user_id);
    }
}

