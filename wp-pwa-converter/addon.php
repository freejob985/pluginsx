<?php
/**
 * Plugin Name: WP PWA Converter
 * Description: تحويل موقع WordPress إلى تطبيق PWA كامل مع إمكانية التصفح أوفلاين ولوحة تحكم شاملة
 * Version: 1.0.0
 * Addon Slug: wp-pwa-converter
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Prevent loading multiple times
if (class_exists('WP_PWA_Converter')) {
    return;
}

// Load the main plugin file
$addon_dir = dirname(__FILE__);
$main_plugin_file = $addon_dir . '/wp-pwa-converter.php';

if (file_exists($main_plugin_file)) {
    // Define addon directory constant before loading main file
    // This ensures the main file knows it's being loaded as an addon
    if (!defined('WP_PWA_ADDON_DIR')) {
        define('WP_PWA_ADDON_DIR', $addon_dir . '/');
    }
    
    // Use output buffering to catch any output
    ob_start();
    
    try {
        require_once $main_plugin_file;
        
        // Check if constants were defined (means file loaded successfully)
        if (defined('WP_PWA_VERSION')) {
            // Initialize immediately if plugins_loaded already fired
            if (did_action('plugins_loaded')) {
                if (function_exists('wp_pwa_converter_init')) {
                    wp_pwa_converter_init();
                }
            }
        }
        
        // Show admin notice on activation
        add_action('admin_notices', function() {
            if (get_transient('wp_pwa_converter_activated')) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <strong><?php _e('WP PWA Converter', 'wp-pwa-converter'); ?></strong> - 
                        <?php _e('تم تفعيل الإضافة بنجاح! يمكنك العثور على رابط "PWA" في قائمة الأدمن.', 'wp-pwa-converter'); ?>
                    </p>
                </div>
                <?php
                delete_transient('wp_pwa_converter_activated');
            }
        });
        
        // Clear any output
        ob_end_clean();
    } catch (ParseError $e) {
        ob_end_clean();
        if (function_exists('error_log')) {
            error_log('WP PWA Converter Parse Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        }
    } catch (Exception $e) {
        ob_end_clean();
        if (function_exists('error_log')) {
            error_log('WP PWA Converter Addon Error: ' . $e->getMessage());
        }
    } catch (Error $e) {
        ob_end_clean();
        if (function_exists('error_log')) {
            error_log('WP PWA Converter Addon Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        }
    }
}
