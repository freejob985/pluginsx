<?php
// Function to display the welcome message
function my_plugin_welcome_message() {
    echo '<div class="wrap">';
    echo '<h1>Welcome to My WordPress Plugin!</h1>';
    echo '<p>Thank you for installing our plugin. We hope you find it useful!</p>';
    echo '</div>';
}

// Function to add the admin menu item
function my_plugin_add_admin_menu() {
    add_menu_page(
        'Welcome', // Page title
        'My Plugin', // Menu title
        'manage_options', // Capability
        'my-plugin-welcome', // Menu slug
        'my_plugin_welcome_message' // Function to display the page content
    );
}

// Hook to add the admin menu
add_action('admin_menu', 'my_plugin_add_admin_menu');
?>