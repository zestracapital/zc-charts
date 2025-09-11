<?php
/**
 * Security Class for ZC Charts Plugin
 * Complete API key validation and rate limiting system
 */

if (!defined('ABSPATH')) {
    exit;
}

class ZC_Charts_Security {
    
    /**
     * Option keys for secure storage
     */
    const API_KEY_OPTION = 'zc_charts_api_key';
    const API_KEY_HASH_OPTION = 'zc_charts_api_key_hash';
    const RATE_LIMIT_PREFIX = 'zc_charts_rate_limit_';
    
    /**
     * Rate limiting configuration
     */
    const RATE_LIMIT_REQUESTS = 100; // requests per hour per key
    const RATE_LIMIT_WINDOW = 3600; // 1 hour in seconds
    
    /**
     * Initialize security system
     */
    public static function init() {
        // Admin validation hooks
        add_action('admin_init', [__CLASS__, 'periodic_key_validation']);
        add_action('wp_ajax_zc_charts_validate_key', [__CLASS__, 'ajax_validate_key']);
        add_action('wp_ajax_zc_charts_test_connection', [__CLASS__, 'ajax_test_connection']);
        
        // Frontend security hooks
        add_action('init', [__CLASS__, 'setup_rate_limiting']);
        
        // Cleanup scheduled task
        if (!wp_next_scheduled('zc_charts_cleanup_security')) {
            wp_schedule_event(time(), 'daily', 'zc_charts_cleanup_security');
        }
        add_action('zc_charts_cleanup_security', [__CLASS__, 'cleanup_security_data']);
    }
    
    /**
     * Store API key with encryption
     */
    public static function store_api_key($api_key) {
        if (empty($api_key)) {
            return false;
        }
        
        // Validate API key format (ZC DMT format: zc_xxxxxx...)
        if (!self::validate_key_format($api_key)) {
            self::log_security_event('invalid_key_format', 'Attempted to store invalid API key format');
            return false;
        }
        
        // Encrypt the key for secure storage
        $encrypted_key = self::encrypt_key($api_key);
        
        // Store encrypted key and hash
        $stored = update_option(self::API_KEY_OPTION, $encrypted_key);
        update_option(self::API_KEY_HASH_OPTION, wp_hash($api_key));
        
        // Clear any cached validation results
        self::clear_validation_cache($api_key);
        
        if ($stored) {
            self::log_security_event('api_key_stored', 'API key stored successfully');
        }
        
        return $stored;
    }
    
    /**
     * Get stored API key (decrypted)
     */
    public static function get_stored_api_key() {
        $encrypted_key = get_option(self::API_KEY_OPTION);
        
        if (empty($encrypted_key)) {
            return '';
        }
        
        $decrypted_key = self::decrypt_key($encrypted_key);
        
        // Validate decrypted key format
        if (!self::validate_key_format($decrypted_key)) {
            // Key corruption detected, remove it
            self::remove_api_key();
            return '';
        }
        
        return $decrypted_key;
    }
    
    /**
     * Remove API key from storage
     */
    public static function remove_api_key() {
        $api_key = self::get_stored_api_key();
        
        delete_option(self::API_KEY_OPTION);
        delete_option(self::API_KEY_HASH_OPTION);
        
        if (!empty($api_key)) {
            self::clear_validation_cache($api_key);
            self::clear_rate_limit_data($api_key);
        }
        
        self::log_security_event('api_key_removed', 'API key removed from storage');
        
        return true;
    }
    
    /**
     * Validate API key format (ZC DMT format)
     */
    public static function validate_key_format($api_key) {
        // ZC DMT keys: 'zc_' prefix + 29 hexadecimal characters = 32 total
        return preg_match('/^zc_[a-f0-9]{29}$/', $api_key) === 1;
    }
    
