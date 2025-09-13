<?php
/**
 * ZC Charts Admin Notices Partial
 * Common notices for admin pages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get all admin notices
$notices = array();

// Check for connection status
if (class_exists('ZC_Charts_API_Client')) {
    $api_client = new ZC_Charts_API_Client();
    $api_key = $api_client->get_api_key();
    
    if (!empty($api_key)) {
        $validation_result = $api_client->validate_api_key($api_key);
        
        if (is_wp_error($validation_result)) {
            $notices[] = array(
                'type' => 'error',
                'message' => $validation_result->get_error_message()
            );
        } elseif (!$validation_result) {
            $notices[] = array(
                'type' => 'error',
                'message' => __('API key is invalid or cannot connect to ZC DMT.', 'zc-charts')
            );
        } else {
            // Check if DMT plugin version is compatible
            if (defined('ZC_DMT_VERSION') && version_compare(ZC_DMT_VERSION, '2.0.0', '<')) {
                $notices[] = array(
                    'type' => 'warning',
                    'message' => sprintf(__('ZC DMT plugin version %s detected. ZC Charts recommends version 2.0.0 or higher for full compatibility.', 'zc-charts'), ZC_DMT_VERSION)
                );
            }
        }
    } else {
        $notices[] = array(
            'type' => 'warning',
            'message' => __('API key not configured. Please set it in Settings > ZC Charts.', 'zc-charts')
        );
    }
}

// Check for chart library issues
$default_library = get_option('zc_charts_default_library', 'chartjs');

if ($default_library === 'highcharts') {
    // Check if user has acknowledged Highcharts license requirement
    $license_acknowledged = get_option('zc_charts_highcharts_license_acknowledged', false);
    
    if (!$license_acknowledged) {
        $notices[] = array(
            'type' => 'info',
            'message' => sprintf(
                __('You have selected Highcharts as your default library. Please note that Highcharts requires a commercial license for production use. %sAcknowledge%s', 'zc-charts'),
                '<a href="#" id="zc-charts-acknowledge-license">',
                '</a>'
            )
        );
    }
}

// Display notices
foreach ($notices as $notice) {
    ?>
    <div class="notice notice-<?php echo esc_attr($notice['type']); ?> zc-charts-notice">
        <p><?php echo esc_html($notice['message']); ?></p>
    </div>
    <?php
}

// Display WordPress admin notices
settings_errors('zc_charts_notices');
?>