<?php
/**
 * Academic Supervision - Reports Tab (Mini Dashboard)
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Filters
$active_year = Olama_School_Academic::get_active_year();
$active_year_id = $active_year ? $active_year->id : 0;

$semesters = $active_year_id ? Olama_School_Academic::get_semesters($active_year_id) : array();
$active_semester = Olama_School_Academic::get_active_semester($active_year_id);
$selected_semester_id = $active_semester ? intval($active_semester->id) : ($semesters[0]->id ?? 0);

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
$current_week_start = '';

if (!empty($current_month_weeks)) {
    if (isset($_GET['week_start'])) {
        $req = sanitize_text_field($_GET['week_start']);
        foreach ($current_month_weeks as $w) {
            if ($w['val'] === $req) {
                $current_week_start = $req;
                break;
            }
        }
    }
    if (empty($current_week_start)) {
        foreach ($current_month_weeks as $w) {
            $range = Olama_School_Helpers::get_week_range($w['val']);
            if ($today >= $range['start'] && $today <= $range['end']) {
                $current_week_start = $w['val'];
                break;
            }
        }
    }
    if (empty($current_week_start)) {
        $current_week_start = $current_month_weeks[0]['val'] ?? '';
    }
}

$selected_week_start = $_GET['week_start'] ?? $current_week_start;
$selected_supervisor_id = !empty($_GET['supervisor_id']) ? intval($_GET['supervisor_id']) : 0;

$supervisors = get_users(['role__in' => ['supervisor', 'olama_supervisor', 'administrator', 'school_manager', 'editor'], 'number' => -1]);

// --- Section 1: Dashboard Top Weekly Plan Global Stats ---
$week_stats_global = [
    'visits' => 0,
    'planned' => 0,
    'completed' => 0,
    'avg_score' => 0,
    'completion_rate' => 0
];

if ($current_week_start) {
    $c_range = Olama_School_Helpers::get_week_range($current_week_start);
    $ws_data = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) as total, AVG(final_score) as avg_score, 
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned
         FROM {$wpdb->prefix}olama_supervisor_visits
         WHERE visit_date BETWEEN %s AND %s",
        $c_range['start'], $c_range['end']
    ));
    if ($ws_data) {
        $week_stats_global['visits'] = $ws_data->total;
        $week_stats_global['planned'] = $ws_data->planned;
        $week_stats_global['completed'] = $ws_data->completed;
        $week_stats_global['avg_score'] = $ws_data->avg_score;
        $week_stats_global['completion_rate'] = $ws_data->total > 0 ? ($ws_data->completed / $ws_data->total) * 100 : 0;
    }
}

// --- Section 2: Supervisor Performance Cards ---
$perf_stats = [
    'week_total' => 0, 'week_planned' => 0, 'week_completed' => 0,
    'month_total' => 0, 'month_planned' => 0, 'month_completed' => 0,
    'semester_total' => 0, 'semester_planned' => 0, 'semester_completed' => 0,
    'year_total' => 0, 'year_planned' => 0, 'year_completed' => 0,
    'week_avg_score' => 0,
    'week_completion_rate' => 0
];

if ($selected_supervisor_id) {
    // Determine bounds for week, month, semester, year based on $selected_week_start
    $week_range = Olama_School_Helpers::get_week_range($selected_week_start);
    $week_start = $week_range['start'];
    $week_end = $week_range['end'];
    
    $month_start = date('Y-m-01', strtotime($week_start));
    $month_end = date('Y-m-t', strtotime($week_start));
    
    $sem_start = $active_semester ? $active_semester->start_date : $week_start;
    $sem_end = $active_semester ? $active_semester->end_date : $week_end;
    
    $year_start = $active_year ? $active_year->start_date : $week_start;
    $year_end = $active_year ? $active_year->end_date : $week_end;
    
    $perf_data = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            SUM(CASE WHEN visit_date BETWEEN %s AND %s THEN 1 ELSE 0 END) as week_count,
            SUM(CASE WHEN visit_date BETWEEN %s AND %s AND status = 'planned' THEN 1 ELSE 0 END) as week_planned,
            SUM(CASE WHEN visit_date BETWEEN %s AND %s AND status = 'completed' THEN 1 ELSE 0 END) as week_completed,
            
            SUM(CASE WHEN visit_date BETWEEN %s AND %s THEN 1 ELSE 0 END) as month_count,
            SUM(CASE WHEN visit_date BETWEEN %s AND %s AND status = 'planned' THEN 1 ELSE 0 END) as month_planned,
            SUM(CASE WHEN visit_date BETWEEN %s AND %s AND status = 'completed' THEN 1 ELSE 0 END) as month_completed,
            
            SUM(CASE WHEN visit_date BETWEEN %s AND %s THEN 1 ELSE 0 END) as sem_count,
            SUM(CASE WHEN visit_date BETWEEN %s AND %s AND status = 'planned' THEN 1 ELSE 0 END) as sem_planned,
            SUM(CASE WHEN visit_date BETWEEN %s AND %s AND status = 'completed' THEN 1 ELSE 0 END) as sem_completed,
            
            SUM(CASE WHEN visit_date BETWEEN %s AND %s THEN 1 ELSE 0 END) as year_count,
            SUM(CASE WHEN visit_date BETWEEN %s AND %s AND status = 'planned' THEN 1 ELSE 0 END) as year_planned,
            SUM(CASE WHEN visit_date BETWEEN %s AND %s AND status = 'completed' THEN 1 ELSE 0 END) as year_completed,
            
            AVG(CASE WHEN visit_date BETWEEN %s AND %s THEN final_score ELSE NULL END) as week_avg
         FROM {$wpdb->prefix}olama_supervisor_visits
         WHERE supervisor_id = %d",
        $week_start, $week_end, $week_start, $week_end, $week_start, $week_end,
        $month_start, $month_end, $month_start, $month_end, $month_start, $month_end,
        $sem_start, $sem_end, $sem_start, $sem_end, $sem_start, $sem_end,
        $year_start, $year_end, $year_start, $year_end, $year_start, $year_end,
        $week_start, $week_end,
        $selected_supervisor_id
    ));
    
    if ($perf_data) {
        $perf_stats['week_total'] = intval($perf_data->week_count);
        $perf_stats['week_planned'] = intval($perf_data->week_planned);
        $perf_stats['week_completed'] = intval($perf_data->week_completed);
        
        $perf_stats['month_total'] = intval($perf_data->month_count);
        $perf_stats['month_planned'] = intval($perf_data->month_planned);
        $perf_stats['month_completed'] = intval($perf_data->month_completed);
        
        $perf_stats['semester_total'] = intval($perf_data->sem_count);
        $perf_stats['semester_planned'] = intval($perf_data->sem_planned);
        $perf_stats['semester_completed'] = intval($perf_data->sem_completed);
        
        $perf_stats['year_total'] = intval($perf_data->year_count);
        $perf_stats['year_planned'] = intval($perf_data->year_planned);
        $perf_stats['year_completed'] = intval($perf_data->year_completed);
        
        $perf_stats['week_avg_score'] = floatval($perf_data->week_avg);
        $perf_stats['week_completion_rate'] = $perf_stats['week_total'] > 0 ? ($perf_stats['week_completed'] / $perf_stats['week_total']) * 100 : 0;
    }
}

// --- Section 3: Coverage Table Logic ---
// Fetch all active sections for this year/semester
$sections = $wpdb->get_results($wpdb->prepare(
    "SELECT sec.id as section_id, sec.section_name, g.grade_name, g.id as grade_id
     FROM {$wpdb->prefix}olama_sections sec
     JOIN {$wpdb->prefix}olama_grades g ON sec.grade_id = g.id
     WHERE sec.academic_year_id = %d
     ORDER BY CAST(g.grade_level AS UNSIGNED) ASC, sec.section_name ASC",
    $active_year_id
));

// Fetch Visit Counts per Section for selected range
$week_range = Olama_School_Helpers::get_week_range($selected_week_start);
if ($week_range) {
    $visit_counts = $wpdb->get_results($wpdb->prepare(
        "SELECT s.section_id, v.supervisor_id, u.display_name as supervisor_name, COUNT(*) as visit_count
         FROM {$wpdb->prefix}olama_supervisor_visits v
         JOIN {$wpdb->prefix}olama_schedule s ON v.schedule_id = s.id
         JOIN {$wpdb->users} u ON v.supervisor_id = u.ID
         WHERE v.visit_date BETWEEN %s AND %s
         GROUP BY s.section_id, v.supervisor_id, u.display_name
         ORDER BY u.display_name ASC",
        $week_range['start'], $week_range['end']
    ));
} else {
    $visit_counts = [];
}

$vc_map = [];
foreach ($visit_counts as $vc) {
    if (!isset($vc_map[$vc->section_id])) $vc_map[$vc->section_id] = [];
    $vc_map[$vc->section_id][$vc->supervisor_id] = [
        'count' => $vc->visit_count,
        'name' => $vc->supervisor_name
    ];
}

// Fetch Assignments for this semester
$assignments_raw = $wpdb->get_results($wpdb->prepare(
    "SELECT a.grade_id, a.subject_id, a.supervisor_id, u.display_name as supervisor_name
     FROM {$wpdb->prefix}olama_supervisor_assignments a
     JOIN {$wpdb->users} u ON a.supervisor_id = u.ID
     WHERE a.academic_year_id = %d AND a.semester_id = %d",
    $active_year_id, $selected_semester_id
));

$assignments_map = [];
foreach ($assignments_raw as $ar) {
    if (!isset($assignments_map[$ar->grade_id])) $assignments_map[$ar->grade_id] = [];
    if (!in_array($ar->supervisor_name, $assignments_map[$ar->grade_id])) {
        $assignments_map[$ar->grade_id][] = $ar->supervisor_name;
    }
}
?>

<div class="olama-supervision-reports-dashboard">

    <!-- SECTION 1: Top Real-time Week Summary -->
    <div style="background: linear-gradient(135deg, #1e293b 0%, #334155 100%); padding: 30px; border-radius: 12px; margin-bottom: 25px; color: #fff; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div>
                <h2 style="margin: 0; font-size: 20px; font-weight: 700; color: #fff;"><?php _e('Current Week Summary', 'olama-school'); ?></h2>
                <div style="opacity: 0.7; font-size: 13px; margin-top: 5px;">
                    <?php if ($current_week_start): $c_range = Olama_School_Helpers::get_week_range($current_week_start); ?>
                        <?php echo sprintf(__('Period: %s to %s', 'olama-school'), '<b>'.date_i18n(get_option('date_format'), strtotime($c_range['start'])).'</b>', '<b>'.date_i18n(get_option('date_format'), strtotime($c_range['end'])).'</b>'); ?>
                    <?php else: ?>
                        <span style="color: #ef4444;"><?php _e('No active week found for today.', 'olama-school'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); font-size: 12px; padding: 6px 15px; border-radius: 20px; font-weight: 600;">
                REAL-TIME DATA
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 10px; border-left: 4px solid #6366f1;">
                <div style="font-size: 12px; opacity: 0.7; margin-bottom: 5px;"><?php _e('Total Visits', 'olama-school'); ?></div>
                <div style="font-size: 28px; font-weight: 800; display:flex; align-items: baseline; gap: 8px;">
                    <?php echo $week_stats_global['visits']; ?>
                    <span style="font-size: 12px; font-weight: 500; opacity: 0.8;"><?php echo sprintf(__('%d planned, %d completed', 'olama-school'), $week_stats_global['planned'] ?? 0, $week_stats_global['completed'] ?? 0); ?></span>
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 10px; border-left: 4px solid #10b981;">
                <div style="font-size: 12px; opacity: 0.7; margin-bottom: 5px;"><?php _e('Avg. Evaluation Score', 'olama-school'); ?></div>
                <div style="font-size: 28px; font-weight: 800; color: #10b981;">
                    <?php echo number_format($week_stats_global['avg_score'], 1); ?>
                </div>
            </div>
            <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 10px; border-left: 4px solid #f59e0b;">
                <div style="font-size: 12px; opacity: 0.7; margin-bottom: 5px;"><?php _e('Completion Rate', 'olama-school'); ?></div>
                <div style="font-size: 28px; font-weight: 800;">
                    <?php echo number_format($week_stats_global['completion_rate'], 1); ?>%
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2: Supervisor Filtering and Mini Dashboard -->
    <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 25px;">
        <form method="GET" style="display: flex; gap: 15px; align-items: flex-end; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #f1f5f9;">
            <input type="hidden" name="page" value="olama-school-supervision">
            <input type="hidden" name="tab" value="reports">
            
            <div style="flex: 1;">
                <label style="display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px;"><?php _e('Academic Year', 'olama-school'); ?></label>
                <input type="text" value="<?php echo esc_attr(Olama_School_Helpers::translate($active_year ? $active_year->year_name : '')); ?>" disabled style="width:100%; opacity:0.6; background:#f8fafc;">
            </div>

            <div style="flex: 1;">
                <label style="display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px;"><?php _e('Semester', 'olama-school'); ?></label>
                <input type="text" value="<?php echo esc_attr(Olama_School_Helpers::translate($active_semester ? $active_semester->semester_name : '')); ?>" disabled style="width:100%; opacity:0.6; background:#f8fafc;">
            </div>

            <div style="flex: 1;">
                <label style="display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px;"><?php _e('Month', 'olama-school'); ?></label>
                <select name="plan_month" style="width:100%;" onchange="this.form.submit()">
                    <?php if (!empty($months_weeks)): foreach ($months_weeks as $m_key => $weeks): ?>
                        <option value="<?php echo esc_attr($m_key); ?>" <?php selected($selected_month, $m_key); ?>>
                            <?php echo date_i18n('F Y', strtotime($m_key . '-01')); ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <div style="flex: 1.5;">
                <label style="display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px;"><?php _e('Week Start', 'olama-school'); ?></label>
                <select name="week_start" style="width:100%;" onchange="this.form.submit()">
                    <?php
                    $w_count = 1;
                    foreach ($current_month_weeks as $w): ?>
                        <option value="<?php echo esc_attr($w['val']); ?>" <?php selected($selected_week_start, $w['val']); ?>>
                            <?php echo sprintf(__('%s %d', 'olama-school'), __('Week', 'olama-school'), $w_count) . ' ' . esc_html($w['label']); ?>
                        </option>
                    <?php $w_count++; endforeach; ?>
                </select>
            </div>
            
            <div style="flex: 1.5;">
                <label style="display:block; font-size:12px; font-weight:600; color:#64748b; margin-bottom:6px;"><?php _e('Supervisor', 'olama-school'); ?></label>
                <select name="supervisor_id" style="width:100%;">
                    <option value="0"><?php _e('-- Select Supervisor --', 'olama-school'); ?></option>
                    <?php foreach ($supervisors as $sup): ?>
                        <option value="<?php echo $sup->ID; ?>" <?php selected($selected_supervisor_id, $sup->ID); ?>><?php echo esc_html($sup->display_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="flex: none; display: flex; gap: 10px;">
                <button type="submit" class="button button-primary" style="height: 35px; background: #6366f1; border-color: #6366f1;"><?php _e('Update Dashboard', 'olama-school'); ?></button>
                <a href="<?php echo admin_url('admin.php?page=olama-school-supervision&tab=reports'); ?>" class="button" style="height: 35px;"><?php _e('Clear', 'olama-school'); ?></a>
            </div>
        </form>

        <?php if ($selected_supervisor_id): ?>
            <!-- Supervisor Cards -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px;">
                <!-- Week Card -->
                <div class="stat-card" style="padding: 15px; border-radius: 8px; border: 1px solid #f1f5f9; background: #f8fafc; font-size: 11px;">
                    <div style="font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 5px;"><?php _e('Visits this Week', 'olama-school'); ?></div>
                    <div style="font-size: 24px; font-weight: 800; color: #1e293b;"><?php echo $perf_stats['week_total']; ?></div>
                    <div style="display: flex; gap: 10px; margin-top: 8px; border-top: 1px solid #e2e8f0; padding-top: 8px;">
                        <div style="color: #6366f1; font-weight: 600;">📝 <?php echo $perf_stats['week_planned']; ?> <?php _e('Planned', 'olama-school'); ?></div>
                        <div style="color: #10b981; font-weight: 600;">✅ <?php echo $perf_stats['week_completed']; ?> <?php _e('Completed', 'olama-school'); ?></div>
                    </div>
                </div>
                <!-- Month Card -->
                <div class="stat-card" style="padding: 15px; border-radius: 8px; border: 1px solid #f1f5f9; background: #f8fafc; font-size: 11px;">
                    <div style="font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 5px;"><?php _e('Visits this Month', 'olama-school'); ?></div>
                    <div style="font-size: 24px; font-weight: 800; color: #1e293b;"><?php echo $perf_stats['month_total']; ?></div>
                    <div style="display: flex; gap: 10px; margin-top: 8px; border-top: 1px solid #e2e8f0; padding-top: 8px;">
                        <div style="color: #6366f1; font-weight: 600;">📝 <?php echo $perf_stats['month_planned']; ?> <?php _e('Planned', 'olama-school'); ?></div>
                        <div style="color: #10b981; font-weight: 600;">✅ <?php echo $perf_stats['month_completed']; ?> <?php _e('Completed', 'olama-school'); ?></div>
                    </div>
                </div>
                <!-- Semester Card -->
                <div class="stat-card" style="padding: 15px; border-radius: 8px; border: 1px solid #f1f5f9; background: #f8fafc; font-size: 11px;">
                    <div style="font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 5px;"><?php _e('Visits this Semester', 'olama-school'); ?></div>
                    <div style="font-size: 24px; font-weight: 800; color: #1e293b;"><?php echo $perf_stats['semester_total']; ?></div>
                    <div style="display: flex; gap: 10px; margin-top: 8px; border-top: 1px solid #e2e8f0; padding-top: 8px;">
                        <div style="color: #6366f1; font-weight: 600;">📝 <?php echo $perf_stats['semester_planned']; ?> <?php _e('Planned', 'olama-school'); ?></div>
                        <div style="color: #10b981; font-weight: 600;">✅ <?php echo $perf_stats['semester_completed']; ?> <?php _e('Completed', 'olama-school'); ?></div>
                    </div>
                </div>
                <!-- Year Card -->
                <div class="stat-card" style="padding: 15px; border-radius: 8px; border: 1px solid #f1f5f9; background: #f8fafc; font-size: 11px;">
                    <div style="font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 5px;"><?php _e('Visits this Year', 'olama-school'); ?></div>
                    <div style="font-size: 24px; font-weight: 800; color: #1e293b;"><?php echo $perf_stats['year_total']; ?></div>
                    <div style="display: flex; gap: 10px; margin-top: 8px; border-top: 1px solid #e2e8f0; padding-top: 8px;">
                        <div style="color: #6366f1; font-weight: 600;">📝 <?php echo $perf_stats['year_planned']; ?> <?php _e('Planned', 'olama-school'); ?></div>
                        <div style="color: #10b981; font-weight: 600;">✅ <?php echo $perf_stats['year_completed']; ?> <?php _e('Completed', 'olama-school'); ?></div>
                    </div>
                </div>
            </div>
            
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 15px; display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; gap: 30px;">
                    <div>
                        <div style="font-size: 11px; font-weight: 600; color: #1e40af; text-transform: uppercase;"><?php _e('Weekly Avg. Score', 'olama-school'); ?></div>
                        <div style="font-size: 20px; font-weight: 800; color: #1e40af;"><?php echo number_format($perf_stats['week_avg_score'], 1); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 11px; font-weight: 600; color: #1e40af; text-transform: uppercase;"><?php _e('Weekly Completion Rate', 'olama-school'); ?></div>
                        <div style="font-size: 20px; font-weight: 800; color: #1e40af;"><?php echo number_format($perf_stats['week_completion_rate'], 1); ?>%</div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #64748b; font-size: 14px;">
                <span class="dashicons dashicons-chart-pie" style="font-size: 48px; width: 48px; height: 48px; color: #cbd5e1; margin-bottom: 15px; display: block; margin-left: auto; margin-right: auto;"></span>
                <?php _e('Select a supervisor to view their performance metrics and dashboard.', 'olama-school'); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- SECTION 3: Coverage Table -->
    <div style="background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
        <h3 style="margin-top: 0; font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 20px;"><?php _e('Coverage Table', 'olama-school'); ?></h3>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Grade', 'olama-school'); ?></th>
                    <th><?php _e('Section', 'olama-school'); ?></th>
                    <th><?php _e('Assigned Supervisors', 'olama-school'); ?></th>
                    <th><?php _e('Supervisor Visits', 'olama-school'); ?></th>
                    <th><?php _e('Total Visits (Grade-Section)', 'olama-school'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sections): ?>
                    <?php foreach ($sections as $sec): 
                        // Determine assigned supervisors for this grade
                        $assigned_sups = $assignments_map[$sec->grade_id] ?? [];
                        
                        // Determine visits for this section
                        $sec_visits = $vc_map[$sec->section_id] ?? [];
                        $total_sec_visits = 0;
                        foreach ($sec_visits as $sv_data) {
                            $total_sec_visits += $sv_data['count'];
                        }
                        
                        $row_count = max(1, count($sec_visits));
                        $is_first = true;
                        
                        if (empty($sec_visits)) {
                            $sec_visits = [0 => ['count' => 0, 'name' => '']]; // empty placeholder
                        }
                    ?>
                        <?php foreach ($sec_visits as $sup_id => $sv_data): ?>
                            <tr>
                                <?php if ($is_first): ?>
                                    <td rowspan="<?php echo $row_count; ?>" style="vertical-align: middle;">
                                        <?php echo esc_html($sec->grade_name); ?>
                                    </td>
                                    <td rowspan="<?php echo $row_count; ?>" style="vertical-align: middle; font-weight: 600;">
                                        <?php echo esc_html($sec->section_name); ?>
                                    </td>
                                    <td rowspan="<?php echo $row_count; ?>" style="vertical-align: middle;">
                                        <?php if (!empty($assigned_sups)): ?>
                                            <ul style="margin: 0; padding-left: 15px;">
                                                <?php foreach ($assigned_sups as $ass_sup): ?>
                                                    <li><?php echo esc_html($ass_sup); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-style: italic; font-size: 11px;"><?php _e('None', 'olama-school'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                
                                <td style="vertical-align: middle;">
                                    <?php if ($sv_data['count'] > 0): ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="font-weight: 600; color: #334155;"><?php echo esc_html($sv_data['name']); ?></span>
                                            <span style="background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700;"><?php echo $sv_data['count']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic; font-size: 11px;"><?php _e('No visits scheduled', 'olama-school'); ?></span>
                                    <?php endif; ?>
                                </td>
                                
                                <?php if ($is_first): ?>
                                    <td rowspan="<?php echo $row_count; ?>" style="vertical-align: middle; text-align: center;">
                                        <span style="font-size: 18px; font-weight: 800; color: <?php echo $total_sec_visits > 0 ? '#10b981' : '#94a3b8'; ?>;">
                                            <?php echo $total_sec_visits; ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php $is_first = false; endforeach; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: #64748b; padding: 15px;"><?php _e('No sections found.', 'olama-school'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>