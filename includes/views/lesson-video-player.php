<?php
if (!defined('ABSPATH')) {
    exit;
}

wp_enqueue_style('olama-student-gateway');
wp_enqueue_script('olama-student-gateway');
$video_title_id = wp_unique_id('olama-plan-video-title-');
?>
<dialog class="olama-gateway__video-dialog" data-video-dialog aria-labelledby="<?php echo esc_attr($video_title_id); ?>" dir="rtl">
    <header>
        <h3 id="<?php echo esc_attr($video_title_id); ?>" data-video-title></h3>
        <button type="button" data-video-close aria-label="إغلاق الفيديو">×</button>
    </header>
    <div class="olama-gateway__video-screen" data-video-screen></div>
    <p data-video-error hidden>تعذر تشغيل الفيديو داخل الصفحة.</p>
</dialog>
