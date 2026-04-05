<?php
/**
 * Academic Supervision - Assignments View
 */
if (!defined('ABSPATH')) exit;

global $wpdb;

$active_year = Olama_School_Academic::get_active_year();
$active_year_id = $active_year ? $active_year->id : 0;
$semesters = $active_year_id ? Olama_School_Academic::get_semesters($active_year_id) : array();
$active_semester = Olama_School_Academic::get_active_semester($active_year_id);
$selected_semester_id = $active_semester ? intval($active_semester->id) : ($semesters[0]->id ?? 0);

$grades = Olama_School_Grade::get_grades();

// Get valid supervisors (users with supervisor role or specific capability)
$supervisors = get_users(array(
    'role__in' => array('supervisor', 'olama_supervisor', 'administrator', 'school_manager', 'editor'),
    'number' => -1
));

// Filter assignments
$assignments = $wpdb->get_results($wpdb->prepare(
    "SELECT a.*, u.display_name as supervisor_name, g.grade_name, s.subject_name
     FROM {$wpdb->prefix}olama_supervisor_assignments a
     JOIN {$wpdb->users} u ON a.supervisor_id = u.ID
     JOIN {$wpdb->prefix}olama_grades g ON a.grade_id = g.id
     LEFT JOIN {$wpdb->prefix}olama_subjects s ON a.subject_id = s.id
     WHERE a.academic_year_id = %d AND a.semester_id = %d
     ORDER BY g.grade_name, u.display_name",
    $active_year_id,
    $selected_semester_id
));

?>

