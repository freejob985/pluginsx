<?php
/**
 * كلاس إدارة ملف Manifest
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure WP_PWA_Settings is loaded
if (!class_exists('WP_PWA_Settings')) {
    $settings_file = dirname(__FILE__) . '/class-pwa-settings.php';
    if (file_exists($settings_file)) {
        require_once $settings_file;
    }
}

class WP_PWA_Manifest {
    
    /**
     * إنشاء ملف Manifest
     */
    public static function generate_manifest() {
        $settings = WP_PWA_Settings::get_settings();
        
        $manifest = array(
            'name' => $settings['app_name'],
            'short_name' => $settings['app_short_name'],
            'description' => $settings['app_description'],
            'start_url' => $settings['start_url'],
            'scope' => $settings['scope'],
            'display' => $settings['display_mode'],
            'orientation' => $settings['orientation'],
            'theme_color' => $settings['theme_color'],
            'background_color' => $settings['background_color'],
            'categories' => $settings['categories'],
            'icons' => self::get_icons($settings),
        );
        
        // إضافة Screenshots إذا كانت موجودة
        if (!empty($settings['screenshots'])) {
            $manifest['screenshots'] = $settings['screenshots'];
        }
        
        return apply_filters('wp_pwa_manifest', $manifest);
    }
    
    /**
     * الحصول على الأيقونات
     */
    private static function get_icons($settings) {
        $icons = array();
        
        // أيقونة 192x192
        if (!empty($settings['icon_192'])) {
            $icons[] = array(
                'src' => $settings['icon_192'],
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            );
        } else {
            // استخدام أيقونة افتراضية
            $icons[] = array(
                'src' => WP_PWA_PLUGIN_URL . 'assets/images/icon-192x192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            );
        }
        
        // أيقونة 512x512
        if (!empty($settings['icon_512'])) {
            $icons[] = array(
                'src' => $settings['icon_512'],
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            );
        } else {
            // استخدام أيقونة افتراضية
            $icons[] = array(
                'src' => WP_PWA_PLUGIN_URL . 'assets/images/icon-512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable'
            );
        }
        
        return $icons;
    }
    
    /**
     * تقديم ملف Manifest
     */
    public static function serve_manifest() {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        
        $manifest = self::generate_manifest();
        echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
