/**
 * Exam Hall Distribution System – Canvas-based frontend
 * Redesigned: grade/section → add halls to canvas → distribute
 */
(function ($) {
    'use strict';

    /* ─── Config ─────────────────────────────────────────────────────────────── */
    const EH = {
        ajaxUrl:    olamaExamHall.ajaxUrl,
        nonce:      olamaExamHall.nonce,
        yearId:     parseInt(olamaExamHall.yearId)     || 0,
        semesterId: parseInt(olamaExamHall.semesterId) || 0,
        isAdmin:    olamaExamHall.isAdmin   === '1',
        isArabic:   olamaExamHall.isArabic  === '1',
        allHalls:   Array.isArray(olamaExamHall.halls) ? olamaExamHall.halls : [],
        sections:   olamaExamHall.sections  || {},

        // Canvas session state
        canvas: {
            gradeId:    0,
            sectionId:  0,
            halls:      [],   // [{id, hall_name, capacity}] on canvas
            students:   [],   // all students from selected grade/section
            assignments:{},   // { hall_id: [studentObj, ...] }
        },
        dragSrc: null,
    };

    /* ─── Persistence (localStorage) ─────────────────────────────────────────── */
    const STORE_KEY = 'eh_canvas_' + EH.yearId + '_' + EH.semesterId;

    function saveState() {
        try {
            localStorage.setItem(STORE_KEY, JSON.stringify({
                gradeId:   EH.canvas.gradeId,
                sectionId: EH.canvas.sectionId,
                hallIds:   EH.canvas.halls.map(h => parseInt(h.id)),
            }));
        } catch (e) {}
    }

    function restoreState() {
        try {
            const d = JSON.parse(localStorage.getItem(STORE_KEY) || '{}');
            if (!d.hallIds || !d.hallIds.length) return false;

            EH.canvas.gradeId   = d.gradeId   || 0;
            EH.canvas.sectionId = d.sectionId || 0;
            EH.canvas.halls     = EH.allHalls.filter(h => d.hallIds.includes(parseInt(h.id)));

            if (EH.canvas.gradeId) {
                $('#eh-filter-grade').val(EH.canvas.gradeId);
                populateSections(EH.canvas.gradeId, EH.canvas.sectionId);
            }
            return true;
        } catch (e) { return false; }
    }

    /* ─── AJAX helper ─────────────────────────────────────────────────────────── */
    function ajax(action, data, cb) {
        $.ajax({
            url:    EH.ajaxUrl,
            method: 'POST',
            data:   Object.assign({
                action,
                nonce:            EH.nonce,
                academic_year_id: EH.yearId,
                semester_id:      EH.semesterId,
            }, data),
            success: r => r.success ? cb && cb(null, r.data) : cb && cb((r.data && r.data.message) || 'Error'),
            error:   () => cb && cb('Network error'),
        });
    }

    /* ─── Toast ───────────────────────────────────────────────────────────────── */
    function toast(msg, type = 'info', ms = 3500) {
        const icons = { success: '✓', error: '✕', info: 'ℹ' };
        const $t = $('<div class="eh-toast ' + type + '">').html(
            '<span>' + (icons[type] || '') + '</span><span>' + msg + '</span>'
        ).appendTo('body');
        setTimeout(() => $t.fadeOut(400, function () { $(this).remove(); }), ms);
    }

    /* ─── Section cascade ─────────────────────────────────────────────────────── */
    function populateSections(gradeId, selectedSectionId) {
        const $sel = $('#eh-filter-section').empty().append('<option value="">-- الشعبة / Section --</option>');
        const secs = EH.sections[gradeId] || [];
        secs.forEach(s => $sel.append($('<option>').val(s.id).text(s.section_name)));
        if (selectedSectionId) $sel.val(selectedSectionId);
    }

    $(document).on('change', '#eh-filter-grade', function () {
        populateSections($(this).val(), 0);
    });

    /* ─── Load students ───────────────────────────────────────────────────────── */
    function loadStudents(cb) {
        const gradeId   = parseInt($('#eh-filter-grade').val())   || 0;
        const sectionId = parseInt($('#eh-filter-section').val()) || 0;

        if (!gradeId && !sectionId) {
            toast('اختر الصف والشعبة أولاً / Select grade and section', 'error');
            return;
        }

        EH.canvas.gradeId   = gradeId;
        EH.canvas.sectionId = sectionId;
        saveState();

        $('#eh-student-panel-body').html(
            '<p style="text-align:center;padding:24px;"><span class="spinner is-active" style="float:none;"></span></p>'
        );

        ajax('olama_eh_get_students', {
            grade_id:        gradeId,
            section_id:      sectionId,
            canvas_hall_ids: EH.canvas.halls.map(h => h.id),
        }, function (err, data) {
            if (err) { toast(err, 'error'); return; }
            EH.canvas.students    = data.students    || [];
            EH.canvas.assignments = data.assignments || {};
            // Ensure each canvas hall is keyed in assignments
            EH.canvas.halls.forEach(h => {
                if (!EH.canvas.assignments[h.id]) EH.canvas.assignments[h.id] = [];
            });

            renderUnassigned(data.unassigned || []);
            renderCanvas();
            updateStats();
            cb && cb();
        });
    }

    $(document).on('click', '#btn-eh-load-students', function () {
        loadStudents();
    });

    // Auto-load when both grade+section are selected
    $(document).on('change', '#eh-filter-grade, #eh-filter-section', function () {
        if ($('#eh-filter-grade').val() && $('#eh-filter-section').val()) {
            loadStudents();
        }
    });

    /* ─── Render: unassigned panel ────────────────────────────────────────────── */
    function renderUnassigned(students) {
        const $body = $('#eh-student-panel-body').empty();
        $('#eh-student-count').text(students.length);

        if (!EH.canvas.gradeId && !EH.canvas.sectionId) {
            $body.html('<p style="color:#9ca3af;text-align:center;padding:24px;font-size:13px;">اختر الصف والشعبة<br>لعرض الطلاب</p>');
            return;
        }
        if (!students.length) {
            $body.html('<p style="color:#22c55e;text-align:center;padding:20px;">✓ جميع الطلاب موزعون</p>');
            return;
        }
        students.forEach((s, i) => $body.append(makeStudentCard(s, '')));
        bindDraggable($body.find('.eh-student-item'));
    }

    /* ─── Render: canvas grid ─────────────────────────────────────────────────── */
    function renderCanvas() {
        const $grid = $('#eh-canvas-grid').empty();

        if (!EH.canvas.halls.length) {
            $grid.html(`
                <div class="eh-canvas-empty">
                    <span class="dashicons dashicons-building" style="font-size:40px;width:40px;height:40px;color:#d1d5db;"></span>
                    <p>انقر <strong>"+إضافة قاعة"</strong> للبدء<br><em>Click "+ Add Hall" to start</em></p>
                </div>`);
            return;
        }

        EH.canvas.halls.forEach(hall => {
            const students = EH.canvas.assignments[hall.id] || [];
            $grid.append(makeHallCard(hall, students));
        });

        bindHallDrop($('.eh-hall-card'));
        bindRemoveButtons();
    }

    function makeHallCard(hall, students) {
        const cap  = parseInt(hall.capacity);
        const cnt  = students.length;
        const full = cnt >= cap;
        const pct  = cap > 0 ? Math.min(100, Math.round(cnt / cap * 100)) : 0;

        const $card = $('<div class="eh-hall-card">')
            .attr({ 'data-hall-id': hall.id, 'data-capacity': cap })
            .attr('id', 'eh-hall-' + hall.id)
            .toggleClass('full', full);

        // Header
        const $headerRight = $('<div style="display:flex;gap:6px;align-items:center;">').append(
            $('<span class="eh-hall-capacity-badge' + (full ? ' full' : '') + '">').text(cnt + '/' + cap),
            $('<button class="btn-eh-print-hall" type="button" title="طباعة هذه القاعة" style="background:rgba(255,255,255,.18);border:none;color:#fff;border-radius:5px;padding:3px 8px;cursor:pointer;font-size:12px;">').html('🖨')
                .data('hall-id', hall.id).data('hall-name', hall.hall_name),
            EH.isAdmin
                ? $('<button class="btn-remove-canvas-hall" type="button" title="Remove">✕</button>').data('hall-id', hall.id)
                : ''
        );
        $card.append($('<div class="eh-hall-card-header">').append(
            $('<h3>').html('<span class="dashicons dashicons-building" style="color:#fff;font-size:16px;"></span> ' + esc(hall.hall_name)),
            $headerRight
        ));

        // Body
        const $body = $('<div class="eh-hall-card-body">').toggleClass('is-empty', !cnt);
        if (!cnt) {
            $body.append($('<div class="eh-drop-hint">').text('← اسحب الطلاب هنا →'));
        } else {
            students.forEach((s, i) => $body.append(makeStudentCard(s, s.seat_number || i + 1)));
        }
        $card.append($body);

        // Progress bar
        $card.append($('<div class="eh-hall-progress">').append(
            $('<div class="eh-hall-progress-fill' + (full ? ' full' : '') + '">').css('width', pct + '%')
        ));

        return $card;
    }

    function makeStudentCard(s, seat) {
        const id   = s.id || s.student_id;
        const meta = [s.grade_name, s.section_name].filter(Boolean).join(' › ');
        return $('<div class="eh-student-item" draggable="' + (EH.isAdmin ? 'true' : 'false') + '">')
            .attr({ 'data-student-id': id, 'data-student-name': s.student_name })
            .append(
                $('<span class="seat-badge">').text(seat || ''),
                $('<div class="student-info">').append(
                    $('<div class="student-name">').text(s.student_name),
                    $('<div class="student-meta">').text(meta)
                ),
                EH.isAdmin ? $('<button class="btn-remove-student" type="button" title="Unassign">✕</button>') : ''
            );
    }

    function esc(str) {
        return $('<span>').text(str).html();
    }

    /* ─── Canvas badges in toolbar ────────────────────────────────────────────── */
    function renderCanvasBadges() {
        const $b = $('#eh-canvas-badges').empty();
        EH.canvas.halls.forEach(h => {
            $b.append($('<span class="eh-canvas-badge">').append(
                $('<span>').text(h.hall_name),
                $('<button class="btn-remove-canvas-hall" type="button" style="background:none;border:none;cursor:pointer;font-size:14px;color:#ef4444;">✕</button>')
                    .data('hall-id', h.id)
            ));
        });
    }

    /* ─── Stats ───────────────────────────────────────────────────────────────── */
    function updateStats() {
        let assigned = 0;
        EH.canvas.halls.forEach(h => {
            const cnt = (EH.canvas.assignments[h.id] || []).length;
            assigned += cnt;
            const cap  = parseInt(h.capacity);
            const pct  = cap ? Math.min(100, Math.round(cnt / cap * 100)) : 0;
            const $c   = $('#eh-hall-' + h.id);
            $c.find('.eh-hall-capacity-badge').text(cnt + '/' + cap).toggleClass('full', cnt >= cap);
            $c.toggleClass('full', cnt >= cap);
            $c.find('.eh-hall-progress-fill').css('width', pct + '%').toggleClass('full', pct >= 100);
        });

        const total      = EH.canvas.students.length;
        const unassigned = Math.max(0, total - assigned);

        $('#eh-stat-students').text(assigned);
        $('#eh-stat-unassigned').text(unassigned);
        $('#eh-student-count').text(unassigned);
    }

    /* ─── Hall picker ─────────────────────────────────────────────────────────── */
    $(document).on('click', '#btn-eh-add-hall', function () {
        const onCanvas = EH.canvas.halls.map(h => parseInt(h.id));
        const avail    = EH.allHalls.filter(h => !onCanvas.includes(parseInt(h.id)));

        const $list = $('#eh-hall-picker-list').empty();
        if (!avail.length) {
            $list.html('<p style="text-align:center;color:#6b7280;">كل القاعات مضافة للقماش بالفعل.</p>');
        } else {
            avail.forEach(h => {
                $list.append(
                    $('<div class="eh-hall-picker-item">').append(
                        $('<div>').append(
                            $('<strong>').text(h.hall_name),
                            $('<span style="margin-right:8px;color:#6b7280;font-size:13px;">').text('(' + h.capacity + ' طالب)')
                        ),
                        $('<button class="button button-primary btn-pick-hall" type="button">').text('+ إضافة').data('hall', h)
                    )
                );
            });
        }
        $('#eh-hall-picker-modal').addClass('active');
    });

    $(document).on('click', '.btn-pick-hall', function () {
        const hall = $(this).data('hall');
        if (!EH.canvas.halls.find(h => parseInt(h.id) === parseInt(hall.id))) {
            EH.canvas.halls.push(hall);
            if (!EH.canvas.assignments[hall.id]) EH.canvas.assignments[hall.id] = [];
        }
        $('#eh-hall-picker-modal').removeClass('active');
        renderCanvas();
        renderCanvasBadges();
        updateStats();
        saveState();

        // Reload students from server with updated canvas halls
        if (EH.canvas.gradeId || EH.canvas.sectionId) loadStudents();
        toast(hall.hall_name + ' أضيفت للقماش ✓', 'success');
    });

    // Remove hall from canvas
    $(document).on('click', '.btn-remove-canvas-hall', function (e) {
        e.stopPropagation();
        const hallId = parseInt($(this).data('hall-id'));
        const hall   = EH.canvas.halls.find(h => parseInt(h.id) === hallId);
        if (!hall) return;
        if (!confirm('إزالة "' + hall.hall_name + '" من القماش؟')) return;

        EH.canvas.halls = EH.canvas.halls.filter(h => parseInt(h.id) !== hallId);
        delete EH.canvas.assignments[hallId];
        renderCanvas();
        renderCanvasBadges();
        if (EH.canvas.gradeId || EH.canvas.sectionId) {
            // Refresh unassigned (students who were in that hall are now unassigned)
            renderUnassigned(getUnassignedList());
        }
        updateStats();
        saveState();
    });

    /* ─── Drag & Drop ─────────────────────────────────────────────────────────── */
    function bindDraggable($items) {
        if (!EH.isAdmin) return;
        $items.off('dragstart dragend').on('dragstart', function (e) {
            EH.dragSrc = {
                studentId:  $(this).data('student-id'),
                fromHallId: $(this).closest('.eh-hall-card').data('hall-id') || 'unassigned',
                $el:        $(this),
            };
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
        }).on('dragend', function () {
            $(this).removeClass('dragging');
        });
    }

    function bindHallDrop($cards) {
        if (!EH.isAdmin) return;
        $cards.off('dragover dragleave drop')
            .on('dragover', function (e) { e.preventDefault(); $(this).addClass('drag-over'); })
            .on('dragleave', function ()  { $(this).removeClass('drag-over'); })
            .on('drop', function (e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
                if (!EH.dragSrc) return;

                const toHallId = parseInt($(this).data('hall-id'));
                const fromId   = EH.dragSrc.fromHallId;
                if (toHallId === fromId) return;

                const hall = EH.canvas.halls.find(h => parseInt(h.id) === toHallId);
                const cnt  = (EH.canvas.assignments[toHallId] || []).length;
                if (hall && cnt >= parseInt(hall.capacity)) {
                    toast('القاعة ممتلئة / Hall is full', 'error');
                    return;
                }

                const sid     = parseInt(EH.dragSrc.studentId);
                const student = findStudentObj(sid, fromId);
                if (!student) return;

                // Optimistic move
                if (fromId !== 'unassigned') {
                    EH.canvas.assignments[fromId] = (EH.canvas.assignments[fromId] || [])
                        .filter(s => parseInt(s.id || s.student_id) !== sid);
                }
                if (!EH.canvas.assignments[toHallId]) EH.canvas.assignments[toHallId] = [];
                EH.canvas.assignments[toHallId].push(student);

                renderCanvas();
                renderUnassigned(getUnassignedList());
                updateStats();

                // Persist
                ajax('olama_eh_move_student', { hall_id: toHallId, student_id: sid }, function (err) {
                    if (err) { toast(err, 'error'); loadStudents(); }
                    else toast('تم النقل ✓', 'success');
                });
                EH.dragSrc = null;
            });
    }

    function bindRemoveButtons() {
        if (!EH.isAdmin) return;
        $(document).off('click.eh-rm', '.btn-remove-student').on('click.eh-rm', '.btn-remove-student', function (e) {
            e.stopPropagation();
            const $item  = $(this).closest('.eh-student-item');
            const sid    = parseInt($item.data('student-id'));
            const hallId = $item.closest('.eh-hall-card').data('hall-id');
            if (!hallId) return;

            EH.canvas.assignments[hallId] = (EH.canvas.assignments[hallId] || [])
                .filter(s => parseInt(s.id || s.student_id) !== sid);

            renderCanvas();
            renderUnassigned(getUnassignedList());
            updateStats();

            ajax('olama_eh_remove_student', { student_id: sid }, err => {
                if (err) toast(err, 'error');
            });
        });
    }

    // Unassigned panel drop zone
    function bindUnassignedDrop() {
        if (!EH.isAdmin) return;
        $('#eh-student-panel').off('dragover dragleave drop')
            .on('dragover', function (e) { e.preventDefault(); $(this).addClass('drag-over'); })
            .on('dragleave', function ()  { $(this).removeClass('drag-over'); })
            .on('drop', function (e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
                if (!EH.dragSrc || EH.dragSrc.fromHallId === 'unassigned') return;

                const sid    = parseInt(EH.dragSrc.studentId);
                const fromId = EH.dragSrc.fromHallId;

                EH.canvas.assignments[fromId] = (EH.canvas.assignments[fromId] || [])
                    .filter(s => parseInt(s.id || s.student_id) !== sid);

                renderCanvas();
                renderUnassigned(getUnassignedList());
                updateStats();

                ajax('olama_eh_remove_student', { student_id: sid }, err => {
                    if (err) { toast(err, 'error'); loadStudents(); }
                    else toast('أُعيد للقائمة / Returned', 'info');
                });
                EH.dragSrc = null;
            });
    }

    function findStudentObj(id, fromHallId) {
        if (fromHallId === 'unassigned') {
            return EH.canvas.students.find(s => parseInt(s.id) === id) || null;
        }
        const arr = EH.canvas.assignments[fromHallId] || [];
        return arr.find(s => parseInt(s.id || s.student_id) === id) || null;
    }

    function getUnassignedList() {
        const assignedSet = new Set();
        Object.values(EH.canvas.assignments).forEach(arr =>
            arr.forEach(s => assignedSet.add(parseInt(s.id || s.student_id)))
        );
        return EH.canvas.students.filter(s => !assignedSet.has(parseInt(s.id)));
    }

    /* ─── Auto Distribute ─────────────────────────────────────────────────────── */
    $(document).on('click', '#btn-eh-auto-distribute', function () {
        if (!EH.canvas.halls.length) { toast('أضف قاعة أولاً / Add halls first', 'error'); return; }
        if (!EH.canvas.gradeId && !EH.canvas.sectionId) { toast('اختر الصف والشعبة / Select grade and section first', 'error'); return; }

        const unassigned = getUnassignedList();
        if (!confirm('توزيع ' + unassigned.length + ' طالب على ' + EH.canvas.halls.length + ' قاعة؟\nDistribute ' + unassigned.length + ' students across ' + EH.canvas.halls.length + ' halls?')) return;

        const $btn = $(this).addClass('eh-loading');

        ajax('olama_eh_auto_distribute', {
            grade_id:        EH.canvas.gradeId,
            section_id:      EH.canvas.sectionId,
            canvas_hall_ids: EH.canvas.halls.map(h => h.id),
            clear_existing:  1,
        }, function (err, data) {
            $btn.removeClass('eh-loading');
            if (err) { toast(err, 'error'); return; }

            EH.canvas.assignments = data.assignments || {};
            EH.canvas.halls.forEach(h => {
                if (!EH.canvas.assignments[h.id]) EH.canvas.assignments[h.id] = [];
            });

            renderCanvas();
            renderUnassigned(data.unassigned || getUnassignedList());
            updateStats();
            toast(data.message, 'success', 5500);
        });
    });

    /* ─── Clear canvas context ────────────────────────────────────────────────── */
    $(document).on('click', '#btn-eh-clear-all', function () {
        if (!confirm('مسح توزيع الطلاب المحددين على القاعات؟ / Clear current assignments?')) return;

        ajax('olama_eh_clear_context', {
            grade_id:        EH.canvas.gradeId,
            section_id:      EH.canvas.sectionId,
            canvas_hall_ids: EH.canvas.halls.map(h => h.id),
        }, function (err, data) {
            if (err) { toast(err, 'error'); return; }
            EH.canvas.halls.forEach(h => EH.canvas.assignments[h.id] = []);
            renderCanvas();
            renderUnassigned(EH.canvas.students);
            updateStats();
            toast(data.message, 'info');
        });
    });

    /* ─── Print ───────────────────────────────────────────────────────────────── */

    /**
     * Safe HTML entity encoding – works in the popup window context too.
     */
    function h(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Open a rich HTML print report in a new tab.
     * @param {number|null} filterHallId – when set, only print that one hall.
     */
    function buildPrintReport(filterHallId) {
        const gradeName   = h($('#eh-filter-grade option:selected').text().trim());
        const sectionName = h($('#eh-filter-section option:selected').text().trim());
        const yearName    = h(olamaExamHall.yearName    || '');
        const semName     = h(olamaExamHall.semesterName || '');
        const today       = new Date().toLocaleDateString('ar-SA', { year:'numeric', month:'long', day:'numeric' });
        const schoolName  = h(document.querySelector('#wpadminbar .ab-item') ? (document.title.length > 4 ? document.title.split(/[–\-|]/)[0].trim() : '') : '');

        const halls = filterHallId
            ? EH.canvas.halls.filter(hall => parseInt(hall.id) === parseInt(filterHallId))
            : EH.canvas.halls;

        if (!halls.length) { toast('لا توجد بيانات للطباعة', 'error'); return; }

        let totalStudents = 0;
        let hallsHtml = '';

        halls.forEach((hall, hallIdx) => {
            const rawList = EH.canvas.assignments[hall.id] || [];
            // Sort by seat number, then name
            const students = rawList.slice().sort((a, b) => {
                const sa = parseInt(a.seat_number) || 9999;
                const sb = parseInt(b.seat_number) || 9999;
                return sa !== sb ? sa - sb : (a.student_name || '').localeCompare(b.student_name || '');
            });
            totalStudents += students.length;
            const pct = hall.capacity > 0 ? Math.min(100, Math.round(students.length / hall.capacity * 100)) : 0;

            const rowsHtml = students.map((s, i) => {
                const gradeCell   = h(s.grade_name   || '');
                const sectionCell = h(s.section_name || '');
                const nameCell    = h(s.student_name || '');
                const seatNo      = s.seat_number || (i + 1);
                const rowClass    = i % 2 === 0 ? '' : ' class="alt"';
                return `<tr${rowClass}>
                    <td class="seat">${seatNo}</td>
                    <td class="name">${nameCell}</td>
                    <td>${gradeCell}</td>
                    <td>${sectionCell}</td>
                    <td class="sig"></td>
                </tr>`;
            }).join('');

            const breakClass = hallIdx > 0 ? ' page-top' : '';
            hallsHtml += `
            <div class="hall-section${breakClass}">
                <div class="hall-header">
                    <div class="hall-title">🏛 ${h(hall.hall_name)}</div>
                    <div class="hall-chips">
                        <span class="chip">السعة: ${h(hall.capacity)}</span>
                        <span class="chip assigned">المُسجَّلون: ${students.length}</span>
                        <span class="chip fill">الامتلاء: ${pct}%</span>
                    </div>
                </div>
                <table class="student-table">
                    <thead>
                        <tr>
                            <th class="th-seat">م / Seat</th>
                            <th>اسم الطالب / Student Name</th>
                            <th class="th-sm">الصف</th>
                            <th class="th-sm">الشعبة</th>
                            <th class="th-sig">التوقيع / Sig.</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml || '<tr><td colspan="5" class="empty-row">لا يوجد طلاب مُوزَّعون في هذه القاعة</td></tr>'}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2" style="font-weight:700;padding:8px 12px;">الإجمالي: ${students.length} طالب</td>
                            <td colspan="3" style="text-align:left;padding:8px 12px;font-size:11px;color:#666;">Verified by: _____________ Date: _______</td>
                        </tr>
                    </tfoot>
                </table>
            </div>`;
        });

        const subTitle = filterHallId
            ? 'كشف قاعة: ' + h(halls[0] ? halls[0].hall_name : '')
            : 'كشف توزيع طلاب قاعات الاختبار';

        const contextLine = (gradeName || sectionName)
            ? `<div class="context-bar">
                <span>📋 الصف: <strong>${gradeName}</strong></span>
                <span>📌 الشعبة: <strong>${sectionName}</strong></span>
                <span>🏫 القاعات: <strong>${halls.length}</strong></span>
                <span>👥 إجمالي الطلاب: <strong>${totalStudents}</strong></span>
               </div>`
            : '';

        const html = `<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<title>${subTitle}</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body {
    font-family: 'Segoe UI', Tahoma, 'Arial', sans-serif;
    font-size: 13px; color: #111; background: #fff; direction: rtl;
  }
  /* ── Report Header ── */
  .report-header {
    text-align: center; padding: 22px 30px 16px;
    border-bottom: 3px solid #1a73e8; margin-bottom: 0;
  }
  .school-logo { font-size: 32px; margin-bottom: 4px; }
  .school-name { font-size: 20px; font-weight: 800; color: #1a1a2e; margin-bottom: 4px; }
  .report-title { font-size: 17px; font-weight: 700; color: #1a73e8; margin-bottom: 12px; }
  .meta-row {
    display: flex; justify-content: center; gap: 16px;
    flex-wrap: wrap; font-size: 12px;
  }
  .meta-chip {
    background: #f0f4ff; border: 1px solid #c4d4f5; border-radius: 14px;
    padding: 4px 14px; color: #374151;
  }
  /* ── Context Bar ── */
  .context-bar {
    background: linear-gradient(135deg,#1a73e8,#0d47a1);
    color: #fff; padding: 10px 24px;
    display: flex; gap: 24px; align-items: center;
    flex-wrap: wrap; font-size: 13px; margin-bottom: 22px;
  }
  .context-bar strong { font-size: 15px; }
  /* ── Hall Section ── */
  .hall-section { margin: 0 20px 30px; }
  .hall-section.page-top { page-break-before: auto; }
  .hall-header {
    display: flex; justify-content: space-between; align-items: center;
    background: linear-gradient(135deg,#1a73e8,#0d47a1);
    color: #fff; padding: 11px 18px;
    border-radius: 10px 10px 0 0;
  }
  .hall-title { font-size: 16px; font-weight: 700; }
  .hall-chips { display: flex; gap: 10px; font-size: 12px; }
  .chip {
    background: rgba(255,255,255,.2); border-radius: 12px;
    padding: 2px 10px; white-space: nowrap;
  }
  .chip.assigned { background: rgba(34,197,94,.3); }
  .chip.fill     { background: rgba(251,191,36,.3); }
  /* ── Student Table ── */
  .student-table {
    width: 100%; border-collapse: collapse;
    border: 1px solid #d1d5db;
    border-radius: 0 0 8px 8px; overflow: hidden;
  }
  .student-table thead tr { background: #e8f0fe; }
  .student-table th {
    padding: 9px 12px; text-align: right;
    font-size: 12px; font-weight: 700; color: #1a1a2e;
    border: 1px solid #c4d4f5;
  }
  .student-table td {
    padding: 8px 12px; border: 1px solid #e5e7eb;
    font-size: 13px; vertical-align: middle;
  }
  .student-table tr.alt td { background: #f0f4ff; }
  .student-table tbody tr:hover td { background: #e8f0fe; }
  .student-table td.seat {
    text-align: center; font-weight: 800;
    color: #1a73e8; font-size: 14px; width: 55px;
  }
  .student-table td.name { font-weight: 600; min-width: 180px; }
  .student-table td.sig  { min-width: 100px; border-bottom: 1px solid #9ca3af; }
  .student-table td.empty-row { text-align:center; color:#9ca3af; padding: 20px; }
  .th-seat { width: 55px; text-align: center; }
  .th-sm   { width: 80px; }
  .th-sig  { width: 110px; }
  .student-table tfoot td {
    background: #f8fafc; font-size: 12px;
    border-top: 2px solid #d1d5db;
  }
  /* ── Summary Box ── */
  .summary-box {
    margin: 10px 20px 30px;
    padding: 14px 20px;
    background: #f0fdf4; border: 2px solid #86efac; border-radius: 10px;
    display: flex; gap: 30px; flex-wrap: wrap; font-size: 13px;
  }
  .summary-box .sval { font-size: 20px; font-weight: 800; color: #166534; display: block; }
  .summary-box .slbl { font-size: 11px; color: #4b7c59; }
  /* ── Footer ── */
  .report-footer {
    text-align: center; margin: 20px; padding: 12px 0;
    border-top: 1px solid #e5e7eb; font-size: 11px; color: #9ca3af;
  }
  /* ── Print ── */
  @media print {
    @page { size: A4 portrait; margin: 12mm 10mm; }
    .hall-section { page-break-inside: avoid; }
    .hall-section.page-top { page-break-before: always; }
    .no-print { display: none !important; }
    body { font-size: 12px; }
  }
  /* ── Screen only ── */
  @media screen {
    body { background: #f3f4f6; padding: 0 0 40px; }
    .report-header { background:#fff; box-shadow:0 2px 10px rgba(0,0,0,.08); }
    .hall-section { background:#fff; border-radius:12px; box-shadow:0 4px 16px rgba(0,0,0,.07); overflow:hidden; }
    .summary-box { box-shadow:0 2px 8px rgba(0,0,0,.05); }
    .print-btn-bar {
      position: sticky; top: 0; z-index: 100;
      background: #1a1a2e; color:#fff;
      padding: 10px 24px;
      display: flex; justify-content: space-between; align-items: center;
    }
    .print-btn {
      background: #1a73e8; color:#fff; border:none; border-radius:8px;
      padding: 8px 22px; font-size:14px; font-weight:700; cursor:pointer;
    }
    .print-btn:hover { background:#0d47a1; }
    .close-btn {
      background: #374151; color:#fff; border:none; border-radius:8px;
      padding: 8px 18px; font-size:13px; cursor:pointer; margin-right: 8px;
    }
  }
</style>
</head>
<body>
  <div class="print-btn-bar no-print">
    <span style="font-size:15px;font-weight:600;">🖨 معاينة الطباعة — ${subTitle}</span>
    <div>
      <button class="close-btn" onclick="window.close()">✕ إغلاق</button>
      <button class="print-btn" onclick="window.print()">🖨 طباعة الآن</button>
    </div>
  </div>

  <div class="report-header">
    <div class="school-logo">🏫</div>
    ${schoolName ? '<div class="school-name">' + schoolName + '</div>' : ''}
    <div class="report-title">${subTitle}</div>
    <div class="meta-row">
      <span class="meta-chip">📅 العام الدراسي: ${yearName}</span>
      <span class="meta-chip">📚 الفصل: ${semName}</span>
      <span class="meta-chip">🗓 تاريخ الطباعة: ${today}</span>
    </div>
  </div>

  ${contextLine}

  ${hallsHtml}

  <div class="summary-box">
    <div><span class="sval">${totalStudents}</span><span class="slbl">إجمالي الطلاب الموزعين</span></div>
    <div><span class="sval">${halls.length}</span><span class="slbl">عدد القاعات</span></div>
    <div><span class="sval">${halls.reduce((s,h) => s + parseInt(h.capacity||0), 0)}</span><span class="slbl">إجمالي السعة</span></div>
  </div>

  <div class="report-footer">
    تم إعداد هذا الكشف بواسطة نظام الإدارة المدرسية &nbsp;|&nbsp; ${new Date().toLocaleString('ar-SA')}
  </div>

  <script>window.addEventListener('afterprint', function(){ /* optional */ });<\/script>
</body>
</html>`;

        const popup = window.open('', '_blank', 'width=900,height=700,scrollbars=yes');
        if (!popup) { toast('يرجى السماح بالنوافذ المنبثقة / Allow popups', 'error', 5000); return; }
        popup.document.open();
        popup.document.write(html);
        popup.document.close();
    }

    // Full canvas print button
    $(document).on('click', '#btn-eh-print', function () {
        if (!EH.canvas.halls.length) { toast('لا توجد قاعات على القماش / No halls on canvas', 'error'); return; }
        buildPrintReport(null);
    });

    // Per-hall print button (on each hall card)
    $(document).on('click', '.btn-eh-print-hall', function (e) {
        e.stopPropagation();
        const hallId = $(this).data('hall-id');
        buildPrintReport(hallId);
    });

    /* ─── Modal close ─────────────────────────────────────────────────────────── */
    $(document).on('click', '.eh-modal-close, .eh-modal-overlay', function (e) {
        if ($(e.target).is('.eh-modal-overlay') || $(e.target).is('.eh-modal-close') || $(e.target).closest('.eh-modal-close').length) {
            $('.eh-modal-overlay').removeClass('active');
        }
    });

    /* ─── Hall Form (Halls Tab) ───────────────────────────────────────────────── */
    $(document).on('click', '#btn-eh-add-hall-form', function () {
        $('#eh-hall-id').val('');
        $('#eh-hall-form')[0] && $('#eh-hall-form')[0].reset();
        $('#eh-hall-modal').addClass('active');
    });

    $(document).on('click', '.btn-eh-edit-hall', function () {
        $('#eh-hall-id').val($(this).data('hall-id'));
        $('#eh-hall-name').val($(this).data('hall-name'));
        $('#eh-hall-capacity').val($(this).data('hall-cap'));
        $('#eh-hall-modal').addClass('active');
    });

    $(document).on('submit', '#eh-hall-form', function (e) {
        e.preventDefault();
        const $btn = $(this).find('[type=submit]').addClass('eh-loading');
        ajax('olama_eh_save_hall', {
            id:        $('#eh-hall-id').val(),
            hall_name: $('#eh-hall-name').val(),
            capacity:  $('#eh-hall-capacity').val(),
        }, function (err, data) {
            $btn.removeClass('eh-loading');
            if (err) { toast(err, 'error'); return; }
            $('#eh-hall-modal').removeClass('active');
            // Update global hall list
            EH.allHalls = data.halls || EH.allHalls;
            renderHallMgmt(data.halls);
            toast(data.message, 'success');
        });
    });

    $(document).on('click', '.btn-eh-delete-hall', function () {
        const hallId = $(this).data('hall-id');
        const name   = $(this).data('hall-name');
        if (!confirm('حذف قاعة "' + name + '"؟')) return;
        ajax('olama_eh_delete_hall', { hall_id: hallId }, function (err, data) {
            if (err) { toast(err, 'error'); return; }
            EH.allHalls = data.halls || [];
            renderHallMgmt(data.halls);
            toast(data.message, 'info');
        });
    });

    function renderHallMgmt(halls) {
        const $g = $('#eh-halls-manage-grid').empty();
        if (!halls || !halls.length) { $g.html('<p style="text-align:center;color:#6b7280;padding:30px;">لا توجد قاعات.</p>'); return; }
        halls.forEach(h => {
            $g.append($('<div class="eh-hall-manage-card">').append(
                $('<div class="hall-name">').html('<span class="dashicons dashicons-building" style="color:#1a73e8;"></span> ' + esc(h.hall_name)),
                $('<div class="hall-cap">').text('السعة: ' + h.capacity + ' طالب'),
                $('<div class="hall-actions">').append(
                    $('<button class="button btn-eh-edit-hall">').attr({ 'data-hall-id': h.id, 'data-hall-name': h.hall_name, 'data-hall-cap': h.capacity })
                        .html('<span class="dashicons dashicons-edit"></span> تعديل'),
                    $('<button class="button btn-eh-delete-hall">').attr({ 'data-hall-id': h.id, 'data-hall-name': h.hall_name })
                        .css({ color: '#dc2626' })
                        .html('<span class="dashicons dashicons-trash"></span> حذف')
                )
            ));
        });
    }

    /* ─── Attendance Tab ──────────────────────────────────────────────────────── */
    $(document).on('change', '#eh-att-hall', function () {
        const hallId = $(this).val();
        if (!hallId) { $('#eh-att-table-wrap').hide(); return; }
        ajax('olama_eh_get_students', { hall_id: hallId }, function (err, data) {
            if (err) return;
            const students = data.students || [];
            const $tbody = $('#eh-att-tbody').empty();
            if (!students.length) {
                $tbody.append('<tr><td colspan="4" style="text-align:center;padding:20px;color:#6b7280;">لا يوجد طلاب في هذه القاعة</td></tr>');
                $('#eh-att-table-wrap').show();
                return;
            }
            students.forEach(s => {
                const sid = s.student_id || s.id;
                $tbody.append($('<tr>').append(
                    $('<td>').text(s.seat_number || ''),
                    $('<td>').html('<strong>' + esc(s.student_name) + '</strong>'),
                    $('<td>').text([s.grade_name, s.section_name].filter(Boolean).join(' / ')),
                    $('<td>').append(
                        $('<div class="status-toggle">').append(
                            $('<button class="status-btn present active" type="button">').data({ student_id: sid, status: 'present' }).text('حاضر'),
                            $('<button class="status-btn absent" type="button">').data({ student_id: sid, status: 'absent' }).text('غائب')
                        )
                    )
                ));
            });
            $('#print-att-date').text($('#eh-att-date').val());
            $('#print-att-session').text($('#eh-att-session').val());
            $('#eh-att-table-wrap').show();
        });
    });

    $(document).on('click', '.status-btn', function () {
        $(this).closest('.status-toggle').find('.status-btn').removeClass('active');
        $(this).addClass('active');
    });

    $(document).on('click', '#btn-mark-all-present', function () {
        $('#eh-att-tbody .status-toggle').each(function () {
            $(this).find('.status-btn').removeClass('active');
            $(this).find('.present').addClass('active');
        });
    });
    $(document).on('click', '#btn-mark-all-absent', function () {
        $('#eh-att-tbody .status-toggle').each(function () {
            $(this).find('.status-btn').removeClass('active');
            $(this).find('.absent').addClass('active');
        });
    });

    $(document).on('click', '#btn-eh-save-attendance', function () {
        const hallId  = $('#eh-att-hall').val();
        const date    = $('#eh-att-date').val();
        const session = $('#eh-att-session').val() || '';
        if (!hallId || !date) { toast('اختر قاعة وتاريخاً', 'error'); return; }

        const statuses = {};
        $('#eh-att-tbody .status-btn.active').each(function () {
            statuses[$(this).data('student_id')] = $(this).data('status');
        });

        const $btn = $(this).addClass('eh-loading');
        ajax('olama_eh_save_attendance', { hall_id: hallId, exam_date: date, session_label: session, statuses }, function (err, data) {
            $btn.removeClass('eh-loading');
            if (err) toast(err, 'error'); else toast(data.message, 'success');
        });
    });

    /* ─── Notes Tab ───────────────────────────────────────────────────────────── */
    $(document).on('change', '#eh-notes-hall', function () {
        const hallId = $(this).val();
        if (!hallId) return;
        ajax('olama_eh_get_students', { hall_id: hallId }, function (err, data) {
            if (err) return;
            const $sel = $('#eh-notes-student').empty().append('<option value="">-- اختر الطالب --</option>');
            (data.students || []).forEach(s => $sel.append($('<option>').val(s.student_id || s.id).text(s.student_name)));
        });
    });

    $(document).on('submit', '#eh-note-form', function (e) {
        e.preventDefault();
        const $btn = $(this).find('[type=submit]').addClass('eh-loading');
        ajax('olama_eh_save_note', {
            hall_id:    $('#eh-notes-hall').val(),
            student_id: $('#eh-notes-student').val(),
            exam_date:  $('#eh-notes-date').val() || new Date().toISOString().slice(0, 10),
            note_type:  $('#eh-note-type').val(),
            note_text:  $('#eh-note-text').val(),
        }, function (err, data) {
            $btn.removeClass('eh-loading');
            if (err) { toast(err, 'error'); return; }
            toast(data.message, 'success');
            $('#eh-note-form')[0].reset();
        });
    });

    $(document).on('click', '.btn-eh-delete-note', function () {
        const noteId = $(this).data('note-id');
        if (!confirm('حذف هذه الملاحظة؟')) return;
        ajax('olama_eh_delete_note', { note_id: noteId }, function (err, data) {
            if (err) { toast(err, 'error'); return; }
            toast(data.message, 'info');
        });
    });

    /* ─── Init ────────────────────────────────────────────────────────────────── */
    $(function () {
        const today = new Date().toISOString().slice(0, 10);
        if ($('#eh-att-date').length && !$('#eh-att-date').val()) $('#eh-att-date').val(today);
        if ($('#eh-notes-date').length && !$('#eh-notes-date').val()) $('#eh-notes-date').val(today);

        if ($('#eh-canvas-grid').length) {
            bindUnassignedDrop();
            // Restore previous canvas if any
            const restored = restoreState();
            renderCanvas();
            renderCanvasBadges();
            if (restored && (EH.canvas.halls.length > 0) &&
                (EH.canvas.gradeId || EH.canvas.sectionId)) {
                loadStudents();
            }
        }
    });

})(jQuery);
