<?php
/**
 * ZC Charts Uninstall Script
 * Handles cleanup of plugin data when the plugin is deleted
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check if the user has the required capability
if (!current_user_can('activate_plugins')) {
    exit;
}

// Check if this is the uninstall process
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if this is the correct plugin being uninstalled
if (WP_UNINSTALL_PLUGIN !== 'zc-charts/zc-charts.php') {
    exit;
}

// Delete plugin options
delete_option('zc_charts_api_key');
delete_option('zc_charts_default_library');

// Clear any transients that might have been set by the plugin
delete_transient('zc_charts_api_key_validation');
delete_transient('zc_charts_last_backup_check');

// Remove any custom capabilities that might have been added (though we're not adding any in this implementation)
// Remove any custom roles that might have been added (though we're not adding any in this implementation)

// Remove any scheduled events that might have been set (though we're not setting any in this implementation)
wp_clear_scheduled_hook('zc_charts_scheduled_backup');

// Remove any uploaded files or directories created by the plugin
// In this implementation, we're not uploading any files directly, so no cleanup needed

// Remove any database tables created by the plugin
// In this implementation, we're not creating any database tables, so no cleanup needed

// Remove any custom post types or taxonomies (though we're not adding any in this implementation)
// Flush rewrite rules to remove any custom endpoints
flush_rewrite_rules();

// Log the uninstallation event
if (class_exists('ZC_DMT_Error_Logger')) {
    $logger = new ZC_DMT_Error_Logger();
    $logger->log('info', 'ZC Charts plugin uninstalled', array(
        'timestamp' => current_time('mysql'),
        'user_id' => get_current_user_id()
    ));
}