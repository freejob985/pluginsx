<?php
/**
 * Debug Customer Type Filtering
 * Add this to functions.php temporarily to debug
 */

function debug_customer_type_filter($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    echo "<h3>Debug Order #$order_id</h3>";
    echo "<p><strong>Email:</strong> " . $order->get_billing_email() . "</p>";
    
    // Check all relevant meta fields
    $meta_fields = [
        '_oj_contactless_order',
        '_oj_table_number', 
        '_oj_session_start',
        '_oj_session_id',
        'exwf_odmethod'
    ];
    
    echo "<p><strong>Meta Fields:</strong></p><ul>";
    foreach ($meta_fields as $field) {
        $value = $order->get_meta($field);
        echo "<li>$field: " . ($value ?: 'empty') . "</li>";
    }
    echo "</ul>";
    
    // Test each customer type
    echo "<p><strong>Customer Type Tests:</strong></p><ul>";
    
    // Table Guest Test
    $is_contactless = $order->get_meta('_oj_contactless_order') === 'yes';
    $has_table = !empty($order->get_meta('_oj_table_number'));
    $email = $order->get_billing_email();
    $has_table_email = preg_match('/^table\d+@restaurant\.local$/', $email);
    $is_table_guest = ($is_contactless && $has_table) || $has_table_email;
    echo "<li>🍽️ Table Guest: " . ($is_table_guest ? 'YES' : 'NO') . "</li>";
    
    // Registered Customer Test
    $is_table_guest_check = $order->get_meta('_oj_contactless_order') === 'yes' || 
                           preg_match('/^table\d+@restaurant\.local$/', $email);
    $is_registered = !empty($email) && !$is_table_guest_check && 
                    !in_array($email, ['N/A', 'noreply@restaurant.local', 'guest@restaurant.local']);
    echo "<li>👤 Registered Customer: " . ($is_registered ? 'YES' : 'NO') . "</li>";
    
    // Session Tests
    $session_start = $order->get_meta('_oj_session_start');
    echo "<li>🆕 New Session: " . ($session_start === 'yes' ? 'YES' : 'NO') . "</li>";
    echo "<li>➕ Continuing Session: " . ($session_start === 'no' && !empty($order->get_meta('_oj_session_id')) ? 'YES' : 'NO') . "</li>";
    
    echo "</ul><hr>";
}

// Usage: debug_customer_type_filter(123); // Replace 123 with actual order ID
?>
