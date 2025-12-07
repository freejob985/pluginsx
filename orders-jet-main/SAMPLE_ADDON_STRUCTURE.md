# Internal Addons - Sample Structure

This document explains how to create an internal addon for Orders Jet.

## Addon Structure

Each addon should be packaged as a ZIP file with the following structure:

```
my-addon-slug/
├── addon.php (required - main addon file)
└── (other files as needed)
```

## addon.php Header Format

The `addon.php` file must start with a header comment containing metadata:

```php
<?php
/**
 * Plugin Name: My Addon Name
 * Description: Short description for this internal addon.
 * Version: 1.0.0
 * Addon Slug: my-addon-slug
 * Author: Your Name
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Your addon code here
// This file will be loaded when the addon is activated

// Example: Add a custom action
add_action('init', function() {
    // Your code here
});
```

## Required Header Fields

- **Plugin Name**: (Required) The display name of your addon
- **Description**: (Optional) Short description
- **Version**: (Optional) Version number
- **Addon Slug**: (Optional) If not provided, will be generated from Plugin Name
- **Author**: (Optional) Author name

## Installation Steps

1. Create your addon folder with `addon.php` inside
2. Zip the folder (the folder itself, not its contents)
3. Go to **Orders > Internal Addons** in WordPress admin
4. Upload the ZIP file
5. Activate the addon

## Example Addon

Here's a complete example addon that adds a custom admin notice:

```php
<?php
/**
 * Plugin Name: Sample Addon
 * Description: This is a sample addon that demonstrates the structure.
 * Version: 1.0.0
 * Addon Slug: sample-addon
 * Author: Orders Jet Team
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin notice
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        echo '<div class="notice notice-info"><p>Sample Addon is active!</p></div>';
    }
});

// Add custom functionality
add_action('wp_footer', function() {
    echo '<!-- Sample Addon Loaded -->';
});
```

## Notes

- Addons are stored in: `wp-content/uploads/orders-jet-addons/`
- Only active addons are loaded
- Addons are completely isolated from WordPress plugin system
- They only affect the Orders Jet plugin functionality
- Use WordPress hooks and filters as normal
