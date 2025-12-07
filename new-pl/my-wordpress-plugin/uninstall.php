<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove options or settings added by the plugin
delete_option('my_plugin_option_name');
delete_site_option('my_plugin_site_option_name');
?>