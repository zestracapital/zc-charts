<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ZC_Charts_Security class.
 * Handles API key validation by communicating with the ZC DMT plugin.
 */
class ZC_Charts_Security {

    /**
     * Validate an API key with the ZC DMT plugin.
     *
     * @param string $key The API key to validate.
     * @return bool True if valid, false otherwise.
     */
    public static function validate_api_key($key) {
        // Basic check
        if (empty($key)) {
            return false;
        }

        // Get the DMT API base URL
        $dmt_api_base = self::get_dmt_api_base_url();

        if (is_wp_error($dmt_api_base)) {
            // Log error?
            return false;
        }

        $validate_url = trailingslashit($dmt_api_base) . 'validate-key';

        $args = array(
            'body' => array(
                'access_key' => $key,
            ),
            'timeout' => 15, // seconds
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
        );

        $response = wp_remote_post($validate_url, $args);

        if (is_wp_error($response)) {
            // Log the error
            error_log('ZC Charts: API Key Validation Failed (WP Error) - ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            // Log the HTTP error
            error_log('ZC Charts: API Key Validation Failed (HTTP ' . $response_code . ') - ' . $body);
            return false;
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ZC Charts: API Key Validation Failed (Invalid JSON Response)');
            return false;
        }

        return isset($data['valid']) && $data['valid'] === true;
    }

    /**
     * Get the stored API key from the ZC Charts settings.
     *
     * @return string The API key.
     */
    public static function get_api_key() {
        return get_option('zc_charts_api_key', '');
    }

    /**
     * Get the base URL for the ZC DMT REST API.
     *
     * @return string|WP_Error The API base URL or WP_Error on failure.
     */
    public static function get_dmt_api_base_url() {
        // The DMT plugin should register its REST routes under this namespace
        $namespace = 'zc-dmt/v1';

        // Use WordPress's built-in function to get the REST URL
        // This automatically handles site URL, home URL, and permalink structure
        $rest_url = rest_url($namespace);

        // rest_url() can return a WP_Error if the REST API is disabled
        if (is_wp_error($rest_url)) {
            return $rest_url;
        }

        // Ensure it ends with a slash
        return trailingslashit($rest_url);
    }

    /**
     * Check if the DMT plugin is active and its REST API is accessible.
     *
     * @return bool True if DMT API is accessible, false otherwise.
     */
    public static function is_dmt_api_accessible() {
        $api_base = self::get_dmt_api_base_url();

        if (is_wp_error($api_base)) {
            return false;
        }

        // A simple HEAD request to the base URL to check if the API namespace exists
        // This is a lightweight way to check API availability
        $args = array(
            'method' => 'HEAD',
            'timeout' => 10,
        );

        $response = wp_remote_request($api_base, $args);

        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);

        // If we get a 200, 404 (namespace exists but no route for HEAD), or other non-error codes,
        // it generally means the API is accessible.
        // A 404 is common for REST API base URLs when using HEAD/GET without a specific route.
        return $response_code < 400 || $response_code == 404;
    }
}
