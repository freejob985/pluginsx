<?php
/**
 * Debug Customer Type Filter
 * Add this to functions.php temporarily to debug the exact issue
 */

function debug_customer_type_filtering() {
    echo "<h2>🔍 Customer Type Filter Debug</h2>";
    
    // Get recent orders
    $orders = wc_get_orders(array(
        'limit' => 10,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    echo "<h3>📊 Recent Orders Analysis:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>Order ID</th><th>Email</th><th>Meta Fields</th><th>Customer Type Result</th>";
    echo "</tr>";
    
    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $email = $order->get_billing_email();
        
        // Get all relevant meta fields
        $meta_info = array();
        $contactless = $order->get_meta('_oj_contactless_order');
        $table_number = $order->get_meta('_oj_table_number');
        $session_start = $order->get_meta('_oj_session_start');
        $session_id = $order->get_meta('_oj_session_id');
        
        if ($contactless) $meta_info[] = "_oj_contactless_order: $contactless";
        if ($table_number) $meta_info[] = "_oj_table_number: $table_number";
        if ($session_start) $meta_info[] = "_oj_session_start: $session_start";
        if ($session_id) $meta_info[] = "_oj_session_id: $session_id";
        
        // Test each customer type using the EXACT logic from the query builder
        $customer_types = test_customer_type_logic($order);
        
        echo "<tr>";
        echo "<td>#$order_id</td>";
        echo "<td>" . esc_html($email) . "</td>";
        echo "<td>" . implode('<br>', $meta_info) . "</td>";
        echo "<td>" . implode('<br>', $customer_types) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Test the actual query builder
    echo "<h3>🧪 Query Builder Test:</h3>";
    test_query_builder_with_customer_types();
}

function test_customer_type_logic($order) {
    $results = array();
    
    // Test table_guest logic (EXACT copy from query builder)
    $is_contactless = $order->get_meta('_oj_contactless_order') === 'yes';
    $has_table = !empty($order->get_meta('_oj_table_number'));
    $email = $order->get_billing_email();
    $has_table_email = preg_match('/^table\d+@restaurant\.local$/', $email);
    $is_table_guest = ($is_contactless && $has_table) || $has_table_email;
    $results[] = "🍽️ Table Guest: " . ($is_table_guest ? 'YES' : 'NO');
    
    // Test registered_customer logic (EXACT copy from query builder)
    $is_table_guest_check = $order->get_meta('_oj_contactless_order') === 'yes' || 
                           preg_match('/^table\d+@restaurant\.local$/', $email);
    $is_registered = !empty($email) && !$is_table_guest_check && 
                    !in_array($email, ['N/A', 'noreply@restaurant.local', 'guest@restaurant.local']);
    $results[] = "👤 Registered: " . ($is_registered ? 'YES' : 'NO');
    
    // Test session logic
    $session_start = $order->get_meta('_oj_session_start');
    $results[] = "🆕 New Session: " . ($session_start === 'yes' ? 'YES' : 'NO');
    $results[] = "➕ Continuing: " . ($session_start === 'no' && !empty($order->get_meta('_oj_session_id')) ? 'YES' : 'NO');
    
    return $results;
}

function test_query_builder_with_customer_types() {
    // Test each customer type filter
    $customer_types = ['table_guest', 'registered_customer', 'new_session', 'continuing_session'];
    
    foreach ($customer_types as $type) {
        echo "<h4>Testing: $type</h4>";
        
        // Create query builder with this customer type
        $params = array('customer_type' => $type);
        
        try {
            // Use Reports query builder
            $query_builder = new Orders_Reports_Query_Builder($params);
            $results = $query_builder->get_orders();
            
            echo "<p><strong>Results:</strong> " . count($results['orders']) . " orders found</p>";
            
            if (count($results['orders']) > 0) {
                echo "<ul>";
                foreach (array_slice($results['orders'], 0, 3) as $order) {
                    echo "<li>Order #" . $order->get_id() . " - " . $order->get_billing_email() . "</li>";
                }
                echo "</ul>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
        }
    }
}

// Uncomment to run the debug
// debug_customer_type_filtering();
?>
