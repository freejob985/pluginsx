<?php
/**
 * Admin Settings View
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wp-pwa-settings">
    <h1><?php _e('إعدادات PWA - الإعدادات العامة', 'wp-pwa-converter'); ?></h1>
    
    <form method="post" action="options.php" id="wp-pwa-settings-form">
        <?php settings_fields('wp_pwa_settings'); ?>
        
        <div class="wp-pwa-container">
            <div class="wp-pwa-main-content">
                
                <!-- معلومات التطبيق -->
                <div class="wp-pwa-card">
                    <h2><?php _e('معلومات التطبيق', 'wp-pwa-converter'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="app_name"><?php _e('اسم التطبيق', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="wp_pwa_settings[app_name]" id="app_name" 
                                       value="<?php echo esc_attr($settings['app_name']); ?>" 
                                       class="regular-text" required>
                                <p class="description"><?php _e('الاسم الكامل للتطبيق', 'wp-pwa-converter'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="app_short_name"><?php _e('الاسم المختصر', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="wp_pwa_settings[app_short_name]" id="app_short_name" 
                                       value="<?php echo esc_attr($settings['app_short_name']); ?>" 
                                       class="regular-text" required>
                                <p class="description"><?php _e('يُستخدم في شاشة البداية (12 حرف كحد أقصى)', 'wp-pwa-converter'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="app_description"><?php _e('وصف التطبيق', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <textarea name="wp_pwa_settings[app_description]" id="app_description" 
                                          rows="3" class="large-text"><?php echo esc_textarea($settings['app_description']); ?></textarea>
                                <p class="description"><?php _e('وصف مختصر للتطبيق', 'wp-pwa-converter'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- عناوين URL -->
                <div class="wp-pwa-card">
                    <h2><?php _e('إعدادات URL', 'wp-pwa-converter'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="start_url"><?php _e('صفحة البداية', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <input type="url" name="wp_pwa_settings[start_url]" id="start_url" 
                                       value="<?php echo esc_url($settings['start_url']); ?>" 
                                       class="regular-text" required>
                                <p class="description"><?php _e('الصفحة التي تُفتح عند تشغيل التطبيق', 'wp-pwa-converter'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="scope"><?php _e('نطاق التطبيق', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <input type="url" name="wp_pwa_settings[scope]" id="scope" 
                                       value="<?php echo esc_url($settings['scope']); ?>" 
                                       class="regular-text" required>
                                <p class="description"><?php _e('نطاق الصفحات التي يشملها التطبيق', 'wp-pwa-converter'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- إعدادات العرض -->
                <div class="wp-pwa-card">
                    <h2><?php _e('إعدادات العرض', 'wp-pwa-converter'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="display_mode"><?php _e('وضع العرض', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <select name="wp_pwa_settings[display_mode]" id="display_mode">
                                    <option value="fullscreen" <?php selected($settings['display_mode'], 'fullscreen'); ?>>
                                        <?php _e('ملء الشاشة (Fullscreen)', 'wp-pwa-converter'); ?>
                                    </option>
                                    <option value="standalone" <?php selected($settings['display_mode'], 'standalone'); ?>>
                                        <?php _e('مستقل (Standalone)', 'wp-pwa-converter'); ?>
                                    </option>
                                    <option value="minimal-ui" <?php selected($settings['display_mode'], 'minimal-ui'); ?>>
                                        <?php _e('واجهة بسيطة (Minimal UI)', 'wp-pwa-converter'); ?>
                                    </option>
                                    <option value="browser" <?php selected($settings['display_mode'], 'browser'); ?>>
                                        <?php _e('متصفح (Browser)', 'wp-pwa-converter'); ?>
                                    </option>
                                </select>
                                <p class="description"><?php _e('كيفية عرض التطبيق', 'wp-pwa-converter'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="orientation"><?php _e('اتجاه الشاشة', 'wp-pwa-converter'); ?></label>
                            </th>
                            <td>
                                <select name="wp_pwa_settings[orientation]" id="orientation">
                                    <option value="any" <?php selected($settings['orientation'], 'any'); ?>>
                                        <?php _e('أي اتجاه (Any)', 'wp-pwa-converter'); ?>
                                    </option>
                                    <option value="portrait" <?php selected($settings['orientation'], 'portrait'); ?>>
                                        <?php _e('عمودي (Portrait)', 'wp-pwa-converter'); ?>
                                    </option>
                                    <option value="portrait-primary" <?php selected($settings['orientation'], 'portrait-primary'); ?>>
                                        <?php _e('عمودي أساسي (Portrait Primary)', 'wp-pwa-converter'); ?>
                                    </option>
                                    <option value="landscape" <?php selected($settings['orientation'], 'landscape'); ?>>
                                        <?php _e('أفقي (Landscape)', 'wp-pwa-converter'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(__('حفظ التغييرات', 'wp-pwa-converter')); ?>
                
            </div>
            
            <div class="wp-pwa-sidebar">
                
                <!-- حالة PWA -->
                <div class="wp-pwa-card">
                    <h3><?php _e('حالة PWA', 'wp-pwa-converter'); ?></h3>
                    <div id="pwa-status">
                        <button type="button" class="button button-secondary" id="test-pwa-btn">
                            <?php _e('اختبار PWA', 'wp-pwa-converter'); ?>
                        </button>
                        <div id="pwa-test-results" style="margin-top: 15px;"></div>
                    </div>
                </div>
                
                <!-- روابط سريعة -->
                <div class="wp-pwa-card">
                    <h3><?php _e('روابط سريعة', 'wp-pwa-converter'); ?></h3>
                    <ul class="wp-pwa-quick-links">
                        <li>
                            <a href="<?php echo esc_url(home_url('/manifest.json')); ?>" target="_blank">
                                <?php _e('عرض Manifest', 'wp-pwa-converter'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo esc_url(home_url('/sw.js')); ?>" target="_blank">
                                <?php _e('عرض Service Worker', 'wp-pwa-converter'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="https://web.dev/measure/" target="_blank">
                                <?php _e('قياس الأداء', 'wp-pwa-converter'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- معلومات -->
                <div class="wp-pwa-card">
                    <h3><?php _e('معلومات', 'wp-pwa-converter'); ?></h3>
                    <p><?php _e('Progressive Web App (PWA) هو تطبيق ويب يمكن تثبيته على الأجهزة والعمل دون اتصال بالإنترنت.', 'wp-pwa-converter'); ?></p>
                </div>
                
            </div>
        </div>
    </form>
</div>
