<?php
/**
 * Script للتنفيذ عند حذف البلاجن
 */

// عدم السماح بالوصول المباشر
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// حذف الخيارات من قاعدة البيانات
delete_option('wp_pwa_settings');

// حذف أي بيانات مخزنة في transients
delete_transient('wp_pwa_cache');

// يمكنك إضافة المزيد من عمليات التنظيف هنا إذا لزم الأمر
