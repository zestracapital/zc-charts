<?php
/**
 * ZC Charts Shortcode Handlers
 * Manages all chart shortcodes and their rendering
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_Charts_Shortcodes {
    
    /**
     * Instance of the class
     */
    private static $instance = null;
    
    /**
     * Get instance of the class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the class
     */
    public function init() {
        // Register shortcodes
        add_action('init', array($this, 'register_shortcodes'));
    }
    
    /**
     * Register all chart shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('zc_chart_dynamic', array($this, 'render_dynamic_chart'));
        add_shortcode('zc_chart_static', array($this, 'render_static_chart'));
    }
    
    /**
     * Render dynamic chart shortcode
     */
    public function render_dynamic_chart($atts) {
        // Normalize attributes
        $atts = shortcode_atts(array(
            'id' => '',
            'library' => get_option('zc_charts_default_library', 'chartjs'),
            'timeframe' => '1y',
            'height' => '400px'
        ), $atts, 'zc_chart_dynamic');
        
        // Validate required attributes
        if (empty($atts['id'])) {
            return $this->render_error(__('Indicator ID is required.', 'zc-charts'));
        }
        
        // Validate API key
        $api_key = get_option('zc_charts_api_key');
        if (empty($api_key)) {
            return $this->render_error(__('API key not configured. Please set it in Settings > ZC Charts.', 'zc-charts'));
        }
        
        // Validate API key with DMT plugin
        if (!class_exists('ZC_Charts_Security')) {
            return $this->render_error(__('Security class not found.', 'zc-charts'));
        }
        
        $security = new ZC_Charts_Security();
        $validation_result = $security->validate_api_key($api_key);
        
        if (!$validation_result) {
            return $this->render_error(__('Invalid API key. Please check your configuration.', 'zc-charts'));
        }
        
        // Fetch data from DMT plugin
        $data = $this->fetch_chart_data($atts['id'], $api_key);
        
        if (is_wp_error($data)) {
            // Try fallback data
            $fallback_data = $this->fetch_fallback_data($atts['id'], $api_key);
            
            if (is_wp_error($fallback_data)) {
                return $this->render_error($data->get_error_message());
            }
            
            $data = $fallback_data;
            // Add fallback notice
            $fallback_notice = '<div class="zc-chart-notice">' . __('Displaying cached data', 'zc-charts') . '</div>';
        } else {
            $fallback_notice = '';
        }
        
        // Generate unique chart ID
        $chart_id = 'zc-chart-' . uniqid();
        
        // Enqueue chart library
        $this->enqueue_chart_library($atts['library']);
        
        // Prepare data for chart
        $chart_data = $this->prepare_chart_data($data, $atts['timeframe']);
        
        // Return chart container with fallback notice
        $output = $fallback_notice;
        $output .= '<div class="zc-chart-wrapper" style="height: ' . esc_attr($atts['height']) . '">';
        $output .= '<div class="zc-chart-container" id="' . esc_attr($chart_id) . '" data-config=\'' . json_encode(array(
            'slug' => $atts['id'],
            'library' => $atts['library'],
            'timeframe' => $atts['timeframe'],
            'height' => $atts['height']
        )) . '\'></div>';
        $output .= '</div>';
        
        // Add chart initialization script
        $output .= '<script type="text/javascript">';
        $output .= 'document.addEventListener("DOMContentLoaded", function() {';
        $output .= 'if (typeof window.zcChartLoader !== "undefined") {';
        $output .= 'window.zcChartLoader.loadChart(document.getElementById("' . esc_js($chart_id) . '"), ' . json_encode(array(
            'slug' => $atts['id'],
            'library' => $atts['library'],
            'timeframe' => $atts['timeframe'],
            'height' => $atts['height']
        )) . ');';
        $output .= '} else {';
        $output .= 'console.error("ZC Chart Loader not found");';
        $output .= '}';
        $output .= '});';
        $output .= '</script>';
        
        return $output;
    }
    
    /**
     * Render static chart shortcode
     */
    public function render_static_chart($atts) {
        // Normalize attributes
        $atts = shortcode_atts(array(
            'id' => '',
            'library' => get_option('zc_charts_default_library', 'chartjs')
        ), $atts, 'zc_chart_static');
        
        // Validate required attributes
        if (empty($atts['id'])) {
            return $this->render_error(__('Indicator ID is required.', 'zc-charts'));
        }
        
        // Validate API key
        $api_key = get_option('zc_charts_api_key');
        if (empty($api_key)) {
            return $this->render_error(__('API key not configured. Please set it in Settings > ZC Charts.', 'zc-charts'));
        }
        
        // Validate API key with DMT plugin
        if (!class_exists('ZC_Charts_Security')) {
            return $this->render_error(__('Security class not found.', 'zc-charts'));
        }
        
        $security = new ZC_Charts_Security();
        $validation_result = $security->validate_api_key($api_key);
        
        if (!$validation_result) {
            return $this->render_error(__('Invalid API key. Please check your configuration.', 'zc-charts'));
        }
        
        // Fetch data from DMT plugin
        $data = $this->fetch_chart_data($atts['id'], $api_key);
        
        if (is_wp_error($data)) {
            // Try fallback data
            $fallback_data = $this->fetch_fallback_data($atts['id'], $api_key);
            
            if (is_wp_error($fallback_data)) {
                return $this->render_error($data->get_error_message());
            }
            
            $data = $fallback_data;
            // Add fallback notice
            $fallback_notice = '<div class="zc-chart-notice">' . __('Displaying cached data', 'zc-charts') . '</div>';
        } else {
            $fallback_notice = '';
        }
        
        // Generate unique chart ID
        $chart_id = 'zc-chart-' . uniqid();
        
        // Enqueue chart library
        $this->enqueue_chart_library($atts['library']);
        
        // Prepare data for chart (no timeframe filter for static)
        $chart_data = $this->prepare_chart_data($data);
        
        // Return chart container with fallback notice
        $output = $fallback_notice;
        $output .= '<div class="zc-chart-wrapper">';
        $output .= '<div class="zc-chart-container" id="' . esc_attr($chart_id) . '" data-config=\'' . json_encode(array(
            'slug' => $atts['id'],
            'library' => $atts['library']
        )) . '\'></div>';
        $output .= '</div>';
        
        // Add chart initialization script
        $output .= '<script type="text/javascript">';
        $output .= 'document.addEventListener("DOMContentLoaded", function() {';
        $output .= 'if (typeof window.zcChartLoader !== "undefined") {';
        $output .= 'window.zcChartLoader.loadChart(document.getElementById("' . esc_js($chart_id) . '"), ' . json_encode(array(
            'slug' => $atts['id'],
            'library' => $atts['library']
        )) . ');';
        $output .= '} else {';
        $output .= 'console.error("ZC Chart Loader not found");';
        $output .= '}';
        $output .= '});';
        $output .= '</script>';
        
        return $output;
    }
    
    /**
     * Fetch chart data from DMT plugin
     */
    private function fetch_chart_data($indicator_slug, $api_key) {
        $dmt_api_url = rest_url('zc-dmt/v1/data/' . $indicator_slug);
        $dmt_api_url = add_query_arg('access_key', $api_key, $dmt_api_url);
        
        $response = wp_remote_get($dmt_api_url, array(
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('network_error', __('Unable to load data. Check connection.', 'zc-charts'));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('data_not_found', __('Requested indicator data not available.', 'zc-charts'));
        }
        
        $data = json_decode($body, true);
        return $data;
    }
    
    /**
     * Fetch fallback data from DMT plugin
     */
    private function fetch_fallback_data($indicator_slug, $api_key) {
        if (!class_exists('ZC_Charts_Fallback')) {
            return new WP_Error('fallback_class_missing', __('Fallback class not found.', 'zc-charts'));
        }
        
        $fallback = new ZC_Charts_Fallback();
        return $fallback->get_backup_data($indicator_slug);
    }
    
    /**
     * Prepare chart data
     */
    private function prepare_chart_data($raw_data, $timeframe = null) {
        $labels = array();
        $values = array();
        
        if (isset($raw_data['data']) && is_array($raw_data['data'])) {
            // Apply timeframe filter if specified
            if ($timeframe && $timeframe !== 'all') {
                $raw_data['data'] = $this->filter_data_by_timeframe($raw_data['data'], $timeframe);
            }
            
            foreach ($raw_data['data'] as $point) {
                if (isset($point['obs_date']) && isset($point['value'])) {
                    $labels[] = $point['obs_date'];
                    $values[] = (float)$point['value'];
                }
            }
        }
        
        return array(
            'labels' => $labels,
            'datasets' => array(
                array(
                    'label' => isset($raw_data['indicator']['name']) ? $raw_data['indicator']['name'] : 'Indicator',
                    'data' => $values,
                    'borderColor' => '#0073aa',
                    'backgroundColor' => 'rgba(0, 115, 170, 0.1)',
                    'fill' => false,
                    'tension' => 0.4
                )
            )
        );
    }
    
    /**
     * Filter data by timeframe
     */
    private function filter_data_by_timeframe($data, $timeframe) {
        if (empty($data)) {
            return $data;
        }
        
        // Determine cutoff date based on timeframe
        $cutoff_date = new DateTime();
        switch ($timeframe) {
            case '3m':
                $cutoff_date->modify('-3 months');
                break;
            case '6m':
                $cutoff_date->modify('-6 months');
                break;
            case '1y':
                $cutoff_date->modify('-1 year');
                break;
            case '2y':
                $cutoff_date->modify('-2 years');
                break;
            case '3y':
                $cutoff_date->modify('-3 years');
                break;
            case '5y':
                $cutoff_date->modify('-5 years');
                break;
            case '10y':
                $cutoff_date->modify('-10 years');
                break;
            case '15y':
                $cutoff_date->modify('-15 years');
                break;
            case '20y':
                $cutoff_date->modify('-20 years');
                break;
            case '25y':
                $cutoff_date->modify('-25 years');
                break;
            default:
                return $data; // 'all' or unknown timeframe
        }
        
        $filtered_data = array();
        foreach ($data as $point) {
            if (isset($point['obs_date'])) {
                $point_date = new DateTime($point['obs_date']);
                if ($point_date >= $cutoff_date) {
                    $filtered_data[] = $point;
                }
            }
        }
        
        return $filtered_data;
    }
    
    /**
     * Enqueue chart library
     */
    private function enqueue_chart_library($library) {
        if ($library === 'chartjs' && !wp_script_is('chartjs', 'enqueued')) {
            wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        } elseif ($library === 'highcharts' && !wp_script_is('highcharts', 'enqueued')) {
            wp_enqueue_script('highcharts', 'https://code.highcharts.com/highcharts.js', array(), '10.3.3', true);
        }
        
        // Enqueue our chart scripts
        wp_enqueue_script('zc-charts-loader', ZC_CHARTS_PLUGIN_URL . 'assets/js/chart-loader.js', array(), ZC_CHARTS_VERSION, true);
        wp_enqueue_script('zc-charts-chartjs-handler', ZC_CHARTS_PLUGIN_URL . 'assets/js/chartjs-handler.js', array('zc-charts-loader'), ZC_CHARTS_VERSION, true);
        wp_enqueue_script('zc-charts-highcharts-handler', ZC_CHARTS_PLUGIN_URL . 'assets/js/highcharts-handler.js', array('zc-charts-loader'), ZC_CHARTS_VERSION, true);
        wp_enqueue_script('zc-charts-fallback-handler', ZC_CHARTS_PLUGIN_URL . 'assets/js/fallback-handler.js', array('zc-charts-loader'), ZC_CHARTS_VERSION, true);
        
        // Localize script with configuration
        wp_localize_script('zc-charts-loader', 'zcChartsConfig', array(
            'restUrl' => rest_url(),
            'apiKey' => get_option('zc_charts_api_key'),
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ));
        
        // Enqueue styles
        wp_enqueue_style('zc-charts-css', ZC_CHARTS_PLUGIN_URL . 'assets/css/public.css', array(), ZC_CHARTS_VERSION);
        wp_enqueue_style('zc-charts-charts-css', ZC_CHARTS_PLUGIN_URL . 'assets/css/charts.css', array(), ZC_CHARTS_VERSION);
    }
    
    /**
     * Render error message
     */
    private function render_error($message) {
        return '<div class="zc-chart-error">' . esc_html($message) . '</div>';
    }
}