<?php
/**
 * Plugin Name: WP PWA Converter
 * Plugin URI: https://example.com/wp-pwa-converter
 * Description: تحويل موقع WordPress إلى تطبيق PWA كامل مع إمكانية التصفح أوفلاين ولوحة تحكم شاملة
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-pwa-converter
 * Domain Path: /languages
 */

// منع الوصول المباشر
if (!defined('ABSPATH')) {
    exit;
}

// منع التحميل المتكرر
if (defined('WP_PWA_VERSION')) {
    return;
}

// تعريف الثوابت
if (!defined('WP_PWA_VERSION')) {
    define('WP_PWA_VERSION', '1.0.0');
}

// Get plugin directory - check if loaded as addon first
if (defined('WP_PWA_ADDON_DIR')) {
    // Loaded as internal addon - use addon directory
    $wp_pwa_plugin_dir = WP_PWA_ADDON_DIR;
} else {
    // Regular plugin - use current file's directory
    $wp_pwa_plugin_dir = dirname(__FILE__);
    $wp_pwa_plugin_dir = rtrim($wp_pwa_plugin_dir, '/\\') . '/';
}

if (!defined('WP_PWA_PLUGIN_DIR')) {
    define('WP_PWA_PLUGIN_DIR', $wp_pwa_plugin_dir);
}

if (!defined('WP_PWA_PLUGIN_URL')) {
    // Try to use plugin_dir_url if available, otherwise calculate manually
    if (function_exists('plugin_dir_url')) {
        define('WP_PWA_PLUGIN_URL', plugin_dir_url(__FILE__));
    } else {
        // Fallback: calculate URL manually
        $wp_content_dir = rtrim(WP_CONTENT_DIR, '/\\') . '/';
        $wp_content_url = content_url();
        
        $relative_path = str_replace($wp_content_dir, '', $wp_pwa_plugin_dir);
        $relative_path = str_replace('\\', '/', $relative_path); // Normalize slashes
        
        // Check if it's in uploads directory (internal addon)
        if (strpos($relative_path, 'uploads/orders-jet-addons') !== false) {
            $upload_dir = wp_upload_dir();
            $wp_pwa_plugin_url = rtrim($upload_dir['baseurl'], '/') . '/' . str_replace('uploads/', '', $relative_path);
        } else {
            // Regular plugin in plugins directory
            $wp_pwa_plugin_url = rtrim($wp_content_url, '/') . '/' . $relative_path;
        }
        
        define('WP_PWA_PLUGIN_URL', $wp_pwa_plugin_url);
    }
}

if (!defined('WP_PWA_PLUGIN_FILE')) {
    define('WP_PWA_PLUGIN_FILE', __FILE__);
}

/**
 * الكلاس الرئيسي للبلاجن
 */
class WP_PWA_Converter {
    
    private static $instance = null;
    
    /**
     * الحصول على نسخة واحدة من الكلاس (Singleton)
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * تهيئة البلاجن
     */
    private function init() {
        // تحميل الملفات المطلوبة
        $this->load_dependencies();
        
        // تسجيل الهوكس
        $this->register_hooks();
        
        // تحميل ملفات الترجمة
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * تحميل الملفات المطلوبة
     */
    private function load_dependencies() {
        // Get includes directory - use WP_PWA_PLUGIN_DIR which is set correctly in addon.php
        // This ensures correct path even when loaded as internal addon
        $includes_dir = WP_PWA_PLUGIN_DIR . 'includes/';
        
        // Load classes in order (Settings first as others depend on it)
        $files = array(
            'class-pwa-settings.php',      // Load first - required by others
            'class-pwa-manifest.php',
            'class-pwa-service-worker.php',
            'class-pwa-admin.php'
        );
        
        foreach ($files as $file) {
            $file_path = $includes_dir . $file;
            
            // Try absolute path first
            if (!file_exists($file_path)) {
                // Try relative to plugin directory
                $file_path = WP_PWA_PLUGIN_DIR . 'includes/' . $file;
            }
            
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                // Log missing file for debugging - always log for troubleshooting
                error_log('WP PWA Converter: Missing file - ' . $file_path);
                error_log('WP PWA Converter: __FILE__ = ' . __FILE__);
                error_log('WP PWA Converter: Plugin DIR = ' . WP_PWA_PLUGIN_DIR);
                error_log('WP PWA Converter: Includes DIR = ' . $includes_dir);
                error_log('WP PWA Converter: dirname(__FILE__) = ' . dirname(__FILE__));
            }
        }
        
        // Verify critical classes are loaded
        if (!class_exists('WP_PWA_Settings')) {
            // Try multiple paths as fallback
            $possible_paths = array(
                $includes_dir . 'class-pwa-settings.php',
                WP_PWA_PLUGIN_DIR . 'includes/class-pwa-settings.php',
            );
            
            // Add addon directory path if defined
            if (defined('WP_PWA_ADDON_DIR')) {
                $possible_paths[] = WP_PWA_ADDON_DIR . 'includes/class-pwa-settings.php';
            }
            
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    if (class_exists('WP_PWA_Settings')) {
                        break;
                    }
                }
            }
            
