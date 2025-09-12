<?php
/**
 * API Client class for ZC Charts plugin
 * Handles communication with ZC DMT plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZC_Charts_API_Client {
    
    /**
     * DMT plugin base URL
     */
    private static $dmt_base_url = null;
    
    /**
     * API endpoints
     */
    const VALIDATE_KEY_ENDPOINT = '/zc-dmt/v1/validate-key';
    const INDICATORS_ENDPOINT = '/zc-dmt/v1/indicators';
    const DATA_ENDPOINT = '/zc-dmt/v1/data';
    const BACKUP_ENDPOINT = '/zc-dmt/v1/backup';
    
    /**
     * Request timeout in seconds
     */
    const REQUEST_TIMEOUT = 30;
    
    /**
     * Initialize API client
     */
    public static function init() {
        self::$dmt_base_url = rest_url();
        
        // Hook into WordPress to handle API responses
        add_action('wp_ajax_zc_charts_test_connection', [__CLASS__, 'ajax_test_connection']);
        add_action('wp_ajax_zc_charts_refresh_indicators', [__CLASS__, 'ajax_refresh_indicators']);
    }
    
    /**
     * Test connection to DMT plugin
     */
    public static function test_connection($api_key = null) {
        if (!$api_key) {
            $api_key = ZC_Charts_Security::get_stored_api_key();
        }
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('No API key provided', ZC_CHARTS_TEXT_DOMAIN),
                'error_type' => 'no_key'
            ];
        }
        
        // Check if DMT plugin is active first
        if (!self::is_dmt_plugin_active()) {
            return [
                'success' => false,
                'message' => __('ZC DMT plugin is not active', ZC_CHARTS_TEXT_DOMAIN),
                'error_type' => 'plugin_inactive'
            ];
        }
        
        $url = self::$dmt_base_url . self::VALIDATE_KEY_ENDPOINT;
        
        $response = wp_remote_post($url, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $api_key
            ],
            'body' => json_encode([
                'api_key' => $api_key,
                'source' => 'zc_charts_test'
            ])
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => sprintf(__('Connection failed: %s', ZC_CHARTS_TEXT_DOMAIN), $response->get_error_message()),
                'error_type' => 'connection_error'
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['success']) && $data['success']) {
            return [
                'success' => true,
                'message' => __('Connection successful', ZC_CHARTS_TEXT_DOMAIN),
                'data' => $data['data'] ?? [],
                'indicators_count' => $data['data']['indicators_count'] ?? 0
            ];
        } else {
            $error_message = $data['message'] ?? __('Invalid API key', ZC_CHARTS_TEXT_DOMAIN);
            
            return [
                'success' => false,
                'message' => $error_message,
                'error_type' => $status_code === 401 ? 'invalid_key' : 'api_error',
                'status_code' => $status_code
            ];
        }
    }
    
    /**
     * Validate API key
     */
    public static function validate_api_key($api_key) {
        $result = self::test_connection($api_key);
        return $result['success'];
    }
    
    /**
     * Get available indicators from DMT
     */
    public static function get_indicators($api_key = null) {
        if (!$api_key) {
            $api_key = ZC_Charts_Security::get_stored_api_key();
        }
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('No API key configured', ZC_CHARTS_TEXT_DOMAIN)
            ];
        }
        
        // Check cache first
        $cache_key = 'zc_charts_indicators_' . md5($api_key);
        $cached_indicators = get_transient($cache_key);
        
        if ($cached_indicators !== false) {
            return [
                'success' => true,
                'data' => $cached_indicators,
                'from_cache' => true
            ];
        }
        
        $url = self::$dmt_base_url . self::INDICATORS_ENDPOINT;
        
        $response = wp_remote_get($url, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'X-API-Key' => $api_key
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => sprintf(__('Failed to fetch indicators: %s', ZC_CHARTS_TEXT_DOMAIN), $response->get_error_message())
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['success']) && $data['success']) {
            $indicators = $data['data'] ?? [];
            
            // Cache for 5 minutes
            set_transient($cache_key, $indicators, 5 * MINUTE_IN_SECONDS);
            
            return [
                'success' => true,
                'data' => $indicators,
                'from_cache' => false
            ];
        } else {
            return [
                'success' => false,
                'message' => $data['message'] ?? __('Failed to fetch indicators', ZC_CHARTS_TEXT_DOMAIN),
                'status_code' => $status_code
            ];
        }
    }
    
    /**
     * Get chart data for specific indicator
     */
    public static function get_chart_data($indicator_slug, $params = []) {
        $api_key = ZC_Charts_Security::get_stored_api_key();
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('No API key configured', ZC_CHARTS_TEXT_DOMAIN),
                'error_type' => 'no_key'
            ];
        }
        
        // Build cache key
        $cache_params = array_merge(['slug' => $indicator_slug], $params);
        $cache_key = 'zc_charts_data_' . md5($api_key . serialize($cache_params));
        
        // Check cache first
        $cache_duration = get_option('zc_charts_cache_duration', 300); // 5 minutes default
        $cached_data = get_transient($cache_key);
        
        if ($cached_data !== false) {
            return [
                'success' => true,
                'data' => $cached_data,
                'from_cache' => true
            ];
        }
        
        // Validate with rate limiting
        $rate_limit_result = ZC_Charts_Security::validate_api_key_with_rate_limit($api_key, $indicator_slug);
        if (!$rate_limit_result['success']) {
            return $rate_limit_result;
        }
        
        $url = self::$dmt_base_url . self::DATA_ENDPOINT . '/' . urlencode($indicator_slug);
        
        // Add query parameters
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $response = wp_remote_get($url, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'X-API-Key' => $api_key,
                'User-Agent' => 'ZC-Charts/' . ZC_CHARTS_VERSION
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => sprintf(__('Data request failed: %s', ZC_CHARTS_TEXT_DOMAIN), $response->get_error_message()),
                'error_type' => 'connection_error'
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Handle different status codes
        switch ($status_code) {
            case 200:
                $data = json_decode($body, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return [
                        'success' => false,
                        'message' => __('Invalid JSON response from server', ZC_CHARTS_TEXT_DOMAIN),
                        'error_type' => 'json_error'
                    ];
                }
                
                if (isset($data['success']) && $data['success']) {
                    $chart_data = $data['data'] ?? [];
                    
                    // Validate chart data structure
                    if (!self::validate_chart_data($chart_data)) {
                        return [
                            'success' => false,
                            'message' => __('Invalid chart data structure', ZC_CHARTS_TEXT_DOMAIN),
                            'error_type' => 'invalid_data'
                        ];
                    }
                    
                    // Cache the data
                    set_transient($cache_key, $chart_data, $cache_duration);
                    
                    return [
                        'success' => true,
                        'data' => $chart_data,
                        'from_cache' => false
                    ];
                } else {
                    return [
                        'success' => false,
                        'message' => $data['message'] ?? __('Unknown error occurred', ZC_CHARTS_TEXT_DOMAIN),
                        'error_type' => 'api_error'
                    ];
                }
                
            case 401:
                return [
                    'success' => false,
                    'message' => __('Invalid or expired API key', ZC_CHARTS_TEXT_DOMAIN),
                    'error_type' => 'unauthorized'
                ];
                
            case 404:
                return [
                    'success' => false,
                    'message' => sprintf(__('Indicator "%s" not found', ZC_CHARTS_TEXT_DOMAIN), $indicator_slug),
                    'error_type' => 'not_found'
                ];
                
            case 429:
                return [
                    'success' => false,
                    'message' => __('Rate limit exceeded. Please try again later.', ZC_CHARTS_TEXT_DOMAIN),
                    'error_type' => 'rate_limit'
                ];
                
            default:
                return [
                    'success' => false,
                    'message' => sprintf(__('Server error (HTTP %d)', ZC_CHARTS_TEXT_DOMAIN), $status_code),
                    'error_type' => 'server_error',
                    'status_code' => $status_code
                ];
        }
    }
    
    /**
     * Get fallback/backup data
     */
    public static function get_fallback_data($indicator_slug, $params = []) {
        $api_key = ZC_Charts_Security::get_stored_api_key();
        
        if (empty($api_key)) {
            return [
                'success' => false,
                'message' => __('No API key configured', ZC_CHARTS_TEXT_DOMAIN)
            ];
        }
        
        $url = self::$dmt_base_url . self::BACKUP_ENDPOINT . '/' . urlencode($indicator_slug);
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $response = wp_remote_get($url, [
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => [
                'X-API-Key' => $api_key
            ]
        ]);
        
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => sprintf(__('Fallback data request failed: %s', ZC_CHARTS_TEXT_DOMAIN), $response->get_error_message())
            ];
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($status_code === 200 && isset($data['success']) && $data['success']) {
            return [
                'success' => true,
                'data' => $data['data'] ?? [],
                'is_fallback' => true
            ];
        } else {
            return [
                'success' => false,
                'message' => $data['message'] ?? __('Fallback data not available', ZC_CHARTS_TEXT_DOMAIN)
            ];
        }
    }
    
    /**
     * Check if DMT plugin is active
     */
    public static function is_dmt_plugin_active() {
        return is_plugin_active('zc-dmt/zc-dmt.php') || class_exists('ZC_DMT_Database');
    }
    
    /**
     * Get DMT plugin status and information
     */
    public static function get_dmt_status() {
        $status = [
            'active' => self::is_dmt_plugin_active(),
            'version' => null,
            'api_available' => false,
            'endpoints' => []
        ];
        
        if ($status['active']) {
            // Try to get version
            if (defined('ZC_DMT_VERSION')) {
                $status['version'] = ZC_DMT_VERSION;
            }
            
            // Check if REST API is available
            $status['api_available'] = function_exists('rest_url');
            
            if ($status['api_available']) {
                $status['endpoints'] = [
                    'validate' => rest_url(self::VALIDATE_KEY_ENDPOINT),
                    'indicators' => rest_url(self::INDICATORS_ENDPOINT),
                    'data' => rest_url(self::DATA_ENDPOINT),
                    'backup' => rest_url(self::BACKUP_ENDPOINT)
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * Validate chart data structure
     */
    private static function validate_chart_data($data) {
        if (!is_array($data)) {
            return false;
        }
        
        // Check required fields
        $required_fields = ['labels', 'data'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || !is_array($data[$field])) {
                return false;
            }
        }
        
        // Check that labels and data arrays have the same length
        if (count($data['labels']) !== count($data['data'])) {
            return false;
        }
        
        // Validate data points are numeric
        foreach ($data['data'] as $point) {
            if (!is_numeric($point)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * AJAX handler for testing connection
     */
    public static function ajax_test_connection() {
        check_ajax_referer('zc_charts_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(json_encode([
                'success' => false,
                'data' => __('Insufficient permissions', ZC_CHARTS_TEXT_DOMAIN)
            ]));
        }
        
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($api_key)) {
            wp_die(json_encode([
                'success' => false,
                'data' => __('No API key provided', ZC_CHARTS_TEXT_DOMAIN)
            ]));
        }
        
        $result = self::test_connection($api_key);
        
        wp_die(json_encode([
            'success' => $result['success'],
            'data' => $result
        ]));
    }
    
    /**
     * AJAX handler for refreshing indicators list
     */
    public static function ajax_refresh_indicators() {
        check_ajax_referer('zc_charts_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(json_encode([
                'success' => false,
                'data' => __('Insufficient permissions', ZC_CHARTS_TEXT_DOMAIN)
            ]));
        }
        
        // Clear cache first
        $api_key = ZC_Charts_Security::get_stored_api_key();
        $cache_key = 'zc_charts_indicators_' . md5($api_key);
        delete_transient($cache_key);
        
        $result = self::get_indicators();
        
        wp_die(json_encode([
            'success' => $result['success'],
            'data' => $result
        ]));
    }
    
    /**
     * Clear all API-related caches
     */
    public static function clear_cache() {
        global $wpdb;
        
        // Remove all ZC Charts transients
        $wpdb->query(
            "DELETE FROM $wpdb->options WHERE option_name LIKE '%transient%zc_charts_%'"
        );
        
        return true;
    }
    
    /**
     * Get API usage statistics
     */
    public static function get_api_stats() {
        $stats = get_transient('zc_charts_api_stats');
        
        if ($stats === false) {
            $stats = [
                'requests_today' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'cache_hits' => 0,
                'last_request_time' => null,
                'average_response_time' => 0
            ];
            
            set_transient('zc_charts_api_stats', $stats, DAY_IN_SECONDS);
        }
        
        return $stats;
    }
    
    /**
     * Update API usage statistics
     */
    public static function update_api_stats($success, $response_time = 0, $from_cache = false) {
        $stats = self::get_api_stats();
        
        $stats['requests_today']++;
        $stats['last_request_time'] = current_time('mysql');
        
        if ($success) {
            $stats['successful_requests']++;
        } else {
            $stats['failed_requests']++;
        }
        
        if ($from_cache) {
            $stats['cache_hits']++;
        }
        
        if ($response_time > 0) {
            $stats['average_response_time'] = ($stats['average_response_time'] + $response_time) / 2;
        }
        
        set_transient('zc_charts_api_stats', $stats, DAY_IN_SECONDS);
    }
    
    /**
     * Health check for DMT connection
     */
    public static function health_check() {
        $start_time = microtime(true);
        $result = self::test_connection();
        $response_time = microtime(true) - $start_time;
        
        self::update_api_stats($result['success'], $response_time);
        
        return array_merge($result, [
            'response_time' => round($response_time * 1000, 2) // in milliseconds
        ]);
    }
}

// Initialize the API client
ZC_Charts_API_Client::init();
