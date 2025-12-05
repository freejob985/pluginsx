<?php
declare(strict_types=1);
/**
 * WooJet Meta Keys Constants
 * 
 * Centralized constants for ALL meta keys used in Orders Jet / WooJet platform
 * 
 * USAGE:
 * Instead of: $order->get_meta('_oj_table_number')
 * Use: $order->get_meta(WooJet_Meta_Keys::TABLE_NUMBER)
 * 
 * Benefits:
 * - No typos
 * - IDE autocomplete
 * - Easy refactoring
 * - Self-documenting code
 * 
 * @package WooJet
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WooJet_Meta_Keys {
    
    // ========================================================================
    // ORDER META KEYS
    // ========================================================================
    
    /**
     * Orders Jet location (future: branch/store)
     * @var string
     */
    const LOCATION = '_oj_location';
    
    /**
     * Table number for dine-in orders
     * @var string
     */
    const TABLE_NUMBER = '_oj_table_number';
    
    /**
     * Order type (will be replaced by Order Source system)
     * @var string
     */
    const ORDER_TYPE = '_oj_order_type';
    
    /**
     * Internal order status (distinct from WooCommerce status)
     * @var string
     */
    const ORDER_STATUS = '_oj_order_status';
    
    /**
     * Assigned waiter ID
     * @var string
     */
    const ASSIGNED_WAITER = '_oj_assigned_waiter';
    
    /**
     * Session ID for combined orders (table sessions)
     * @var string
     */
    const SESSION_ID = '_oj_session_id';
    
    /**
     * Session start time
     * @var string
     */
    const SESSION_START = '_oj_session_start';
    
    /**
     * Array of order IDs in session
     * @var string
     */
    const SESSION_ORDERS = '_oj_session_orders';
    
    /**
     * Session total amount
     * @var string
     */
    const SESSION_TOTAL = '_oj_session_total';
    
    /**
     * Order received timestamp
     * @var string
     */
    const RECEIVED_TIME = '_oj_received_time';
    
    /**
     * Order preparing started timestamp
     * @var string
     */
    const PREPARING_TIME = '_oj_preparing_time';
    
    /**
     * Delivery address for delivery orders
     * @var string
     */
    const DELIVERY_ADDRESS = '_oj_delivery_address';
    
    /**
     * Kitchen type (food, beverages, mixed)
     * @var string
     */
    const KITCHEN_TYPE = '_oj_kitchen_type';
    
    /**
     * Food kitchen ready status
     * @var string
     */
    const FOOD_KITCHEN_READY = '_oj_food_kitchen_ready';
    
    /**
     * Beverage kitchen ready status
     * @var string
     */
    const BEVERAGE_KITCHEN_READY = '_oj_beverage_kitchen_ready';
    
    /**
     * Payment method
     * @var string
     */
    const PAYMENT_METHOD = '_oj_payment_method';
    
    /**
     * Payment confirmed flag
     * @var string
     */
    const PAYMENT_CONFIRMED = '_oj_payment_confirmed';
    
    /**
     * Payment confirmed date
     * @var string
     */
    const PAYMENT_CONFIRMED_DATE = '_oj_payment_confirmed_date';
    
    /**
     * Tax calculation method
     * @var string
     */
    const TAX_METHOD = '_oj_tax_method';
    
    // ========================================================================
    // ORDER ITEM META KEYS
    // ========================================================================
    
    /**
     * Item add-ons (structured data)
     * @var string
     */
    const ADDONS_DATA = '_oj_addons_data';
    
    /**
     * Item add-ons (string format - legacy)
     * @var string
     */
    const ITEM_ADDONS = '_oj_item_addons';
    
    /**
     * Item special notes/instructions
     * @var string
     */
    const ITEM_NOTES = '_oj_item_notes';
    
    /**
     * Item base price (before add-ons)
     * @var string
     */
    const BASE_PRICE = '_oj_base_price';
    
    // ========================================================================
    // TABLE POST TYPE META KEYS
    // ========================================================================
    
    /**
     * Table number/name (for oj_table post type)
     * @var string
     */
    const TABLE_POST_NUMBER = '_oj_table_number';
    
    /**
     * Table capacity (number of seats)
     * @var string
     */
    const TABLE_CAPACITY = '_oj_table_capacity';
    
    /**
     * Table status (Available, Occupied, Reserved)
     * @var string
     */
    const TABLE_STATUS = '_oj_table_status';
    
    /**
     * Table location/zone
     * @var string
     */
    const TABLE_LOCATION = '_oj_table_location';
    
    /**
     * Guest invoice requested timestamp
     * @var string
     */
    const GUEST_INVOICE_REQUESTED = '_oj_guest_invoice_requested';
    
    /**
     * Invoice request status
     * @var string
     */
    const INVOICE_REQUEST_STATUS = '_oj_invoice_request_status';
    
    // ========================================================================
    // USER META KEYS
    // ========================================================================
    
    /**
     * User function/role (kitchen, waiter, manager)
     * @var string
     */
    const USER_FUNCTION = '_oj_function';
    
    /**
     * Kitchen specialization (food, beverages, both)
     * @var string
     */
    const KITCHEN_SPECIALIZATION = '_oj_kitchen_type';
    
    /**
     * Assigned tables for waiter
     * @var string
     */
    const ASSIGNED_TABLES = '_oj_assigned_tables';
    
    // ========================================================================
    // COUPON META KEYS
    // ========================================================================
    
    /**
     * Flag indicating coupon was generated by Orders Jet
     * @var string
     */
    const COUPON_GENERATED = '_oj_generated';
    
    /**
     * Order ID that generated this coupon
     * @var string
     */
    const COUPON_ORDER_ID = '_oj_order_id';
    
    // ========================================================================
    // WOOFOOD INTEGRATION KEYS (For backward compatibility)
    // ========================================================================
    
    /**
     * WooFood order type
     * @deprecated Will be replaced by Order Source system
     * @var string
     */
    const WOOFOOD_ORDER_TYPE = '_woo_food_order_type';
    
    /**
     * WooFood location
     * @deprecated Will be replaced by Branch/Location system
     * @var string
     */
    const WOOFOOD_LOCATION = '_woo_food_location';
    
    /**
     * External WooFood order method
     * @deprecated Will be replaced by Order Source system
     * @var string
     */
    const EXWF_ODMETHOD = 'exwf_odmethod';
    
    /**
     * External WooFood delivery address
     * @deprecated Use DELIVERY_ADDRESS instead
     * @var string
     */
    const EXWF_DELIVERY_ADDRESS = '_exwf_delivery_address';
    
    // ========================================================================
    // EXTERNAL/LEGACY KEYS (WooCommerce, Other Plugins)
    // ========================================================================
    
    /**
     * Generic table number field (legacy)
     * @deprecated Use TABLE_NUMBER instead
     * @var string
     */
    const LEGACY_TABLE_NUMBER = 'table_number';
    
    /**
     * WooCommerce Product Add-Ons notes field
     * @var string
     */
    const WC_PAO_ADDON_NOTES = '_wc_pao_addon_notes';
    
    // ========================================================================
    // UTILITY METHODS
    // ========================================================================
    
    /**
     * Get all Orders Jet meta keys
     * Useful for bulk queries, migrations, etc.
     * 
     * @return array Array of all OJ meta key constants
     */
    public static function get_all_oj_keys() {
        return array(
            self::LOCATION,
            self::TABLE_NUMBER,
            self::ORDER_TYPE,
            self::ORDER_STATUS,
            self::ASSIGNED_WAITER,
            self::SESSION_ID,
            self::SESSION_START,
            self::SESSION_ORDERS,
            self::SESSION_TOTAL,
            self::RECEIVED_TIME,
            self::PREPARING_TIME,
            self::DELIVERY_ADDRESS,
            self::KITCHEN_TYPE,
            self::FOOD_KITCHEN_READY,
            self::BEVERAGE_KITCHEN_READY,
            self::PAYMENT_METHOD,
            self::PAYMENT_CONFIRMED,
            self::PAYMENT_CONFIRMED_DATE,
            self::TAX_METHOD,
            self::ADDONS_DATA,
            self::ITEM_ADDONS,
            self::ITEM_NOTES,
            self::BASE_PRICE,
            self::TABLE_POST_NUMBER,
            self::TABLE_CAPACITY,
            self::TABLE_STATUS,
            self::TABLE_LOCATION,
            self::GUEST_INVOICE_REQUESTED,
            self::INVOICE_REQUEST_STATUS,
            self::USER_FUNCTION,
            self::KITCHEN_SPECIALIZATION,
            self::ASSIGNED_TABLES,
            self::COUPON_GENERATED,
            self::COUPON_ORDER_ID,
        );
    }
    
    /**
     * Get order-related meta keys
     * 
     * @return array Array of order meta key constants
     */
    public static function get_order_keys() {
        return array(
            self::LOCATION,
            self::TABLE_NUMBER,
            self::ORDER_TYPE,
            self::ORDER_STATUS,
            self::ASSIGNED_WAITER,
            self::SESSION_ID,
            self::SESSION_START,
            self::SESSION_ORDERS,
            self::SESSION_TOTAL,
            self::RECEIVED_TIME,
            self::PREPARING_TIME,
            self::DELIVERY_ADDRESS,
            self::KITCHEN_TYPE,
            self::FOOD_KITCHEN_READY,
            self::BEVERAGE_KITCHEN_READY,
            self::PAYMENT_METHOD,
            self::PAYMENT_CONFIRMED,
            self::PAYMENT_CONFIRMED_DATE,
            self::TAX_METHOD,
        );
    }
    
    /**
     * Get table-related meta keys
     * 
     * @return array Array of table meta key constants
     */
    public static function get_table_keys() {
        return array(
            self::TABLE_POST_NUMBER,
            self::TABLE_CAPACITY,
            self::TABLE_STATUS,
            self::TABLE_LOCATION,
            self::SESSION_START,
            self::SESSION_ORDERS,
            self::SESSION_TOTAL,
            self::ASSIGNED_WAITER,
            self::GUEST_INVOICE_REQUESTED,
            self::INVOICE_REQUEST_STATUS,
        );
    }
    
    /**
     * Get deprecated/legacy keys that should be migrated
     * 
     * @return array Array of deprecated meta key constants
     */
    public static function get_deprecated_keys() {
        return array(
            self::WOOFOOD_ORDER_TYPE,
            self::WOOFOOD_LOCATION,
            self::EXWF_ODMETHOD,
            self::EXWF_DELIVERY_ADDRESS,
            self::LEGACY_TABLE_NUMBER,
        );
    }
    
    /**
     * Check if a meta key is deprecated
     * 
     * @param string $key Meta key to check
     * @return bool True if deprecated
     */
    public static function is_deprecated($key) {
        return in_array($key, self::get_deprecated_keys(), true);
    }
    
    /**
     * Get human-readable label for a meta key
     * Useful for debugging, admin interfaces, etc.
     * 
     * @param string $key Meta key constant
     * @return string Human-readable label
     */
    public static function get_label($key) {
        $labels = array(
            self::LOCATION => 'Location',
            self::TABLE_NUMBER => 'Table Number',
            self::ORDER_TYPE => 'Order Type',
            self::ORDER_STATUS => 'Order Status',
            self::ASSIGNED_WAITER => 'Assigned Waiter',
            self::SESSION_ID => 'Session ID',
            self::DELIVERY_ADDRESS => 'Delivery Address',
            self::KITCHEN_TYPE => 'Kitchen Type',
            self::PAYMENT_METHOD => 'Payment Method',
            self::ADDONS_DATA => 'Add-ons',
            self::ITEM_NOTES => 'Item Notes',
            self::TABLE_CAPACITY => 'Table Capacity',
            self::TABLE_STATUS => 'Table Status',
            // Add more as needed
        );
        
        return $labels[$key] ?? $key;
    }
}

