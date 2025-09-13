<?php
/**
 * ZC Charts Fallback Class
 * Handles backup data retrieval when live data fails
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class ZC_Charts_Fallback {
    
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
        // Initialize the class
        $this->init();
    }
    
    /**
     * Initialize the class
     */
    public function init() {
        // Initialization logic if needed
    }
    
    /**
     * Get backup data for an indicator
     */
    public static function get_backup_data($indicator_slug) {
        // Validate API key
        $api_key = get_option('zc_charts_api_key');
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', __('API key not configured.', 'zc-charts'));
        }
        
        // Validate key with DMT plugin
        if (!class_exists('ZC_Charts_Security')) {
            return new WP_Error('security_class_missing', __('Security class not found.', 'zc-charts'));
        }
        
        $security = new ZC_Charts_Security();
        $validation_result = $security->validate_api_key($api_key);
        
        if (!$validation_result) {
            return new WP_Error('invalid_api_key', __('Invalid API key.', 'zc-charts'));
        }
        
        // Construct backup URL to DMT plugin
        $backup_url = rest_url('zc-dmt/v1/backup/' . $indicator_slug);
        $backup_url = add_query_arg('access_key', $api_key, $backup_url);
        
        // Fetch backup data
        $response = wp_remote_get($backup_url, array(
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error('network_error', __('Unable to load backup data. Check connection.', 'zc-charts'));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            return new WP_Error('backup_not_found', __('Backup data not available.', 'zc-charts'));
        }
        
        $data = json_decode($body, true);
        
        if (!isset($data['data']) || !is_array($data['data'])) {
            return new WP_Error('invalid_backup_data', __('Invalid backup data format.', 'zc-charts'));
        }
        
        return $data;
    }
    
    /**
     * Check if backup data is available for an indicator
     */
    public static function is_backup_available($indicator_slug) {
        // Validate API key
        $api_key = get_option('zc_charts_api_key');
        if (empty($api_key)) {
            return false;
        }
        
        // Validate key with DMT plugin
        if (!class_exists('ZC_Charts_Security')) {
            return false;
        }
        
        $security = new ZC_Charts_Security();
        $validation_result = $security->validate_api_key($api_key);
        
        if (!$validation_result) {
            return false;
        }
        
        // Construct backup check URL to DMT plugin
        $backup_check_url = rest_url('zc-dmt/v1/backup/' . $indicator_slug . '/check');
        $backup_check_url = add_query_arg('access_key', $api_key, $backup_check_url);
        
        // Check if backup exists
        $response = wp_remote_get($backup_check_url, array(
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
    public static function get_last_backup_timestamp($indicator_slug) {
        // Validate API key
        $api_key = get_option('zc_charts_api_key');
        if (empty($api_key)) {
            return false;
        }
        
        // Validate key with DMT plugin
        if (!class_exists('ZC_Charts_Security')) {
            return false;
        }
        
        $security = new ZC_Charts_Security();
        $validation_result = $security->validate_api_key($api_key);
        
        if (!$validation_result) {
            return false;
        }
        
        // Construct backup info URL to DMT plugin
        $backup_info_url = rest_url('zc-dmt/v1/backup/' . $indicator_slug . '/info');
        $backup_info_url = add_query_arg('access_key', $api_key, $backup_info_url);
        
        // Get backup info
        $response = wp_remote_get($backup_info_url, array(
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
     * Handle fallback when live data fails
     */
    public static function handle_fallback($indicator_slug, $error_message = '') {
        // Log the fallback attempt
        if (class_exists('ZC_Charts_Security')) {
            $security = new ZC_Charts_Security();
            $security->log_security_event('data_fallback', array(
                'indicator_slug' => $indicator_slug,
                'error_message' => $error_message,
                'timestamp' => current_time('mysql')
            ));
        }
        
        // Try to get backup data
        $backup_data = self::get_backup_data($indicator_slug);
        
        if (is_wp_error($backup_data)) {
            // Log the failure to get backup data
            if (class_exists('ZC_Charts_Security')) {
                $security = new ZC_Charts_Security();
                $security->log_security_event('backup_failed', array(
                    'indicator_slug' => $indicator_slug,
                    'error_message' => $backup_data->get_error_message(),
                    'timestamp' => current_time('mysql')
                ));
            }
            
            return $backup_data; // Return the error
        }
        
        // Log successful fallback
        if (class_exists('ZC_Charts_Security')) {
            $security = new ZC_Charts_Security();
            $security->log_security_event('fallback_success', array(
                'indicator_slug' => $indicator_slug,
                'data_points' => count($backup_data['data']),
                'timestamp' => current_time('mysql')
            ));
        }
        
        return $backup_data;
    }
    
    /**
     * Get fallback notice message
     */
    public static function get_fallback_notice() {
        return __('Displaying cached data', 'zc-charts');
    }
    
    /**
     * Check if we should show fallback notice
     */
    public static function should_show_fallback_notice() {
        // Always show notice when using fallback data
        return true;
    }
}