    /**
     * Validate API key with DMT plugin
     */
    public static function validate_api_key($api_key = null, $force_check = false) {
        if ($api_key === null) {
            $api_key = self::get_stored_api_key();
        }
        
        if (empty($api_key)) {
            return false;
        }
        
        // Check format first
        if (!self::validate_key_format($api_key)) {
            return false;
        }
        
        // Check cache for recent validation (unless forced)
        if (!$force_check) {
            $cache_result = self::get_cached_validation($api_key);
            if ($cache_result !== null) {
                return $cache_result;
            }
        }
        
        // Validate with DMT plugin via API client
        $validation_result = self::validate_with_dmt($api_key);
        
        // Cache the result
        self::cache_validation_result($api_key, $validation_result);
        
        // Log validation attempt
        self::log_security_event(
            $validation_result ? 'key_validation_success' : 'key_validation_failed',
            $validation_result ? 'API key validated successfully' : 'API key validation failed',
            ['key_preview' => self::get_key_preview($api_key)]
        );
        
        return $validation_result;
    }
    
    /**
     * Validate API key with rate limiting
     */
    public static function validate_api_key_with_rate_limit($api_key, $context = '') {
        // First check if key is valid
        if (!self::validate_api_key($api_key)) {
            return [
                'success' => false,
                'message' => __('Invalid API key', ZC_CHARTS_TEXT_DOMAIN),
                'error_type' => 'invalid_key'
            ];
        }
        
        // Check rate limiting
        $rate_check = self::check_rate_limit($api_key, $context);
        
        if (!$rate_check['allowed']) {
            self::log_security_event('rate_limit_exceeded', 'Rate limit exceeded', [
                'key_preview' => self::get_key_preview($api_key),
                'context' => $context,
                'requests_made' => $rate_check['requests_made']
            ]);
            
            return [
                'success' => false,
                'message' => sprintf(
                    __('Rate limit exceeded. Try again in %d minutes.', ZC_CHARTS_TEXT_DOMAIN),
                    ceil($rate_check['retry_after'] / 60)
                ),
                'error_type' => 'rate_limit',
                'retry_after' => $rate_check['retry_after']
            ];
        }
        
        // Record this request
        self::record_api_request($api_key, $context);
        
        return [
            'success' => true,
            'message' => __('API key validated successfully', ZC_CHARTS_TEXT_DOMAIN),
            'requests_remaining' => $rate_check['requests_remaining']
        ];
    }
    
    /**
     * Get API key status information
     */
    public static function get_api_key_status() {
        $api_key = self::get_stored_api_key();
        
        $status = [
            'configured' => !empty($api_key),
            'valid' => false,
            'message' => '',
            'key_preview' => '',
            'last_validated' => get_option('zc_charts_last_validation_time', 'Never'),
            'validation_status' => get_option('zc_charts_validation_status', 'unknown')
        ];
        
        if (empty($api_key)) {
            $status['message'] = __('No API key configured', ZC_CHARTS_TEXT_DOMAIN);
            return $status;
        }
        
        $status['key_preview'] = self::get_key_preview($api_key);
        
        if (!self::validate_key_format($api_key)) {
            $status['message'] = __('API key has invalid format', ZC_CHARTS_TEXT_DOMAIN);
            return $status;
        }
        
        // Check validation status
        $is_valid = self::validate_api_key($api_key);
        $status['valid'] = $is_valid;
        $status['message'] = $is_valid ? 
            __('API key is valid and active', ZC_CHARTS_TEXT_DOMAIN) : 
            __('API key validation failed', ZC_CHARTS_TEXT_DOMAIN);
        
        return $status;
    }
    
    /**
     * Get API key preview (first 8 + last 4 characters)
     */
    public static function get_key_preview($api_key) {
        if (strlen($api_key) < 12) {
            return str_repeat('*', strlen($api_key));
        }
        
        return substr($api_key, 0, 8) . '...' . substr($api_key, -4);
    }
    
    /**
     * Validate with DMT plugin
     */
    private static function validate_with_dmt($api_key) {
        // Check if DMT plugin is active
        if (!class_exists('ZC_DMT_Security')) {
            return false;
        }
        
        // Use API client for validation
        if (!class_exists('ZC_Charts_API_Client')) {
            require_once ZC_CHARTS_PLUGIN_DIR . 'includes/class-api-client.php';
        }
        
        $test_result = ZC_Charts_API_Client::test_connection($api_key);
        return isset($test_result['success']) ? $test_result['success'] : false;
    }
    
