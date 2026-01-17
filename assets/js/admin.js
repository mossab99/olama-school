/* Admin JS for Olama School Weekly Plan System */
jQuery(document).ready(function ($) {
    console.log('Olama School Weekly Plan Admin Loaded');

    // Initialize datepickers with localized format
    if (typeof olamaAdmin !== 'undefined' && $.fn.datepicker) {
        $('.olama-datepicker').datepicker({
            dateFormat: olamaAdmin.dateFormat,
            changeMonth: true,
            changeYear: true,
            firstDay: 0, // Sunday
            isRTL: olamaAdmin.isArabic,
            onSelect: function (dateText, inst) {
                const day = inst.selectedDay.toString().padStart(2, '0');
                const month = (inst.selectedMonth + 1).toString().padStart(2, '0');
                const year = inst.selectedYear;
                $(this).attr('data-raw', `${year}-${month}-${day}`).trigger('change');
            }
        });
    }
});
