/* Admin JS for Olama School. */
jQuery(function ($) {
    if (typeof olamaAdmin === 'undefined' || !$.fn.datepicker) {
        return;
    }

    $('.olama-datepicker').datepicker({
        dateFormat: olamaAdmin.dateFormat,
        changeMonth: true,
        changeYear: true,
        firstDay: 0,
        isRTL: olamaAdmin.isArabic,
        onSelect: function (dateText, inst) {
            const day = inst.selectedDay.toString().padStart(2, '0');
            const month = (inst.selectedMonth + 1).toString().padStart(2, '0');
            const year = inst.selectedYear;
            $(this).attr('data-raw', `${year}-${month}-${day}`).trigger('change');
        }
    });
});
