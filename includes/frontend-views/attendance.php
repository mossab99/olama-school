<?php if (!defined('ABSPATH')) { exit; }
$present = 0; $absent = 0;
foreach ($students as $student) {
    if (($attendance_records[$student->student_uid]->status ?? 'present') === 'absent') { $absent++; } else { $present++; }
}
?>
<link rel="stylesheet" href="<?php echo esc_url(OLAMA_SCHOOL_URL . 'assets/css/attendance.css?ver=' . filemtime(OLAMA_SCHOOL_PATH . 'assets/css/attendance.css')); ?>">
<div class="olama-attendance-shortcode" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
    <div class="olama-attendance-header">
        <h2><?php echo esc_html(Olama_School_Helpers::translate('Daily Attendance')); ?></h2>
        <p><?php echo esc_html($active_year->year_name ?? ''); ?> · <?php echo esc_html($active_semester->semester_name ?? ''); ?></p>
        <form method="get">
            <?php if (is_admin()): ?><input type="hidden" name="page" value="olama-dashboard"><input type="hidden" name="view" value="attendance"><?php endif; ?>
            <?php if (get_query_var('page_id')): ?><input type="hidden" name="page_id" value="<?php echo esc_attr(get_query_var('page_id')); ?>"><?php endif; ?>
            <input type="hidden" name="section_id" value="<?php echo esc_attr($section_id); ?>">
            <input type="hidden" name="grade_id" value="<?php echo esc_attr($grade_id); ?>">
            <label><?php echo esc_html(Olama_School_Helpers::translate('Date:')); ?> <input type="date" name="attendance_date" value="<?php echo esc_attr($attendance_date); ?>" onchange="this.form.submit()"></label>
        </form>
    </div>
    <div class="olama-section-selector">
        <?php foreach ($allowed_sections as $section): ?>
            <a class="olama-section-chip <?php echo (int) $section->id === $section_id ? 'olama-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(array('section_id' => $section->id, 'grade_id' => $section->grade_id, 'message' => false))); ?>">
                <?php echo esc_html(($section->grade_name ?? '') . ' - ' . $section->section_name); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if (!$active_year || !$active_semester): ?>
        <p><?php esc_html_e('Set an active academic year and semester before recording attendance.', 'olama-school'); ?></p>
    <?php elseif (!$allowed_sections): ?>
        <p><?php esc_html_e('No classes are assigned to you for the active academic year.', 'olama-school'); ?></p>
    <?php elseif (!$students): ?>
        <p><?php echo esc_html(Olama_School_Helpers::translate('No students found in this section.')); ?></p>
    <?php else: ?>
        <div class="olama-attendance-summary-cards">
            <div class="olama-summary-card olama-attendance-status <?php echo $attendance_taken ? 'olama-taken' : 'olama-not-taken'; ?>" role="status" aria-live="polite" data-attendance-status>
                <span><?php echo esc_html(Olama_School_Helpers::translate('Attendance Status')); ?></span>
                <strong data-attendance-status-value><?php echo esc_html(Olama_School_Helpers::translate($attendance_taken ? 'Taken' : 'Not Taken')); ?></strong>
            </div>
            <div class="olama-summary-card olama-present"><span><?php echo esc_html(Olama_School_Helpers::translate('Present')); ?></span><strong class="olama-card-value" data-present-count><?php echo (int) $present; ?></strong></div>
            <div class="olama-summary-card olama-absent"><span><?php echo esc_html(Olama_School_Helpers::translate('Absent')); ?></span><strong class="olama-card-value" data-absent-count><?php echo (int) $absent; ?></strong></div>
        </div>
        <form method="post" class="olama-mobile-attendance-form">
            <?php wp_nonce_field('olama_frontend_attendance'); ?>
            <input type="hidden" name="olama_frontend_attendance" value="1">
            <input type="hidden" name="section_id" value="<?php echo esc_attr($section_id); ?>">
            <input type="hidden" name="attendance_date" value="<?php echo esc_attr($attendance_date); ?>">
            <div class="olama-attendance-actions"><button type="button" class="olama-btn-all-present"><?php echo esc_html(Olama_School_Helpers::translate('All Present')); ?></button><p role="status" aria-live="polite" class="olama-save-status"></p></div>
            <div class="olama-students-grid">
                <?php foreach ($students as $student): $record = $attendance_records[$student->student_uid] ?? null; $status = ($record->status ?? 'present') === 'absent' ? 'absent' : 'present'; ?>
                    <div class="olama-student-card">
                        <button type="button" class="olama-attendance-btn olama-<?php echo esc_attr($status); ?>" aria-pressed="<?php echo $status === 'absent' ? 'true' : 'false'; ?>">
                            <span class="olama-student-name"><?php echo esc_html($student->student_name); ?></span>
                            <span class="olama-student-status"><span class="olama-status-present"><?php echo esc_html(Olama_School_Helpers::translate('Present')); ?></span><span class="olama-status-absent"><?php echo esc_html(Olama_School_Helpers::translate('Absent')); ?></span></span>
                        </button>
                        <input type="hidden" class="olama-status-value" name="attendance[<?php echo esc_attr($student->id); ?>][status]" value="<?php echo esc_attr($status); ?>">
                        <input type="text" class="olama-reason" name="attendance[<?php echo esc_attr($student->id); ?>][reason]" value="<?php echo esc_attr($record->reason ?? ''); ?>" aria-label="<?php echo esc_attr(Olama_School_Helpers::translate('Reason (if absent)') . ': ' . $student->student_name); ?>" placeholder="<?php echo esc_attr(Olama_School_Helpers::translate('Reason...')); ?>" <?php echo $status === 'present' ? 'hidden' : ''; ?>>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="olama-attendance-footer"><p><?php echo esc_html(Olama_School_Helpers::translate('Click on a student name to mark them as absent (Red) or present (Blue). Changes are saved instantly.')); ?></p><button type="submit"><?php echo esc_html(Olama_School_Helpers::translate('Save Attendance')); ?></button></div>
        </form>
        <script>
        (function () {
            const root = document.currentScript.closest('.olama-attendance-shortcode');
            const form = root.querySelector('.olama-mobile-attendance-form');
            const cards = Array.from(form.querySelectorAll('.olama-student-card'));
            const message = form.querySelector('.olama-save-status');
            let busy = false;
            let confirmed = cards.map(c => ({status: c.querySelector('.olama-status-value').value, reason: c.querySelector('.olama-reason').value}));
            function refresh() {
                let absent = 0;
                cards.forEach(c => {
                    const isAbsent = c.querySelector('.olama-status-value').value === 'absent';
                    const button = c.querySelector('.olama-attendance-btn');
                    button.classList.toggle('olama-absent', isAbsent); button.classList.toggle('olama-present', !isAbsent);
                    button.setAttribute('aria-pressed', String(isAbsent)); c.querySelector('.olama-reason').hidden = !isAbsent;
                    if (isAbsent) absent++;
                });
                root.querySelector('[data-present-count]').textContent = cards.length - absent;
                root.querySelector('[data-absent-count]').textContent = absent;
            }
            async function save() {
                if (busy) return;
                busy = true;
                const data = new FormData(form); data.set('action', 'olama_frontend_attendance_save');
                form.querySelectorAll('button, .olama-reason').forEach(el => el.disabled = true);
                message.textContent = <?php echo wp_json_encode(__('Saving...', 'olama-school')); ?>;
                try {
                    const response = await fetch(<?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, {method: 'POST', body: data, credentials: 'same-origin'});
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error();
                    confirmed = cards.map(c => ({status: c.querySelector('.olama-status-value').value, reason: c.querySelector('.olama-reason').value}));
                    message.textContent = result.data.message;
                    const statusCard = root.querySelector('[data-attendance-status]');
                    statusCard.classList.remove('olama-not-taken');
                    statusCard.classList.add('olama-taken');
                    statusCard.querySelector('[data-attendance-status-value]').textContent = <?php echo wp_json_encode(Olama_School_Helpers::translate('Taken')); ?>;
                } catch (error) {
                    cards.forEach((c, i) => {c.querySelector('.olama-status-value').value = confirmed[i].status; c.querySelector('.olama-reason').value = confirmed[i].reason;}); refresh();
                    message.textContent = <?php echo wp_json_encode(__('Attendance could not be saved. Please try again.', 'olama-school')); ?>;
                } finally {
                    busy = false; form.querySelectorAll('button, .olama-reason').forEach(el => el.disabled = false);
                }
            }
            cards.forEach(c => {
                c.querySelector('button').addEventListener('click', () => {if (busy) return; const value = c.querySelector('.olama-status-value'); value.value = value.value === 'present' ? 'absent' : 'present'; refresh(); save();});
                c.querySelector('.olama-reason').addEventListener('change', save);
            });
            form.querySelector('.olama-btn-all-present').addEventListener('click', () => {
                if (busy || !confirm(<?php echo wp_json_encode(Olama_School_Helpers::translate('Are you sure everyone is present? This will reset all current marks for this section today.')); ?>)) return;
                cards.forEach(c => {c.querySelector('.olama-status-value').value = 'present'; c.querySelector('.olama-reason').value = '';}); refresh(); save();
            });
            form.addEventListener('submit', e => {e.preventDefault(); save();});
        })();
        </script>
    <?php endif; ?>
</div>
