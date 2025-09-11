<?php
/**
 * Shortcodes Handler for ZC Charts Plugin
 * Renders dynamic and static charts with full validation
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZC_Charts_Shortcodes {
    
    /**
     * Initialize shortcodes system
     */
    public static function init() {
        // Register main shortcodes
        add_shortcode('zc_chart_dynamic', [__CLASS__, 'render_dynamic_chart']);
        add_shortcode('zc_chart_static', [__CLASS__, 'render_static_chart']);
        
        // Legacy support
        add_shortcode('zc_chart', [__CLASS__, 'render_legacy_chart']);
        
        // Frontend asset loading
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend_assets']);
        add_action('wp_footer', [__CLASS__, 'output_chart_config']);
        
        // Admin hooks
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }
    
    /**
     * Render dynamic chart with interactive controls
     * [zc_chart_dynamic id="gdp_us" library="chartjs" timeframe="1y" height="400px"]
     */
    public static function render_dynamic_chart($atts) {
        return self::render_chart($atts, 'dynamic');
    }
    
    /**
     * Render static chart without controls
     * [zc_chart_static id="unemployment" library="highcharts" height="300px"]
     */
    public static function render_static_chart($atts) {
        return self::render_chart($atts, 'static');
    }
    
    /**
     * Legacy shortcode support
     * [zc_chart id="indicator" type="dynamic"]
     */
    public static function render_legacy_chart($atts) {
        $type = isset($atts['type']) ? $atts['type'] : 'dynamic';
        return self::render_chart($atts, $type);
    }
    
    /**
     * Main chart rendering function
     */
    private static function render_chart($atts, $type) {
        // Default attributes
        $defaults = [
            'id' => '',
            'library' => get_option('zc_charts_default_library', 'chartjs'),
            'timeframe' => '1y',
            'height' => $type === 'dynamic' ? '400px' : '300px',
            'width' => '100%',
            'controls' => $type === 'dynamic' ? 'true' : 'false',
            'title' => '',
            'subtitle' => '',
            'theme' => 'default',
            'animation' => 'true',
            'responsive' => 'true',
            'class' => '',
            'style' => ''
        ];
        
        $atts = shortcode_atts($defaults, $atts);
        
        // CRITICAL: Validate required parameters
        if (empty($atts['id'])) {
            return self::render_error('Chart ID is required');
        }
        
        // CRITICAL: Check API key validation
        $api_key = ZC_Charts_Security::get_stored_api_key();
        if (empty($api_key)) {
            return self::render_error('No API key configured. Please configure in Charts plugin settings.');
        }
        
        // Validate API key with DMT plugin
        if (!ZC_Charts_Security::validate_api_key($api_key)) {
            return self::render_error('Invalid API key. Charts cannot render without valid authentication.');
        }
        
        // Check if chart type is enabled
        if ($type === 'dynamic' && !get_option('zc_charts_enable_dynamic', 1)) {
            return self::render_error('Dynamic charts are disabled');
        }
        
        if ($type === 'static' && !get_option('zc_charts_enable_static', 1)) {
            return self::render_error('Static charts are disabled');
        }
        
        // Sanitize and validate attributes
        $config = self::sanitize_chart_config($atts, $type, $api_key);
        
        // Generate unique chart container ID
        $chart_id = 'zc-chart-' . sanitize_key($atts['id']) . '-' . uniqid();
        
        // Start output buffering
        ob_start();
        
        // Render chart HTML
        self::render_chart_html($chart_id, $config, $type);
        
        return ob_get_clean();
    }
    
    /**
     * Render chart HTML container
     */
    private static function render_chart_html($chart_id, $config, $type) {
        $wrapper_classes = [
            'zc-chart-wrapper',
            'zc-chart-' . $type,
            'zc-chart-library-' . $config['library']
        ];
        
        if ($config['responsive']) {
            $wrapper_classes[] = 'zc-chart-responsive';
        }
        
        if (!empty($config['class'])) {
            $wrapper_classes[] = sanitize_html_class($config['class']);
        }
        
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>"
             data-chart-config="<?php echo esc_attr(json_encode($config)); ?>"
             data-chart-type="<?php echo esc_attr($type); ?>"
             style="<?php echo esc_attr($config['style']); ?>">
            
            <!-- Chart Header -->
            <?php if (!empty($config['title']) || !empty($config['subtitle'])): ?>
                <div class="zc-chart-header">
                    <?php if (!empty($config['title'])): ?>
                        <h3 class="zc-chart-title"><?php echo esc_html($config['title']); ?></h3>
                    <?php endif; ?>
                    
                    <?php if (!empty($config['subtitle'])): ?>
                        <p class="zc-chart-subtitle"><?php echo esc_html($config['subtitle']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Chart Controls (Dynamic only) -->
            <?php if ($type === 'dynamic' && $config['controls']): ?>
                <div class="zc-chart-controls">
                    <div class="zc-timeframe-controls">
                        <?php
                        $timeframes = [
                            '3m' => __('3M', ZC_CHARTS_TEXT_DOMAIN),
                            '6m' => __('6M', ZC_CHARTS_TEXT_DOMAIN),
                            '1y' => __('1Y', ZC_CHARTS_TEXT_DOMAIN),
                            '2y' => __('2Y', ZC_CHARTS_TEXT_DOMAIN),
                            '3y' => __('3Y', ZC_CHARTS_TEXT_DOMAIN),
                            '5y' => __('5Y', ZC_CHARTS_TEXT_DOMAIN),
                            '10y' => __('10Y', ZC_CHARTS_TEXT_DOMAIN),
                            'all' => __('All', ZC_CHARTS_TEXT_DOMAIN)
                        ];
                        
                        foreach ($timeframes as $key => $label) {
                            $active_class = ($key === $config['timeframe']) ? ' active' : '';
                            printf(
                                '<button type="button" class="zc-timeframe-btn%s" data-range="%s">%s</button>',
                                $active_class,
                                esc_attr($key),
                                esc_html($label)
                            );
                        }
                        ?>
                    </div>
                    
                    <div class="zc-chart-tools">
                        <select class="zc-chart-type-selector">
                            <option value="line"><?php _e('Line Chart', ZC_CHARTS_TEXT_DOMAIN); ?></option>
                            <option value="area"><?php _e('Area Chart', ZC_CHARTS_TEXT_DOMAIN); ?></option>
                            <option value="bar"><?php _e('Bar Chart', ZC_CHARTS_TEXT_DOMAIN); ?></option>
                        </select>
                        
                        <button type="button" class="zc-export-btn" title="<?php esc_attr_e('Export Chart', ZC_CHARTS_TEXT_DOMAIN); ?>">
                            <span class="dashicons dashicons-download"></span>
                        </button>
                        
                        <button type="button" class="zc-fullscreen-btn" title="<?php esc_attr_e('Fullscreen', ZC_CHARTS_TEXT_DOMAIN); ?>">
                            <span class="dashicons dashicons-fullscreen-alt"></span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Chart Container -->
            <div id="<?php echo esc_attr($chart_id); ?>" 
                 class="zc-chart-container"
                 style="height: <?php echo esc_attr($config['height']); ?>; width: <?php echo esc_attr($config['width']); ?>;"
                 data-library="<?php echo esc_attr($config['library']); ?>"
                 data-indicator="<?php echo esc_attr($config['slug']); ?>">
                 
                <!-- Loading State -->
                <div class="zc-chart-loading">
                    <div class="zc-loading-spinner"></div>
                    <p><?php _e('Loading chart data...', ZC_CHARTS_TEXT_DOMAIN); ?></p>
                </div>
                
                <!-- Chart will be rendered here by JavaScript -->
                
            </div>
            
            <!-- Chart Footer -->
            <div class="zc-chart-footer">
                <div class="zc-chart-meta">
                    <span class="zc-data-source">
                        <strong><?php _e('Source:', ZC_CHARTS_TEXT_DOMAIN); ?></strong> 
                        <span class="zc-source-name">-</span>
                    </span>
                    <span class="zc-last-updated">
                        <strong><?php _e('Updated:', ZC_CHARTS_TEXT_DOMAIN); ?></strong> 
                        <span class="zc-update-time">-</span>
                    </span>
                </div>
            </div>
            
        </div>
        
        <!-- Initialize Chart -->
        <script>
        jQuery(document).ready(function($) {
            if (typeof ZCChartLoader !== 'undefined') {
                var chartLoader = new ZCChartLoader('<?php echo esc_js($chart_id); ?>', <?php echo json_encode($config); ?>);
                chartLoader.loadChart();
            } else {
                console.error('ZC Chart Loader not available');
                $('#<?php echo esc_js($chart_id); ?>').html('<div class="zc-chart-error">Chart loader not available</div>');
            }
        });
        </script>
        <?php
    }
    
    /**
     * Sanitize chart configuration
     */
    private static function sanitize_chart_config($atts, $type, $api_key) {
        $config = [];
        
        // Required fields
        $config['slug'] = sanitize_key($atts['id']);
        $config['type'] = $type;
        $config['api_key'] = $api_key;
        
        // Library validation
        $allowed_libraries = ['chartjs', 'highcharts'];
        $config['library'] = in_array($atts['library'], $allowed_libraries) ? $atts['library'] : 'chartjs';
        
        // Timeframe validation
        $allowed_timeframes = ['3m', '6m', '1y', '2y', '3y', '5y', '10y', '15y', '20y', '25y', 'all'];
        $config['timeframe'] = in_array($atts['timeframe'], $allowed_timeframes) ? $atts['timeframe'] : '1y';
        
        // Dimensions
        $config['height'] = self::sanitize_dimension($atts['height']);
        $config['width'] = self::sanitize_dimension($atts['width']);
        
        // Boolean options
        $config['controls'] = filter_var($atts['controls'], FILTER_VALIDATE_BOOLEAN);
        $config['animation'] = filter_var($atts['animation'], FILTER_VALIDATE_BOOLEAN);
        $config['responsive'] = filter_var($atts['responsive'], FILTER_VALIDATE_BOOLEAN);
        
        // Text fields
        $config['title'] = sanitize_text_field($atts['title']);
        $config['subtitle'] = sanitize_text_field($atts['subtitle']);
        
        // Theme validation
        $allowed_themes = ['default', 'dark', 'light', 'colorful'];
        $config['theme'] = in_array($atts['theme'], $allowed_themes) ? $atts['theme'] : 'default';
        
        // Style attributes
        $config['class'] = sanitize_html_class($atts['class']);
        $config['style'] = wp_strip_all_tags($atts['style']);
        
        return $config;
    }
    
    /**
     * Sanitize dimension values
     */
    private static function sanitize_dimension($value) {
        if (empty($value)) {
            return '400px';
        }
        
        // Allow percentage, pixels, em, rem, vh, vw
        if (preg_match('/^\d+(%|px|em|rem|vh|vw)$/', $value)) {
            return $value;
        }
        
        // Plain numbers default to pixels
        if (is_numeric($value)) {
            return $value . 'px';
        }
        
        return '400px';
    }
    
    /**
     * Render error message
     */
    private static function render_error($message) {
        return sprintf(
            '<div class="zc-chart-error">
                <div class="zc-error-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="zc-error-content">
                    <div class="zc-error-message">%s</div>
                    %s
                </div>
            </div>',
            esc_html($message),
            current_user_can('manage_options') ? 
                '<div class="zc-error-admin-link"><a href="' . admin_url('admin.php?page=zc-charts-settings') . '">Configure Charts Settings</a></div>' : ''
        );
    }
    
    /**
     * Enqueue frontend assets
     */
    public static function enqueue_frontend_assets() {
        global $post;
        
        // Only load if shortcodes are present
        if (!is_object($post)) {
            return;
        }
        
        $has_charts = has_shortcode($post->post_content, 'zc_chart_dynamic') ||
                     has_shortcode($post->post_content, 'zc_chart_static') ||
                     has_shortcode($post->post_content, 'zc_chart');
        
        if (!$has_charts) {
            return;
        }
        
        // Chart styles
        wp_enqueue_style(
            'zc-charts-public',
            ZC_CHARTS_PLUGIN_URL . 'assets/css/charts.css',
            [],
            ZC_CHARTS_VERSION
        );
        
        // Chart loader
        wp_enqueue_script(
            'zc-charts-loader',
            ZC_CHARTS_PLUGIN_URL . 'assets/js/chart-loader.js',
            ['jquery'],
            ZC_CHARTS_VERSION,
            true
        );
        
        // Chart.js library (conditional)
        if (self::page_needs_library('chartjs')) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js',
                [],
                '4.4.0',
                true
            );
        }
        
        // Highcharts library (conditional)
        if (self::page_needs_library('highcharts')) {
            wp_enqueue_script(
                'highcharts',
                'https://code.highcharts.com/highcharts.js',
                [],
                '11.1.0',
                true
            );
        }
        
        // Dashicons for controls
        wp_enqueue_style('dashicons');
    }
    
    /**
     * Check if page needs specific chart library
     */
    private static function page_needs_library($library) {
        global $post;
        
        if (!is_object($post)) {
            return false;
        }
        
        return strpos($post->post_content, 'library="' . $library . '"') !== false ||
               get_option('zc_charts_default_library', 'chartjs') === $library;
    }
    
    /**
     * Output chart configuration to footer
     */
    public static function output_chart_config() {
        global $post;
        
        if (!is_object($post)) {
            return;
        }
        
        $has_charts = has_shortcode($post->post_content, 'zc_chart_dynamic') ||
                     has_shortcode($post->post_content, 'zc_chart_static') ||
                     has_shortcode($post->post_content, 'zc_chart');
        
        if (!$has_charts) {
            return;
        }
        
        $config = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'dmt_api_url' => rest_url('zc-dmt/v1'),
            'fallback_enabled' => get_option('zc_charts_enable_fallback', 1),
            'default_library' => get_option('zc_charts_default_library', 'chartjs'),
            'cache_duration' => get_option('zc_charts_cache_duration', 300),
            'strings' => [
                'loading_chart' => __('Loading chart...', ZC_CHARTS_TEXT_DOMAIN),
                'error_loading' => __('Error loading chart data', ZC_CHARTS_TEXT_DOMAIN),
                'unauthorized' => __('Unauthorized: Invalid API key', ZC_CHARTS_TEXT_DOMAIN),
                'data_not_found' => __('Data not found for this indicator', ZC_CHARTS_TEXT_DOMAIN),
                'network_error' => __('Network error occurred', ZC_CHARTS_TEXT_DOMAIN),
                'fallback_data' => __('Displaying backup data', ZC_CHARTS_TEXT_DOMAIN),
                'no_data' => __('No data available', ZC_CHARTS_TEXT_DOMAIN)
            ]
        ];
        
        ?>
        <script type="text/javascript">
            window.zcChartsConfig = <?php echo json_encode($config); ?>;
        </script>
        <?php
    }
    
    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets($hook) {
        // Only load on post editing pages
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }
        
        wp_enqueue_script(
            'zc-charts-admin-shortcodes',
            ZC_CHARTS_PLUGIN_URL . 'assets/js/admin-shortcodes.js',
            ['jquery'],
            ZC_CHARTS_VERSION,
            true
        );
        
        wp_localize_script('zc-charts-admin-shortcodes', 'zcChartsAdmin', [
            'nonce' => wp_create_nonce('zc_charts_nonce'),
            'strings' => [
                'insert_chart' => __('Insert Chart', ZC_CHARTS_TEXT_DOMAIN),
                'select_indicator' => __('Select Indicator', ZC_CHARTS_TEXT_DOMAIN),
                'chart_settings' => __('Chart Settings', ZC_CHARTS_TEXT_DOMAIN)
            ]
        ]);
    }
    
    /**
     * Get shortcode usage statistics
     */
    public static function get_usage_statistics() {
        global $wpdb;
        
        $stats = [];
        
        // Count dynamic charts
        $stats['dynamic_charts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $wpdb->posts 
             WHERE post_content LIKE '%[zc_chart_dynamic%' 
             AND post_status = 'publish'"
        );
        
        // Count static charts
        $stats['static_charts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $wpdb->posts 
             WHERE post_content LIKE '%[zc_chart_static%' 
             AND post_status = 'publish'"
        );
        
        // Count legacy charts
        $stats['legacy_charts'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM $wpdb->posts 
             WHERE post_content LIKE '%[zc_chart %' 
             AND post_status = 'publish'"
        );
        
        $stats['total_charts'] = $stats['dynamic_charts'] + $stats['static_charts'] + $stats['legacy_charts'];
        
        return $stats;
    }
    
    /**
     * Validate shortcode attributes (for admin use)
     */
    public static function validate_shortcode_attributes($atts, $type = 'dynamic') {
        $errors = [];
        
        // Required ID
        if (empty($atts['id'])) {
            $errors[] = __('Chart ID is required', ZC_CHARTS_TEXT_DOMAIN);
        }
        
        // Library validation
        if (isset($atts['library'])) {
            $allowed_libraries = ['chartjs', 'highcharts'];
            if (!in_array($atts['library'], $allowed_libraries)) {
                $errors[] = sprintf(
                    __('Invalid library "%s". Allowed: %s', ZC_CHARTS_TEXT_DOMAIN),
                    $atts['library'],
                    implode(', ', $allowed_libraries)
                );
            }
        }
        
        // Timeframe validation
        if (isset($atts['timeframe'])) {
            $allowed_timeframes = ['3m', '6m', '1y', '2y', '3y', '5y', '10y', '15y', '20y', '25y', 'all'];
            if (!in_array($atts['timeframe'], $allowed_timeframes)) {
                $errors[] = sprintf(
                    __('Invalid timeframe "%s". Allowed: %s', ZC_CHARTS_TEXT_DOMAIN),
                    $atts['timeframe'],
                    implode(', ', $allowed_timeframes)
                );
            }
        }
        
        // API key check
        $api_key_status = ZC_Charts_Security::get_api_key_status();
        if (!$api_key_status['valid']) {
            $errors[] = __('Invalid or missing API key', ZC_CHARTS_TEXT_DOMAIN);
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Get example shortcodes for documentation
     */
    public static function get_shortcode_examples() {
        return [
            'dynamic_basic' => [
                'code' => '[zc_chart_dynamic id="gdp_us"]',
                'description' => __('Basic dynamic chart with default settings', ZC_CHARTS_TEXT_DOMAIN)
            ],
            'dynamic_advanced' => [
                'code' => '[zc_chart_dynamic id="unemployment_rate" library="highcharts" timeframe="5y" height="500px" title="US Unemployment Rate"]',
                'description' => __('Advanced dynamic chart with custom settings', ZC_CHARTS_TEXT_DOMAIN)
            ],
            'static_basic' => [
                'code' => '[zc_chart_static id="inflation_rate"]',
                'description' => __('Basic static chart without interactive controls', ZC_CHARTS_TEXT_DOMAIN)
            ],
            'static_advanced' => [
                'code' => '[zc_chart_static id="population_growth" library="chartjs" height="300px" title="Population Growth" theme="dark"]',
                'description' => __('Advanced static chart with styling options', ZC_CHARTS_TEXT_DOMAIN)
            ]
        ];
    }
    
    /**
     * Health check for shortcode system
     */
    public static function health_check() {
        $issues = [];
        
        // Check API key
        $api_key_status = ZC_Charts_Security::get_api_key_status();
        if (!$api_key_status['valid']) {
            $issues[] = 'Invalid or missing API key';
        }
        
        // Check required JavaScript files
        $js_files = [
            'chart-loader.js' => ZC_CHARTS_PLUGIN_DIR . 'assets/js/chart-loader.js'
        ];
        
        foreach ($js_files as $name => $path) {
            if (!file_exists($path)) {
                $issues[] = "Missing JavaScript file: $name";
            }
        }
        
        // Check CSS files
        $css_files = [
            'charts.css' => ZC_CHARTS_PLUGIN_DIR . 'assets/css/charts.css'
        ];
        
        foreach ($css_files as $name => $path) {
            if (!file_exists($path)) {
                $issues[] = "Missing CSS file: $name";
            }
        }
        
        return [
            'healthy' => empty($issues),
            'issues' => $issues
        ];
    }
}

// Initialize shortcodes
ZC_Charts_Shortcodes::init();
