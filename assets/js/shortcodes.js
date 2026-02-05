/**
 * Olama School Shortcodes JavaScript
 */
jQuery(document).ready(function ($) {
    // Exam Schedule & Report Accordion
    $(document).on('click', '.olama-exam-schedule-student .exam-card-header, .olama-exam-report-v2 .day-header', function () {
        const item = $(this).closest('.exam-card, .day-item');
        const wasActive = item.hasClass('active');

        // Close others in the same group
        item.parent().find('.exam-card, .day-item').removeClass('active');
        
        if (!wasActive) {
            item.addClass('active');
        }
    });

    // Stationary Accordion
    $(document).on('click', '.olama-stationary-accordion .accordion-header', function () {
        const item = $(this).closest('.accordion-item');
        const content = item.find('.accordion-content');
        const wasActive = item.hasClass('active');

        // Close all others
        $('.olama-stationary-accordion .accordion-item').removeClass('active');
        $('.olama-stationary-accordion .accordion-content').hide();

        // Toggle current
        if (!wasActive) {
            item.addClass('active');
            content.show();
        }
    });
});
