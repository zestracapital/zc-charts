<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZC_Charts_Charts class.
 * Handles the core logic for chart rendering.
 */
class ZC_Charts_Charts {

    /**
     * Render a chart based on the provided configuration.
     *
     * @param array $config {
     *     Configuration array for the chart.
     *
     *     @type string $id           Indicator slug (required).
     *     @type string $library      Chart library ('chartjs' or 'highcharts'). Default 'chartjs'.
     *     @type string $type         Chart type ('line', 'bar', etc.). Default 'line'.
     *     @type string $height       Chart container height. Default '300px'.
     *     @type bool   $controls     Whether to show controls (for dynamic charts). Default false.
     *     @type string $timeframe    Timeframe for data (e.g., '1y', 'all'). Default '1y'.
     *     @type string $unique_id    Unique identifier for this chart instance. Default generated.
     *     @type string $css_class    Additional CSS class for the wrapper. Default ''.
     * }
     * @return string The HTML output for the chart.
     */
    public static function render_chart($config) {
        // Parse and sanitize configuration
        $defaults = array(
            'id'         => '',
            'library'    => 'chartjs',
            'type'       => 'line',
            'height'     => '300px',
            'controls'   => false,
            'timeframe'  => '1y',
            'unique_id'  => 'zc-chart-' . uniqid(),
            'css_class'  => '',
            'is_static'  => false, // Internal flag to differentiate static/dynamic
        );

        $config = wp_parse_args($config, $defaults);

        // Validate required parameters
        if (empty($config['id'])) {
            return self::get_error_markup(__('Indicator ID is required to render a chart.', 'zc-charts'));
        }

        // Sanitize inputs
        $indicator_slug = sanitize_key($config['id']);
        $library        = in_array($config['library'], array('chartjs', 'highcharts')) ? $config['library'] : 'chartjs';
        $type           = sanitize_key($config['type']);
        $height         = sanitize_text_field($config['height']);
        $controls       = wp_validate_boolean($config['controls']);
        $timeframe      = sanitize_text_field($config['timeframe']);
        $unique_id      = sanitize_html_class($config['unique_id']);
        $css_class      = sanitize_html_class($config['css_class']);
        $is_static      = wp_validate_boolean($config['is_static']);

        // Get the API key from settings
        $api_key = ZC_Charts_Security::get_api_key();

        // Validate the API key first
        if (empty($api_key)) {
            return self::get_error_markup(__('API key is not configured. Please set a valid API key in the ZC Charts settings.', 'zc-charts'), 'api_key_missing');
        }

        $is_key_valid = ZC_Charts_API_Client::validate_api_key($api_key);

        if (!$is_key_valid) {
            return self::get_error_markup(__('Unauthorized access. Please check your API key configuration in the ZC Charts settings.', 'zc-charts'), 'invalid_api_key');
        }

        // Prepare API arguments based on timeframe (for dynamic charts)
        $api_args = array();
        if (!$is_static && !empty($timeframe) && $timeframe !== 'all') {
            $start_date = self::calculate_start_date($timeframe);
            if ($start_date) {
                $api_args['start_date'] = $start_date;
            }
        }

        // 1. Attempt to fetch live data
        $live_data = ZC_Charts_API_Client::fetch_live_data($indicator_slug, $api_key, $api_args);

        if (is_wp_error($live_data)) {
            // Log the live data fetch error for debugging
            error_log('ZC Charts: Live data fetch failed for indicator ' . $indicator_slug . '. Error: ' . $live_data->get_error_message());

            // 2. If live data fails, attempt to fetch backup data
            // Check if fallback is enabled in settings (default to true if not set)
            $enable_fallback = get_option('zc_charts_enable_fallback', true);

            if ($enable_fallback) {
                $backup_data = ZC_Charts_API_Client::fetch_backup_data($indicator_slug, $api_key);

                if (is_wp_error($backup_data)) {
                    // Log the backup data fetch error
                    error_log('ZC Charts: Backup data fetch also failed for indicator ' . $indicator_slug . '. Error: ' . $backup_data->get_error_message());

                    // 3. If both fail, return a generic error message
                    // The message should be user-friendly but not expose internal errors
                    $user_message = __('Unable to load data. Both live and backup data sources are unavailable.', 'zc-charts');
                    if ($live_data->get_error_code() === 'api_http_error' && strpos($live_data->get_error_message(), '404') !== false) {
                         $user_message = __('The requested indicator data is not available.', 'zc-charts');
                    }
                    return self::get_error_markup($user_message, 'data_unavailable');
                } else {
                    // 2a. Backup data fetch successful
                    // Use backup data and indicate it's fallback data
                    $chart_data = $backup_data;
                    $is_fallback = true;
                }
            } else {
                 // Fallback is disabled, only show live data error
                 $user_message = __('Unable to load data.', 'zc-charts');
                 if ($live_data->get_error_code() === 'api_http_error' && strpos($live_data->get_error_message(), '404') !== false) {
                      $user_message = __('The requested indicator data is not available.', 'zc-charts');
                 }
                 return self::get_error_markup($user_message, 'data_unavailable');
            }
        } else {
            // 1a. Live data fetch successful
            $chart_data = $live_data;
            $is_fallback = false;
        }

        // Process the data for the chart library
        $processed_data = self::process_chart_data($chart_data, $library);

        if (is_wp_error($processed_data)) {
            return self::get_error_markup($processed_data->get_error_message(), 'data_processing_error');
        }

        // Enqueue necessary scripts and styles for the chosen library
        // This should ideally be done in the shortcode handler or a buffer, but for simplicity, we do it here.
        // In a more complex plugin, you might want to enqueue on `wp_enqueue_scripts` with a flag.
        self::enqueue_chart_library($library);

        // Build the HTML output
        $wrapper_classes = array('zc-chart-wrapper');
        if (!empty($css_class)) {
            $wrapper_classes[] = $css_class;
        }
        if ($is_fallback) {
            $wrapper_classes[] = 'zc-chart-fallback';
        }
        if ($controls) {
            $wrapper_classes[] = 'zc-chart-has-controls';
        }

        $output = '<div id="' . esc_attr($unique_id . '-wrapper') . '" class="' . esc_attr(implode(' ', $wrapper_classes)) . '">';

        // Add controls for dynamic charts
        if ($controls && !$is_static) {
            $output .= self::get_control_markup($unique_id, $timeframe);
        }

        // Add fallback notice if data is from backup
        if ($is_fallback) {
            $output .= '<div class="zc-chart-notice">' . esc_html__('Displaying cached data', 'zc-charts') . '</div>';
        }

        // Chart container
        $output .= '<div id="' . esc_attr($unique_id) . '" class="zc-chart-container" style="height: ' . esc_attr($height) . '; min-height: ' . esc_attr($height) . ';">';
        // The actual chart will be rendered by JavaScript
        $output .= '</div>';

        // Pass configuration to JavaScript
        $js_config = array(
            'slug'          => $indicator_slug,
            'library'       => $library,
            'type'          => $type,
            'controls'      => $controls,
            'timeframe'     => $timeframe,
            'isFallback'    => $is_fallback,
            'data'          => $processed_data, // Pass processed data directly for static rendering or initial load
            'isStatic'      => $is_static,
            // Add any other options needed by the JS handler
        );

        $output .= '<script type="text/javascript">';
        $output .= 'document.addEventListener("DOMContentLoaded", function() {';
        $output .= 'if (typeof window.ZCChartLoader !== "undefined") {';
        $output .= 'new ZCChartLoader("' . esc_js($unique_id) . '", ' . wp_json_encode($js_config) . ').loadChart();';
        $output .= '} else {';
        $output .= 'console.error("ZCChartLoader not found. Chart library may not be loaded correctly.");';
        $output .= '}';
        $output .= '});';
        $output .= '</script>';

        $output .= '</div>'; // .zc-chart-wrapper

        return $output;
    }