<div class="olama-supervision-assignments-wrap">
    <div style="display: grid; grid-template-columns: 350px 1fr; gap: 25px; align-items: start;">
        
        <!-- Add Assignment Form -->
        <div class="olama-card" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #e2e8f0;">
            <h3 style="margin-top: 0; margin-bottom: 20px; font-size: 1.1em; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                <span class="dashicons dashicons-plus-alt" style="color: #6366f1;"></span>
                <?php _e('Assign Supervisor', 'olama-school'); ?>
            </h3>
            
            <form id="olama-add-assignment-form">
                <input type="hidden" name="academic_year_id" value="<?php echo $active_year_id; ?>">
                <input type="hidden" name="semester_id" value="<?php echo $selected_semester_id; ?>">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;"><?php _e('Supervisor', 'olama-school'); ?></label>
                    <select name="supervisor_id" required style="width: 100%;">
                        <option value=""><?php _e('-- Select Supervisor --', 'olama-school'); ?></option>
                        <?php foreach ($supervisors as $s): ?>
                            <option value="<?php echo $s->ID; ?>"><?php echo esc_html($s->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 15px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;"><?php _e('Grade', 'olama-school'); ?></label>
                    <select name="grade_id" id="assignment-grade-select" required style="width: 100%;">
                        <option value=""><?php _e('-- Select Grade --', 'olama-school'); ?></option>
                        <?php foreach ($grades as $g): ?>
                            <option value="<?php echo $g->id; ?>"><?php echo esc_html($g->grade_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;"><?php _e('Subject (Optional)', 'olama-school'); ?></label>
                    <select name="subject_id" id="assignment-subject-select" style="width: 100%;">
                        <option value=""><?php _e('-- All Subjects --', 'olama-school'); ?></option>
                    </select>
                    <p style="font-size: 11px; color: #94a3b8; margin-top: 5px;"><?php _e('Leave empty to assign to all subjects in this grade.', 'olama-school'); ?></p>
                </div>

                <button type="submit" class="button button-primary button-large" style="width: 100%; height: 45px; background: #6366f1; border-color: #6366f1;">
                    <?php _e('Create Assignment', 'olama-school'); ?>
                </button>
            </form>
        </div>

        <!-- Assignments List -->
        <div class="olama-card" style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #e2e8f0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; font-size: 1.1em; color: #1e293b;"><?php _e('Active Assignments', 'olama-school'); ?></h3>
                <div style="font-size: 12px; color: #64748b;">
                    <?php echo sprintf(__('Showing assignments for %s', 'olama-school'), '<b>'.Olama_School_Helpers::translate($active_semester->semester_name).'</b>'); ?>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30%;"><?php _e('Supervisor', 'olama-school'); ?></th>
                        <th style="width: 25%;"><?php _e('Grade', 'olama-school'); ?></th>
                        <th style="width: 30%;"><?php _e('Subject', 'olama-school'); ?></th>
                        <th style="width: 15%; text-align: center;"><?php _e('Action', 'olama-school'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($assignments): ?>
                        <?php foreach ($assignments as $a): ?>
                            <tr>
                                <td style="font-weight: 600; color: #1e293b;"><?php echo esc_html($a->supervisor_name); ?></td>
                                <td><?php echo esc_html($a->grade_name); ?></td>
                                <td>
                                    <?php if ($a->subject_id): ?>
                                        <span style="background: #f1f5f9; padding: 3px 8px; border-radius: 4px; font-size: 12px;"><?php echo esc_html($a->subject_name); ?></span>
                                    <?php else: ?>
                                        <span style="color: #6366f1; font-weight: 600; font-size: 12px; text-transform: uppercase;"><?php _e('All Subjects', 'olama-school'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <button type="button" class="button button-link-delete olama-delete-assignment" data-id="<?php echo $a->id; ?>" style="color: #ef4444;">
                                        <span class="dashicons dashicons-trash" style="font-size: 18px; margin-top: 4px;"></span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: #94a3b8; font-style: italic;">
                                <?php _e('No supervisor assignments found for this semester.', 'olama-school'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    jQuery(document).ready(function($) {
        // Dynamic subjects based on grade
        $('#assignment-grade-select').on('change', function() {
            const gradeId = $(this).val();
            const subjectSelect = $('#assignment-subject-select');
            
            if (!gradeId) {
                subjectSelect.html('<option value=""><?php _e('-- All Subjects --', 'olama-school'); ?></option>');
                return;
            }

            subjectSelect.html('<option value=""><?php _e('Loading subjects...', 'olama-school'); ?></option>');

            $.post(ajaxurl, {
                action: 'olama_get_subjects',
                grade_id: gradeId,
                nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
            }, function(res) {
                if (res.success) {
                    let html = '<option value=""><?php _e('-- All Subjects --', 'olama-school'); ?></option>';
                    res.data.forEach(function(s) {
                        html += `<option value="${s.id}">${s.subject_name}</option>`;
                    });
                    subjectSelect.html(html);
                } else {
                    subjectSelect.html('<option value=""><?php _e('Error loading subjects', 'olama-school'); ?></option>');
                }
            });
        });

        // Add Assignment
        $('#olama-add-assignment-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const btn = form.find('button[type="submit"]');
            
            btn.prop('disabled', true).text('<?php _e('Saving...', 'olama-school'); ?>');

            $.post(ajaxurl, {
                action: 'olama_save_supervisor_assignment',
                supervisor_id: form.find('[name="supervisor_id"]').val(),
                grade_id: form.find('[name="grade_id"]').val(),
                subject_id: form.find('[name="subject_id"]').val(),
                academic_year_id: form.find('[name="academic_year_id"]').val(),
                semester_id: form.find('[name="semester_id"]').val(),
                nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
            }, function(res) {
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.data || '<?php _e('Failed to save assignment', 'olama-school'); ?>');
                    btn.prop('disabled', false).text('<?php _e('Create Assignment', 'olama-school'); ?>');
                }
            });
        });

        // Delete Assignment
        $('.olama-delete-assignment').on('click', function() {
            if (!confirm('<?php _e('Are you sure you want to remove this assignment?', 'olama-school'); ?>')) return;
            
            const btn = $(this);
            const id = btn.data('id');
            
            $.post(ajaxurl, {
                action: 'olama_delete_supervisor_assignment',
                id: id,
                nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
            }, function(res) {
                if (res.success) {
                    btn.closest('tr').fadeOut(function() { $(this).remove(); });
                } else {
                    alert(res.data || '<?php _e('Failed to delete assignment', 'olama-school'); ?>');
                }
            });
        });
    });
</script>
