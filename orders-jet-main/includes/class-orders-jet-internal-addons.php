<?php
declare(strict_types=1);
/**
 * Orders Jet - Internal Addons Manager
 * Manages internal add-ons (plugins within plugin) system
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Internal_Addons {
    
    /**
     * Option name for storing addon statuses
     */
    const OPTION_NAME = 'oj_internal_addons_status';
    
    /**
     * Addon directory name
     */
    const ADDON_DIR_NAME = 'orders-jet-addons';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize on admin
        if (is_admin()) {
            add_action('admin_menu', array($this, 'register_admin_page'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
            add_action('admin_init', array($this, 'handle_actions'));
            
            // AJAX handlers
            add_action('wp_ajax_oj_addon_toggle_status', array($this, 'ajax_toggle_status'));
            add_action('wp_ajax_oj_addon_delete', array($this, 'ajax_delete_addon'));
        }
        
        // Load active addons - multiple hooks to ensure they load early
        add_action('plugins_loaded', array($this, 'load_active_addons'), 5);
        add_action('init', array($this, 'load_active_addons'), 5);
        
        // Also load on admin_menu in case they weren't loaded yet
        add_action('admin_menu', array($this, 'load_active_addons'), 1);
        
        // Create directory on init (will be created if doesn't exist)
        add_action('admin_init', array($this, 'maybe_create_directory'));
    }
    
    /**
     * Get addons directory path
     */
    public static function get_addons_dir(): string {
        $upload_dir = wp_upload_dir();
        $addons_dir = trailingslashit($upload_dir['basedir']) . self::ADDON_DIR_NAME . '/';
        return $addons_dir;
    }
    
    /**
     * Get addons directory URL
     */
    public static function get_addons_url(): string {
        $upload_dir = wp_upload_dir();
        $addons_url = trailingslashit($upload_dir['baseurl']) . self::ADDON_DIR_NAME . '/';
        return $addons_url;
    }
    
    /**
     * Maybe create addons directory if it doesn't exist
     */
    public function maybe_create_directory(): void {
        $this->create_addons_directory();
    }
    
    /**
     * Create addons directory
     */
    public function create_addons_directory(): void {
        $addons_dir = self::get_addons_dir();
        
        if (!file_exists($addons_dir)) {
            wp_mkdir_p($addons_dir);
            
            // Create .htaccess for security
            $htaccess_content = "# Deny direct access\n";
            $htaccess_content .= "Order Deny,Allow\n";
            $htaccess_content .= "Deny from all\n";
            file_put_contents($addons_dir . '.htaccess', $htaccess_content);
            
            // Create index.php for security
            file_put_contents($addons_dir . 'index.php', "<?php\n// Silence is golden.\n");
        }
        
        // Initialize option if not exists
        if (get_option(self::OPTION_NAME) === false) {
            update_option(self::OPTION_NAME, array());
        }
    }
    
    /**
     * Register admin page
     */
    public function register_admin_page(): void {
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'orders-overview',
                __('Internal Addons', 'orders-jet'),
                __('🔌 Internal Addons', 'orders-jet'),
                'manage_options',
                'orders-jet-addons',
                array($this, 'render_admin_page')
            );
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook): void {
        if ($hook !== 'orders_page_orders-jet-addons') {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'oj-internal-addons-admin',
            ORDERS_JET_PLUGIN_URL . 'assets/css/internal-addons-admin.css',
            array(),
            ORDERS_JET_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'oj-internal-addons-admin',
            ORDERS_JET_PLUGIN_URL . 'assets/js/internal-addons-admin.js',
            array('jquery'),
            ORDERS_JET_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('oj-internal-addons-admin', 'ojAddons', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oj_addons_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this addon? This action cannot be undone.', 'orders-jet'),
                'error' => __('An error occurred. Please try again.', 'orders-jet'),
                'success' => __('Operation completed successfully.', 'orders-jet'),
            )
        ));
    }
    
    /**
     * Handle admin actions (upload, install)
     */
    public function handle_actions(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Handle file upload
        if (isset($_POST['oj_upload_addon']) && isset($_FILES['addon_file'])) {
            check_admin_referer('oj_upload_addon');
            $this->handle_upload();
        }
    }
    
    /**
     * Handle addon file upload
     */
    private function handle_upload(): void {
        if (!isset($_FILES['addon_file']) || $_FILES['addon_file']['error'] !== UPLOAD_ERR_OK) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Error uploading file. Please try again.', 'orders-jet') . '</p></div>';
            });
            return;
        }
        
        $file = $_FILES['addon_file'];
        
        // Check file type
        $file_type = wp_check_filetype($file['name']);
        if ($file_type['ext'] !== 'zip') {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . __('Only ZIP files are allowed.', 'orders-jet') . '</p></div>';
            });
            return;
        }
        
        // Create temporary file
        $tmp_file = $file['tmp_name'];
        
        // Extract ZIP
        $result = $this->install_addon_from_zip($tmp_file);
        
        if (is_wp_error($result)) {
            add_action('admin_notices', function() use ($result) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            });
        } else {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>' . __('Addon installed successfully!', 'orders-jet') . '</p></div>';
            });
        }
    }
    
    /**
     * Install addon from ZIP file
     */
    private function install_addon_from_zip(string $zip_path) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('no_zip', __('PHP ZipArchive extension is required.', 'orders-jet'));
        }
        
        $zip = new ZipArchive();
        $result = $zip->open($zip_path);
        
        if ($result !== true) {
            return new WP_Error('zip_open', __('Failed to open ZIP file.', 'orders-jet'));
        }
        
        // Get addon slug from first directory
        $first_entry = $zip->getNameIndex(0);
        if (!$first_entry) {
            $zip->close();
            return new WP_Error('invalid_zip', __('Invalid ZIP file structure.', 'orders-jet'));
        }
        
        // Extract slug from path
        $parts = explode('/', trim($first_entry, '/'));
        $addon_slug = sanitize_file_name($parts[0]);
        
        if (empty($addon_slug)) {
            $zip->close();
            return new WP_Error('invalid_slug', __('Could not determine addon slug from ZIP file.', 'orders-jet'));
        }
        
        // Check if addon already exists
        $addons_dir = self::get_addons_dir();
        $addon_path = $addons_dir . $addon_slug . '/';
        
        if (file_exists($addon_path)) {
            $zip->close();
            return new WP_Error('exists', sprintf(__('Addon "%s" already exists. Please delete it first.', 'orders-jet'), $addon_slug));
        }
        
        // Extract to temporary directory first
        $tmp_dir = get_temp_dir() . 'oj-addon-' . uniqid() . '/';
        wp_mkdir_p($tmp_dir);
        
        if (!$zip->extractTo($tmp_dir)) {
            $zip->close();
            return new WP_Error('extract', __('Failed to extract ZIP file.', 'orders-jet'));
        }
        
        $zip->close();
        
        // Move to final location
        if (!file_exists($addons_dir)) {
            wp_mkdir_p($addons_dir);
        }
        
        // Find the addon.php file - search recursively
        $addon_file = null;
        $found_dir = null;
        
        // Helper function to search for addon.php recursively
        $search_addon_file = function($dir) use (&$search_addon_file, &$addon_file, &$found_dir) {
            // Normalize directory path
            $dir = rtrim(str_replace('\\', '/', $dir), '/');
            
            // Check if addon.php exists in current directory (multiple path formats)
            $possible_paths = array(
                $dir . '/addon.php',
                $dir . '\\addon.php',
                $dir . DIRECTORY_SEPARATOR . 'addon.php'
            );
            
            foreach ($possible_paths as $path) {
                if (file_exists($path) && is_file($path)) {
                    $addon_file = $path;
                    $found_dir = $dir;
                    return true;
                }
            }
            
            // Search in subdirectories
            $items = @scandir($dir);
            if ($items === false) {
                return false;
            }
            
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                
                $item_path = $dir . '/' . $item;
                
                if (is_dir($item_path)) {
                    if ($search_addon_file($item_path)) {
                        return true;
                    }
                }
            }
            
            return false;
        };
        
        // Start searching from temp directory
        $search_addon_file($tmp_dir);
        
        if (!$addon_file || !$found_dir) {
            // Instead of failing, create a default addon.php file
            
            // Find the main directory in the extracted files
            $items = @scandir($tmp_dir);
            $main_dir = null;
            
            if ($items !== false) {
                $items = array_diff($items, array('.', '..'));
                foreach ($items as $item) {
                    $item_path = $tmp_dir . '/' . $item;
                    if (is_dir($item_path)) {
                        $main_dir = $item_path;
                        $found_dir = $item_path;
                        break;
                    }
                }
            }
            
            // If no directory found, use temp dir itself
            if (!$main_dir) {
                $main_dir = $tmp_dir;
                $found_dir = $tmp_dir;
            }
            
            // Generate addon slug from directory name
            $dir_name = basename($main_dir);
            $addon_slug = sanitize_file_name($dir_name);
            
            // Create a default addon.php file
            $addon_file = $main_dir . '/addon.php';
            $default_addon_content = $this->generate_default_addon_file($addon_slug, $dir_name);
            
            if (!file_put_contents($addon_file, $default_addon_content)) {
                $this->delete_directory($tmp_dir);
                return new WP_Error('create_file', __('Failed to create addon.php file.', 'orders-jet'));
            }
            
            // Set metadata for the created addon
            $metadata = array(
                'Plugin Name' => $dir_name,
                'Description' => __('Auto-generated addon', 'orders-jet'),
                'Version' => '1.0.0',
                'Addon Slug' => $addon_slug,
                'Author' => 'Unknown'
            );
        } else {
            // Get addon metadata from the existing or created file
            $metadata = $this->get_addon_metadata($addon_file);
            if (is_wp_error($metadata)) {
                $this->delete_directory($tmp_dir);
                return $metadata;
            }
        }
        
        // Use slug from metadata if available
        if (!empty($metadata['Addon Slug'])) {
            $addon_slug = sanitize_file_name($metadata['Addon Slug']);
            $addon_path = $addons_dir . $addon_slug . '/';
        } else {
            // Use the directory name as slug if metadata doesn't have it
            $addon_path = $addons_dir . $addon_slug . '/';
        }
        
        // Normalize paths for Windows compatibility
        $found_dir = rtrim(str_replace('\\', '/', $found_dir), '/');
        $addon_path = rtrim(str_replace('\\', '/', $addon_path), '/');
        
        // Ensure destination directory doesn't exist
        if (file_exists($addon_path)) {
            $this->delete_directory($tmp_dir);
            return new WP_Error('exists', sprintf(__('Addon directory "%s" already exists. Please delete it first.', 'orders-jet'), $addon_slug));
        }
        
        // Move files to final location
        // Always use copy_directory for better reliability and file cleaning
        if (!wp_mkdir_p($addon_path)) {
            $this->delete_directory($tmp_dir);
            return new WP_Error('move', __('Failed to create addon directory.', 'orders-jet'));
        }
        
        // Copy all files using safe copy method
        $copy_result = $this->copy_directory($found_dir, $addon_path);
        if (!$copy_result) {
            $this->delete_directory($tmp_dir);
            $this->delete_directory($addon_path);
            return new WP_Error('move', __('Failed to copy addon files to destination.', 'orders-jet'));
        }
        
        // Verify and clean addon.php file after copy
        $final_addon_file = $addon_path . 'addon.php';
        if (file_exists($final_addon_file)) {
            // Clean and validate the file
            $clean_result = $this->clean_and_validate_php_file($final_addon_file);
            if ($clean_result !== true) {
                $this->delete_directory($tmp_dir);
                $this->delete_directory($addon_path);
                return new WP_Error('syntax_error', sprintf(
                    __('Copied addon.php file has syntax errors: %s', 'orders-jet'),
                    $clean_result
                ));
            }
        }
        
        // Clean up temp directory
        $this->delete_directory($tmp_dir);
        
        // Set addon as inactive by default
        $this->set_addon_status($addon_slug, 'inactive');
        
        // Set transient for activation notice (if addon has this feature)
        if ($addon_slug === 'sample-welcome-addon') {
            set_transient('oj_sample_addon_activated', true, 30);
        }
        
        return true;
    }
    
    /**
     * Get addon metadata from addon.php file
     */
    private function get_addon_metadata(string $file_path) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('addon.php file not found.', 'orders-jet'));
        }
        
        $file_content = file_get_contents($file_path);
        
        // Parse plugin header
        $headers = array(
            'Plugin Name' => 'Plugin Name',
            'Description' => 'Description',
            'Version' => 'Version',
            'Addon Slug' => 'Addon Slug',
            'Author' => 'Author',
        );
        
        $metadata = get_file_data($file_path, $headers);
        
        // Validate required fields
        if (empty($metadata['Plugin Name'])) {
            return new WP_Error('invalid_header', __('Plugin Name is required in addon.php header.', 'orders-jet'));
        }
        
        // Set default slug if not provided
        if (empty($metadata['Addon Slug'])) {
            $metadata['Addon Slug'] = sanitize_file_name($metadata['Plugin Name']);
        }
        
        return $metadata;
    }
    
    /**
     * Generate a default addon.php file content
     */
    private function generate_default_addon_file(string $slug, string $name): string {
        $sanitized_name = ucwords(str_replace(array('-', '_'), ' ', $name));
        
        return "<?php
/**
 * Plugin Name: {$sanitized_name}
 * Description: Auto-generated addon file
 * Version: 1.0.0
 * Addon Slug: {$slug}
 * Author: Unknown
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main addon function
 * Add your custom code here
 */
function {$slug}_init() {
    // Add your initialization code here
}

// Hook into WordPress
add_action('init', '{$slug}_init');
";
    }
    
    /**
     * Get all installed addons
     */
    public function get_all_addons(): array {
        $addons_dir = self::get_addons_dir();
        $addons = array();
        $statuses = get_option(self::OPTION_NAME, array());
        
        if (!file_exists($addons_dir)) {
            return $addons;
        }
        
        $dirs = glob($addons_dir . '*', GLOB_ONLYDIR);
        
        foreach ($dirs as $dir) {
            $addon_slug = basename($dir);
            $addon_file = $dir . '/addon.php';
            
            if (!file_exists($addon_file)) {
                continue;
            }
            
            $metadata = $this->get_addon_metadata($addon_file);
            if (is_wp_error($metadata)) {
                continue;
            }
            
            $addons[$addon_slug] = array(
                'slug' => $addon_slug,
                'name' => $metadata['Plugin Name'] ?? $addon_slug,
                'description' => $metadata['Description'] ?? '',
                'version' => $metadata['Version'] ?? '1.0.0',
                'author' => $metadata['Author'] ?? '',
                'path' => $dir,
                'file' => $addon_file,
                'status' => isset($statuses[$addon_slug]) ? $statuses[$addon_slug] : 'inactive',
            );
        }
        
        return $addons;
    }
    
    /**
     * Set addon status
     */
    public function set_addon_status(string $slug, string $status): void {
        $statuses = get_option(self::OPTION_NAME, array());
        $statuses[$slug] = $status;
        update_option(self::OPTION_NAME, $statuses);
    }
    
    /**
     * Get addon status
     */
    public function get_addon_status(string $slug): string {
        $statuses = get_option(self::OPTION_NAME, array());
        return isset($statuses[$slug]) ? $statuses[$slug] : 'inactive';
    }
    
    /**
     * Toggle addon status
     */
    public function toggle_addon_status(string $slug): bool {
        $current_status = $this->get_addon_status($slug);
        $new_status = ($current_status === 'active') ? 'inactive' : 'active';
        $this->set_addon_status($slug, $new_status);
        return true;
    }
    
    /**
     * Delete addon
     */
    public function delete_addon(string $slug): bool {
        $addons_dir = self::get_addons_dir();
        $addon_path = $addons_dir . $slug . '/';
        
        if (!file_exists($addon_path)) {
            return false;
        }
        
        // Remove from statuses
        $statuses = get_option(self::OPTION_NAME, array());
        unset($statuses[$slug]);
        update_option(self::OPTION_NAME, $statuses);
        
        // Delete directory
        return $this->delete_directory($addon_path);
    }
    
    /**
     * Copy directory recursively
     */
    private function copy_directory(string $source, string $destination): bool {
        if (!is_dir($source)) {
            return false;
        }
        
        if (!is_dir($destination)) {
            if (!wp_mkdir_p($destination)) {
                return false;
            }
        }
        
        $items = scandir($source);
        if ($items === false) {
            return false;
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $source_path = $source . '/' . $item;
            $dest_path = $destination . '/' . $item;
            
            if (is_dir($source_path)) {
                if (!$this->copy_directory($source_path, $dest_path)) {
                    return false;
                }
            } else {
                // For PHP files, ensure proper encoding and remove BOM
                if (pathinfo($item, PATHINFO_EXTENSION) === 'php') {
                    if (!$this->copy_php_file_safely($source_path, $dest_path)) {
                        return false;
                    }
                } else {
                    if (!copy($source_path, $dest_path)) {
                        return false;
                    }
                }
            }
        }
        
        return true;
    }
    
    /**
     * Clean and validate PHP file
     */
    private function clean_and_validate_php_file(string $file_path) {
        // Read file in binary mode
        $handle = @fopen($file_path, 'rb');
        if ($handle === false) {
            return __('Could not open file for reading.', 'orders-jet');
        }
        
        $content = '';
        while (!feof($handle)) {
            $chunk = fread($handle, 8192);
            if ($chunk === false) {
                break;
            }
            $content .= $chunk;
        }
        fclose($handle);
        
        if (empty($content)) {
            return __('File is empty.', 'orders-jet');
        }
        
        // Remove BOM variants
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        if (substr($content, 0, 2) === "\xFF\xFE" || substr($content, 0, 2) === "\xFE\xFF") {
            $content = substr($content, 2);
        }
        
        // Remove null bytes and control characters (except newlines and tabs)
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
        
        // Normalize line endings
        $content = str_replace(array("\r\n", "\r"), "\n", $content);
        
        // Ensure file starts with <?php
        $trimmed = ltrim($content);
        if (strpos($trimmed, '<?php') !== 0 && strpos($trimmed, '<?') !== 0) {
            $content = "<?php\n" . $content;
        }
        
        // Ensure file ends with newline
        $content = rtrim($content) . "\n";
        
        // Write cleaned content back
        $result = @file_put_contents($file_path, $content, LOCK_EX);
        if ($result === false) {
            return __('Could not write cleaned file.', 'orders-jet');
        }
        
        @chmod($file_path, 0644);
        
        // Validate syntax
        return $this->validate_php_syntax($file_path);
    }
    
    /**
     * Copy PHP file safely, removing BOM and ensuring proper encoding
     */
    private function copy_php_file_safely(string $source, string $destination): bool {
        // Read source file in binary mode first
        $handle = @fopen($source, 'rb');
        if ($handle === false) {
            // Fallback to file_get_contents
            $content = @file_get_contents($source);
            if ($content === false) {
                return false;
            }
        } else {
            $content = '';
            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false) {
                    break;
                }
                $content .= $chunk;
            }
            fclose($handle);
        }
        
        if (empty($content)) {
            return false;
        }
        
        // Remove UTF-8 BOM if present (EF BB BF)
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        
        // Remove UTF-16 BOM variants
        if (substr($content, 0, 2) === "\xFF\xFE" || substr($content, 0, 2) === "\xFE\xFF") {
            $content = substr($content, 2);
        }
        
        // Remove null bytes and control characters (except newlines and tabs)
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
        
        // Normalize line endings to Unix style (LF)
        $content = str_replace(array("\r\n", "\r"), "\n", $content);
        
        // Ensure file starts with <?php or <? (trim whitespace first)
        $trimmed = ltrim($content);
        if (strpos($trimmed, '<?php') !== 0 && strpos($trimmed, '<?') !== 0) {
            // File doesn't start with PHP tag - add it
            $content = "<?php\n" . $content;
        }
        
        // Ensure file ends with newline
        $content = rtrim($content) . "\n";
        
        // Write to destination with proper encoding (UTF-8 without BOM)
        // Use binary write mode
        $dest_handle = @fopen($destination, 'wb');
        if ($dest_handle === false) {
            // Fallback to file_put_contents
            $result = @file_put_contents($destination, $content, LOCK_EX);
        } else {
            $result = fwrite($dest_handle, $content);
            fclose($dest_handle);
            $result = ($result !== false);
        }
        
        // Set proper file permissions
        if ($result !== false) {
            @chmod($destination, 0644);
        }
        
        return $result !== false;
    }
    
    /**
     * Delete directory recursively
     */
    private function delete_directory(string $dir): bool {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $file_path = $dir . '/' . $file;
            if (is_dir($file_path)) {
                $this->delete_directory($file_path);
            } else {
                unlink($file_path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Static flag to prevent multiple loads
     */
    private static $addons_loaded = false;
    
    /**
     * Load active addons
     */
    public function load_active_addons(): void {
        // Prevent loading multiple times
        if (self::$addons_loaded) {
            return;
        }
        
        $statuses = get_option(self::OPTION_NAME, array());
        if (empty($statuses)) {
            self::$addons_loaded = true;
            return;
        }
        
        $addons_dir = self::get_addons_dir();
        
        if (!file_exists($addons_dir)) {
            self::$addons_loaded = true;
            return;
        }
        
        foreach ($statuses as $slug => $status) {
            if ($status === 'active') {
                $addon_file = $addons_dir . $slug . '/addon.php';
                if (file_exists($addon_file)) {
                    try {
                        // Use require_once to prevent multiple includes
                        require_once $addon_file;
                    } catch (Exception $e) {
                        // Log error and deactivate problematic addon
                        error_log('Orders Jet Addon Error (' . $slug . '): ' . $e->getMessage());
                        $this->set_addon_status($slug, 'inactive');
                        
                        // Show admin notice
                        add_action('admin_notices', function() use ($slug, $e) {
                            echo '<div class="notice notice-error"><p>';
                            echo sprintf(
                                __('Addon "%s" has been deactivated due to an error: %s', 'orders-jet'),
                                esc_html($slug),
                                esc_html($e->getMessage())
                            );
                            echo '</p></div>';
                        });
                    }
                }
            }
        }
        
        // Mark as loaded
        self::$addons_loaded = true;
    }
    
    /**
     * Force reload addons (useful after activation)
     */
    public function reload_addons(): void {
        self::$addons_loaded = false;
        $this->load_active_addons();
    }
    
    /**
     * Validate PHP syntax of a file
     */
    private function validate_php_syntax(string $file_path) {
        if (!file_exists($file_path)) {
            return __('File does not exist.', 'orders-jet');
        }
        
        // Read file content
        $content = file_get_contents($file_path);
        if ($content === false) {
            return __('Could not read file.', 'orders-jet');
        }
        
        // Check for PHP opening tag
        if (strpos(trim($content), '<?php') !== 0 && strpos(trim($content), '<?') !== 0) {
            return __('File does not start with PHP opening tag.', 'orders-jet');
        }
        
        // Use php -l to check syntax (if available)
        if (function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
            $php_binary = defined('PHP_BINARY') ? PHP_BINARY : 'php';
            $command = escapeshellarg($php_binary) . ' -l ' . escapeshellarg($file_path) . ' 2>&1';
            $output = shell_exec($command);
            
            if ($output && strpos($output, 'No syntax errors') === false) {
                // Extract error message
                preg_match('/Parse error: (.+?) in/', $output, $matches);
                if (!empty($matches[1])) {
                    return $matches[1];
                }
                return __('Syntax error detected.', 'orders-jet');
            }
        }
        
        // Fallback: Try to tokenize the file
        if (function_exists('token_get_all')) {
            $tokens = @token_get_all($content);
            if ($tokens === false) {
                return __('Could not parse PHP tokens.', 'orders-jet');
            }
            
            // Basic validation - check for common syntax issues
            $open_tags = 0;
            foreach ($tokens as $token) {
                if (is_array($token)) {
                    if ($token[0] === T_OPEN_TAG || $token[0] === T_OPEN_TAG_WITH_ECHO) {
                        $open_tags++;
                    }
                }
            }
        }
        
        return true; // Syntax appears valid
    }
    
    /**
     * AJAX: Toggle addon status
     */
    public function ajax_toggle_status(): void {
        check_ajax_referer('oj_addons_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'orders-jet')));
            return;
        }
        
        $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
        
        if (empty($slug)) {
            wp_send_json_error(array('message' => __('Invalid addon slug.', 'orders-jet')));
            return;
        }
        
        try {
            $this->toggle_addon_status($slug);
            $new_status = $this->get_addon_status($slug);
            
            // If activated, reload addons immediately
            if ($new_status === 'active') {
                // Clear the loaded flag to force reload
                self::$addons_loaded = false;
                
                // Try to load the addon
                $addons_dir = self::get_addons_dir();
                $addon_file = $addons_dir . $slug . '/addon.php';
                
                if (file_exists($addon_file)) {
                    // Clean and validate the file first
                    $clean_result = $this->clean_and_validate_php_file($addon_file);
                    if ($clean_result !== true) {
                        $this->set_addon_status($slug, 'inactive');
                        wp_send_json_error(array(
                            'message' => sprintf(
                                __('Failed to activate addon. Error: %s', 'orders-jet'),
                                $clean_result
                            )
                        ));
                        return;
                    }
                    
                    // Capture any output or errors
                    ob_start();
                    $load_error = null;
                    $error_type = null;
                    
                    // Set error handler to catch parse errors
                    set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$load_error, &$error_type) {
                        if ($errno === E_PARSE || $errno === E_COMPILE_ERROR || $errno === E_ERROR) {
                            $load_error = $errstr . ' on line ' . $errline;
                            $error_type = 'parse';
                            return true; // Suppress error
                        }
                        return false; // Let other errors through
                    }, E_ALL | E_STRICT);
                    
                    try {
                        require_once $addon_file;
                    } catch (ParseError $e) {
                        $load_error = $e->getMessage() . ' on line ' . $e->getLine();
                        $error_type = 'parse';
                    } catch (Exception $e) {
                        $load_error = $e->getMessage();
                        $error_type = 'exception';
                    } catch (Error $e) {
                        $load_error = $e->getMessage() . ' on line ' . $e->getLine();
                        $error_type = 'error';
                    } finally {
                        restore_error_handler();
                    }
                    
                    $output = ob_get_clean();
                    
                    // If there was an error, deactivate and return error
                    if ($load_error !== null) {
                        $this->set_addon_status($slug, 'inactive');
                        wp_send_json_error(array(
                            'message' => sprintf(
                                __('Failed to activate addon. Error: %s', 'orders-jet'),
                                esc_html($load_error)
                            )
                        ));
                        return;
                    }
                    
                    // If there was unexpected output, show warning but continue
                    if (!empty($output)) {
                        error_log('Orders Jet Addon (' . $slug . ') produced output: ' . $output);
                    }
                }
                
                // Mark as loaded to prevent double-loading
                self::$addons_loaded = true;
            }
            
            wp_send_json_success(array(
                'status' => $new_status,
                'message' => sprintf(
                    __('Addon %s successfully.', 'orders-jet'), 
                    $new_status === 'active' ? __('activated', 'orders-jet') : __('deactivated', 'orders-jet')
                )
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => sprintf(__('Error: %s', 'orders-jet'), $e->getMessage())
            ));
        }
    }
    
    /**
     * AJAX: Delete addon
     */
    public function ajax_delete_addon(): void {
        check_ajax_referer('oj_addons_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'orders-jet')));
        }
        
        $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
        
        if (empty($slug)) {
            wp_send_json_error(array('message' => __('Invalid addon slug.', 'orders-jet')));
        }
        
        if ($this->delete_addon($slug)) {
            wp_send_json_success(array('message' => __('Addon deleted successfully.', 'orders-jet')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete addon.', 'orders-jet')));
        }
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page(): void {
        $addons = $this->get_all_addons();
        ?>
        <div class="wrap oj-addons-wrapper">
            <h1 class="oj-addons-title">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php _e('Internal Addons Manager', 'orders-jet'); ?>
            </h1>
            
            <!-- Addons Directory Info -->
            <div class="oj-addons-info-card" style="background: #f0f6fc; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 4px solid #2271b1;">
                <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-info"></span>
                    <?php _e('معلومات مجلد الإضافات', 'orders-jet'); ?>
                </h3>
                <p style="margin: 10px 0;">
                    <strong><?php _e('مسار المجلد:', 'orders-jet'); ?></strong><br>
                    <code style="background: #fff; padding: 8px 12px; border-radius: 4px; display: inline-block; margin-top: 5px;">
                        <?php echo esc_html(self::get_addons_dir()); ?>
                    </code>
                </p>
                <p style="margin: 10px 0;">
                    <strong><?php _e('رابط المجلد:', 'orders-jet'); ?></strong><br>
                    <code style="background: #fff; padding: 8px 12px; border-radius: 4px; display: inline-block; margin-top: 5px;">
                        <?php echo esc_html(self::get_addons_url()); ?>
                    </code>
                </p>
            </div>
            
            <div class="oj-addons-container">
                <!-- Upload Section -->
                <div class="oj-addons-upload-card">
                    <h2 class="oj-card-title">
                        <span class="dashicons dashicons-upload"></span>
                        <?php _e('Upload New Addon', 'orders-jet'); ?>
                    </h2>
                    <form method="post" enctype="multipart/form-data" class="oj-upload-form">
                        <?php wp_nonce_field('oj_upload_addon'); ?>
                        <div class="oj-upload-field">
                            <label for="addon_file" class="oj-upload-label">
                                <span class="dashicons dashicons-media-archive"></span>
                                <?php _e('Select ZIP File', 'orders-jet'); ?>
                            </label>
                            <input type="file" name="addon_file" id="addon_file" accept=".zip" required>
                            <p class="description">
                                <?php _e('Upload a ZIP file containing your addon. The ZIP should contain a folder with addon.php file.', 'orders-jet'); ?>
                            </p>
                        </div>
                        <button type="submit" name="oj_upload_addon" class="button button-primary button-large oj-upload-button">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('Upload & Install', 'orders-jet'); ?>
                        </button>
                    </form>
                </div>
                
                <!-- Addons List -->
                <div class="oj-addons-list-section">
                    <h2 class="oj-section-title">
                        <span class="dashicons dashicons-admin-plugins"></span>
                        <?php _e('Installed Addons', 'orders-jet'); ?>
                        <span class="oj-addons-count">(<?php echo count($addons); ?>)</span>
                    </h2>
                    
                    <?php if (empty($addons)): ?>
                        <div class="oj-empty-state">
                            <span class="dashicons dashicons-admin-plugins"></span>
                            <p><?php _e('No addons installed yet. Upload your first addon above!', 'orders-jet'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="oj-addons-grid">
                            <?php foreach ($addons as $addon): ?>
                                <div class="oj-addon-card" data-slug="<?php echo esc_attr($addon['slug']); ?>">
                                    <div class="oj-addon-header">
                                        <div class="oj-addon-info">
                                            <h3 class="oj-addon-name"><?php echo esc_html($addon['name']); ?></h3>
                                            <span class="oj-addon-version">v<?php echo esc_html($addon['version']); ?></span>
                                        </div>
                                        <div class="oj-addon-status-badge <?php echo esc_attr($addon['status']); ?>">
                                            <?php echo $addon['status'] === 'active' ? __('Active', 'orders-jet') : __('Inactive', 'orders-jet'); ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($addon['description'])): ?>
                                        <p class="oj-addon-description"><?php echo esc_html($addon['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($addon['author'])): ?>
                                        <p class="oj-addon-author">
                                            <span class="dashicons dashicons-admin-users"></span>
                                            <?php echo esc_html($addon['author']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div class="oj-addon-actions">
                                        <button 
                                            type="button" 
                                            class="button oj-toggle-btn <?php echo $addon['status'] === 'active' ? 'button-secondary' : 'button-primary'; ?>"
                                            data-slug="<?php echo esc_attr($addon['slug']); ?>"
                                            data-action="toggle"
                                        >
                                            <span class="dashicons <?php echo $addon['status'] === 'active' ? 'dashicons-dismiss' : 'dashicons-yes-alt'; ?>"></span>
                                            <?php echo $addon['status'] === 'active' ? __('Deactivate', 'orders-jet') : __('Activate', 'orders-jet'); ?>
                                        </button>
                                        
                                        <button 
                                            type="button" 
                                            class="button button-link-delete oj-delete-btn"
                                            data-slug="<?php echo esc_attr($addon['slug']); ?>"
                                            data-action="delete"
                                        >
                                            <span class="dashicons dashicons-trash"></span>
                                            <?php _e('Delete', 'orders-jet'); ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
