<?php
/**
 * BI Pagination Component
 * 
 * Displays pagination controls for Individual Orders mode
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only show pagination for individual mode with pagination info
if (empty($pagination_info) || $bi_mode !== 'individual') {
    return;
}

$current_page = $pagination_info['current_page'];
$total_pages = $pagination_info['total_pages'];
$total_orders = $pagination_info['total_orders'];
$per_page = $pagination_info['per_page'];
$start_order = $pagination_info['start_order'];
$end_order = $pagination_info['end_order'];
$has_prev = $pagination_info['has_prev'];
$has_next = $pagination_info['has_next'];

// Don't show pagination if there's only one page or no orders
if ($total_pages <= 1) {
    return;
}

// Get current URL parameters for pagination links
$current_url = remove_query_arg('paged');
?>

<div class="oj-bi-pagination">
    <div class="oj-pagination-info">
        <span class="oj-pagination-text">
            <?php 
            printf(
                __('Showing %d-%d of %d orders', 'orders-jet'),
                $start_order,
                $end_order,
                $total_orders
            ); 
            ?>
        </span>
    </div>
    
    <div class="oj-pagination-controls">
        <?php if ($has_prev): ?>
        <a href="<?php echo esc_url(add_query_arg('paged', 1, $current_url)); ?>" 
           class="oj-pagination-btn oj-pagination-first" 
           title="<?php _e('First Page', 'orders-jet'); ?>">
            <span class="dashicons dashicons-controls-skipback"></span>
        </a>
        
        <a href="<?php echo esc_url(add_query_arg('paged', $pagination_info['prev_page'], $current_url)); ?>" 
           class="oj-pagination-btn oj-pagination-prev" 
           title="<?php _e('Previous Page', 'orders-jet'); ?>">
            <span class="dashicons dashicons-controls-back"></span>
        </a>
        <?php else: ?>
        <span class="oj-pagination-btn oj-pagination-disabled">
            <span class="dashicons dashicons-controls-skipback"></span>
        </span>
        <span class="oj-pagination-btn oj-pagination-disabled">
            <span class="dashicons dashicons-controls-back"></span>
        </span>
        <?php endif; ?>
        
        <div class="oj-pagination-pages">
            <?php
            // Calculate page range to show
            $range = 2; // Show 2 pages before and after current
            $start_page = max(1, $current_page - $range);
            $end_page = min($total_pages, $current_page + $range);
            
            // Show first page if not in range
            if ($start_page > 1) {
                ?>
                <a href="<?php echo esc_url(add_query_arg('paged', 1, $current_url)); ?>" 
                   class="oj-pagination-page">1</a>
                <?php if ($start_page > 2): ?>
                <span class="oj-pagination-ellipsis">...</span>
                <?php endif; ?>
                <?php
            }
            
            // Show page range
            for ($i = $start_page; $i <= $end_page; $i++) {
                if ($i == $current_page) {
                    ?>
                    <span class="oj-pagination-page oj-pagination-current"><?php echo $i; ?></span>
                    <?php
                } else {
                    ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $i, $current_url)); ?>" 
                       class="oj-pagination-page"><?php echo $i; ?></a>
                    <?php
                }
            }
            
            // Show last page if not in range
            if ($end_page < $total_pages) {
                ?>
                <?php if ($end_page < $total_pages - 1): ?>
                <span class="oj-pagination-ellipsis">...</span>
                <?php endif; ?>
                <a href="<?php echo esc_url(add_query_arg('paged', $total_pages, $current_url)); ?>" 
                   class="oj-pagination-page"><?php echo $total_pages; ?></a>
                <?php
            }
            ?>
        </div>
        
        <?php if ($has_next): ?>
        <a href="<?php echo esc_url(add_query_arg('paged', $pagination_info['next_page'], $current_url)); ?>" 
           class="oj-pagination-btn oj-pagination-next" 
           title="<?php _e('Next Page', 'orders-jet'); ?>">
            <span class="dashicons dashicons-controls-forward"></span>
        </a>
        
        <a href="<?php echo esc_url(add_query_arg('paged', $total_pages, $current_url)); ?>" 
           class="oj-pagination-btn oj-pagination-last" 
           title="<?php _e('Last Page', 'orders-jet'); ?>">
            <span class="dashicons dashicons-controls-skipforward"></span>
        </a>
        <?php else: ?>
        <span class="oj-pagination-btn oj-pagination-disabled">
            <span class="dashicons dashicons-controls-forward"></span>
        </span>
        <span class="oj-pagination-btn oj-pagination-disabled">
            <span class="dashicons dashicons-controls-skipforward"></span>
        </span>
        <?php endif; ?>
    </div>
</div>
