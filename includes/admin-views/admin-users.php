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
            <div class="olama-card" style="background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;"><?php _e('Student Management', 'olama-school'); ?></h2>
                    <button type="button" class="button button-primary" onclick="olamaOpenRegisterModal()">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                        <?php _e('Add New Student', 'olama-school'); ?>
                    </button>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'olama-school'); ?></th>
                            <th><?php _e('ID Number', 'olama-school'); ?></th>
                            <th><?php _e('Family ID', 'olama-school'); ?></th>
                            <th><?php _e('Year', 'olama-school'); ?></th>
                            <th><?php _e('Grade', 'olama-school'); ?></th>
                            <th><?php _e('Section', 'olama-school'); ?></th>
                            <th><?php _e('Status', 'olama-school'); ?></th>
                            <th style="width: 160px;"><?php _e('Actions', 'olama-school'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($students): ?>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <a href="javascript:void(0)" onclick="olamaShowStudentHistory(<?php echo $student->id; ?>, '<?php echo esc_attr($student->student_name); ?>')" style="font-weight: 600; text-decoration: none;">
                                            <?php echo esc_html($student->student_name); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($student->student_uid); ?></td>
                                    <td><?php echo esc_html($student->family_id ?? '-'); ?></td>
                                    <td><?php echo $student->academic_year_name ?: '-'; ?></td>
                                    <td><?php echo $student->grade_name ?: '-'; ?></td>
                                    <td><?php echo $student->section_name ?: '-'; ?></td>
                                    <td>
                                        <?php if ($student->section_id): ?>
                                            <span class="olama-status-pill olama-status-published"><?php _e('Enrolled', 'olama-school'); ?></span>
                                        <?php else: ?>
                                            <span class="olama-status-pill olama-status-draft"><?php _e('Not Enrolled', 'olama-school'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($student->id): ?>
                                            <div style="display: flex; gap: 5px;">
                                                <?php if ($student->section_id): ?>
                                                    <button type="button" class="button button-small" style="color: #d63638;"
                                                            onclick="olamaUnenrollStudent(<?php echo $student->id; ?>, '<?php echo esc_attr($student->student_name); ?>', <?php echo intval($student->academic_year_id ?? 0); ?>)">
                                                        <?php _e('Unenroll', 'olama-school'); ?>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="button button-small" 
                                                            onclick="olamaOpenEnrollmentModal(<?php echo $student->id; ?>, '<?php echo esc_attr($student->student_name); ?>')">
                                                        <?php _e('Enroll', 'olama-school'); ?>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="button button-small" 
                                                        onclick="olamaOpenEditStudentModal(<?php echo $student->id; ?>, '<?php echo esc_attr($student->student_name); ?>', '<?php echo esc_attr($student->student_uid); ?>', '<?php echo esc_attr($student->family_id ?? ''); ?>')">
                                                    <span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                                                </button>
                                                <button type="button" class="button button-small" style="color: #d63638;"
                                                        onclick="olamaDeleteStudent(<?php echo $student->id; ?>, '<?php echo esc_attr($student->student_name); ?>')">
                                                    <span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8"><?php _e('No students found.', 'olama-school'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Register Student Modal -->
            <div id="olama-register-student-modal" class="olama-modal">
                <div class="olama-modal-content" style="margin: 5% auto; width: 450px;">
                    <div class="olama-modal-header">
                        <h2><?php _e('Register New Student', 'olama-school'); ?></h2>
                        <span class="olama-modal-close" onclick="olamaCloseRegisterModal()">&times;</span>
                    </div>
                    <form method="post" action="">
                        <div class="olama-modal-body">
                            <?php wp_nonce_field('olama_register_student'); ?>
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Student Name', 'olama-school'); ?></label>
                                <input type="text" name="student_name" required class="widefat" placeholder="<?php _e('Full Name', 'olama-school'); ?>" />
                            </div>
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('ID Number', 'olama-school'); ?></label>
                                <input type="text" name="student_id_number" required class="widefat" placeholder="<?php _e('National ID / Passport', 'olama-school'); ?>" />
                            </div>
                            <div style="margin-bottom: 20px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 8px;"><?php _e('Family ID', 'olama-school'); ?></label>
                                <input type="text" name="family_id" class="widefat" placeholder="<?php _e('Optional Family Reference', 'olama-school'); ?>" />
                            </div>
                        </div>
                        <div class="olama-modal-footer">
                            <button type="button" class="button" onclick="olamaCloseRegisterModal()"><?php _e('Cancel', 'olama-school'); ?></button>
                            <button type="submit" name="register_student" class="button button-primary"><?php _e('Create Registry Entry', 'olama-school'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Student Enrollment Modal -->
            <div id="olama-student-enrollment-modal" class="olama-modal">
                <div class="olama-modal-content" style="margin: 2% auto; width: 450px;">
                    <div class="olama-modal-header">
                        <h2><?php _e('Enroll Student', 'olama-school'); ?></h2>
                        <span class="olama-modal-close" onclick="olamaCloseEnrollmentModal()">&times;</span>
                    </div>
                    <form method="post" action="">
                        <div class="olama-modal-body">
                            <p style="background: #f0f7ff; padding: 10px; border-radius: 4px; border-left: 4px solid #007cba;">
                                <?php _e('Enrolling:', 'olama-school'); ?> <strong id="enrollment-student-name"></strong>
                            </p>
                            <?php wp_nonce_field('olama_enroll_student'); ?>
                            <input type="hidden" name="student_id" id="enrollment-student-id" />
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Academic Year', 'olama-school'); ?></label>
                                <select name="academic_year_id" required style="width: 100%;">
                                    <?php foreach ($academic_years as $year): ?>
                                        <option value="<?php echo $year->id; ?>" <?php echo $year->is_active ? 'selected' : ''; ?>>
                                            <?php echo esc_html($year->year_name); ?>
                                            <?php echo $year->is_active ? __('(Active)', 'olama-school') : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Grade', 'olama-school'); ?></label>
                                <select name="grade_id" required id="olama-enroll-grade-filter" style="width: 100%;">
                                    <option value=""><?php _e('Select Grade', 'olama-school'); ?></option>
                                    <?php foreach ($grades as $grade): ?>
                                        <option value="<?php echo $grade->id; ?>">
                                            <?php echo esc_html($grade->grade_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Section', 'olama-school'); ?></label>
                                <select name="section_id" required id="olama-enroll-section-filter" style="width: 100%;">
                                    <option value=""><?php _e('Select Section', 'olama-school'); ?></option>
                                    <?php foreach ($sections as $section): ?>
                                        <option value="<?php echo $section->id; ?>" data-grade="<?php echo $section->grade_id; ?>">
                                            <?php echo esc_html($section->section_name); ?> (<?php echo esc_html($section->grade_name); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="olama-modal-footer">
                            <button type="button" class="button" onclick="olamaCloseEnrollmentModal()"><?php _e('Cancel', 'olama-school'); ?></button>
                            <button type="submit" name="enroll_student" class="button button-primary"><?php _e('Enroll Student', 'olama-school'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Student History Modal -->
            <div id="olama-student-history-modal" class="olama-modal">
                <div class="olama-modal-content" style="margin: 5% auto; width: 600px;">
                    <div class="olama-modal-header">
                        <h2><?php _e('Enrollment History:', 'olama-school'); ?> <span id="history-student-name"></span></h2>
                        <span class="olama-modal-close" onclick="olamaCloseHistoryModal()">&times;</span>
                    </div>
                    <div class="olama-modal-body" id="history-modal-body">
                        <div class="olama-loading" style="text-align: center; padding: 20px;">
                            <span class="spinner is-active" style="float: none; margin: 0 auto;"></span>
                            <p><?php _e('Loading history...', 'olama-school'); ?></p>
                        </div>
                    </div>
                    <div class="olama-modal-footer">
                        <button type="button" class="button" onclick="olamaCloseHistoryModal()"><?php _e('Close', 'olama-school'); ?></button>
                    </div>
                </div>
            </div>

            <!-- Edit Student Modal -->
            <div id="olama-edit-student-modal" class="olama-modal">
                <div class="olama-modal-content" style="margin: 5% auto; width: 450px;">
                    <div class="olama-modal-header">
                        <h2><?php _e('Edit Student Details', 'olama-school'); ?></h2>
                        <span class="olama-modal-close" onclick="olamaCloseEditStudentModal()">&times;</span>
                    </div>
                    <form method="post" action="">
                        <div class="olama-modal-body">
                            <?php wp_nonce_field('olama_update_student'); ?>
                            <input type="hidden" name="student_id" id="edit-student-id" />
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Name', 'olama-school'); ?></label>
                                <input type="text" name="student_name" id="edit-student-name" required class="widefat" />
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('ID Number', 'olama-school'); ?></label>
                                <input type="text" name="student_id_number" id="edit-student-uid" required class="widefat" />
                            </div>

                            <div style="margin-bottom: 15px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Family ID', 'olama-school'); ?></label>
                                <input type="text" name="family_id" id="edit-student-family-id" class="widefat" />
                            </div>
                        </div>
                        <div class="olama-modal-footer">
                            <button type="button" class="button" onclick="olamaCloseEditStudentModal()"><?php _e('Cancel', 'olama-school'); ?></button>
                            <button type="submit" name="update_student" class="button button-primary"><?php _e('Update Details', 'olama-school'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Student Form (Hidden) -->
            <form id="olama-delete-student-form" method="post" action="" style="display: none;">
                <?php wp_nonce_field('olama_delete_student'); ?>
                <input type="hidden" name="student_id" id="delete-student-id" />
                <input type="hidden" name="delete_student" value="1" />
            </form>

            <!-- Unenroll Student Form (Hidden) -->
            <form id="olama-unenroll-student-form" method="post" action="" style="display: none;">
                <?php wp_nonce_field('olama_unenroll_student'); ?>
                <input type="hidden" name="student_id" id="unenroll-student-id" />
                <input type="hidden" name="academic_year_id" id="unenroll-academic-year-id" />
                <input type="hidden" name="unenroll_student" value="1" />
            </form>

            <script>
                jQuery(document).ready(function ($) {
                    $('#olama-enroll-grade-filter').on('change', function () {
                        var gradeId = $(this).val();
                        $('#olama-enroll-section-filter option').each(function () {
                            var optionGradeId = $(this).data('grade');
                            if (!gradeId || !optionGradeId || gradeId == optionGradeId) {
                                $(this).show();
                            } else {
                                $(this).hide();
                            }
                        });
                        $('#olama-enroll-section-filter').val('');
                    });
                });

                function olamaOpenRegisterModal() {
                    document.getElementById('olama-register-student-modal').style.display = 'block';
                }

                function olamaCloseRegisterModal() {
                    document.getElementById('olama-register-student-modal').style.display = 'none';
                }

                function olamaOpenEnrollmentModal(studentId, studentName) {
                    document.getElementById('enrollment-student-id').value = studentId;
                    document.getElementById('enrollment-student-name').innerText = studentName;
                    document.getElementById('olama-student-enrollment-modal').style.display = 'block';
                }

                function olamaCloseEnrollmentModal() {
                    document.getElementById('olama-student-enrollment-modal').style.display = 'none';
                }

                function olamaOpenEditStudentModal(id, name, uid, familyId) {
                    document.getElementById('edit-student-id').value = id;
                    document.getElementById('edit-student-name').value = name;
                    document.getElementById('edit-student-uid').value = uid;
                    document.getElementById('edit-student-family-id').value = familyId;
                    document.getElementById('olama-edit-student-modal').style.display = 'block';
                }

                function olamaCloseEditStudentModal() {
                    document.getElementById('olama-edit-student-modal').style.display = 'none';
                }

                function olamaDeleteStudent(id, name) {
                    if (confirm('<?php _e('Are you sure you want to delete student: ', 'olama-school'); ?>' + name + '? <?php _e('This will also delete ALL their enrollment history.', 'olama-school'); ?>')) {
                        document.getElementById('delete-student-id').value = id;
                        document.getElementById('olama-delete-student-form').submit();
                    }
                }

                function olamaUnenrollStudent(id, name, yearId) {
                    if (confirm('<?php _e('Are you sure you want to unenroll student: ', 'olama-school'); ?>' + name + '?')) {
                        document.getElementById('unenroll-student-id').value = id;
                        document.getElementById('unenroll-academic-year-id').value = yearId;
                        document.getElementById('olama-unenroll-student-form').submit();
                    }
                }

                function olamaShowStudentHistory(studentId, studentName) {
                    document.getElementById('history-student-name').innerText = studentName;
                    document.getElementById('history-modal-body').innerHTML = '<div class="olama-loading" style="text-align: center; padding: 20px;"><span class="spinner is-active" style="float: none; margin: 0 auto;"></span><p><?php _e('Loading history...', 'olama-school'); ?></p></div>';
                    document.getElementById('olama-student-history-modal').style.display = 'block';

                    jQuery.ajax({
                        url: ajaxurl,
                        data: {
                            action: 'olama_get_student_history',
                            student_id: studentId,
                            nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var history = response.data;
                                var html = '<table class="wp-list-table widefat fixed striped">';
                                html += '<thead><tr><th><?php _e('Year', 'olama-school'); ?></th><th><?php _e('Grade', 'olama-school'); ?></th><th><?php _e('Section', 'olama-school'); ?></th><th><?php _e('Date', 'olama-school'); ?></th></tr></thead><tbody>';
                                
                                if (history.length > 0) {
                                    history.forEach(function(item) {
                                        html += '<tr>';
                                        html += '<td>' + item.academic_year_name + '</td>';
                                        html += '<td>' + item.grade_name + '</td>';
                                        html += '<td>' + item.section_name + '</td>';
                                        html += '<td>' + item.enrollment_date + '</td>';
                                        html += '</tr>';
                                    });
                                } else {
                                    html += '<tr><td colspan="4"><?php _e('No history found.', 'olama-school'); ?></td></tr>';
                                }
                                html += '</tbody></table>';
                                document.getElementById('history-modal-body').innerHTML = html;
                            } else {
                                document.getElementById('history-modal-body').innerHTML = '<p style="color: red;">' + response.data + '</p>';
                            }
                        }
                    });
                }

                function olamaCloseHistoryModal() {
                    document.getElementById('olama-student-history-modal').style.display = 'none';
                }
            </script>


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