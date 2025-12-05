<?php
/**
 * Admin Test Page for Customer Types
 * Add this as a WordPress admin page to easily create test orders
 */

// Add admin menu
add_action('admin_menu', 'add_customer_type_test_page');

function add_customer_type_test_page() {
    add_submenu_page(
        'orders-jet',
        'Test Customer Types',
        'Test Customer Types',
        'manage_options',
        'test-customer-types',
        'render_customer_type_test_page'
    );
}

function render_customer_type_test_page() {
    // Handle form submissions
    if (isset($_POST['create_test_orders']) && wp_verify_nonce($_POST['_wpnonce'], 'create_test_orders')) {
        create_all_test_orders();
        echo '<div class="notice notice-success"><p>✅ Test orders created successfully!</p></div>';
    }
    
    if (isset($_POST['check_existing_orders']) && wp_verify_nonce($_POST['_wpnonce'], 'check_existing_orders')) {
        check_existing_customer_types();
    }
    ?>
    <div class="wrap">
        <h1>🧪 Customer Type Testing</h1>
        
        <div class="card" style="max-width: 800px;">
            <h2>Create Test Orders</h2>
            <p>This will create sample orders for each customer type to test the filtering.</p>
            
            <form method="post">
                <?php wp_nonce_field('create_test_orders'); ?>
                <input type="submit" name="create_test_orders" class="button button-primary" value="Create Test Orders">
            </form>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Check Existing Orders</h2>
            <p>Analyze your existing orders to see their customer types.</p>
            
            <form method="post">
                <?php wp_nonce_field('check_existing_orders'); ?>
                <input type="submit" name="check_existing_orders" class="button" value="Analyze Existing Orders">
            </form>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>📋 Customer Type Definitions</h2>
            <ul>
                <li><strong>🍽️ Table Guests:</strong> Orders with <code>_oj_contactless_order = 'yes'</code> and <code>_oj_table_number</code></li>
                <li><strong>👤 Registered Customers:</strong> Orders with real email addresses (not table@restaurant.local pattern)</li>
                <li><strong>🔄 Repeat Visitors:</strong> Tables with multiple orders today</li>
                <li><strong>🆕 New Session:</strong> Orders with <code>_oj_session_start = 'yes'</code></li>
                <li><strong>➕ Continuing Session:</strong> Orders with <code>_oj_session_start = 'no'</code> and session ID</li>
            </ul>
        </div>
    </div>
    <?php
}

function create_all_test_orders() {
    // Get a sample product ID
    $products = wc_get_products(array('limit' => 1, 'status' => 'publish'));
    if (empty($products)) {
        echo '<div class="notice notice-error"><p>❌ No products found. Please create a product first.</p></div>';
        return;
    }
    $product_id = $products[0]->get_id();
    
    // 1. Table Guest (New Session)
    $order1 = wc_create_order();
    $order1->set_billing_first_name('Table 5');
    $order1->set_billing_last_name('Guest');
    $order1->set_billing_email('table5@restaurant.local');
    $order1->update_meta_data('_oj_table_number', '5');
    $order1->update_meta_data('_oj_contactless_order', 'yes');
    $order1->update_meta_data('_oj_session_start', 'yes');
    $order1->update_meta_data('_oj_session_id', 'session_5_' . time());
    $order1->add_product(wc_get_product($product_id), 1);
    $order1->set_status('processing');
    $order1->calculate_totals();
    $order1->save();
    
    // 2. Registered Customer
    $order2 = wc_create_order();
    $order2->set_billing_first_name('John');
    $order2->set_billing_last_name('Doe');
    $order2->set_billing_email('john.doe@example.com');
    $order2->set_billing_phone('+1234567890');
    $order2->add_product(wc_get_product($product_id), 1);
    $order2->set_status('processing');
    $order2->calculate_totals();
    $order2->save();
    
    // 3. Repeat Visitor (Second order for Table 5)
    $order3 = wc_create_order();
    $order3->set_billing_first_name('Table 5');
    $order3->set_billing_last_name('Guest');
    $order3->set_billing_email('table5@restaurant.local');
    $order3->update_meta_data('_oj_table_number', '5');
    $order3->update_meta_data('_oj_contactless_order', 'yes');
    $order3->update_meta_data('_oj_session_start', 'no');
    $order3->update_meta_data('_oj_session_id', 'session_5_' . time());
    $order3->add_product(wc_get_product($product_id), 1);
    $order3->set_status('processing');
    $order3->calculate_totals();
    $order3->save();
    
    echo '<p>Created orders: #' . $order1->get_id() . ' (Table Guest), #' . $order2->get_id() . ' (Registered), #' . $order3->get_id() . ' (Repeat Visitor)</p>';
}

function check_existing_customer_types() {
    $orders = wc_get_orders(array('limit' => 20, 'orderby' => 'date', 'order' => 'DESC'));
    
    echo '<div class="card" style="margin-top: 20px;"><h3>📊 Existing Orders Analysis</h3>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>Order ID</th><th>Email</th><th>Customer Type</th><th>Meta Fields</th></tr></thead><tbody>';
    
    foreach ($orders as $order) {
        $order_id = $order->get_id();
        $email = $order->get_billing_email();
        $customer_type = determine_customer_type($order);
        
        // Get relevant meta
        $meta_info = array();
        if ($order->get_meta('_oj_contactless_order')) {
            $meta_info[] = '_oj_contactless_order: ' . $order->get_meta('_oj_contactless_order');
        }
        if ($order->get_meta('_oj_table_number')) {
            $meta_info[] = '_oj_table_number: ' . $order->get_meta('_oj_table_number');
        }
        if ($order->get_meta('_oj_session_start')) {
            $meta_info[] = '_oj_session_start: ' . $order->get_meta('_oj_session_start');
        }
        
        echo '<tr>';
        echo '<td>#' . $order_id . '</td>';
        echo '<td>' . esc_html($email) . '</td>';
        echo '<td><strong>' . $customer_type . '</strong></td>';
        echo '<td>' . implode(', ', $meta_info) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table></div>';
}

function determine_customer_type($order) {
    $email = $order->get_billing_email();
    $is_contactless = $order->get_meta('_oj_contactless_order') === 'yes';
    $has_table = !empty($order->get_meta('_oj_table_number'));
    $has_table_email = preg_match('/^table\d+@restaurant\.local$/', $email);
    $session_start = $order->get_meta('_oj_session_start');
    
    if (($is_contactless && $has_table) || $has_table_email) {
        if ($session_start === 'yes') {
            return '🆕 New Session (Table Guest)';
        } elseif ($session_start === 'no') {
            return '➕ Continuing Session (Table Guest)';
        } else {
            return '🍽️ Table Guest';
        }
    } elseif (!empty($email) && !$has_table_email && !$is_contactless) {
        return '👤 Registered Customer';
    } else {
        return '❓ Unknown';
    }
}
?>
