<?php
/**
 * Settings admin page for ZC Charts plugin
 * Configuration interface for API keys and chart preferences
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['zc_charts_save_settings'])) {
    check_admin_referer('zc_charts_settings_nonce');
    
    $api_key = sanitize_text_field($_POST['api_key']);
    $default_library = sanitize_text_field($_POST['default_library']);
    $enable_fallback = isset($_POST['enable_fallback']) ? 1 : 0;
    $cache_duration = intval($_POST['cache_duration']);
    $enable_dynamic = isset($_POST['enable_dynamic']) ? 1 : 0;
    $enable_static = isset($_POST['enable_static']) ? 1 : 0;
    
    // Validate API key if provided
    $api_key_valid = false;
    $connection_message = '';
    
    if (!empty($api_key)) {
        $test_result = ZC_Charts_API_Client::test_connection($api_key);
        
        if ($test_result['success']) {
            $api_key_valid = true;
            $connection_message = 'API key is valid and connection successful!';
            
            // Store the API key
            ZC_Charts_Security::store_api_key($api_key);
        } else {
            $connection_error = $test_result['message'] ?? 'Connection failed';
            $connection_message = 'API key validation failed: ' . $connection_error;
        }
    } else {
        // Remove API key if empty
        ZC_Charts_Security::remove_api_key();
    }
    
    // Save other settings
    update_option('zc_charts_default_library', $default_library);
    update_option('zc_charts_enable_fallback', $enable_fallback);
    update_option('zc_charts_cache_duration', $cache_duration);
    update_option('zc_charts_enable_dynamic', $enable_dynamic);
    update_option('zc_charts_enable_static', $enable_static);
    
    if ($api_key_valid || empty($api_key)) {
        $success_message = __('Settings saved successfully!', ZC_CHARTS_TEXT_DOMAIN);
    } else {
        $error_message = $connection_message;
    }
}

// Get current settings
$current_api_key = ZC_Charts_Security::get_stored_api_key();
$api_key_status = ZC_Charts_Security::get_api_key_status();
$default_library = get_option('zc_charts_default_library', 'chartjs');
$enable_fallback = get_option('zc_charts_enable_fallback', 1);
$cache_duration = get_option('zc_charts_cache_duration', 300);
$enable_dynamic = get_option('zc_charts_enable_dynamic', 1);
$enable_static = get_option('zc_charts_enable_static', 1);

// Get DMT plugin status
$dmt_status = zc_charts()->get_dmt_status();

// Get available API keys from DMT (if accessible)
$available_keys = [];
if ($dmt_status['active'] && !empty($current_api_key)) {
    // This would typically call the DMT API to get available keys
    // For now, we'll show current key status
}

?>

<div class="wrap">
    <h1><?php _e('ZC Charts Settings', ZC_CHARTS_TEXT_DOMAIN); ?></h1>
    
    <?php if (isset($success_message)): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($success_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if (isset($connection_message) && isset($api_key_valid) && $api_key_valid): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($connection_message); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- DMT Plugin Status -->
    <div class="zc-settings-section">
        <h2><?php _e('Plugin Status', ZC_CHARTS_TEXT_DOMAIN); ?></h2>
        
        <div class="zc-status-grid">
            <div class="zc-status-item">
                <div class="zc-status-icon <?php echo $dmt_status['active'] ? 'zc-status-good' : 'zc-status-error'; ?>">
                    <span class="dashicons <?php echo $dmt_status['active'] ? 'dashicons-yes-alt' : 'dashicons-dismiss'; ?>"></span>
                </div>
                <div class="zc-status-info">
                    <h4><?php _e('DMT Plugin', ZC_CHARTS_TEXT_DOMAIN); ?></h4>
                    <p>
                        <?php if ($dmt_status['active']): ?>
                            <?php _e('Active and accessible', ZC_CHARTS_TEXT_DOMAIN); ?>
                            <?php if ($dmt_status['version']): ?>
                                <br><small><?php printf(__('Version: %s', ZC_CHARTS_TEXT_DOMAIN), $dmt_status['version']); ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php _e('Not active or not found', ZC_CHARTS_TEXT_DOMAIN); ?>
                            <br><small><?php _e('ZC Charts requires ZC DMT plugin to function', ZC_CHARTS_TEXT_DOMAIN); ?></small>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <div class="zc-status-item">
                <div class="zc-status-icon <?php echo $api_key_status['valid'] ? 'zc-status-good' : ($api_key_status['configured'] ? 'zc-status-warning' : 'zc-status-error'); ?>">
                    <span class="dashicons <?php echo $api_key_status['valid'] ? 'dashicons-admin-network' : 'dashicons-warning'; ?>"></span>
                </div>
                <div class="zc-status-info">
                    <h4><?php _e('API Connection', ZC_CHARTS_TEXT_DOMAIN); ?></h4>
                    <p>
                        <?php echo esc_html($api_key_status['message']); ?>
                        <?php if ($api_key_status['configured'] && isset($api_key_status['key_preview'])): ?>
                            <br><small><?php printf(__('Key: %s', ZC_CHARTS_TEXT_DOMAIN), $api_key_status['key_preview']); ?></small>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!$dmt_status['active']): ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('ZC DMT Plugin Required', ZC_CHARTS_TEXT_DOMAIN); ?></strong><br>
                <?php _e('The Charts plugin requires the ZC DMT (Data Management Tool) plugin to be installed and activated.', ZC_CHARTS_TEXT_DOMAIN); ?>
            </p>
            <p>
                <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-primary">
                    <?php _e('Go to Plugins', ZC_CHARTS_TEXT_DOMAIN); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('zc_charts_settings_nonce'); ?>
        
        <!-- API Configuration -->
        <div class="zc-settings-section">
            <h2><?php _e('API Configuration', ZC_CHARTS_TEXT_DOMAIN); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('DMT API Key', ZC_CHARTS_TEXT_DOMAIN); ?></th>
                    <td>
                        <input type="text" name="api_key" value="<?php echo esc_attr($current_api_key); ?>" 
                               class="large-text" placeholder="zc_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                        <p class="description">
                            <?php _e('API key generated by the ZC DMT plugin. Required for accessing chart data.', ZC_CHARTS_TEXT_DOMAIN); ?>
                            <?php if ($dmt_status['active']): ?>
                                <br>
                                <a href="<?php echo admin_url('admin.php?page=zc-dmt-settings'); ?>" target="_blank">
                                    <?php _e('Generate API key in DMT Settings', ZC_CHARTS_TEXT_DOMAIN); ?>
                                </a>
                            <?php endif; ?>
                        </p>
                        
                        <?php if (!empty($current_api_key)): ?>
                            <button type="button" id="zc-test-connection" class="button" <?php disabled(!$dmt_status['active']); ?>>
                                <?php _e('Test Connection', ZC_CHARTS_TEXT_DOMAIN); ?>
                            </button>
                            <span id="zc-test-result"></span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Chart Configuration -->
        <div class="zc-settings-section">
            <h2><?php _e('Chart Configuration', ZC_CHARTS_TEXT_DOMAIN); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Default Chart Library', ZC_CHARTS_TEXT_DOMAIN); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="default_library" value="chartjs" <?php checked($default_library, 'chartjs'); ?>>
                                <?php _e('Chart.js', ZC_CHARTS_TEXT_DOMAIN); ?>
                            </label><br>
                            
                            <label>
                                <input type="radio" name="default_library" value="highcharts" <?php checked($default_library, 'highcharts'); ?>>
                                <?php _e('Highcharts', ZC_CHARTS_TEXT_DOMAIN); ?>
                            </label>
                        </fieldset>
                        <p class="description">
                            <?php _e('Default chart rendering library. Can be overridden in individual shortcodes.', ZC_CHARTS_TEXT_DOMAIN); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Enable Chart Types', ZC_CHARTS_TEXT_DOMAIN); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="enable_dynamic" value="1" <?php checked($enable_dynamic, 1); ?>>
                                <?php _e('Dynamic Charts', ZC_CHARTS_TEXT_DOMAIN); ?>
                            </label>
                            <p class="description"><?php _e('Charts with interactive controls and timeframe selection', ZC_CHARTS_TEXT_DOMAIN); ?></p>
                            
                            <br>
                            
                            <label>
                                <input type="checkbox" name="enable_static" value="1" <?php checked($enable_static, 1); ?>>
                                <?php _e('Static Charts', ZC_CHARTS_TEXT_DOMAIN); ?>
                            </label>
                            <p class="description"><?php _e('Simple charts without interactive controls', ZC_CHARTS_TEXT_DOMAIN); ?></p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Cache Duration', ZC_CHARTS_TEXT_DOMAIN); ?></th>
                    <td>
                        <select name="cache_duration">
                            <option value="60" <?php selected($cache_duration, 60); ?>><?php _e('1 minute', ZC_CHARTS_TEXT_DOMAIN); ?></option>
                            <option value="300" <?php selected($cache_duration, 300); ?>><?php _e('5 minutes', ZC_CHARTS_TEXT_DOMAIN); ?></option>
                            <option value="900" <?php selected($cache_duration, 900); ?>><?php _e('15 minutes', ZC_CHARTS_TEXT_DOMAIN); ?></option>
                            <option value="1800" <?php selected($cache_duration, 1800); ?>><?php _e('30 minutes', ZC_CHARTS_TEXT_DOMAIN); ?></option>
                            <option value="3600" <?php selected($cache_duration, 3600); ?>><?php _e('1 hour', ZC_CHARTS_TEXT_DOMAIN); ?></option>
                        </select>
                        <p class="description"><?php _e('How long to cache chart data for better performance', ZC_CHARTS_TEXT_DOMAIN); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Fallback Configuration -->
        <div class="zc-settings-section">
            <h2><?php _e('Fallback & Error Handling', ZC_CHARTS_TEXT_DOMAIN); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Enable Fallback Data', ZC_CHARTS_TEXT_DOMAIN); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_fallback" value="1" <?php checked($enable_fallback, 1); ?>>
                            <?php _e('Automatically use backup data when live data is unavailable', ZC_CHARTS_TEXT_DOMAIN); ?>
                        </label>
                        <p class="description">
                            <?php _e('When enabled, charts will display backup data from Google Drive if live data cannot be loaded.', ZC_CHARTS_TEXT_DOMAIN); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Shortcode Examples -->
        <div class="zc-settings-section">
            <h2><?php _e('Shortcode Examples', ZC_CHARTS_TEXT_DOMAIN); ?></h2>
            
            <div class="zc-shortcode-examples">
                <h4><?php _e('Dynamic Chart (with controls)', ZC_CHARTS_TEXT_DOMAIN); ?></h4>
                <code>[zc_chart_dynamic id="gdp_us_sample" library="chartjs" timeframe="1y" height="400px"]</code>
                
                <h4><?php _e('Static Chart (simple display)', ZC_CHARTS_TEXT_DOMAIN); ?></h4>
                <code>[zc_chart_static id="unemployment_us_sample" library="highcharts" height="300px"]</code>
                
                <h4><?php _e('Available Parameters:', ZC_CHARTS_TEXT_DOMAIN); ?></h4>
                <ul class="zc-parameter-list">
                    <li><strong>id</strong> - <?php _e('Indicator slug (required)', ZC_CHARTS_TEXT_DOMAIN); ?></li>
                    <li><strong>library</strong> - <?php _e('Chart library: chartjs or highcharts', ZC_CHARTS_TEXT_DOMAIN); ?></li>
                    <li><strong>timeframe</strong> - <?php _e('Time period: 3m, 6m, 1y, 2y, 3y, 5y, 10y, all', ZC_CHARTS_TEXT_DOMAIN); ?></li>
                    <li><strong>height</strong> - <?php _e('Chart height (e.g., 400px)', ZC_CHARTS_TEXT_DOMAIN); ?></li>
                    <li><strong>width</strong> - <?php _e('Chart width (e.g., 100%, 800px)', ZC_CHARTS_TEXT_DOMAIN); ?></li>
                    <li><strong>controls</strong> - <?php _e('Show controls on dynamic charts: true/false', ZC_CHARTS_TEXT_DOMAIN); ?></li>
                </ul>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="zc_charts_save_settings" class="button-primary" 
                   value="<?php esc_attr_e('Save Settings', ZC_CHARTS_TEXT_DOMAIN); ?>">
        </p>
    </form>
    
</div>

<script>
jQuery(document).ready(function($) {
    
    // Test API connection
    $('#zc-test-connection').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var result = $('#zc-test-result');
        var apiKey = $('input[name="api_key"]').val();
        
        if (!apiKey) {
            result.html('<span style="color: #d63638;"><?php esc_js(_e('Please enter an API key first', ZC_CHARTS_TEXT_DOMAIN)); ?></span>');
            return;
        }
        
        button.prop('disabled', true).text('<?php esc_js(_e('Testing...', ZC_CHARTS_TEXT_DOMAIN)); ?>');
        result.html('<span style="color: #666;"><?php esc_js(_e('Testing connection...', ZC_CHARTS_TEXT_DOMAIN)); ?></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'zc_charts_test_connection',
                api_key: apiKey,
                nonce: '<?php echo wp_create_nonce('zc_charts_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    result.html('<span style="color: #00a32a;">✓ ' + response.data.message + '</span>');
                    if (response.data.indicators_count) {
                        result.append('<br><small><?php esc_js(_e('Available indicators:', ZC_CHARTS_TEXT_DOMAIN)); ?> ' + response.data.indicators_count + '</small>');
                    }
                } else {
                    result.html('<span style="color: #d63638;">✗ ' + (response.data || '<?php esc_js(_e('Connection failed', ZC_CHARTS_TEXT_DOMAIN)); ?>') + '</span>');
                }
            },
            error: function() {
                result.html('<span style="color: #d63638;">✗ <?php esc_js(_e('Request failed', ZC_CHARTS_TEXT_DOMAIN)); ?></span>');
            },
            complete: function() {
                button.prop('disabled', false).text('<?php esc_js(_e('Test Connection', ZC_CHARTS_TEXT_DOMAIN)); ?>');
            }
        });
    });
    
});
</script>

<style>
.zc-settings-section {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
}

.zc-settings-section h2 {
    margin: 0;
    padding: 15px 20px;
    border-bottom: 1px solid #c3c4c7;
    background: #f6f7f7;
    font-size: 18px;
    font-weight: 600;
}

.zc-settings-section .form-table {
    margin: 0;
    padding: 20px;
}

.zc-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    padding: 20px;
}

.zc-status-item {
    display: flex;
    align-items: center;
    gap: 15px;
}

.zc-status-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.zc-status-good {
    background: #00a32a;
}

.zc-status-warning {
    background: #dba617;
}

.zc-status-error {
    background: #d63638;
}

.zc-status-icon .dashicons {
    color: #fff;
    font-size: 20px;
    width: 20px;
    height: 20px;
}

.zc-status-info h4 {
    margin: 0 0 5px 0;
    font-size: 16px;
    font-weight: 600;
}

.zc-status-info p {
    margin: 0;
    font-size: 14px;
    color: #646970;
}

.zc-shortcode-examples {
    padding: 20px;
    background: #f6f7f7;
    border-radius: 4px;
}

.zc-shortcode-examples h4 {
    margin: 20px 0 10px 0;
    color: #1d2327;
}

.zc-shortcode-examples h4:first-child {
    margin-top: 0;
}

.zc-shortcode-examples code {
    display: block;
    background: #fff;
    padding: 10px 15px;
    border-radius: 4px;
    border: 1px solid #c3c4c7;
    font-family: Monaco, Consolas, 'Courier New', monospace;
    color: #d63638;
    margin-bottom: 15px;
}

.zc-parameter-list {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 15px 20px;
    margin: 10px 0;
}

.zc-parameter-list li {
    margin-bottom: 8px;
}

.zc-parameter-list strong {
    color: #2271b1;
    font-family: Monaco, Consolas, monospace;
}

#zc-test-result {
    margin-left: 10px;
    font-weight: 500;
}

@media (max-width: 768px) {
    .zc-status-grid {
        grid-template-columns: 1fr;
    }
}
</style>
