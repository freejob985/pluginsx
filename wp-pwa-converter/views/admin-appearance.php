<?php
/**
 * Admin Appearance View
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wp-pwa-settings">
    <h1><?php _e('إعدادات PWA - التصميم والمظهر', 'wp-pwa-converter'); ?></h1>
    
    <form method="post" action="options.php" id="wp-pwa-appearance-form">
        <?php settings_fields('wp_pwa_settings'); ?>
        
        <div class="wp-pwa-container">
            <div class="wp-pwa-main-content">
                
                <!-- الألوان -->
                <div class="wp-pwa-card">
                    <h2><?php _e('الألوان', 'wp-pwa-converter'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="theme_color"><?php _e('لون المظهر', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="wp_pwa_settings[theme_color]" id="theme_color" 
                                       value="<?php echo esc_attr($settings['theme_color']); ?>" 
                                       class="wp-pwa-color-picker">
                                <p class="description"><?php _e('يُستخدم في شريط العنوان والمهام', 'wp-pwa-converter'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="background_color"><?php _e('لون الخلفية', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="wp_pwa_settings[background_color]" id="background_color" 
                                       value="<?php echo esc_attr($settings['background_color']); ?>" 
                                       class="wp-pwa-color-picker">
                                <p class="description"><?php _e('يُستخدم في شاشة البداية', 'wp-pwa-converter'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- الأيقونات -->
                <div class="wp-pwa-card">
                    <h2><?php _e('أيقونات التطبيق', 'wp-pwa-converter'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label><?php _e('أيقونة 192x192', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <div class="wp-pwa-icon-upload">
                                    <input type="hidden" name="wp_pwa_settings[icon_192]" id="icon_192" 
                                           value="<?php echo esc_url($settings['icon_192']); ?>">
                                    <button type="button" class="button wp-pwa-upload-icon" data-target="icon_192">
                                        <?php _e('رفع أيقونة', 'wp-pwa-converter'); ?>
                                    </button>
                                    <div class="wp-pwa-icon-preview" id="icon_192_preview">
                                        <?php if (!empty($settings['icon_192'])): ?>
                                            <img src="<?php echo esc_url($settings['icon_192']); ?>" alt="Icon 192x192">
                                            <button type="button" class="wp-pwa-remove-icon" data-target="icon_192">×</button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description"><?php _e('الحد الأدنى للحجم: 192x192 بكسل', 'wp-pwa-converter'); ?></p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label><?php _e('أيقونة 512x512', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <div class="wp-pwa-icon-upload">
                                    <input type="hidden" name="wp_pwa_settings[icon_512]" id="icon_512" 
                                           value="<?php echo esc_url($settings['icon_512']); ?>">
                                    <button type="button" class="button wp-pwa-upload-icon" data-target="icon_512">
                                        <?php _e('رفع أيقونة', 'wp-pwa-converter'); ?>
                                    </button>
                                    <div class="wp-pwa-icon-preview" id="icon_512_preview">
                                        <?php if (!empty($settings['icon_512'])): ?>
                                            <img src="<?php echo esc_url($settings['icon_512']); ?>" alt="Icon 512x512">
                                            <button type="button" class="wp-pwa-remove-icon" data-target="icon_512">×</button>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description"><?php _e('الحجم الموصى به: 512x512 بكسل', 'wp-pwa-converter'); ?></p>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- صفحة أوفلاين مخصصة -->
                <div class="wp-pwa-card">
                    <h2><?php _e('صفحة Offline', 'wp-pwa-converter'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="offline_page"><?php _e('رابط صفحة Offline', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <input type="url" name="wp_pwa_settings[offline_page]" id="offline_page" 
                                       value="<?php echo esc_url($settings['offline_page']); ?>" 
                                       class="regular-text">
                                <p class="description"><?php _e('الصفحة التي تظهر عند عدم الاتصال بالإنترنت (اتركها فارغة للصفحة الافتراضية)', 'wp-pwa-converter'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(__('حفظ التغييرات', 'wp-pwa-converter')); ?>
                
            </div>
            
            <div class="wp-pwa-sidebar">
                
                <!-- معاينة الألوان -->
                <div class="wp-pwa-card">
                    <h3><?php _e('معاينة', 'wp-pwa-converter'); ?></h3>
                    <div class="wp-pwa-preview-device">
                        <div class="preview-header" id="preview-header" style="background-color: <?php echo esc_attr($settings['theme_color']); ?>;">
                            <span><?php echo esc_html($settings['app_short_name']); ?></span>
                        </div>
                        <div class="preview-body" id="preview-body" style="background-color: <?php echo esc_attr($settings['background_color']); ?>;">
                            <div class="preview-icon" id="preview-icon">
                                <?php if (!empty($settings['icon_192'])): ?>
                                    <img src="<?php echo esc_url($settings['icon_192']); ?>" alt="App Icon">
                                <?php else: ?>
                                    <div class="preview-icon-placeholder">PWA</div>
                                <?php endif; ?>
                            </div>
                            <p><?php echo esc_html($settings['app_name']); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- نصائح -->
                <div class="wp-pwa-card">
                    <h3><?php _e('نصائح التصميم', 'wp-pwa-converter'); ?></h3>
                    <ul class="wp-pwa-tips">
                        <li><?php _e('استخدم أيقونات بدقة عالية (PNG)', 'wp-pwa-converter'); ?></li>
                        <li><?php _e('اختر ألوان متناسقة مع موقعك', 'wp-pwa-converter'); ?></li>
                        <li><?php _e('تأكد من وضوح الأيقونات على الخلفيات المختلفة', 'wp-pwa-converter'); ?></li>
                    </ul>
                </div>
                
            </div>
        </div>
    </form>
</div>
