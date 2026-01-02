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
     * Get week date range from a given date
     * 
     * @param string $date Any date within the week
     * @return array Array with 'start' and 'end' keys
     */
    public static function get_week_range($date)
    {
        $ts = strtotime($date);
        $day_of_week = date('w', $ts);
        $week_start = date('Y-m-d', $ts - ($day_of_week * 86400));
        $week_end = date('Y-m-d', strtotime($week_start . ' +4 days'));

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
        if (current_user_can('manage_options')) {
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
     * Get Arabic translation for a string
     * 
     * @param string $text The English text
     * @return string Translated text if in Arabic mode, otherwise original
     */
    public static function translate($text)
    {
        if (!self::is_arabic()) {
            return $text;
        }

        static $map = array(
        'Olama School' => 'أكاديمية علماء المستقبل',
        'Dashboard' => 'لوحة القيادة',
        'Reports' => 'التقارير',
        'Weekly Plan Management' => 'إدارة الخطط الأسبوعية',
        'Academic Management' => 'الإدارة الأكاديمية',
        'Curriculum Management' => 'إدارة المناهج',
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
        'Plan Load' => 'تحميل الخطط',
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
        'Coordinator/Editor' => 'منسق/محرر',
        'Teacher' => 'معلم',
        'Author' => 'مؤلف',
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
        'Homework (WS)' => 'الواجب (ورقة عمل)',
        'Homework (Student Book)' => 'الواجب (كتاب الطالب)',
        'Homework (Exercise Book)' => 'الواجب (كتاب التمارين)',
        'Homework (Notebook)' => 'الواجب (الدفتر)',
        'Homework (Worksheet)' => 'الواجب (ورقة عمل)',
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
        'WS:' => 'ورقة عمل:',
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
        'Semester' => 'الفصل الدراسي',
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
        'Plan Load Management' => 'إدارة حمل الخطط الأسبوعية',
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
        'Plan Load settings saved successfully.' => 'تم حفظ إعدادات حمل الخطط بنجاح.',
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
        'Add Exam Subject' => 'إضافة مادة امتحان',
        'Evaluation/Assessment' => 'التقويم',
        'First Exam' => 'التقويم الاول',
        'Second Exam' => 'التقويم الثاني',
        'Final Exam' => 'الامتحان النهائي',
        'Choose the appropriate exam' => 'اختر التقويم المناسب',
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
        'Student Material' => 'مادة الطالب',
        'Workbook' => 'كتاب التدريب',
        'Exercise' => 'كتاب التمارين',
        'Notebook' => 'الدفتر',
        'Search by teacher name...' => 'البحث باسم المعلم...',
        'No teachers found.' => 'لم يتم العثور على معلمين.',
        );

        return $map[$text] ?? $text;
    }
}
