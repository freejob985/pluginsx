<?php
declare(strict_types=1);
/**
 * Orders Jet - Centralized Logging System
 * Replaces scattered error_log calls with controlled, rate-limited logging
 * 
 * Performance Optimization: Solution 2 - Remove Production Logging
 */

if (!defined('ABSPATH')) {
    exit;
}

class Orders_Jet_Logger {
    
    private static $instance = null;
    private static $log_cache = array();
    private static $max_cache_size = 100;
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Debug logging with rate limiting and context
     * Only logs in development environment
     */
    public static function debug($message, $context = '', $data = null) {
        // Only log if we're in debug mode
        if (!self::should_log()) {
            return;
        }
        
        // Rate limiting to prevent log flooding
        $log_key = 'oj_log_' . md5($message . $context);
        if (self::is_rate_limited($log_key)) {
            return;
        }
        
        // Format message with context
        $formatted_message = 'Orders Jet';
        if (!empty($context)) {
            $formatted_message .= " [{$context}]";
        }
        $formatted_message .= ': ' . $message;
        
        // Add data if provided
        if ($data !== null) {
            $formatted_message .= ' | Data: ' . wp_json_encode($data);
        }
        
        // Log the message
        error_log($formatted_message);
        
        // Set rate limit
        self::set_rate_limit($log_key);
    }
    
    /**
     * Error logging (always logs, even in production)
     */
    public static function error($message, $context = '', $data = null) {
        $formatted_message = 'Orders Jet ERROR';
        if (!empty($context)) {
            $formatted_message .= " [{$context}]";
        }
        $formatted_message .= ': ' . $message;
        
        if ($data !== null) {
            $formatted_message .= ' | Data: ' . wp_json_encode($data);
        }
        
        error_log($formatted_message);
    }
    
    /**
     * Performance logging for optimization tracking
     */
    public static function performance($message, $execution_time_ms, $context = '') {
        if (!self::should_log()) {
            return;
        }
        
        $formatted_message = 'Orders Jet PERF';
        if (!empty($context)) {
            $formatted_message .= " [{$context}]";
        }
        $formatted_message .= ": {$message} | Time: {$execution_time_ms}ms";
        
        error_log($formatted_message);
    }
    
    /**
     * Check if we should log based on environment
     */
    private static function should_log() {
        return (
            defined('WP_DEBUG') && WP_DEBUG && 
            defined('WP_DEBUG_LOG') && WP_DEBUG_LOG
        );
    }
    
    /**
     * Check if message is rate limited
     */
    private static function is_rate_limited($log_key) {
        return get_transient($log_key) !== false;
    }
    
    /**
     * Set rate limit for message (60 seconds)
     */
    private static function set_rate_limit($log_key) {
        set_transient($log_key, true, 60);
    }
    
    /**
     * Bulk logging for performance-critical operations
     */
    public static function bulk_debug($messages, $context = '') {
        if (!self::should_log()) {
            return;
        }
        
        $bulk_message = 'Orders Jet BULK';
        if (!empty($context)) {
            $bulk_message .= " [{$context}]";
        }
        $bulk_message .= ': ' . implode(' | ', $messages);
        
        error_log($bulk_message);
    }
    
    /**
     * Clean up old cache entries
     */
    public static function cleanup_cache() {
        if (count(self::$log_cache) > self::$max_cache_size) {
            self::$log_cache = array_slice(self::$log_cache, -50, null, true);
        }
    }
}

/**
 * Global helper functions for easy access
 */
function oj_debug_log($message, $context = '', $data = null) {
    Orders_Jet_Logger::debug($message, $context, $data);
}

function oj_error_log($message, $context = '', $data = null) {
    Orders_Jet_Logger::error($message, $context, $data);
}

function oj_perf_log($message, $execution_time_ms, $context = '') {
    Orders_Jet_Logger::performance($message, $execution_time_ms, $context);
}
