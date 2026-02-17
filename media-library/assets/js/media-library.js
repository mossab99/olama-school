jQuery(document).ready(function ($) {
    'use strict';

    const state = {
        activeTab: 'upload',
        curriculum: [],
        currentLesson: null,
        logPage: 1
    };

    // --- Tab Management ---
    $('.nav-tab').on('click', function (e) {
        e.preventDefault();
        const tab = $(this).data('tab');
        state.activeTab = tab;

        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        $('.tab-content').removeClass('active');
        $('#tab-' + tab).addClass('active');

        if (tab === 'log') {
            loadLog(1);
        }
    });

    // --- Filter Handlers ---
    $('#filter-grade').on('change', function () {
        const gradeId = $(this).val();
        const $subjectSelect = $('#filter-subject');

        if (!gradeId) {
            $subjectSelect.html('<option value="">' + academyMedia.i18n.error + '</option>').prop('disabled', true);
            return;
        }

        $subjectSelect.html('<option value="">' + academyMedia.i18n.uploading + '</option>').prop('disabled', true);

        $.ajax({
            url: academyMedia.ajaxurl,
            data: {
                action: 'olama_get_subjects',
                grade_id: gradeId,
                nonce: academyMedia.nonce
            },
            success: function (response) {
                if (response.success) {
                    let html = '<option value="">-- Select --</option>';
                    response.data.forEach(sub => {
                        html += `<option value="${sub.id}" data-name="${sub.subject_name}">${sub.subject_name}</option>`;
                    });
                    $subjectSelect.html(html).prop('disabled', false);
                }
            }
        });
    });

    // --- Curriculum Loading ---
    $('#btn-load-curriculum').on('click', function () {
        const filters = {
            academic_year_id: $('#filter-year-id').val(),
            semester_id: $('#filter-semester').val(),
            grade_id: $('#filter-grade').val(),
            subject_id: $('#filter-subject').val()
        };

        if (!filters.grade_id || !filters.subject_id || !filters.semester_id) {
            alert('Please select all filters first.');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).text(academyMedia.i18n.uploading);

        $.ajax({
            url: academyMedia.ajaxurl,
            data: {
                action: 'academy_load_media_curriculum',
                ...filters,
                nonce: academyMedia.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderCurriculum(response.data);
                } else {
                    alert(response.data || academyMedia.i18n.error);
                }
            },
            complete: function () {
                $btn.prop('disabled', false).text('Load Curriculum');
            }
        });
    });

    // --- Sync Status Logic ---
    $('#btn-sync-status').on('click', function () {
        const filters = {
            academic_year_id: $('#filter-year-id').val(),
            semester_id: $('#filter-semester').val(),
            grade_id: $('#filter-grade').val(),
            subject_id: $('#filter-subject').val(),
            academic_year_name: $('#filter-year-name').val(),
            semester_name: $('#filter-semester-name').val(),
            grade_name: $('#filter-grade option:selected').data('name'),
            subject_name: $('#filter-subject option:selected').data('name')
        };

        if (!filters.grade_id || !filters.subject_id || !filters.semester_id) {
            alert('Please select all filters first.');
            return;
        }

        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text('Syncing...');

        $.ajax({
            url: academyMedia.ajaxurl,
            type: 'POST',
            data: {
                action: 'academy_sync_lessons_status',
                ...filters,
                nonce: academyMedia.nonce
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data);
                    $('#btn-load-curriculum').click(); // Refresh list to show new statuses
                } else {
                    alert(response.data || academyMedia.i18n.error);
                }
            },
            error: function () {
                alert(academyMedia.i18n.error);
            },
            complete: function () {
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    function renderCurriculum(units) {
        const $container = $('#curriculum-container');
        if (!units || units.length === 0) {
            $container.html('<div class="notice notice-warning"><p>No curriculum found for these filters.</p></div>');
            return;
        }

        let html = '';
        units.forEach(unit => {
            html += `<div class="unit-card card">
                <h3>Unit ${unit.unit_number}: ${unit.unit_name}</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="80">#</th>
                            <th>Lesson Title</th>
                            <th width="150">Status</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>`;

            if (unit.lessons && unit.lessons.length > 0) {
                unit.lessons.forEach(lesson => {
                    const hasVideo = lesson.upload_status === 'completed';
                    html += `<tr>
                        <td>${lesson.lesson_number}</td>
                        <td>${lesson.lesson_title}</td>
                        <td>
                            <span class="status-badge status-${lesson.upload_status || 'none'}">
                                ${lesson.upload_status || 'No Video'}
                            </span>
                        </td>
                        <td>
                            ${hasVideo ? `<a href="${lesson.drive_file_url}" target="_blank" class="button">View</a>` : ''}
                            <button type="button" class="button btn-trigger-upload" 
                                data-lesson-id="${lesson.id}" 
                                data-unit-id="${unit.id}"
                                data-lesson-name="${lesson.lesson_title}"
                                data-lesson-number="${lesson.lesson_number}"
                                data-unit-name="${unit.unit_name}">
                                ${hasVideo ? 'Replace' : 'Upload'}
                            </button>
                            <div class="upload-progress-container" id="progress-${lesson.id}" style="display:none;">
                                <div class="progress-bar"></div>
                            </div>
                        </td>
                    </tr>`;
                });
            } else {
                html += '<tr><td colspan="4">No lessons found.</td></tr>';
            }

            html += `</tbody></table></div>`;
        });

        $container.html(html);
    }

    // --- Upload Logic ---
    $(document).on('click', '.btn-trigger-upload', function () {
        state.currentLesson = $(this).data();
        $('#media-video-input').click();
    });

    $('#media-video-input').on('change', function () {
        const file = this.files[0];
        if (!file || !state.currentLesson) return;

        // --- File Type Validation ---
        const allowedExtensions = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'm4v'];
        const extension = file.name.split('.').pop().toLowerCase();

        if (!allowedExtensions.includes(extension)) {
            alert('يسمح فقط بملفات الفيديو (mp4, mov, avi, mkv, wmv, m4v)');
            this.value = '';
            return;
        }

        performUpload(file, state.currentLesson);
        this.value = ''; // Reset input
    });

    function performUpload(file, lessonData) {
        const $progressCont = $(`#progress-${lessonData.lessonId}`);
        const $progressBar = $progressCont.find('.progress-bar');

        $progressCont.show();
        $progressBar.css('width', '5%');

        const formData = new FormData();
        formData.append('action', 'academy_upload_media_video');
        formData.append('nonce', academyMedia.nonce);
        formData.append('video_file', file);
        formData.append('lesson_id', lessonData.lessonId);
        formData.append('unit_id', lessonData.unitId);
        formData.append('lesson_name', lessonData.lessonName);
        formData.append('lesson_number', lessonData.lessonNumber);
        formData.append('unit_name', lessonData.unitName);
        formData.append('grade_name', $('#filter-grade option:selected').data('name'));
        formData.append('subject_name', $('#filter-subject option:selected').data('name'));
        formData.append('semester_name', $('#filter-semester-name').val());
        formData.append('academic_year_name', $('#filter-year-name').val());

        $.ajax({
            url: academyMedia.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function () {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener("progress", function (evt) {
                    if (evt.lengthComputable) {
                        const percentComplete = (evt.loaded / evt.total) * 100;
                        $progressBar.css('width', percentComplete + '%');
                    }
                }, false);
                return xhr;
            },
            success: function (response) {
                if (response.success) {
                    alert(response.data.message);
                    $('#btn-load-curriculum').click(); // Refresh list
                } else {
                    alert(response.data || academyMedia.i18n.error);
                }
            },
            error: function () {
                alert(academyMedia.i18n.error);
            },
            complete: function () {
                $progressCont.hide();
            }
        });
    }

    // --- Settings Management ---
    $('#drive-settings-form').on('submit', function (e) {
        e.preventDefault();
        const $btn = $(this).find('button[type="submit"]');
        const $status = $('#settings-status');

        $btn.prop('disabled', true);
        $status.text('Saving...').css('color', 'inherit');

        $.ajax({
            url: academyMedia.ajaxurl,
            type: 'POST',
            data: $(this).serialize() + '&action=academy_save_drive_settings&nonce=' + academyMedia.nonce,
            success: function (response) {
                if (response.success) {
                    $status.text(response.data).css('color', 'green');
                } else {
                    $status.text(response.data || 'Error saving').css('color', 'red');
                }
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });

    $('#btn-test-connection').on('click', function () {
        const $btn = $(this);
        const $status = $('#settings-status');

        $btn.prop('disabled', true);
        $status.text('Testing...').css('color', 'inherit');

        $.ajax({
            url: academyMedia.ajaxurl,
            data: {
                action: 'academy_test_drive_connection',
                nonce: academyMedia.nonce
            },
            success: function (response) {
                if (response.success) {
                    $status.text(response.data).css('color', 'green');
                } else {
                    $status.text(response.data || 'Connection failed').css('color', 'red');
                }
            },
            complete: function () {
                $btn.prop('disabled', false);
            }
        });
    });

    // --- Log Management ---
    function loadLog(page = 1) {
        state.logPage = page;
        const $tbody = $('#log-table-body');

        $tbody.html('<tr><td colspan="6" align="center">Loading...</td></tr>');

        $.ajax({
            url: academyMedia.ajaxurl,
            data: {
                action: 'academy_get_upload_log',
                paged: page,
                grade: '', // Add filters later
                nonce: academyMedia.nonce
            },
            success: function (response) {
                if (response.success) {
                    renderLog(response.data);
                }
            }
        });
    }

    function renderLog(data) {
        const $tbody = $('#log-table-body');
        if (!data.items || data.items.length === 0) {
            $tbody.html('<tr><td colspan="6" align="center">No uploads found.</td></tr>');
            return;
        }

        let html = '';
        data.items.forEach(item => {
            html += `<tr>
                <td>${item.created_at}</td>
                <td>${item.lesson_name} <br><small>(${item.unit_name})</small></td>
                <td>${item.grade} - ${item.subject}</td>
                <td><span class="status-badge status-${item.upload_status}">${item.upload_status}</span></td>
                <td>${item.drive_file_url ? `<a href="${item.drive_file_url}" target="_blank">View on Drive</a>` : '-'}</td>
                <td>
                    <button type="button" class="button btn-delete-log" data-id="${item.id}">Delete</button>
                </td>
            </tr>`;
        });
        $tbody.html(html);

        // Simple pagination
        let paginationHtml = '';
        if (data.pages > 1) {
            for (let i = 1; i <= data.pages; i++) {
                paginationHtml += `<button class="button page-numbers ${i === state.logPage ? 'current' : ''}" data-page="${i}">${i}</button> `;
            }
        }
        $('#log-pagination').html(paginationHtml);
    }

    $(document).on('click', '#log-pagination .page-numbers', function () {
        loadLog($(this).data('page'));
    });

    $(document).on('click', '.btn-delete-log', function () {
        if (!confirm(academyMedia.i18n.confirm_delete)) return;
        const id = $(this).data('id');
        $.ajax({
            url: academyMedia.ajaxurl,
            type: 'POST',
            data: {
                action: 'academy_delete_log_entry',
                id: id,
                nonce: academyMedia.nonce
            },
            success: function () {
                loadLog(state.logPage);
            }
        });
    });

    $('#btn-refresh-log').on('click', () => loadLog(1));

});
