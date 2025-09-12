<?php
/**
 * ZC Charts Admin Header Partial
 *
 * This file contains the common header markup for the ZC Charts plugin admin pages.
 *
 * @package ZC_Charts
 * @since 1.0.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    return;
}

// Get the current page for navigation highlighting (if needed in future)
// $current_page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';

?>
<div class="zc-charts-header">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php
    // Display admin notices/errors from settings
    settings_errors('zc_charts_messages');
    
    // Display general admin notices
    if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'zc-charts') . '</p></div>';
    }
    ?>
</div>

<!-- 
Navigation could be added here if more admin pages are created in the future.
For now, the settings are contained within the main settings page.
<nav class="zc-charts-navigation">
    <nav class="nav-tab-wrapper">
        <a href="<?php // echo esc_url(admin_url('options-general.php?page=zc-charts-settings')); ?>" class="nav-tab <?php // echo ($current_page == 'zc-charts-settings') ? 'nav-tab-active' : ''; ?>"><?php // esc_html_e('Settings', 'zc-charts'); ?></a>
    </nav>
</nav>
-->
