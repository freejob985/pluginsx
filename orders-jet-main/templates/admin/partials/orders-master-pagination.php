<?php
/**
 * Orders Master V2 - Pagination Partial
 * 
 * Display pagination controls with record count
 * 
 * @package Orders_Jet
 * @var int $current_page Current page number
 * @var int $total_pages Total number of pages
 * @var int $per_page Items per page
 * @var int $total_orders Total orders count
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

<!-- Pagination -->
<?php if ($total_orders > 0): ?>
    <div class="oj-pagination" style="background: #fff; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px; display: flex; justify-content: space-between; align-items: center;">
        <!-- Record Count -->
        <div style="color: #666; font-size: 14px;">
            <?php 
            $start_record = ($current_page - 1) * $per_page + 1;
            $end_record = min($current_page * $per_page, $total_orders);
            ?>
            Showing <strong><?php echo $start_record; ?>-<?php echo $end_record; ?></strong> of <strong><?php echo $total_orders; ?></strong> orders
        </div>
        
        <!-- Pagination Links -->
        <?php if ($total_pages > 1): ?>
            <div style="display: flex; gap: 5px; align-items: center;">
                <?php 
                // Build URL with all current parameters - SIMPLE & CLEAN!
                $pagination_params = array('page' => 'orders-master-v2');
                
                // Preserve all parameters that exist in the URL
                $preserve_params = array('filter', 'search', 'orderby', 'order', 'date_preset', 'date_from', 'date_to');
                
                foreach ($preserve_params as $param) {
                    if (isset($_GET[$param])) {
                        // Get the already-sanitized variable
                        $var_name = ($param === 'filter') ? 'current_filter' : $param;
                        $pagination_params[$param] = $$var_name;
                    }
                }
                
                $base_url = add_query_arg($pagination_params, admin_url('admin.php'));
                ?>
                
                <!-- Previous Button -->
                <?php if ($current_page > 1): ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $base_url)); ?>" 
                       class="button">← Previous</a>
                <?php endif; ?>
                
                <!-- Page Numbers -->
                <?php 
                $range = 2; // Show 2 pages on each side of current page
                $start_page = max(1, $current_page - $range);
                $end_page = min($total_pages, $current_page + $range);
                
                if ($start_page > 1): ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', 1, $base_url)); ?>" 
                       class="button">1</a>
                    <?php if ($start_page > 2): ?>
                        <span style="padding: 0 5px;">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $i, $base_url)); ?>" 
                       class="button <?php echo $i === $current_page ? 'button-primary' : ''; ?>"
                       style="<?php echo $i === $current_page ? 'pointer-events: none;' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span style="padding: 0 5px;">...</span>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $total_pages, $base_url)); ?>" 
                       class="button"><?php echo $total_pages; ?></a>
                <?php endif; ?>
                
                <!-- Next Button -->
                <?php if ($current_page < $total_pages): ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $base_url)); ?>" 
                       class="button">Next →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

