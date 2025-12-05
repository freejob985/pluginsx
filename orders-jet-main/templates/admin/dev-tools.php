<?php
/**
 * Development Tools Page
 * Centralized location for all development and testing tools
 * 
 * @package Orders_Jet
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Admin only
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have permission to access this page.', 'orders-jet'));
}

?>

<div class="wrap">
    <h1>🛠️ <?php _e('Development Tools', 'orders-jet'); ?></h1>
    <p class="description">
        <?php _e('Centralized tools for testing, development, and debugging. Admin access required.', 'orders-jet'); ?>
    </p>
    
    <!-- Order Management Tools -->
    <div style="background: #fff; border: 1px solid #ddd; padding: 25px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin-top: 0; border-bottom: 2px solid #2196f3; padding-bottom: 10px;">
            📦 <?php _e('Order Management', 'orders-jet'); ?>
        </h2>
        
        <div style="display: grid; gap: 15px; margin-top: 20px;">
            <!-- Generate Test Orders -->
            <div style="background: #f0f9ff; border-left: 4px solid #2196f3; padding: 15px;">
                <h3 style="margin: 0 0 10px 0;">
                    ➕ <?php _e('Generate Test Orders', 'orders-jet'); ?>
                </h3>
                <p style="margin: 0 0 15px 0; color: #666;">
                    <?php _e('Creates 20 realistic test orders with proper order types (dine-in/takeaway/delivery), date distribution, and kitchen types. Perfect for testing the advanced filtering system.', 'orders-jet'); ?>
                </p>
                
                <button id="oj-generate-test-orders" class="button button-primary button-large">
                    ➕ <?php _e('Generate 20 Test Orders', 'orders-jet'); ?>
                </button>
                
                <details style="margin-top: 15px;">
                    <summary style="cursor: pointer; color: #2196f3; font-weight: bold;">
                        <?php _e('📋 What will be generated?', 'orders-jet'); ?>
                    </summary>
                    <ul style="margin: 10px 0 0 20px; color: #666;">
                        <li><strong>Order Types</strong>: Dine-in (40%), Takeaway (35%), Delivery (25%)</li>
                        <li><strong>Status Distribution</strong>: Processing (25%), Pending Payment (15%), Completed (60%)</li>
                        <li><strong>Kitchen Types</strong>: Food, Beverages, Mixed (with proper meta fields)</li>
                        <li><strong>Date Distribution</strong>: Today (40%), Yesterday (25%), This week (20%), Last week (10%), Last month (5%)</li>
                        <li><strong>Tables</strong>: T01-T20 for dine-in orders only</li>
                        <li><strong>Delivery Addresses</strong>: Realistic Arabic addresses for delivery orders</li>
                        <li><strong>Customer Data</strong>: Arabic names and phone numbers</li>
                        <li><strong>Perfect for testing</strong>: Date range filters, order type filters, kitchen filters</li>
                    </ul>
                </details>
            </div>
            
            <!-- Delete All Orders -->
            <div style="background: #fff3f3; border-left: 4px solid #f44336; padding: 15px;">
                <h3 style="margin: 0 0 10px 0; color: #d32f2f;">
                    🗑️ <?php _e('Delete All Orders', 'orders-jet'); ?>
                </h3>
                <p style="margin: 0 0 15px 0; color: #666;">
                    <?php _e('Permanently deletes all WooCommerce orders. This action cannot be undone!', 'orders-jet'); ?>
                </p>
                
                <button id="oj-clear-all-orders" class="button button-large" style="background: #f44336; border-color: #d32f2f; color: #fff;">
                    🗑️ <?php _e('Delete All Orders', 'orders-jet'); ?>
                </button>
                
                <p style="margin: 10px 0 0 0; color: #d32f2f; font-size: 12px;">
                    ⚠️ <?php _e('Warning: Requires double confirmation with DELETE keyword', 'orders-jet'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Future Tools Section -->
    <div style="background: #fff; border: 1px solid #ddd; padding: 25px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); opacity: 0.6;">
        <h2 style="margin-top: 0; border-bottom: 2px solid #9e9e9e; padding-bottom: 10px;">
            🚀 <?php _e('Coming Soon', 'orders-jet'); ?>
        </h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 20px;">
            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                <h4 style="margin: 0 0 5px 0;">🗄️ <?php _e('Cache Management', 'orders-jet'); ?></h4>
                <p style="margin: 0; font-size: 13px; color: #666;">Clear transients and optimize performance</p>
            </div>
            
            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                <h4 style="margin: 0 0 5px 0;">📊 <?php _e('Performance Testing', 'orders-jet'); ?></h4>
                <p style="margin: 0; font-size: 13px; color: #666;">Test query performance and load times</p>
            </div>
            
            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                <h4 style="margin: 0 0 5px 0;">💾 <?php _e('Data Export/Import', 'orders-jet'); ?></h4>
                <p style="margin: 0; font-size: 13px; color: #666;">Export and import test data sets</p>
            </div>
            
            <div style="padding: 15px; background: #f5f5f5; border-radius: 4px;">
                <h4 style="margin: 0 0 5px 0;">🎯 <?php _e('Scenario Generator', 'orders-jet'); ?></h4>
                <p style="margin: 0; font-size: 13px; color: #666;">Generate specific test scenarios</p>
            </div>
        </div>
    </div>
    
    <!-- System Info -->
    <div style="background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h3 style="margin: 0 0 15px 0;">ℹ️ <?php _e('System Information', 'orders-jet'); ?></h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 8px; font-weight: bold; width: 200px;">WP_DEBUG:</td>
                <td style="padding: 8px;">
                    <?php if (WP_DEBUG): ?>
                        <span style="color: #4caf50; font-weight: bold;">✅ Enabled</span>
                    <?php else: ?>
                        <span style="color: #ff9800; font-weight: bold;">⚠️ Disabled</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 8px; font-weight: bold;">Total Orders:</td>
                <td style="padding: 8px;">
                    <?php 
                    $total_orders = wc_get_orders(array('limit' => 1000, 'return' => 'ids', 'status' => 'any'));
                    echo count($total_orders); 
                    ?>
                </td>
            </tr>
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 8px; font-weight: bold;">Total Products:</td>
                <td style="padding: 8px;">
                    <?php 
                    $total_products = wc_get_products(array('limit' => -1, 'return' => 'ids'));
                    echo count($total_products); 
                    ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 8px; font-weight: bold;">Orders Jet Version:</td>
                <td style="padding: 8px;"><?php echo defined('ORDERS_JET_VERSION') ? ORDERS_JET_VERSION : 'Unknown'; ?></td>
            </tr>
        </table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    
    // Generate Test Orders - BATCHED APPROACH (5 at a time, 4 batches = 20 total)
    $('#oj-generate-test-orders').on('click', function() {
        const $btn = $(this);
        
        if (!confirm('Generate 20 test orders with various statuses, tables, and scenarios?\n\nThis will create:\n- Processing orders\n- Pending payment orders\n- Completed orders\n- Mixed kitchen types')) {
            return;
        }
        
        $btn.prop('disabled', true).text('⏳ Starting generation...');
        
        const totalBatches = 4;
        const ordersPerBatch = 5;
        const totalOrders = totalBatches * ordersPerBatch;
        let generated = 0;
        
        function generateBatch(batchNumber) {
            if (batchNumber > totalBatches) {
                // Done!
                alert('✅ Successfully generated ' + totalOrders + ' test orders!');
                location.reload();
                return;
            }
            
            $btn.text('⏳ Generating batch ' + batchNumber + '/' + totalBatches + '...');
            
            $.post(ajaxurl, {
                action: 'oj_generate_orders_batch',
                nonce: '<?php echo wp_create_nonce('oj_dev_tools'); ?>',
                count: ordersPerBatch
            }, function(response) {
                if (response.success) {
                    generated += response.data.generated;
                    // Process next batch
                    generateBatch(batchNumber + 1);
                } else {
                    alert('❌ Error in batch ' + batchNumber + ': ' + (response.data.message || 'Unknown error') + '\n\nGenerated ' + generated + ' orders before error.');
                    location.reload(); // Reload to show generated orders
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                console.error('Response:', jqXHR.responseText);
                alert('❌ Network error in batch ' + batchNumber + '.\n\nGenerated ' + generated + ' orders before error.');
                location.reload(); // Reload to show generated orders
            });
        }
        
        generateBatch(1);
    });
    
    // Clear All Orders - BATCHED APPROACH (10 at a time)
    $('#oj-clear-all-orders').on('click', function() {
        const $btn = $(this);
        
        if (!confirm('⚠️ DELETE ALL ORDERS?\n\nThis will permanently delete all WooCommerce orders.\n\nThis action cannot be undone!')) {
            return;
        }
        
        const confirmation = prompt('Type DELETE in capital letters to confirm:');
        if (confirmation !== 'DELETE') {
            alert('Cancelled - confirmation did not match');
            return;
        }
        
        $btn.prop('disabled', true).text('⏳ Getting order list...');
        
        // Step 1: Get all order IDs
        $.post(ajaxurl, {
            action: 'oj_get_all_order_ids',
            nonce: '<?php echo wp_create_nonce('oj_dev_tools'); ?>'
        }, function(response) {
            if (!response.success) {
                alert('❌ Error: ' + (response.data.message || 'Failed to get order list'));
                $btn.prop('disabled', false).text('🗑️ Delete All Orders');
                return;
            }
            
            const orderIds = response.data.order_ids;
            const total = response.data.total;
            
            if (total === 0) {
                alert('✅ No orders found!');
                $btn.prop('disabled', false).text('🗑️ Delete All Orders');
                return;
            }
            
            // Step 2: Delete in batches of 10
            const batchSize = 10;
            let processed = 0;
            
            function deleteBatch(startIndex) {
                const batch = orderIds.slice(startIndex, startIndex + batchSize);
                
                if (batch.length === 0) {
                    // Done!
                    alert('✅ Successfully deleted all ' + total + ' orders!');
                    location.reload();
                    return;
                }
                
                processed += batch.length;
                $btn.text('⏳ Deleting ' + processed + '/' + total + ' orders...');
                
                $.post(ajaxurl, {
                    action: 'oj_clear_orders_batch',
                    nonce: '<?php echo wp_create_nonce('oj_dev_tools'); ?>',
                    order_ids: batch
                }, function(batchResponse) {
                    if (batchResponse.success) {
                        // Process next batch
                        deleteBatch(startIndex + batchSize);
                    } else {
                        alert('❌ Error in batch: ' + (batchResponse.data.message || 'Unknown error'));
                        location.reload(); // Reload to show remaining orders
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX Error:', textStatus, errorThrown);
                    alert('❌ Network error during deletion.\n\nDeleted ' + (processed - batch.length) + '/' + total + ' orders before error.');
                    location.reload(); // Reload to show remaining orders
                });
            }
            
            deleteBatch(0);
            
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            alert('❌ Network error getting order list.');
            $btn.prop('disabled', false).text('🗑️ Delete All Orders');
        });
    });
});
</script>

