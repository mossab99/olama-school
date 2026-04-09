<?php
/**
 * Academic Supervision - Reports View
 */
if (!defined('ABSPATH'))
    exit;

global $wpdb;

$active_year = Olama_School_Academic::get_active_year();
$active_year_id = $active_year ? $active_year->id : 0;

$active_semester = Olama_School_Academic::get_active_semester($active_year_id);
$academic_weeks = Olama_School_Academic::get_academic_weeks($active_year_id);
$months_weeks = array();
if (!empty($academic_weeks)) {
    foreach ($academic_weeks as $val => $label) {
        if (empty($val)) continue;
        $m_key_start = date('Y-m', strtotime($val));
        $months_weeks[$m_key_start][] = array('val' => $val, 'label' => $label);

        $week_range = Olama_School_Helpers::get_week_range($val);
        $m_key_end = date('Y-m', strtotime($week_range['end']));
        if ($m_key_end !== $m_key_start) {
            $months_weeks[$m_key_end][] = array('val' => $val, 'label' => $label);
        }
    }
    ksort($months_weeks);
}

// Ensure default month based on current time or query
$selected_month = isset($_GET['plan_month']) ? sanitize_text_field($_GET['plan_month']) : '';
if (empty($selected_month) || !isset($months_weeks[$selected_month])) {
    $today_month = date('Y-m');
    if (isset($months_weeks[$today_month])) {
        $selected_month = $today_month;
    } elseif (!empty($months_weeks)) {
        $m_keys = array_keys($months_weeks);
        $selected_month = $m_keys[0];
    }
}

$current_month_weeks = $months_weeks[$selected_month] ?? array();

$current_time = current_time('mysql');
$today = date('Y-m-d', strtotime($current_time));
$week_start = '';

if (!empty($current_month_weeks)) {
    if (isset($_GET['week_start'])) {
        $req = sanitize_text_field($_GET['week_start']);
        foreach ($current_month_weeks as $w) {
            if ($w['val'] === $req) {
                $week_start = $req;
                break;
            }
        }
    }
    
    if (empty($week_start)) {
        foreach ($current_month_weeks as $w) {
            $range = Olama_School_Helpers::get_week_range($w['val']);
            if ($today >= $range['start'] && $today <= $range['end']) {
                $week_start = $w['val'];
                break;
            }
        }
    }
    
    if (empty($week_start)) {
        $week_start = $current_month_weeks[0]['val'] ?? '';
    }
}

// Grades and Sections
$grades = Olama_School_Grade::get_grades();
$selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0;
$sections = $selected_grade_id ? Olama_School_Section::get_by_grade($selected_grade_id) : [];
$selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

$week_range = Olama_School_Helpers::get_week_range($week_start);

// Fetch all visits with status and evaluation score if available
$where_clauses = ["sec.academic_year_id = " . intval($active_year_id)];
if ($week_range) {
    $where_clauses[] = $wpdb->prepare("v.visit_date BETWEEN %s AND %s", $week_range['start'], $week_range['end']);
}
if ($selected_grade_id) {
    $where_clauses[] = $wpdb->prepare("g.id = %d", $selected_grade_id);
}
if ($selected_section_id) {
    $where_clauses[] = $wpdb->prepare("sec.id = %d", $selected_section_id);
}
$where_sql = implode(' AND ', $where_clauses);

$visits = $wpdb->get_results(
    "SELECT v.*, s.day_name, s.period_number, sec.section_name, g.grade_name, sub.subject_name, u.display_name as supervisor_name,
            e.id as evaluation_id, e.status as evaluation_status, e.template_id, tu.display_name as teacher_name
     FROM {$wpdb->prefix}olama_supervisor_visits v
     JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
     JOIN {$wpdb->prefix}olama_sections sec ON s.section_id = sec.id
     JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
     JOIN {$wpdb->prefix}olama_subjects sub ON s.subject_id = sub.id
     JOIN {$wpdb->users} u ON v.supervisor_id = u.ID
     LEFT JOIN {$wpdb->prefix}olama_ev_records e ON e.related_entity_id = v.id AND e.related_entity_type = 'supervisor_visit'
     LEFT JOIN {$wpdb->prefix}olama_teacher_assignments ta ON ta.section_id = sec.id AND ta.subject_id = s.subject_id AND ta.academic_year_id = sec.academic_year_id
     LEFT JOIN {$wpdb->users} tu ON ta.teacher_id = tu.ID
     WHERE {$where_sql}
     ORDER BY v.visit_date DESC"
);

