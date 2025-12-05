<?php
declare(strict_types=1);
/**
 * Orders Jet - Menu Service Class
 * Handles product and category data management for QR menu
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Menu_Service {
    
    /**
     * Get categories with their products
     * OPTIMIZED: Fixed N+1 query problem by bulk-fetching product categories + caching
     * 
     * @param int|null $location_id WooFood location ID for filtering
     * @return array Categories with products
     */
    public function get_categories_with_products($location_id = null) {
        // PERFORMANCE: Add caching for expensive menu operations
        $cache_key = 'oj_menu_categories_products_' . ($location_id ?? 'all');
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            oj_debug_log('Serving menu categories from cache for location: ' . ($location_id ?? 'all'), 'MENU_SERVICE');
            return $cached_result;
        }
        
        $categories = $this->get_menu_categories();
        $products = $this->get_products_by_location($location_id);
        
        if (empty($products)) {
            return array();
        }
        
        // PERFORMANCE FIX: Bulk fetch all product categories in one query
        $product_ids = array_map(function($product) {
            return $product->get_id();
        }, $products);
        
        $all_product_categories = $this->get_products_categories_bulk($product_ids);
        
        // Group products by category
        $categorized_products = array();
        
        foreach ($categories as $category) {
            $category_products = array();
            
            foreach ($products as $product) {
                $product_id = $product->get_id();
                $product_category_slugs = $all_product_categories[$product_id] ?? array();
                
                if (in_array($category->slug, $product_category_slugs)) {
                    $category_products[] = $this->format_product_for_menu($product, $product_category_slugs);
                }
            }
            
            if (!empty($category_products)) {
                $categorized_products[] = array(
                    'category' => $this->format_category($category),
                    'products' => $category_products
                );
            }
        }
        
        // Cache for 5 minutes (menu data doesn't change frequently)
        set_transient($cache_key, $categorized_products, 300);
        
        return $categorized_products;
    }
    
    /**
     * Get detailed product information
     * 
     * @param int $product_id The product ID
     * @return array Detailed product data
     */
    public function get_product_details($product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            throw new Exception(__('Product not found', 'orders-jet'));
        }
        
        $product_data = $this->format_product_for_menu($product);
        
        // Add detailed information
        $product_data['description'] = $product->get_description();
        $product_data['short_description'] = $product->get_short_description();
        $product_data['variations'] = $this->get_product_variations($product);
        $product_data['addons'] = $this->get_product_addons($product_id);
        $product_data['gallery'] = $this->get_product_gallery($product);
        
        return $product_data;
    }
    
    /**
     * Get product add-ons from various sources
     * 
     * @param int $product_id The product ID
     * @return array Product add-ons
     */
    public function get_product_addons($product_id) {
        $addons = array();
        
        // TODO: Implement add-ons integration when needed
        // This is a placeholder for future WooFood/WC Product Add-ons integration
        
        // Apply filters for extensibility
        return apply_filters('oj_product_addons', $addons, $product_id);
    }
    
    /**
     * Filter products by location
     * OPTIMIZED: Fixed N+1 query problem by bulk-fetching product locations
     * 
     * @param array $products Products array
     * @param int|null $location_id WooFood location ID
     * @return array Filtered products
     */
    public function filter_products_by_location($products, $location_id = null) {
        if (!$location_id || empty($products)) {
            return $products;
        }
        
        // PERFORMANCE FIX: Bulk fetch all product locations in one query
        $product_ids = array_map(function($product) {
            return $product->get_id();
        }, $products);
        
        $all_product_locations = $this->get_products_locations_bulk($product_ids);
        
        $filtered_products = array();
        
        foreach ($products as $product) {
            $product_id = $product->get_id();
            $product_location_ids = $all_product_locations[$product_id] ?? array();
            
            if (empty($product_location_ids) || in_array($location_id, $product_location_ids)) {
                $filtered_products[] = $product;
            }
        }
        
        return $filtered_products;
    }
    
    /**
     * Get menu categories
     * 
     * @return array Menu categories
     */
    private function get_menu_categories() {
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => true,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        return is_wp_error($categories) ? array() : $categories;
    }
    
    /**
     * Get products by location
     * 
     * @param int|null $location_id WooFood location ID
     * @return array Products
     */
    private function get_products_by_location($location_id = null) {
        // OPTIMIZED: Reasonable limit for menu products
        $product_args = array(
            'limit' => 500, // Reasonable limit for menu service
            'status' => 'publish',
            'orderby' => 'menu_order',
            'order' => 'ASC'
        );
        
        // Add location filter if specified
        if ($location_id) {
            $product_args['tax_query'] = array(
                array(
                    'taxonomy' => 'exwoofood_loc',
                    'field'    => 'term_id',
                    'terms'    => $location_id,
                )
            );
        }
        
        return wc_get_products($product_args);
    }
    
    /**
     * Format category for menu display
     * 
     * @param WP_Term $category Category term
     * @return array Formatted category data
     */
    private function format_category($category) {
        return array(
            'id' => $category->term_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'count' => $category->count
        );
    }
    
    /**
     * Format product for menu display
     * OPTIMIZED: Accepts pre-fetched categories to avoid N+1 queries
     * 
     * @param WC_Product $product WooCommerce product
     * @param array|null $categories Pre-fetched product categories (optional)
     * @return array Formatted product data
     */
    private function format_product_for_menu($product, $categories = null) {
        // Use pre-fetched categories if provided, otherwise fetch individually (fallback)
        if ($categories === null) {
            $categories = wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'slugs'));
            if (is_wp_error($categories)) {
                $categories = array();
            }
        }
        
        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'slug' => $product->get_slug(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'formatted_price' => wc_price($product->get_price()),
            'image_url' => wp_get_attachment_image_url($product->get_image_id(), 'medium'),
            'image_alt' => get_post_meta($product->get_image_id(), '_wp_attachment_image_alt', true),
            'short_description' => $product->get_short_description(),
            'in_stock' => $product->is_in_stock(),
            'stock_status' => $product->get_stock_status(),
            'categories' => $categories
        );
    }
    
    /**
     * Get product variations
     * 
     * @param WC_Product $product WooCommerce product
     * @return array Product variations
     */
    private function get_product_variations($product) {
        if (!$product->is_type('variable')) {
            return array();
        }
        
        $variations = array();
        $available_variations = $product->get_available_variations();
        
        foreach ($available_variations as $variation_data) {
            $variation = wc_get_product($variation_data['variation_id']);
            if ($variation) {
                $variations[] = array(
                    'id' => $variation->get_id(),
                    'attributes' => $variation->get_variation_attributes(),
                    'price' => $variation->get_price(),
                    'formatted_price' => wc_price($variation->get_price()),
                    'in_stock' => $variation->is_in_stock(),
                    'image_url' => wp_get_attachment_image_url($variation->get_image_id(), 'medium')
                );
            }
        }
        
        return $variations;
    }
    
    /**
     * Get product gallery images
     * 
     * @param WC_Product $product WooCommerce product
     * @return array Gallery images
     */
    private function get_product_gallery($product) {
        $gallery_ids = $product->get_gallery_image_ids();
        $gallery = array();
        
        foreach ($gallery_ids as $image_id) {
            $gallery[] = array(
                'id' => $image_id,
                'url' => wp_get_attachment_image_url($image_id, 'large'),
                'thumbnail' => wp_get_attachment_image_url($image_id, 'thumbnail'),
                'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
            );
        }
        
        return $gallery;
    }
    
    /**
     * PERFORMANCE OPTIMIZATION: Bulk fetch product categories for multiple products
     * This replaces N+1 wp_get_post_terms() calls with a single optimized query
     * 
     * @param array $product_ids Array of product IDs
     * @return array Associative array: product_id => array of category slugs
     */
    public function get_products_categories_bulk($product_ids) {
        if (empty($product_ids)) {
            return array();
        }
        
        global $wpdb;
        
        // Sanitize product IDs
        $product_ids = array_map('intval', $product_ids);
        $product_ids_str = implode(',', $product_ids);
        
        // Single optimized query to get all product-category relationships
        $query = "
            SELECT p.ID as product_id, t.slug as category_slug
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE p.ID IN ({$product_ids_str})
            AND tt.taxonomy = 'product_cat'
            ORDER BY p.ID, t.name
        ";
        
        $results = $wpdb->get_results($query);
        
        // Group results by product ID
        $product_categories = array();
        
        // Initialize all product IDs with empty arrays
        foreach ($product_ids as $product_id) {
            $product_categories[$product_id] = array();
        }
        
        // Populate with actual category data
        foreach ($results as $row) {
            $product_categories[$row->product_id][] = $row->category_slug;
        }
        
        oj_debug_log('Bulk fetched categories for ' . count($product_ids) . ' products in single query', 'MENU_SERVICE');
        
        return $product_categories;
    }
    
    /**
     * PERFORMANCE OPTIMIZATION: Bulk fetch product locations for multiple products
     * This replaces N+1 wp_get_post_terms() calls with a single optimized query
     * 
     * @param array $product_ids Array of product IDs
     * @return array Associative array: product_id => array of location IDs
     */
    private function get_products_locations_bulk($product_ids) {
        if (empty($product_ids)) {
            return array();
        }
        
        global $wpdb;
        
        // Sanitize product IDs
        $product_ids = array_map('intval', $product_ids);
        $product_ids_str = implode(',', $product_ids);
        
        // Single optimized query to get all product-location relationships
        $query = "
            SELECT p.ID as product_id, t.term_id as location_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE p.ID IN ({$product_ids_str})
            AND tt.taxonomy = 'exwoofood_loc'
            ORDER BY p.ID, t.term_id
        ";
        
        $results = $wpdb->get_results($query);
        
        // Group results by product ID
        $product_locations = array();
        
        // Initialize all product IDs with empty arrays
        foreach ($product_ids as $product_id) {
            $product_locations[$product_id] = array();
        }
        
        // Populate with actual location data
        foreach ($results as $row) {
            $product_locations[$row->product_id][] = intval($row->location_id);
        }
        
        oj_debug_log('Bulk fetched locations for ' . count($product_ids) . ' products in single query', 'MENU_SERVICE');
        
        return $product_locations;
    }
    
    
    
    /**
     * Clear menu cache (call when products/categories are updated)
     * 
     * @param int|null $location_id Specific location ID or null for all locations
     */
    public function clear_menu_cache($location_id = null) {
        if ($location_id) {
            $cache_key = 'oj_menu_categories_products_' . $location_id;
            delete_transient($cache_key);
            oj_debug_log('Cleared menu cache for location: ' . $location_id, 'MENU_SERVICE');
        } else {
            // Clear all menu-related caches
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_oj_menu_categories_products_%' OR option_name LIKE '_transient_timeout_oj_menu_categories_products_%'");
            oj_debug_log('Cleared all menu caches', 'MENU_SERVICE');
        }
    }
}
