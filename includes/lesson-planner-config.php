<?php
/**
 * Lesson Planner Configuration
 * Centralized predefined enums for teaching strategies, assessment strategies,
 * assessment tools, lesson stages, and Bloom's taxonomy measurable verbs.
 * 
 * This is the single source of truth for both PHP and JS rendering.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Olama_Lesson_Planner_Config
{
    /**
     * Lesson Stages (مراحل الدرس)
     * Official Ministry structure: Preparation → Engagement → Explanation → Elaboration → Closing
     */
    public static function get_stages()
    {
        return array(
            'preparation' => array(
                'label_en' => 'Preparation',
                'label_ar' => 'التهيئة',
                'description_ar' => 'تنفذ في بداية الدرس لجذب انتباه الطلاب وربط الدرس بمعارفهم السابقة وتحفيز فضولهم.',
                'description_en' => 'Carried out at the beginning of the lesson to capture students\' attention and connect to prior knowledge.',
                'hints' => array(
                    'ar' => array('طرح أسئلة مثيرة للتفكير', 'عرض صورة أو فيديو قصير', 'نشاط عملي بسيط', 'قصة أو سيناريو واقعي'),
                    'en' => array('Thought-provoking questions', 'Picture or short video', 'Simple hands-on activity', 'Real-life story or scenario'),
                ),
            ),
            'engagement' => array(
                'label_en' => 'Engagement',
                'label_ar' => 'الانخراط',
                'description_ar' => 'يبدأ الطلاب بالتفاعل ذهنياً وعاطفياً مع المحتوى والمشاركة الفعّالة.',
                'description_en' => 'Students begin engaging mentally and emotionally with the content and start participating.',
                'hints' => array(
                    'ar' => array('مناقشة جماعية قصيرة', 'عصف ذهني حول الموضوع', 'نشاط تعاوني لاستنتاج العنوان', 'عرض وتحليل موقف واقعي'),
                    'en' => array('Short group discussion', 'Brainstorming the topic', 'Cooperative activity', 'Analyze a real-life situation'),
                ),
            ),
            'explanation' => array(
                'label_en' => 'Explanation & Interpretation',
                'label_ar' => 'الشرح والتفسير',
                'description_ar' => 'المرحلة الأساسية للدرس حيث يتم تقديم المحتوى وتفسيره وشرح المفاهيم والمهارات الجديدة.',
                'description_en' => 'The core of the lesson where content is presented, clarified, and new concepts/skills are explained.',
                'hints' => array(
                    'ar' => array('شرح مباشر مع أمثلة', 'استخدام الوسائط المتعددة', 'الربط بين المفاهيم القديمة والجديدة', 'أسئلة موجهة وحوار'),
                    'en' => array('Direct explanation with examples', 'Multimedia usage', 'Connect old and new concepts', 'Guided questions and dialogue'),
                ),
            ),
            'elaboration' => array(
                'label_en' => 'Elaboration & Differentiation',
                'label_ar' => 'التوسع والتمايز',
                'description_ar' => 'توسيع فهم الطلاب وتقديم أنشطة تمايز للمجموعات المختلفة (متفوقين، عاديين، ذوي صعوبات).',
                'description_en' => 'Expand understanding and provide differentiated activities for different learner groups.',
                'hints' => array(
                    'ar' => array('تعميق الفهم بالتدريب', 'ربط الدرس بمواضيع جديدة', 'أنشطة إثرائية للمتفوقين', 'أنشطة علاجية لذوي الصعوبات'),
                    'en' => array('Deepen understanding through practice', 'Link to new topics', 'Enrichment for advanced learners', 'Remedial activities for struggling learners'),
                ),
            ),
            'closing' => array(
                'label_en' => 'Closing',
                'label_ar' => 'إغلاق الدرس',
                'description_ar' => 'تلخيص الدرس والتأكد من تحقق نواتج التعلم وتقديم التغذية الراجعة وتهيئة الطلاب للدرس القادم.',
                'description_en' => 'Summarize the lesson, verify learning outcomes are achieved, provide feedback, and prepare for next lesson.',
                'hints' => array(
                    'ar' => array('تلخيص النقاط الرئيسية', 'سؤال ختامي أو نشاط تقييمي', 'ربط بالواجب المنزلي', 'تمهيد للدرس القادم'),
                    'en' => array('Summarize key points', 'Closing question or assessment activity', 'Link to homework', 'Preview of next lesson'),
                ),
            ),
        );
    }

    /**
     * Teaching Strategies (استراتيجيات التدريس)
     */
    public static function get_teaching_strategies()
    {
        return array(
            'direct_teaching' => array('en' => 'Direct Teaching', 'ar' => 'التدريس المباشر'),
            'role_play' => array('en' => 'Role Play', 'ar' => 'لعب الأدوار'),
            'cooperative_learning' => array('en' => 'Cooperative Learning', 'ar' => 'التعلم التعاوني'),
            'active_learning' => array('en' => 'Active Learning', 'ar' => 'التعلم النشط'),
            'critical_thinking' => array('en' => 'Critical Thinking', 'ar' => 'التفكير الناقد'),
            'problem_solving' => array('en' => 'Problem Solving', 'ar' => 'حل المشكلات'),
            'brainstorming' => array('en' => 'Brainstorming', 'ar' => 'العصف الذهني'),
            'discussion' => array('en' => 'Discussion & Dialogue', 'ar' => 'المناقشة والحوار'),
        );
    }

    /**
     * Assessment Strategies (استراتيجيات التقويم)
     */
    public static function get_assessment_strategies()
    {
        return array(
            'performance_based' => array('en' => 'Performance-based', 'ar' => 'التقويم المعتمد على الأداء'),
            'pen_and_paper' => array('en' => 'Pen & Paper', 'ar' => 'القلم والورقة'),
            'observation' => array('en' => 'Observation', 'ar' => 'الملاحظة'),
            'communication' => array('en' => 'Communication', 'ar' => 'التواصل'),
            'self_assessment' => array('en' => 'Self-Assessment', 'ar' => 'التقويم الذاتي'),
        );
    }

    /**
     * Assessment Tools (أدوات التقويم)
     */
    public static function get_assessment_tools()
    {
        return array(
            'observation_checklist' => array('en' => 'Observation Checklist', 'ar' => 'قائمة الرصد'),
            'rating_scale' => array('en' => 'Rating Scale', 'ar' => 'سلم التقدير'),
            'rubric' => array('en' => 'Rubric', 'ar' => 'سلم التقدير اللفظي'),
            'learning_log' => array('en' => 'Learning Log', 'ar' => 'سجل وصف سير التعلم'),
            'anecdotal_record' => array('en' => 'Anecdotal Record', 'ar' => 'السجل القصصي'),
        );
    }

    /**
     * Strategy → Tool alignment hints
     * When a teacher selects an assessment strategy, suggest compatible tools
     */
    public static function get_strategy_tool_alignment()
    {
        return array(
            'performance_based' => array('rubric', 'rating_scale'),
            'pen_and_paper' => array('rating_scale', 'rubric'),
            'observation' => array('observation_checklist', 'anecdotal_record'),
            'communication' => array('learning_log', 'anecdotal_record'),
            'self_assessment' => array('learning_log', 'rubric'),
        );
    }

    /**
     * Bloom's Taxonomy Measurable Verbs (أفعال قابلة للقياس)
     * Organized by cognitive level, used in Learning Outcomes builder
     */
    public static function get_blooms_verbs()
    {
        return array(
            'remember' => array(
                'label_en' => 'Remember',
                'label_ar' => 'التذكر',
                'verbs' => array(
                    array('en' => 'List', 'ar' => 'يعدد'),
                    array('en' => 'Identify', 'ar' => 'يحدد'),
                    array('en' => 'Name', 'ar' => 'يسمّي'),
                    array('en' => 'Define', 'ar' => 'يعرّف'),
                    array('en' => 'Recall', 'ar' => 'يستذكر'),
                    array('en' => 'State', 'ar' => 'يذكر'),
                    array('en' => 'Describe', 'ar' => 'يصف'),
                ),
            ),
            'understand' => array(
                'label_en' => 'Understand',
                'label_ar' => 'الفهم',
                'verbs' => array(
                    array('en' => 'Explain', 'ar' => 'يشرح'),
                    array('en' => 'Summarize', 'ar' => 'يلخص'),
                    array('en' => 'Interpret', 'ar' => 'يفسر'),
                    array('en' => 'Compare', 'ar' => 'يقارن'),
                    array('en' => 'Classify', 'ar' => 'يصنف'),
                    array('en' => 'Illustrate', 'ar' => 'يوضح'),
                    array('en' => 'Distinguish', 'ar' => 'يميز'),
                ),
            ),
            'apply' => array(
                'label_en' => 'Apply',
                'label_ar' => 'التطبيق',
                'verbs' => array(
                    array('en' => 'Apply', 'ar' => 'يطبق'),
                    array('en' => 'Solve', 'ar' => 'يحل'),
                    array('en' => 'Use', 'ar' => 'يستخدم'),
                    array('en' => 'Execute', 'ar' => 'ينفذ'),
                    array('en' => 'Demonstrate', 'ar' => 'يوظف'),
                    array('en' => 'Calculate', 'ar' => 'يحسب'),
                ),
            ),
            'analyze' => array(
                'label_en' => 'Analyze',
                'label_ar' => 'التحليل',
                'verbs' => array(
                    array('en' => 'Analyze', 'ar' => 'يحلل'),
                    array('en' => 'Differentiate', 'ar' => 'يفرق'),
                    array('en' => 'Organize', 'ar' => 'ينظم'),
                    array('en' => 'Deconstruct', 'ar' => 'يفكك'),
                    array('en' => 'Examine', 'ar' => 'يفحص'),
                    array('en' => 'Deduce', 'ar' => 'يستنتج'),
                ),
            ),
            'evaluate' => array(
                'label_en' => 'Evaluate',
                'label_ar' => 'التقويم',
                'verbs' => array(
                    array('en' => 'Evaluate', 'ar' => 'يقيّم'),
                    array('en' => 'Judge', 'ar' => 'يحكم'),
                    array('en' => 'Justify', 'ar' => 'يبرر'),
                    array('en' => 'Critique', 'ar' => 'ينتقد'),
                    array('en' => 'Assess', 'ar' => 'يقدّر'),
                    array('en' => 'Defend', 'ar' => 'يدافع'),
                ),
            ),
            'create' => array(
                'label_en' => 'Create',
                'label_ar' => 'الإبداع',
                'verbs' => array(
                    array('en' => 'Design', 'ar' => 'يصمم'),
                    array('en' => 'Create', 'ar' => 'يبتكر'),
                    array('en' => 'Compose', 'ar' => 'يؤلف'),
                    array('en' => 'Formulate', 'ar' => 'يصوغ'),
                    array('en' => 'Plan', 'ar' => 'يخطط'),
                    array('en' => 'Produce', 'ar' => 'ينتج'),
                ),
            ),
        );
    }

    /**
     * Compliance scoring weights
     */
    public static function get_compliance_weights()
    {
        return array(
            'outcomes_with_verb' => 15,  // ≥1 outcome with verb + content + level
            'stages_teacher_action' => 20,  // All 5 stages have teacher_action
            'stages_learner_action' => 15,  // All 5 stages have learner_action
            'time_distribution' => 10,  // Time sums to classes × 45
            'teaching_strategy' => 10,  // Teaching strategy selected per stage
            'assessment_strategy' => 10,  // Assessment strategy selected per stage
            'assessment_tool' => 5,   // Assessment tool selected per stage
            'resources' => 5,   // Resources not empty
            'self_reflection' => 5,   // Self-reflection not empty
            'homework' => 5,   // Homework not empty
        );
    }

    /**
     * Get all config as JSON for JavaScript consumption
     */
    public static function get_js_config()
    {
        $lang = Olama_School_Helpers::is_arabic() ? 'ar' : 'en';

        // Flatten strategies/tools to key → label for JS
        $flatten = function ($items) use ($lang) {
            $result = array();
            foreach ($items as $key => $item) {
                $result[$key] = $item[$lang];
            }
            return $result;
        };

        // Flatten stages
        $stages = array();
        foreach (self::get_stages() as $key => $stage) {
            $stages[$key] = array(
                'label' => $stage['label_' . $lang],
                'description' => $stage['description_' . $lang],
                'hints' => $stage['hints'][$lang],
            );
        }

        // Flatten Bloom's verbs
        $verbs = array();
        foreach (self::get_blooms_verbs() as $level_key => $level) {
            $group = array(
                'label' => $level['label_' . $lang],
                'verbs' => array(),
            );
            foreach ($level['verbs'] as $verb) {
                $group['verbs'][] = $verb[$lang];
            }
            $verbs[$level_key] = $group;
        }

        return array(
            'stages' => $stages,
            'teaching_strategies' => $flatten(self::get_teaching_strategies()),
            'assessment_strategies' => $flatten(self::get_assessment_strategies()),
            'assessment_tools' => $flatten(self::get_assessment_tools()),
            'strategy_tool_alignment' => self::get_strategy_tool_alignment(),
            'blooms_verbs' => $verbs,
            'compliance_weights' => self::get_compliance_weights(),
            'class_duration_minutes' => 45,
        );
    }
}