    /**
     * Check rate limiting
     */
    private static function check_rate_limit($api_key, $context = '') {
        $rate_key = self::get_rate_limit_key($api_key, $context);
        $transient_key = self::RATE_LIMIT_PREFIX . wp_hash($rate_key);
        
        $current_count = get_transient($transient_key);
        
        if ($current_count === false) {
            // No previous requests in this window
            return [
                'allowed' => true,
                'requests_made' => 0,
                'requests_remaining' => self::RATE_LIMIT_REQUESTS,
                'window_reset' => time() + self::RATE_LIMIT_WINDOW,
                'retry_after' => 0
            ];
        }
        
        if ($current_count >= self::RATE_LIMIT_REQUESTS) {
            // Rate limit exceeded
            $ttl = self::get_transient_ttl($transient_key);
            return [
                'allowed' => false,
                'requests_made' => $current_count,
                'requests_remaining' => 0,
                'window_reset' => time() + $ttl,
                'retry_after' => $ttl
            ];
        }
        
        // Within limits
        return [
            'allowed' => true,
            'requests_made' => $current_count,
            'requests_remaining' => self::RATE_LIMIT_REQUESTS - $current_count,
            'window_reset' => time() + self::get_transient_ttl($transient_key),
            'retry_after' => 0
        ];
    }
    
    /**
     * Record API request
     */
    private static function record_api_request($api_key, $context = '') {
        $rate_key = self::get_rate_limit_key($api_key, $context);
        $transient_key = self::RATE_LIMIT_PREFIX . wp_hash($rate_key);
        
        $current_count = get_transient($transient_key);
        
        if ($current_count === false) {
            set_transient($transient_key, 1, self::RATE_LIMIT_WINDOW);
        } else {
            $ttl = max(1, self::get_transient_ttl($transient_key));
            set_transient($transient_key, $current_count + 1, $ttl);
        }
        
        // Update global request counter
        $total_requests = get_option('zc_charts_total_requests', 0);
        update_option('zc_charts_total_requests', $total_requests + 1);
        update_option('zc_charts_last_request_time', current_time('mysql'));
    }
    
    /**
     * Get rate limiting key
     */
    private static function get_rate_limit_key($api_key, $context = '') {
        $ip = self::get_client_ip();
        $user_id = get_current_user_id();
        
        return wp_hash($api_key . '_' . $ip . '_' . $user_id . '_' . $context);
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips);
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Encrypt API key for storage
     */
    private static function encrypt_key($api_key) {
        // Use WordPress authentication salts for encryption
        $salt = defined('AUTH_SALT') ? AUTH_SALT : 'zc_charts_fallback_salt';
        
        // Simple XOR encryption with salt
        $encrypted = '';
        $key_len = strlen($api_key);
        $salt_hash = wp_hash($salt);
        $salt_len = strlen($salt_hash);
        
        for ($i = 0; $i < $key_len; $i++) {
            $encrypted .= chr(ord($api_key[$i]) ^ ord($salt_hash[$i % $salt_len]));
        }
        
        // Base64 encode for storage
        return base64_encode($encrypted);
    }
    
    /**
     * Decrypt API key from storage
     */
    private static function decrypt_key($encrypted_key) {
        $encrypted = base64_decode($encrypted_key);
        
        if ($encrypted === false) {
            return '';
        }
        
        $salt = defined('AUTH_SALT') ? AUTH_SALT : 'zc_charts_fallback_salt';
        $salt_hash = wp_hash($salt);
        $salt_len = strlen($salt_hash);
        
        $decrypted = '';
        $key_len = strlen($encrypted);
        
        for ($i = 0; $i < $key_len; $i++) {
            $decrypted .= chr(ord($encrypted[$i]) ^ ord($salt_hash[$i % $salt_len]));
        }
        
        return $decrypted;
    }
    
