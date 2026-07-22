<?php
/**
 * Academic Management - Teacher Assignments View
 */
if (!defined('ABSPATH')) {
    exit;
}

$teachers = Olama_School_Teacher::get_teachers();
$grades = Olama_School_Grade::get_grades();
?>
<div class="olama-assignment-wrap">
    <div class="assignment-header-info">
        <h2>
            <?php _e('Assign Teachers to Subjects', 'olama-school'); ?>
        </h2>
        <p>
            <?php _e('Teachers come from active Olama Users employee accounts. Grades, sections, and subjects come from Oracle through Olama Core.', 'olama-school'); ?>
        </p>
    </div>

    <div class="assignment-interface-grid">
        <!-- Step 1: Teachers -->
        <div class="assignment-column" id="teacher-col">
            <div class="column-header">
                <span class="dashicons dashicons-businessman"></span>
                <h3>
                    <?php printf(__('1. Teachers (%d)', 'olama-school'), count($teachers)); ?>
                </h3>
            </div>
            <div class="assignment-search-wrap">
                <input type="search" id="teacher-assignment-search" placeholder="<?php esc_attr_e('Search teacher or employee ID', 'olama-school'); ?>">
            </div>
            <div class="assignment-list" id="teachers-list">
                <?php foreach ($teachers as $teacher): ?>
                    <div class="assignment-item teacher-item" data-id="<?php echo esc_attr($teacher->ID); ?>"
                        data-search="<?php echo esc_attr(strtolower($teacher->display_name . ' ' . $teacher->employee_id . ' ' . $teacher->user_email)); ?>">
                        <div class="item-main">
                            <span class="item-title">
                                <?php echo esc_html($teacher->display_name); ?>
                            </span>
                            <span class="item-sub">
                                <?php echo esc_html($teacher->employee_id ? __('Employee ID:', 'olama-school') . ' ' . $teacher->employee_id : ''); ?>
                            </span>
                        </div>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($teachers)): ?>
                    <div class="select-hint"><p><?php _e('No active synchronized teachers were found. Run the employee sync in Olama Users.', 'olama-school'); ?></p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Step 2: Grades -->
        <div class="assignment-column disabled-col" id="grade-col">
            <div class="column-header">
                <span class="dashicons dashicons-welcome-learn-more"></span>
                <h3>
                    <?php _e('2. Grades', 'olama-school'); ?>
                </h3>
            </div>
            <div class="assignment-list" id="grades-list">
                <?php foreach ($grades as $grade): ?>
                    <div class="assignment-item grade-item" data-id="<?php echo $grade->id; ?>">
                        <span class="item-title">
                            <?php echo esc_html($grade->grade_name); ?>
                        </span>
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 3: Sections -->
        <div class="assignment-column disabled-col" id="section-col">
            <div class="column-header">
                <span class="dashicons dashicons-groups"></span>
                <h3>
                    <?php _e('3. Sections', 'olama-school'); ?>
                </h3>
            </div>
            <div class="assignment-list" id="sections-list">
                <div class="select-hint">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <p>
                        <?php _e('Select Grade first', 'olama-school'); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Step 4: Subjects -->
        <div class="assignment-column disabled-col" id="subject-col">
            <div class="column-header">
                <span class="dashicons dashicons-book"></span>
                <h3>
                    <?php _e('4. Subjects', 'olama-school'); ?>
                </h3>
            </div>
            <div class="assignment-list" id="subjects-list">
                <div class="select-hint">
                    <span class="dashicons dashicons-arrow-left-alt2"></span>
                    <p>
                        <?php _e('Select Section first', 'olama-school'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
