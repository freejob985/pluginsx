<?php
/**
 * Admin Cache View
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wp-pwa-settings">
    <h1><?php _e('إعدادات PWA - التخزين المؤقت', 'wp-pwa-converter'); ?></h1>
    
    <form method="post" action="options.php" id="wp-pwa-cache-form">
        <?php settings_fields('wp_pwa_settings'); ?>
        
        <div class="wp-pwa-container">
            <div class="wp-pwa-main-content">
                
                <!-- إعدادات التخزين المؤقت -->
                <div class="wp-pwa-card">
                    <h2><?php _e('استراتيجية التخزين المؤقت', 'wp-pwa-converter'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cache_strategy"><?php _e('الاستراتيجية', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <select name="wp_pwa_settings[cache_strategy]" id="cache_strategy">
                                    <option value="cache-first" <?php selected($settings['cache_strategy'], 'cache-first'); ?>>
                                        <?php _e('Cache First - التخزين أولاً', 'wp-pwa-converter'); ?>
                                    </option>
                                    <option value="network-first" <?php selected($settings['cache_strategy'], 'network-first'); ?>>
                                        <?php _e('Network First - الشبكة أولاً', 'wp-pwa-converter'); ?>
                                    </option>
                                    <option value="cache-only" <?php selected($settings['cache_strategy'], 'cache-only'); ?>>
                                        <?php _e('Cache Only - التخزين فقط', 'wp-pwa-converter'); ?>
                                    </option>
                                    <option value="network-only" <?php selected($settings['cache_strategy'], 'network-only'); ?>>
                                        <?php _e('Network Only - الشبكة فقط', 'wp-pwa-converter'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <strong><?php _e('Cache First:', 'wp-pwa-converter'); ?></strong> <?php _e('سريع، يعمل أوفلاين، لكن قد يعرض محتوى قديم', 'wp-pwa-converter'); ?><br>
                                    <strong><?php _e('Network First:', 'wp-pwa-converter'); ?></strong> <?php _e('محتوى محدث دائماً، يعمل أوفلاين عند الحاجة', 'wp-pwa-converter'); ?><br>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="offline_enabled">
                                    <?php _e('تفعيل وضع Offline', 'wp-pwa-converter'); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wp_pwa_settings[offline_enabled]" 
                                           id="offline_enabled" value="1" 
                                           <?php checked($settings['offline_enabled'], 1); ?>>
                                    <?php _e('السماح بالتصفح دون اتصال', 'wp-pwa-converter'); ?>
                                </label>
                                <p class="description"><?php _e('عند التفعيل، سيتمكن المستخدمون من تصفح المحتوى المخزن دون إنترنت', 'wp-pwa-converter'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- إدارة الكاش -->
                <div class="wp-pwa-card">
                    <h2><?php _e('إدارة الكاش', 'wp-pwa-converter'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="cache_version"><?php _e('إصدار الكاش', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="wp_pwa_settings[cache_version]" id="cache_version" 
                                       value="<?php echo esc_attr($settings['cache_version']); ?>" 
                                       class="small-text" min="1" readonly>
                                <p class="description"><?php _e('رقم إصدار الكاش الحالي (يتم تحديثه تلقائياً عند مسح الكاش)', 'wp-pwa-converter'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php _e('مسح الكاش', 'wp-pwa-converter'); ?>
                            </th>
                            <td>
                                <button type="button" class="button button-secondary" id="clear-cache-btn">
                                    <?php _e('مسح جميع الملفات المخزنة', 'wp-pwa-converter'); ?>
                                </button>
                                <p class="description"><?php _e('سيؤدي هذا إلى حذف جميع الملفات المخزنة مؤقتاً لدى المستخدمين', 'wp-pwa-converter'); ?></p>
                                <div id="clear-cache-result" style="margin-top: 10px;"></div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- معلومات الكاش -->
                <div class="wp-pwa-card">
                    <h2><?php _e('معلومات الكاش', 'wp-pwa-converter'); ?></h2>
                    <div class="wp-pwa-cache-info">
                        <p><?php _e('يتم تخزين الملفات التالية تلقائياً:', 'wp-pwa-converter'); ?></p>
                        <ul class="wp-pwa-cached-files">
                            <li><?php _e('الصفحة الرئيسية', 'wp-pwa-converter'); ?></li>
                            <li><?php _e('ملفات CSS و JavaScript الأساسية', 'wp-pwa-converter'); ?></li>
                            <li><?php _e('الصور المستخدمة في التصميم', 'wp-pwa-converter'); ?></li>
                            <li><?php _e('الصفحات المزارة من قبل المستخدم', 'wp-pwa-converter'); ?></li>
                        </ul>
                    </div>
                </div>
                
                <?php submit_button(__('حفظ التغييرات', 'wp-pwa-converter')); ?>
                
            </div>
            
            <div class="wp-pwa-sidebar">
                
                <!-- شرح الاستراتيجيات -->
                <div class="wp-pwa-card">
                    <h3><?php _e('شرح الاستراتيجيات', 'wp-pwa-converter'); ?></h3>
                    <div class="wp-pwa-strategy-guide">
                        <h4><?php _e('Cache First', 'wp-pwa-converter'); ?></h4>
                        <p><?php _e('الأسرع والأفضل للمحتوى الثابت. يبحث في الكاش أولاً، ثم الشبكة.', 'wp-pwa-converter'); ?></p>
                        
                        <h4><?php _e('Network First', 'wp-pwa-converter'); ?></h4>
                        <p><?php _e('الأفضل للمحتوى الديناميكي. يحاول الشبكة أولاً، ثم يعود للكاش.', 'wp-pwa-converter'); ?></p>
                        
                        <h4><?php _e('Cache Only', 'wp-pwa-converter'); ?></h4>
                        <p><?php _e('يستخدم الكاش فقط. مفيد لمحتوى ثابت تماماً.', 'wp-pwa-converter'); ?></p>
                        
                        <h4><?php _e('Network Only', 'wp-pwa-converter'); ?></h4>
                        <p><?php _e('يستخدم الشبكة فقط. لا يخزن أي شيء.', 'wp-pwa-converter'); ?></p>
                    </div>
                </div>
                
                <!-- نصائح -->
                <div class="wp-pwa-card">
                    <h3><?php _e('نصائح', 'wp-pwa-converter'); ?></h3>
                    <ul class="wp-pwa-tips">
                        <li><?php _e('استخدم Cache First للأداء الأمثل', 'wp-pwa-converter'); ?></li>
                        <li><?php _e('امسح الكاش بعد التحديثات الكبيرة', 'wp-pwa-converter'); ?></li>
                        <li><?php _e('فعّل وضع Offline لتجربة أفضل', 'wp-pwa-converter'); ?></li>
                    </ul>
                </div>
                
            </div>
        </div>
    </form>
</div>
