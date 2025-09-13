<?php
/**
 * ZC Charts Error Template
 * Template for rendering error messages when charts fail to load
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Extract variables passed to the template
$error_message = isset($error_message) ? $error_message : __('An unknown error occurred.', 'zc-charts');
$error_code = isset($error_code) ? $error_code : 'unknown_error';
$show_details = isset($show_details) ? $show_details : false;
$error_details = isset($error_details) ? $error_details : '';
?>

<div class="zc-chart-error-container">
    <div class="zc-chart-error">
        <div class="error-icon">⚠️</div>
        <div class="error-message"><?php echo esc_html($error_message); ?></div>
        
        <?php if ($show_details && !empty($error_details)) : ?>
            <div class="error-details"><?php echo esc_html($error_details); ?></div>
        <?php endif; ?>
        
        <div class="error-actions">
            <button type="button" class="retry-btn" onclick="location.reload();">
                <?php echo esc_html__('Try Again', 'zc-charts'); ?>
            </button>
            
            <?php if ($error_code === 'invalid_api_key') : ?>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=zc-charts')); ?>" class="configure-btn">
                    <?php echo esc_html__('Configure API Key', 'zc-charts'); ?>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.zc-chart-error-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 300px;
    width: 100%;
    background-color: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    color: #6c757d;
    text-align: center;
    padding: 2rem;
    box-sizing: border-box;
}

.zc-chart-error {
    max-width: 400px;
}

.error-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #dc3545;
}

.error-message {
    font-size: 1.125rem;
    margin-bottom: 0.5rem;
    color: #495057;
}

.error-details {
    font-size: 0.875rem;
    opacity: 0.75;
    margin-bottom: 1rem;
    color: #6c757d;
}

.error-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 1rem;
}

.retry-btn,
.configure-btn {
    padding: 0.5rem 1rem;
    border: 1px solid #0073aa;
    border-radius: 4px;
    background-color: #0073aa;
    color: #fff;
    text-decoration: none;
    cursor: pointer;
    font-size: 0.875rem;
    transition: background-color 0.2s ease-in-out;
}

.retry-btn:hover,
.configure-btn:hover {
    background-color: #005a87;
    border-color: #005a87;
}

.configure-btn {
    background-color: #f8f9fa;
    color: #0073aa;
}

.configure-btn:hover {
    background-color: #e9ecef;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .zc-chart-error-container {
        background-color: #2d2d2d;
        border-color: #555;
        color: #cccccc;
    }
    
    .error-icon {
        color: #dc3545;
    }
    
    .error-message {
        color: #ffffff;
    }
    
    .error-details {
        color: #aaaaaa;
    }
    
    .retry-btn,
    .configure-btn {
        border-color: #0073aa;
        background-color: #0073aa;
        color: #ffffff;
    }
    
    .retry-btn:hover,
    .configure-btn:hover {
        background-color: #005a87;
        border-color: #005a87;
    }
    
    .configure-btn {
        background-color: #3d3d3d;
        color: #0073aa;
    }
    
    .configure-btn:hover {
        background-color: #4d4d4d;
    }
}
</style>