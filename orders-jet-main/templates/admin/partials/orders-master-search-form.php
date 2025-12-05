<?php
/**
 * Orders Master V2 - Search & Sort Form Partial
 * 
 * @package Orders_Jet
 * @var string $current_filter Current filter
 * @var string $search Search term
 * @var string $orderby Sort field
 * @var string $order Sort direction
 * @var string $date_preset Date preset
 * @var string $date_from Date from
 * @var string $date_to Date to
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Search & Sort Form -->
<div style="background: #fff; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">
    <form method="get" action="" style="display: flex; gap: 10px; align-items: center;">
        <input type="hidden" name="page" value="orders-master-v2">
        <input type="hidden" name="filter" value="<?php echo esc_attr($current_filter); ?>">
        
        <!-- Preserve date range parameters -->
        <?php if (!empty($date_preset)): ?>
            <input type="hidden" name="date_preset" value="<?php echo esc_attr($date_preset); ?>">
        <?php endif; ?>
        <?php if (!empty($date_from)): ?>
            <input type="hidden" name="date_from" value="<?php echo esc_attr($date_from); ?>">
        <?php endif; ?>
        <?php if (!empty($date_to)): ?>
            <input type="hidden" name="date_to" value="<?php echo esc_attr($date_to); ?>">
        <?php endif; ?>
        
        <!-- Search Input -->
        <label style="font-weight: bold;">🔍 Search:</label>
        <input type="text" 
               name="search" 
               value="<?php echo esc_attr($search); ?>"
               placeholder="Order # (909), Table (T18), or Customer name"
               style="padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px; width: 300px;">
        
        <!-- Spacer to push sort to the right -->
        <div style="flex: 1;"></div>
        
        <!-- Sort By -->
        <label style="font-weight: bold;">📊 Sort:</label>
        <select name="orderby" style="padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="date_created" <?php selected($orderby, 'date_created'); ?>>Date Created</option>
            <option value="date_modified" <?php selected($orderby, 'date_modified'); ?>>Date Modified</option>
        </select>
        
        <!-- Sort Direction -->
        <select name="order" style="padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px;">
            <option value="DESC" <?php selected($order, 'DESC'); ?>>Newest First (DESC)</option>
            <option value="ASC" <?php selected($order, 'ASC'); ?>>Oldest First (ASC)</option>
        </select>
        
        <button type="submit" class="button button-primary">Apply</button>
        
        <?php if (!empty($search) || $orderby !== 'date_created' || $order !== 'DESC'): ?>
            <?php
            // Build reset URL preserving filter and date range
            $reset_params = array(
                'page' => 'orders-master-v2',
                'filter' => $current_filter
            );
            if (!empty($date_preset)) {
                $reset_params['date_preset'] = $date_preset;
            }
            if (!empty($date_from)) {
                $reset_params['date_from'] = $date_from;
            }
            if (!empty($date_to)) {
                $reset_params['date_to'] = $date_to;
            }
            $reset_url = add_query_arg($reset_params, admin_url('admin.php'));
            ?>
            <a href="<?php echo esc_url($reset_url); ?>" class="button">Reset</a>
        <?php endif; ?>
    </form>
</div>

