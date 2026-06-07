<?php
/**
 * Database Schema Class
 */

if (!defined('ABSPATH')) {
	exit;
}

class Olama_School_DB
{
	/**
	 * Get all tables managed by this plugin
	 */
	public static function get_tables()
	{
		return array(
			'olama_settings',
			'olama_academic_years',
			'olama_semesters',
			'olama_grades',
			'olama_sections',
			'olama_subjects',
			'olama_teachers',
			'olama_families',
			'olama_students',
			'olama_student_enrollment',
			'olama_plans',
			'olama_plan_questions',
			'olama_templates',
			'olama_schedule',
			'olama_curriculum_units',
			'olama_curriculum_lessons',
			'olama_curriculum_questions',
			'olama_logs',
			'olama_transport_buses',
			'olama_student_bus_assignments',
			'olama_academic_events',
			'olama_teacher_assignments',
			'olama_teacher_office_hours',
			'olama_exams',
			'olama_user_preferences',
			'olama_notifications',
			'olama_ev_templates',
			'olama_ev_domains',
			'olama_ev_categories',
			'olama_ev_indicators',
			'olama_ev_records',
			'olama_ev_scores',
			'olama_semester_exams',
			'olama_stationary',
			'olama_exam_attachments',
			'olama_attendance',
			'olama_attendance_sheets',
			'olama_shifts_locations',
			'olama_shifts_time_slots',
			'olama_shifts_schedule',
			'olama_shifts_periods',
			'olama_shifts',
			'olama_shifts_assignments',
			'olama_lesson_plans',
			'olama_supervisor_visits',
			'olama_supervisor_assignments',
			'olama_cleaning_logs',
			'olama_cleaning_items',
			'olama_cleaning_floors',
			'olama_cleaning_cleaners',
			'olama_cleaning_slots',
			'olama_cleaning_assignments',
			'olama_kg_photo_session',
			'olama_kg_graduation_session',
			'olama_exam_halls',
			'olama_exam_hall_assignments',
			'olama_exam_hall_attendance',
			'olama_exam_hall_notes',
			'olama_exam_hall_invigilators',
			'olama_system_logs'
		);
	}

	/**
	 * Create database tables
	 * 
	 */
	public function create_tables()
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$tables = array(
			'olama_settings' => "CREATE TABLE {$wpdb->prefix}olama_settings (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				setting_name varchar(100) NOT NULL,
				setting_value longtext NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY  setting_name (setting_name)
			) $charset_collate;",

