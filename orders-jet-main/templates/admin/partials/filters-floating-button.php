<?php
/**
 * Filters Floating Button - Trigger for Advanced Filters Panel
 * 
 * @package Orders_Jet
 * @version 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Floating Filters Button -->
<button id="oj-filters-trigger" class="oj-filters-trigger" type="button" title="<?php _e('Advanced Filters', 'orders-jet'); ?>">
    <span class="oj-filters-icon">🔧</span>
    <span class="oj-filters-text"><?php _e('More Filters', 'orders-jet'); ?></span>
    <span class="oj-filters-count" id="oj-filters-count" style="display: none;">0</span>
</button>
