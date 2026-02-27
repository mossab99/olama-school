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

    <?php
    if ($msg = get_transient('olama_import_message')) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
        delete_transient('olama_import_message');
    }
    if ($error = get_transient('olama_import_error')) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error) . '</p></div>';
        delete_transient('olama_import_error');
    }
    ?>

    <h2 class="nav-tab-wrapper">
        <?php
        $tab_labels = array(
            'families' => __('Families', 'olama-school'),
            'students' => __('Students / Enrollment', 'olama-school'),
            'teachers' => __('Teachers', 'olama-school'),
            'permissions' => __('Permissions', 'olama-school'),
            'logs' => __('Activity Logs', 'olama-school'),
        );
        foreach ($allowed_tabs as $id => $tab): ?>
            <a href="?page=olama-school-users&tab=<?php echo $id; ?>"
                class="nav-tab <?php echo $active_tab === $id ? 'nav-tab-active' : ''; ?>">
                <?php echo $tab_labels[$id]; ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <div class="olama-tab-content" style="margin-top: 20px;">
        <?php if ($active_tab === 'families'): ?>
            <!-- Families Tab -->
            <div class="olama-card"
                style="background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;"><?php _e('Family Management', 'olama-school'); ?></h2>
                    <div style="display: flex; gap: 10px;">
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('olama_export_families'); ?>
                            <button type="submit" name="olama_export_families" class="button">
                                <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                                <?php _e('Export Families', 'olama-school'); ?>
                            </button>
                        </form>
                        <button type="button" class="button"
                            onclick="document.getElementById('olama-import-families-modal').style.display='block'">
                            <span class="dashicons dashicons-upload" style="margin-top: 4px;"></span>
                            <?php _e('Import Families', 'olama-school'); ?>
                        </button>
                        <button type="button" class="button button-primary" onclick="olamaOpenFamilyModal()">
                            <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                            <?php _e('Add New Family', 'olama-school'); ?>
                        </button>
                        <button type="button" class="button" style="color: #d63638; border-color: #d63638;"
                            onclick="olamaDeleteAllFamilies()">
                            <span class="dashicons dashicons-trash" style="margin-top: 4px;"></span>
                            <?php _e('Delete All Families', 'olama-school'); ?>
                        </button>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Family ID', 'olama-school'); ?></th>
                            <th><?php _e('Family Name', 'olama-school'); ?></th>
                            <th><?php _e('Father Mobile', 'olama-school'); ?></th>
                            <th><?php _e('Mother Mobile', 'olama-school'); ?></th>
                            <th><?php _e('Students', 'olama-school'); ?></th>
                            <th style="width: 120px;"><?php _e('Actions', 'olama-school'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($families): ?>
                            <?php foreach ($families as $family): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($family->family_uid); ?></strong></td>
                                    <td><?php echo esc_html($family->family_name); ?></td>
                                    <td><?php echo esc_html($family->father_mobile ?: '-'); ?></td>
                                    <td><?php echo esc_html($family->mother_mobile ?: '-'); ?></td>
                                    <td>
                                        <span class="olama-status-pill olama-status-published">
                                            <?php echo intval($family->student_count); ?>             <?php _e('Students', 'olama-school'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <button type="button" class="button button-small"
                                                onclick="olamaOpenFamilyModal(<?php echo htmlspecialchars(json_encode($family)); ?>)">
                                                <span class="dashicons dashicons-edit"
                                                    style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                                            </button>
                                            <button type="button" class="button button-small" style="color: #d63638;"
                                                onclick="olamaDeleteFamily(<?php echo $family->id; ?>, '<?php echo esc_attr($family->family_name); ?>')">
                                                <span class="dashicons dashicons-trash"
                                                    style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6"><?php _e('No families found.', 'olama-school'); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Family Modal -->
            <div id="olama-family-modal" class="olama-modal">
                <div class="olama-modal-content family-modal">
                    <div class="olama-modal-header">
                        <h2 id="family-modal-title"><?php _e('Add New Family', 'olama-school'); ?></h2>
                        <span class="olama-modal-close" onclick="olamaCloseFamilyModal()">&times;</span>
                    </div>
                    <form method="post" action="">
                        <div class="olama-modal-body">
                            <?php wp_nonce_field('olama_save_family'); ?>
                            <input type="hidden" name="family_db_id" id="family-db-id" />

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label
                                        style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Family ID (UID)', 'olama-school'); ?></label>
                                    <input type="text" name="family_uid" id="family-uid" required class="widefat"
                                        placeholder="e.g. 630" />
                                </div>
                                <div>
                                    <label
                                        style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Family Name', 'olama-school'); ?></label>
                                    <input type="text" name="family_name" id="family-name" required class="widefat" />
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                <div>
                                    <label
                                        style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Father Mobile', 'olama-school'); ?></label>
                                    <input type="text" name="father_mobile" id="family-father-mobile" class="widefat" />
                                </div>
                                <div>
                                    <label
                                        style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Mother Mobile', 'olama-school'); ?></label>
                                    <input type="text" name="mother_mobile" id="family-mother-mobile" class="widefat" />
                                </div>
                            </div>

                            <div style="margin-bottom: 20px;">
                                <label
                                    style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Address Details', 'olama-school'); ?></label>
                                <textarea name="address" id="family-address" class="widefat" rows="2"></textarea>
                            </div>

                            <hr />
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <h3 style="margin: 0;"><?php _e('Students', 'olama-school'); ?></h3>
                                <button type="button" class="button" onclick="olamaAddStudentRow()">
                                    <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                                    <?php _e('Add Student', 'olama-school'); ?>
                                </button>
                            </div>

                            <div id="family-students-container">
                                <!-- Student rows rendered by JS -->
                            </div>
                        </div>
                        <div class="olama-modal-footer">
                            <button type="button" class="button"
                                onclick="olamaCloseFamilyModal()"><?php _e('Cancel', 'olama-school'); ?></button>
                            <button type="submit" name="save_family"
                                class="button button-primary"><?php _e('Save Family', 'olama-school'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Delete Family Form (Hidden) -->
            <form id="olama-delete-family-form" method="post" action="" style="display: none;">
                <?php wp_nonce_field('olama_delete_family'); ?>
                <input type="hidden" name="family_id" id="delete-family-id" />
                <input type="hidden" name="delete_family" value="1" />
            </form>

            <form id="olama-delete-all-families-form" method="post" action="" style="display: none;">
                <?php wp_nonce_field('olama_delete_all_families'); ?>
                <input type="hidden" name="delete_all_families" value="1" />
            </form>

            <script>
                function olamaOpenFamilyModal(family = null) {
                    const container = document.getElementById('family-students-container');
                        container.innerHTML = '';

                        if (family) {
                            document.getElementById('family-modal-title').innerText = '<?php _e('Edit Family Details', 'olama-school'); ?>';
                            document.getElementById('family-db-id').value = family.id;
                            document.getElementById('family-uid').value = family.family_uid;
                            document.getElementById('family-name').value = family.family_name;
                            document.getElementById('family-father-mobile').value = family.father_mobile;
                            document.getElementById('family-mother-mobile').value = family.mother_mobile;
                            document.getElementById('family-address').value = family.address;
                            document.getElementById('family-uid').readOnly = true;

                            // Load Students via AJAX
                            jQuery.ajax({
                                url: ajaxurl,
                                data: {
                                    action: 'olama_get_family_students',
                                    family_uid: family.family_uid,
                                    nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
                                },
                                success: function (response) {
                                    if (response.success && response.data.length > 0) {
                                        response.data.forEach(stu => olamaAddStudentRow(stu));
                                    } else {
                                        olamaAddStudentRow(); // Add one empty row
                                    }
                                }
                            });
                        } else {
                            document.getElementById('family-modal-title').innerText = '<?php _e('Add New Family', 'olama-school'); ?>';
                            document.getElementById('family-db-id').value = '';
                            document.getElementById('family-uid').value = '';
                            document.getElementById('family-name').value = '';
                            document.getElementById('family-father-mobile').value = '';
                            document.getElementById('family-mother-mobile').value = '';
                            document.getElementById('family-address').value = '';
                            document.getElementById('family-uid').readOnly = false;
                            olamaAddStudentRow(); // Add one empty row
                        }
                        document.getElementById('olama-family-modal').style.display = 'block';
                    }

                    function olamaAddStudentRow(stu = null) {
                        const container = document.getElementById('family-students-container');
                        const index = container.querySelectorAll('.olama-student-card').length;

                        const card = document.createElement('div');
                        card.className = 'olama-student-card';
                        card.style.cssText = 'background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 20px; position: relative;';

                        // Helper to get safe value (not 'null' or null)
                        const getVal = (v) => (v === null || v === 'null' || v === undefined) ? '' : v;

                        const dobValue = stu ? getVal(stu.dob) : '';
                        const nidValue = stu ? getVal(stu.national_id) : '';

                        card.innerHTML = `
                        <div class="olama-student-delete-btn"
                            onclick="this.closest('.olama-student-card').remove()"
                            title="<?php _e('Remove Student', 'olama-school'); ?>">
                            <span class="dashicons dashicons-no-alt"></span>
                        </div>

                        <input type="hidden" name="students[${index}][db_id]" value="${stu ? stu.id : ''}" />

                        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 15px; margin-bottom: 15px;">
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 11px; color: #64748b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em;"><?php _e('Student ID', 'olama-school'); ?></label>
                                <input type="text" name="students[${index}][uid]" value="${stu ? getVal(stu.student_uid) : ''}" required
                                    style="width: 100%; box-sizing: border-box;" placeholder="e.g. 50001" />
                            </div>
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 11px; color: #64748b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em;"><?php _e('Full Name', 'olama-school'); ?></label>
                                <input type="text" name="students[${index}][name]" value="${stu ? getVal(stu.student_name) : ''}" required
                                    style="width: 100%; box-sizing: border-box;" />
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 11px; color: #64748b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em;"><?php _e('Date of Birth', 'olama-school'); ?></label>
                                <input type="date" name="students[${index}][dob]" value="${dobValue}"
                                    style="width: 100%; box-sizing: border-box;" />
                            </div>
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 11px; color: #64748b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em;"><?php _e('National ID', 'olama-school'); ?></label>
                                <input type="text" name="students[${index}][national_id]" value="${nidValue}"
                                    style="width: 100%; box-sizing: border-box;" />
                            </div>
                            <div>
                                <label style="display: block; font-weight: 600; font-size: 11px; color: #64748b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.05em;"><?php _e('Gender', 'olama-school'); ?></label>
                                <select name="students[${index}][gender]" style="width: 100%; box-sizing: border-box; height: 30px;">
                                    <option value="male" ${stu && stu.gender == 'male' ? 'selected' : ''}><?php _e('Male', 'olama-school'); ?></option>
                                    <option value="female" ${stu && stu.gender == 'female' ? 'selected' : ''}><?php _e('Female', 'olama-school'); ?></option>
                                </select>
                            </div>
                        </div>
                    `;

                        container.appendChild(card);
                    }


                    function olamaCloseFamilyModal() {
                        document.getElementById('olama-family-modal').style.display = 'none';
                    }

                    function olamaDeleteFamily(id, name) {
                        if (confirm('<?php _e('Are you sure you want to delete family: ', 'olama-school'); ?>' + name + '?')) {
                            document.getElementById('delete-family-id').value = id;
                            document.getElementById('olama-delete-family-form').submit();
                        }
                    }

                    function olamaDeleteAllFamilies() {
                        if (confirm('<?php _e('CRITICAL WARNING: Are you sure you want to delete ALL families? This action is IRREVERSIBLE!', 'olama-school'); ?>')) {
                            const confirmText = prompt('<?php _e('FINAL CONFIRMATION: To proceed, please type "DELETE" in the box below:', 'olama-school'); ?>');
                            if (confirmText === 'DELETE') {
                                document.getElementById('olama-delete-all-families-form').submit();
                            } else {
                                alert('<?php _e('Wipe cancelled. Final confirmation mismatched.', 'olama-school'); ?>');
                            }
                        }
                    }
                </script>

                <!-- Import Families Modal -->
                <div id="olama-import-families-modal" class="olama-modal">
                    <div class="olama-modal-content" style="margin: 10% auto; width: 400px;">
                        <div class="olama-modal-header">
                            <h2><?php _e('Import Families', 'olama-school'); ?></h2>
                            <span class="olama-modal-close"
                                onclick="document.getElementById('olama-import-families-modal').style.display='none'">&times;</span>
                        </div>
                        <form method="post" enctype="multipart/form-data">
                            <div class="olama-modal-body">
                                <?php wp_nonce_field('olama_import_families'); ?>
                                <input type="hidden" name="olama_import_type" value="families" />
                                <p style="margin-bottom: 15px;">
                                    <?php _e('Upload a CSV file to import family details.', 'olama-school'); ?></p>
                                <input type="file" name="olama_import_file" accept=".csv" required
                                    style="display: block; width: 100%; padding: 10px; border: 1px dashed #ccd0d4; border-radius: 4px; background: #f9f9f9;" />
                                <p style="font-size: 11px; color: #666; margin-top: 15px;">
                                    <?php _e('Expected columns: Family ID, Family Name, Father Mobile, Mother Mobile, Address', 'olama-school'); ?>
                                </p>
                            </div>
                            <div class="olama-modal-footer">
                                <button type="button" class="button"
                                    onclick="document.getElementById('olama-import-families-modal').style.display='none'"><?php _e('Cancel', 'olama-school'); ?></button>
                                <button type="submit" name="olama_import_families"
                                    class="button button-primary"><?php _e('Start Import', 'olama-school'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>

        <?php elseif ($active_tab === 'students'): ?>
                <!-- Students Tab -->
                <div class="olama-card"
                    style="background: #fff; padding: 25px; border: 1px solid #ccd0d4; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2 style="margin: 0;"><?php _e('Student Enrollment Registry', 'olama-school'); ?>
                            (<?php echo count($students); ?>)</h2>
                        <div style="display: flex; gap: 10px;">
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('olama_export_students'); ?>
                                <input type="hidden" name="olama_export_students" value="true" />
                                <button type="submit" class="button">
                                    <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                                    <?php _e('Export Registry', 'olama-school'); ?>
                                </button>
                            </form>
                            <button type="button" class="button button-primary"
                                onclick="document.getElementById('olama-import-students-enrollment-modal').style.display='block'">
                                <span class="dashicons dashicons-upload" style="margin-top: 4px;"></span>
                                <?php _e('Import Students & Enroll', 'olama-school'); ?>
                            </button>
                            <button type="button" class="button" style="color: #d63638; border-color: #d63638;"
                                onclick="olamaDeleteAllStudents()">
                                <span class="dashicons dashicons-trash" style="margin-top: 4px;"></span>
                                <?php _e('Delete All Students & Enrollments', 'olama-school'); ?>
                            </button>
                        </div>
                    </div>

                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Name', 'olama-school'); ?></th>
                                <th><?php _e('ID Number', 'olama-school'); ?></th>
                                <th><?php _e('Family', 'olama-school'); ?></th>
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
                                                    <a href="javascript:void(0)"
                                                        onclick="olamaShowStudentHistory(<?php echo $student->id; ?>, '<?php echo esc_attr($student->student_name); ?>')"
                                                        style="font-weight: 600; text-decoration: none;">
                                                        <?php echo esc_html($student->student_name); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo esc_html($student->student_uid); ?></td>
                                                <td>
                                                    <strong><?php echo esc_html($student->family_id ?? '-'); ?></strong>
                                                    <?php if (!empty($student->family_name)): ?>
                                                            <div style="font-size: 11px; color: #666;"><?php echo esc_html($student->family_name); ?>
                                                            </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $student->academic_year_name ?: '-'; ?></td>
                                                <td><?php echo $student->grade_name ?: '-'; ?></td>
                                                <td><?php echo $student->section_name ?: '-'; ?></td>
                                                <td>
                                                    <?php if ($student->section_id): ?>
                                                            <span
                                                                class="olama-status-pill olama-status-published"><?php _e('Enrolled', 'olama-school'); ?></span>
                                                    <?php else: ?>
                                                            <span
                                                                class="olama-status-pill olama-status-draft"><?php _e('Not Enrolled', 'olama-school'); ?></span>
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

                                                                <button type="button" class="button button-small" style="color: #d63638;"
                                                                    onclick="olamaDeleteStudent(<?php echo $student->id; ?>, '<?php echo esc_attr($student->student_name); ?>')">
                                                                    <span class="dashicons dashicons-trash"
                                                                        style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px;"></span>
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


                <!-- Student Enrollment Modal -->
                <div id="olama-student-enrollment-modal" class="olama-modal">
                    <div class="olama-modal-content" style="margin: 2% auto; width: 450px;">
                        <div class="olama-modal-header">
                            <h2><?php _e('Enroll Student', 'olama-school'); ?></h2>
                            <span class="olama-modal-close" onclick="olamaCloseEnrollmentModal()">&times;</span>
                        </div>
                        <form method="post" action="">
                            <div class="olama-modal-body">
                                <p
                                    style="background: #f0f7ff; padding: 10px; border-radius: 4px; border-left: 4px solid #007cba;">
                                    <?php _e('Enrolling:', 'olama-school'); ?> <strong id="enrollment-student-name"></strong>
                                </p>
                                <?php wp_nonce_field('olama_enroll_student'); ?>
                                <input type="hidden" name="student_id" id="enrollment-student-id" />

                                <div style="margin-bottom: 15px;">
                                    <label
                                        style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Academic Year', 'olama-school'); ?></label>
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
                                    <label
                                        style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Grade', 'olama-school'); ?></label>
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
                                    <label
                                        style="display: block; font-weight: 600; margin-bottom: 5px;"><?php _e('Section', 'olama-school'); ?></label>
                                    <select name="section_id" required id="olama-enroll-section-filter" style="width: 100%;">
                                        <option value=""><?php _e('Select Section', 'olama-school'); ?></option>
                                        <?php foreach ($sections as $section): ?>
                                                <option value="<?php echo $section->id; ?>"
                                                    data-grade="<?php echo $section->grade_id; ?>">
                                                    <?php echo esc_html($section->section_name); ?>
                                                    (<?php echo esc_html($section->grade_name); ?>)
                                                </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="olama-modal-footer">
                                <button type="button" class="button"
                                    onclick="olamaCloseEnrollmentModal()"><?php _e('Cancel', 'olama-school'); ?></button>
                                <button type="submit" name="enroll_student"
                                    class="button button-primary"><?php _e('Enroll Student', 'olama-school'); ?></button>
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
                            <button type="button" class="button"
                                onclick="olamaCloseHistoryModal()"><?php _e('Close', 'olama-school'); ?></button>
                        </div>
                    </div>
                </div>


                <!-- Import Students & Enrollment Modal -->
                <div id="olama-import-students-enrollment-modal" class="olama-modal">
                    <div class="olama-modal-content" style="margin: 10% auto; width: 450px;">
                        <div class="olama-modal-header">
                            <h2><?php _e('Import & Enroll Students', 'olama-school'); ?></h2>
                            <span class="olama-modal-close"
                                onclick="document.getElementById('olama-import-students-enrollment-modal').style.display='none'">&times;</span>
                        </div>
                        <form method="post" enctype="multipart/form-data">
                            <div class="olama-modal-body">
                                <?php wp_nonce_field('olama_import_students_enrollment'); ?>
                                <input type="hidden" name="olama_import_type" value="students_enrollment" />
                                <p style="margin-bottom: 15px;">
                                    <?php _e('Upload a CSV file to import students and automatically enroll them.', 'olama-school'); ?>
                                </p>
                                <input type="file" name="olama_import_file" accept=".csv" required
                                    style="display: block; width: 100%; padding: 10px; border: 1px dashed #ccd0d4; border-radius: 4px; background: #f9f9f9;" />
                                <p style="font-size: 11px; color: #666; margin-top: 15px;">
                                    <?php _e('Expected columns: Name, ID Number, Family ID, Year, Grade, Section', 'olama-school'); ?>
                                </p>
                            </div>
                            <div class="olama-modal-footer">
                                <button type="button" class="button"
                                    onclick="document.getElementById('olama-import-students-enrollment-modal').style.display='none'"><?php _e('Cancel', 'olama-school'); ?></button>
                                <button type="submit" name="olama_import_students_enrollment"
                                    class="button button-primary"><?php _e('Import & Enroll', 'olama-school'); ?></button>
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

                <form id="olama-delete-all-students-form" method="post" action="" style="display: none;">
                    <?php wp_nonce_field('olama_delete_all_students'); ?>
                    <input type="hidden" name="delete_all_students" value="1" />
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

                    function olamaDeleteAllStudents() {
                        if (confirm('<?php _e('CRITICAL WARNING: Are you sure you want to delete ALL students and their enrollment history? This action is IRREVERSIBLE!', 'olama-school'); ?>')) {
                            const confirmText = prompt('<?php _e('FINAL CONFIRMATION: To proceed, please type "DELETE" in the box below:', 'olama-school'); ?>');
                            if (confirmText === 'DELETE') {
                                document.getElementById('olama-delete-all-students-form').submit();
                            } else {
                                alert('<?php _e('Wipe cancelled. Final confirmation mismatched.', 'olama-school'); ?>');
                            }
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
                            success: function (response) {
                                if (response.success) {
                                    var history = response.data;
                                    var html = '<table class="wp-list-table widefat fixed striped">';
                                    html += '<thead><tr><th><?php _e('Year', 'olama-school'); ?></th><th><?php _e('Grade', 'olama-school'); ?></th><th><?php _e('Section', 'olama-school'); ?></th><th><?php _e('Date', 'olama-school'); ?></th></tr></thead><tbody>';

                                    if (history.length > 0) {
                                        history.forEach(function (item) {
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