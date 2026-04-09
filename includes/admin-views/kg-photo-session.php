<?php
/**
 * KG Photo Session View - Premium Redesign
 */
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_photo = $wpdb->prefix . 'olama_kg_photo_session';
$semester_id = $active_semester ? $active_semester->id : 0;

// Fetch existing session data, indexed by student_uid
$_raw_data = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $table_photo WHERE semester_id = %d",
    $semester_id
));
$session_data = array();
foreach ($_raw_data as $_row) {
    $session_data[$_row->student_uid] = $_row;
}

?>
<style>
    .kg-session-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-top: 20px;
    }

    .kg-session-table th {
        background: var(--kg-bg);
        padding: 16px;
        text-align: right;
        font-weight: 600;
        font-size: 13px;
        color: var(--kg-text-light);
        text-transform: uppercase;
        border-bottom: 2px solid var(--kg-border);
    }

    .kg-session-table td {
        padding: 16px;
        border-bottom: 1px solid var(--kg-border);
        font-size: 15px;
    }

    .kg-session-table tr:hover td {
        background-color: rgba(37, 99, 235, 0.02);
    }

    .kg-bulk-controls {
        background: var(--kg-bg);
        border-radius: 8px;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border: 1px solid var(--kg-border);
    }

    .kg-input-group {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .kg-fees-input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid var(--kg-border);
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.2s;
    }

    .kg-fees-input:focus {
        border-color: var(--kg-primary);
        box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
    }

    .row-updated {
        font-size: 12px;
        color: var(--kg-text-light);
    }

    .kg-status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: var(--kg-text-light);
    }

    .loading-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(255, 255, 255, 0.7);
        z-index: 10000;
        justify-content: center;
        align-items: center;
        backdrop-filter: blur(2px);
    }

    .kg-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid var(--kg-border);
        border-top-color: var(--kg-primary);
        border-radius: 50%;
        animation: kg-spin 1s linear infinite;
    }

    @keyframes kg-spin {
        to { transform: rotate(360deg); }
    }
</style>

<form method="post" action="" id="kg_photo_form">
    <input type="hidden" name="session_type" value="photo">
    <input type="hidden" name="semester_id" value="<?php echo esc_attr($semester_id); ?>">
    <input type="hidden" name="grade_id" value="<?php echo esc_attr($selected_grade_id ?? 0); ?>">
    <input type="hidden" name="section_id" value="<?php echo esc_attr($selected_section_id ?? 0); ?>">
    <?php wp_nonce_field('olama_kg_session_save', 'olama_kg_nonce'); ?>

    <div class="kg-bulk-controls">
        <div class="kg-input-group">
            <label class="kg-label" style="margin-bottom:0; min-width: 100px;"><?php echo Olama_School_Helpers::translate('Default Fees'); ?></label>
            <input type="text" id="kg_default_photo_fees" class="kg-fees-input" style="width: 120px;" placeholder="0.00">
            <button type="button" class="kg-btn" style="background: #e2e8f0; color: #475569;" onclick="kgApplyDefaultFees()">
                <?php echo Olama_School_Helpers::translate('Apply to All'); ?>
            </button>
        </div>
        
        <div class="kg-input-group">
            <button type="button" class="kg-btn" style="background: white; border: 1px solid var(--kg-border); color: var(--kg-text-light);" onclick="kgToggleAll('attended')">
                <?php echo Olama_School_Helpers::translate('Toggle All Attended'); ?>
            </button>
            <button type="submit" name="olama_save_kg_session" value="1" class="kg-btn kg-btn-primary">
                <?php echo Olama_School_Helpers::translate('Save All Changes'); ?>
            </button>
        </div>
    </div>

    <table class="kg-session-table">
        <thead>
            <tr>
                <th width="80px"><?php echo Olama_School_Helpers::translate('ID'); ?></th>
                <th><?php echo Olama_School_Helpers::translate('Student Name'); ?></th>
                <th width="150px"><?php echo Olama_School_Helpers::translate('Attended Session'); ?></th>
                <th width="200px"><?php echo Olama_School_Helpers::translate('Photo Fees Collected'); ?></th>
                <th width="150px"><?php echo Olama_School_Helpers::translate('Received Photo'); ?></th>
                <th width="200px"><?php echo Olama_School_Helpers::translate('Last Updated'); ?></th>
            </tr>
        </thead>
        <tbody id="kg_students_body">
            <?php foreach ($students as $student): 
                $uid = $student->student_uid;
                $data = isset($session_data[$uid]) ? $session_data[$uid] : (object)[
                    'attended_session' => 0,
                    'fees_collected' => '',
                    'photo_received' => 0,
                    'updated_at' => '—'
                ];
            ?>
                <tr>
                    <td><span style="color: var(--kg-text-light); font-weight: 500;"><?php echo esc_html($student->id); ?></span></td>
                    <td><strong><?php echo esc_html($student->student_name); ?></strong></td>
                    <td>
                        <input type="hidden" name="kg_data[<?php echo esc_attr($uid); ?>][attended_session]" value="0">
                        <input type="checkbox" name="kg_data[<?php echo esc_attr($uid); ?>][attended_session]" value="1" class="kg-check-attended" <?php checked($data->attended_session, 1); ?> style="width: 20px; height: 20px; cursor: pointer;">
                    </td>
                    <td>
                        <input type="text" name="kg_data[<?php echo esc_attr($uid); ?>][fees_collected]" class="kg-fees-input" value="<?php echo esc_attr($data->fees_collected); ?>" placeholder="0.00">
                    </td>
                    <td>
                        <input type="hidden" name="kg_data[<?php echo esc_attr($uid); ?>][photo_received]" value="0">
                        <input type="checkbox" name="kg_data[<?php echo esc_attr($uid); ?>][photo_received]" value="1" class="kg-check-received" <?php checked($data->photo_received, 1); ?> style="width: 20px; height: 20px; cursor: pointer;">
                    </td>
                    <td class="row-updated"><?php echo esc_html($data->updated_at); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top: 30px; text-align: left;">
        <button type="submit" name="olama_save_kg_session" value="1" class="kg-btn kg-btn-primary">
            <?php echo Olama_School_Helpers::translate('Save All Changes'); ?>
        </button>
    </div>
</form>

<script>
document.getElementById('kg_default_photo_fees').addEventListener('input', function() {
    const val = this.value;
    document.querySelectorAll('.kg-fees-input').forEach(function(input) {
        input.value = val;
    });
});

function kgApplyDefaultFees() {
    const val = document.getElementById('kg_default_photo_fees').value;
    document.querySelectorAll('.kg-fees-input').forEach(function(input) {
        if (input.value === '' || input.value === '0.00' || input.value === '0') {
            input.value = val;
        }
    });
}

function kgToggleAll(field) {
    const selector = field === 'attended' ? '.kg-check-attended' : '.kg-check-received';
    const checkboxes = document.querySelectorAll(selector);
    if (!checkboxes.length) return;
    const firstVal = checkboxes[0].checked;
    checkboxes.forEach(function(cb) { cb.checked = !firstVal; });
}
</script>
