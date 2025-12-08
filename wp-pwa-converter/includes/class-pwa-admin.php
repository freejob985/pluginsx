<?php
/**
 * كلاس لوحة التحكم الإدارية
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

class WP_PWA_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_wp_pwa_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_wp_pwa_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_wp_pwa_test_pwa', array($this, 'ajax_test_pwa'));
    }
    
    /**
     * إضافة قائمة إدارية
     */
    public function add_admin_menu() {
        add_menu_page(
            __('إعدادات PWA', 'wp-pwa-converter'),
            __('PWA', 'wp-pwa-converter'),
            'manage_options',
            'wp-pwa-settings',
            array($this, 'render_settings_page'),
            'dashicons-smartphone',
            30
        );
        
        add_submenu_page(
            'wp-pwa-settings',
            __('الإعدادات العامة', 'wp-pwa-converter'),
            __('الإعدادات العامة', 'wp-pwa-converter'),
            'manage_options',
            'wp-pwa-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'wp-pwa-settings',
            __('التصميم والمظهر', 'wp-pwa-converter'),
            __('التصميم والمظهر', 'wp-pwa-converter'),
            'manage_options',
            'wp-pwa-appearance',
            array($this, 'render_appearance_page')
        );
        
        add_submenu_page(
            'wp-pwa-settings',
            __('التخزين المؤقت', 'wp-pwa-converter'),
            __('التخزين المؤقت', 'wp-pwa-converter'),
            'manage_options',
            'wp-pwa-cache',
            array($this, 'render_cache_page')
        );
        
        add_submenu_page(
            'wp-pwa-settings',
            __('الإشعارات', 'wp-pwa-converter'),
            __('الإشعارات', 'wp-pwa-converter'),
            'manage_options',
            'wp-pwa-notifications',
            array($this, 'render_notifications_page')
        );
    }
    
    /**
     * تسجيل الإعدادات
     */
    public function register_settings() {
        register_setting('wp_pwa_settings', 'wp_pwa_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
    }
    
    /**
     * تنظيف الإعدادات
     */
    public function sanitize_settings($settings) {
        $sanitized = array();
        
        $sanitized['app_name'] = sanitize_text_field($settings['app_name']);
        $sanitized['app_short_name'] = sanitize_text_field($settings['app_short_name']);
        $sanitized['app_description'] = sanitize_textarea_field($settings['app_description']);
        $sanitized['theme_color'] = sanitize_hex_color($settings['theme_color']);
        $sanitized['background_color'] = sanitize_hex_color($settings['background_color']);
        $sanitized['display_mode'] = sanitize_text_field($settings['display_mode']);
        $sanitized['orientation'] = sanitize_text_field($settings['orientation']);
        $sanitized['offline_enabled'] = isset($settings['offline_enabled']) ? 1 : 0;
        $sanitized['cache_strategy'] = sanitize_text_field($settings['cache_strategy']);
        $sanitized['cache_version'] = intval($settings['cache_version']);
        $sanitized['start_url'] = esc_url_raw($settings['start_url']);
        $sanitized['scope'] = esc_url_raw($settings['scope']);
        $sanitized['icon_192'] = esc_url_raw($settings['icon_192']);
        $sanitized['icon_512'] = esc_url_raw($settings['icon_512']);
        $sanitized['offline_page'] = esc_url_raw($settings['offline_page']);
        $sanitized['enable_notifications'] = isset($settings['enable_notifications']) ? 1 : 0;
        
        return $sanitized;
    }
    
    /**
     * تحميل السكريبتات الإدارية
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wp-pwa') === false) {
            return;
        }
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();
        
        wp_enqueue_style(
            'wp-pwa-admin-style',
            WP_PWA_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            WP_PWA_VERSION
        );
        
        wp_enqueue_script(
            'wp-pwa-admin-script',
            WP_PWA_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery', 'wp-color-picker'),
            WP_PWA_VERSION,
            true
        );
        
        wp_localize_script('wp-pwa-admin-script', 'wpPwaAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_pwa_admin_nonce'),
            'strings' => array(
                'saving' => __('جاري الحفظ...', 'wp-pwa-converter'),
                'saved' => __('تم الحفظ بنجاح!', 'wp-pwa-converter'),
                'error' => __('حدث خطأ!', 'wp-pwa-converter'),
            )
        ));
    }
    
    /**
     * عرض صفحة الإعدادات
     */
    public function render_settings_page() {
        if (!class_exists('WP_PWA_Settings')) {
            wp_die(__('خطأ: لم يتم تحميل كلاس الإعدادات', 'wp-pwa-converter'));
        }
        $settings = WP_PWA_Settings::get_settings();
        include WP_PWA_PLUGIN_DIR . 'views/admin-settings.php';
    }
    
    /**
     * عرض صفحة المظهر
     */
    public function render_appearance_page() {
        if (!class_exists('WP_PWA_Settings')) {
            wp_die(__('خطأ: لم يتم تحميل كلاس الإعدادات', 'wp-pwa-converter'));
        }
        $settings = WP_PWA_Settings::get_settings();
        include WP_PWA_PLUGIN_DIR . 'views/admin-appearance.php';
    }
    
    /**
     * عرض صفحة التخزين المؤقت
     */
    public function render_cache_page() {
        if (!class_exists('WP_PWA_Settings')) {
            wp_die(__('خطأ: لم يتم تحميل كلاس الإعدادات', 'wp-pwa-converter'));
        }
        $settings = WP_PWA_Settings::get_settings();
        include WP_PWA_PLUGIN_DIR . 'views/admin-cache.php';
    }
    
    /**
     * عرض صفحة الإشعارات
     */
    public function render_notifications_page() {
        if (!class_exists('WP_PWA_Settings')) {
            wp_die(__('خطأ: لم يتم تحميل كلاس الإعدادات', 'wp-pwa-converter'));
        }
        $settings = WP_PWA_Settings::get_settings();
        include WP_PWA_PLUGIN_DIR . 'views/admin-notifications.php';
    }
    
    /**
     * AJAX: حفظ الإعدادات
     */
    public function ajax_save_settings() {
        check_ajax_referer('wp_pwa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        if (!class_exists('WP_PWA_Settings')) {
            wp_send_json_error(array('message' => __('خطأ: لم يتم تحميل كلاس الإعدادات', 'wp-pwa-converter')));
        }
        
        $settings = $_POST['settings'];
        $sanitized = $this->sanitize_settings($settings);
        
        if (WP_PWA_Settings::save_settings($sanitized)) {
            wp_send_json_success(array('message' => __('تم الحفظ بنجاح!', 'wp-pwa-converter')));
        } else {
            wp_send_json_error(array('message' => __('فشل الحفظ!', 'wp-pwa-converter')));
        }
    }
    
    /**
     * AJAX: مسح الكاش
     */
    public function ajax_clear_cache() {
        check_ajax_referer('wp_pwa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        if (!class_exists('WP_PWA_Settings')) {
            wp_send_json_error(array('message' => __('خطأ: لم يتم تحميل كلاس الإعدادات', 'wp-pwa-converter')));
        }
        
        // زيادة رقم إصدار الكاش
        $cache_version = WP_PWA_Settings::get_setting('cache_version', 1);
        WP_PWA_Settings::update_setting('cache_version', $cache_version + 1);
        
        wp_send_json_success(array('message' => __('تم مسح الكاش بنجاح!', 'wp-pwa-converter')));
    }
    
    /**
     * AJAX: اختبار PWA
     */
    public function ajax_test_pwa() {
        check_ajax_referer('wp_pwa_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $tests = array(
            'manifest' => $this->test_manifest(),
            'service_worker' => $this->test_service_worker(),
            'https' => $this->test_https(),
            'icons' => $this->test_icons(),
        );
        
        wp_send_json_success($tests);
    }
    
    /**
     * اختبار Manifest
     */
    private function test_manifest() {
        $manifest_url = home_url('/manifest.json');
        $response = wp_remote_get($manifest_url);
        
        if (is_wp_error($response)) {
            return array('status' => 'error', 'message' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $manifest = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('status' => 'error', 'message' => __('فشل تحليل Manifest', 'wp-pwa-converter'));
        }
        
        return array('status' => 'success', 'message' => __('Manifest يعمل بشكل صحيح', 'wp-pwa-converter'));
    }
    
    /**
     * اختبار Service Worker
     */
    private function test_service_worker() {
        $sw_url = home_url('/sw.js');
        $response = wp_remote_get($sw_url);
        
        if (is_wp_error($response)) {
            return array('status' => 'error', 'message' => $response->get_error_message());
        }
        
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            return array('status' => 'error', 'message' => __('Service Worker غير متاح', 'wp-pwa-converter'));
        }
        
        return array('status' => 'success', 'message' => __('Service Worker يعمل بشكل صحيح', 'wp-pwa-converter'));
    }
    
    /**
     * اختبار HTTPS
     */
    private function test_https() {
        if (is_ssl() || (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local')) {
            return array('status' => 'success', 'message' => __('HTTPS مفعّل', 'wp-pwa-converter'));
        }
        
        return array('status' => 'warning', 'message' => __('PWA يتطلب HTTPS في الإنتاج', 'wp-pwa-converter'));
    }
    
    /**
     * اختبار الأيقونات
     */
    private function test_icons() {
        if (!class_exists('WP_PWA_Settings')) {
            return array('status' => 'error', 'message' => __('خطأ: لم يتم تحميل كلاس الإعدادات', 'wp-pwa-converter'));
        }
        
        $settings = WP_PWA_Settings::get_settings();
        
        if (empty($settings['icon_192']) || empty($settings['icon_512'])) {
            return array('status' => 'warning', 'message' => __('يُنصح بإضافة أيقونات مخصصة', 'wp-pwa-converter'));
        }
        
        return array('status' => 'success', 'message' => __('الأيقونات متوفرة', 'wp-pwa-converter'));
    }
}

// تهيئة لوحة التحكم (فقط إذا لم يتم تحميلها من قبل)
if (class_exists('WP_PWA_Admin') && !isset($GLOBALS['wp_pwa_admin_instance'])) {
    $GLOBALS['wp_pwa_admin_instance'] = new WP_PWA_Admin();
}
