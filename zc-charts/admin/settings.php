<?php
// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Handle form submissions
$messages = array();
if (isset($_POST['zc_charts_save_settings']) && wp_verify_nonce($_POST['zc_charts_settings_nonce'], 'save_settings')) {
    // Sanitize and save settings
    $new_api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
    $default_library = isset($_POST['default_library']) && in_array($_POST['default_library'], array('chartjs', 'highcharts')) ? $_POST['default_library'] : 'chartjs';
    $default_height = isset($_POST['default_height']) ? sanitize_text_field($_POST['default_height']) : '300px';
    $enable_fallback = isset($_POST['enable_fallback']) ? 1 : 0;
    $enable_controls = isset($_POST['enable_controls']) ? 1 : 0;

    update_option('zc_charts_api_key', $new_api_key);
    update_option('zc_charts_default_library', $default_library);
    update_option('zc_charts_default_height', $default_height);
    update_option('zc_charts_enable_fallback', $enable_fallback);
    update_option('zc_charts_enable_controls', $enable_controls);

    $messages[] = array('type' => 'success', 'text' => __('Settings saved.', 'zc-charts'));
}

if (isset($_POST['zc_charts_test_connection']) && wp_verify_nonce($_POST['zc_charts_settings_nonce'], 'test_connection')) {
    $test_api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : get_option('zc_charts_api_key', '');

    if (empty($test_api_key)) {
        $messages[] = array('type' => 'error', 'text' => __('API key is required to test the connection.', 'zc-charts'));
    } else {
        $is_valid = ZC_Charts_API_Client::validate_api_key($test_api_key);

        if ($is_valid) {
            $messages[] = array('type' => 'success', 'text' => __('Connection test successful. API key is valid.', 'zc-charts'));
        } else {
            $messages[] = array('type' => 'error', 'text' => __('Connection test failed. API key is invalid or there was a problem connecting to the DMT plugin.', 'zc-charts'));
        }
    }
}

// Get current settings
$api_key = get_option('zc_charts_api_key', '');
$default_library = get_option('zc_charts_default_library', 'chartjs');
$default_height = get_option('zc_charts_default_height', '300px');
$enable_fallback = get_option('zc_charts_enable_fallback', 1);
$enable_controls = get_option('zc_charts_enable_controls', 1);

// Check DMT API accessibility
$is_dmt_accessible = ZC_Charts_Security::is_dmt_api_accessible();

?>
<div class="wrap zc-charts-wrap">
    <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Settings saved.', 'zc-charts'); ?></p>
        </div>
    <?php endif; ?>

    <?php foreach ($messages as $message): ?>
        <div class="notice notice-<?php echo esc_attr($message['type']); ?> is-dismissible">
            <p><?php echo esc_html($message['text']); ?></p>
        </div>
    <?php endforeach; ?>

    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields('zc_charts_settings_group'); ?>
        <?php do_settings_sections('zc-charts-settings'); ?>

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('DMT Plugin Status', 'zc-charts'); ?></th>
                <td>
                    <?php if ($is_dmt_accessible): ?>
                        <span class="zc-charts-status-ok"><?php esc_html_e('Connected', 'zc-charts'); ?></span>
                        <p class="description"><?php esc_html_e('The ZC DMT plugin is accessible and its REST API is responding.', 'zc-charts'); ?></p>
                    <?php else: ?>
                        <span class="zc-charts-status-error"><?php esc_html_e('Not Accessible', 'zc-charts'); ?></span>
                        <p class="description"><?php esc_html_e('The ZC DMT plugin REST API is not accessible. Please ensure the ZC DMT plugin is installed and activated.', 'zc-charts'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zc_charts_api_key"><?php esc_html_e('DMT API Key', 'zc-charts'); ?></label>
                </th>
                <td>
                    <input name="zc_charts_api_key" type="text" id="zc_charts_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e('Select an API key generated in the ZC DMT plugin settings. This key is required for the Charts plugin to access data.', 'zc-charts'); ?></p>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: 1: Link to DMT settings */
                            esc_html__('You can manage API keys in the %s.', 'zc-charts'),
                            '<a href="' . esc_url(admin_url('admin.php?page=zc-dmt-settings&tab=security')) . '">' . esc_html__('ZC DMT Settings', 'zc-charts') . '</a>'
                        );
                        ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Default Chart Library', 'zc-charts'); ?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php esc_html_e('Default Chart Library', 'zc-charts'); ?></span></legend>
                        <label>
                            <input type="radio" name="zc_charts_default_library" value="chartjs" <?php checked($default_library, 'chartjs'); ?> />
                            <span><?php esc_html_e('Chart.js', 'zc-charts'); ?></span>
                        </label>
                        <br/>
                        <label>
                            <input type="radio" name="zc_charts_default_library" value="highcharts" <?php checked($default_library, 'highcharts'); ?> />
                            <span><?php esc_html_e('Highcharts', 'zc-charts'); ?></span>
                        </label>
                    </fieldset>
                    <p class="description"><?php esc_html_e('Choose the default JavaScript library used to render charts.', 'zc-charts'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zc_charts_default_height"><?php esc_html_e('Default Chart Height', 'zc-charts'); ?></label>
                </th>
                <td>
                    <input name="zc_charts_default_height" type="text" id="zc_charts_default_height" value="<?php echo esc_attr($default_height); ?>" class="small-text" />
                    <p class="description"><?php esc_html_e('Set the default height for charts (e.g., 300px, 400px).', 'zc-charts'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Fallback Settings', 'zc-charts'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input name="zc_charts_enable_fallback" type="checkbox" id="zc_charts_enable_fallback" value="1" <?php checked($enable_fallback, 1); ?> />
                            <?php esc_html_e('Enable fallback to backup data', 'zc-charts'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('If live data is unavailable, attempt to load data from the DMT plugin\'s backup system.', 'zc-charts'); ?></p>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Dynamic Chart Settings', 'zc-charts'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input name="zc_charts_enable_controls" type="checkbox" id="zc_charts_enable_controls" value="1" <?php checked($enable_controls, 1); ?> />
                            <?php esc_html_e('Enable interactive controls by default', 'zc-charts'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Show timeframe selectors and chart type options on dynamic charts.', 'zc-charts'); ?></p>
                    </fieldset>
                </td>
            </tr>
        </table>

        <?php submit_button(__('Save Changes', 'zc-charts'), 'primary', 'zc_charts_save_settings'); ?>
    </form>

    <form method="post" action="" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ccc;">
        <?php wp_nonce_field('test_connection', 'zc_charts_settings_nonce'); ?>
        <input type="hidden" name="api_key" value="<?php echo esc_attr($api_key); ?>" />
        <h2><?php esc_html_e('Test Connection', 'zc-charts'); ?></h2>
        <p><?php esc_html_e('Verify that the Charts plugin can successfully communicate with the DMT plugin using the configured API key.', 'zc-charts'); ?></p>
        <?php submit_button(__('Test Connection', 'zc-charts'), 'secondary', 'zc_charts_test_connection'); ?>
    </form>
</div> <!-- .wrap -->
