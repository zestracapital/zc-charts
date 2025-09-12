<?php
/**
 * ZC Charts Error Template
 *
 * This template is used for rendering error messages when charts fail to load.
 * It is called by the ZC_Charts_Charts class when an error occurs.
 *
 * @package ZC_Charts
 * @since 1.0.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// This template expects the following variables to be defined:
// $message (string) - The error message to display
// $type (string) - The type of error (e.g., 'api_key_missing', 'invalid_api_key', 'data_unavailable')

// Sanitize inputs
$message = isset($message) ? esc_html($message) : esc_html__('An unknown error occurred.', 'zc-charts');
$type = isset($type) ? sanitize_key($type) : 'generic';

// Determine icon based on error type
$icon = '⚠️'; // Default warning icon
if ($type === 'invalid_api_key' || $type === 'api_key_missing') {
    $icon = '🔐'; // Lock icon for auth errors
} elseif ($type === 'data_unavailable') {
    $icon = '📊'; // Chart icon for data errors
}

?>
<div class="zc-chart-wrapper zc-chart-error zc-chart-error-<?php echo esc_attr($type); ?>">
    <div class="zc-chart-error-content">
        <div class="error-icon"><?php echo esc_html($icon); ?></div>
        <div class="error-message"><?php echo $message; ?></div>
        
        <?php if ($type === 'invalid_api_key' || $type === 'api_key_missing') : ?>
            <div class="error-details"><?php esc_html_e('Please check your API key configuration in the WordPress admin settings.', 'zc-charts'); ?></div>
        <?php elseif ($type === 'data_unavailable') : ?>
            <div class="error-details"><?php esc_html_e('Please try again later or contact the site administrator.', 'zc-charts'); ?></div>
        <?php else: ?>
            <div class="error-details"><?php esc_html_e('Please try again later.', 'zc-charts'); ?></div>
        <?php endif; ?>
    </div>
</div>
