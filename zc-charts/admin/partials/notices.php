<?php
/**
 * ZC Charts Admin Notices Partial
 *
 * This file handles the display of admin notices for the ZC Charts plugin.
 *
 * @package ZC_Charts
 * @since 1.0.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// This file is intentionally left minimal as notices are primarily handled
// by WordPress's settings API and direct echo in the settings page.
// However, we can include any custom notice logic here if needed.

/**
 * Display a custom admin notice.
 *
 * @param string $message The notice message.
 * @param string $type The type of notice (success, error, warning, info).
 * @param bool $dismissible Whether the notice is dismissible.
 */
function zc_charts_display_admin_notice($message, $type = 'info', $dismissible = true) {
    $class = 'notice notice-' . $type;
    if ($dismissible) {
        $class .= ' is-dismissible';
    }
    
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}

// Example usage (commented out as it would normally be called conditionally):
// zc_charts_display_admin_notice(__('This is a sample notice.', 'zc-charts'), 'info');