            // If still not loaded, show error with all attempted paths
            if (!class_exists('WP_PWA_Settings')) {
                add_action('admin_notices', function() use ($includes_dir, $possible_paths) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>' . __('خطأ: لم يتم تحميل كلاس الإعدادات (WP_PWA_Settings).', 'wp-pwa-converter') . '</strong><br>';
                    echo __('المسارات المحاولة:', 'wp-pwa-converter') . '<br>';
                    foreach ($possible_paths as $path) {
                        $exists = file_exists($path) ? '✓ موجود' : '✗ غير موجود';
                        echo '&nbsp;&nbsp;- ' . esc_html($path) . ' <strong>(' . $exists . ')</strong><br>';
                    }
                    echo '<br>' . __('يرجى التأكد من أن ملف class-pwa-settings.php موجود في مجلد includes.', 'wp-pwa-converter');
                    echo '</p></div>';
                });
                return;
            }
        }
        
        // Initialize admin class if in admin area (but don't create multiple instances)
        if (is_admin() && class_exists('WP_PWA_Admin') && !isset($GLOBALS['wp_pwa_admin_instance'])) {
            $GLOBALS['wp_pwa_admin_instance'] = new WP_PWA_Admin();
        }
    }
    
    /**
     * تسجيل الهوكس
     */
    private function register_hooks() {
        // Only register activation/deactivation hooks if this is a regular plugin
        // For internal addons, these hooks won't work correctly
        if (strpos(WP_PWA_PLUGIN_FILE, 'addon.php') === false) {
            register_activation_hook(WP_PWA_PLUGIN_FILE, array($this, 'activate'));
            register_deactivation_hook(WP_PWA_PLUGIN_FILE, array($this, 'deactivate'));
        }
        
        add_action('wp_head', array($this, 'add_meta_tags'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'register_service_worker_routes'));
        
        // Initialize settings on first load if they don't exist
        add_action('init', array($this, 'maybe_initialize_settings'), 1);
    }
    
    /**
     * تهيئة الإعدادات إذا لم تكن موجودة
     */
    public function maybe_initialize_settings() {
        $settings = get_option('wp_pwa_settings', false);
        if ($settings === false) {
            $this->initialize_default_settings();
        }
    }
    
    /**
     * تهيئة الإعدادات الافتراضية
     */
    private function initialize_default_settings() {
        // إنشاء الخيارات الافتراضية
        $default_options = array(
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
        );
        
        add_option('wp_pwa_settings', $default_options);
    }
    
    /**
     * تفعيل البلاجن
     */
    public function activate() {
        $this->initialize_default_settings();
        
        // مسح الكاش
        flush_rewrite_rules();
    }
    
    /**
     * إلغاء تفعيل البلاجن
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * تحميل ملفات الترجمة
     */
    public function load_textdomain() {
        load_plugin_textdomain('wp-pwa-converter', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * إضافة Meta Tags للـ PWA
     */
    public function add_meta_tags() {
        $settings = get_option('wp_pwa_settings', array());
        
        // Default values if settings don't exist
        $theme_color = isset($settings['theme_color']) ? $settings['theme_color'] : '#1e73be';
        $app_short_name = isset($settings['app_short_name']) ? $settings['app_short_name'] : get_bloginfo('name');
        
        ?>
        <!-- PWA Meta Tags -->
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="<?php echo esc_attr($theme_color); ?>">
        <meta name="apple-mobile-web-app-title" content="<?php echo esc_attr($app_short_name); ?>">
        <meta name="theme-color" content="<?php echo esc_attr($theme_color); ?>">
        <link rel="manifest" href="<?php echo esc_url(home_url('/manifest.json')); ?>">
        <?php
    }
    
    /**
     * تحميل السكريبتات والأنماط
     */
    public function enqueue_scripts() {
        // تحميل ملف CSS الرئيسي
        wp_enqueue_style(
            'wp-pwa-style',
            WP_PWA_PLUGIN_URL . 'assets/css/pwa-style.css',
            array(),
            WP_PWA_VERSION
        );
        
        // تحميل ملف JavaScript الرئيسي
        wp_enqueue_script(
            'wp-pwa-main',
            WP_PWA_PLUGIN_URL . 'assets/js/pwa-main.js',
            array('jquery'),
            WP_PWA_VERSION,
            true
        );
        
        // تمرير البيانات إلى JavaScript
        wp_localize_script('wp-pwa-main', 'wpPwaData', array(
            'serviceWorkerUrl' => home_url('/sw.js'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_pwa_nonce'),
        ));
    }
    
    /**
     * تسجيل routes للـ Service Worker و Manifest
     */
    public function register_service_worker_routes() {
        add_rewrite_rule('^manifest\.json$', 'index.php?pwa_manifest=1', 'top');
        add_rewrite_rule('^sw\.js$', 'index.php?pwa_service_worker=1', 'top');
        
        add_filter('query_vars', function($vars) {
            $vars[] = 'pwa_manifest';
            $vars[] = 'pwa_service_worker';
            return $vars;
        });
        
        add_action('template_redirect', function() {
            if (get_query_var('pwa_manifest') && class_exists('WP_PWA_Manifest')) {
                WP_PWA_Manifest::serve_manifest();
                exit;
            }
            if (get_query_var('pwa_service_worker') && class_exists('WP_PWA_Service_Worker')) {
                WP_PWA_Service_Worker::serve_service_worker();
                exit;
            }
        });
    }
}

// تشغيل البلاجن
if (!function_exists('wp_pwa_converter_init')) {
    function wp_pwa_converter_init() {
        // Prevent multiple initializations
        static $initialized = false;
        if ($initialized) {
            return WP_PWA_Converter::get_instance();
        }
        
        $initialized = true;
        return WP_PWA_Converter::get_instance();
    }
    
    // تهيئة البلاجن
    add_action('plugins_loaded', 'wp_pwa_converter_init');
}
