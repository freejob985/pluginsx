<?php
/**
 * Debug Returning Customer Method
 * Add this to functions.php temporarily to debug the is_returning_customer() method
 */


 
function debug_returning_customer_method() {
    echo "<h2>🔍 Debug: WooCommerce is_returning_customer() Method</h2>";
    
    // Get some recent orders
    $orders = wc_get_orders(array(
        'limit' => 10,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>Order ID</th><th>Customer</th><th>Email</th><th>Customer ID</th>";
    echo "<th>Method Exists?</th><th>is_returning_customer()</th><th>Manual Check</th>";
    echo "</tr>";
    
    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $email = $order->get_billing_email();
        $customer_id = $order->get_customer_id();
        
        // Check if method exists
        $method_exists = method_exists($order, 'is_returning_customer');
        
        // Try to call the method
        $is_returning_result = 'N/A';
        $error_message = '';
        if ($method_exists) {
            try {
                $is_returning_result = $order->is_returning_customer() ? 'YES' : 'NO';
            } catch (Exception $e) {
                $is_returning_result = 'ERROR';
                $error_message = $e->getMessage();
            }
        }
        
        // Manual check for comparison
        $manual_check = 'N/A';
        if ($customer_id > 0) {
            $customer_orders = wc_get_orders(array(
                'customer_id' => $customer_id,
                'limit' => 2,
                'return' => 'ids'
            ));
            $manual_check = count($customer_orders) > 1 ? 'YES' : 'NO';
        } elseif (!empty($email) && !preg_match('/^table\d+@restaurant\.local$/', $email)) {
            $email_orders = wc_get_orders(array(
                'billing_email' => $email,
                'limit' => 2,
                'return' => 'ids'
            ));
            $manual_check = count($email_orders) > 1 ? 'YES' : 'NO';
        }
        
        echo "<tr>";
        echo "<td>#$order_id</td>";
        echo "<td>" . esc_html($customer_name) . "</td>";
        echo "<td>" . esc_html($email) . "</td>";
        echo "<td>$customer_id</td>";
        echo "<td>" . ($method_exists ? '✅ YES' : '❌ NO') . "</td>";
        echo "<td style='color: " . ($is_returning_result === 'YES' ? 'green' : ($is_returning_result === 'NO' ? 'red' : 'orange')) . ";'>$is_returning_result</td>";
        echo "<td style='color: " . ($manual_check === 'YES' ? 'green' : ($manual_check === 'NO' ? 'red' : 'gray')) . ";'>$manual_check</td>";
        echo "</tr>";
        
        if ($error_message) {
            echo "<tr><td colspan='7' style='background: #ffeeee; color: red;'>Error: $error_message</td></tr>";
        }
    }
    
    echo "</table>";
    
    // Test the actual filtering logic
    echo "<h3>🧪 Test Filtering Logic</h3>";
    
    $test_orders = wc_get_orders(array('limit' => 5));
    foreach ($test_orders as $order) {
        $order_id = $order->get_id();
        
        // Test our exact filtering logic
        $is_contactless = $order->get_meta('_oj_contactless_order') === 'yes';
        $is_consolidated = $order->get_meta('_oj_consolidated_order') === 'yes';
        $email = $order->get_billing_email();
        $has_table_email = preg_match('/^table\d+@restaurant\.local$/', $email);
        
        echo "<p><strong>Order #$order_id:</strong></p>";
        echo "<ul>";
        echo "<li>Is contactless: " . ($is_contactless ? 'YES' : 'NO') . "</li>";
        echo "<li>Is consolidated: " . ($is_consolidated ? 'YES' : 'NO') . "</li>";
        echo "<li>Has table email: " . ($has_table_email ? 'YES' : 'NO') . "</li>";
        echo "<li>Should be excluded: " . (($is_contactless || $is_consolidated || $has_table_email) ? 'YES' : 'NO') . "</li>";
        
        if (!($is_contactless || $is_consolidated || $has_table_email)) {
            if (method_exists($order, 'is_returning_customer')) {
                try {
                    $result = $order->is_returning_customer();
                    echo "<li>is_returning_customer(): " . ($result ? 'YES' : 'NO') . "</li>";
                } catch (Exception $e) {
                    echo "<li>is_returning_customer(): ERROR - " . $e->getMessage() . "</li>";
                }
            } else {
                echo "<li>is_returning_customer(): METHOD NOT AVAILABLE</li>";
            }
        }
        echo "</ul><hr>";
    }
}

// Uncomment to run
// debug_returning_customer_method();
?>
