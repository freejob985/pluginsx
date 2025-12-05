<?php
/**
 * Test script for BI Query Builder
 * 
 * Run this from WordPress admin or via WP-CLI to test the BI functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // For testing outside WordPress, you can comment this out
    exit('This script must be run within WordPress context');
}

// Load required classes
require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-master-query-builder.php';
require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-bi-query-builder.php';
require_once ORDERS_JET_PLUGIN_DIR . 'includes/classes/class-orders-bi-insights.php';

echo "<h2>🧪 Testing BI Query Builder</h2>\n";

// Test 1: Basic BI Query Builder initialization
echo "<h3>Test 1: Basic Initialization</h3>\n";
try {
    $test_params = array(
        'bi_mode' => 'grouped',
        'group_by' => 'day',
        'filter' => 'all'
    );
    
    $bi_query_builder = new Orders_BI_Query_Builder($test_params);
    echo "✅ BI Query Builder initialized successfully<br>\n";
    echo "Mode: " . $bi_query_builder->get_bi_mode() . "<br>\n";
    echo "Group By: " . $bi_query_builder->get_group_by() . "<br>\n";
    
} catch (Exception $e) {
    echo "❌ Error initializing BI Query Builder: " . $e->getMessage() . "<br>\n";
}

// Test 2: BI Insights initialization
echo "<h3>Test 2: BI Insights Initialization</h3>\n";
try {
    $bi_insights = new Orders_BI_Insights($bi_query_builder);
    echo "✅ BI Insights initialized successfully<br>\n";
    
} catch (Exception $e) {
    echo "❌ Error initializing BI Insights: " . $e->getMessage() . "<br>\n";
}

// Test 3: Get summary statistics
echo "<h3>Test 3: Summary Statistics</h3>\n";
try {
    $summary = $bi_query_builder->get_summary_statistics();
    echo "✅ Summary statistics retrieved<br>\n";
    echo "Total Orders: " . ($summary['total_orders'] ?? 0) . "<br>\n";
    echo "Total Revenue: " . wc_price($summary['total_revenue'] ?? 0) . "<br>\n";
    echo "Avg Order Value: " . wc_price($summary['avg_order_value'] ?? 0) . "<br>\n";
    
} catch (Exception $e) {
    echo "❌ Error getting summary statistics: " . $e->getMessage() . "<br>\n";
}

// Test 4: Get BI insights
echo "<h3>Test 4: BI Insights Calculation</h3>\n";
try {
    $insights = $bi_insights->calculate_bi_insights();
    echo "✅ BI Insights calculated successfully<br>\n";
    echo "Number of insights: " . count($insights) . "<br>\n";
    
    foreach ($insights as $key => $insight) {
        echo "- {$key}: {$insight['title']} = {$insight['value']}<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error calculating BI insights: " . $e->getMessage() . "<br>\n";
}

// Test 5: Test different grouping modes
echo "<h3>Test 5: Different Grouping Modes</h3>\n";
$group_modes = array('day', 'waiter', 'shift', 'table', 'discount_status');

foreach ($group_modes as $mode) {
    try {
        $test_params['group_by'] = $mode;
        $test_builder = new Orders_BI_Query_Builder($test_params);
        $data = $test_builder->get_bi_data();
        
        echo "✅ {$mode} grouping: " . count($data) . " groups found<br>\n";
        
    } catch (Exception $e) {
        echo "❌ Error with {$mode} grouping: " . $e->getMessage() . "<br>\n";
    }
}

// Test 6: Individual mode
echo "<h3>Test 6: Individual Mode</h3>\n";
try {
    $individual_params = array(
        'bi_mode' => 'individual',
        'group_by' => 'day',
        'filter' => 'all'
    );
    
    $individual_builder = new Orders_BI_Query_Builder($individual_params);
    $individual_data = $individual_builder->get_bi_data();
    
    echo "✅ Individual mode: " . count($individual_data) . " orders with BI context<br>\n";
    
    if (!empty($individual_data)) {
        $first_order = $individual_data[0];
        echo "Sample BI context: Shift = " . ($first_order['bi_context']['shift'] ?? 'unknown') . "<br>\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error with individual mode: " . $e->getMessage() . "<br>\n";
}

echo "<h3>🎉 BI Query Builder Testing Complete!</h3>\n";
echo "<p>If you see mostly ✅ marks above, the BI Query Builder is working correctly.</p>\n";
?>