    /**
     * Get the HTML markup for an error message.
     *
     * @param string $message The error message.
     * @param string $type    Optional. Type of error for specific styling. Default 'generic'.
     * @return string The HTML markup for the error.
     */
    public static function get_error_markup($message, $type = 'generic') {
        ob_start();
        ?>
        <div class="zc-chart-wrapper zc-chart-error zc-chart-error-<?php echo esc_attr($type); ?>">
            <div class="zc-chart-error-content">
                <div class="error-icon">⚠️</div>
                <div class="error-message"><?php echo esc_html($message); ?></div>
                <?php if ($type === 'invalid_api_key' || $type === 'api_key_missing') : ?>
                    <div class="error-details"><?php esc_html_e('Please check your API key configuration in the WordPress admin settings.', 'zc-charts'); ?></div>
                <?php elseif ($type === 'data_unavailable') : ?>
                    <div class="error-details"><?php esc_html_e('Please try again later or contact the site administrator.', 'zc-charts'); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Process raw data from the API into a format suitable for the chart library.
     *
     * @param array  $data    The raw data array from the API.
     * @param string $library The chart library ('chartjs' or 'highcharts').
     * @return array|WP_Error The processed data array or WP_Error on failure.
     */
    public static function process_chart_data($data, $library) {
        if (!isset($data['data']) || !is_array($data['data'])) {
             return new WP_Error('invalid_data_format', __('Invalid data format received from API.', 'zc-charts'));
        }

        $raw_points = $data['data'];
        $processed_points = array();

        if ($library === 'highcharts') {
            // Highcharts typically expects [[timestamp, value], ...] or [value1, value2, ...] for simple series
            // Or {x: timestamp, y: value} objects.
            // For date-based data, [timestamp, value] is common.
            foreach ($raw_points as $point) {
                if (isset($point['date']) && isset($point['value'])) {
                    // Convert date string to timestamp (in milliseconds for Highcharts)
                    $timestamp = strtotime($point['date']) * 1000; // Highcharts uses milliseconds
                    $value = floatval($point['value']);
                    $processed_points[] = array($timestamp, $value);
                }
            }
        } else {
            // Default to Chart.js format
            // Chart.js expects labels array and datasets array
            // For simplicity, we'll structure it as a single dataset
            // Return an array with 'labels' and 'datasets'
            $labels = array();
            $values = array();
            foreach ($raw_points as $point) {
                if (isset($point['date']) && isset($point['value'])) {
                    // Chart.js can work with date strings directly on the x-axis with proper configuration
                    $labels[] = $point['date']; // or format it: date('Y-m-d', strtotime($point['date']));
                    $values[] = floatval($point['value']);
                }
            }
            $processed_points = array(
                'labels' => $labels,
                'datasets' => array(
                    array(
                        'label' => isset($data['name']) ? $data['name'] : '',
                        'data' => $values,
                        // Add more dataset options if needed
                    )
                )
            );
        }

        return $processed_points;
    }

    /**
     * Enqueue the necessary JavaScript library for the chart.
     *
     * @param string $library The chart library ('chartjs' or 'highcharts').
     */
    public static function enqueue_chart_library($library) {
        if ($library === 'highcharts' && !wp_script_is('highcharts', 'registered')) {
            // Register Highcharts from CDN
            wp_register_script('highcharts', 'https://code.highcharts.com/highcharts.js', array(), 'latest', true);
        } elseif ($library === 'chartjs' && !wp_script_is('chartjs', 'registered')) {
            // Register Chart.js from CDN
            wp_register_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), 'latest', true);
        }