?>

<div class="olama-supervision-reports-wrap">

        <div class="olama-filter-section" style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #e2e8f0;">
            <form method="get" class="olama-filter-row" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="page" value="olama-school-supervision" />
                <input type="hidden" name="tab" value="complete_plan" />
                
                <div class="olama-filter-item" style="flex:1; min-width:150px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px;"><?php _e('Academic Year', 'olama-school'); ?></label>
                    <input type="text" value="<?php echo esc_attr(Olama_School_Helpers::translate($active_year ? $active_year->year_name : '')); ?>" disabled style="width:100%; opacity:0.6; background:#f8fafc;">
                </div>

                <div class="olama-filter-item" style="flex:1; min-width:150px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px;"><?php _e('Semester', 'olama-school'); ?></label>
                    <input type="text" value="<?php echo esc_attr(Olama_School_Helpers::translate($active_semester ? $active_semester->semester_name : '')); ?>" disabled style="width:100%; opacity:0.6; background:#f8fafc;">
                </div>
                
                <div class="olama-filter-item" style="flex:1; min-width:150px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px;"><?php _e('Grade', 'olama-school'); ?></label>
                    <select name="grade_id" onchange="this.form.submit()" style="width:100%;">
                        <option value="0" <?php selected($selected_grade_id, 0); ?>><?php echo Olama_School_Helpers::translate('All Grades'); ?></option>
                        <?php foreach ($grades as $g): ?>
                            <option value="<?php echo $g->id; ?>" <?php selected($selected_grade_id, $g->id); ?>><?php echo esc_html($g->grade_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="olama-filter-item" style="flex:1; min-width:150px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px;"><?php _e('Section', 'olama-school'); ?></label>
                    <select name="section_id" onchange="this.form.submit()" style="width:100%;">
                        <option value="0" <?php selected($selected_section_id, 0); ?>><?php echo Olama_School_Helpers::translate('All Sections'); ?></option>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?php echo $s->id; ?>" <?php selected($selected_section_id, $s->id); ?>><?php echo esc_html($s->section_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="olama-filter-item" style="flex:1; min-width:150px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px;"><?php _e('Month', 'olama-school'); ?></label>
                    <select name="plan_month" style="width:100%;" onchange="this.form.submit()">
                        <?php if (!empty($months_weeks)): foreach ($months_weeks as $m_key => $weeks): ?>
                            <option value="<?php echo esc_attr($m_key); ?>" <?php selected($selected_month, $m_key); ?>>
                                <?php echo date_i18n('F Y', strtotime($m_key . '-01')); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>

                <div class="olama-filter-item" style="flex:1.5; min-width:200px;">
                    <label style="display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px;"><?php echo Olama_School_Helpers::translate('Week Start'); ?></label>
                    <select name="week_start" style="width:100%;" onchange="this.form.submit()">
                        <?php
                        $w_count = 1;
                        foreach ($current_month_weeks as $w): ?>
                            <option value="<?php echo esc_attr($w['val']); ?>" <?php selected($week_start, $w['val']); ?>>
                                <?php echo sprintf(__('%s %d', 'olama-school'), __('Week', 'olama-school'), $w_count) . ' ' . esc_html($w['label']); ?>
                            </option>
                        <?php $w_count++; endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

    <div class="olama-card"
        style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
        <h2>
            <?php _e('Supervisor Visits Report', 'olama-school'); ?>
        </h2>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>
                        <?php _e('Date', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php _e('Supervisor', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php _e('Section', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php _e('Subject', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php echo Olama_School_Helpers::translate('Teacher'); ?>
                    </th>
                    <th>
                        <?php _e('Status', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php _e('Score', 'olama-school'); ?>
                    </th>
                    <th>
                        <?php _e('Actions', 'olama-school'); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if ($visits): ?>
                    <?php foreach ($visits as $v):
                        $status_label = ucfirst($v->status);
                        $status_color = $v->status === 'completed' ? '#10b981' : ($v->status === 'planned' ? '#3b82f6' : '#f59e0b');
                        ?>
                        <tr>
                            <td>
                                <?php echo date_i18n(get_option('date_format'), strtotime($v->visit_date)); ?>
                            </td>
                            <td>
                                <?php echo esc_html($v->supervisor_name); ?>
                            </td>
                            <td>
                                <?php echo esc_html($v->grade_name . ' - ' . $v->section_name); ?>
                            </td>
                            <td>
                                <?php echo esc_html($v->subject_name); ?>
                            </td>
                            <td style="font-weight: 500; color: #1e293b;">
                                <?php echo esc_html($v->teacher_name ?: __('Not Assigned', 'olama-school')); ?>
                            </td>
                            <td>
                                <span
                                    style="background: <?php echo $status_color; ?>15; color: <?php echo $status_color; ?>; padding: 4px 8px; border-radius: 4px; font-weight: 600; font-size: 11px;">
                                    <?php echo esc_html(Olama_School_Helpers::translate($status_label)); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($v->status === 'completed'): ?>
                                    <span style="font-weight: 700; color: #1e293b;">
                                        <?php echo number_format($v->final_score, 1); ?>%
                                    </span>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($v->status === 'planned' || $v->status === 'draft'): ?>
                                    <button type="button" class="button button-small button-primary olama-start-eval-btn"
                                        data-visit-id="<?php echo $v->id; ?>" data-template-id="<?php echo $v->template_id; ?>">
                                        <?php _e('Start Evaluation', 'olama-school'); ?>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="button button-small olama-start-eval-btn"
                                        data-visit-id="<?php echo $v->id; ?>" data-template-id="<?php echo $v->template_id; ?>">
                                        <?php _e('View Evaluation', 'olama-school'); ?>
                                    </button>
                                <?php endif; ?>

                                <button type="button" class="button button-small button-link-delete olama-delete-visit"
                                    data-id="<?php echo $v->id; ?>" style="color: #ef4444;">
                                    <?php _e('Delete', 'olama-school'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; font-style: italic; color: #64748b; padding: 20px;">
                            <?php _e('No visits planned yet.', 'olama-school'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="olama-supervisor-eval-modal" class="olama-modal" style="display:none;">
    <div class="olama-modal-content" style="max-width: 900px; width: 95%; max-height: 90vh; overflow-y: auto;">
        <div class="olama-modal-header" style="position: sticky; top: 0; background: #fff; z-index: 10;">
            <h3><?php _e('Supervisor Visit Evaluation', 'olama-school'); ?></h3>
            <span class="olama-close">&times;</span>
        </div>
        <div class="olama-modal-body" id="olama-supervisor-eval-content" style="padding: 20px;">
            <!-- Content loaded via AJAX -->
            <div style="text-align:center; padding: 40px;">
                <span class="spinner is-active" style="float:none; margin:0;"></span>
                <p><?php _e('Loading evaluation details...', 'olama-school'); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
    .olama-modal {
        position: fixed;
        z-index: 99999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .olama-modal-content {
        background: #fff;
        padding: 0;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
    }

    .olama-modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .olama-modal-header h3 {
        margin: 0;
        font-size: 1.25em;
    }

    .olama-close {
        cursor: pointer;
        font-size: 24px;
        color: #64748b;
    }

    .olama-close:hover {
        color: #0f172a;
    }

    /* Styles for Part 1 Form within Modal */
    .eval-part1-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e2e8f0;
    }

    .eval-info-box {
        background: #f8fafc;
        padding: 10px 15px;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
    }

    .eval-info-box label {
        display: block;
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
    }

    .eval-info-box div {
        font-weight: 600;
        color: #1e293b;
    }

    /* Reused SCSS bits for Part 2 */
    .ev-domain-section {
        margin-bottom: 30px;
    }

    .ev-domain-header {
        background: #1e293b;
        color: #fff;
        padding: 12px 20px;
        border-radius: 8px;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .ev-category-header {
        background: #f1f5f9;
        padding: 10px 15px;
        font-weight: 600;
        color: #334155;
        margin-bottom: 10px;
        border-radius: 4px;
    }

    .ev-indicator-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border-bottom: 1px solid #e2e8f0;
    }

    .ev-scoring-options {
        display: flex;
        gap: 10px;
    }

    .ev-score-option {
        display: flex;
        align-items: center;
        gap: 5px;
        cursor: pointer;
        padding: 6px 12px;
        border-radius: 20px;
        border: 1px solid #cbd5e1;
        background: #fff;
        transition: all 0.2s;
    }

    .ev-score-option.mastered.active {
        background: #10b981;
        color: white;
        border-color: #10b981;
    }

    .ev-score-option.partial.active {
        background: #f59e0b;
        color: white;
        border-color: #f59e0b;
    }

    .ev-score-option.not-mastered.active {
        background: #ef4444;
        color: white;
        border-color: #ef4444;
    }

    .ev-score-option input[type="radio"] {
        display: none;
    }
</style>

<script>
    jQuery(document).ready(function ($) {
        // Delete Visit
        $('.olama-delete-visit').on('click', function () {
            if (!confirm('<?php _e('Are you sure you want to delete this visit and its evaluation data?', 'olama-school'); ?>')) return;

            const visitId = $(this).data('id');
            $.post(ajaxurl, {
                action: 'olama_delete_supervisor_visit',
                id: visitId,
                nonce: '<?php echo wp_create_nonce('olama_delete_visit_nonce'); ?>'
            }, function (response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data || 'Error deleting visit');
                }
            });
        });

        // Open Modal
        $('.olama-start-eval-btn').on('click', function () {
            const visitId = $(this).data('visit-id');
            const templateId = $(this).data('template-id'); $('#olama-supervisor-eval-modal').show();
            $('#olama-supervisor-eval-content').html(`
                <div style="text-align:center; padding: 40px;">
                    <span class="spinner is-active" style="float:none; margin:0;"></span>
                    <p><?php _e('Loading evaluation details...', 'olama-school'); ?></p>
                </div>
            `);

            $.post(ajaxurl, {
                action: 'olama_get_supervisor_evaluation_modal',
                visit_id: visitId,
                template_id: templateId,
                nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
            }, function (res) {
                if (res.success) {
                    $('#olama-supervisor-eval-content').html(res.data.html);
                    initModalEvents();
                } else {
                    $('#olama-supervisor-eval-content').html('<div class="error"><p>' + (res.data || 'Error loading form') + '</p></div>');
                }
            });
        });

        // Close Modal
        $('.olama-close').on('click', function () {
            $('#olama-supervisor-eval-modal').hide();
        });

        $(window).on('click', function (event) {
            if ($(event.target).hasClass('olama-modal')) {
                $('.olama-modal').hide();
            }
        });

        function initModalEvents() {
            // Dropdown dependencies (Unit -> Lessons)
            $('#eval-unit-select').on('change', function () {
                const unitId = $(this).val();
                const lessonSelect = $('#eval-lesson-select');
                lessonSelect.html('<option value="">Loading...</option>');

                if (!unitId) {
                    lessonSelect.html('<option value="">-- Select Lesson --</option>');
                    return;
                }

                $.post(ajaxurl, {
                    action: 'olama_get_lessons',
                    unit_id: unitId,
                    nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
                }, function (res) {
                    if (res.success) {
                        let html = '<option value="">-- Select Lesson --</option>';
                        res.data.forEach(function (l) {
                            html += `<option value="${l.id}">${l.lesson_title}</option>`;
                        });
                        lessonSelect.html(html);
                    } else {
                        lessonSelect.html('<option value="">Error</option>');
                    }
                });
            });

            // Radio Button Selection UX
            $('.ev-score-option').on('click', function () {
                const group = $(this).closest('.ev-scoring-options');
                group.find('.ev-score-option').removeClass('active');
                $(this).addClass('active');
                $(this).find('input[type="radio"]').prop('checked', true);
            });

            // Form Submit
            $('#olama-supervisor-eval-form').on('submit', function (e) {
                e.preventDefault();
                const btn = $(this).find('button[type="submit"]');
                const originalText = btn.text();
                btn.text('<?php _e('Saving...', 'olama-school'); ?>').prop('disabled', true);

                $.post(ajaxurl, {
                    action: 'olama_save_supervisor_evaluation_modal',
                    data: $(this).serialize(),
                    nonce: '<?php echo wp_create_nonce('olama_admin_nonce'); ?>'
                }, function (res) {
                    if (res.success) {
                        location.reload(); // Reload to show updated status/score
                    } else {
                        alert(res.data || 'Error saving evaluation');
                        btn.text(originalText).prop('disabled', false);
                    }
                });
            });
        }
    });
</script>