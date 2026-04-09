<?php
/**
 * Olama School Helpers Class
 * Shared utility functions used across the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_School_Helpers
{
    /**
     * Get default services for the Family Gateway
     */
    public static function get_default_gateway_services()
    {
        return array(
            array(
                'title_ar' => 'تقرير التقييم',
                'title_en' => 'Evaluation Report',
                'url' => '/family-evaluation/',
                'icon' => 'assignment',
                'shortcode' => '[olama_family_performance]'
            ),
            array(
                'title_ar' => 'الاختبارات الإلكترونية',
                'title_en' => 'Online Exams',
                'url' => '/online-exams/',
                'icon' => 'quiz',
                'shortcode' => '[olama_online_exams]'
            ),
            array(
                'title_ar' => 'الخطة الأسبوعية',
                'title_en' => 'Weekly Plan',
                'url' => '/weekly-plan/',
                'icon' => 'event_note',
                'shortcode' => '[olama_weekly_plan]'
            ),
            array(
                'title_ar' => 'جدول الاختبارات',
                'title_en' => 'Exam Schedule',
                'url' => '/exam-schedule/',
                'icon' => 'calendar_month',
                'shortcode' => '[olama_exam_report]'
            )
        );
    }

    /**
     * Get user display name safely
     */
    public static function get_user_display_name($user_id)
    {
        $user = get_userdata($user_id);
        return $user ? $user->display_name : '';
    }

    /**
     * Calculate progress status based on plan date vs lesson dates
     * 
     * @param string $plan_date The date the plan was executed
     * @param string $start_date The lesson start date
     * @param string $end_date The lesson end date
     * @return array|null Status array with 'label' and 'class' keys
     */
    public static function get_progress_status($plan_date, $start_date, $end_date)
    {
        if (!$start_date || !$end_date) {
            return null;
        }

        $plan_ts = strtotime($plan_date);
        $start_ts = strtotime($start_date);
        $end_ts = strtotime($end_date);

        if ($plan_ts >= $start_ts && $plan_ts <= $end_ts) {
            return array('label' => __('On-time', 'olama-school'), 'class' => 'status-ontime');
        } elseif ($plan_ts > $end_ts) {
            $days = ceil(($plan_ts - $end_ts) / 86400);
            return array('label' => sprintf(__('Delayed by %d days', 'olama-school'), $days), 'class' => 'status-delayed');
        } else {
            $days = ceil(($start_ts - $plan_ts) / 86400);
            return array('label' => sprintf(__('Bypass by %d days', 'olama-school'), $days), 'class' => 'status-bypass');
        }
    }

    /**
     * Get the active week start date based on plugin settings:
     * - If today is the day before the configured start day, return tomorrow's date
     * - Otherwise, return the most recent occurrence of the start day
     * 
     * @return string Date in Y-m-d format
     */
    public static function get_active_week_start()
    {
        $settings = get_option('olama_school_settings', array());
        $start_day_name = $settings['start_day'] ?? 'Sunday';

        $today = current_time('timestamp');
        $today_idx = (int) date('w', $today); // 0 (Sunday) to 6 (Saturday)

        $all_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $start_idx = array_search($start_day_name, $all_days);

        if ($start_idx === false)
            $start_idx = 0;

        // Switch day is the day before start day
        $switch_idx = ($start_idx - 1 + 7) % 7;

        if ($today_idx === $switch_idx) {
            // It's the switch day, return tomorrow
            return date('Y-m-d', $today + 86400);
        }

        // Return the most recent start day
        $days_to_subtract = ($today_idx - $start_idx + 7) % 7;
        return date('Y-m-d', $today - ($days_to_subtract * 86400));
    }

    /**
     * Get the previous week start date
     * 
     * @return string Date in Y-m-d format
     */
    public static function get_previous_week_start()
    {
        $active_start = self::get_active_week_start();
        return date('Y-m-d', strtotime($active_start . ' -7 days'));
    }

    /**
     * Get week date range from a given date based on school settings
     * 
     * @param string $date Any date within the week
     * @return array Array with 'start' and 'end' keys
     */
    public static function get_week_range($date)
    {
        $settings = get_option('olama_school_settings', array());
        $start_day_name = $settings['start_day'] ?? 'Sunday';
        $last_day_name = $settings['last_day'] ?? 'Thursday';

        $ts = strtotime($date);
        $today_idx = (int) date('w', $ts);

        $all_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $start_idx = array_search($start_day_name, $all_days);
        $last_idx = array_search($last_day_name, $all_days);

        if ($start_idx === false)
            $start_idx = 0;
        if ($last_idx === false)
            $last_idx = 4;

        $days_to_subtract = ($today_idx - $start_idx + 7) % 7;
        $week_start = date('Y-m-d', $ts - ($days_to_subtract * 86400));

        $days_diff = ($last_idx - $start_idx + 7) % 7;
        $week_end = date('Y-m-d', strtotime($week_start . " +$days_diff days"));

        return array(
            'start' => $week_start,
            'end' => $week_end
        );
    }

    /**
     * Filter grades/sections by teacher assignment for non-admin users
     * 
     * @param array $items Array of grade or section objects
     * @param int $user_id The user ID to filter for
     * @param string $type Either 'grades' or 'sections'
     * @param int $grade_id Optional grade ID for section filtering
     * @return array Filtered array
     */
    public static function filter_by_assignment($items, $user_id, $type = 'grades', $grade_id = 0)
    {
        if (Olama_School_Permissions::can('olama_manage_academic_assignment')) {
            return $items;
        }

        global $wpdb;

        if ($type === 'grades') {
            $assigned_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT grade_id FROM {$wpdb->prefix}olama_teacher_assignments WHERE teacher_id = %d",
                $user_id
            ));
        } else {
            $assigned_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT section_id FROM {$wpdb->prefix}olama_teacher_assignments WHERE teacher_id = %d AND grade_id = %d",
                $user_id,
                $grade_id
            ));
        }

        $filtered = array_filter($items, function ($item) use ($assigned_ids) {
            return in_array($item->id, $assigned_ids);
        });

        return array_values($filtered);
    }

    /**
     * Check if the plugin is currently in Arabic mode
     * 
     * @return bool
     */
    public static function is_arabic()
    {
        $settings = get_option('olama_school_settings', array());
        return isset($settings['default_lang']) && $settings['default_lang'] === 'ar';
    }

    /**
     * Format date according to plugin settings
     * 
     * @param string|int $date Date string or timestamp
     * @param bool $include_time Whether to include time in output
     * @param string $custom_format Optional custom format override
     * @return string Formatted date string
     */
    public static function format_date($date, $include_time = false, $custom_format = '')
    {
        if (empty($date)) {
            return '';
        }

        // Convert to timestamp if needed
        $timestamp = is_numeric($date) ? $date : strtotime($date);

        if (!$timestamp) {
            return $date; // Return original if conversion fails
        }

        // Get format from settings or use custom
        if ($custom_format) {
            $format = $custom_format;
        } else {
            $format = 'd-m-Y';

            // Add time if requested
            if ($include_time) {
                $format .= ' H:i';
            }
        }

        return date_i18n($format, $timestamp);
    }

    /**
     * Get a human-readable time ago string
     */
    public static function time_ago($datetime, $full = false)
    {
        $now = new DateTime;
        $ago = new DateTime($datetime);
        $diff = $now->diff($ago);

        $weeks = floor($diff->d / 7);
        $days = $diff->d - ($weeks * 7);

        $units = array(
            'y' => array('value' => $diff->y, 'label' => 'year'),
            'm' => array('value' => $diff->m, 'label' => 'month'),
            'w' => array('value' => $weeks, 'label' => 'week'),
            'd' => array('value' => $days, 'label' => 'day'),
            'h' => array('value' => $diff->h, 'label' => 'hour'),
            'i' => array('value' => $diff->i, 'label' => 'minute'),
            's' => array('value' => $diff->s, 'label' => 'second'),
        );

        $string = array();
        foreach ($units as $k => $unit) {
            if ($unit['value']) {
                $string[$k] = $unit['value'] . ' ' . __($unit['label'] . ($unit['value'] > 1 ? 's' : ''), 'olama-school');
            }
        }

        if (!$full)
            $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ' . __('ago', 'olama-school') : __('just now', 'olama-school');
    }

    /**
     * Sanitize date from input format to Y-m-d
     */
    public static function sanitize_date($date_str)
    {
        if (empty($date_str)) {
            return '';
        }

        // Try Y-m-d format first (from JavaScript data-raw attribute)
        $date = DateTime::createFromFormat('Y-m-d', $date_str);
        if ($date && $date->format('Y-m-d') === $date_str) {
            return $date_str; // Already in correct format for database
        }

        // Try d-m-Y format (from display format)
        $date = DateTime::createFromFormat('d-m-Y', $date_str);
        if ($date) {
            return $date->format('Y-m-d');
        }

        // Try m-d-Y format as fallback
        $date = DateTime::createFromFormat('m-d-Y', $date_str);
        if ($date) {
            return $date->format('Y-m-d');
        }

        // Return original if no format matches (might already be valid)
        return $date_str;
    }

    /**
     * Check if the current language is Arabic
     * 
     * @param string $text The English text
     * @return string Translated text if in Arabic mode, otherwise original
     */
    public static function translate($text)
    {
        if ($text === null) {
            return '';
        }
        $text = trim($text);
        if (!self::is_arabic()) {
            return $text;
        }

        static $map = array(
        'Olama School' => 'أكاديمية علماء المستقبل',
        'Dashboard' => 'لوحة القيادة',
        'Reports' => 'التقارير',
        'Weekly Plan Management' => 'إدارة الخطط الأسبوعية',
        'Academic Management' => 'الإدارة الأكاديمية',
        'Stationary' => 'القرطاسية',
        'Required Notebooks' => 'الدفاتر المطلوبة',
        'Required Stationary' => 'القرطاسية المطلوبة',
        'Class Teacher Notes' => 'ملاحظات المعلم',
        'Save Stationary' => 'حفظ القرطاسية',
        'Curriculum Management' => 'إدارة المناهج',
        'Exam Management' => 'إدارة الامتحانات',
        'Evaluation' => 'التقييم',
        'Evaluation Management' => 'إدارة التقييمات',
        'Evaluation Progress' => 'متابعة التقييمات',
        'Track evaluation completion by grade and section.' => 'تتبع إنجاز التقييم حسب الصف والشعبة.',
        'Today\'s Teaching Schedule' => 'جدول الحصص لهذا اليوم',
        'Please log in to view your schedule.' => 'يرجى تسجيل الدخول لعرض جدولك.',
        'This feature is only available for teachers.' => 'هذه الميزة متاحة للمعلمين فقط.',
        'No classes scheduled for today.' => 'لا توجد حصص مجدولة لهذا اليوم.',
        'My Weekly Shifts' => 'مناوباتي الأسبوعية',
        'Slot' => 'الفترة',
        'Location' => 'المكان',
        'No shifts assigned for this week.' => 'لا توجد مناوبات مسندة لهذا الأسبوع.',
        'Completion Ratio' => 'نسبة الإنجاز',
        'List Student' => 'عرض الطلاب',
        'Approve All Drafts' => 'اعتماد جميع المسودات',
        'No evaluation templates found for this selection.' => 'لم يتم العثور على نماذج تقييم لهذا الاختيار.',
        'Select a student to view evaluation details.' => 'اختر طالباً لعرض تفاصيل التقييم.',
        'Please select a grade and section to view progress.' => 'يرجى تحديد صف وشعبة لعرض التقدم.',
        'Are you sure you want to approve this evaluation? It will be published immediately.' => 'هل أنت متأكد أنك تريد اعتماد هذا التقييم؟ سيتم نشره فوراً.',
        'Are you sure you want to approve ALL draft evaluations for this template and section?' => 'هل أنت متأكد أنك تريد اعتماد جميع التقييمات المسودة لهذا النموذج والشعبة؟',
        'Student Evaluation' => 'تقييم الطلاب',
        'Religious Domain' => 'المجال الديني',
        'Cognitive Domain' => 'المجال المعرفي',
        'Linguistic Domain' => 'المجال اللغوي',
        'Social/Emotional' => 'المجال الانفعالي والاجتماعي',
        'Motor Skills' => 'المجال الحركي',
        'Mastered' => 'أتقن المهارة',
        'Partially Mastered' => 'أتقن جزءاً من المهارة',
        'Not Mastered' => 'لم يتقن المهارة',
        'Save Draft' => 'حفظ مسودة',
        'Publish Evaluation' => 'اعتماد ونشر النتيجة',
        'Add Note' => 'إضافة ملاحظة',
        'Select Student' => 'اختر الطالب',
        'Evaluation Form' => 'استمارة التقييم',
        'Student Evaluation Report' => 'تقرير تقييم الطالب',
        'Student Name' => 'اسم الطالب',
        'Class Teacher' => 'معلم الصف',
        'Supervisor' => 'المشرف التربوي',
        'Supervisor Feedback' => 'ملاحظات المشرف',
        'Parent Signature' => 'توقيع ولي الأمر',
        'Notes' => 'ملاحظات',
        'Add Domain' => 'إضافة مجال',
        'Add Category' => 'إضافة فئة',
        'Add Indicator' => 'إضافة مؤشر',
        'Domain Title' => 'عنوان المجال',
        'Category Title' => 'عنوان الفئة',
        'Indicator Text' => 'نص المؤشر',
        'Sort Order' => 'ترتيب العرض',
        'Fill the evaluation form for the selected student.' => 'املاً نموذج التقييم للطالب المختار.',
        'Manage and create evaluation structures for all school grades.' => 'إدارة وإنشاء هياكل التقييم لجميع الصفوف الدراسية.',
        'Create New Evaluation' => 'إنشاء تقييم جديد',
        'Create New Evaluation Template' => 'إنشاء نموذج تقييم جديد',
        'Evaluation Title (e.g., Progress Report Q1)' => 'عنوان التقييم (مثلاً: تقرير الأداء الربع الأول)',
        'Confirm & Create' => 'تأكيد وإنشاء',
        'No evaluations created yet for this grade.' => 'لم يتم إنشاء تقييمات لهذا الصف بعد.',
        'Choose a Student...' => 'اختر طالباً...',
        'Evaluated' => 'تم تقييمه',
        'In Progress' => 'قيد التقييم',
        '-- Select --' => '-- اختر --',
        'Family' => 'العائلة',
        'Families' => 'العائلات',
        'Family ID' => 'رقم العائلة',
        'Family Name' => 'اسم العائلة',
        'Mother Mobile' => 'جوال الأم',
        'Father Mobile' => 'جوال الأب',
        'Address Details' => 'تفاصيل العنوان',
        'Family ID (UID)' => 'رقم العائلة',
        'Add New Family' => 'إضافة عائلة جديدة',
        'Family Management' => 'إدارة العائلات',
        'Students / Enrollment' => 'الطلاب / التسجيل',
        '-- Select Family --' => '-- اختر العائلة --',
        'Edit Family Details' => 'تعديل بيانات العائلة',
        'Save Family' => 'حفظ العائلة',
        'Add Student' => 'إضافة طالب',
        'Student ID' => 'رقم الطالب',
        'DOB' => 'تاريخ الميلاد',
        'National ID' => 'الرقم الوطني',
        'Sex' => 'الجنس',
        'Male' => 'ذكر',
        'Female' => 'أنثى',
        'Family and students saved successfully.' => 'تم حفظ العائلة والطلاب بنجاح.',
        'Student Enrollment Registry' => 'سجل تسجيل الطلاب',
        'Export Families' => 'تصدير العائلات',
        'Import Families' => 'استيراد العائلات',
        'Import Students & Enroll' => 'استيراد الطلاب وتسجيلهم',
        'Start Import' => 'بدء الاستيراد',
        'Academic Year' => 'العام الدراسي',
        'Hall/Room' => 'القاعة/الغرفة',
        'Exam Subject' => 'مادة الاختبار',
        'Important Notes' => 'ملاحظات هامة',
        'Day' => 'اليوم',
        'Period' => 'الحصة',
        'No periods found' => 'لا توجد حصص',
        'School Stationery' => 'القرطاسية المدرسية',
        'Stationery list for each grade' => 'قائمة المستلزمات المدرسية لكل صف',
        'Required Notebooks' => 'الدفاتر المطلوبة',
        'Required Stationery' => 'القرطاسية المطلوبة',
        'Teacher Notes' => 'ملاحظات المعلم',
        'No stationary defined for this grade yet.' => 'لم يتم تحديد قرطاسية لهذا الصف بعد.',
        'Please bring all supplies on the first day of school' => 'يرجى إحضار جميع المستلزمات في اليوم الأول من الدراسة',
        'Import & Enroll' => 'استيراد وتسجيل',
        'Upload a CSV file to import family details.' => 'قم بتحميل ملف CSV لاستيراد تفاصيل العائلة.',
        'Upload a CSV file to import students and automatically enroll them.' => 'قم بتحميل ملف CSV لاستيراد الطلاب وتسجيلهم تلقائياً.',
        'Expected columns: Family ID, Family Name, Father Mobile, Mother Mobile, Address' => 'الأعمدة المتوقعة: رقم العائلة، اسم العائلة، جوال الأب، جوال الأم، العنوان',
        'Expected columns: Name, ID Number, Family ID, Year, Grade, Section' => 'الأعمدة المتوقعة: الاسم، رقم الهوية، رقم العائلة، السنة، الصف، الشعبة',
        'Add details...' => 'إضافة تفاصيل...',
        'Changes saved as draft.' => 'تم حفظ التغييرات كمسودة.',
        'Saving...' => 'جاري الحفظ...',
        'Draft saved locally.' => 'تم حفظ المسودة.',
        'Evaluation Title' => 'عنوان التقييم',
        'Created Date' => 'تاريخ الإنشاء',
        'Manage Structure' => 'إدارة الهيكل',
        'Delete this evaluation?' => 'حذف هذا التقييم؟',
        'Managing:' => 'إدارة:',
        'Add domains and indicators for this report.' => 'إضافة مجالات ومؤشرات لهذا التقرير.',
        'Back to List' => 'العودة للقائمة',
        'No indicators defined for this evaluation yet.' => 'لم يتم تحديد مؤشرات لهذا التقييم بعد.',
        'Are you sure you want to delete this domain and all its contents?' => 'هل أنت متأكد من حذف هذا المجال وجميع محتوياته؟',
        'Delete this category?' => 'حذف هذه الفئة؟',
        'Delete this indicator?' => 'حذف هذا المؤشر؟',
        'No evaluation structure defined for this template yet.' => 'لم يتم تحديد هيكل تقييم لهذا النموذج بعد.',
        'Select Section' => 'اختر الشعبة',
        'Actions' => 'الإجراءات',
        'Save' => 'حفظ',
        'Add' => 'إضافة',
        'Print Report' => 'طباعة التقرير',
        'ev_success' => 'تمت العملية بنجاح.',
        'ev_eval_saved' => 'تم حفظ التقييم بنجاح.',
        'Users & Permissions' => 'المستخدمون والصلاحيات',
        'Settings' => 'الإعدادات',
        'Plugin Settings' => 'إعدادات الإضافة',
        'General Settings' => 'الإعدادات العامة',
        'Shortcode Generator' => 'مولد الكود القصير',
        'School Name (Arabic)' => 'اسم المدرسة (بالعربية)',
        'School Name (English)' => 'اسم المدرسة (بالإنجليزية)',
        'School Start Day' => 'بداية الأسبوع الدراسي',
        'School Last Day' => 'نهاية الأسبوع الدراسي',
        'Default Language' => 'اللغة الافتراضية',
        'Date Format' => 'تنسيق التاريخ',
        'Day-Month-Year (16-01-2026)' => 'يوم-شهر-سنة (16-01-2026)',
        'Month-Day-Year (01-16-2026)' => 'شهر-يوم-سنة (01-16-2026)',
        'Year-Month-Day (2026-01-16)' => 'سنة-شهر-يوم (2026-01-16)',
        'Choose how dates should be displayed throughout the plugin.' => 'اختر كيفية عرض التواريخ في جميع أنحاء الإضافة.',
        'Arabic' => 'العربية',
        'English' => 'الإنجليزية',
        'Save Changes' => 'حفظ التغييرات',
        'Grades' => 'الصفوف',
        'Sections' => 'الشعب',
        'Teachers' => 'المعلمون',
        'Students' => 'الطلاب',
        'Permissions' => 'الصلاحيات',
        'Plans Overview' => 'نظرة عامة على الخطط',
        'Recent Plans' => 'الخطط الأخيرة',
        'Plan Creation' => 'إنشاء خطة',
        'Plan List' => 'قائمة الخطط',
        'Plan Comparison' => 'مقارنة الخطط',
        'Weekly Schedule' => 'الجدول الأسبوعي',
        'Data Management' => 'إدارة البيانات',
        'Plan Load' => 'حدود الخطة',
        'Curriculum Coverage' => 'تغطية المنهاج',
        'View Weekly Plans' => 'عرض الخطط الأسبوعية',
        'Create Own Plans' => 'إنشاء خطط خاصة',
        'Edit Own Plans' => 'تعديل خطط خاصة',
        'Approve Weekly Plans' => 'اعتماد الخطط الأسبوعية',
        'Manage Academic Structure' => 'إدارة الهيكل الأكاديمي',
        'Manage Curriculum' => 'إدارة المناهج',
        'View Reports' => 'عرض التقارير',
        'Import/Export Data' => 'استيراد/تصدير البيانات',
        'View Logs' => 'عرض السجلات',
        'Manage Settings & Permissions' => 'إدارة الإعدادات والصلاحيات',
        'Save All Permissions' => 'حفظ جميع الصلاحيات',
        'Capability' => 'الصلاحية',
        'Administrator' => 'مدير النظام',
        'Supervisor' => 'مشرف',
        'Teacher' => 'معلم',
        'Assistant' => 'مساعد',
        'Activity Logs' => 'سجلات النشاط',
        'Add Student' => 'إضافة طالب',
        'Name' => 'الاسم',
        'ID Number' => 'رقم الهوية',
        'Grade' => 'الصف',
        'Section' => 'الشعبة',
        'Select Grade' => 'اختر الصف',
        'Select Section' => 'اختر الشعبة',
        'No students found.' => 'لم يتم العثور على طلاب.',
        'Teacher Information' => 'معلومات المعلم',
        'Employee ID' => 'الرقم الوظيفي',
        'Phone' => 'الهاتف',
        'Edit' => 'تعديل',
        'Update Teacher' => 'تحديث المعلم',
        'Cancel' => 'إلغاء',
        'Permissions updated successfully.' => 'تم تحديث الصلاحيات بنجاح.',
        'On-time' => 'في الوقت المحدد',
        'Delayed by %d days' => 'متأخر بـ %d أيام',
        'Bypass by %d days' => 'متقدم بـ %d أيام',
        'Sunday' => 'الأحد',
        'Monday' => 'الاثنين',
        'Tuesday' => 'الثلاثاء',
        'Wednesday' => 'الأربعاء',
        'Thursday' => 'الخميس',
        'Friday' => 'الجمعة',
        'Saturday' => 'السبت',
        'Date' => 'التاريخ',
        'Details' => 'التفاصيل',
        'Status' => 'الحالة',
        'Draft' => 'مسودة',
        'Submitted' => 'تم التسليم',
        'Approved' => 'تم الاعتماد',
        'Completed' => 'مكتمل',
        'Planned' => 'مجدول',
        'No recent plans found.' => 'لا توجد خطط حديثة.',
        'Week' => 'أسبوع',
        '%s %d' => '%s %d',
        '%s\'s Plan' => 'خطة يوم %s',
        'Month' => 'الشهر',
        'Current Status' => 'الحالة الحالية',
        'Revert to Draft' => 'إرجاع إلى مسودة',
        'Subject' => 'المادة',
        '-- Select Subject --' => '-- اختر المادة --',
        'Unit' => 'الوحدة',
        '-- Select Unit --' => '-- اختر الوحدة --',
        'Lesson' => 'الدرس',
        '-- Select Lesson --' => '-- اختر الدرس --',
        'Questions to Cover' => 'الأسئلة المطلوب تغطيتها',
        'Homework' => 'الواجب',
        'Homework (SB)' => 'الواجب (كتاب الطالب)',
        'Homework (EB)' => 'الواجب (كتاب التمارين)',
        'Homework (NB)' => 'الواجب (الدفتر)',
        'Homework (WS)' => 'الواجب (الدوسية)',
        'Homework (Student Book)' => 'الواجب (كتاب الطالب)',
        'Homework (Exercise Book)' => 'الواجب (كتاب التمارين)',
        'Homework (Notebook)' => 'الواجب (الدفتر)',
        'Homework (Worksheet)' => 'الواجب (الدوسية)',
        'Teacher Notes' => 'ملاحظات المعلم',
        'Teacher\'s Notes' => 'ملاحظات المعلم',
        'Additional notes...' => 'ملاحظات إضافية...',
        'Save as Draft' => 'حفظ كمسودة',
        'Saved Plans for Today' => 'الخطط المحفوظة لهذا اليوم',
        'No plans saved for this day.' => 'لا توجد خطط محفوظة لهذا اليوم.',
        'Delete' => 'حذف',
        'No sections found' => 'لم يتم العثور على شعب',
        'Plan Details' => 'تفاصيل الخطة',
        'Topic' => 'الموضوع',
        'Click on a plan to see details.' => 'انقر على الخطة لعرض التفاصيل',
        'Are you sure you want to approve (publish) all plans for this week and section?' => 'هل أنت متأكد من رغبتك في اعتماد (نشر) جميع خطط هذا الأسبوع لهذه الشعبة؟',
        'All plans have been approved successfully.' => 'تم اعتماد جميع الخطط بنجاح',
        'No plans' => 'لا توجد خطط',
        'Week Start' => 'بداية الأسبوع',
        'Approve All' => 'اعتماد الكل',
        'Loading...' => 'جاري التحميل...',
        'W%d' => 'أسبوع %d',
        'SB:' => 'كتاب الطالب:',
        'EB:' => 'كتاب التمارين:',
        'NB:' => 'الدفتر:',
        'WS:' => 'الدوسية:',
        // Weekly Plan Form Strings
        'Select Unit' => 'اختر الوحدة',
        'Select Lesson' => 'اختر الدرس',
        'No units found.' => 'لم يتم العثور على وحدات.',
        'No lessons found.' => 'لم يتم العثور على دروس.',
        'No questions found for this lesson.' => 'لم يتم العثور على أسئلة لهذا الدرس.',
        'Update Plan' => 'تحديث الخطة',
        'Published' => 'منشور',
        'Submit for Review' => 'إرسال للمتابعة',
        'Approve' => 'اعتماد',
        'Request Edits' => 'طلب تعديلات',
        'Sending...' => 'جاري الإرسال...',
        'Approving...' => 'جاري الاعتماد...',
        'Please enter some feedback.' => 'الرجاء إدخال ملاحظات.',
        'Error occurred' => 'حدث خطأ',
        'Communication error' => 'خطأ في الاتصال',
        'Error loading units' => 'خطأ في تحميل الوحدات',
        'Error loading lessons' => 'خطأ في تحميل الدروس',
        'Loading questions...' => 'جاري تحميل الأسئلة...',
        'Error loading questions' => 'خطأ في تحميل الأسئلة',
        'Are you sure you want to delete this plan?' => 'هل أنت متأكد من حذف هذه الخطة؟',
        'An error occurred while deleting the plan.' => 'حدث خطأ أثناء حذف الخطة.',
        'Failed to delete plan.' => 'فشل حذف الخطة.',
        'No plans saved for today yet.' => 'لم يتم حفظ خطط لهذا اليوم بعد.',
        'Please enter at least one homework (Student Book, Workbook, Notebook, or Booklet/Worksheet).' => 'يرجى إدخال واجب واحد على الأقل (كتاب الطالب، أو كتاب التمارين، أو الدفتر، أو الدوسية).',
        'واجبات مدرسية' => 'واجبات مدرسية',
        'واجبات' => 'واجبات',
        'متابعات' => 'متابعات',
        'لا واجبات' => 'لا واجبات',
        // Plan Type Translations
        'Plan Type' => 'نوع الخطة',
        'Homework Plan' => 'خطة الواجبات',
        'Review Plan' => 'خطة المتابعة',
        'Review' => 'متابعة',
        'Review the following lesson' => 'متابعة الدرس التالي',
        'Page numbers or details...' => 'أرقام الصفحات أو التفاصيل...',
        'Notebook instructions...' => 'تعليمات الدفتر...',
        'Worksheet details...' => 'تفاصيل الدوسية...',
        'Semester' => 'الفصل الدراسي',
        'Academic Year' => 'السنة الأكاديمية',
        'Plan Review' => 'متابعة الخطة',
        'Feedback' => 'الملاحظات',
        'Review Submitted Plans' => 'متابعة الخطط المسلمة',
        'Plans awaiting review' => 'خطط تنتظر المتابعة',
        'No plans awaiting review.' => 'لا توجد خطط تنتظر المتابعة.',
        // Stationary Shortcode Strings
        'Stationary' => 'القرطاسية',
        'القرطاسية المدرسية' => 'القرطاسية المدرسية',
        'قائمة المستلزمات المدرسية لكل صف' => 'قائمة المستلزمات المدرسية لكل صف',
        'الدفاتر المطلوبة' => 'الدفاتر المطلوبة',
        'القرطاسية المطلوبة' => 'القرطاسية المطلوبة',
        'لم يتم تحديد قرطاسية لهذا الصف بعد.' => 'لم يتم تحديد قرطاسية لهذا الصف بعد.',
        'يرجى إحضار جميع المستلزمات في اليوم الأول من الدراسة' => 'يرجى إحضار جميع المستلزمات في اليوم الأول من الدراسة',
        'No academic year specified or active.' => 'لم يتم تحديد سنة أكاديمية أو لا توجد سنة نشطة.',
        'No stationary defined for this academic year.' => 'لم يتم تحديد قرطاسية لهذه السنة الأكاديمية.',
        'Required Notebooks' => 'الدفاتر المطلوبة',
        'Required Stationary' => 'القرطاسية المطلوبة',
        // Academic Management Strings
        'Academic Calendar' => 'التقويم الأكاديمي',
        'Grades & Sections' => 'الصفوف والشعب',
        'Subjects' => 'المواد الدراسية',
        'Assign Teachers to Subjects' => 'إسناد المعلمين للمواد',
        'Academic Years' => 'السنوات الأكاديمية',
        'ID' => 'المعرف',
        'Year Name' => 'اسم السنة',
        'Start Date' => 'تاريخ البدء',
        'End Date' => 'تاريخ الانتهاء',
        'Actions' => 'الإجراءات',
        'Manage Semesters' => 'إدارة الفصول الدراسية',
        'Add Academic Year' => 'إضافة سنة أكاديمية',
        'Set as Active' => 'تعيين كنشط',
        'Add Year' => 'إضافة سنة',
        'Active' => 'نشط',
        'Inactive' => 'غير نشط',
        'Delete Year and its Semesters?' => 'حذف السنة وفصولها الدراسية؟',
        'Activate' => 'تنشيط',
        'No academic years found.' => 'لم يتم العثور على سنوات أكاديمية.',
        'Semesters for %s' => 'فصول سنة %s',
        'Add Semester' => 'إضافة فصل دراسي',
        'First Semester' => 'الفصل الأول',
        'Second Semester' => 'الفصل الثاني',
        '1st Semester' => 'الفصل الأول',
        '2nd Semester' => 'الفصل الثاني',
        'Summer Semester' => 'فصل صيفي',
        'Semester Name' => 'اسم الفصل الدراسي',
        'No semesters defined for this year.' => 'لم يتم تحديد فصول لهذه السنة.',
        'Events for %s' => 'أحداث سنة %s',
        'Add Event' => 'إضافة حدث',
        'Description' => 'الوصف',
        'Event Description' => 'وصف الحدث',
        'Update' => 'تحديث',
        'Delete Event?' => 'حذف الحدث؟',
        'No events defined for this year.' => 'لم يتم تحديد أحداث لهذه السنة.',
        'Export Grades & Sections (CSV)' => 'تصدير الصفوف والشعب (CSV)',
        'Import Grades & Sections' => 'استيراد الصفوف والشعب',
        'Existing Grades' => 'الصفوف الحالية',
        'Level' => 'المستوى',
        'Periods' => 'الحصص',
        'Manage Sections' => 'إدارة الشعب',
        'Delete Grade?' => 'حذف الصف؟',
        'Sections for %s' => 'شعب %s',
        'Add Section' => 'إضافة شعبة',
        'Add New Section' => 'إضافة شعبة جديدة',
        'Room Number' => 'رقم الغرفة',
        'Save Section' => 'حفظ الشعبة',
        'Edit Section' => 'تعديل الشعبة',
        'Update Section' => 'تحديث الشعبة',
        'Room' => 'الغرفة',
        'No sections defined for this grade.' => 'لم يتم تحديد شعب لهذا الصف.',
        'Material Icon' => 'أيقونة (Material Icon)',
        'KG الروضة' => 'KG الروضة',
        'Photo Session' => 'جلسة التصوير',
        'Graduation Session' => 'حفل التخرج',
        'Attended Session' => 'حضر الجلسة',
        'Photo Fees Collected' => 'الرسوم المحصلة للصور',
        'Received Photo' => 'استلم الصور',
        'Participating' => 'مشارك',
        'Fees' => 'الرسوم',
        'Custom Fees' => 'رسوم إضافية',
        'Apply to All' => 'تطبيق على الكل',
        'Apply Fees' => 'تطبيق الرسوم',
        'Apply Custom' => 'تطبيق الإضافي',
        'Toggle All Participating' => 'تحديد الكل مشارك',
        'Toggle All Attended' => 'تحديد الكل حاضر',
        'Default Fees' => 'الرسوم الافتراضية',
        'Default Custom Fees' => 'الرسوم الإضافية الافتراضية',
        'Please select a grade and section to list students.' => 'يرجى تحديد الصف والشعبة لعرض الطلاب.',
        'Bulk Actions & Defaults' => 'العمليات الجماعية والافتراضية',
        'Save All Changes' => 'حفظ جميع التغييرات',
        'No students found in this section.' => 'لا يوجد طلاب في هذه الشعبة.',
        'Last Updated' => 'آخر تحديث',
        'Edit Grade' => 'تعديل الصف',
        'Grade Level' => 'مستوى الصف',
        'Periods per Day' => 'عدد الحصص في اليوم',
        'Update Grade' => 'تحديث الصف',
        'Grade not found.' => 'الصف غير موجود.',
        'Add New Grade' => 'إضافة صف جديد',
        'Periods/Day' => 'حصص/يوم',
        'Export Subjects (CSV)' => 'تصدير المواد (CSV)',
        'Import Subjects' => 'استيراد المواد',
        'Edit Subject' => 'تعديل المادة',
        'Subject Name' => 'اسم المادة',
        'Subject Code' => 'كود المادة',
        'Color Code' => 'كود اللون',
        'Update Subject' => 'تحديث المادة',
        'Add Subject' => 'إضافة مادة',
        'Existing Subjects' => 'المواد الحالية',
        'Code' => 'الكود',
        'Color' => 'اللون',
        'No subjects found. Add your first subject using the form on the left.' => 'لم يتم العثور على مواد. أضف مادتك الأولى باستخدام النموذج على اليسار.',
        'Delete Subject?' => 'حذف المادة؟',
        'Manage subject assignments by selecting a teacher, then narrowing down by grade and section.' => 'إدارة إسناد المعلمين للمواد من خلال تحديد المعلم، ثم الصف والشعبة.',
        '1. Teachers' => '1. المعلمون',
        '2. Grades' => '2. الصفوف',
        '3. Sections' => '3. الشعب',
        '4. Subjects' => '4. المواد',
        'Select Grade first' => 'اختر الصف أولاً',
        'Select Section first' => 'اختر الشعبة أولاً',
        'Employee ID: ' => 'الرقم الوظيفي: ',
        'Unit saved successfully' => 'تم حفظ الوحدة بنجاح',
        'Lesson saved successfully' => 'تم حفظ الدرس بنجاح',
        'Question saved successfully' => 'تم حفظ السؤال بنجاح',
        'Timeline dates saved successfully.' => 'تم حفظ تواريخ الخط الزمني بنجاح.',
        'Select Unit' => 'اختر الوحدة',
        'No units found.' => 'لم يتم العثور على وحدات.',
        'Select Lesson' => 'اختر الدرس',
        'No lessons found.' => 'لم يتم العثور على دروس.',
        'No questions found for this lesson.' => 'لم يتم العثور على أسئلة لهذا الدرس.',
        'Published' => 'منشور',
        'Update Plan' => 'تحديث الخطة',
        'Select Subject' => 'اختر المادة',
        'No units found for this subject.' => 'لم يتم العثور على وحدات لهذه المادة.',
        'No lessons found for this unit.' => 'لم يتم العثور على دروس لهذه الوحدة.',
        'Are you sure you want to delete this item?' => 'هل أنت متأكد من رغبتك في حذف هذا العنصر؟',
        'An error occurred.' => 'حدث خطأ ما.',
        'Start date cannot be after end date.' => 'تاريخ البدء لا يمكن أن يكون بعد تاريخ الانتهاء.',
        'Dates must be within the semester range.' => 'التواريخ يجب أن تكون ضمن نطاق الفصل الدراسي.',
        'Unit dates cannot overlap.' => 'تواريخ الوحدات لا يمكن أن تتداخل.',
        'Lesson dates must be within unit dates.' => 'تواريخ الدروس يجب أن تكون ضمن تواريخ الوحدة.',
        'Are you sure you want to clear all dates? This will remove all start and end dates for the current view.' => 'هل أنت متأكد من مسح جميع التواريخ؟ سيؤدي ذلك إلى إزالة جميع تواريخ البدء والانتهاء للعرض الحالي.',
        'Please select a teacher first.' => 'يرجى اختيار معلم أولاً.',
        'Please select a grade first.' => 'يرجى اختيار صف أولاً.',
        'Please select a section first.' => 'يرجى اختيار شعبة أولاً.',
        'Academic Year activated.' => 'تم تفعيل السنة الأكاديمية.',
        'Academic Year deleted.' => 'تم حذف السنة الأكاديمية.',
        'Academic Year added successfully.' => 'تم إضافة السنة الأكاديمية بنجاح.',
        'Semester added successfully.' => 'تم إضافة الفصل الدراسي بنجاح.',
        'Semester deleted.' => 'تم حذف الفصل الدراسي.',
        'Event added successfully.' => 'تم إضافة الحدث بنجاح.',
        'Event updated successfully.' => 'تم تحديث الحدث بنجاح.',
        'Event deleted.' => 'تم حذف الحدث.',
        'Grade added successfully.' => 'تم إضافة الصف بنجاح.',
        'Grade updated successfully.' => 'تم تحديث الصف بنجاح.',
        'Grade deleted.' => 'تم حذف الصف.',
        'Section added successfully.' => 'تم إضافة الشعبة بنجاح.',
        'Section updated successfully.' => 'تم تحديث الشعبة بنجاح.',
        'Section deleted.' => 'تم حذف الشعبة.',
        'Subject added successfully.' => 'تم إضافة المادة بنجاح.',
        'Subject updated successfully.' => 'تم تحديث المادة بنجاح.',
        'Subject deleted.' => 'تم حذف المادة.',
        'Student added successfully.' => 'تم إضافة الطالب بنجاح.',
        'Teacher information updated.' => 'تم تحديث معلومات المعلم.',
        'Semester activated.' => 'تم تفعيل الفصل الدراسي.',
        'Academic Year activated.' => 'تم تفعيل السنة الأكاديمية.',
        'Semester' => 'الفصل الدراسي',
        'Family Gateway Settings' => 'إعدادات بوابة العائلة',
        'Evaluation Page URL' => 'رابط صفحة تقرير التقييم',
        'Exams Page URL' => 'رابط صفحة الاختبارات',
        'Weekly Plan Page URL' => 'رابط صفحة الخطط الأسبوعية',
        'Evaluation Report' => 'تقرير التقييم',
        'Online Exams' => 'الاختبارات الإلكترونية',
        'Weekly Plan' => 'الخطة الأسبوعية',
        'Exam Schedule' => 'جدول الاختبارات',
        'Family Performance' => 'بيانات التفوق والتقييم',
        'Family Gateway' => 'بوابة العائلة',
        'Gateway Services Management' => 'إدارة خدمات بوابة العائلة',
        'Add New Service' => 'إضافة خدمة جديدة',
        'Service Title (Arabic)' => 'عنوان الخدمة (بالعربية)',
        'Service Title (English)' => 'عنوان الخدمة (بالإنجليزية)',
        'Page URL' => 'رابط الصفحة',
        'Material Icon' => 'أيقونة (Material Icon)',
        'Services' => 'الخدمات',
        'No matching student found for this family.' => 'لم يتم العثور على الطالب المطلوب لهذا الحساب.',
        'No students found for this family.' => 'لم يتم العثور على طلاب لهذا الحساب.',
        'جدول اليوم' => 'جدول اليوم',
        'Are you sure you want to remove this service?' => 'هل أنت متأكد من حذف هذه الخدمة؟',
        'Family Gateway settings updated.' => 'تم تحديث إعدادات بوابة العائلة.',
        'View' => 'عرض',
        'Dashboard labels and visibility' => 'عناوين البوابة والظهور',
        'Gateway Label' => 'عنوان الخدمة بالبوابة',
        'Gateway Page' => 'صفحة البوابة',
        'Service' => 'الخدمة',
        'Order' => 'الترتيب',
        'Shortcode' => 'الكود القصير (Shortcode)',
        'No services found. Add your first service above.' => 'لا توجد خدمات مضافة. أضف أول خدمة أعلاه.',
        'Select a student to view their reports' => 'اختر الطالب لعرض تقاريره',
        'Choose a child to access their academic profile and school services.' => 'يرجى اختيار أحد الطلاب للوصول إلى ملفه الأكاديمي وخدمات المدرسة.',
        'Active Student:' => 'الطالب الحالي:',
        'Switch' => 'تبديل',
        'Back to Student List' => 'العودة لقائمة الطلاب',
        'Student Identity' => 'هوية الطالب',
        'Grade & Section' => 'الصف والشعبة',
        'Student Age' => 'عمر الطالب',
        'Academic Progress' => 'التقدم الأكاديمي',
        'Average Mastery' => 'متوسط الإتقان',
        'Individual Student Reports' => 'تقارير الطالب الفردية',
        'Please select a student from the family dashboard to view this report.' => 'يرجى اختيار طالب من بوابة العائلة لعرض هذا التقرير.',
        'Plan Load Management' => 'إدارة حدود الخطط الأسبوعية',
        '-- Select Semester --' => '-- اختر الفصل الدراسي --',
        '-- Select Grade --' => '-- اختر الصف --',
        'Export Curriculum CSV' => 'تصدير المنهاج (CSV)',
        'Import Curriculum CSV' => 'استيراد المنهاج (CSV)',
        'Select Semester, Grade, and Subject to enable Export/Import.' => 'اختر الفصل الدراسي، الصف، والمادة لتفعيل الاستيراد والتصدير.',
        '1. Units' => '1. الوحدات',
        '+ Add Unit' => '+ إضافة وحدة',
        'Learning Objectives' => 'أهداف التعلم',
        'Save Unit' => 'حفظ الوحدة',
        'Select Subject to see units.' => 'اختر المادة لعرض الوحدات.',
        '2. Lessons' => '2. الدروس',
        '+ Add Lesson' => '+ إضافة درس',
        'Video URL' => 'رابط الفيديو',
        'Number of Periods' => 'عدد الحصص',
        'Save Lesson' => 'حفظ الدرس',
        'Select Unit to see lessons.' => 'اختر الوحدة لعرض الدروس.',
        '3. Question Bank' => '3. بنك الأسئلة',
        '+ Add Question' => '+ إضافة سؤال',
        'Suggested Answer' => 'الإجابة المقترحة',
        'Save Question' => 'حفظ السؤال',
        'Select Lesson to see questions.' => 'اختر الدرس لعرض الأسئلة.',
        'Select Semester' => 'اختر الفصل الدراسي',
        'Choose Grade...' => 'اختر الصف...',
        'Select Grade first...' => 'اختر الصف أولاً...',
        'All Grades' => 'جميع الصفوف',
        'All Sections' => 'جميع الشعب',
        'Semester Total' => 'مجموع الفصل',
        'Total Visits (Selected Week)' => 'زيارات الأسبوع المختار',
        'Teacher' => 'المعلم',
        'Performance by Teacher' => 'الأداء حسب المعلم',
        'No teacher data available.' => 'لا توجد بيانات متاحة للمعلمين.',
        'Visits' => 'الزيارات',
        'Performance by Grade' => 'الأداء حسب الصف الدراسي',
        'Average Score' => 'متوسط التقييم',
        'Cannot delete this evaluation because it already has student records.' => 'لا يمكن حذف هذا التقييم لوجود سجلات تقييم مرتبطة به بالفعل.',
        'Load Timeline' => 'تحميل الخط الزمني',
        'Clear All Dates' => 'مسح جميع التواريخ',
        'Save All Dates' => 'حفظ جميع التواريخ',
        'Timeline' => 'الخط الزمني',
        'Curriculum' => 'المنهج',
        'Saved Schedules' => 'الجداول المحفوظة',
        'Print Schedule' => 'طباعة الجدول',
        'Save Master Schedule' => 'حفظ الجدول العام',
        'Period' => 'الحصة',
        'Scheduled' => 'مجدول',
        'Delete this entire schedule?' => 'هل أنت متأكد من حذف هذا الجدول بالكامل؟',
        'Master schedule saved successfully.' => 'تم حفظ الجدول العام بنجاح.',
        'No schedules defined yet. Use the filters below to create one.' => 'لا توجد جداول محددة بعد. استخدم الفلاتر أدناه لإنشاء جدول.',
        'No sections' => 'لا توجد شعب',
        'Day' => 'اليوم',
        '1 - First' => '1 - الأولى',
        '2 - Second' => '2 - الثانية',
        '3 - Third' => '3 - الثالثة',
        '4 - Fourth' => '4 - الرابعة',
        '5 - Fifth' => '5 - الخامسة',
        '6 - Sixth' => '6 - السادسة',
        '7 - Seventh' => '7 - السابعة',
        '8 - Eighth' => '8 - الثامنة',
        'Homework Curriculum Coverage' => 'تغطية المنهاج الدراسي',
        'Track how much of the curriculum is covered by weekly plans and monitor performance trends.' => 'تتبع مدى تغطية المناهج الدراسية من خلال الخطط الأسبوعية ومراقبة اتجاهات الأداء.',
        'Section:' => 'الشعبة:',
        'Semester:' => 'الفصل الدراسي:',
        'Please select a grade from the sidebar to view coverage analysis.' => 'يرجى اختيار صف من القائمة الجانبية لعرض تحليل التغطية.',
        'Coverage Report: %s' => 'تقرير التغطية: %s',
        '%d / %d Lessons Covered' => 'تم تغطية %d / %d درس',
        'On-time Plans' => 'خطط في الوقت المحدد',
        'Delayed Plans' => 'خطط متأخرة',
        'Bypass Plans' => 'خطط متجاوزة',
        'No subjects found for this grade.' => 'لم يتم العثور على مواد لهذا الصف.',
        'Bypass' => 'متجاوز',
        'Delayed' => 'متأخر',
        'On-time' => 'في الوقت المحدد',
        'Cleaning' => 'النظافة',
        'Toilet Cleaning Follow-up' => 'متابعة نظافة الحمامات',
        'Floor' => 'الطابق',
        'Ground Floor' => 'الطابق الأرضي',
        'First Floor' => 'الطابق الأول',
        'Second Floor' => 'الطابق الثاني',
        'Third Floor' => 'الطابق الثالث',
        'Cleaner Name' => 'اسم عامل/عاملة النظافة',
        'Cleaning Sinks' => 'تنظيف المغاسل',
        'Cleaning Toilets' => 'تنظيف الحمامات (المراحيض)',
        'Mopping/Washing Floor' => 'تلييف/غسيل الأرض',
        'Refilling Soap' => 'تعبئة الصابون',
        'Refilling Tissues' => 'تعبئة المناديل',
        'Time' => 'الوقت',
        'Done' => 'تم',
        'Not Done' => 'لم يتم',
        'Staff/Signature' => 'الموظف/التوقيع',
        'Cleaning log saved successfully.' => 'تم حفظ سجل النظافة بنجاح.',
        'Select Staff' => 'اختيار الموظف',
        'Save Cleaning Log' => 'حفظ سجل النظافة',
        'Item' => 'البند',
        'Configuration' => 'الإعدادات',
        'Cleaning Configuration' => 'إعدادات وحدة النظافة',
        'Cleaning Items' => 'بنود النظافة',
        'Manage Floors' => 'إدارة الطوابق',
        'Manage Cleaners' => 'إدارة عمال النظافة',
        'Manage Time Slots' => 'إدارة الفترات الزمنية',
        'Auto-assigned Cleaner' => 'عامل النظافة الموزع آلياً',
        'Floor Selection' => 'اختيار الطابق',
        'Checkup Time Slot' => 'فترة التفتيش',
        'Assigned Cleaner' => 'العامل المسؤول',
        'Not Editable' => 'غير قابل للتعديل',
        'Add New Item' => 'إضافة بند جديد',
        'Add New Floor' => 'إضافة طابق جديد',
        'Add New Cleaner' => 'إضافة عامل جديد',
        'Add New Slot' => 'إضافة فترة تفتيش',
        'Assign Cleaner to Floor' => 'تعيين عامل للطابق',
        'Setup floors first' => 'يرجى إعداد الطوابق أولاً',
        'Setup slots first' => 'يرجى إعداد فترات التفتيش أولاً',
        'No cleaning items defined in setup.' => 'لا توجد بنود نظافة معرفة في الإعدادات.',
        'Personnel name' => 'الاسم',
        'Add' => 'إضافة',
        'Back to Logs' => 'العودة للسجلات',
        'Date:' => 'التاريخ:',
        'Item Name' => 'اسم البند',
        'Slot Time' => 'وقت الفترة',
        'Floor Name' => 'اسم الطابق',
        'Actions' => 'الإجراءات',
        'Edit' => 'تعديل',
        'Delete' => 'حذف',
        'Status' => 'الحالة',
        'Yesterday' => 'يوم أمس',
        'Today' => 'اليوم',
        'Toilet Cleaning Monitoring' => 'متابعة نظافة الحمامات',
        'Status by Floor' => 'الحالة حسب الطوابق',
        'Supervisor Status' => 'حالة المشرفين',
        'Total Cleaning Tasks' => 'إجمالي مهام النظافة',
        'In Progress' => 'قيد التنفيذ',
        'No supervisors assigned' => 'لا يوجد مشرفين معينين',
        'Responsible Supervisor' => 'المشرف المسؤول',
        'Assign a cleaner to each floor for automatic selection during checkup.' => 'تعيين عامل لكل طابق للاختيار التلقائي أثناء التفتيش.',
        'Assign' => 'تعيين',
        '%d of %d slots checked' => 'تم فحص %d من أصل %d فترة',
        'Configure Cleaning Settings' => 'إعدادات وحدة النظافة',
        'Your Daily Progress' => 'تقدمك اليومي',
        'Ontime' => 'في الوقت المحدد',
        'Delay' => 'تأخير',
        'Entry Status:' => 'حالة التسجيل:',
        'mins delay' => 'دقائق تأخير',
        'Visited' => 'تمت الزيارة',
        'Pending' => 'قيد الانتظار',
        'Missing Tasks' => 'المهام الناقصة',
        'Req' => 'المطلوب',
        'Done' => 'المنجز',
        'Ratio' => 'النسبة',
        'Cleaning monitoring and follow-up across the school.' => 'مراقبة ومتابعة النظافة في أنحاء المدرسة.',
        'Please complete the setup to start using the cleaning module.' => 'يرجى إكمال الإعدادات للبدء باستخدام وحدة النظافة.',
        'Yes' => 'نعم',
        'No' => 'لا',
        'Time Slot' => 'فترة التفتيش',
        'Not Assigned' => 'غير محدد',
        'Exam Schedule Page URL' => 'رابط صفحة جدول الاختبارات',
        'Family Gateway Settings' => 'إعدادات بوابة العائلة',
        'Evaluation Page URL' => 'رابط صفحة تقرير التقييم',
        'Exams Page URL' => 'رابط صفحة الاختبارات',
        'Weekly Plan Page URL' => 'رابط صفحة الخطط الأسبوعية',
        'Evaluation Report' => 'تقرير التقييم',
        'Online Exams' => 'الاختبارات الإلكترونية',
        'Weekly Plan' => 'الخطة الأسبوعية',
        'Exam Schedule' => 'جدول الاختبارات',
        'Family Performance' => 'بيانات التفوق والتقييم',
        'Family Gateway' => 'بوابة العائلة',
        'Gateway Services Management' => 'إدارة خدمات بوابة العائلة',
        'Add New Service' => 'إضافة خدمة جديدة',
        'Service Title (Arabic)' => 'عنوان الخدمة (بالعربية)',
        'Service Title (English)' => 'عنوان الخدمة (بالإنجليزية)',
        'Page URL' => 'رابط الصفحة',
        'Material Icon' => 'أيقونة (Material Icon)',
        'Services' => 'الخدمات',
        'No matching student found for this family.' => 'لم يتم العثور على الطالب المطلوب لهذا الحساب.',
        'No students found for this family.' => 'لم يتم العثور على طلاب لهذا الحساب.',
        'جدول اليوم' => 'جدول اليوم',
        'Are you sure you want to remove this service?' => 'هل أنت متأكد من حذف هذه الخدمة؟',
        'Plan Load Management' => 'إدارة حدود الخطط الأسبوعية',
        'Manage the maximum number of plans allowed per week. Define limits at the grade level or for specific subjects.' => 'إدارة الحد الأقصى لعدد الخطط المسموح بها في الأسبوع. تحديد الحدود على مستوى الصف أو لمواد محددة.',
        'Grades & Sections Limits' => 'حدود الصفوف والشعب',
        'Grade Name' => 'اسم الصف',
        'Max Weekly Plans' => 'الحد الأقصى للخطط الأسبوعية',
        'plans' => 'خطط',
        'Manage Subjects' => 'إدارة المواد',
        'Subject Limits for %s' => 'حدود المواد لـ %s',
        'Overrides grade-level limit for specific subjects' => 'يتجاوز حد مستوى الصف لمواد معينة',
        'Save All Load Settings' => 'حفظ جميع إعدادات الحمل',
        'Close Subject Limits' => 'إغلاق حدود المواد',
        'Plan Load settings saved successfully.' => 'تم حفظ إعدادات حدود الخطط بنجاح.',
        'Settings were saved, but some limits were adjusted to respect grade constraints.' => 'تم حفظ الإعدادات، ولكن تم تعديل بعض الحدود لاحترام قيود الصف.',
        'Teachers Office Hours' => 'ساعات الاستقبال للمعلمين',
        'Office Hours' => 'ساعات الاستقبال',
        'Office Hours: %s' => 'ساعات الاستقبال: %s',
        'Switch Teacher:' => 'تبديل المعلم:',
        'Office hours saved successfully.' => 'تم حفظ ساعات الاستقبال بنجاح.',
        'Free Time / Slots' => 'وقت الفراغ / الفترات',
        'Action' => 'الإجراء',
        'No office hours defined yet. Click "Add Slot" to begin.' => 'لم يتم تحديد ساعات استقبال بعد. انقر فوق "إضافة فترة" للبدء.',
        'e.g., 10:00 AM - 12:00 PM' => 'مثال: 10:00 صباحاً - 12:00 مساءً',
        'Add Slot' => 'إضافة فترة',
        'Save Office Hours' => 'حفظ المواعيد',
        'Exam Schedule' => 'برنامج الامتحانات',
        'Teacher Exams' => 'امتحانات المعلم',
        'Semester exam added successfully.' => 'تم إضافة امتحان الفصل بنجاح.',
        'Semester exam updated successfully.' => 'تم تحديث امتحان الفصل بنجاح.',
        'Semester exam deleted.' => 'تم حذف امتحان الفصل.',
        'Semester exam activated.' => 'تم تفعيل امتحان الفصل.',
        'Add Exam Subject' => 'إضافة مادة امتحان',
        'Evaluation/Assessment' => 'التقويم',
        'First Exam' => 'التقويم الاول',
        'Second Exam' => 'التقويم الثاني',
        'Final Exam' => 'الامتحان النهائي',
        'Choose the appropriate exam' => 'اختر التقويم المناسب',
        'Search Plan' => 'بحث الخطة',
        'Select Subject' => 'اختر المادة',
        'Exam Date' => 'موعد الامتحان',
        'Exam Description' => 'وصف مادة الامتحان',
        'Student Book Material' => 'مادة كتاب الطالب',
        'Workbook Material' => 'مادة كتاب التدريب',
        'Exercise Book Material' => 'مادة كتاب التمارين',
        'Notebook Material' => 'مادة الدفتر',
        'Teacher Notes' => 'ملاحظات المعلم',
        'Add' => 'إضافة',
        'Apply and Add New' => 'تطبيق وإضافة جديد',
        'Cancel' => 'إلغاء',
        'Update' => 'تحديث',
        'Exam saved successfully.' => 'تم حفظ الامتحان بنجاح.',
        'Exam deleted successfully.' => 'تم حذف الامتحان بنجاح.',
        'No exams found for the selected criteria.' => 'لا توجد امتحانات للمعايير المختارة.',
        'Material' => 'المادة',
        'Follow Up' => 'المتابعات',
        'Student Attendance' => 'حضور الطلبة',
        'Employee Shifts' => 'مناوبات الموظفين',
        'Academic Year:' => 'العام الدراسي:',
        'Semester:' => 'الفصل الدراسي:',
        'Grade:' => 'الصف:',
        'Select Grade' => 'اختر الصف',
        'Section:' => 'الشعبة:',
        'Select Section' => 'اختر الشعبة',
        'Date:' => 'التاريخ:',
        'ID' => 'المعرف',
        'Student Name' => 'اسم الطالب',
        'Status' => 'الحالة',
        'Reason (if absent)' => 'السبب (في حال الغياب)',
        'No students found in this section.' => 'لم يتم العثور على طلاب في هذه الشعبة.',
        'Present' => 'حاضر',
        'Absent' => 'غائب',
        'Reason...' => 'السبب...',
        'Save Attendance' => 'حفظ الحضور',
        'Please select a Grade and Section to load students.' => 'يرجى اختيار الصف والشعبة لتحميل الطلاب.',
        'Manage Periods' => 'إدارة الفترات',
        'Manage Locations' => 'إدارة المواقع',
        'Time Slots' => 'الفترات الزمنية',
        'Define Shift' => 'تحديد المناوبة',
        'Select Period:' => 'اختر الفترة:',
        'Select Period' => 'اختر الفترة',
        'Loading shifts...' => 'جاري تحميل المناوبات...',
        'Manage Shift Periods' => 'إدارة فترات المناوبة',
        'Year:' => 'السنة:',
        'Type:' => 'النوع:',
        'Morning, Evening...' => 'صباحي، مسائي...',
        'Actions' => 'الإجراءات',
        'Location Name (e.g. Playground)' => 'اسم الموقع (مثال: الساحة)',
        'Area/Floor' => 'المنطقة/الطابق',
        'Mixed Gender' => 'مختلط',
        'Boys School' => 'مدرسة بنين',
        'Girls School' => 'مدرسة بنات',
        'Add Location' => 'إضافة موقع',
        'Area' => 'المنطقة',
        'Gender' => 'الجنس',
        'Slot Label (e.g. Morning Break)' => 'تسمية الفترة (مثال: استراحة الصباح)',
        'Start:' => 'البداية:',
        'End:' => 'النهاية:',
        'Add Slot' => 'إضافة فترة',
        'Label' => 'التسمية',
        'Define Shift & Assign Teachers' => 'تحديد المناوبة وتعيين المعلمين',
        'Day of Week:' => 'يوم الأسبوع:',
        'Time Slot:' => 'الفترة الزمنية:',
        'Location:' => 'الموقع:',
        'Assign Teachers:' => 'تعيين المعلمين:',
        'Save Shift & Assignments' => 'حفظ المناوبة والتعيينات',
        'Attendance saved successfully.' => 'تم حفظ الحضور بنجاح.',
        'Type' => 'النوع',
        'Time' => 'الوقت',
        'Student Material' => 'مادة الطالب',
        'Workbook' => 'كتاب التدريب',
        'Exercise' => 'كتاب التمارين',
        'Notebook' => 'الدفتر',
        'Search by teacher name...' => 'البحث باسم المعلم...',
        'No teachers found.' => 'لم يتم العثور على معلمين.',
        'Exam Report' => 'تقرير الاختبارات',
        'Specific Exam' => 'اختبار محدد',
        '-- All Exams --' => '-- جميع الاختبارات --',
        'Active Exam' => 'الاختبار النشط',
        'No exams found' => 'لم يتم العثور على اختبارات',

        // Bulk Upload Strings
        'Bulk Upload' => 'رفع جماعي',
        'Bulk Upload Instructions' => 'تعليمات الرفع الجماعي',
        'File Format:' => 'صيغة الملف:',
        'For Excel files (.xlsx): Each sheet represents one subject' => 'لملفات Excel (.xlsx): كل ورقة تمثل مادة واحدة',
        'For CSV files (.csv): Each file represents one subject' => 'لملفات CSV (.csv): كل ملف يمثل مادة واحدة',
        'Required Columns:' => 'الأعمدة المطلوبة:',
        'Unit number' => 'رقم الوحدة',
        'Unit name' => 'اسم الوحدة',
        'Learning objectives' => 'أهداف التعلم',
        'Lesson number' => 'رقم الدرس',
        'Lesson title' => 'عنوان الدرس',
        'Video URL (optional)' => 'رابط الفيديو (اختياري)',
        'Number of periods' => 'عدد الحصص',
        'Download CSV Template' => 'تحميل نموذج CSV',
        'Upload Curriculum Data' => 'رفع بيانات المنهج',
        'Select File' => 'اختر ملف',
        'Supported formats: Excel (.xlsx, .xls) or CSV (.csv)' => 'الصيغ المدعومة: Excel (.xlsx, .xls) أو CSV (.csv)',
        'Upload and Process' => 'رفع ومعالجة',
        'Upload Results' => 'نتائج الرفع',
        'Upload completed successfully' => 'تم الرفع بنجاح',
        'An error occurred during upload' => 'حدث خطأ أثناء الرفع',
        'Analysis' => 'تحليل المناهج',
        'Curriculum' => 'المنهج',
        'Timeline' => 'الخط الزمني',
        'Bulk Upload' => 'رفع جماعي',
        'All' => 'الكل',
        'Export Curriculum CSV' => 'تصدير المنهاج (CSV)',
        'Import Curriculum CSV' => 'استيراد المنهاج (CSV)',
        'Clear Curriculum' => 'مسح المادة',
        'Clear Grade Curriculum' => 'مسح منهج الصف بالكامل',
        '-- Select Grade --' => '-- اختر الصف --',
        '-- Select Semester --' => '-- اختر الفصل الدراسي --',
        'Select Subject to see units.' => 'اختر المادة لعرض الوحدات.',
        '1. Units' => '1. الوحدات',
        '2. Lessons' => '2. الدروس',
        '3. Question Bank' => '3. بنك الأسئلة',
        '+ Add Unit' => '+ إضافة وحدة',
        '+ Add Lesson' => '+ إضافة درس',
        '+ Add Question' => '+ إضافة سؤال',
        'Unit #' => 'رقم الوحدة',
        'Unit Name' => 'اسم الوحدة',
        'Learning Objectives' => 'أهداف التعلم',
        'Lesson #' => 'رقم الدرس',
        'Lesson Title' => 'عنوان الدرس',
        'Video URL' => 'رابط الفيديو',
        'Number of Periods' => 'عدد الحصص',
        'Question #' => 'رقم السؤال',
        'Question' => 'السؤال',
        'Suggested Answer' => 'الإجابة المقترحة',
        'Save Unit' => 'حفظ الوحدة',
        'Save Lesson' => 'حفظ الدرس',
        'Save Question' => 'حفظ السؤال',
        'Cancel' => 'إلغاء',
        'Select Unit to see lessons.' => 'اختر الوحدة لعرض الدروس.',
        'Select Lesson to see questions.' => 'اختر الدرس لعرض الأسئلة.',
        'Uploading and processing...' => 'جاري الرفع والمعالجة...',
        'Please select an academic year first.' => 'يرجى اختيار السنة الأكاديمية أولاً.',
        'Global curriculum wipe completed successfully!' => 'تم مسح جميع بيانات المنهج بنجاح!',
        'Error performing global wipe.' => 'خطأ أثناء مسح بيانات المنهج.',
        'Processing subjects...' => 'جاري معالجة المواد...',
        'Units Imported' => 'الوحدات المستوردة',
        'Lessons Imported' => 'الدروس المستوردة',
        'Errors' => 'الأخطاء',
        'Total Subjects Processed' => 'إجمالي المواد المعالجة',
        'Total Units Imported' => 'إجمالي الوحدات المستوردة',
        'Total Lessons Imported' => 'إجمالي الدروس المستوردة',
        'Please select both semester and grade' => 'يرجى اختيار الفصل والصف',
        'Please select a file to upload' => 'يرجى اختيار ملف للرفع',
        'Invalid semester or grade parameters.' => 'معاملات الفصل أو الصف غير صحيحة.',
        'Excel format not yet supported. Please use CSV format with subject names in the first column.' => 'صيغة Excel غير مدعومة حالياً. يرجى استخدام صيغة CSV مع أسماء المواد في العمود الأول.',
        'Unsupported file format. Please upload Excel (.xlsx, .xls) or CSV (.csv) file.' => 'صيغة الملف غير مدعومة. يرجى رفع ملف Excel (.xlsx, .xls) أو CSV (.csv).',
        'Required columns (Unit #, Unit Name) are missing.' => 'الأعمدة المطلوبة (رقم الوحدة، اسم الوحدة) مفقودة.',
        'Failed to create subject.' => 'فشل في إنشاء المادة.',
        'You do not have permission to import curriculum data.' => 'ليس لديك صلاحية لاستيراد بيانات المناهج.',
        'Please upload a valid file.' => 'يرجى رفع ملف صحيح.',

        // Clear Curriculum Strings
        'Are you sure you want to delete ALL units and lessons for "{subject}"? This action cannot be undone!' => 'هل أنت متأكد من رغبتك في حذف جميع الوحدات والدروس لـ "{subject}"؟ لا يمكن التراجع عن هذا الإجراء!',
        'Deleting...' => 'جاري الحذف...',
        'Curriculum cleared successfully!' => 'تم مسح المنهج بنجاح!',
        'Error clearing curriculum.' => 'خطأ في مسح المنهج.',
        'You do not have permission to delete curriculum data.' => 'ليس لديك صلاحية لحذف بيانات المناهج.',
        'Please select semester, grade, and subject.' => 'يرجى اختيار الفصل والصف والمادة.',
        'No curriculum data found to delete.' => 'لم يتم العثور على بيانات منهج للحذف.',
        'Successfully deleted all curriculum data. %d unit(s) and their lessons were removed.' => 'تم حذف جميع بيانات المنهج بنجاح. تم حذف %d وحدة/وحدات ودروسها.',

        // Clear Grade Curriculum Strings
        'Are you sure you want to delete ALL curriculum data for this grade? This will remove all units, lessons, and questions for ALL subjects in the selected semester and grade. This action cannot be undone!' => 'هل أنت متأكد من حذف جميع بيانات المنهج لهذا الصف؟ سيؤدي هذا إلى إزالة جميع الوحدات والدروس والأسئلة لجميع المواد في الفصل والصف المحددين. لا يمكن التراجع عن هذا الإجراء!',
        'Grade curriculum cleared successfully! %d unit(s) across all subjects were removed.' => 'تم مسح منهج الصف بنجاح! تم حذف %d وحدة/وحدات من جميع المواد.',

        // Clear All Academic Data Strings
        'Clear All Grades & Sections' => 'مسح جميع الصفوف والشعب',
        'Clear All Subjects' => 'مسح جميع المواد',
        'Are you sure you want to delete ALL grades and their sections? This action cannot be undone!' => 'هل أنت متأكد من حذف جميع الصفوف وشعبها؟ لا يمكن التراجع عن هذا الإجراء!',
        'Are you sure you want to delete ALL subjects? This action cannot be undone!' => 'هل أنت متأكد من حذف جميع المواد؟ لا يمكن التراجع عن هذا الإجراء!',
        'All grades and sections cleared successfully!' => 'تم مسح جميع الصفوف والشعب بنجاح!',
        'All subjects cleared successfully!' => 'تم مسح جميع المواد بنجاح!',
        'Cannot delete grades because some grades have linked data (sections, students, subjects, or curriculum). Please delete dependent data first.' => 'لا يمكن حذف الصفوف لأن بعض الصفوف لديها بيانات مرتبطة (شعب، طلاب، مواد، أو مناهج). يرجى حذف البيانات التابعة أولاً.',

        // Weekly Schedule Import/Export/PDF Strings
        'Export Schedule (CSV)' => 'تصدير الجدول (CSV)',
        'Import Schedule' => 'استيراد جدول',
        'Download PDF' => 'تحميل PDF',
        'Schedule exported successfully!' => 'تم تصدير الجدول بنجاح!',
        'Schedule imported successfully! %d items added.' => 'تم استيراد الجدول بنجاح! تم إضافة %d عنصر.',
        'Invalid CSV file format.' => 'تنسيق ملف CSV غير صحيح.',
        'Please select a file to import.' => 'يرجى تحديد الملف المراد استيراده.',
        'Error processing import file.' => 'خطأ في معالجة ملف الاستيراد.',
        'No data found in CSV file.' => 'لم يتم العثور على بيانات في ملف CSV.',
        'Curriculum Analysis' => 'تحليل المناهج',
        'Curriculum Coverage Summary' => 'ملخص تغطية المناهج',
        'Number of Units' => 'عدد الوحدات',
        'Number of Lessons' => 'عدد الدروس',
        'Success' => 'تم الرفع بنجاح',
        'No curriculum data found for the selected filters.' => 'لا توجد بيانات للمناهج حسب الفلاتر المختارة.',
        'Lessons Distribution (Selected Grade)' => 'توزيع الدروس (للصف المختار)',
        'Lessons Across All Grades' => 'الدروس عبر جميع الصفوف',
        'Subject for Trend Analysis' => 'المادة للتحليل المقارن',
        '"%s" Lessons Across All Grades' => '"%s" الدروس عبر جميع الصفوف',
        'Total' => 'الإجمالي',
        '%d Units' => '%d وحدة',
        '%d Lessons' => '%d درس',
        'Grade %s has a maximum of %d plans.' => 'الصف %s لديه حد أقصى %d خطط.',
        'A maximum of %d plans are allowed on %s.' => 'يسمح بحد أقصى %d خطط يوم %s.',
        'Subject %s has a maximum of %d plans.' => 'المادة %s لديها حد أقصى %d خطط.',
        'Weekly limit reached for this grade (%d plans).' => 'تم الوصول للحد الأقصى الأسبوعي لهذا الصف (%d خطة).',
        'Daily limit reached for this day (%d plans).' => 'تم الوصول للحد الأقصى اليومي لهذا اليوم (%d خطة).',
        'Weekly limit reached for this subject (%d plans).' => 'تم الوصول للحد الأقصى الأسبوعي لهذه المادة (%d خطة).',
        'Daily Max Limits (Sun - Thu)' => 'الحد الأقصى اليومي (الأحد - الخميس)',
        'Approving...' => 'جاري الاعتماد...',
        'Communication error' => 'خطأ في الاتصال',
        'Error occurred' => 'حدث خطأ',
        'Error loading units' => 'خطأ في تحميل الوحدات',
        'Error loading lessons' => 'خطأ في تحميل الدروس',
        'Loading questions...' => 'جاري تحميل الأسئلة...',
        'Error loading questions' => 'خطأ في تحميل الأسئلة',
        'An error occurred while deleting the plan.' => 'حدث خطأ أثناء حذف الخطة.',
        'Failed to delete plan.' => 'فشل حذف الخطة.',
        'Are you sure you want to delete this plan?' => 'هل أنت متأكد من رغبتك في حذف هذه الخطة؟',
        'No plans saved for today yet.' => 'لا توجد خطط محفوظة لهذا اليوم بعد.',
        'Invalid ID' => 'معرف غير صالح',
        'Unauthorized' => 'غير مصرح',
        'Invalid parameters' => 'معايير غير صالحة',
        'Database error' => 'خطأ في قاعدة البيانات',
        'Invalid Grade ID' => 'معرف صف غير صالح',
        'Invalid Teacher ID' => 'معرف معلم غير صالح',
        'Notebook instructions...' => 'تعليمات الدفتر...',
        'Worksheet details...' => 'تفاصيل ورقة العمل...',
        'Weekly Plan Analysis' => 'تحليل الخطط الأسبوعية',
        'Plan Coverage' => 'تغطية الخطط الأسبوعية',
        'Curriculum Coverage' => 'تغطية المنهاج',
        'Optimal' => 'مثالي',
        'High' => 'مرتفع',
        'Low' => 'منخفض',
        '(%d plans)' => '(%d خطط)',
        '(%d lessons)' => '(%d دروس)',
        'Plan Coverage is calculated as (Plans for Subject / Total Plans for the Week). Curriculum Coverage is calculated based on the total lessons assigned to this grade in the current semester.' => 'يتم احتساب تغطية الخطط كـ (عدد الخطط للمادة / إجمالي الخطط للأسبوع). يتم احتساب تغطية المنهاج بناءً على إجمالي الدروس المسندة لهذا الصف في الفصل الدراسي الحالي.',
        'Weekly Coverage Total' => 'إجمالي التغطية الأسبوعية',
        '%d / %d Plans' => '%d / %d خطة',
        'Coverage Percentage' => 'نسبة التغطية',
        'No published plans found for this week, but drafts exist.' => 'لم يتم العثور على خطط منشورة لهذا الأسبوع، ولكن توجد مسودات.',
        'No weekly plans found for the selected week.' => 'لم يتم العثور على أي خطط أسبوعية للأسبوع المحدد.',
        'Preview' => 'معاينة',
        'Note: As an admin, you can see draft plans.' => 'ملاحظة: بصفتك مسؤولاً، يمكنك رؤية مسودات الخطط.',
        'Academic Year' => 'السنة الأكاديمية',
        'Draft' => 'مسودة',
        'Published' => 'منشور',
        'عدد الواجبات' => 'عدد الواجبات',
        'Semester dates must be within the academic year range.' => 'يجب أن تكون تواريخ الفصل الدراسي ضمن نطاق السنة الأكاديمية.',
        'A semester with this name already exists in this academic year.' => 'يوجد فصل دراسي بهذا الاسم بالفعل في هذه السنة الأكاديمية.',
        'Semester dates overlap with another existing semester.' => 'تتداخل تواريخ الفصل الدراسي مع فصل دراسي آخر موجود.',
        'Invalid Semester.' => 'فصل دراسي غير صالح.',
        'Subject Coverage' => 'تغطية المادة',
        'Guide & Definitions' => 'دليل وتعريفات',
        'Understanding the columns' => 'فهم الأعمدة',
        'Plan Coverage:' => 'تغطية الخطط:',
        'Subject Coverage:' => 'تغطية المادة:',
        'Shows the percentage of coverage of the required total number of plans (how many did you cover out of the required number per week compared to all subjects).' => 'يوضح نسبة تغطية العدد الإجمالي المطلوب للخطط (كم خطة قمت بتغطيتها من العدد المطلوب أسبوعياً مقارنة بجميع المواد).',
        'Shows the percentage of coverage of the required subject total number of plans (how many did you cover out of the required number per subject during the week).' => 'يوضح نسبة تغطية العدد الإجمالي المطلوب للمادة (كم خطة قمت بتغطيتها من العدد المطلوب لكل مادة خلال الأسبوع).',
        '%d / %d plans' => 'خطة %d / %d',
        'Plan coverage' => 'تغطية الخطط',
        'Subject coverage' => 'تغطية المادة',
        'Plan Coverage' => 'تغطية الخطط',
        'Subject Coverage' => 'تغطية المادة',
        'Semester:' => 'الفصل الدراسي:',
        'Week:' => 'الأسبوع:',
        'Year:' => 'السنة:',
        'Section:' => 'الشعبة:',
        'All Weeks' => 'جميع الأسابيع',
        'Week %d (%s)' => 'الأسبوع %d (%s)',
        'Week %d' => 'الأسبوع %d',
        '%d / %d Lessons' => 'درس %d / %d',
        'Total Grade Coverage' => 'إجمالي تغطية الصف',
        'Total number of plans and reviews' => 'إجمالي عدد الخطط والمتابعات',
        'Total number of all lesson' => 'إجمالي عدد جميع الدروس',
        'Total number of covered lessons' => 'إجمالي عدد الدروس المنجزة',
        'Current Lesson Coverage' => 'غطاء الدروس الحالي',
        'Total Lesson Coverage' => 'إجمالي غطاء الدروس',
        'Understanding the columns' => 'فهم الأعمدة',
        'year' => 'سنة',
        'years' => 'سنوات',
        'month' => 'شهر',
        'months' => 'شهور',
        'week' => 'أسبوع',
        'weeks' => 'أسابيع',
        'day' => 'يوم',
        'days' => 'أيام',
        'hour' => 'ساعة',
        'hours' => 'ساعات',
        'minute' => 'دقيقة',
        'minutes' => 'دقائق',
        'second' => 'ثانية',
        'seconds' => 'ثواني',
        'ago' => 'مضت',
        'just now' => 'الآن',
        'Approve' => 'اعتماد',
        'Request Edits' => 'طلب تعديل',
        'Send & Request Edits' => 'إرسال وطلب تعديل',
        'Please provide feedback to the teacher about why this plan needs changes.' => 'يرجى تقديم ملاحظات للمعلم حول سبب حاجة هذه الخطة لتعديلات.',
        'Enter your comments here...' => 'أدخل ملاحظاتك هنا...',
        'Please enter some feedback.' => 'يرجى إدخال ملاحظات.',
        'Sending...' => 'جاري الإرسال...',
        'Approving...' => 'جاري الاعتماد...',
        'FORCE DELETE EVERYTHING' => 'حذف جميع بيانات المناهج',
        'Security Settings' => 'إعدادات الأمان',
        'Admin Deletion Password' => 'كلمة مرور الحذف الإداري',
        'Required for the "Force Delete Everything" feature in Curriculum Management.' => 'مطلوب لميزة "حذف جميع بيانات المناهج" في إدارة المناهج.',
        'Invalid deletion password.' => 'كلمة مرور الحذف غير صحيحة.',
        'Please set a deletion password in General Settings first.' => 'يرجى تعيين كلمة مرور الحذف في الإعدادات العامة أولاً.',
        'Curriculum wipe for Year: {year}, Semester: {semester}, Grade: {grade} completed successfully!' => 'تم حذف المنهج بنجاح لـ السنة: {year}، الفصل: {semester}، الصف: {grade}!',
        'Missing academic year, semester, or grade.' => 'السنة الأكاديمية أو الفصل أو الصف مفقود.',
        'Wipe cancelled. Password is required.' => 'تم إلغاء المسح. كلمة المرور مطلوبة.',
        'Wipe cancelled. Final confirmation mismatched.' => 'تم إلغاء المسح. تأكيد المسح غير متطابق.',
        'FINAL CONFIRMATION: To proceed, please type "DELETE" in the box below:' => 'التأكيد النهائي: للاستمرار، يرجى كتابة "DELETE" في المربع أدناه:',
        'SECURITY AUTHORIZATION REQUIRED: Please enter the Admin Deletion Password:' => 'مطلوب تفويض أمني: يرجى إدخال كلمة مرور الحذف الإداري:',
        'SECURITY ERROR: Admin Deletion Password not found.\nPlease navigate to Settings > General and set a deletion password before attempting this action.' => "خطأ أمني: كلمة مرور الحذف الإداري غير موجودة.\nيرجى الانتقال إلى الإعدادات > عام وتعيين كلمة مرور الحذف قبل محاولة هذا الإجراء.",
        'CRITICAL WARNING: This will delete ALL curriculum data (Units, Lessons, Questions) for the selected year: {year}. This action is IRREVERSIBLE!\n\nAre you absolutely sure?' => "تحذير حرج: سيؤدي هذا إلى حذف جميع بيانات المناهج (الوحدات، الدروس، الأسئلة) للسنة المختارة: {year}.\n\nهذا الإجراء لا يمكن التراجع عنه!\n\nهل أنت متأكد تماماً؟",
        'Please select a subject.' => 'يرجى اختيار المادة.',
        'Please select a unit.' => 'يرجى اختيار الوحدة.',
        'Please select a lesson.' => 'يرجى اختيار الدرس.',
        'Subject %s already has a plan for today.' => 'المادة %s لديها خطة مسبقة لهذا اليوم.',
        'Grade %s has a maximum of %d homework plans per week.' => 'الصف %s لديه بحد أقصى %d خطط واجبات أسبوعياً.',
        'A maximum of %d homework plans are allowed on %s.' => 'يسمح بحد أقصى بـ %d خطط واجبات في يوم %s.',
        'Subject %s has a maximum of %d homework plans per week.' => 'المادة %s لديها بحد أقصى %d خطط واجبات أسبوعياً.',
        'Homeworks' => 'الواجبات',
        'Required Plans' => 'الخطط المطلوبة',
        'Approved Plans' => 'الخطط المعتمدة',
        'Reviews' => 'المتابعات',
        'Teacher Plan Coverage' => 'تغطية خطط المعلم',
        'Schedule Coverage' => 'تغطية الجدول',
        'Teacher coverage of the required weekly plans.' => 'تغطية المعلم للخطط الأسبوعية المطلوبة.',
        'Schedule coverage by plans and reviews compared to master schedule periods.' => 'تغطية الجدول بالخطط والمتابعات مقارنة بعدد حصص الجدول.',
        'Total Grade Coverage' => 'إجمالي تغطية الصف',
        'Total Weekly Coverage' => 'إجمالي التغطية الأسبوعية',
        'Review Queue' => 'قائمة المتابعة',
        'Needs Revision' => 'بحاجة لتعديل',
        'Edited' => 'تم التعديل',
        'Teacher Response' => 'رد المعلم',
        'Admin Feedback' => 'ملاحظات المشرف',
        'Pending Edits' => 'طلبات معلقة',
        'Approved History' => 'سجل الاعتمادات',
        'Mark as Checked' => 'تم التحقق',
        'Mark as Needs Revision' => 'طلب تعديل',
        'Final Approve' => 'اعتماد نهائي',
        'Feedback History' => 'سجل الملاحظات',
        'Request Edits' => 'طلب تعديلات',
        'Submit Revision' => 'إرسال التعديلات',
        'Semester exam added successfully.' => 'تم إضافة امتحان الفصل بنجاح.',
        'Semester exam updated successfully.' => 'تم تحديث امتحان الفصل بنجاح.',
        'Semester exam deleted.' => 'تم حذف امتحان الفصل.',
        'Semester exam activated.' => 'تم تفعيل امتحان الفصل.',
        'Manage Exams' => 'إدارة الامتحانات',
        'Exams for %s' => 'امتحانات %s',
        'Add Exam' => 'إضافة امتحان',
        'Exam Name' => 'اسم الامتحان',
        'Delete this exam?' => 'حذف هذا الامتحان؟',
        'Exam Schedule' => 'جدول الامتحانات',
        'Exam Schedule for %s' => 'جدول الاختبارات لـ: %s',
        'No approved exams found for student: %s' => 'لا توجد اختبارات معتمدة للطالب: %s',
        'Evaluation' => 'التقييم',
        'Material' => 'المادة',
        'Date' => 'التاريخ',
        'Room' => 'القاعة',
        'Description' => 'الوصف',
        'Actions' => 'الإجراءات',
        'Completed' => 'مكتمل',
        'Not Completed' => 'غير مكتمل',
        'Add Exam Subject' => 'إضافة مادة للامتحان',
        'Active Year' => 'العام الدراسي',
        'Active Semester' => 'الفصل الدراسي',
        'Active Exam' => 'الامتحان النشط',
        'Exam Details' => 'تفاصيل الامتحان',
        'Student Book' => 'كتاب الطالب',
        'Workbook' => 'كتاب التمارين',
        'Exercise Notebook' => 'دفتر المتابعة',
        'Teacher Notes' => 'ملاحظات المعلم',
        'Subject' => 'المادة',
        'Grade' => 'الصف',
        'First Exam' => 'الامتحان الأول',
        'Second Exam' => 'الامتحان الثاني',
        'Final Exam' => 'الامتحان النهائي',
        'Academic Portal' => 'أكاديمية علماء المستقبل',
        'Search by teacher name...' => 'البحث باسم المعلم...',
        'Search by subject name...' => 'البحث باسم المادة...',
        'Available Times' => 'الأوقات المتاحة',
        'No teachers match your search.' => 'لم يتم العثور على معلمين يطابقون بحثك.',
        'Please specify a valid grade ID in the shortcode.' => 'يرجى تحديد معرف صف صالح في الكود القصير.',
        'Total Exams' => 'إجمالي الاختبارات',
        'Booklets & Notebooks' => 'الدوسيات والدفاتر',
        'Edit Template Settings' => 'تعديل إعدادات النموذج',
        'Score Labels (Max 5, Highest to Lowest)' => 'تسميات الدرجات (بحد أقصى 5، من الأعلى للأدنى)',
        'Template Name' => 'اسم النموذج',
        'No data' => 'لا يوجد بيانات',
        'Section 1' => 'الشعبة 1',
        'Section 2' => 'الشعبة 2',
        'Level' => 'مستوى',
        'e.g., Mastered' => 'مثل: أتقن المهارة',
        'e.g., Partially Mastered' => 'مثل: أتقن جزءاً من المهارة',
        'e.g., Not Mastered' => 'مثل: لم يتقن المهارة',
        'e.g., (Optional Level 4)' => 'مثل: (مستوى اختياري 4)',
        'e.g., (Optional Level 5)' => 'مثل: (مستوى اختياري 5)',
        'Transportation' => 'المواصلات',
        'Buses' => 'الباصات',
        'Bus Management' => 'إدارة الباصات',
        'Add New Bus' => 'إضافة باص جديد',
        'Bus Number' => 'رقم الباص',
        'Plate Number' => 'رقم اللوحة',
        'Passenger Capacity' => 'سعة الركاب',
        'Driver' => 'السائق',
        'Companion' => 'المرافقة',
        'License Expiry' => 'تاريخ انتهاء الرخصة',
        'Engine Capacity' => 'سعة المحرك',
        'Fuel Type' => 'نوع الوقود',
        'Status' => 'الحالة',
        'Active' => 'فعال',
        'Inactive' => 'غير فعال',
        'Edit Bus' => 'تعديل بيانات الباص',
        'Save Bus' => 'حفظ البيانات',
        'Are you sure you want to delete this bus?' => 'هل أنت متأكد من حذف هذا الباص؟',
        'Select Driver' => 'اختر السائق',
        'Select Companion' => 'اختر المرافقة',
        'Student Assignments' => 'تسجيل الطلاب على الباصات',
        'Capacity' => 'السعة',
        'Assigned Students' => 'الطلاب المسجلين',
        'Unassigned Students' => 'الطلاب غير المسجلين',
        'Assign Selected' => 'تسجيل المحددين',
        'Select a bus' => 'اختر باصاً',
        'Please select a bus to manage student assignments' => 'يرجى اختيار باص لإدارة تسجيل الطلاب',
        'Loading...' => 'جاري التحميل...',
        'No students assigned' => 'لا يوجد طلاب مسجلين',
        'All students are assigned' => 'جميع الطلاب مسجلين',
        'Unassign' => 'إلغاء التسجيل',
        'Assign selected students to this bus?' => 'تسجيل الطلاب المحددين على هذا الباص؟',
        'Unassign this student from the bus?' => 'إلغاء تسجيل هذا الطالب من الباص؟',
        '%d student(s) assigned successfully' => 'تم تسجيل %d طالب/طلاب بنجاح',
        'Student unassigned successfully' => 'تم إلغاء تسجيل الطالب بنجاح',
        'Cannot assign %d students. Only %d seats available.' => 'لا يمكن تسجيل %d طالب/طلاب. يوجد %d مقعد/مقاعد فقط.',
        'Bus not found.' => 'الباص غير موجود.',
        'Missing required parameters' => 'معاملات مطلوبة مفقودة',
        'Failed to unassign student' => 'فشل إلغاء تسجيل الطالب',
        'Missing academic year ID' => 'معرف السنة الأكاديمية مفقود',

        // Lesson Planner Strings
        'Lesson Planner' => 'دفتر تحضير الدروس',
        'Create and manage daily lesson plans.' => 'إنشاء وإدارة خطط الدروس اليومية.',
        'Add New Lesson Plan' => 'إضافة خطة درس جديدة',
        'Lesson plan saved successfully.' => 'تم حفظ خطة الدرس بنجاح.',
        'Lesson plan deleted.' => 'تم حذف خطة الدرس.',
        'No lesson plans found.' => 'لم يتم العثور على خطط دروس.',
        'Basic Information' => 'المعلومات الأساسية',
        'Lesson Title' => 'عنوان الدرس',
        'Number of Classes' => 'عدد الحصص',
        'Enter lesson title...' => 'أدخل عنوان الدرس...',
        'from curriculum' => 'من المنهج',
        'Learning Outcomes' => 'نواتج التعلم',
        'Learning outcome...' => 'ناتج التعلم...',
        'Add Outcome' => 'إضافة ناتج',
        'Teaching Strategies' => 'استراتيجيات التدريس',
        'Preparation & Prior Learning' => 'التهيئة والتعلم السابق',
        'Student Engagement' => 'انخراط الطلاب',
        'Explanation & Demonstration' => 'الشرح والتفسير',
        'How will you introduce the lesson and connect to prior knowledge?' => 'كيف ستقدم الدرس وتربطه بالمعرفة السابقة؟',
        'How will students actively participate?' => 'كيف سيشارك الطلاب بفاعلية؟',
        'Explain the lesson content delivery approach.' => 'اشرح أسلوب تقديم محتوى الدرس.',
        'Prior Learning' => 'التعلم السابق',
        'Prerequisites' => 'المتطلبات السابقة',
        'What should students already know before this lesson?' => 'ما الذي يجب أن يعرفه الطلاب قبل هذا الدرس؟',
        'Assessment Tools' => 'أدوات التقييم',
        'Assessment methods, tools, and strategies used to evaluate learning...' => 'طرق وأدوات واستراتيجيات التقييم المستخدمة لتقييم التعلم...',
        'Teaching Resources' => 'الوسائل التعليمية',
        'Teaching materials, technology, and resources...' => 'المواد التعليمية والتقنية والوسائل...',
        'Differentiation & Support' => 'التمايز والدعم',
        'Differentiation strategies for different learner levels...' => 'استراتيجيات التمايز لمستويات المتعلمين المختلفة...',
        'Closing Activity' => 'إغلاق الدرس',
        'How will you consolidate and wrap up the lesson?' => 'كيف ستلخص وتختتم الدرس؟',
        'Self-Reflection' => 'التأمل الذاتي',
        'Were the learning outcomes achieved? What would you change?' => 'هل تم تحقيق نواتج التعلم؟ ما الذي ستغيره؟',
        'Homework assignment details...' => 'تفاصيل الواجب المنزلي...',
        'Save as Final' => 'حفظ نهائي',
        'Final' => 'نهائي',
        'Are you sure you want to delete this lesson plan?' => 'هل أنت متأكد من حذف خطة الدرس هذه؟',
        'Print' => 'طباعة',

        // Lesson Planner V2 — Stage Builder & Compliance
        'Lesson Stages' => 'مراحل الدرس',
        'Teacher Action' => 'نشاط المعلم',
        'Learner Action' => 'نشاط المتعلم',
        'Time' => 'الزمن',
        'min' => 'دقيقة',
        'Compliance' => 'نسبة الامتثال',
        'Details' => 'التفاصيل',
        'SMART: Verb + Content + Performance Level' => 'ذكية: فعل + محتوى + مستوى الأداء',
        'Select Verb' => 'اختر الفعل',
        'Content / concept' => 'المحتوى / المفهوم',
        'Performance level' => 'مستوى الأداء',
        'Teacher Actions' => 'أنشطة المعلم',
        'Learner Actions' => 'أنشطة المتعلم',
        'Back to List' => 'العودة للقائمة',
        'Suggested' => 'مقترح',
        'Save as Draft' => 'حفظ كمسودة',
        'Select Subject' => 'اختر المادة',
        '-- Select Subject --' => '-- اختر المادة --',
        '-- Select Unit --' => '-- اختر الوحدة --',

        // Lesson Planner V2.1 — Curriculum Timeline Integration
        'Lesson' => 'الدرس',
        '-- Select Lesson --' => '-- اختر الدرس --',
        'Start Date' => 'تاريخ البداية',
        'End Date' => 'تاريخ النهاية',
        'Auto-filled from timeline' => 'يتم التعبئة تلقائياً من الخطة الزمنية',
        'Period Duration (min)' => 'مدة الحصة (دقيقة)',
        // Academic Supervision Strings
        'Academic Supervision' => 'المتابعة الأكاديمية',
        'Plan Visit' => 'زيارة صفية',
        'Complete Plan' => 'توثيق الزيارة',
        'Assign Supervisor' => 'إسناد المشرفين',
        'Reports' => 'التقارير',
        'Analytics' => 'التحليلات',
        'Schedule Type' => 'نوع الجدول',
        'Normal Schedule' => 'الجدول العادي',
        'Ramadan Schedule' => 'جدول رمضان',
        'Month' => 'الشهر',
        'Week Start' => 'بداية الأسبوع',
        'Week' => 'الأسبوع',
        'Grade' => 'الصف',
        'Section' => 'الشعبة',
        'Supervisor' => 'المشرف',
        '-- Select Supervisor --' => '-- اختر المشرف --',
        'Coverage Table' => 'جدول التغطية',
        'Assigned Supervisors' => 'المشرفون المسندون',
        'Supervisor Visits' => 'زيارات المشرف',
        'Total Visits (Grade-Section)' => 'إجمالي الزيارات (صف-شعبة)',
        'Current Week Summary' => 'ملخص الأسبوع الحالي',
        'Total Visits' => 'إجمالي الزيارات',
        'Avg. Evaluation Score' => 'متوسط التقييم',
        'Completion Rate' => 'نسبة الإنجاز',
        'Update Dashboard' => 'تحديث اللوحة',
        'Clear' => 'مسح',
        'Visits this Week' => 'زيارات هذا الأسبوع',
        'Visits this Month' => 'زيارات هذا الشهر',
        'Visits this Semester' => 'زيارات هذا الفصل',
        'Visits this Year' => 'زيارات هذا العام',
        'Weekly Avg. Score' => 'متوسط أداء الأسبوع',
        'Weekly Completion Rate' => 'نسبة إنجاز الأسبوع',
        'Select a supervisor to view their performance metrics and dashboard.' => 'قم باختيار مشرف لعرض مقاييس أدائه.',
        'No active week found for today.' => 'لا يوجد أسبوع نشط لليوم.',
        'No sections found.' => 'لا توجد شعب.',
        'No visits scheduled' => 'لا توجد زيارات مجدولة',
        'None' => 'لا يوجد',
        'Period: %s to %s' => 'الفترة: %s إلى %s',
        '%d planned, %d completed' => '%d مجدولة، %d مكتملة',
        'Performance by Supervisor' => 'الأداء حسب المشرف',
        'Total Planned Visits' => 'الزيارات المجدولة',
        'Total Completed Visits' => 'الزيارات المنجزة',
        'Completed Visits' => 'زيارات منجزة',
        'Plan Supervisor Visit' => 'جدولة زيارة إشرافية',
        'Visit Date' => 'تاريخ الزيارة',
        'Evaluation Template' => 'نموذج التقييم',
        'Confirm Plan' => 'تأكيد الجدولة',
        'Selected date does not match the scheduled day.' => 'التاريخ المختار لا يطابق اليوم المجدول.',
        'Supervisor Visits Report' => 'تقرير الزيارات الإشرافية',
        'Score' => 'الدرجة',
        'Start Evaluation' => 'بدء التقييم',
        'View Evaluation' => 'عرض التقييم',
        'Supervisor Visit Evaluation' => 'تقييم زيارة مشرف',
        'Loading evaluation details...' => 'جاري تحميل تفاصيل التقييم...',
        'Are you sure you want to delete this visit and its evaluation data?' => 'هل أنت متأكد من حذف هذه الزيارة وبيانات التقييم الخاصة بها؟',
        'Subject (Optional)' => 'المادة (اختياري)',
        'Leave empty to assign to all subjects in this grade.' => 'اترك الحقل فارغاً لإسناد جميع المواد في هذا الصف.',
        'Create Assignment' => 'إنشاء إسناد',
        'Active Assignments' => 'الإسنادات النشطة',
        'Showing assignments for %s' => 'عرض الإسنادات لـ %s',
        'No supervisor assignments found for this semester.' => 'لم يتم العثور على إسنادات مشرفين في هذا الفصل.',
        'Are you sure you want to remove this assignment?' => 'هل أنت متأكد من رغبتك في إزالة هذا الإسناد؟',
        'Failed to save assignment' => 'فشل في حفظ الإسناد',
        'Failed to delete assignment' => 'فشل في حذف الإسناد',
        'Confirm Plan' => 'تأكيد الخطة المنفذة',
        'Delete Planned Visit' => 'حذف الزيارة المجدولة',
        'Are you sure you want to delete this planned visit?' => 'هل أنت متأكد من حذف هذه الزيارة المجدولة؟',
        'Supervisor Visits Report' => 'تقرير زيارات المشرفين',
        'Performance by Grade' => 'الأداء حسب الصف الدراسي',
        'Performance by Supervisor' => 'الأداء حسب المشرف',
        'Total Scheduled Visits' => 'إجمالي الزيارات المجدولة',
        'Completed Visits' => 'الزيارات المكتملة',
        'Performance by Teacher' => 'الأداء حسب المعلم',
        'Total Scheduled Visits' => 'إجمالي الزيارات المجدولة',
        'Average Score' => 'متوسط الدرجة',
        'Performance by Grade' => 'الأداء حسب الصف',
        'Visits' => 'الزيارات',
        'No visits planned yet.' => 'لا توجد زيارات مجدولة بعد.',
        'No data available.' => 'لا توجد بيانات متاحة.',
        'Period %d' => 'الحصة %d',
        'Planned' => 'مخطط له',
        // Weekly Plan Load Strings
        'Weekly Limit' => 'الحد الأسبوعي',
        'Current Load' => 'الحمل الحالي',
        'Balance' => 'الرصيد',
        'Manual Override' => 'تجاوز يدوي',
        'Grade Setup' => 'إعدادات الصف',
        'Subject Setup' => 'إعدادات المادة',
        // Coverage & Analysis Strings
        'Subject / Section' => 'المادة / الشعبة',
        'Subject Name / Section' => 'اسم المادة / الشعبة',
        'Total Plans' => 'إجمالي الخطط',
        'Covered' => 'المغطى',
        'Remaining' => 'المتبقي',
        'Percentage' => 'النسبة',
        'Weekly Plan Analysis' => 'تحليل الخطط الأسبوعية',
        'Overall metrics for plan coverage and curriculum progress.' => 'القياسات العامة لتغطية الخطط وتقدم المنهاج.',
        'Please select a grade from the sidebar to view analysis.' => 'يرجى اختيار صف من القائمة الجانبية لعرض التحليل.',
        'Analysis Report: %s' => 'تقرير التحليل: %s',
        'Total number of plans' => 'إجمالي عدد الخطط',
        'Total number of all lesson' => 'إجمالي عدد الدروس',
        'Total number of covered lessons' => 'إجمالي عدد الدروس المغطاة',
        'The total lessons scheduled in the curriculum timeline for the entire semester.' => 'إجمالي الدروس المجدولة في الخط الزمني للمنهاج للفصل الدراسي بأكمله.',
        'Percentage of uniquely covered lessons up to the selected week compared to the total curriculum lessons.' => 'نسبة الدروس المغطاة بشكل فريد حتى الأسبوع المختار مقارنة بإجمالي دروس المنهاج.',
        'Lesson Coverage' => 'تغطية الدروس',
        'Number of Units' => 'عدد الوحدات',
        'Number of Lessons' => 'عدد الدروس',
        'Coverage Percentage' => 'نسبة التغطية',
        'Curriculum Coverage Summary' => 'ملخص تغطية المنهج',
        'Total Weekly Coverage' => 'إجمالي التغطية الأسبوعية',
        'Teacher Plan Coverage' => 'تغطية خطط المعلم',
        'Schedule Coverage' => 'تغطية الجدول',
        '%d / %d Lessons Covered' => 'تم تغطية %d / %d درس',
        'Resources & Reflection' => 'المصادر والتأمل الذاتي',
        'Preparation & Engagement' => 'التهيئة والانخراط',
        'Explanation & Interpretation' => 'الشرح والتفسير',
        'Elaboration & Differentiation' => 'التوسع والتمايز',
        'Teaching Strategy' => 'استراتيجية التدريس',
        'Assessment Strategy' => 'استراتيجية التقويم',
        'Assessment Tool' => 'أداة التقويم',
        // Permissions & Roles
        'Submenu / Tab Permission' => 'صلاحية القائمة الفرعية / التبويب',
        'Supervisor (Custom)' => 'مشرف (مخصص)',
        'Assistant (Custom)' => 'مساعد (مخصص)',
        'View Dashboard' => 'عرض لوحة القيادة',
        'Access Reports' => 'الوصول للتقارير',
        'Plan Completion Report' => 'تقرير إنجاز الخطط',
        'Homework Summary Report' => 'تقرير ملخص الواجبات',
        'Access Management' => 'إدارة الوصول',
        'View Plan List' => 'عرض قائمة الخطط',
        'Approve/Request Edits' => 'اعتماد / طلب تعديلات',
        'Assign Teachers' => 'إسناد المعلمين',
        'Office Hours' => 'الساعات المكتبية',
        'Timeline Management' => 'إدارة الجدول الزمني',
        'View Timeline' => 'عرض الجدول الزمني',
        'Bulk Upload' => 'تحميل جماعي',
        'Fill Exam Details' => 'تعبئة تفاصيل الامتحان',
        'Upload Exam Files' => 'تحميل ملفات الامتحانات',
        'Question Bank (Exam Engine)' => 'بنك الأسئلة (محرك الاختبارات)',
        'Create / Edit Exams (Exam Engine)' => 'إنشاء / تعديل الاختبارات (محرك الاختبارات)',
        'Grade Essays (Exam Engine)' => 'تصحيح الأسئلة المقالية (محرك الاختبارات)',
        'View Results (Exam Engine)' => 'عرض النتائج (محرك الاختبارات)',
        'Access Evaluation' => 'الوصول للتقييمات',
        'Manage Families' => 'إدارة العائلات',
        'Manage Students / Enrollment' => 'إدارة الطلاب / التسجيل',
        'Manage Teachers' => 'إدارة المعلمين',
        'Manage Permissions' => 'إدارة الصلاحيات',
        'View Activity Logs' => 'عرض سجلات النشاط',
        'Access Media Library' => 'الوصول لمكتبة الوسائط',
        'Upload Video' => 'تحميل فيديو',
        'Drive Settings' => 'إعدادات الدرايف',
        'Upload Log' => 'سجل التحميلات',
        'Approve Video' => 'اعتماد الفيديو',
        'Student Attendance' => 'حضور الطلاب',
        'Employee Shifts' => 'مناوبات الموظفين',
        'Follow Up' => 'المتابعة',
        'Transportation' => 'النقل والمواصلات',
        'Manage Buses' => 'إدارة الحافلات',
        'Access Supervision' => 'الوصول للإشراف',
        'View Plan Load' => 'عرض حدود الخطط',
        'Teachers (WordPress Users with Teacher Role)' => 'المعلمون (مستخدمو ووردبريس برتبة معلم)',
        'No teachers found. Assign the "Teacher" role to users to make them teachers.' => 'لم يتم العثور على معلمين. قم بإسناد دور "معلم" للمستخدمين ليظهروا هنا.',
        'Update Teacher' => 'تحديث المعلم',
        'Employee ID' => 'الرقم الوظيفي',
        'Media Library' => 'مكتبة الوسائط',
        'Upload Log' => 'سجل الرفع',
        'Drive Settings' => 'إعدادات الدرايف',
        'Academic Year' => 'السنة الأكاديمية',
        'Semester' => 'الفصل الدراسي',
        'Grade' => 'الصف',
        '-- Select Grade --' => '-- اختر الصف --',
        'Subject' => 'المادة',
        '-- Select Subject --' => '-- اختر المادة --',
        'Load Curriculum' => 'تحميل المناهج',
        'Sync Lesson Status' => 'تزامن حالة الدروس',
        'Select filters and click Load Curriculum to start uploading videos.' => 'اختر الفلاتر واضغط على تحميل المناهج لبدء رفع الفيديوهات.',
        'All Statuses' => 'كل الحالات',
        'Completed' => 'مكتمل',
        'Pending' => 'قيد الانتظار',
        'Failed' => 'فشل',
        'Refresh' => 'تحديث',
        'Date' => 'التاريخ',
        'Lesson' => 'الدرس',
        'Grade/Subject' => 'الصف/المادة',
        'Status' => 'الحالة',
        'Drive Link' => 'رابط الدرايف',
        'Actions' => 'الإجراءات',
        'Loading logs...' => 'جاري تحميل السجلات...',
        'Google Drive Settings' => 'إعدادات جوجل درايف',
        'Client ID' => 'معرف العميل (Client ID)',
        'Client Secret' => 'سر العميل (Client Secret)',
        'Redirect URI' => 'رابط إعادة التوجيه (Redirect URI)',
        'Copy this URL and add it to "Authorized redirect URIs" in the Google Cloud Console.' => 'انسخ هذا الرابط وأضفه إلى "Authorized redirect URIs" في كونسول جوجل كلاود.',
        'Root Folder ID' => 'معرف المجلد الرئيسي',
        'The folder ID on Google Drive where videos will be stored.' => 'معرف المجلد على جوجل درايف حيث سيتم تخزين الفيديوهات.',
        'Max Upload Size (MB)' => 'أقصى حجم للرفع (MB)',
        'Save Settings' => 'حفظ الإعدادات',
        'Authentication Status' => 'حالة المصادقة',
        'Connected!' => 'متصل!',
        'Connected as: %s' => 'متصل كـ: %s',
        'Refresh token saved.' => 'رمز التحديث محفوظ.',
        'Test Connection' => 'فحص الاتصال',
        'You must authenticate with your Google account to enable uploading.' => 'يجب عليك المصادقة مع حساب جوجل الخاص بك لتمكين الرفع.',
        'Authenticate with Google' => 'المصادقة مع جوجل',
        'Please save the Client ID and Client Secret to proceed with authentication.' => 'يرجى حفظ معرف العميل وسر العميل للمتابعة مع المصادقة.',
        'Notes' => 'الملاحظات',
        'Write your notes here...' => 'اكتب ملاحظاتك هنا...',
        'Save' => 'حفظ',
        'Close' => 'إغلاق',
        'Not authenticated. Please click "Authenticate with Google" in settings.' => 'غير مصادق. يرجى الضغط على "المصادقة مع جوجل" في الإعدادات.',
        'Client ID or Secret missing.' => 'معرف العميل أو السر مفقود.',
        'No refresh token returned. Revoke access and try again.' => 'لم يتم إرجاع رمز التحديث. قم بإلغاء الوصول والمحاولة مرة أخرى.',
        'Invalid or missing credentials' => 'بيانات اعتماد غير صالحة أو مفقودة',
        'Root Folder ID is missing' => 'معرف المجلد الرئيسي مفقود',
        'Drive Error: %s' => 'خطأ في الدرايف: %s',
        'Root Folder ID is missing in settings' => 'معرف المجلد الرئيسي مفقود في الإعدادات',
        'Google Drive service not initialized' => 'خدمة جوجل درايف لم يتم تهيئتها',
        'Upload failed' => 'فشل الرفع',
        'Unauthorized access' => 'غير مصرح لك بالوصول',
        'Missing information' => 'معلومات ناقصة',
        'No file was uploaded' => 'لم يتم رفع أي ملف',
        'Only video files are allowed.' => 'مسموح بملفات الفيديو فقط.',
        'Failed to create folder structure on Google Drive' => 'فشل في إنشاء هيكل المجلدات على جوجل درايف',
        'Uploaded successfully' => 'تم الرفع بنجاح',
        'Unauthorized to perform this action' => 'غير مصرح لك باتخاذ هذا الإجراء',
        'Invalid data' => 'بيانات غير صالحة',
        'Status updated successfully' => 'تم تحديث الحالة بنجاح',
        'Sync completed. Found %d matches out of %d lessons.' => 'اكتمل التزامن. تم العثور على %d تطابقاً من أصل %d درساً.',
        'Settings saved' => 'تم حفظ الإعدادات',
        'Connection successful! Root folder: %s' => 'تم الاتصال بنجاح! المجلد الرئيسي: %s',
        'Are you sure you want to delete this record?' => 'هل أنت متأكد من حذف هذا السجل؟',
        'Uploading...' => 'جاري الرفع...',
        'Something went wrong' => 'حدث خطأ ما',
        'Please select all filters first.' => 'يرجى اختيار جميع الفلاتر أولاً.',
        'Syncing...' => 'جاري التزامن...',
        'No curriculum found for these filters.' => 'لم يتم العثور على مناهج لهذه الفلاتر.',
        'No Video' => 'لا يوجد فيديو',
        'View' => 'عرض',
        'Replace' => 'تبديل',
        'Testing...' => 'جاري الفحص...',
        'No logs found.' => 'لم يتم العثور على سجلات.',
        'View on Drive' => 'عرض على الدرايف',
        'Rejected' => 'مرفوض',
        'Uploader' => 'الرافع',
        'Reject' => 'رفض',
        'Part' => 'جزء',
        'Lesson' => 'درس',
        'Family Number Lookup' => 'البحث عن رقم العائلة',
        'Find Your Family Number' => 'اعرف رقم عائلتك',
        'Enter your name to retrieve your portal access number' => 'أدخل اسمك لاسترجاع رقم الدخول الخاص بك',
        'Parent Name' => 'اسم ولي الأمر',
        'Start typing your name...' => 'ابدأ بكتابة اسمك...',
        'Your Family Number is:' => 'رقم عائلتك هو:',
        'Copy Number' => 'نسخ الرقم',
        'Copied!' => 'تم النسخ!',
        'Search term too short' => 'كلمة البحث قصيرة جداً',
        // ── KG الروضة ────────────────────────────────────────────────
        'KG الروضة'                                          => 'الروضة',
        'Manage and track KG sessions'                       => 'إدارة متابعة جلسات الروضة والتصوير والتخرج',

        // Tabs
        'Photo Session'                                      => 'جلسة التصوير',
        'Graduation Session'                                 => 'حفل التخرج',

        // Header badges
        'Academic Year'                                      => 'العام الدراسي',
        'Semester'                                           => 'الفصل الدراسي',

        // Filter
        'Selection Required'                                 => 'يرجى الاختيار',
        'Please select a grade and section above to manage students.' => 'الرجاء اختيار الصف والشعبة أعلاه لعرض الطلاب.',
        'No Students Found'                                  => 'لا يوجد طلاب',
        'There are no students enrolled in the selected section.' => 'لا يوجد طلاب مقيدون في هذه الشعبة.',

        // Table headers – photo
        'Attended Session'                                   => 'حضر الجلسة',
        'Photo Fees Collected'                               => 'تم تحصيل رسوم التصوير',
        'Received Photo'                                     => 'استلم الصور',
        'Last Updated'                                       => 'آخر تحديث',

        // Table headers – graduation
        'Participating'                                      => 'يشارك في حفل التخرج',
        'Fees'                                               => 'الرسوم',
        'Custom Fees'                                        => 'رسوم إضافية',

        // Bulk controls
        'Default Fees'                                       => 'الرسوم الافتراضية',
        'Default Custom Fees'                                => 'الرسوم الإضافية الافتراضية',
        'Apply to All'                                       => 'تطبيق على الجميع',
        'Apply Fees'                                         => 'تطبيق الرسوم',
        'Apply Custom'                                       => 'تطبيق الإضافية',
        'Apply'                                              => 'تطبيق',
        'Toggle All Attended'                                => 'تبديل حضور الجميع',
        'Toggle All Participating'                           => 'تبديل مشاركة الجميع',
        'Bulk Actions & Defaults'                            => 'الإجراءات الجماعية والقيم الافتراضية',
        'Save All Changes'                                   => 'حفظ جميع التغييرات',

        // Legacy / alternate keys still referenced elsewhere
        'Please select a grade and section to list students.' => 'يرجى اختيار الصف والشعبة لعرض الطلاب.',
        'No students found in this section.'                 => 'لا يوجد طلاب في هذه الشعبة.',
        'Error: No active semester found.'                   => 'خطأ: لا يوجد فصل دراسي نشط.',
        'Saved successfully'                                 => 'تم الحفظ بنجاح',

        );

        return $map[$text] ?? $text;
    }

    /**
     * Render Academic Year Selector
     */
    public static function academic_year_selector($selected_year_id, $disabled = false)
    {
        if ($disabled) {
            $years = Olama_School_Academic::get_years();
            $year_name = '—';
            foreach ($years as $year) {
                if ($year->id == $selected_year_id) {
                    $year_name = $year->year_name;
                    break;
                }
            }
            return self::locked_filter_render(self::translate('Academic Year'), $year_name, 'academic_year_id', $selected_year_id);
        }

        $years = Olama_School_Academic::get_years();
        if (empty($years))
            return '';

        ob_start();
        ?>
        <div style="flex: 1; min-width: 150px;">
            <label style="display: block; font-weight: 600; margin-bottom: 5px;">
                <?php echo self::translate('Academic Year'); ?>
            </label>
            <select name="academic_year_id" id="olama-academic-year-select" class="olama-select" onchange="this.form.submit()">
                <?php foreach ($years as $year): ?>
                    <option value="<?php echo $year->id; ?>" <?php selected($selected_year_id, $year->id); ?>>
                        <?php echo esc_html($year->year_name); ?>
                        <?php echo !empty($year->is_active) ? '(' . self::translate('Active') . ')' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Locked Filter (matches Weekly Plan style)
     */
    public static function locked_filter_render($label, $value, $name = '', $hidden_value = '', $id = '')
    {
        ob_start();
        ?>
        <div class="olama-filter-item" style="flex: 1; min-width: 150px;">
            <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php echo esc_html($label); ?></label>
            <?php if ($name && $hidden_value !== ''): ?>
                <input type="hidden" name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($id); ?>"
                    value="<?php echo esc_attr($hidden_value); ?>" />
            <?php endif; ?>
            <div
                style="padding: 8px 12px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; font-weight: 600; color: #475569; cursor: not-allowed; min-height: 18px;">
                <?php echo esc_html($value); ?>
                <span
                    style="font-size: 0.8em; color: #10b981; margin-right: 4px;">(<?php echo self::translate('Active'); ?>)</span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Normalize Arabic characters for better string matching
     */
    public static function normalize_arabic($string)
    {
        $string = trim($string);
        if (function_exists('mb_strtolower')) {
            $string = mb_strtolower($string, 'UTF-8');
        } else {
            $string = strtolower($string);
        }

        // Replace variations of Alif
        $string = preg_replace('/[أإآ]/u', 'ا', $string);

        // Replace Teh Marbuta with Heh
        $string = str_replace('ة', 'ه', $string);

        // Replace Alif Maksura with Yaa
        $string = str_replace('ى', 'ي', $string);

        // Remove Harakat (vowels/diacritics)
        $string = preg_replace('/[\x{064B}-\x{0652}]/u', '', $string);

        return $string;
    }

    /**
     * Aggressive normalization for matching (removes all spaces)
     */
    public static function normalize_for_match($string)
    {
        $string = self::normalize_arabic($string);
        // Remove all whitespace
        $string = preg_replace('/\s+/', '', $string);
        // Remove common punctuation/specials that might vary
        $string = str_replace(array('-', '_', '.', '(', ')', '[', ']'), '', $string);
        return $string;
    }

    /**
     * Map day names (Arabic/English) to canonical English
     */
    public static function get_day_translation($day)
    {
        $day = self::normalize_arabic($day);

        $map = array(
            'sunday' => 'Sunday',
            'الاحد' => 'Sunday',
            'monday' => 'Monday',
            'الاثنين' => 'Monday',
            'tuesday' => 'Tuesday',
            'الثلاثاء' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'الاربعاء' => 'Wednesday',
            'thursday' => 'Thursday',
            'الخميس' => 'Thursday',
            'friday' => 'Friday',
            'الجمعه' => 'Friday',
            'saturday' => 'Saturday',
            'السبت' => 'Saturday',
        );

        return $map[$day] ?? null;
    }

    /**
     * Get configured school days
     * 
     * @return array Array of English day names
     */
    public static function get_school_days()
    {
        $settings = get_option('olama_school_settings', array());
        $start_day = $settings['start_day'] ?? 'Sunday';
        $last_day = $settings['last_day'] ?? 'Thursday';

        $all_days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $start_idx = array_search($start_day, $all_days);
        $last_idx = array_search($last_day, $all_days);

        if ($start_idx === false)
            $start_idx = 0;
        if ($last_idx === false)
            $last_idx = 4;

        $display_days = [];
        if ($start_idx <= $last_idx) {
            for ($i = $start_idx; $i <= $last_idx; $i++) {
                $display_days[] = $all_days[$i];
            }
        } else {
            // Wraps around
            for ($i = $start_idx; $i < 7; $i++) {
                $display_days[] = $all_days[$i];
            }
            for ($i = 0; $i <= $last_idx; $i++) {
                $display_days[] = $all_days[$i];
            }
        }
        return $display_days;
    }
}