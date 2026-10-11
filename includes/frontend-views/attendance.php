<?php if (!defined("ABSPATH")) { exit; } ?>
<div class="olama-attendance-shortcode" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
<div class="olama-header-section" style="margin-bottom:25px;">
    <h2 style="margin:0;font-size:1.5em;"><?php echo esc_html(Olama_School_Helpers::translate('Student Attendance')); ?></h2>
    <p class="description"><?php echo esc_html(Olama_School_Helpers::translate('Record daily attendance for students by grade and section.')); ?></p>
</div>

<?php if (isset($_GET['message']) && 'attendance_saved' === sanitize_key(wp_unslash($_GET['message']))): ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html(Olama_School_Helpers::translate('Attendance saved successfully.')); ?></p></div>
<?php endif; ?>

<?php if (!$active_year || !$active_semester): ?>
    <div class="notice notice-warning"><p><?php esc_html_e('Set an active academic year and semester before recording attendance.', 'olama-school'); ?></p></div>
<?php endif; ?>

<div class="attendance-filters card" style="padding:15px;margin-bottom:20px;max-width:100%;">
    <form method="get" action="">
        <?php if (is_admin()): ?>
            <input type="hidden" name="page" value="olama-dashboard">
            <input type="hidden" name="view" value="attendance">
        <?php endif; ?>
        <?php if (get_query_var("page_id")): ?><input type="hidden" name="page_id" value="<?php echo esc_attr(get_query_var("page_id")); ?>"><?php endif; ?>
        
        <div style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;">
            <div><label><strong><?php echo esc_html(Olama_School_Helpers::translate('Academic Year:')); ?></strong></label><br>
                <input type="text" value="<?php echo esc_attr($active_year->year_name ?? ''); ?>" readonly disabled class="regular-text" style="width:150px;"></div>
            <div><label><strong><?php echo esc_html(Olama_School_Helpers::translate('Semester:')); ?></strong></label><br>
                <input type="text" value="<?php echo esc_attr($active_semester->semester_name ?? ''); ?>" readonly disabled class="regular-text" style="width:150px;"></div>
            <div><label for="attendance-grade"><strong><?php echo esc_html(Olama_School_Helpers::translate('Grade:')); ?></strong></label><br>
                <select id="attendance-grade" name="grade_id" onchange="this.form.elements.section_id.value='';this.form.submit()">
                    <option value=""><?php echo esc_html(Olama_School_Helpers::translate('Select Grade')); ?></option>
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?php echo esc_attr($grade->id); ?>" <?php selected($grade_id, $grade->id); ?>><?php echo esc_html($grade->grade_name); ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label for="attendance-section"><strong><?php echo esc_html(Olama_School_Helpers::translate('Section:')); ?></strong></label><br>
                <select id="attendance-section" name="section_id" onchange="this.form.submit()" <?php disabled(empty($sections)); ?>>
                    <option value=""><?php echo esc_html(Olama_School_Helpers::translate('Select Section')); ?></option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo esc_attr($section->id); ?>" <?php selected($section_id, $section->id); ?>><?php echo esc_html($section->section_name); ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label for="attendance-date"><strong><?php echo esc_html(Olama_School_Helpers::translate('Date:')); ?></strong></label><br>
                <input id="attendance-date" type="date" name="attendance_date" value="<?php echo esc_attr($attendance_date); ?>" onchange="this.form.submit()"></div>
        </div>
    </form>
</div>

<?php if ($section_id && $active_year && $active_semester): ?>
    <form method="post" action="">
        <?php wp_nonce_field('olama_frontend_attendance'); ?>
        <input type="hidden" name="olama_frontend_attendance" value="1">
        <input type="hidden" name="section_id" value="<?php echo esc_attr($section_id); ?>">
        <input type="hidden" name="attendance_date" value="<?php echo esc_attr($attendance_date); ?>">
        <input type="hidden" name="academic_year_id" value="<?php echo esc_attr($active_year->id); ?>">
        <input type="hidden" name="semester_id" value="<?php echo esc_attr($active_semester->id); ?>">
        <table class="wp-list-table widefat fixed striped">
            <thead><tr>
                <th width="100"><?php echo esc_html(Olama_School_Helpers::translate('ID')); ?></th>
                <th><?php echo esc_html(Olama_School_Helpers::translate('Student Name')); ?></th>
                <th width="160"><?php echo esc_html(Olama_School_Helpers::translate('Status')); ?></th>
                <th><?php echo esc_html(Olama_School_Helpers::translate('Reason (if absent)')); ?></th>
            </tr></thead>
            <tbody>
                <?php if (!$students): ?>
                    <tr><td colspan="4"><?php echo esc_html(Olama_School_Helpers::translate('No students found in this section.')); ?></td></tr>
                <?php else: foreach ($students as $student):
                    $record = $attendance_records[$student->student_uid] ?? null;
                    $status = $record ? $record->status : 'present';
                    $reason = $record ? $record->reason : '';
                ?>
                    <tr>
                        <td><?php echo esc_html($student->student_uid); ?></td>
                        <td><strong><?php echo esc_html($student->student_name); ?></strong></td>
                        <td><select name="attendance[<?php echo esc_attr($student->id); ?>][status]" class="attendance-status-selector">
                            <option value="present" <?php selected($status, 'present'); ?>><?php echo esc_html(Olama_School_Helpers::translate('Present')); ?></option>
                            <option value="absent" <?php selected($status, 'absent'); ?>><?php echo esc_html(Olama_School_Helpers::translate('Absent')); ?></option>
                        </select></td>
                        <td><input type="text" name="attendance[<?php echo esc_attr($student->id); ?>][reason]" value="<?php echo esc_attr($reason); ?>" class="large-text" placeholder="<?php echo esc_attr(Olama_School_Helpers::translate('Reason...')); ?>"></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <p class="submit"><button type="submit" class="button button-primary button-large"><?php echo esc_html(Olama_School_Helpers::translate('Save Attendance')); ?></button></p>
    </form>
<?php else: ?>
    <div class="notice notice-info"><p><?php echo esc_html(Olama_School_Helpers::translate('Please select a Grade and Section to load students.')); ?></p></div>
<?php endif; ?>

</div>
