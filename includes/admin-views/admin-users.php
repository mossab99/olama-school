<?php
/**
 * Admin Users Page - Students, Teachers, Admins
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap olama-school-wrap">
    <h1>
        <?php _e('Users & Permissions', 'olama-school'); ?>
    </h1>

    <h2 class="nav-tab-wrapper">
        <a href="?page=olama-school-users&tab=students"
            class="nav-tab <?php echo $active_tab === 'students' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Students', 'olama-school'); ?>
        </a>
        <a href="?page=olama-school-users&tab=teachers"
            class="nav-tab <?php echo $active_tab === 'teachers' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Teachers', 'olama-school'); ?>
        </a>
        <a href="?page=olama-school-users&tab=permissions"
            class="nav-tab <?php echo $active_tab === 'permissions' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Permissions', 'olama-school'); ?>
        </a>
        <a href="?page=olama-school-users&tab=logs"
            class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Activity Logs', 'olama-school'); ?>
        </a>
    </h2>

    <div class="olama-tab-content" style="margin-top: 20px;">
        <?php if ($active_tab === 'students'): ?>
            <!-- Students Tab -->
            <div class="olama-flex" style="display: flex; gap: 20px;">
                <div class="olama-main-col" style="flex: 2;">
                    <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                        <h2>
                            <?php _e('Students', 'olama-school'); ?>
                        </h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>
                                        <?php _e('Name', 'olama-school'); ?>
                                    </th>
                                    <th>
                                        <?php _e('ID Number', 'olama-school'); ?>
                                    </th>
                                    <th>
                                        <?php _e('Grade', 'olama-school'); ?>
                                    </th>
                                    <th>
                                        <?php _e('Section', 'olama-school'); ?>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($students): ?>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td>
                                                <?php echo esc_html($student->student_name); ?>
                                            </td>
                                            <td>
                                                <?php echo esc_html($student->student_id_number); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $grade = Olama_School_Grade::get_grade($student->grade_id);
                                                echo $grade ? esc_html($grade->grade_name) : '-';
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                $section = Olama_School_Section::get_section($student->section_id);
                                                echo $section ? esc_html($section->section_name) : '-';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4">
                                            <?php _e('No students found.', 'olama-school'); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="olama-side-col" style="flex: 1;">
                    <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                        <h2>
                            <?php _e('Add Student', 'olama-school'); ?>
                        </h2>
                        <form method="post" action="">
                            <?php wp_nonce_field('olama_add_student'); ?>
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <?php _e('Name', 'olama-school'); ?>
                                    </th>
                                    <td><input type="text" name="student_name" required class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php _e('ID Number', 'olama-school'); ?>
                                    </th>
                                    <td><input type="text" name="student_id_number" required class="regular-text" /></td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php _e('Grade', 'olama-school'); ?>
                                    </th>
                                    <td>
                                        <select name="grade_id" required>
                                            <option value="">
                                                <?php _e('Select Grade', 'olama-school'); ?>
                                            </option>
                                            <?php foreach ($grades as $grade): ?>
                                                <option value="<?php echo $grade->id; ?>">
                                                    <?php echo esc_html($grade->grade_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <?php _e('Section', 'olama-school'); ?>
                                    </th>
                                    <td>
                                        <select name="section_id" required>
                                            <option value="">
                                                <?php _e('Select Section', 'olama-school'); ?>
                                            </option>
                                            <?php foreach ($sections as $section): ?>
                                                <option value="<?php echo $section->id; ?>">
                                                    <?php echo esc_html($section->section_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <?php submit_button(__('Add Student', 'olama-school'), 'primary', 'add_student'); ?>
                        </form>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab === 'teachers'): ?>
            <!-- Teachers Tab -->
            <div class="olama-card" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
                <h2>
                    <?php _e('Teachers (WordPress Users with Teacher Role)', 'olama-school'); ?>
                </h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>
                                <?php _e('Name', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('Email', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('Employee ID', 'olama-school'); ?>
                            </th>
                            <th>
                                <?php _e('Phone', 'olama-school'); ?>
                            </th>
                            <th style="width: 100px;">
                                <?php _e('Actions', 'olama-school'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($teachers): ?>
                            <?php foreach ($teachers as $teacher): ?>
                                <tr>
                                    <td>
                                        <?php echo esc_html($teacher->display_name); ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($teacher->user_email); ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($teacher->employee_id ?? '-'); ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($teacher->phone_number ?? '-'); ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <button type="button" class="button button-small"
                                                onclick="olamaEditTeacher(<?php echo $teacher->ID; ?>, '<?php echo esc_attr($teacher->employee_id ?? ''); ?>', '<?php echo esc_attr($teacher->phone_number ?? ''); ?>')">
                                                <?php _e('Edit', 'olama-school'); ?>
                                            </button>
                                            <a href="<?php echo admin_url('admin.php?page=olama-school-plans&tab=office_hours&teacher_id=' . $teacher->ID); ?>"
                                                class="button button-small">
                                                <span class="dashicons dashicons-calendar-alt"
                                                    style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                                                <?php _e('Office Hours', 'olama-school'); ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <?php _e('No teachers found. Assign the "Teacher" role to users to make them teachers.', 'olama-school'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Edit Teacher Modal -->
            <div id="olama-edit-teacher-form"
                style="display: none; background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px;">
                <h3>
                    <?php _e('Edit Teacher Information', 'olama-school'); ?>
                </h3>
                <form method="post" action="">
                    <?php wp_nonce_field('olama_update_teacher'); ?>
                    <input type="hidden" name="teacher_id" id="edit_teacher_id" />
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php _e('Employee ID', 'olama-school'); ?>
                            </th>
                            <td><input type="text" name="employee_id" id="edit_employee_id" class="regular-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php _e('Phone Number', 'olama-school'); ?>
                            </th>
                            <td><input type="text" name="phone_number" id="edit_phone_number" class="regular-text" /></td>
                        </tr>
                    </table>
                    <?php submit_button(__('Update Teacher', 'olama-school'), 'primary', 'update_teacher', false); ?>
                    <button type="button" class="button"
                        onclick="document.getElementById('olama-edit-teacher-form').style.display='none';">
                        <?php _e('Cancel', 'olama-school'); ?>
                    </button>
                </form>
            </div>

            <script>
                function olamaEditTeacher(id, employeeId, phone) {
                    document.getElementById('edit_teacher_id').value = id;
                    document.getElementById('edit_employee_id').value = employeeId;
                    document.getElementById('edit_phone_number').value = phone;
                    document.getElementById('olama-edit-teacher-form').style.display = 'block';
                    document.getElementById('olama-edit-teacher-form').scrollIntoView({ behavior: 'smooth' });
                }
            </script>

        <?php elseif ($active_tab === 'permissions'): ?>
            <?php
            // Load permissions content directly
            include OLAMA_SCHOOL_PATH . 'includes/admin-views/admin-permissions.php';
            ?>

        <?php elseif ($active_tab === 'logs'): ?>
            <?php
            // Load notifications/logs content directly
            include OLAMA_SCHOOL_PATH . 'includes/admin-views/admin-logs.php';
            ?>

        <?php endif; ?>
    </div>
</div>