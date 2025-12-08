<?php
/**
 * كلاس إدارة الإعدادات
 */

if (!defined('ABSPATH')) {
    exit;
}

class WP_PWA_Settings {
    
    /**
     * الحصول على الإعدادات
     */
    public static function get_settings() {
        $defaults = array(
            'app_name' => get_bloginfo('name'),
            'app_short_name' => get_bloginfo('name'),
            'app_description' => get_bloginfo('description'),
            'theme_color' => '#1e73be',
            'background_color' => '#ffffff',
            'display_mode' => 'standalone',
            'orientation' => 'portrait-primary',
            'offline_enabled' => true,
            'cache_strategy' => 'cache-first',
            'cache_version' => 1,
            'start_url' => home_url('/'),
            'scope' => home_url('/'),
            'icon_192' => '',
            'icon_512' => '',
            'screenshots' => array(),
            'categories' => array('productivity'),
            'offline_page' => '',
            'enable_notifications' => false,
        );
        
        $settings = get_option('wp_pwa_settings', $defaults);
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * حفظ الإعدادات
     */
    public static function save_settings($settings) {
        return update_option('wp_pwa_settings', $settings);
    }
    
    /**
     * الحصول على إعداد محدد
     */
    public static function get_setting($key, $default = '') {
        $settings = self::get_settings();
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
    
    /**
     * تحديث إعداد محدد
     */
    public static function update_setting($key, $value) {
        $settings = self::get_settings();
        $settings[$key] = $value;
        return self::save_settings($settings);
    }
}
