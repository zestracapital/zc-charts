<?php
/**
 * ZC Charts API Client
 * Handles communication with the ZC DMT plugin REST API
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_Charts_API_Client {
    
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
        // Initialization logic if needed
    }
    
    /**
     * Get API key from settings
     */
    public function get_api_key() {
        return get_option('zc_charts_api_key', '');
    }
    
    /**
     * Validate API key with DMT plugin
     */
    public function validate_api_key($api_key = null) {
        if (is_null($api_key)) {
            $api_key = $this->get_api_key();
        }
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key not configured.', 'zc-charts'));
        }
        
        $validation_url = rest_url('zc-dmt/v1/validate-key');
        
        $response = wp_remote_post($validation_url, array(
            'body' => array(
                'access_key' => $api_key
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('network_error', __('Unable to validate API key. Check connection.', 'zc-charts'));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($response_code !== 200) {
            return new WP_Error('validation_failed', __('API key validation failed.', 'zc-charts'));
        }
        
        if (!isset($data['valid']) || !$data['valid']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Fetch chart data from DMT plugin
     */
    public function fetch_chart_data($indicator_slug, $api_key = null) {
        if (is_null($api_key)) {
            $api_key = $this->get_api_key();
        }
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key not configured.', 'zc-charts'));
        }
        
        $data_url = rest_url('zc-dmt/v1/data/' . $indicator_slug);
        $data_url = add_query_arg('access_key', $api_key, $data_url);
        
        $response = wp_remote_get($data_url, array(
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
    public function fetch_fallback_data($indicator_slug, $api_key = null) {
        if (is_null($api_key)) {
            $api_key = $this->get_api_key();
        }
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key not configured.', 'zc-charts'));
        }
        
        $backup_url = rest_url('zc-dmt/v1/backup/' . $indicator_slug);
        $backup_url = add_query_arg('access_key', $api_key, $backup_url);
        
        $response = wp_remote_get($backup_url, array(
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('backup_network_error', __('Unable to load backup data. Check connection.', 'zc-charts'));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('backup_data_not_found', __('Backup data not available.', 'zc-charts'));
        }
        
        $data = json_decode($body, true);
        return $data;
    }
    
    /**
     * Check if backup data is available for an indicator
     */
    public function is_backup_available($indicator_slug, $api_key = null) {
        if (is_null($api_key)) {
            $api_key = $this->get_api_key();
        }
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key not configured.', 'zc-charts'));
        }
        
        $check_url = rest_url('zc-dmt/v1/backup/' . $indicator_slug . '/check');
        $check_url = add_query_arg('access_key', $api_key, $check_url);
        
        $response = wp_remote_get($check_url, array(
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return ($response_code === 200 && isset($data['available']) && $data['available']);
    }
    
    /**
     * Get the last backup timestamp for an indicator
     */
    public function get_last_backup_timestamp($indicator_slug, $api_key = null) {
        if (is_null($api_key)) {
            $api_key = $this->get_api_key();
        }
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key not configured.', 'zc-charts'));
        }
        
        $info_url = rest_url('zc-dmt/v1/backup/' . $indicator_slug . '/info');
        $info_url = add_query_arg('access_key', $api_key, $info_url);
        
        $response = wp_remote_get($info_url, array(
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($response_code === 200 && isset($data['last_backup'])) {
            return $data['last_backup'];
        }
        
        return false;
    }
    
    /**
     * Test connection to DMT plugin
     */
    public function test_connection($api_key = null) {
        if (is_null($api_key)) {
            $api_key = $this->get_api_key();
        }
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key not configured.', 'zc-charts'));
        }
        
        // Validate API key
        $validation_result = $this->validate_api_key($api_key);
        
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        if (!$validation_result) {
            return new WP_Error('invalid_api_key', __('Invalid API key.', 'zc-charts'));
        }
        
        // Try to fetch a simple endpoint to test connectivity
        $test_url = rest_url('zc-dmt/v1/indicators');
        $test_url = add_query_arg('access_key', $api_key, $test_url);
        
        $response = wp_remote_get($test_url, array(
            'timeout' => 10
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('connection_failed', __('Connection to ZC DMT failed. Check network.', 'zc-charts'));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            return true;
        } elseif ($response_code === 401) {
            return new WP_Error('unauthorized', __('Unauthorized access. Check API key configuration.', 'zc-charts'));
        } else {
            return new WP_Error('connection_error', sprintf(__('Connection error: HTTP %d', 'zc-charts'), $response_code));
        }
    }
    
    /**
     * Get list of available indicators
     */
    public function get_indicators($api_key = null) {
        if (is_null($api_key)) {
            $api_key = $this->get_api_key();
        }
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key not configured.', 'zc-charts'));
        }
        
        $indicators_url = rest_url('zc-dmt/v1/indicators');
        $indicators_url = add_query_arg('access_key', $api_key, $indicators_url);
        
        $response = wp_remote_get($indicators_url, array(
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('network_error', __('Unable to load indicators. Check connection.', 'zc-charts'));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('indicators_not_found', __('Indicators not available.', 'zc-charts'));
        }
        
        $data = json_decode($body, true);
        return $data;
    }
    
    /**
     * Get list of available calculations
     */
    public function get_calculations($api_key = null) {
        if (is_null($api_key)) {
            $api_key = $this->get_api_key();
        }
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key not configured.', 'zc-charts'));
        }
        
        $calculations_url = rest_url('zc-dmt/v1/calculations');
        $calculations_url = add_query_arg('access_key', $api_key, $calculations_url);
        
        $response = wp_remote_get($calculations_url, array(
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('network_error', __('Unable to load calculations. Check connection.', 'zc-charts'));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('calculations_not_found', __('Calculations not available.', 'zc-charts'));
        }
        
        $data = json_decode($body, true);
        return $data;
    }
}