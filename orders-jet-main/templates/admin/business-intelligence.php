<?php
/**
 * Business Intelligence Dashboard - Main Template
 * 
 * Purpose-built BI interface separate from Orders Reports
 * 
 * @package Orders_Jet
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Load BI-specific dependencies
require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-master-query-builder.php';
require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-bi-query-builder.php';
require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-bi-insights.php';

// Get current user info
$current_user = wp_get_current_user();
$user_role = oj_get_user_role();

// Initialize BI parameters
$bi_mode = isset($_GET['bi_mode']) ? sanitize_text_field($_GET['bi_mode']) : 'grouped';
$group_by = isset($_GET['group_by']) ? sanitize_text_field($_GET['group_by']) : 'day';

// Initialize BI Query Builder (extends Orders_Master_Query_Builder)
$bi_query_builder = new Orders_BI_Query_Builder($_GET);

// Get BI insights
$bi_insights = new Orders_BI_Insights($bi_query_builder);
$insights_data = $bi_insights->calculate_bi_insights();

// Get grouped or individual data based on mode
$display_data = $bi_query_builder->get_bi_data();

// Get pagination info for individual mode
$pagination_info = $bi_query_builder->get_pagination_info();

// Get summary statistics for debugging
$summary_stats = $bi_query_builder->get_summary_statistics();

?>

<div class="wrap oj-business-intelligence">
    <!-- BI Header -->
    <?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/bi/bi-header.php'; ?>
    
    <!-- BI Mode Toggle Component (NEW) -->
    <?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/bi/bi-mode-toggle.php'; ?>
    
    <!-- Row 3: Business Intelligence Overview (BI Insights Cards) -->
    <?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/bi/bi-insights-cards.php'; ?>
    
    <!-- Row 4: Business Data Analysis (BI Content Area) -->
    <?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/bi/bi-content-area.php'; ?>
</div>

<!-- BI Filters Panel -->
<?php include ORDERS_JET_PLUGIN_DIR . 'templates/admin/partials/bi/bi-filters-panel.php'; ?>

<!-- Basic JavaScript for Step 2 Testing -->
<script>
jQuery(document).ready(function($) {
    // Mode toggle functionality
    $('.oj-mode-btn').on('click', function() {
        const mode = $(this).data('mode');
        
        // Update active state
        $('.oj-mode-btn').removeClass('active');
        $(this).addClass('active');
        
        // Show/hide toolbar
        if (mode === 'grouped') {
            $('.oj-bi-toolbar').slideDown();
        } else {
            $('.oj-bi-toolbar').slideUp();
        }
        
        // Update content area
        if (mode === 'grouped') {
            $('#oj-bi-content').html('<div class="oj-bi-grouped-placeholder"><div class="oj-placeholder-card"><h3>📊 Grouped Business Intelligence</h3><p>Switched to Grouped Reports mode</p></div></div>');
        } else {
            $('#oj-bi-content').html('<div class="oj-bi-individual-placeholder"><div class="oj-placeholder-card"><h3>📋 Individual Orders Analysis</h3><p>Switched to Individual Orders mode</p></div></div>');
        }
        
        console.log('BI Mode switched to:', mode);
    });
    
    // Group by change
    $('#oj-bi-group-by').on('change', function() {
        const groupBy = $(this).val();
        const groupLabel = $(this).find('option:selected').text();
        
        $('#oj-bi-content').html('<div class="oj-bi-grouped-placeholder"><div class="oj-placeholder-card"><h3>📊 Grouped Business Intelligence</h3><p>Grouped by: <strong>' + groupLabel + '</strong></p><p>Group by changed to: ' + groupBy + '</p></div></div>');
        
        console.log('Group by changed to:', groupBy);
    });
    
    // Placeholder button handlers
    $('#oj-export-bi-report').on('click', function() {
        alert('Export BI Report functionality will be implemented in later steps');
    });
    
    $('#oj-save-bi-view').on('click', function() {
        alert('Save BI View functionality will be implemented in later steps');
    });
    
    // BI Filters Panel functionality (Following Orders Master Pattern)
    function openBIFilters() {
        const $overlay = $('#oj-bi-filters-overlay');
        const $button = $('#oj-open-bi-filters');
        
        $overlay.addClass('active');
        $button.addClass('active');
        $('body').addClass('oj-filters-open'); // Prevent body scroll
        console.log('BI Filters panel opened');
    }
    
    function closeBIFilters() {
        const $overlay = $('#oj-bi-filters-overlay');
        const $button = $('#oj-open-bi-filters');
        
        $overlay.removeClass('active');
        $button.removeClass('active');
        $('body').removeClass('oj-filters-open');
        console.log('BI Filters panel closed');
    }
    
    $('#oj-open-bi-filters').on('click', function(e) {
        e.preventDefault();
        const $overlay = $('#oj-bi-filters-overlay');
        
        if ($overlay.hasClass('active')) {
            closeBIFilters();
        } else {
            openBIFilters();
        }
    });
    
    $('#oj-close-bi-filters').on('click', function(e) {
        e.preventDefault();
        closeBIFilters();
    });
    
    // Close filters when clicking overlay (outside panel)
    $('#oj-bi-filters-overlay').on('click', function(e) {
        // Only close if clicking the overlay itself, not the panel
        if (e.target === this) {
            closeBIFilters();
        }
    });
    
    // Close filters with Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#oj-bi-filters-overlay').hasClass('active')) {
            closeBIFilters();
        }
    });
    
    console.log('Business Intelligence Dashboard loaded successfully');
});
</script>
