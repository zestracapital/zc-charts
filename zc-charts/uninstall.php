<?php
/**
 * ZC Charts Uninstall
 *
 * Uninstalling ZC Charts deletes plugin options.
 *
 * @package ZC_Charts
 * @since 1.0.0
 */

// If uninstall.php is not called by WordPress, die.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('zc_charts_api_key');
delete_option('zc_charts_default_library');
delete_option('zc_charts_default_height');
delete_option('zc_charts_enable_fallback');
delete_option('zc_charts_enable_controls');
delete_option('zc_charts_version');

// Delete any transients set by the plugin
delete_transient('zc_dmt_new_api_key'); // Although this is from DMT, just in case it was used here

// Note: This plugin does not create custom database tables or custom post types,
// so there is no need to delete them here.

// If you added any other options or transients in the future, delete them here.
