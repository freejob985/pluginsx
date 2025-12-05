<?php
/**
 * BI Toolbar (Group By + Sorting)
 */
if (!defined('ABSPATH')) exit;
?>

<div class="oj-bi-toolbar" <?php echo $bi_mode === 'individual' ? 'style="display: none;"' : ''; ?>>
    <div class="oj-toolbar-section">
        <label for="oj-bi-group-by"><?php _e('Group Business Data By:', 'orders-jet'); ?></label>
        <select id="oj-bi-group-by" name="group_by" class="oj-bi-select">
            <option value="day" <?php selected($group_by, 'day'); ?>><?php _e('📅 Daily Performance', 'orders-jet'); ?></option>
            <option value="waiter" <?php selected($group_by, 'waiter'); ?>><?php _e('👨‍💼 Staff Performance', 'orders-jet'); ?></option>
            <option value="shift" <?php selected($group_by, 'shift'); ?>><?php _e('🕐 Shift Analysis', 'orders-jet'); ?></option>
            <option value="table" <?php selected($group_by, 'table'); ?>><?php _e('🍽️ Table Performance', 'orders-jet'); ?></option>
            <option value="discount_status" <?php selected($group_by, 'discount_status'); ?>><?php _e('💰 Discount Analysis', 'orders-jet'); ?></option>
        </select>
    </div>
    
    <div class="oj-toolbar-actions">
        <button class="button button-secondary" id="oj-open-bi-filters">
            <span class="dashicons dashicons-filter"></span>
            <?php _e('BI Filters', 'orders-jet'); ?>
        </button>
    </div>
</div>
