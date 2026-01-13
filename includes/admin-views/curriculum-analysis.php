<?php
/**
 * Curriculum Analysis View
 */
if (!defined('ABSPATH')) {
    exit;
}

$grades = Olama_School_Grade::get_grades();
$active_year = Olama_School_Academic::get_active_year();
$semesters = $active_year ? Olama_School_Academic::get_semesters($active_year->id) : array();

$selected_semester_id = isset($_GET['semester_id']) ? intval($_GET['semester_id']) : 0;
$selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0;

$stats = [];
if ($selected_semester_id && $selected_grade_id) {
    // 1. Get all subjects assigned to this grade
    $all_subjects = Olama_School_Subject::get_by_grade($selected_grade_id);

    // 2. Get existing curriculum stats
    $raw_stats = Olama_School_Curriculum::get_curriculum_stats($selected_semester_id, $selected_grade_id);
    $stats_map = [];
    foreach ($raw_stats as $rs) {
        $stats_map[$rs->subject_id] = $rs;
    }

    // 3. Merge: ensure every subject exists in the final list
    foreach ($all_subjects as $subject) {
        $subject_stats = $stats_map[$subject->id] ?? null;
        $stats[] = (object) [
            'subject_id' => $subject->id,
            'subject_name' => $subject->subject_name,
            'unit_count' => $subject_stats ? $subject_stats->unit_count : 0,
            'lesson_count' => $subject_stats ? $subject_stats->lesson_count : 0,
        ];
    }
}
?>

<div class="olama-card" style="margin-bottom: 20px;">
    <form method="get" action="">
        <input type="hidden" name="page" value="olama-school-curriculum">
        <input type="hidden" name="tab" value="analysis">

        <div style="display: flex; gap: 20px; align-items: flex-end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px;">
                <label class="olama-label"><?php echo Olama_School_Helpers::translate('Semester'); ?></label>
                <select name="semester_id" class="olama-select" onchange="this.form.submit()">
                    <option value=""><?php echo Olama_School_Helpers::translate('-- Select Semester --'); ?></option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?php echo $sem->id; ?>" <?php selected($selected_semester_id, $sem->id); ?>>
                            <?php echo esc_html($sem->semester_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex: 1; min-width: 200px;">
                <label class="olama-label"><?php echo Olama_School_Helpers::translate('Grade'); ?></label>
                <select name="grade_id" class="olama-select" onchange="this.form.submit()">
                    <option value=""><?php echo Olama_School_Helpers::translate('-- Select Grade --'); ?></option>
                    <?php foreach ($grades as $grade): ?>
                        <option value="<?php echo $grade->id; ?>" <?php selected($selected_grade_id, $grade->id); ?>>
                            <?php echo esc_html($grade->grade_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </form>
</div>

<!-- Curriculum Report Table -->
<div class="olama-card" style="margin-bottom: 20px;">
    <h2 style="margin-top: 0; color: #2271b1; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
        <?php echo Olama_School_Helpers::translate('Curriculum Coverage Summary'); ?>
    </h2>

    <?php if (!empty($stats)):
        $total_units = 0;
        $total_lessons = 0;
        ?>
            <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="font-weight: 700;"><?php echo Olama_School_Helpers::translate('Subject'); ?></th>
                    <th style="font-weight: 700;"><?php echo Olama_School_Helpers::translate('Number of Units'); ?></th>
                    <th style="font-weight: 700;"><?php echo Olama_School_Helpers::translate('Number of Lessons'); ?></th>
                </tr>
            </thead>
            <tbody>
                    <?php foreach ($stats as $stat):
                        $total_units += $stat->unit_count;
                        $total_lessons += $stat->lesson_count;
                        ?>
                    <tr>
                        <td style="font-weight: 600;"><?php echo esc_html($stat->subject_name); ?></td>
                        <td><?php echo sprintf(Olama_School_Helpers::translate('%d Units'), $stat->unit_count); ?></td>
                        <td><?php echo sprintf(Olama_School_Helpers::translate('%d Lessons'), $stat->lesson_count); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot style="background: #f8fafc; font-weight: 800; border-top: 2pt solid #e2e8f0;">
                <tr>
                    <td style="color: #2271b1;"><?php echo Olama_School_Helpers::translate('Total'); ?></td>
                    <td><?php echo sprintf(Olama_School_Helpers::translate('%d Units'), $total_units); ?></td>
                    <td><?php echo sprintf(Olama_School_Helpers::translate('%d Lessons'), $total_lessons); ?></td>
                </tr>
            </tfoot>
        </table>
    <?php else: ?>
        <p style="text-align: center; color: #64748b; padding: 40px;">
            <?php echo Olama_School_Helpers::translate('No curriculum data found for the selected filters.'); ?>
        </p>
    <?php endif; ?>
</div>

<!-- Charts Section -->
<div style="max-width: 600px; margin: 0 auto;">
    <div class="olama-card">
        <h3 style="margin-top: 0; text-align: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px;">
            <?php echo Olama_School_Helpers::translate('Lessons Distribution (Selected Grade)'); ?>
        </h3>
        <div style="height: 400px; padding: 20px;">
            <canvas id="gradeLessonsChart"></canvas>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Grade Lessons Distribution Chart
        const statsData = <?php echo json_encode($stats); ?>;
        if (statsData.length > 0) {
            const ctxGrade = document.getElementById('gradeLessonsChart').getContext('2d');
            new Chart(ctxGrade, {
                type: 'pie',
                data: {
                    labels: statsData.map(s => s.subject_name),
                    datasets: [{
                        data: statsData.map(s => s.lesson_count),
                        backgroundColor: [
                            '#2563eb', '#dc2626', '#16a34a', '#ca8a04',
                            '#9333ea', '#0891b2', '#ea580c', '#475569'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 12, weight: '600' }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const current = context.raw;
                                    const percentage = ((current / total) * 100).toFixed(1);
                                    return `${context.label}: ${current} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
    });
</script>

<style>
    .olama-label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.9em;
        color: #555;
    }

    .olama-select {
        width: 100%;
        height: 35px;
    }
</style>