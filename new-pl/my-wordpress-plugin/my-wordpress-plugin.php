<?php
/**
 * Plugin Name: My WordPress Plugin
 * Description: A simple plugin that adds a welcome message to the admin page.
 * Version: 1.0
 * Author: Your Name
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include the admin page functionality
require_once plugin_dir_path( __FILE__ ) . 'includes/admin-page.php';

// Hook to add the admin menu
add_action( 'admin_menu', 'my_plugin_add_admin_menu' );

// Initialize the plugin
function my_plugin_init() {
    // Enqueue admin styles
    wp_enqueue_style( 'my-plugin-admin-styles', plugin_dir_url( __FILE__ ) . 'assets/css/admin-styles.css' );
}
add_action( 'admin_enqueue_scripts', 'my_plugin_init' );
?>