        // Enqueue the selected library
        if ($library === 'highcharts') {
            wp_enqueue_script('highcharts');
        } else {
            wp_enqueue_script('chartjs');
        }

        // Enqueue our chart loader script which depends on the library
        if (!wp_script_is('zc-chart-loader', 'registered')) {
            wp_register_script(
                'zc-chart-loader',
                ZC_CHARTS_PLUGIN_URL . 'assets/js/chart-loader.js',
                array('jquery'), // jQuery is often a dependency, adjust if not needed
                ZC_CHARTS_VERSION,
                true
            );
            // Pass data to JavaScript
            wp_localize_script('zc-chart-loader', 'zcChartsConfig', array(
                'apiKey' => ZC_Charts_Security::get_api_key(),
                'dmtApiUrl' => ZC_Charts_Security::get_dmt_api_base_url(), // This returns the base URL with namespace
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('zc_charts_nonce')
            ));
        }
        wp_enqueue_script('zc-chart-loader');
    }

    /**
     * Calculate the start date based on a timeframe string.
     *
     * @param string $timeframe The timeframe (e.g., '1y', '6m').
     * @return string|false The start date in 'Y-m-d' format, or false on failure.
     */
    public static function calculate_start_date($timeframe) {
        $map = array(
            '1d'  => '1 day ago',
            '3d'  => '3 days ago',
            '1w'  => '1 week ago',
            '2w'  => '2 weeks ago',
            '1m'  => '1 month ago',
            '3m'  => '3 months ago',
            '6m'  => '6 months ago',
            '1y'  => '1 year ago',
            '2y'  => '2 years ago',
            '3y'  => '3 years ago',
            '5y'  => '5 years ago',
            '10y' => '10 years ago',
        );

        if (isset($map[$timeframe])) {
            return date('Y-m-d', strtotime($map[$timeframe]));
        }

        // Handle 'all' or unknown timeframes
        return false;
    }

    /**
     * Get the HTML markup for chart controls.
     *
     * @param string $container_id The ID of the chart container.
     * @param string $current_timeframe The currently selected timeframe.
     * @return string The HTML markup for controls.
     */
    public static function get_control_markup($container_id, $current_timeframe = '1y') {
        // Define available timeframes
        $timeframes = array(
            '3m'  => __('3M', 'zc-charts'),
            '6m'  => __('6M', 'zc-charts'),
            '1y'  => __('1Y', 'zc-charts'),
            '2y'  => __('2Y', 'zc-charts'),
            '5y'  => __('5Y', 'zc-charts'),
            'all' => __('All', 'zc-charts'),
        );

        ob_start();
        ?>
        <div class="zc-chart-controls" data-chart-id="<?php echo esc_attr($container_id); ?>">
            <div class="timeframe-controls">
                <?php foreach ($timeframes as $tf_value => $tf_label) : ?>
                    <button type="button" data-range="<?php echo esc_attr($tf_value); ?>" class="<?php echo ($tf_value === $current_timeframe) ? 'active' : ''; ?>">
                        <?php echo esc_html($tf_label); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="chart-controls">
                <select class="chart-type-selector">
                    <option value="line"><?php esc_html_e('Line Chart', 'zc-charts'); ?></option>
                    <option value="bar"><?php esc_html_e('Bar Chart', 'zc-charts'); ?></option>
                    <option value="area"><?php esc_html_e('Area Chart', 'zc-charts'); ?></option>
                </select>
                <button type="button" class="export-btn"><?php esc_html_e('Export', 'zc-charts'); ?></button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
