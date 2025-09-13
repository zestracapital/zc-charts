<?php
/**
 * ZC Charts Admin Header Partial
 * Common header for admin pages
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="zc-charts-admin-header">
    <div class="zc-charts-branding">
        <h1>
            <?php echo esc_html(get_admin_page_title()); ?>
            <span class="zc-charts-version">v<?php echo esc_html(ZC_CHARTS_VERSION); ?></span>
        </h1>
    </div>
    
    <div class="zc-charts-header-actions">
        <?php if (isset($header_actions) && is_array($header_actions)) : ?>
            <?php foreach ($header_actions as $action) : ?>
                <a href="<?php echo esc_url($action['url']); ?>" class="button <?php echo esc_attr($action['class'] ?? ''); ?>">
                    <?php echo esc_html($action['label']); ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>