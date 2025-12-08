<?php
/**
 * Admin Notifications View
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wp-pwa-settings">
    <h1><?php _e('إعدادات PWA - الإشعارات', 'wp-pwa-converter'); ?></h1>
    
    <form method="post" action="options.php" id="wp-pwa-notifications-form">
        <?php settings_fields('wp_pwa_settings'); ?>
        
        <div class="wp-pwa-container">
            <div class="wp-pwa-main-content">
                
                <!-- إعدادات الإشعارات -->
                <div class="wp-pwa-card">
                    <h2><?php _e('إعدادات Push Notifications', 'wp-pwa-converter'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="enable_notifications">
                                    <?php _e('تفعيل الإشعارات', 'wp-pwa-converter'); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wp_pwa_settings[enable_notifications]" 
                                           id="enable_notifications" value="1" 
                                           <?php checked($settings['enable_notifications'], 1); ?>>
                                    <?php _e('تفعيل إشعارات Push', 'wp-pwa-converter'); ?>
                                </label>
                                <p class="description"><?php _e('السماح بإرسال إشعارات للمستخدمين', 'wp-pwa-converter'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- معلومات الإشعارات -->
                <div class="wp-pwa-card">
                    <h2><?php _e('معلومات', 'wp-pwa-converter'); ?></h2>
                    <div class="wp-pwa-notifications-info">
                        <div class="notice notice-info inline">
                            <p><?php _e('لإرسال الإشعارات، ستحتاج إلى:', 'wp-pwa-converter'); ?></p>
                            <ol>
                                <li><?php _e('خادم HTTPS', 'wp-pwa-converter'); ?></li>
                                <li><?php _e('مفاتيح VAPID من Firebase أو OneSignal', 'wp-pwa-converter'); ?></li>
                                <li><?php _e('موافقة المستخدم على تلقي الإشعارات', 'wp-pwa-converter'); ?></li>
                            </ol>
                        </div>
                        
                        <h3><?php _e('كيفية الإعداد:', 'wp-pwa-converter'); ?></h3>
                        <ol class="wp-pwa-setup-steps">
                            <li>
                                <strong><?php _e('إنشاء حساب Firebase:', 'wp-pwa-converter'); ?></strong>
                                <p><?php _e('قم بزيارة', 'wp-pwa-converter'); ?> <a href="https://console.firebase.google.com/" target="_blank">Firebase Console</a></p>
                            </li>
                            <li>
                                <strong><?php _e('إنشاء مشروع جديد', 'wp-pwa-converter'); ?></strong>
                            </li>
                            <li>
                                <strong><?php _e('تفعيل Cloud Messaging', 'wp-pwa-converter'); ?></strong>
                            </li>
                            <li>
                                <strong><?php _e('الحصول على مفاتيح VAPID', 'wp-pwa-converter'); ?></strong>
                            </li>
                        </ol>
                    </div>
                </div>
                
                <!-- اختبار الإشعارات -->
                <div class="wp-pwa-card">
                    <h2><?php _e('اختبار الإشعارات', 'wp-pwa-converter'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php _e('إرسال إشعار تجريبي', 'wp-pwa-converter'); ?>
                            </th>
                            <td>
                                <button type="button" class="button button-secondary" id="test-notification-btn" 
                                        <?php disabled(!$settings['enable_notifications']); ?>>
                                    <?php _e('إرسال إشعار تجريبي', 'wp-pwa-converter'); ?>
                                </button>
                                <p class="description"><?php _e('سيظهر إشعار تجريبي في متصفحك', 'wp-pwa-converter'); ?></p>
                                <div id="test-notification-result" style="margin-top: 10px;"></div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(__('حفظ التغييرات', 'wp-pwa-converter')); ?>
                
            </div>
            
            <div class="wp-pwa-sidebar">
                
                <!-- حالة الإشعارات -->
                <div class="wp-pwa-card">
                    <h3><?php _e('حالة الإشعارات', 'wp-pwa-converter'); ?></h3>
                    <div id="notification-status">
                        <?php if ($settings['enable_notifications']): ?>
                            <div class="notice notice-success inline">
                                <p><?php _e('الإشعارات مفعّلة', 'wp-pwa-converter'); ?></p>
                            </div>
                        <?php else: ?>
                            <div class="notice notice-warning inline">
                                <p><?php _e('الإشعارات غير مفعّلة', 'wp-pwa-converter'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- روابط مفيدة -->
                <div class="wp-pwa-card">
                    <h3><?php _e('روابط مفيدة', 'wp-pwa-converter'); ?></h3>
                    <ul class="wp-pwa-quick-links">
                        <li>
                            <a href="https://console.firebase.google.com/" target="_blank">
                                <?php _e('Firebase Console', 'wp-pwa-converter'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="https://onesignal.com/" target="_blank">
                                <?php _e('OneSignal', 'wp-pwa-converter'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="https://web.dev/push-notifications-overview/" target="_blank">
                                <?php _e('دليل Push Notifications', 'wp-pwa-converter'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
                
            </div>
        </div>
    </form>
</div>
