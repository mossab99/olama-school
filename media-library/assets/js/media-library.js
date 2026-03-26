jQuery(document).ready(function ($) {
    'use strict';

    const state = {
        activeTab: 'upload',
        curriculum: [],
        currentLesson: null,
        logPage: 1
    };

    const getStatusLabel = (status) => {
        if (!status || status === 'none') return academyMedia.i18n.status_none;
        return academyMedia.i18n['status_' + status] || status.toUpperCase();
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
                    let html = `<option value="">${academyMedia.i18n.select}</option>`;
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
            alert(academyMedia.i18n.select_all);
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
                $btn.prop('disabled', false).text(academyMedia.i18n.load_curriculum);
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
            alert(academyMedia.i18n.select_all);
            return;
        }

        const $btn = $(this);
        const originalText = $btn.text();
        $btn.prop('disabled', true).text(academyMedia.i18n.syncing);

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
            $container.html(`<div class="notice notice-warning"><p>${academyMedia.i18n.no_curriculum}</p></div>`);
            return;
        }

        let html = '';
        units.forEach(unit => {
            html += `<div class="unit-card card">
                <h3>${academyMedia.i18n.unit} ${unit.unit_number}: ${unit.unit_name}</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="30">#</th>
                            <th width="20%">${academyMedia.i18n.lesson_title}</th>
                            <th>${academyMedia.i18n.comments}</th>
                            <th width="150">${academyMedia.i18n.uploader} / ${academyMedia.i18n.date}</th>
                            <th width="140">${academyMedia.i18n.status}</th>
                            <th width="220">${academyMedia.i18n.actions}</th>
                        </tr>
                    </thead>
                    <tbody>`;

            if (unit.lessons && unit.lessons.length > 0) {
                unit.lessons.forEach(lesson => {
                    const hasVideo = lesson.upload_status === 'completed';

                    // Uploader Info
                    let uploaderHtml = '-';
                    if (hasVideo && lesson.uploader_name) {
                        uploaderHtml = `<strong>${lesson.uploader_name}</strong><br><small>${lesson.uploaded_at}</small>`;
                    }

                    // Status Badges
                    let statusHtml = `<span class="status-badge status-${lesson.upload_status || 'none'}">${getStatusLabel(lesson.upload_status)}</span>`;
                    if (hasVideo && lesson.approval_status) {
                        statusHtml += ` <span class="status-badge status-${lesson.approval_status}">${getStatusLabel(lesson.approval_status)}</span>`;
                    }

                    // Notes (Editable)
                    // If permissions allow editing? Usually teachers can edit their own or supervisors. 
                    // Let's assume anyone with access can edit notes for now or restrict it. 
                    // User said "editable", assuming by the teacher/supervisor.
                    const notesValue = lesson.comments || '';
                    const notesHtml = `<textarea class="editable-note regular-text" rows="2" style="width:100%; font-size:12px;" 
                                        data-id="${lesson.media_record_id || ''}" 
                                        data-status="${lesson.approval_status || 'pending'}"
                                        ${!hasVideo ? 'disabled' : ''} placeholder="${academyMedia.i18n.comments}...">${notesValue}</textarea>`;

                    // Actions (Icons)
                    let actionsHtml = '';
                    if (hasVideo) {
                        // View
                        actionsHtml += `<button type="button" class="button-icon btn-view-video" title="${academyMedia.i18n.view}" data-url="${lesson.drive_file_url}" data-title="${lesson.lesson_title}">
                                            <span class="dashicons dashicons-controls-play"></span>
                                        </button> `;

                        // Download
                        if (lesson.drive_file_id) {
                            actionsHtml += `<a href="https://drive.google.com/uc?export=download&id=${lesson.drive_file_id}" target="_blank" class="button-icon" title="Download">
                                                <span class="dashicons dashicons-download"></span>
                                            </a> `;
                        }

                        // Approval Actions
                        if (academyMedia.can_approve && lesson.approval_status === 'pending') {
                            actionsHtml += `<button type="button" class="button-icon btn-update-status text-success" title="${academyMedia.i18n.approve}" data-id="${lesson.media_record_id}" data-status="approved">
                                                <span class="dashicons dashicons-yes-alt"></span>
                                            </button> `;
                            actionsHtml += `<button type="button" class="button-icon btn-update-status text-danger" title="${academyMedia.i18n.reject}" data-id="${lesson.media_record_id}" data-status="rejected">
                                                <span class="dashicons dashicons-dismiss"></span>
                                            </button> `;
                        }
                    }

                    // Upload/Replace
                    const uploadIcon = hasVideo ? 'dashicons-update' : 'dashicons-upload';
                    const uploadTitle = hasVideo ? academyMedia.i18n.replace : academyMedia.i18n.upload;
                    actionsHtml += ` <button type="button" class="button-icon btn-trigger-upload" title="${uploadTitle}"
                                data-lesson-id="${lesson.id}" 
                                data-unit-id="${unit.id}"
                                data-lesson-name="${lesson.lesson_title}"
                                data-lesson-number="${lesson.lesson_number}"
                                data-unit-name="${unit.unit_name}"
                                data-record-id="${lesson.media_record_id || ''}">
                                <span class="dashicons ${uploadIcon}"></span>
                            </button>`;

                    // Progress Bar
                    actionsHtml += `<div class="upload-progress-container" id="progress-${lesson.id}" style="display:none;">
                                <div class="progress-bar"></div>
                            </div>`;

                    const lessonTitle = lesson.part_number ? `${lesson.lesson_title} (${academyMedia.i18n.part} ${lesson.part_number})` : lesson.lesson_title;

                    html += `<tr>
                        <td>${lesson.lesson_number}</td>
                        <td>${lessonTitle}</td>
                        <td>${notesHtml}</td>
                        <td>${uploaderHtml}</td>
                        <td>${statusHtml}</td>
                        <td>
                            <div class="actions-wrapper icons-mode">
                                ${actionsHtml}
                            </div>
                        </td>
                    </tr>`;
                });
            } else {
                html += `<tr><td colspan="6">${academyMedia.i18n.no_lessons}</td></tr>`;
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
        const files = Array.from(this.files);
        if (files.length === 0 || !state.currentLesson) return;

        const allowedExtensions = ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'm4v'];

        // Filter and validate files
        const validFiles = files.filter(file => {
            const ext = file.name.split('.').pop().toLowerCase();
            return allowedExtensions.includes(ext);
        });

        if (validFiles.length < files.length) {
            alert('تم استبعاد بعض الملفات لأنها ليست بصيغة فيديو مدعومة.');
        }

        if (validFiles.length === 0) {
            this.value = '';
            return;
        }

        // --- Multi-Upload Loop ---
        validFiles.forEach((file, index) => {
            const data = { ...state.currentLesson };

            // If we are uploading multiple files, they become parts.
            // If we are replacing an existing record (data.recordId is set), the FIRST file replaces it,
            // subsequent files are added as new parts for the same lesson.
            if (validFiles.length > 1) {
                data.part = index + 1;
                // Important: Only clear recordId for subsequent files to avoid overwriting the same one
                if (index > 0) {
                    data.recordId = ''; 
                }
            }

            performUpload(file, data);
        });

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
        formData.append('id', lessonData.recordId || '');  // If we are replacing
        formData.append('lesson_id', lessonData.lessonId);
        formData.append('unit_id', lessonData.unitId);
        formData.append('lesson_name', lessonData.lessonName);
        formData.append('lesson_number', lessonData.lessonNumber);
        formData.append('unit_name', lessonData.unitName);
        if (lessonData.part) {
            formData.append('part_number', lessonData.part);
        }
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
        $status.text(academyMedia.i18n.saving).css('color', 'inherit');

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
        $status.text(academyMedia.i18n.testing).css('color', 'inherit');

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

    // --- Status Update & Comments ---
    $(document).on('click', '.btn-update-status', function () {
        const id = $(this).data('id');
        const status = $(this).data('status');
        let comment = null;

        if (status === 'rejected') {
            comment = prompt(academyMedia.i18n.comments + ':', '');
            if (comment === null) return; // Cancelled
        }

        updateStatus(id, status, comment);
    });

    // --- Notes Auto-Save ---
    $(document).on('change', '.editable-note', function () {
        const id = $(this).data('id');
        const status = $(this).data('status');
        const comment = $(this).val();

        // Visual feedback?
        const $textarea = $(this);
        $textarea.css('border-color', '#ffc107'); // Yellow indicating saving

        $.ajax({
            url: academyMedia.ajaxurl,
            type: 'POST',
            data: {
                action: 'academy_update_media_status',
                media_id: id,
                status: status, // Maintain status
                comment: comment,
                nonce: academyMedia.nonce
            },
            success: function (response) {
                if (response.success) {
                    $textarea.css('border-color', '#28a745'); // Green success
                    setTimeout(() => $textarea.css('border-color', ''), 1000);
                } else {
                    $textarea.css('border-color', '#dc3545'); // Red error
                    alert(response.data);
                }
            },
            error: function () {
                $textarea.css('border-color', '#dc3545');
            }
        });
    });

    // --- Comment Modal Logic ---
    $(document).on('click', '.btn-view-comments', function () {
        const comments = decodeURIComponent($(this).data('comments'));
        const id = $(this).data('id');
        // If undefined (e.g. for pending), default to pending
        const status = $(this).data('status') || 'pending';

        $('#comment-modal-text').val(comments);
        $('#comment-media-id').val(id);
        // Store status in data attribute of the modal
        $('#comment-modal').data('status', status);

        if (academyMedia.can_approve) {
            $('#comment-modal-text').prop('readonly', false);
            $('#btn-save-comment').show();
        } else {
            $('#comment-modal-text').prop('readonly', true);
            $('#btn-save-comment').hide();
        }

        $('#comment-modal').fadeIn(200);
    });

    // Save comment from modal
    $('#btn-save-comment').on('click', function () {
        const id = $('#comment-media-id').val();
        const comment = $('#comment-modal-text').val();
        const status = $('#comment-modal').data('status');

        updateStatus(id, status, comment);
        $('#comment-modal').fadeOut(200);
    });

    function updateStatus(id, status, comment) {
        $.ajax({
            url: academyMedia.ajaxurl,
            type: 'POST',
            data: {
                action: 'academy_update_media_status',
                media_id: id,
                status: status,
                comment: comment,
                nonce: academyMedia.nonce
            },
            success: function (response) {
                if (response.success) {
                    $('#btn-load-curriculum').click(); // Refresh
                } else {
                    alert(response.data);
                }
            }
        });
    }

    // Close Comment Modal
    $('.academy-modal-close, .academy-modal-overlay').on('click', function (e) {
        if (e.target !== this) return;
        $('#comment-modal').fadeOut(200);
    });

    // --- Log Management ---
    function loadLog(page = 1) {
        state.logPage = page;
        const $tbody = $('#log-table-body');

        $tbody.html(`<tr><td colspan="6" align="center">${academyMedia.i18n.loading}</td></tr>`);

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
            $tbody.html(`<tr><td colspan="6" align="center">${academyMedia.i18n.no_logs}</td></tr>`);
            return;
        }

        let html = '';
        data.items.forEach(item => {
            html += `<tr>
                <td>${item.created_at}</td>
                <td>${item.lesson_name} <br><small>(${item.unit_name})</small></td>
                <td>${item.grade} - ${item.subject}</td>
                <td><span class="status-badge status-${item.upload_status}">${getStatusLabel(item.upload_status)}</span></td>
                <td>${item.drive_file_url ? `<a href="${item.drive_file_url}" target="_blank">${academyMedia.i18n.view_on_drive}</a>` : '-'}</td>
                <td>
                    <button type="button" class="button btn-delete-log" data-id="${item.id}">${academyMedia.i18n.delete}</button>
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

    // --- Video Preview Modal ---
    const $modal = $('#video-preview-modal');
    const $iframe = $('#video-preview-iframe');
    const $modalTitle = $('#modal-video-title');

    $(document).on('click', '.btn-view-video', function () {
        const url = $(this).data('url');
        const title = $(this).data('title');

        // Convert view link to preview link if needed
        // Drive links usually end in /view, we want /preview for embedding
        let previewUrl = url;
        if (url.includes('/view')) {
            previewUrl = url.replace('/view', '/preview');
        }

        $iframe.attr('src', previewUrl);
        $modalTitle.text(title);
        $modal.fadeIn(200);
    });

    // Close Modal
    $('.academy-modal-close, .academy-modal-overlay').on('click', function (e) {
        if (e.target !== this) return; // Prevent closing when clicking inside modal body

        $modal.fadeOut(200, function () {
            $iframe.attr('src', ''); // Stop video
        });
    });

});
