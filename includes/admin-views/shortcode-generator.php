<?php
/**
 * Shortcode Generator View
 */
if (!defined('ABSPATH')) {
    exit;
}

$shortcodes = array(
    'olama_weekly_plan' => __('Weekly Plan', 'olama-school'),
    'olama_weekly_schedule' => __('Weekly Schedule', 'olama-school'),
    'olama_teachers_office_hours' => __('Teachers Office Hours', 'olama-school'),
    'olama_stationary' => __('Stationary', 'olama-school'),
    'olama_logged_teacher_schedule' => __('Today\'s Teaching Schedule', 'olama-school'),
);
?>
<div class="olama-card" style="max-width:800px;margin:0 auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);">
    <h2 style="margin-top:0;color:#1e293b;font-size:1.5rem;font-weight:700;">
        <span class="dashicons dashicons-shortcode" style="font-size:24px;width:24px;height:24px;margin-right:10px;color:#2563eb;"></span>
        <?php esc_html_e('Shortcode Generator', 'olama-school'); ?>
    </h2>
    <p><?php esc_html_e('Select an Olama School shortcode to copy into a post, page, or widget.', 'olama-school'); ?></p>

    <label for="olama-shortcode-select" style="display:block;font-weight:600;margin:24px 0 8px;">
        <?php esc_html_e('Content Type', 'olama-school'); ?>
    </label>
    <select id="olama-shortcode-select" style="width:100%;max-width:420px;">
        <?php foreach ($shortcodes as $tag => $label): ?>
            <option value="<?php echo esc_attr($tag); ?>"><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
    </select>

    <div style="background:#f8fafc;padding:25px;border-radius:12px;border:2px dashed #e2e8f0;text-align:center;margin-top:30px;">
        <code id="generated-shortcode" style="display:block;font-size:1.2rem;background:#fff;padding:20px 15px;border:1px solid #cbd5e1;border-radius:8px;color:#2563eb;">[olama_weekly_plan]</code>
        <button type="button" class="button button-primary button-large" id="copy-shortcode" style="margin-top:20px;">
            <?php esc_html_e('Copy Shortcode', 'olama-school'); ?>
        </button>
    </div>
</div>

<script>
jQuery(function ($) {
    const $select = $('#olama-shortcode-select');
    const $code = $('#generated-shortcode');
    $select.on('change', function () {
        $code.text('[' + this.value + ']');
    });
    $('#copy-shortcode').on('click', function () {
        navigator.clipboard.writeText($code.text());
    });
});
</script>
