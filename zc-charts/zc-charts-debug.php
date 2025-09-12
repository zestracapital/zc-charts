<?php
/**
 * ZC Charts Plugin - Temporary Debugging File
 * 
 * This file is for temporary debugging purposes only.
 * It can be used to test various components of the Charts plugin.
 * 
 * IMPORTANT: This file should be removed or secured before deploying to production.
 * 
 * To use:
 * 1. Visit: yoursite.com/wp-content/plugins/zc-charts/zc-charts-debug.php
 * 2. Check the output for debugging information.
 * 
 * WARNING: This file outputs sensitive information. Use with caution.
 */

// Security check - Ensure WordPress is loaded
if (!defined('ABSPATH')) {
    echo "Direct access forbidden.";
    exit;
}

// Only allow administrators to access this file
if (!current_user_can('manage_options')) {
    echo "Access denied. Administrator privileges required.";
    exit;
}

// Load WordPress environment
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

// Start output buffering to capture any errors
ob_start();

echo "<!DOCTYPE html>
<html>
<head>
    <title>ZC Charts Plugin Debug Information</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f0f0f1; }
        .container { background: white; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #2271b1; }
        h2 { color: #1d2327; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        pre { background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 3px; overflow-x: auto; }
        .success { color: #008a20; }
        .error { color: #b32d2e; }
        .warning { color: #dba617; }
        .info { color: #2271b1; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>ZC Charts Plugin Debug Information</h1>
        <p><strong>Generated on:</strong> " . date('Y-m-d H:i:s') . "</p>
        <hr>";

// Check if main plugin file exists
echo "<h2>Plugin Status</h2>";
if (file_exists(plugin_dir_path(__FILE__) . 'zc-charts.php')) {
    echo "<p class='success'>✓ Main plugin file (zc-charts.php) found.</p>";
} else {
    echo "<p class='error'>✗ Main plugin file (zc-charts.php) NOT found.</p>";
}

// Check if required classes exist
echo "<h2>Class Availability</h2>";
$required_classes = array(
    'ZC_Charts_Plugin',
    'ZC_Charts_Security',
    'ZC_Charts_API_Client',
    'ZC_Charts_Fallback',
    'ZC_Charts_Charts',
    'ZC_Charts_Shortcodes'
);

foreach ($required_classes as $class) {
    if (class_exists($class)) {
        echo "<p class='success'>✓ Class {$class} is available.</p>";
    } else {
        echo "<p class='error'>✗ Class {$class} is NOT available.</p>";
    }
}

// Check dependency on ZC DMT
echo "<h2>ZC DMT Dependency</h2>";
if (is_plugin_active('zc-dmt/zc-dmt.php')) {
    echo "<p class='success'>✓ ZC DMT plugin is active.</p>";
    
    // Check if DMT classes are accessible
    if (class_exists('ZC_DMT_Security')) {
        echo "<p class='success'>✓ ZC DMT classes are accessible.</p>";
    } else {
        echo "<p class='warning'>⚠ ZC DMT classes are NOT accessible. There might be an autoloading issue.</p>";
    }
    
} else {
    echo "<p class='error'>✗ ZC DMT plugin is NOT active. ZC Charts depends on it.</p>";
}

// Check plugin settings
echo "<h2>Plugin Settings</h2>";
$api_key = get_option('zc_charts_api_key', '');
$default_library = get_option('zc_charts_default_library', 'chartjs');
$default_height = get_option('zc_charts_default_height', '300px');
$enable_fallback = get_option('zc_charts_enable_fallback', 1);
$enable_controls = get_option('zc_charts_enable_controls', 1);

echo "<table>
        <tr><th>Setting</th><th>Value</th></tr>
        <tr><td>API Key</td><td>" . ($api_key ? "<code>" . substr($api_key, 0, 10) . "...</code> (Set)" : "<span class='warning'>Not Set</span>") . "</td></tr>
        <tr><td>Default Library</td><td>{$default_library}</td></tr>
        <tr><td>Default Height</td><td>{$default_height}</td></tr>
        <tr><td>Enable Fallback</td><td>" . ($enable_fallback ? 'Yes' : 'No') . "</td></tr>
        <tr><td>Enable Controls</td><td>" . ($enable_controls ? 'Yes' : 'No') . "</td></tr>
      </table>";

// Test API key validation
echo "<h2>API Key Validation</h2>";
if (!empty($api_key)) {
    if (class_exists('ZC_Charts_API_Client')) {
        // Note: This will make an actual HTTP request to the DMT plugin
        echo "<p>Testing API key validation (this may take a moment)...</p>";
        
        // Flush output to show the message above before the test
        ob_flush();
        flush();
        
        $is_valid = ZC_Charts_API_Client::validate_api_key($api_key);
        if ($is_valid) {
            echo "<p class='success'>✓ API key is valid.</p>";
        } else {
            echo "<p class='error'>✗ API key is invalid or there was a problem connecting to the DMT plugin.</p>";
            echo "<p class='info'>Check that the DMT plugin is running and the API key is correct.</p>";
        }
    } else {
        echo "<p class='error'>ZC_Charts_API_Client class not available to test API key.</p>";
    }
} else {
    echo "<p class='warning'>No API key configured. Cannot test validation.</p>";
}

// Check shortcode registration
echo "<h2>Shortcode Registration</h2>";
global $shortcode_tags;
$chart_shortcodes = array('zc_chart_dynamic', 'zc_chart_static');

foreach ($chart_shortcodes as $shortcode) {
    if (isset($shortcode_tags[$shortcode]) && is_callable($shortcode_tags[$shortcode])) {
        echo "<p class='success'>✓ Shortcode [{$shortcode}] is registered.</p>";
    } else {
        echo "<p class='error'>✗ Shortcode [{$shortcode}] is NOT registered.</p>";
    }
}

// Check asset enqueueing
echo "<h2>Asset Enqueue Status</h2>";
echo "<p>Asset enqueueing is typically checked during page load. This debug script runs outside the normal WordPress loop, so we can't directly check if assets are enqueued.</p>";
echo "<p>To check asset enqueueing:</p>";
echo "<ul>
        <li>View the page source of a page where you've used a chart shortcode</li>
        <li>Look for script tags loading Chart.js, Highcharts, or chart-loader.js</li>
      </ul>";

// Display any errors that occurred during execution
$buffer_output = ob_get_contents();
ob_end_clean();

echo "<h2>Execution Output</h2>";
if (!empty($buffer_output)) {
    echo "<pre>" . htmlspecialchars($buffer_output) . "</pre>";
} else {
    echo "<p class='info'>No output captured from execution.</p>";
}

echo "<hr>
        <p><strong>Note:</strong> This is a temporary debugging file. Please remove it from production environments.</p>
        <p><strong>Important:</strong> The API key validation test makes an HTTP request to the DMT plugin.</p>
    </div>
</body>
</html>";
