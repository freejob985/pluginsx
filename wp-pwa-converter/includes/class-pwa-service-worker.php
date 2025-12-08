<?php
/**
 * كلاس إدارة Service Worker
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

class WP_PWA_Service_Worker {
    
    /**
     * إنشاء محتوى Service Worker
     */
    public static function generate_service_worker() {
        $settings = WP_PWA_Settings::get_settings();
        $cache_version = 'v' . $settings['cache_version'];
        $cache_strategy = $settings['cache_strategy'];
        $offline_enabled = $settings['offline_enabled'];
        
        ob_start();
        ?>
const CACHE_VERSION = '<?php echo esc_js($cache_version); ?>';
const CACHE_NAME = 'wp-pwa-cache-' + CACHE_VERSION;
const OFFLINE_URL = '<?php echo esc_url($settings['offline_page'] ?: home_url('/')); ?>';

// الملفات التي سيتم تخزينها مباشرة عند التثبيت
const PRECACHE_URLS = [
    '/',
    '/wp-includes/css/dist/block-library/style.min.css',
    '/wp-includes/js/jquery/jquery.min.js',
    <?php echo self::get_precache_urls(); ?>
];

// التثبيت - تخزين الملفات الأساسية
self.addEventListener('install', (event) => {
    console.log('[Service Worker] Installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => {
                console.log('[Service Worker] Caching precache files');
                return cache.addAll(PRECACHE_URLS);
            })
            .then(() => self.skipWaiting())
    );
});

// التفعيل - حذف الكاش القديم
self.addEventListener('activate', (event) => {
    console.log('[Service Worker] Activating...');
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[Service Worker] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch - استراتيجية التخزين المؤقت
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // تجاهل الطلبات من نطاقات أخرى
    if (url.origin !== location.origin) {
        return;
    }
    
    // تجاهل طلبات Admin و Login
    if (url.pathname.startsWith('/wp-admin') || url.pathname.startsWith('/wp-login')) {
        return;
    }
    
    event.respondWith(
        <?php echo self::get_cache_strategy_code($cache_strategy, $offline_enabled); ?>
    );
});

<?php if ($settings['enable_notifications']): ?>
// إشعارات Push
self.addEventListener('push', (event) => {
    const data = event.data ? event.data.json() : {};
    const options = {
        body: data.body || 'لديك إشعار جديد',
        icon: data.icon || '/icon-192x192.png',
        badge: data.badge || '/icon-192x192.png',
        vibrate: [200, 100, 200],
        data: data.data || {}
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'إشعار', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(
        clients.openWindow(event.notification.data.url || '/')
    );
});
<?php endif; ?>

// التعامل مع الرسائل من الصفحة
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CLEAR_CACHE') {
        event.waitUntil(
            caches.delete(CACHE_NAME).then(() => {
                return caches.open(CACHE_NAME);
            })
        );
    }
});

console.log('[Service Worker] Ready');
<?php
        return ob_get_clean();
    }
    
    /**
     * الحصول على كود استراتيجية التخزين المؤقت
     */
    private static function get_cache_strategy_code($strategy, $offline_enabled) {
        switch ($strategy) {
            case 'cache-first':
                return self::cache_first_strategy($offline_enabled);
            case 'network-first':
                return self::network_first_strategy($offline_enabled);
            case 'cache-only':
                return 'caches.match(request)';
            case 'network-only':
                return 'fetch(request)';
            default:
                return self::cache_first_strategy($offline_enabled);
        }
    }
    
    /**
     * استراتيجية Cache First
     */
    private static function cache_first_strategy($offline_enabled) {
        $offline_code = $offline_enabled ? '.catch(() => caches.match(OFFLINE_URL))' : '';
        return "caches.match(request)
            .then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                return fetch(request).then((response) => {
                    if (!response || response.status !== 200 || response.type !== 'basic') {
                        return response;
                    }
                    const responseToCache = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseToCache);
                    });
                    return response;
                }){$offline_code};
            })";
    }
    
    /**
     * استراتيجية Network First
     */
    private static function network_first_strategy($offline_enabled) {
        $offline_code = $offline_enabled ? 'return caches.match(OFFLINE_URL);' : 'throw error;';
        return "fetch(request)
            .then((response) => {
                const responseToCache = response.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(request, responseToCache);
                });
                return response;
            })
            .catch((error) => {
                return caches.match(request).then((cachedResponse) => {
                    if (cachedResponse) {
                        return cachedResponse;
                    }
                    {$offline_code}
                });
            })";
    }
    
    /**
     * الحصول على URLs للتخزين المسبق
     */
    private static function get_precache_urls() {
        $urls = apply_filters('wp_pwa_precache_urls', array());
        $js_urls = array();
        
        foreach ($urls as $url) {
            $js_urls[] = "'" . esc_js($url) . "'";
        }
        
        return implode(",\n    ", $js_urls);
    }
    
    /**
     * تقديم ملف Service Worker
     */
    public static function serve_service_worker() {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('X-Content-Type-Options: nosniff');
        
        echo self::generate_service_worker();
    }
}
