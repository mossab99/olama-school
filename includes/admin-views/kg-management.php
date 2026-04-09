<?php
/**
 * KG Management View - Premium Redesign
 */
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'photo_session';
$active_year = Olama_School_Academic::get_active_year();
$active_semester = Olama_School_Academic::get_active_semester();

$grades = Olama_School_Grade::get_grades();
$sections = array();

$selected_grade_id = isset($_GET['grade_id']) ? intval($_GET['grade_id']) : 0;
$selected_section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;

if ($selected_grade_id) {
    $sections = Olama_School_Section::get_by_grade($selected_grade_id);
}

// Show success notice after redirect from save handler
$kg_save_message = '';
if (isset($_GET['kg_saved']) && $_GET['kg_saved'] === '1') {
    $kg_save_message = '<div class="notice notice-success" style="margin: 15px 0; padding: 12px 16px; border-radius: 6px; border-left: 4px solid #00a32a; background: #f0fdf4;"><strong>✅ ' . Olama_School_Helpers::translate('Saved successfully') . '</strong></div>';
}


?>
<style>
    :root {
        --kg-primary: #2563eb;
        --kg-primary-hover: #1d4ed8;
        --kg-bg: #f8fafc;
        --kg-card-bg: #ffffff;
        --kg-border: #e2e8f0;
        --kg-text: #1e293b;
        --kg-text-light: #64748b;
        --kg-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        --kg-radius: 12px;
    }

    .kg-dashboard {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: var(--kg-text);
        max-width: 1400px;
        margin: 20px auto;
        padding: 0 20px;
    }

    .kg-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        padding: 30px;
        border-radius: var(--kg-radius);
        color: white;
        box-shadow: var(--kg-shadow);
    }

    .kg-header h1 {
        margin: 0;
        font-size: 28px;
        font-weight: 700;
        color: white;
    }

    .kg-header-meta {
        display: flex;
        gap: 20px;
    }

    .meta-badge {
        background: rgba(255, 255, 255, 0.1);
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .kg-filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .kg-card {
        background: var(--kg-card-bg);
        border: 1px solid var(--kg-border);
        border-radius: var(--kg-radius);
        padding: 24px;
        box-shadow: var(--kg-shadow);
        transition: transform 0.2s;
    }

    .kg-card:hover {
        transform: translateY(-2px);
    }

    .kg-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: var(--kg-text-light);
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.025em;
    }

    .kg-select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--kg-border);
        border-radius: 8px;
        background-color: white;
        font-size: 15px;
        outline: none;
        transition: border-color 0.2s;
    }

    .kg-select:focus {
        border-color: var(--kg-primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .kg-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        font-size: 15px;
    }

    .kg-btn-primary {
        background: var(--kg-primary);
        color: white;
    }

    .kg-btn-primary:hover {
        background: var(--kg-primary-hover);
    }

    .kg-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 0;
        border-bottom: 2px solid var(--kg-border);
        padding-bottom: 2px;
    }

    .kg-tab-link {
        padding: 12px 24px;
        text-decoration: none;
        color: var(--kg-text-light);
        font-weight: 600;
        border-radius: 8px 8px 0 0;
        transition: all 0.2s;
        border: 2px solid transparent;
        margin-bottom: -2px;
    }

    .kg-tab-link:hover {
        color: var(--kg-primary);
        background: rgba(37, 99, 235, 0.05);
    }

    .kg-tab-link.active {
        color: var(--kg-primary);
        border-bottom-color: var(--kg-primary);
        background: white;
    }

    .kg-content-area {
        background: white;
        border-radius: 0 0 var(--kg-radius) var(--kg-radius);
        padding: 30px;
        border: 1px solid var(--kg-border);
        border-top: none;
        box-shadow: var(--kg-shadow);
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--kg-text-light);
    }

    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.5;
    }

    /* RTL Support */
    body.rtl .kg-header-meta {
        flex-direction: row-reverse;
    }
</style>

