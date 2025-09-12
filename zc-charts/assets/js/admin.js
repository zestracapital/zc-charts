/**
 * ZC Charts Admin JavaScript
 * 
 * Handles admin-side interactions for the ZC Charts plugin settings page.
 */

jQuery(document).ready(function($) {
    'use strict';

    /**
     * Handle the "Test Connection" button click.
     * This sends an AJAX request to test the API key with the DMT plugin.
     */
    $('#zc_charts_test_connection').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const $apiKeyField = $('#zc_charts_api_key');
        const apiKey = $apiKeyField.val().trim();
        
        // Disable button and show loading state
        $button.prop('disabled', true).text('Testing...');
        
        // Basic validation
        if (!apiKey) {
            alert('Please enter an API key to test.');
            $button.prop('disabled', false).text('Test Connection');
            return;
        }
        
        // In a real implementation, you would make an AJAX call to admin-ajax.php
        // For now, we'll simulate the process
        
        // Simulate API call delay
        setTimeout(function() {
            // Simulate success/failure (in reality, this would be based on the actual API response)
            const isSuccess = Math.random() > 0.3; // 70% chance of success for simulation
            
            if (isSuccess) {
                alert('Connection test successful! API key is valid.');
            } else {
                alert('Connection test failed. Please check your API key and ensure the ZC DMT plugin is active.');
            }
            
            // Re-enable button
            $button.prop('disabled', false).text('Test Connection');
        }, 1500);
        
        /*
        // Example of how a real AJAX call might look:
        $.ajax({
            url: ajaxurl, // This is defined by WordPress
            type: 'POST',
            data: {
                action: 'zc_charts_test_api_key',
                api_key: apiKey,
                nonce: zcChartsAdmin.nonce // Assuming you localized a nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Connection test successful! API key is valid.');
                } else {
                    alert('Connection test failed: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                alert('An error occurred while testing the connection: ' + error);
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Connection');
            }
        });
        */
    });
    
    /**
     * Handle changes to the default library selector.
     * This could be used to show/hide library-specific options.
     */
    $('#zc_charts_default_library').on('change', function() {
        const selectedLibrary = $(this).val();
        
        // Example: Show a notice for Highcharts
        if (selectedLibrary === 'highcharts') {
            if (!$('#highcharts-notice').length) {
                $('#zc_charts_default_library').after(
                    '<p id="highcharts-notice" class="description" style="color: #0073aa;">' +
                    'Note: Highcharts is a powerful library with advanced features. ' +
                    'Make sure your usage complies with their licensing terms.' +
                    '</p>'
                );
            }
        } else {
            $('#highcharts-notice').remove();
        }
    });
    
    /**
     * Handle changes to the fallback enable checkbox.
     * This could show/hide related settings.
     */
    $('#zc_charts_enable_fallback').on('change', function() {
        // Example implementation - in a full plugin, you might show/hide other fields
        // based on this checkbox state.
        // For now, we'll just log the change.
        console.log('Fallback setting changed to: ' + ($(this).is(':checked') ? 'enabled' : 'disabled'));
    });
    
    /**
     * Handle changes to the controls enable checkbox.
     */
    $('#zc_charts_enable_controls').on('change', function() {
        console.log('Controls setting changed to: ' + ($(this).is(':checked') ? 'enabled' : 'disabled'));
    });
    
    /**
     * Add confirmation for critical actions if needed in the future.
     * For example, clearing cached data or resetting settings.
     */
    // This is just a placeholder for future functionality
    // $('.zc-charts-confirm-action').on('click', function(e) {
    //     if (!confirm('Are you sure you want to perform this action?')) {
    //         e.preventDefault();
    //     }
    // });
    
    /**
     * Initialize any tooltips or other UI enhancements.
     */
    // Example: Initialize tooltips if you add any
    // $('.zc-charts-tooltip').tooltip();
    
    console.log('ZC Charts Admin JavaScript loaded.');
});
