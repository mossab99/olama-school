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

    // ===== Family Performance Dashboard =====
    // Student Accordion (single-open)
    $(document).on('click', '.olama-family-perf .fp-student-header', function () {
        const card = $(this).closest('.fp-student-card');
        const wasActive = card.hasClass('active');

        // Close all others
        $('.olama-family-perf .fp-student-card').removeClass('active');

        // Toggle current
        if (!wasActive) {
            card.addClass('active');
        }
    });

    // Evaluation Tab switching
    $(document).on('click', '.olama-family-perf .fp-eval-tab', function () {
        const card = $(this).closest('.fp-student-card');
        const idx = $(this).data('tab-index');

        // Update tabs
        card.find('.fp-eval-tab').removeClass('active');
        $(this).addClass('active');

        // Update panels
        card.find('.fp-eval-panel').removeClass('active');
        card.find('.fp-eval-panel[data-panel-index="' + idx + '"]').addClass('active');
    });

    // Evaluation Pill click → switch tab
    $(document).on('click', '.olama-family-perf .fp-eval-pill', function () {
        const card = $(this).closest('.fp-student-card');
        const idx = $(this).data('tab-index');

        // Switch tab
        card.find('.fp-eval-tab').removeClass('active');
        card.find('.fp-eval-tab[data-tab-index="' + idx + '"]').addClass('active');

        // Switch panel
        card.find('.fp-eval-panel').removeClass('active');
        card.find('.fp-eval-panel[data-panel-index="' + idx + '"]').addClass('active');
    });
});
