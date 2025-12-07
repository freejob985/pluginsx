<?php
/**
 * Plugin Name: Sample Welcome Addon
 * Description: إضافة نموذجية بسيطة تعرض رسالة ترحيب في قائمة الأدمن
 * Version: 1.0.0
 * Addon Slug: sample-welcome-addon
 * Author: Orders Jet Team
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add admin menu item
 * Use early priority to ensure it loads before other menus
 */
add_action('admin_menu', 'oj_sample_addon_menu', 5);

function oj_sample_addon_menu() {
    add_menu_page(
        __('Welcome Message', 'orders-jet'),
        __('Welcome Message', 'orders-jet'),
        'manage_options',
        'oj-welcome-addon',
        'oj_sample_addon_page',
        'dashicons-smiley',
        30
    );
}

/**
 * Render admin page
 */
function oj_sample_addon_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Welcome to Sample Addon!', 'orders-jet'); ?></h1>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2><?php _e('مرحباً بك!', 'orders-jet'); ?></h2>
            <p style="font-size: 16px; line-height: 1.8;">
                <?php _e('هذه إضافة نموذجية بسيطة تم إنشاؤها لتجربة نظام الإضافات الداخلية.', 'orders-jet'); ?>
            </p>
            <p style="font-size: 16px; line-height: 1.8;">
                <?php _e('إذا كنت ترى هذه الصفحة، فهذا يعني أن الإضافة تعمل بشكل صحيح!', 'orders-jet'); ?>
            </p>
            
            <div style="background: #f0f6fc; padding: 15px; border-radius: 5px; margin-top: 20px; border-left: 4px solid #2271b1;">
                <h3 style="margin-top: 0;"><?php _e('معلومات الإضافة:', 'orders-jet'); ?></h3>
                <ul>
                    <li><strong><?php _e('اسم الإضافة:', 'orders-jet'); ?></strong> Sample Welcome Addon</li>
                    <li><strong><?php _e('الإصدار:', 'orders-jet'); ?></strong> 1.0.0</li>
                    <li><strong><?php _e('المسار:', 'orders-jet'); ?></strong> 
                        <code><?php echo esc_html(Orders_Jet_Internal_Addons::get_addons_dir() . 'sample-welcome-addon/'); ?></code>
                    </li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Add admin notice on activation
 */
add_action('admin_notices', 'oj_sample_addon_notice');

function oj_sample_addon_notice() {
    if (get_transient('oj_sample_addon_activated')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <strong><?php _e('Sample Welcome Addon', 'orders-jet'); ?></strong> - 
                <?php _e('تم تفعيل الإضافة بنجاح! يمكنك العثور على رابط "Welcome Message" في قائمة الأدمن.', 'orders-jet'); ?>
            </p>
        </div>
        <?php
        delete_transient('oj_sample_addon_activated');
    }
}
