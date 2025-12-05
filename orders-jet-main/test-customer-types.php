<?php
/**
 * Customer Type Testing Script
 * Run this in WordPress admin or via WP-CLI to create test orders
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create a Table Guest Order (Orders Jet style)
 */
function create_table_guest_order($table_number = 5) {
    // Create WooCommerce order
    $order = wc_create_order();
    
    // Set table guest billing info (Orders Jet pattern)
    $order->set_billing_first_name('Table ' . $table_number);
    $order->set_billing_last_name('Guest');
    $order->set_billing_phone('N/A');
    $order->set_billing_email('table' . $table_number . '@restaurant.local');
    
    // Add Orders Jet meta fields
    $order->update_meta_data('_oj_table_number', $table_number);
    $order->update_meta_data('_oj_contactless_order', 'yes');
    $order->update_meta_data('_oj_order_method', 'dinein');
    $order->update_meta_data('_oj_session_start', 'yes'); // New session
    $order->update_meta_data('_oj_session_id', 'session_' . $table_number . '_' . time());
    $order->update_meta_data('exwf_odmethod', 'dinein');
    
    // Add a sample product (replace with your product ID)
    $product_id = 123; // Change this to an existing product ID
    if (wc_get_product($product_id)) {
        $order->add_product(wc_get_product($product_id), 1);
    }
    
    $order->set_status('processing');
    $order->calculate_totals();
    $order_id = $order->save();
    
    echo "✅ Created Table Guest Order #$order_id for Table $table_number\n";
    return $order_id;
}

/**
 * Create a Registered Customer Order (Regular WooCommerce)
 */
function create_registered_customer_order($email = 'john.doe@example.com') {
    // Create WooCommerce order
    $order = wc_create_order();
    
    // Set real customer billing info
    $order->set_billing_first_name('John');
    $order->set_billing_last_name('Doe');
    $order->set_billing_phone('+1234567890');
    $order->set_billing_email($email);
    $order->set_billing_address_1('123 Main Street');
    $order->set_billing_city('New York');
    $order->set_billing_postcode('10001');
    
    // Regular WooCommerce order - no Orders Jet meta
    $order->update_meta_data('exwf_odmethod', 'takeaway'); // or delivery
    
    // Add a sample product
    $product_id = 123; // Change this to an existing product ID
    if (wc_get_product($product_id)) {
        $order->add_product(wc_get_product($product_id), 1);
    }
    
    $order->set_status('processing');
    $order->calculate_totals();
    $order_id = $order->save();
    
    echo "✅ Created Registered Customer Order #$order_id for $email\n";
    return $order_id;
}

/**
 * Create a Repeat Visitor Order (Second order for same table)
 */
function create_repeat_visitor_order($table_number = 5) {
    // Create second order for the same table
    $order = wc_create_order();
    
    $order->set_billing_first_name('Table ' . $table_number);
    $order->set_billing_last_name('Guest');
    $order->set_billing_phone('N/A');
    $order->set_billing_email('table' . $table_number . '@restaurant.local');
    
    // Same table, continuing session
    $order->update_meta_data('_oj_table_number', $table_number);
    $order->update_meta_data('_oj_contactless_order', 'yes');
    $order->update_meta_data('_oj_order_method', 'dinein');
    $order->update_meta_data('_oj_session_start', 'no'); // Continuing session
    $order->update_meta_data('_oj_session_id', 'session_' . $table_number . '_' . (time() - 3600)); // Same session ID
    $order->update_meta_data('exwf_odmethod', 'dinein');
    
    // Add a sample product
    $product_id = 123; // Change this to an existing product ID
    if (wc_get_product($product_id)) {
        $order->add_product(wc_get_product($product_id), 1);
    }
    
    $order->set_status('processing');
    $order->calculate_totals();
    $order_id = $order->save();
    
    echo "✅ Created Repeat Visitor Order #$order_id for Table $table_number (continuing session)\n";
    return $order_id;
}

/**
 * Run all tests
 */
function run_customer_type_tests() {
    echo "🧪 Creating Customer Type Test Orders...\n\n";
    
    // Test 1: Table Guest (New Session)
    create_table_guest_order(5);
    
    // Test 2: Registered Customer
    create_registered_customer_order('jane.smith@example.com');
    
    // Test 3: Repeat Visitor (same table, second order)
    create_repeat_visitor_order(5);
    
    // Test 4: Another Table Guest (different table)
    create_table_guest_order(8);
    
    // Test 5: Another Registered Customer
    create_registered_customer_order('mike.wilson@example.com');
    
    echo "\n✅ Test orders created! Check your Orders Reports page and test the Customer Type filter.\n";
}

// Uncomment the line below to run the tests
// run_customer_type_tests();
?>
