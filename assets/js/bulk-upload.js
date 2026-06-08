jQuery(document).ready(function ($) {
    'use strict';

    let selectedSemester = $('#bulk-semester').val() || null;
    let selectedGrade = $('#bulk-grade').val() || null;
    let selectedFile = null;

    // Enable/disable file input when semester and grade are selected
    function updateUploadButtonState() {
        const semesterSelected = $('#bulk-semester').val();
        const gradeSelected = $('#bulk-grade').val();
        
        const fileInput = $('#bulk-upload-file')[0];
        const fileSelected = fileInput && fileInput.files && fileInput.files.length > 0;

        if (semesterSelected && gradeSelected) {
            $('#bulk-upload-file').prop('disabled', false);
        } else {
            $('#bulk-upload-file').prop('disabled', true);
            $('#bulk-upload-btn').prop('disabled', true);
        }

        if (semesterSelected && gradeSelected && fileSelected) {
            $('#bulk-upload-btn').prop('disabled', false);
        } else {
            $('#bulk-upload-btn').prop('disabled', true);
        }
    }

    // Set initial state on page load
    updateUploadButtonState();

    // Semester change handler
    $('#bulk-semester').on('change', function () {
        selectedSemester = $(this).val();
        updateUploadButtonState();
    });

    // Grade change handler
    $('#bulk-grade').on('change', function () {
        selectedGrade = $(this).val();
        updateUploadButtonState();
    });

    // File selection handler
    $('#bulk-upload-file').on('change', function () {
        selectedFile = this.files[0];
        updateUploadButtonState();
    });

    // Upload button click handler
    $('#bulk-upload-btn').on('click', function () {
        if (!selectedSemester || !selectedGrade) {
            alert(olamaBulkUpload.i18n.selectBoth);
            return;
        }

        if (!selectedFile) {
            alert(olamaBulkUpload.i18n.selectFile);
            return;
        }

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'olama_bulk_upload_curriculum');
        formData.append('nonce', olamaBulkUpload.nonce);
        formData.append('semester_id', selectedSemester);
        formData.append('grade_id', selectedGrade);
        formData.append('file', selectedFile);

        // Show loading state
        $('#bulk-upload-btn').prop('disabled', true);
        $('#bulk-upload-spinner').addClass('is-active');
        $('#bulk-upload-results').hide();

        // Send AJAX request
        $.ajax({
            url: olamaBulkUpload.ajaxUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    displayResults(response.data);
                } else {
                    displayError(response.data.message || olamaBulkUpload.i18n.error);
                }
            },
            error: function (xhr, status, error) {
                console.error('Upload error:', error);
                displayError(olamaBulkUpload.i18n.error + ': ' + error);
            },
            complete: function () {
                $('#bulk-upload-btn').prop('disabled', false);
                $('#bulk-upload-spinner').removeClass('is-active');
            }
        });
    });

    // Display upload results
    function displayResults(data) {
        let html = '';

        // Summary section
        const totalUnits = data.results.reduce((sum, subject) => sum + (subject.units_count || 0), 0);
        const totalLessons = data.results.reduce((sum, subject) => sum + (subject.lessons_count || 0), 0);
        const hasErrors = data.results.some(subject => subject.errors && subject.errors.length > 0);

        html += '<div class="bulk-result-summary ' + (hasErrors ? 'error' : 'success') + '">';
        html += '<h3 style="margin-top: 0;">' + olamaBulkUpload.i18n.success + '</h3>';
        html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
        html += '<div><strong>' + olamaBulkUpload.i18n.totalSubjects + ':</strong> ' + data.results.length + '</div>';
        html += '<div><strong>' + olamaBulkUpload.i18n.totalUnits + ':</strong> ' + totalUnits + '</div>';
        html += '<div><strong>' + olamaBulkUpload.i18n.totalLessons + ':</strong> ' + totalLessons + '</div>';
        html += '</div>';
        html += '</div>';

        // Detailed results table
        html += '<table class="bulk-result-table">';
        html += '<thead><tr>';
        html += '<th>' + olamaBulkUpload.i18n.subject + '</th>';
        html += '<th>' + olamaBulkUpload.i18n.unitsImported + '</th>';
        html += '<th>' + olamaBulkUpload.i18n.lessonsImported + '</th>';
        html += '<th>' + olamaBulkUpload.i18n.status + '</th>';
        html += '</tr></thead>';
        html += '<tbody>';

        data.results.forEach(function (subject) {
            html += '<tr>';
            html += '<td><strong>' + escapeHtml(subject.subject_name) + '</strong></td>';
            html += '<td>' + (subject.units_count || 0) + '</td>';
            html += '<td>' + (subject.lessons_count || 0) + '</td>';

            if (subject.errors && subject.errors.length > 0) {
                html += '<td><span class="result-badge error">Error</span>';
                html += '<ul class="error-list">';
                subject.errors.forEach(function (error) {
                    html += '<li>' + escapeHtml(error) + '</li>';
                });
                html += '</ul></td>';
            } else {
                html += '<td><span class="result-badge success">Success</span></td>';
            }

            html += '</tr>';
        });

        html += '</tbody></table>';

        $('#bulk-upload-results-content').html(html);
        $('#bulk-upload-results').fadeIn();

        // Scroll to results
        $('html, body').animate({
            scrollTop: $('#bulk-upload-results').offset().top - 100
        }, 500);
    }

    // Display error message
    function displayError(message) {
        let html = '<div class="bulk-result-summary error">';
        html += '<h3 style="margin-top: 0; color: #991b1b;">' + olamaBulkUpload.i18n.error + '</h3>';
        html += '<p>' + escapeHtml(message) + '</p>';
        html += '</div>';

        $('#bulk-upload-results-content').html(html);
        $('#bulk-upload-results').fadeIn();
    }

    //Helper function to escape HTML
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
    }
});