			'olama_academic_years' => "CREATE TABLE {$wpdb->prefix}olama_academic_years (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				year_name varchar(50) NOT NULL,
				start_date date NOT NULL,
				end_date date NOT NULL,
				is_active tinyint(1) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_semesters' => "CREATE TABLE {$wpdb->prefix}olama_semesters (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				semester_name varchar(50) NOT NULL,
				start_date date NOT NULL,
				end_date date NOT NULL,
				is_active tinyint(1) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id)
			) $charset_collate;",

			'olama_grades' => "CREATE TABLE {$wpdb->prefix}olama_grades (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				grade_name varchar(50) NOT NULL,
				grade_level varchar(20) NOT NULL,
				periods_count tinyint(4) DEFAULT 8 NOT NULL,
				max_weekly_plans tinyint(4) DEFAULT 0 NOT NULL,
				max_sun tinyint(4) DEFAULT 0 NOT NULL,
				max_mon tinyint(4) DEFAULT 0 NOT NULL,
				max_tue tinyint(4) DEFAULT 0 NOT NULL,
				max_wed tinyint(4) DEFAULT 0 NOT NULL,
				max_thu tinyint(4) DEFAULT 0 NOT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_sections' => "CREATE TABLE {$wpdb->prefix}olama_sections (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				grade_id mediumint(9) NOT NULL,
				section_name varchar(50) NOT NULL,
				room_number varchar(20) DEFAULT NULL,
				homeroom_teacher_id bigint(20) UNSIGNED DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id),
				KEY  grade_id (grade_id)
			) $charset_collate;",

			'olama_subjects' => "CREATE TABLE {$wpdb->prefix}olama_subjects (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				subject_name varchar(100) NOT NULL,
				subject_code varchar(20) DEFAULT NULL,
				grade_id mediumint(9) NOT NULL,
				color_code varchar(7) DEFAULT NULL,
				max_weekly_plans tinyint(4) DEFAULT 0 NOT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				PRIMARY KEY  (id),
				KEY  grade_id (grade_id)
			) $charset_collate;",

			'olama_teachers' => "CREATE TABLE {$wpdb->prefix}olama_teachers (
				id bigint(20) UNSIGNED NOT NULL,
				employee_id varchar(50) DEFAULT NULL,
				phone_number varchar(20) DEFAULT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_families' => "CREATE TABLE {$wpdb->prefix}olama_families (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				family_uid varchar(50) NOT NULL,
				family_name varchar(255) NOT NULL,
				father_first_name varchar(100) DEFAULT NULL,
				father_second_name varchar(100) DEFAULT NULL,
				father_third_name varchar(100) DEFAULT NULL,
				father_family_name varchar(100) DEFAULT NULL,
				father_nationality varchar(50) DEFAULT NULL,
				father_job varchar(100) DEFAULT NULL,
				father_workplace varchar(100) DEFAULT NULL,
				father_mobile varchar(20) DEFAULT NULL,
				father_email varchar(100) DEFAULT NULL,
				mother_full_name varchar(255) DEFAULT NULL,
				mother_nationality varchar(50) DEFAULT NULL,
				mother_mobile varchar(20) DEFAULT NULL,
				mother_email varchar(100) DEFAULT NULL,
				residential_area varchar(100) DEFAULT NULL,
				home_address text DEFAULT NULL,
				building_number varchar(50) DEFAULT NULL,
				apartment_number varchar(50) DEFAULT NULL,
				home_phone varchar(50) DEFAULT NULL,
				address text DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY family_uid (family_uid)
			) $charset_collate;",

			'olama_students' => "CREATE TABLE {$wpdb->prefix}olama_students (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				student_name varchar(100) NOT NULL,
				student_uid varchar(50) NOT NULL,
				family_id varchar(50) DEFAULT NULL,
				dob date DEFAULT NULL,
				national_id varchar(50) DEFAULT NULL,
				gender varchar(20) DEFAULT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				PRIMARY KEY  (id),
				KEY student_uid (student_uid),
				KEY family_id (family_id)
			) $charset_collate;",

			'olama_student_enrollment' => "CREATE TABLE {$wpdb->prefix}olama_student_enrollment (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				student_id mediumint(9) NOT NULL,
				student_uid varchar(50) DEFAULT NULL,
				academic_year_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				enrollment_date date DEFAULT NULL,
				status varchar(20) DEFAULT 'active' NOT NULL,
				PRIMARY KEY  (id),
				KEY student_id (student_id),
				KEY student_uid (student_uid),
				KEY academic_year_id (academic_year_id),
				KEY section_id (section_id)
			) $charset_collate;",

			'olama_plans' => "CREATE TABLE {$wpdb->prefix}olama_plans (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				plan_date date NOT NULL,
				period_number tinyint(4) NOT NULL,
				unit_id mediumint(9) DEFAULT NULL,
				lesson_id mediumint(9) DEFAULT NULL,
				curriculum_id mediumint(9) DEFAULT NULL,
				custom_topic text DEFAULT NULL,
				homework_sb varchar(255) DEFAULT NULL,
				homework_eb varchar(255) DEFAULT NULL,
				homework_nb text DEFAULT NULL,
				homework_ws text DEFAULT NULL,
				teacher_notes text DEFAULT NULL,
				supervisor_feedback text DEFAULT NULL,
				teacher_response text DEFAULT NULL,
				rating tinyint(4) DEFAULT 0 NOT NULL,
				plan_type varchar(20) DEFAULT 'homework' NOT NULL,
				status varchar(20) DEFAULT 'draft' NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  year_semester (academic_year_id,semester_id),
				KEY  section_date (section_id,plan_date),
				KEY  plan_lookup (academic_year_id,section_id,plan_date),
				KEY  subject_id (subject_id),
				KEY  teacher_id (teacher_id),
				KEY  section_subject_date (section_id,subject_id,plan_date)
			) $charset_collate;",

			'olama_plan_questions' => "CREATE TABLE {$wpdb->prefix}olama_plan_questions (
				plan_id mediumint(9) NOT NULL,
				question_id mediumint(9) NOT NULL,
				PRIMARY KEY  (plan_id,question_id)
			) $charset_collate;",

			'olama_templates' => "CREATE TABLE {$wpdb->prefix}olama_templates (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				template_name varchar(100) NOT NULL,
				grade_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				template_data longtext NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY teacher_subject (teacher_id, subject_id)
			) $charset_collate;",

			'olama_schedule' => "CREATE TABLE {$wpdb->prefix}olama_schedule (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				semester_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				day_name varchar(20) NOT NULL,
				period_number tinyint(4) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				schedule_type varchar(20) DEFAULT 'normal' NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY  schedule_slot (semester_id,section_id,day_name,period_number,schedule_type)
			) $charset_collate;",

			'olama_curriculum_units' => "CREATE TABLE {$wpdb->prefix}olama_curriculum_units (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				grade_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				unit_number varchar(10) NOT NULL,
				unit_name varchar(255) NOT NULL,
				objectives text DEFAULT NULL,
				start_date date DEFAULT NULL,
				end_date date DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY  unit_lookup (grade_id,subject_id,semester_id)
			) $charset_collate;",

			'olama_curriculum_lessons' => "CREATE TABLE {$wpdb->prefix}olama_curriculum_lessons (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				unit_id mediumint(9) NOT NULL,
				lesson_number varchar(10) DEFAULT NULL,
				lesson_title text NOT NULL,
				video_url varchar(255) DEFAULT NULL,
				periods tinyint(4) DEFAULT 1 NOT NULL,
				start_date date DEFAULT NULL,
				end_date date DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY  unit_id (unit_id),
				KEY  lesson_dates (start_date, end_date)
			) $charset_collate;",

			'olama_curriculum_questions' => "CREATE TABLE {$wpdb->prefix}olama_curriculum_questions (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				lesson_id mediumint(9) NOT NULL,
				question_number varchar(10) DEFAULT NULL,
				question text NOT NULL,
				answer text DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY  lesson_id (lesson_id)
			) $charset_collate;",

			'olama_logs' => "CREATE TABLE {$wpdb->prefix}olama_logs (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id bigint(20) UNSIGNED NOT NULL,
				action varchar(255) NOT NULL,
				details text DEFAULT NULL,
				ip_address varchar(45) DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  user_id (user_id),
				KEY  created_at (created_at)
			) $charset_collate;",

			'olama_system_logs' => "CREATE TABLE {$wpdb->prefix}olama_system_logs (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				level varchar(10) NOT NULL DEFAULT 'info',
				source varchar(50) NOT NULL DEFAULT 'school',
				message text NOT NULL,
				context longtext DEFAULT NULL,
				user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  source (source),
				KEY  level (level),
				KEY  created_at (created_at),
				KEY  source_level (source, level)
			) $charset_collate;",

			'olama_transport_buses' => "CREATE TABLE {$wpdb->prefix}olama_transport_buses (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				bus_number varchar(50) NOT NULL,
				plate_number varchar(50) NOT NULL,
				passenger_capacity tinyint(4) NOT NULL,
				driver_user_id bigint(20) UNSIGNED DEFAULT NULL,
				companion_user_id bigint(20) UNSIGNED DEFAULT NULL,
				license_expiry_date date DEFAULT NULL,
				engine_capacity varchar(50) DEFAULT NULL,
				fuel_type varchar(50) DEFAULT NULL,
				status varchar(20) DEFAULT 'active' NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY plate_number (plate_number)
			) $charset_collate;",

			'olama_student_bus_assignments' => "CREATE TABLE {$wpdb->prefix}olama_student_bus_assignments (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				student_id mediumint(9) NOT NULL,
				student_uid varchar(50) DEFAULT NULL,
				bus_id mediumint(9) NOT NULL,
				academic_year_id mediumint(9) NOT NULL,
				pickup_location varchar(255) DEFAULT NULL,
				dropoff_location varchar(255) DEFAULT NULL,
				notes text DEFAULT NULL,
				assigned_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				assigned_by bigint(20) UNSIGNED NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY student_uid_year (student_uid, academic_year_id),
				KEY student_uid (student_uid),
				KEY bus_id (bus_id),
				KEY academic_year_id (academic_year_id)
			) $charset_collate;",

			'olama_academic_events' => "CREATE TABLE {$wpdb->prefix}olama_academic_events (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				event_description text NOT NULL,
				start_date date NOT NULL,
				end_date date NOT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id)
			) $charset_collate;",

			'olama_teacher_assignments' => "CREATE TABLE {$wpdb->prefix}olama_teacher_assignments (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				grade_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id),
				KEY  teacher_id (teacher_id),
				KEY  section_id (section_id),
				KEY  assignment (teacher_id, section_id, subject_id),
				KEY  assignment_full (academic_year_id, grade_id, section_id)
			) $charset_collate;",

			'olama_teacher_office_hours' => "CREATE TABLE {$wpdb->prefix}olama_teacher_office_hours (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				day_name varchar(20) NOT NULL,
				available_time text NOT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id),
				KEY  semester_id (semester_id),
				KEY  teacher_id (teacher_id)
			) $charset_collate;",

			'olama_exams' => "CREATE TABLE {$wpdb->prefix}olama_exams (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				semester_exam_id mediumint(9) DEFAULT NULL,
				grade_id mediumint(9) NOT NULL,
				subject_id mediumint(9) NOT NULL,
				evaluation_type varchar(50) NOT NULL,
				exam_date date NOT NULL,
				room_number varchar(50) DEFAULT NULL,
				description text DEFAULT NULL,
				student_book_material text DEFAULT NULL,
				workbook_material text DEFAULT NULL,
				exercise_book_material text DEFAULT NULL,
				notebook_material text DEFAULT NULL,
				teacher_notes text DEFAULT NULL,
				exam_material_json longtext DEFAULT NULL,
				status varchar(20) DEFAULT 'draft' NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  year_semester (academic_year_id,semester_id),
				KEY  grade_subject (grade_id,subject_id),
				KEY  semester_exam_id (semester_exam_id),
				KEY  exam_tracking (grade_id, subject_id, status)
			) $charset_collate;",

			'olama_user_preferences' => "CREATE TABLE {$wpdb->prefix}olama_user_preferences (
				user_id bigint(20) UNSIGNED NOT NULL,
				preference_key varchar(100) NOT NULL,
				preference_value longtext,
				PRIMARY KEY (user_id, preference_key)
			) $charset_collate;",

			'olama_notifications' => "CREATE TABLE {$wpdb->prefix}olama_notifications (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id bigint(20) UNSIGNED NOT NULL,
				notification_type varchar(50) NOT NULL,
				message text NOT NULL,
				is_read tinyint(1) DEFAULT 0,
				created_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY is_read (is_read)
			) $charset_collate;",

			'olama_ev_templates' => "CREATE TABLE {$wpdb->prefix}olama_ev_templates (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				grade_id mediumint(9) NOT NULL,
				subject_id mediumint(9) DEFAULT NULL,
				semester_id mediumint(9) DEFAULT NULL,
				template_name varchar(255) NOT NULL,
				context_type varchar(50) DEFAULT 'student' NOT NULL,
				score_config longtext DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY year_grade (academic_year_id, grade_id),
				KEY subject_id (subject_id),
				KEY context_type (context_type)
			) $charset_collate;",

			'olama_ev_domains' => "CREATE TABLE {$wpdb->prefix}olama_ev_domains (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				template_id mediumint(9) NOT NULL,
				grade_id mediumint(9) DEFAULT NULL,
				title_ar varchar(255) NOT NULL,
				context_type varchar(50) DEFAULT 'student' NOT NULL,
				sort_order int(11) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id),
				KEY template_id (template_id),
				KEY context_type (context_type)
			) $charset_collate;",

			'olama_ev_categories' => "CREATE TABLE {$wpdb->prefix}olama_ev_categories (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				domain_id mediumint(9) NOT NULL,
				title_ar varchar(255) NOT NULL,
				sort_order int(11) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id),
				KEY domain_id (domain_id)
			) $charset_collate;",

			'olama_ev_indicators' => "CREATE TABLE {$wpdb->prefix}olama_ev_indicators (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				category_id mediumint(9) NOT NULL,
				indicator_text text NOT NULL,
				max_score int(11) DEFAULT 5 NOT NULL,
				weight decimal(5,2) DEFAULT 1.00 NOT NULL,
				is_critical boolean DEFAULT 0 NOT NULL,
				context_type varchar(50) DEFAULT 'student' NOT NULL,
				sort_order int(11) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id),
				KEY category_id (category_id),
				KEY context_type (context_type)
			) $charset_collate;",

			'olama_ev_records' => "CREATE TABLE {$wpdb->prefix}olama_ev_records (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				template_id mediumint(9) NOT NULL,
				student_id mediumint(9) DEFAULT NULL,
				student_uid varchar(50) DEFAULT NULL,
				subject_id mediumint(9) DEFAULT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				context_type varchar(50) DEFAULT 'student' NOT NULL,
				related_entity_type varchar(50) DEFAULT NULL,
				related_entity_id bigint(20) DEFAULT NULL,
				status varchar(20) DEFAULT 'draft' NOT NULL,
				supervisor_comments text DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY template_id (template_id),
				KEY student_id (student_id),
				KEY student_uid (student_uid),
				KEY subject_id (subject_id),
				KEY teacher_id (teacher_id),
				KEY year_semester (academic_year_id, semester_id),
				KEY context_type (context_type)
			) $charset_collate;",

			'olama_ev_scores' => "CREATE TABLE {$wpdb->prefix}olama_ev_scores (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				evaluation_id mediumint(9) NOT NULL,
				indicator_id mediumint(9) NOT NULL,
				score tinyint(1) DEFAULT NULL,
				calculated_score decimal(5,2) DEFAULT NULL,
				notes text DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY evaluation_id (evaluation_id),
				KEY indicator_id (indicator_id)
			) $charset_collate;",

			'olama_semester_exams' => "CREATE TABLE {$wpdb->prefix}olama_semester_exams (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				semester_id mediumint(9) NOT NULL,
				grade_id mediumint(9) DEFAULT NULL,
				exam_name varchar(100) NOT NULL,
				room_number varchar(50) DEFAULT NULL,
				start_date date NOT NULL,
				end_date date NOT NULL,
				is_active tinyint(1) DEFAULT 0 NOT NULL,
				PRIMARY KEY  (id),
				KEY  semester_id (semester_id),
				KEY  grade_id (grade_id)
			) $charset_collate;",

			'olama_stationary' => "CREATE TABLE {$wpdb->prefix}olama_stationary (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				grade_id mediumint(9) NOT NULL,
				notebooks text DEFAULT NULL,
				stationary text DEFAULT NULL,
				teacher_notes text DEFAULT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY grade_year (academic_year_id, grade_id)
			) $charset_collate;",

			'olama_exam_attachments' => "CREATE TABLE {$wpdb->prefix}olama_exam_attachments (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				exam_id mediumint(9) NOT NULL,
				user_id bigint(20) UNSIGNED NOT NULL,
				original_filename varchar(255) NOT NULL,
				stored_filename varchar(255) NOT NULL,
				file_size bigint(20) NOT NULL,
				file_hash varchar(64) NOT NULL,
				file_status varchar(20) DEFAULT 'uploaded' NOT NULL,
				supervisor_comments text DEFAULT NULL,
				uploaded_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  exam_id (exam_id),
				KEY  user_id (user_id)
			) $charset_collate;",

			'olama_attendance' => "CREATE TABLE {$wpdb->prefix}olama_attendance (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				student_id mediumint(9) NOT NULL,
				student_uid varchar(50) DEFAULT NULL,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				attendance_date date NOT NULL,
				status varchar(20) DEFAULT 'present' NOT NULL,
				reason text DEFAULT NULL,
				recorded_by bigint(20) UNSIGNED DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY student_uid_date (student_uid, attendance_date),
				KEY student_id (student_id),
				KEY student_uid (student_uid),
				KEY academic_year_id (academic_year_id),
				KEY semester_id (semester_id),
				KEY section_id (section_id),
				KEY attendance_date (attendance_date)
			) $charset_collate;",

			'olama_attendance_sheets' => "CREATE TABLE {$wpdb->prefix}olama_attendance_sheets (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				attendance_date date NOT NULL,
				recorded_by bigint(20) UNSIGNED DEFAULT NULL,
				status varchar(20) DEFAULT 'completed' NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY section_date (section_id, attendance_date),
				KEY academic_year_id (academic_year_id),
				KEY attendance_date (attendance_date)
			) $charset_collate;",

			'olama_shifts_locations' => "CREATE TABLE {$wpdb->prefix}olama_shifts_locations (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				location_name varchar(100) NOT NULL,
				area_floor varchar(100) DEFAULT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_shifts_time_slots' => "CREATE TABLE {$wpdb->prefix}olama_shifts_time_slots (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				slot_label varchar(100) NOT NULL,
				start_time time NOT NULL,
				end_time time NOT NULL,
				gender_focus varchar(20) DEFAULT 'mixed' NOT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_shifts_schedule' => "CREATE TABLE {$wpdb->prefix}olama_shifts_schedule (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				location_id mediumint(9) NOT NULL,
				day_of_week tinyint(4) NOT NULL,
				slot_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				academic_year_id mediumint(9) NOT NULL,
				PRIMARY KEY  (id),
				KEY teacher_id (teacher_id),
				KEY location_id (location_id),
				KEY slot_id (slot_id),
				KEY semester_year (semester_id, academic_year_id)
			) $charset_collate;",

			'olama_shifts_periods' => "CREATE TABLE {$wpdb->prefix}olama_shifts_periods (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				shift_type varchar(50) NOT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				PRIMARY KEY  (id),
				KEY academic_year_id (academic_year_id),
				KEY semester_id (semester_id)
			) $charset_collate;",

			'olama_shifts' => "CREATE TABLE {$wpdb->prefix}olama_shifts (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				period_id mediumint(9) NOT NULL,
				day_of_week tinyint(4) NOT NULL,
				slot_id mediumint(9) NOT NULL,
				location_id mediumint(9) NOT NULL,
				PRIMARY KEY  (id),
				KEY period_id (period_id),
				KEY slot_id (slot_id),
				KEY location_id (location_id)
			) $charset_collate;",

			'olama_shifts_assignments' => "CREATE TABLE {$wpdb->prefix}olama_shifts_assignments (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				shift_id bigint(20) NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				role varchar(50) DEFAULT 'primary' NOT NULL,
				PRIMARY KEY  (id),
				KEY shift_id (shift_id),
				KEY teacher_id (teacher_id)
			) $charset_collate;",

			'olama_lesson_plans' => "CREATE TABLE {$wpdb->prefix}olama_lesson_plans (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				teacher_id bigint(20) UNSIGNED NOT NULL,
				subject_id mediumint(9) NOT NULL,
				grade_id mediumint(9) NOT NULL,
				section_id mediumint(9) NOT NULL,
				unit_id mediumint(9) DEFAULT NULL,
				lesson_id mediumint(9) DEFAULT NULL,
				lesson_title text NOT NULL,
				start_date date DEFAULT NULL,
				end_date date DEFAULT NULL,
				number_of_classes tinyint(4) DEFAULT 1 NOT NULL,
				period_duration tinyint(4) DEFAULT 45 NOT NULL,
				learning_outcomes longtext DEFAULT NULL,
				prior_learning text DEFAULT NULL,
				stages longtext DEFAULT NULL,
				teaching_strategies_used longtext DEFAULT NULL,
				assessment_strategies_used longtext DEFAULT NULL,
				assessment_tools_used longtext DEFAULT NULL,
				resources text DEFAULT NULL,
				self_reflection text DEFAULT NULL,
				homework text DEFAULT NULL,
				compliance_score tinyint(4) DEFAULT 0 NOT NULL,
				status varchar(20) DEFAULT 'draft' NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY year_semester (academic_year_id,semester_id),
				KEY teacher_id (teacher_id),
				KEY section_subject_date (section_id,subject_id,start_date)
			) $charset_collate;",

			'olama_supervisor_visits' => "CREATE TABLE {$wpdb->prefix}olama_supervisor_visits (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				schedule_id mediumint(9) NOT NULL,
				supervisor_id bigint(20) UNSIGNED NOT NULL,
				unit_id mediumint(9) DEFAULT NULL,
				lesson_id mediumint(9) DEFAULT NULL,
				visit_date date NOT NULL,
				status enum('planned','completed','approved') DEFAULT 'planned' NOT NULL,
				final_score decimal(5,2) DEFAULT NULL,
				notes text DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  schedule_id (schedule_id),
				KEY  visit_date (visit_date),
				KEY  supervisor_id (supervisor_id)
			) $charset_collate;",

			'olama_supervisor_assignments' => "CREATE TABLE {$wpdb->prefix}olama_supervisor_assignments (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL,
				supervisor_id bigint(20) UNSIGNED NOT NULL,
				grade_id mediumint(9) NOT NULL,
				subject_id mediumint(9) DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  year_semester (academic_year_id,semester_id),
				KEY  supervisor_id (supervisor_id),
				KEY  grade_subject (grade_id,subject_id)
			) $charset_collate;",

			'olama_cleaning_logs' => "CREATE TABLE {$wpdb->prefix}olama_cleaning_logs (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				academic_year_id mediumint(9) NOT NULL,
				floor_id bigint(20) UNSIGNED DEFAULT NULL,
				floor_name varchar(100) NOT NULL,
				cleaning_date date NOT NULL,
				slot_id bigint(20) UNSIGNED DEFAULT NULL,
				slot_time varchar(50) DEFAULT NULL,
				cleaner_id bigint(20) UNSIGNED DEFAULT NULL,
				cleaner_name varchar(255) DEFAULT NULL,
				checkpoints_data longtext NOT NULL,
				recorded_by bigint(20) UNSIGNED NOT NULL,
				recorded_by_name varchar(255) DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id),
				KEY  cleaning_date (cleaning_date),
				KEY  floor_id (floor_id),
				KEY  slot_id (slot_id)
			) $charset_collate;",

			'olama_cleaning_items' => "CREATE TABLE {$wpdb->prefix}olama_cleaning_items (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				item_name varchar(255) NOT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_cleaning_floors' => "CREATE TABLE {$wpdb->prefix}olama_cleaning_floors (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				floor_name varchar(255) NOT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_cleaning_cleaners' => "CREATE TABLE {$wpdb->prefix}olama_cleaning_cleaners (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				cleaner_name varchar(255) NOT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_cleaning_slots' => "CREATE TABLE {$wpdb->prefix}olama_cleaning_slots (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				slot_time varchar(50) NOT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id)
			) $charset_collate;",

			'olama_cleaning_assignments' => "CREATE TABLE {$wpdb->prefix}olama_cleaning_assignments (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				floor_id bigint(20) UNSIGNED NOT NULL,
				cleaner_id bigint(20) UNSIGNED NOT NULL,
				supervisor_id bigint(20) UNSIGNED DEFAULT NULL,
				PRIMARY KEY  (id),
				KEY  floor_cleaner (floor_id,cleaner_id),
				KEY  supervisor_id (supervisor_id)
			) $charset_collate;",

			'olama_kg_photo_session' => "CREATE TABLE {$wpdb->prefix}olama_kg_photo_session (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				academic_year_id bigint(20) NOT NULL,
				semester_id bigint(20) NOT NULL,
				student_uid varchar(50) NOT NULL,
				attended_session tinyint(1) DEFAULT 0 NOT NULL,
				fees_collected varchar(50) DEFAULT NULL,
				photo_received tinyint(1) DEFAULT 0 NOT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY  student_semester (student_uid, semester_id)
			) $charset_collate;",

			'olama_kg_graduation_session' => "CREATE TABLE {$wpdb->prefix}olama_kg_graduation_session (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				academic_year_id bigint(20) NOT NULL,
				semester_id bigint(20) NOT NULL,
				student_uid varchar(50) NOT NULL,
				participate tinyint(1) DEFAULT 0 NOT NULL,
				fees varchar(50) DEFAULT NULL,
				custom_fees varchar(50) DEFAULT NULL,
				updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY  student_semester (student_uid, semester_id)
			) $charset_collate;",

			'olama_exam_halls' => "CREATE TABLE {$wpdb->prefix}olama_exam_halls (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				hall_name varchar(100) NOT NULL,
				capacity smallint(6) NOT NULL DEFAULT 30,
				academic_year_id mediumint(9) NOT NULL,
				is_active tinyint(1) DEFAULT 1 NOT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  academic_year_id (academic_year_id)
			) $charset_collate;",

			'olama_exam_hall_assignments' => "CREATE TABLE {$wpdb->prefix}olama_exam_hall_assignments (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				hall_id mediumint(9) NOT NULL,
				student_id mediumint(9) NOT NULL,
				student_uid varchar(50) DEFAULT NULL,
				academic_year_id mediumint(9) NOT NULL,
				seat_number smallint(6) DEFAULT NULL,
				assigned_by bigint(20) UNSIGNED DEFAULT NULL,
				assigned_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY  student_year (student_id, academic_year_id),
				KEY  hall_id (hall_id),
				KEY  academic_year_id (academic_year_id),
				KEY  student_uid (student_uid)
			) $charset_collate;",

			'olama_exam_hall_attendance' => "CREATE TABLE {$wpdb->prefix}olama_exam_hall_attendance (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				hall_id mediumint(9) NOT NULL,
				academic_year_id mediumint(9) NOT NULL,
				student_id mediumint(9) NOT NULL,
				student_uid varchar(50) DEFAULT NULL,
				exam_date date NOT NULL,
				session_label varchar(100) DEFAULT '' NOT NULL,
				status varchar(20) DEFAULT 'present' NOT NULL,
				recorded_by bigint(20) UNSIGNED DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY  session_student (hall_id, student_id, exam_date, session_label),
				KEY  hall_id (hall_id),
				KEY  exam_date (exam_date)
			) $charset_collate;",

			'olama_exam_hall_notes' => "CREATE TABLE {$wpdb->prefix}olama_exam_hall_notes (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				hall_id mediumint(9) NOT NULL,
				student_id mediumint(9) NOT NULL,
				student_uid varchar(50) DEFAULT NULL,
				exam_date date NOT NULL,
				note_type varchar(50) DEFAULT 'ملتزم' NOT NULL,
				note_text text DEFAULT NULL,
				recorded_by bigint(20) UNSIGNED DEFAULT NULL,
				created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
				PRIMARY KEY  (id),
				KEY  hall_student_date (hall_id, student_id, exam_date)
			) $charset_collate;",

			'olama_exam_hall_invigilators' => "CREATE TABLE {$wpdb->prefix}olama_exam_hall_invigilators (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				hall_id mediumint(9) NOT NULL,
				invigilator_id bigint(20) UNSIGNED NOT NULL,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL DEFAULT 0,
				assigned_by bigint(20) NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY inv_context (invigilator_id, academic_year_id, semester_id),
				KEY hall_context (hall_id, academic_year_id, semester_id)
			) $charset_collate;"
		);

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		foreach ($tables as $table_sql) {
			dbDelta($table_sql);
		}

		$this->ensure_schema_updates();

		// Rename existing semesters for better naming convention
		$wpdb->query("UPDATE {$wpdb->prefix}olama_semesters SET semester_name = 'First Semester' WHERE semester_name = '1st Semester'");
		$wpdb->query("UPDATE {$wpdb->prefix}olama_semesters SET semester_name = 'Second Semester' WHERE semester_name = '2nd Semester'");
	}

	/**
	 * Ensure critical schema updates that dbDelta might miss
	 * Made public so it can be called during plugin init for incremental updates
	 */
	public function ensure_schema_updates()
	{
		global $wpdb;

		// Check if academic_year_id column exists in olama_sections
		$column_exists = $wpdb->get_results(
			"SHOW COLUMNS FROM {$wpdb->prefix}olama_sections LIKE 'academic_year_id'"
		);

		if (empty($column_exists)) {
			// Add the missing column
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}olama_sections 
				ADD COLUMN academic_year_id mediumint(9) NOT NULL AFTER id"
			);

			// Add the index if it doesn't exist
			$index_exists = $wpdb->get_results(
				"SHOW INDEX FROM {$wpdb->prefix}olama_sections WHERE Key_name = 'academic_year_id'"
			);

			if (empty($index_exists)) {
				$wpdb->query(
					"ALTER TABLE {$wpdb->prefix}olama_sections 
					ADD KEY academic_year_id (academic_year_id)"
				);
			}
		}

		// Check if 'assignment' index exists in olama_teacher_assignments
		$assignment_index = $wpdb->get_results(
			"SHOW INDEX FROM {$wpdb->prefix}olama_teacher_assignments WHERE Key_name = 'assignment'"
		);

		if (empty($assignment_index)) {
			// Add the assignment index if it doesn't exist
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}olama_teacher_assignments 
				ADD KEY assignment (teacher_id, section_id, subject_id)"
			);
		}

		// Check if is_active column exists in olama_subjects
		$subject_active_exists = $wpdb->get_results(
			"SHOW COLUMNS FROM {$wpdb->prefix}olama_subjects LIKE 'is_active'"
		);

		if (empty($subject_active_exists)) {
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}olama_subjects 
				ADD COLUMN is_active tinyint(1) DEFAULT 1 NOT NULL"
			);
		}

		// Ensure student schema updates
		$student_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_students");
		$col_names = wp_list_pluck($student_cols, 'Field');

		if (!in_array('family_id', $col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_students ADD COLUMN family_id varchar(50) DEFAULT NULL AFTER student_uid");
		}

		if (!in_array('dob', $col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_students ADD COLUMN dob date DEFAULT NULL AFTER family_id");
		}

		if (!in_array('national_id', $col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_students ADD COLUMN national_id varchar(50) DEFAULT NULL AFTER dob");
		}

		if (!in_array('gender', $col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_students ADD COLUMN gender varchar(20) DEFAULT NULL AFTER national_id");
		}

		// EV Template Migration (one-time rename)
		$kg_ev_tables = array(
			'olama_kg_templates' => 'olama_ev_templates',
			'olama_kg_domains' => 'olama_ev_domains',
			'olama_kg_categories' => 'olama_ev_categories',
			'olama_kg_indicators' => 'olama_ev_indicators',
			'olama_kg_evaluations' => 'olama_ev_records',
			'olama_kg_evaluation_scores' => 'olama_ev_scores'
		);

		foreach ($kg_ev_tables as $old_name => $new_name) {
			$old_full = $wpdb->prefix . $old_name;
			$new_full = $wpdb->prefix . $new_name;
			if ($wpdb->get_var("SHOW TABLES LIKE '$old_full'") === $old_full) {
				if ($wpdb->get_var("SHOW TABLES LIKE '$new_full'") !== $new_full) {
					$wpdb->query("RENAME TABLE $old_full TO $new_full");
				} else {
					// Both exist, just drop the old one (or keep it, but rename is better)
					$wpdb->query("DROP TABLE $old_full");
				}
			}
		}

		// Check if score_config column exists in olama_ev_templates
		$ev_template_config_exists = $wpdb->get_results(
			"SHOW COLUMNS FROM {$wpdb->prefix}olama_ev_templates LIKE 'score_config'"
		);

		if (empty($ev_template_config_exists)) {
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}olama_ev_templates 
				ADD COLUMN score_config longtext DEFAULT NULL AFTER template_name"
			);
		}

		// Ensure student_uid column exists in olama_ev_records
		$ev_records_uid_exists = $wpdb->get_results(
			"SHOW COLUMNS FROM {$wpdb->prefix}olama_ev_records LIKE 'student_uid'"
		);

		if (empty($ev_records_uid_exists)) {
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}olama_ev_records 
				ADD COLUMN student_uid varchar(50) DEFAULT NULL AFTER student_id"
			);
			// Add key
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_records ADD KEY student_uid (student_uid)");

			// Backfill student_uid from olama_students
			$wpdb->query(
				"UPDATE {$wpdb->prefix}olama_ev_records r 
				INNER JOIN {$wpdb->prefix}olama_students s ON r.student_id = s.id 
				SET r.student_uid = s.student_uid 
				WHERE r.student_uid IS NULL"
			);
		}

		// Ensure supervisor_comments column exists in olama_ev_records
		$ev_records_comments_exists = $wpdb->get_results(
			"SHOW COLUMNS FROM {$wpdb->prefix}olama_ev_records LIKE 'supervisor_comments'"
		);

		if (empty($ev_records_comments_exists)) {
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}olama_ev_records 
				ADD COLUMN supervisor_comments text DEFAULT NULL AFTER status"
			);
		}

		// Ensure student_uid exists in other student tables
		$student_link_tables = array(
			'olama_student_enrollment',
			'olama_attendance',
			'olama_student_bus_assignments'
		);

		foreach ($student_link_tables as $table_base) {
			$table_name = $wpdb->prefix . $table_base;
			$uid_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'student_uid'");

			if (empty($uid_exists)) {
				// Add column
				$wpdb->query("ALTER TABLE $table_name ADD COLUMN student_uid varchar(50) DEFAULT NULL AFTER student_id");
				// Add key
				$wpdb->query("ALTER TABLE $table_name ADD KEY student_uid (student_uid)");
			}

			// Always backfill NULL student_uid values (column may exist but be empty)
			$wpdb->query(
				"UPDATE $table_name t 
				INNER JOIN {$wpdb->prefix}olama_students s ON t.student_id = s.id 
				SET t.student_uid = s.student_uid 
				WHERE t.student_uid IS NULL"
			);
		}

		// Migrate UNIQUE KEY constraints from student_id to student_uid
		// dbDelta cannot alter existing UNIQUE keys, so we do it explicitly
		$unique_key_migrations = array(
			'olama_attendance' => array(
				'old_key' => 'student_date',
				'new_key' => 'student_uid_date',
				'new_columns' => 'student_uid, attendance_date'
			),
			'olama_student_bus_assignments' => array(
				'old_key' => 'student_year',
				'new_key' => 'student_uid_year',
				'new_columns' => 'student_uid, academic_year_id'
			)
		);

		foreach ($unique_key_migrations as $table_base => $key_info) {
			$table_name = $wpdb->prefix . $table_base;
			// Check if old key still exists
			$old_key_exists = $wpdb->get_row($wpdb->prepare(
				"SELECT 1 FROM information_schema.STATISTICS WHERE table_schema = %s AND table_name = %s AND index_name = %s LIMIT 1",
				DB_NAME,
				$table_name,
				$key_info['old_key']
			));
 
			if ($old_key_exists) {
				$wpdb->query("ALTER TABLE $table_name DROP INDEX {$key_info['old_key']}");
				$wpdb->query("ALTER TABLE $table_name ADD UNIQUE KEY {$key_info['new_key']} ({$key_info['new_columns']})");
			}
		}
 
		// Ensure Exam Hall Invigilators table exists (Production Fix)
		$invigilators_table = $wpdb->prefix . 'olama_exam_hall_invigilators';
		if ($wpdb->get_var("SHOW TABLES LIKE '$invigilators_table'") !== $invigilators_table) {
			$charset_collate = $wpdb->get_charset_collate();
			$wpdb->query("CREATE TABLE $invigilators_table (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				hall_id mediumint(9) NOT NULL,
				invigilator_id bigint(20) UNSIGNED NOT NULL,
				academic_year_id mediumint(9) NOT NULL,
				semester_id mediumint(9) NOT NULL DEFAULT 0,
				assigned_by bigint(20) NOT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY inv_context (invigilator_id, academic_year_id, semester_id),
				KEY hall_context (hall_id, academic_year_id, semester_id)
			) $charset_collate;");
		}

		// Ensure Exam Hall Attendance table has required columns (Production Fix)
		$attendance_table = $wpdb->prefix . 'olama_exam_hall_attendance';
		if ($wpdb->get_var("SHOW TABLES LIKE '$attendance_table'") === $attendance_table) {
			$attendance_cols = $wpdb->get_results("SHOW COLUMNS FROM $attendance_table");
			$att_col_names = wp_list_pluck($attendance_cols, 'Field');

			if (!in_array('academic_year_id', $att_col_names)) {
				$wpdb->query("ALTER TABLE $attendance_table ADD COLUMN academic_year_id mediumint(9) NOT NULL AFTER hall_id");
				$wpdb->query("ALTER TABLE $attendance_table ADD KEY academic_year_id (academic_year_id)");
			}

			if (!in_array('semester_id', $att_col_names)) {
				$wpdb->query("ALTER TABLE $attendance_table ADD COLUMN semester_id mediumint(9) NOT NULL DEFAULT 0 AFTER academic_year_id");
				$wpdb->query("ALTER TABLE $attendance_table ADD KEY semester_id (semester_id)");
			}
		}

		// Check if plan_type column exists in olama_plans
		$plan_type_exists = $wpdb->get_results(
			"SHOW COLUMNS FROM {$wpdb->prefix}olama_plans LIKE 'plan_type'"
		);

		if (empty($plan_type_exists)) {
			$wpdb->query(
				"ALTER TABLE {$wpdb->prefix}olama_plans 
				ADD COLUMN plan_type varchar(20) DEFAULT 'homework' NOT NULL AFTER status"
			);
		}

		// Ensure other missing columns in olama_plans
		$plan_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_plans");
		$plan_col_names = wp_list_pluck($plan_cols, 'Field');

		if (!in_array('curriculum_id', $plan_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_plans ADD COLUMN curriculum_id mediumint(9) DEFAULT NULL AFTER lesson_id");
		}
		if (!in_array('supervisor_feedback', $plan_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_plans ADD COLUMN supervisor_feedback text DEFAULT NULL AFTER teacher_notes");
		}
		if (!in_array('teacher_response', $plan_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_plans ADD COLUMN teacher_response text DEFAULT NULL AFTER supervisor_feedback");
		}
		if (!in_array('rating', $plan_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_plans ADD COLUMN rating tinyint(4) DEFAULT 0 NOT NULL AFTER teacher_response");
		}

		// Ensure olama_exams schema updates
		$exam_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_exams");
		$exam_col_names = wp_list_pluck($exam_cols, 'Field');

		if (!in_array('semester_exam_id', $exam_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_exams ADD COLUMN semester_exam_id mediumint(9) DEFAULT NULL AFTER semester_id");
		}
		if (!in_array('grade_id', $exam_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_exams ADD COLUMN grade_id mediumint(9) NOT NULL AFTER semester_exam_id");

			// Backfill grade_id from semester_exams if possible
			$wpdb->query("UPDATE {$wpdb->prefix}olama_exams e 
						 JOIN {$wpdb->prefix}olama_semester_exams se ON e.semester_exam_id = se.id 
						 SET e.grade_id = se.grade_id 
						 WHERE e.grade_id = 0 AND se.grade_id IS NOT NULL AND se.grade_id > 0");
		}
		if (!in_array('room_number', $exam_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_exams ADD COLUMN room_number varchar(50) DEFAULT NULL AFTER exam_date");
		}
		if (!in_array('status', $exam_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_exams ADD COLUMN status varchar(20) DEFAULT 'draft' NOT NULL AFTER teacher_notes");
		}
		if (!in_array('supervisor_comments', $exam_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_exams ADD COLUMN supervisor_comments text DEFAULT NULL AFTER status");
		}
		if (!in_array('exam_material_json', $exam_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_exams ADD COLUMN exam_material_json longtext DEFAULT NULL AFTER teacher_notes");
		}

		$exam_indices = $wpdb->get_results("SHOW INDEX FROM {$wpdb->prefix}olama_exams WHERE Key_name = 'semester_exam_id'");
		if (empty($exam_indices)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_exams ADD KEY semester_exam_id (semester_exam_id)");
		}

		// Ensure olama_semester_exams schema updates
		$sem_exam_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_semester_exams");
		$sem_exam_col_names = wp_list_pluck($sem_exam_cols, 'Field');

		if (!in_array('grade_id', $sem_exam_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_semester_exams ADD COLUMN grade_id mediumint(9) DEFAULT NULL AFTER semester_id");
		}
		if (!in_array('room_number', $sem_exam_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_semester_exams ADD COLUMN room_number varchar(50) DEFAULT NULL AFTER exam_name");
		}

		$grade_index = $wpdb->get_results("SHOW INDEX FROM {$wpdb->prefix}olama_semester_exams WHERE Key_name = 'grade_id'");
		if (empty($grade_index)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_semester_exams ADD KEY grade_id (grade_id)");
		}

		// Ensure olama_semester_exams exists
		if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}olama_semester_exams'") !== "{$wpdb->prefix}olama_semester_exams") {
			$this->create_tables();
		}

		// Ensure olama_schedule schema updates
		$schedule_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_schedule");
		$schedule_col_names = wp_list_pluck($schedule_cols, 'Field');

		if (!in_array('schedule_type', $schedule_col_names)) {
			// 1. Add column with default
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_schedule ADD COLUMN schedule_type varchar(20) DEFAULT 'normal' NOT NULL");

			// 2. Force update existing rows just in case default wasn't applied
			$wpdb->query("UPDATE {$wpdb->prefix}olama_schedule SET schedule_type = 'normal' WHERE schedule_type IS NULL OR schedule_type = ''");

			// 3. Drop old index (it might be named differently or missing)
			$indices = $wpdb->get_results("SHOW INDEX FROM {$wpdb->prefix}olama_schedule WHERE Key_name = 'schedule_slot'");
			if (!empty($indices)) {
				$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_schedule DROP INDEX schedule_slot");
			}

			// 4. Add new unique index
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_schedule ADD UNIQUE KEY schedule_slot (semester_id, section_id, day_name, period_number, schedule_type)");
		} else {
			// Column exists, but let's ensure data integrity for existing rows
			$wpdb->query("UPDATE {$wpdb->prefix}olama_schedule SET schedule_type = 'normal' WHERE schedule_type IS NULL OR schedule_type = '' OR schedule_type = '0'");

			// Also ensure the unique index includes schedule_type (critical for preventing cross-type overwrites)
			$index_cols = $wpdb->get_results("SHOW INDEX FROM {$wpdb->prefix}olama_schedule WHERE Key_name = 'schedule_slot'");
			$idx_col_names = wp_list_pluck($index_cols, 'Column_name');
			if (empty($idx_col_names) || !in_array('schedule_type', $idx_col_names)) {
				if (!empty($idx_col_names)) {
					$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_schedule DROP INDEX schedule_slot");
				}
				$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_schedule ADD UNIQUE KEY schedule_slot (semester_id, section_id, day_name, period_number, schedule_type)");
			}
		}

		// New Performance Indexes Optimizations
		$this->ensure_index_exists('olama_students', 'student_uid', 'student_uid');
		$this->ensure_index_exists('olama_students', 'family_id', 'family_id');
		$this->ensure_index_exists('olama_plans', 'section_subject_date', '(section_id, subject_id, plan_date)');
		$this->ensure_index_exists('olama_templates', 'teacher_subject', '(teacher_id, subject_id)');
		$this->ensure_index_exists('olama_curriculum_lessons', 'lesson_dates', '(start_date, end_date)');
		$this->ensure_index_exists('olama_teacher_assignments', 'assignment_full', '(academic_year_id, grade_id, section_id)');
		$this->ensure_index_exists('olama_exams', 'exam_tracking', '(grade_id, subject_id, status)');

		// Ensure olama_teacher_office_hours has academic_year_id and semester_id
		$oh_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_teacher_office_hours");
		$oh_col_names = wp_list_pluck($oh_cols, 'Field');
		if (!in_array('academic_year_id', $oh_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_teacher_office_hours ADD COLUMN academic_year_id mediumint(9) NOT NULL AFTER id");
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_teacher_office_hours ADD KEY academic_year_id (academic_year_id)");
		}
		if (!in_array('semester_id', $oh_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_teacher_office_hours ADD COLUMN semester_id mediumint(9) NOT NULL AFTER academic_year_id");
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_teacher_office_hours ADD KEY semester_id (semester_id)");
		}

		// Backfill legacy office hours to active year/semester
		$active_year_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}olama_academic_years WHERE is_active = 1 LIMIT 1");
		if ($active_year_id) {
			$active_semester_id = $wpdb->get_var($wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}olama_semesters WHERE academic_year_id = %d AND is_active = 1 LIMIT 1",
				$active_year_id
			));
			if ($active_semester_id) {
				$wpdb->query($wpdb->prepare(
					"UPDATE {$wpdb->prefix}olama_teacher_office_hours 
					SET academic_year_id = %d, semester_id = %d 
					WHERE (academic_year_id = 0 OR academic_year_id IS NULL) 
					OR (semester_id = 0 OR semester_id IS NULL)",
					$active_year_id,
					$active_semester_id
				));
			}
		}

		// Ensure shifts refactor updates
		if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}olama_shifts_locations'") === "{$wpdb->prefix}olama_shifts_locations") {
			$location_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_shifts_locations");
			$location_col_names = wp_list_pluck($location_cols, 'Field');
			if (!in_array('gender', $location_col_names)) {
				$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_shifts_locations ADD COLUMN gender varchar(20) DEFAULT 'mixed' NOT NULL AFTER area_floor");
			}
		}

		// --- Generic Evaluation Framework & Supervisor Visit Updates ---

		// 1. olama_ev_templates
		$template_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_ev_templates");
		$template_col_names = wp_list_pluck($template_cols, 'Field');
		if (!in_array('context_type', $template_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_templates ADD COLUMN context_type varchar(50) DEFAULT 'student' NOT NULL AFTER template_name");
			$this->ensure_index_exists('olama_ev_templates', 'context_type', '(context_type)');
		}

		// 2. olama_ev_domains
		$domain_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_ev_domains");
		$domain_col_names = wp_list_pluck($domain_cols, 'Field');
		if (!in_array('context_type', $domain_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_domains ADD COLUMN context_type varchar(50) DEFAULT 'student' NOT NULL AFTER title_ar");
			$this->ensure_index_exists('olama_ev_domains', 'context_type', '(context_type)');
		}

		// 3. olama_ev_indicators
		$indicator_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_ev_indicators");
		$indicator_col_names = wp_list_pluck($indicator_cols, 'Field');
		if (!in_array('max_score', $indicator_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_indicators ADD COLUMN max_score int(11) DEFAULT 5 NOT NULL AFTER indicator_text");
		}
		if (!in_array('weight', $indicator_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_indicators ADD COLUMN weight decimal(5,2) DEFAULT 1.00 NOT NULL AFTER max_score");
		}
		if (!in_array('is_critical', $indicator_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_indicators ADD COLUMN is_critical boolean DEFAULT 0 NOT NULL AFTER weight");
		}
		if (!in_array('context_type', $indicator_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_indicators ADD COLUMN context_type varchar(50) DEFAULT 'student' NOT NULL AFTER is_critical");
			$this->ensure_index_exists('olama_ev_indicators', 'context_type', '(context_type)');
		}

		// 4. olama_ev_records
		$record_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_ev_records");
		$record_col_names = wp_list_pluck($record_cols, 'Field');
		if (!in_array('context_type', $record_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_records ADD COLUMN context_type varchar(50) DEFAULT 'student' NOT NULL AFTER semester_id");
			$this->ensure_index_exists('olama_ev_records', 'context_type', '(context_type)');
		}
		if (!in_array('related_entity_type', $record_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_records ADD COLUMN related_entity_type varchar(50) DEFAULT NULL AFTER context_type");
		}
		if (!in_array('related_entity_id', $record_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_records ADD COLUMN related_entity_id bigint(20) DEFAULT NULL AFTER related_entity_type");
		}

		// Adjust student_id to be nullable
		if (in_array('student_id', $record_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_records MODIFY COLUMN student_id mediumint(9) DEFAULT NULL");
		}

		// 5. olama_ev_scores
		$score_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_ev_scores");
		$score_col_names = wp_list_pluck($score_cols, 'Field');
		if (!in_array('calculated_score', $score_col_names)) {
			$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_ev_scores ADD COLUMN calculated_score decimal(5,2) DEFAULT NULL AFTER score");
		}

		// 6. supervisor_visits 
		if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}olama_supervisor_visits'") === "{$wpdb->prefix}olama_supervisor_visits") {
			$sv_cols = $wpdb->get_results("SHOW COLUMNS FROM {$wpdb->prefix}olama_supervisor_visits");
			$sv_col_names = wp_list_pluck($sv_cols, 'Field');

			if (!in_array('unit_id', $sv_col_names)) {
				$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_supervisor_visits ADD COLUMN unit_id mediumint(9) DEFAULT NULL AFTER supervisor_id");
			}
			if (!in_array('lesson_id', $sv_col_names)) {
				$wpdb->query("ALTER TABLE {$wpdb->prefix}olama_supervisor_visits ADD COLUMN lesson_id mediumint(9) DEFAULT NULL AFTER unit_id");
			}

			$this->ensure_index_exists('olama_supervisor_visits', 'schedule_id', '(schedule_id)');
			$this->ensure_index_exists('olama_supervisor_visits', 'visit_date', '(visit_date)');
			$this->ensure_index_exists('olama_supervisor_visits', 'supervisor_id', '(supervisor_id)');
		}

		// 7. Ensure subject_id for evaluations
		$this->ensure_column_exists('olama_ev_templates', 'subject_id', 'mediumint(9) DEFAULT NULL AFTER grade_id');
		$this->ensure_index_exists('olama_ev_templates', 'subject_id', '(subject_id)');

		$this->ensure_column_exists('olama_ev_records', 'subject_id', 'mediumint(9) DEFAULT NULL AFTER student_uid');
		$this->ensure_index_exists('olama_ev_records', 'subject_id', '(subject_id)');

		// 8. One-time Migration: Shift rating mapping and scores
		$this->migrate_evaluation_ratings();

		// 9. Exam Hall Distribution – add semester_id to module tables
		if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}olama_exam_hall_assignments'") === "{$wpdb->prefix}olama_exam_hall_assignments") {
			$this->ensure_column_exists('olama_exam_hall_assignments', 'semester_id', 'mediumint(9) NOT NULL DEFAULT 0 AFTER academic_year_id');
		}
		if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}olama_exam_hall_attendance'") === "{$wpdb->prefix}olama_exam_hall_attendance") {
			$this->ensure_column_exists('olama_exam_hall_attendance', 'academic_year_id', 'mediumint(9) NOT NULL AFTER hall_id');
			$this->ensure_column_exists('olama_exam_hall_attendance', 'semester_id', 'mediumint(9) NOT NULL DEFAULT 0 AFTER academic_year_id');
		}
		if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}olama_exam_hall_notes'") === "{$wpdb->prefix}olama_exam_hall_notes") {
			$this->ensure_column_exists('olama_exam_hall_notes', 'semester_id', 'mediumint(9) NOT NULL DEFAULT 0 AFTER exam_date');
		}
	}

	/**
	 * Migrates evaluation ratings to ensure the highest label is always 5.
	 * Also updates associated scores and percentages.
	 */
	private function migrate_evaluation_ratings()
	{
		global $wpdb;

		$templates = $wpdb->get_results("SELECT id, score_config FROM {$wpdb->prefix}olama_ev_templates");

		foreach ($templates as $template) {
			$config = maybe_unserialize($template->score_config);
			if (!is_array($config) || empty($config))
				continue;

			$keys = array_keys($config);
			$max_key = !empty($keys) ? max($keys) : 0;

			// If max key is less than 5, we need to shift (e.g., if it's 3, delta is 2)
			if ($max_key > 0 && $max_key < 5) {
				$delta = 5 - $max_key;

				// 1. Shift Template Config
				$new_config = array();
				foreach ($config as $k => $v) {
					$new_config[$k + $delta] = $v;
				}
				$wpdb->update(
					"{$wpdb->prefix}olama_ev_templates",
					array('score_config' => maybe_serialize($new_config)),
					array('id' => $template->id)
				);

				// 2. Shift Scores in olama_ev_scores
				$score_rows = $wpdb->get_results($wpdb->prepare(
					"SELECT s.id, s.score, i.weight, i.is_critical 
					 FROM {$wpdb->prefix}olama_ev_scores s
					 JOIN {$wpdb->prefix}olama_ev_records r ON s.evaluation_id = r.id
					 JOIN {$wpdb->prefix}olama_ev_indicators i ON s.indicator_id = i.id
					 WHERE r.template_id = %d AND s.score IS NOT NULL",
					$template->id
				));

				foreach ($score_rows as $row) {
					$new_score = min(5, (int) $row->score + $delta);

					// Recalculate calculated_score using the non-linear formula
					$weight = (float) $row->weight;
					$multiplier = (bool) $row->is_critical ? 2.0 : 1.0;
					$points = ($new_score * $new_score) / 5.0;
					$new_calculated = $points * $weight * $multiplier;

					$wpdb->update(
						"{$wpdb->prefix}olama_ev_scores",
						array(
							'score' => $new_score,
							'calculated_score' => $new_calculated
						),
						array('id' => $row->id)
					);
				}
			}
		}
	}

	/**
	 * Helper to ensure a column exists
	 */
	private function ensure_column_exists($table_name, $column_name, $column_def)
	{
		global $wpdb;
		$table_full = $wpdb->prefix . $table_name;
		$column_exists = $wpdb->get_results($wpdb->prepare(
			"SHOW COLUMNS FROM $table_full LIKE %s",
			$column_name
		));

		if (empty($column_exists)) {
			$wpdb->query("ALTER TABLE $table_full ADD COLUMN $column_name $column_def");
		}
	}

	/**
	 * Helper to ensure an index exists on a table
	 */
	private function ensure_index_exists($table_name, $index_name, $index_columns)
	{
		global $wpdb;
		$table_full = $wpdb->prefix . $table_name;
		$index_exists = $wpdb->get_results($wpdb->prepare(
			"SHOW INDEX FROM $table_full WHERE Key_name = %s",
			$index_name
		));

		if (empty($index_exists)) {
			$wpdb->query("ALTER TABLE $table_full ADD KEY $index_name $index_columns");
		}
	}

	public function drop_tables()
	{
		global $wpdb;

		$tables = array(
			'olama_supervisor_visits',
			'olama_attendance',
			'olama_ev_scores',
			'olama_ev_records',
			'olama_ev_indicators',
			'olama_ev_categories',
			'olama_ev_domains',
			'olama_ev_templates',
			'olama_exam_attachments',
			'olama_stationary',
			'olama_exams',
			'olama_teacher_office_hours',
			'olama_teacher_assignments',
			'olama_academic_events',
			'olama_logs',
			'olama_curriculum_questions',
			'olama_curriculum_lessons',
			'olama_curriculum_units',
			'olama_schedule',
			'olama_templates',
			'olama_plan_questions',
			'olama_plans',
			'olama_students',
			'olama_teachers',
			'olama_subjects',
			'olama_sections',
			'olama_grades',
			'olama_semesters',
			'olama_academic_years',
			'olama_settings',
			'olama_user_preferences',
			'olama_notifications',
			'olama_shifts_schedule',
			'olama_shifts_time_slots',
			'olama_shifts_periods',
			'olama_shifts',
			'olama_shifts_assignments',
			'olama_shifts_locations'
		);

		foreach ($tables as $table) {
			$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}$table");
		}

		delete_option('olama_school_version');
	}
}