<div class="kg-dashboard">
    <div class="kg-header">
        <div>
            <h1><?php echo Olama_School_Helpers::translate('KG الروضة'); ?></h1>
            <p style="margin: 5px 0 0 0; opacity: 0.8;"><?php echo Olama_School_Helpers::translate('Manage and track KG sessions'); ?></p>
        </div>
        <div class="kg-header-meta">
            <div class="meta-badge">
                <strong><?php echo Olama_School_Helpers::translate('Academic Year'); ?>:</strong> 
                <?php echo $active_year ? esc_html($active_year->year_name) : '—'; ?>
            </div>
            <div class="meta-badge">
                <strong><?php echo Olama_School_Helpers::translate('Semester'); ?>:</strong> 
                <?php echo $active_semester ? esc_html($active_semester->semester_name) : '—'; ?>
            </div>
        </div>
    </div>

    <?php echo $kg_save_message; ?>

    <form method="get" action="" class="kg-filter-grid">
        <input type="hidden" name="page" value="olama-school-kg">
        <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">

        <div class="kg-card">
            <label class="kg-label"><?php echo Olama_School_Helpers::translate('Grade'); ?></label>
            <select name="grade_id" id="kg_grade_id" class="kg-select" onchange="this.form.submit()">
                <option value=""><?php echo Olama_School_Helpers::translate('-- Select Grade --'); ?></option>
                <?php foreach ($grades as $grade): ?>
                    <option value="<?php echo $grade->id; ?>" <?php selected($selected_grade_id, $grade->id); ?>><?php echo $grade->grade_name; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="kg-card">
            <label class="kg-label"><?php echo Olama_School_Helpers::translate('Section'); ?></label>
            <select name="section_id" id="kg_section_id" class="kg-select" onchange="this.form.submit()">
                <option value=""><?php echo Olama_School_Helpers::translate('-- Select Section --'); ?></option>
                <?php foreach ($sections as $section): ?>
                    <option value="<?php echo $section->id; ?>" <?php selected($selected_section_id, $section->id); ?>><?php echo $section->section_name; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="kg-card" style="display: flex; align-items: flex-end;">
            <button type="submit" class="kg-btn kg-btn-primary" style="width: 100%;">
                <?php echo Olama_School_Helpers::translate('Search'); ?>
            </button>
        </div>
    </form>

    <div class="kg-tabs">
        <a href="?page=olama-school-kg&tab=photo_session&grade_id=<?php echo $selected_grade_id; ?>&section_id=<?php echo $selected_section_id; ?>" 
           class="kg-tab-link <?php echo $active_tab === 'photo_session' ? 'active' : ''; ?>">
            <?php echo Olama_School_Helpers::translate('Photo Session'); ?>
        </a>
        <a href="?page=olama-school-kg&tab=graduation_session&grade_id=<?php echo $selected_grade_id; ?>&section_id=<?php echo $selected_section_id; ?>" 
           class="kg-tab-link <?php echo $active_tab === 'graduation_session' ? 'active' : ''; ?>">
            <?php echo Olama_School_Helpers::translate('Graduation Session'); ?>
        </a>
    </div>

    <div class="kg-content-area">
        <?php if (!$selected_grade_id || !$selected_section_id): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔍</div>
                <h3><?php echo Olama_School_Helpers::translate('Selection Required'); ?></h3>
                <p><?php echo Olama_School_Helpers::translate('Please select a grade and section above to manage students.'); ?></p>
            </div>
        <?php else: 
            $students = Olama_School_Student::get_students(array(
                'academic_year_id' => $active_year ? $active_year->id : 0,
                'section_id' => $selected_section_id
            ));
            
            if (empty($students)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">👥</div>
                    <h3><?php echo Olama_School_Helpers::translate('No Students Found'); ?></h3>
                    <p><?php echo Olama_School_Helpers::translate('There are no students enrolled in the selected section.'); ?></p>
                </div>
            <?php else: 
                // Pass a unified ID for the active semester hidden input
                echo '<input type="hidden" id="kg_active_semester_id" value="' . ($active_semester ? $active_semester->id : '') . '">';
                
                if ($active_tab === 'photo_session'):
                    include OLAMA_SCHOOL_PATH . 'includes/admin-views/kg-photo-session.php';
                else:
                    include OLAMA_SCHOOL_PATH . 'includes/admin-views/kg-graduation-session.php';
                endif;
            endif;
        endif; ?>
    </div>
</div>