    /**
     * Cache validation result
     */
    private static function cache_validation_result($api_key, $result) {
        $cache_key = 'zc_charts_validation_' . wp_hash($api_key);
        $cache_duration = $result ? 5 * MINUTE_IN_SECONDS : 1 * MINUTE_IN_SECONDS;
        
        set_transient($cache_key, $result ? 'valid' : 'invalid', $cache_duration);
        
        // Update persistent validation status
        update_option('zc_charts_validation_status', $result ? 'valid' : 'invalid');
        update_option('zc_charts_last_validation_time', current_time('mysql'));
    }
    
    /**
     * Get cached validation result
     */
    private static function get_cached_validation($api_key) {
        $cache_key = 'zc_charts_validation_' . wp_hash($api_key);
        $cached_result = get_transient($cache_key);
        
        if ($cached_result === 'valid') {
            return true;
        } elseif ($cached_result === 'invalid') {
            return false;
        }
        
        return null; // Not cached
    }
    
    /**
     * Clear validation cache
     */
    private static function clear_validation_cache($api_key) {
        $cache_key = 'zc_charts_validation_' . wp_hash($api_key);
        delete_transient($cache_key);
    }
    
    /**
     * Clear rate limit data
     */
    private static function clear_rate_limit_data($api_key = null) {
        global $wpdb;
        
        if ($api_key) {
            // Clear for specific key
            $rate_key = self::get_rate_limit_key($api_key);
            $transient_key = self::RATE_LIMIT_PREFIX . wp_hash($rate_key);
            delete_transient($transient_key);
        } else {
            // Clear all rate limit data
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                    '%' . self::RATE_LIMIT_PREFIX . '%'
                )
            );
        }
    }
    
    /**
     * Get transient TTL
     */
    private static function get_transient_ttl($transient_key) {
        global $wpdb;
        
        $timeout_key = '_transient_timeout_' . $transient_key;
        $timeout = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM $wpdb->options WHERE option_name = %s",
            $timeout_key
        ));
        
        if (!$timeout) {
            return 0;
        }
        
        return max(0, $timeout - time());
    }
    
    /**
     * Setup rate limiting
     */
    public static function setup_rate_limiting() {
        // Schedule cleanup of expired rate limit data
        add_action('wp_loaded', [__CLASS__, 'cleanup_expired_rate_limits'], 20);
    }
    
    /**
     * Cleanup expired rate limits
     */
    public static function cleanup_expired_rate_limits() {
        global $wpdb;
        
        // Remove expired rate limit transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $wpdb->options 
                 WHERE option_name LIKE %s 
                 AND option_name LIKE %s 
                 AND option_value < UNIX_TIMESTAMP()",
                '%' . self::RATE_LIMIT_PREFIX . '%',
                '%_transient_timeout_%'
            )
        );
    }
    
    /**
     * Periodic key validation
     */
    public static function periodic_key_validation() {
        if (!is_admin()) {
            return;
        }
        
        $last_check = get_option('zc_charts_last_periodic_check', 0);
        $check_interval = 4 * HOUR_IN_SECONDS; // Every 4 hours
        
        if (time() - $last_check > $check_interval) {
            $api_key = self::get_stored_api_key();
            
            if (!empty($api_key)) {
                $is_valid = self::validate_api_key($api_key, true);
                update_option('zc_charts_last_periodic_check', time());
                
                // Show admin notice if validation fails
                if (!$is_valid) {
                    add_action('admin_notices', [__CLASS__, 'show_validation_error_notice']);
                }
            }
        }
    }
    
    /**
     * Show validation error notice
     */
    public static function show_validation_error_notice() {
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php _e('ZC Charts:', ZC_CHARTS_TEXT_DOMAIN); ?></strong>
                <?php printf(
                    __('API key validation failed. Please <a href="%s">check your settings</a>.', ZC_CHARTS_TEXT_DOMAIN),
                    admin_url('admin.php?page=zc-charts-settings')
                ); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Log security events
     */
    private static function log_security_event($event_type, $message, $context = []) {
        // Only log if debugging is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $log_entry = [
            'timestamp' => current_time('c'),
            'event' => $event_type,
            'message' => $message,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'ip' => self::get_client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200)
        ];
        
        // Log to error log
        error_log('ZC Charts Security: ' . json_encode($log_entry));
        
        // Store in database for admin viewing
        $security_log = get_option('zc_charts_security_events', []);
        $security_log[] = $log_entry;
        
        // Keep only last 50 events
        if (count($security_log) > 50) {
            $security_log = array_slice($security_log, -50);
        }
        
        update_option('zc_charts_security_events', $security_log);
    }
    
    /**
     * Get security statistics
     */
    public static function get_security_statistics() {
        $api_key = self::get_stored_api_key();
        
        return [
            'api_key_configured' => !empty($api_key),
            'api_key_valid' => !empty($api_key) ? self::validate_api_key($api_key) : false,
            'total_requests' => get_option('zc_charts_total_requests', 0),
            'last_request_time' => get_option('zc_charts_last_request_time', 'Never'),
            'last_validation_time' => get_option('zc_charts_last_validation_time', 'Never'),
            'validation_status' => get_option('zc_charts_validation_status', 'unknown'),
            'recent_events_count' => count(get_option('zc_charts_security_events', [])),
            'rate_limiting_active' => self::is_rate_limiting_active()
        ];
    }
    
    /**
     * Check if rate limiting is active
     */
    private static function is_rate_limiting_active() {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->options WHERE option_name LIKE %s",
            '%' . self::RATE_LIMIT_PREFIX . '%'
        ));
        
        return intval($count) > 0;
    }
    
    /**
     * Cleanup security data
     */
    public static function cleanup_security_data() {
        // Clean up old security events
        $events = get_option('zc_charts_security_events', []);
        if (count($events) > 50) {
            $events = array_slice($events, -50);
            update_option('zc_charts_security_events', $events);
        }
        
        // Clean up expired rate limits
        self::cleanup_expired_rate_limits();
        
        // Clean up old validation caches
        global $wpdb;
        $wpdb->query(
            "DELETE FROM $wpdb->options 
             WHERE option_name LIKE '%zc_charts_validation_%' 
             AND option_name LIKE '%_transient_timeout_%' 
             AND option_value < UNIX_TIMESTAMP()"
        );
    }
    
    /**
     * AJAX handler for key validation
     */
    public static function ajax_validate_key() {
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
        
        $is_valid = self::validate_api_key($api_key, true);
        
        wp_die(json_encode([
            'success' => $is_valid,
            'data' => [
                'valid' => $is_valid,
                'message' => $is_valid ? 
                    __('API key is valid', ZC_CHARTS_TEXT_DOMAIN) : 
                    __('API key is invalid', ZC_CHARTS_TEXT_DOMAIN)
            ]
        ]));
    }
    
    /**
     * AJAX handler for connection testing
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
        
        // Test connection using API client
        if (!class_exists('ZC_Charts_API_Client')) {
            require_once ZC_CHARTS_PLUGIN_DIR . 'includes/class-api-client.php';
        }
        
        $test_result = ZC_Charts_API_Client::test_connection($api_key);
        
        wp_die(json_encode([
            'success' => $test_result['success'],
            'data' => $test_result
        ]));
    }
    
    /**
     * Generate nonce for admin forms
     */
    public static function generate_nonce($action = 'zc_charts_nonce') {
        return wp_create_nonce($action);
    }
    
    /**
     * Verify nonce
     */
    public static function verify_nonce($nonce, $action = 'zc_charts_nonce') {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Check user permissions for chart management
     */
    public static function current_user_can_manage_charts() {
        return current_user_can('manage_options') || current_user_can('edit_posts');
    }
    
    /**
     * Get recent security events
     */
    public static function get_recent_security_events($limit = 20) {
        $events = get_option('zc_charts_security_events', []);
        return array_slice(array_reverse($events), 0, $limit);
    }
}

// Initialize the security system
ZC_Charts_Security::init();
