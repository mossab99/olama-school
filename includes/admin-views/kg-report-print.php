<?php
/**
 * KG Report Print View
 */
if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html dir="<?php echo Olama_School_Helpers::is_arabic() ? 'rtl' : 'ltr'; ?>">

<head>
    <meta charset="UTF-8">
    <title>
        <?php _e('Student Evaluation Report', 'olama-school'); ?>
    </title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            color: #333;
        }

        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .student-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            font-weight: bold;
        }

        table.kg-report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        table.kg-report-table th,
        table.kg-report-table td {
            border: 1px solid #333;
            padding: 10px;
            text-align: center;
        }

        table.kg-report-table th {
            background: #f0f0f0;
        }

        table.kg-report-table .text-left {
            text-align: <?php echo Olama_School_Helpers::is_arabic() ? 'right' : 'left'; ?>;
        }

        .domain-row {
            background: #e0e0e0;
            font-weight: bold;
            font-size: 1.1em;
        }

        .category-row {
            background: #f9f9f9;
            font-weight: bold;
        }

        .checkmark {
            font-size: 1.2em;
            font-weight: bold;
            color: #000;
        }

        .footer-sig {
            margin-top: 50px;
            display: flex;
            justify-content: space-around;
        }

        .sig-box {
            border-top: 1px solid #333;
            width: 200px;
            text-align: center;
            padding-top: 10px;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                margin: 20px;
            }
        }
    </style>
</head>

<body>
    <div class="no-print"
        style="background: #fdf6b2; padding: 15px; border: 1px solid #e3a008; margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" class="button button-primary">
            <?php _e('Print Report', 'olama-school'); ?>
        </button>
    </div>

    <div class="report-header">
        <h1>
            <?php echo Olama_School_Helpers::translate('Olama School'); ?>
        </h1>
        <h2>
            <?php echo Olama_School_Helpers::translate('Evaluation Form'); ?>
        </h2>
        <p>
            <?php echo esc_html($evaluation->year_name); ?> -
            <?php echo esc_html($evaluation->semester_name); ?>
        </p>
    </div>

    <div class="student-info">
        <div>
            <?php echo Olama_School_Helpers::translate('Select Student'); ?>:
            <?php echo esc_html($evaluation->student_name); ?>
        </div>
        <div>
            <?php _e('Grade:', 'olama-school'); ?>
            <?php echo esc_html($evaluation->grade_name); ?>
        </div>
        <div>
            <?php _e('ID:', 'olama-school'); ?>
            <?php echo esc_html($evaluation->student_uid); ?>
        </div>
    </div>

    <table class="kg-report-table">
        <thead>
            <tr>
                <th rowspan="2" class="text-left">
                    <?php echo Olama_School_Helpers::translate('Indicator Text'); ?>
                </th>
                <th colspan="3">
                    <?php _e('Evaluation', 'olama-school'); ?>
                </th>
                <th rowspan="2">
                    <?php _e('Notes', 'olama-school'); ?>
                </th>
            </tr>
            <tr>
                <th style="width: 60px;">M (أتقن)</th>
                <th style="width: 60px;">P (جزء)</th>
                <th style="width: 60px;">N (لم)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($curriculum as $domain): ?>
                <tr class="domain-row">
                    <td colspan="5" class="text-left">
                        <?php echo esc_html($domain->title_ar); ?>
                    </td>
                </tr>
                <?php foreach ($domain->categories as $category): ?>
                    <tr class="category-row">
                        <td colspan="5" class="text-left" style="padding-right: 30px;">
                            <?php echo esc_html($category->title_ar); ?>
                        </td>
                    </tr>
                    <?php foreach ($category->indicators as $indicator):
                        $score = isset($scores[$indicator->id]) ? $scores[$indicator->id]->score : null;
                        $note = isset($scores[$indicator->id]) ? $scores[$indicator->id]->notes : '';
                        ?>
                        <tr>
                            <td class="text-left" style="padding-right: 40px;">
                                <?php echo esc_html($indicator->indicator_text); ?>
                            </td>
                            <td>
                                <?php echo $score == 3 ? '<span class="checkmark">✔</span>' : ''; ?>
                            </td>
                            <td>
                                <?php echo $score == 2 ? '<span class="checkmark">✔</span>' : ''; ?>
                            </td>
                            <td>
                                <?php echo $score == 1 ? '<span class="checkmark">✔</span>' : ''; ?>
                            </td>
                            <td style="font-size: 0.9em;">
                                <?php echo esc_html($note); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer-sig">
        <div class="sig-box">
            <?php _e('Class Teacher', 'olama-school'); ?>
        </div>
        <div class="sig-box">
            <?php _e('Supervisor', 'olama-school'); ?>
        </div>
        <div class="sig-box">
            <?php _e('Parent Signature', 'olama-school'); ?>
        </div>
    </div>
</body>

</html>