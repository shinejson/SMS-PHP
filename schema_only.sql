CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year_name` varchar(20) NOT NULL,
  `is_current` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `year_name` (`year_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `account_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `transaction_type` enum('deposit','withdrawal') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `account_id` (`account_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `account_transactions_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `student_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_transactions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `activities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('login','create','update','delete','view','export','system') NOT NULL DEFAULT 'system',
  `icon` varchar(50) DEFAULT 'fas fa-circle',
  `user_id` int(11) NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_type` (`type`),
  KEY `idx_created` (`created_at`),
  KEY `idx_related` (`related_id`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=656 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `assignment_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `submitted_file_path` varchar(255) DEFAULT NULL,
  `marks_obtained` decimal(5,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `status` enum('pending','submitted','graded','late') NOT NULL DEFAULT 'pending',
  `submitted_content` text DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `graded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_assignment_student` (`assignment_id`,`student_id`),
  KEY `fk_submission_student` (`student_id`),
  KEY `fk_submission_teacher` (`teacher_id`),
  CONSTRAINT `fk_submission_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submission_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submission_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL,
  `term_id` int(11) NOT NULL,
  `assignment_date` date NOT NULL,
  `due_date` date NOT NULL,
  `max_marks` decimal(5,2) DEFAULT 100.00,
  `assignment_type` enum('homework','classwork','project','quiz','exam') DEFAULT 'homework',
  `status` enum('active','completed','cancelled') DEFAULT 'active',
  `instructions` text DEFAULT NULL,
  `attachment_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_assignment_date` (`assignment_date`),
  KEY `idx_status_due_date` (`status`,`due_date`),
  KEY `idx_class_subject` (`class_id`,`subject`),
  KEY `fk_assignment_term` (`term_id`),
  CONSTRAINT `fk_assignment_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_assignment_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_assignment_term` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `term_id` int(11) DEFAULT NULL,
  `academic_year_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `marked_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_date` (`student_id`,`attendance_date`),
  KEY `idx_class_id` (`class_id`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_status` (`status`),
  KEY `fk_attendance_marker` (`marked_by`),
  KEY `idx_class_date` (`class_id`,`attendance_date`),
  KEY `idx_student_date_range` (`student_id`,`attendance_date`),
  KEY `fk_attendance_academic_year` (`academic_year_id`),
  KEY `fk_attendance_term` (`term_id`),
  CONSTRAINT `fk_attendance_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_marked_by` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_attendance_term` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendance_summary` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `month` varchar(2) NOT NULL,
  `year` varchar(4) NOT NULL,
  `total_days` int(11) DEFAULT 0,
  `present_days` int(11) DEFAULT 0,
  `absent_days` int(11) DEFAULT 0,
  `late_days` int(11) DEFAULT 0,
  `excused_days` int(11) DEFAULT 0,
  `attendance_percentage` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_month_year` (`student_id`,`class_id`,`month`,`year`),
  KEY `class_id` (`class_id`),
  CONSTRAINT `attendance_summary_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `attendance_summary_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `billing` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_type` varchar(50) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `term_id` int(11) DEFAULT NULL,
  `academic_year_id` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_billing_class` (`class_id`),
  KEY `fk_billing_academic_year` (`academic_year_id`),
  CONSTRAINT `fk_billing_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_billing_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=102 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `class_score_marks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `term_id` int(11) DEFAULT NULL,
  `mark1` decimal(5,2) DEFAULT NULL,
  `mark2` decimal(5,2) DEFAULT NULL,
  `mark3` decimal(5,2) DEFAULT NULL,
  `mark4` decimal(5,2) DEFAULT NULL,
  `mark5` decimal(5,2) DEFAULT NULL,
  `mark6` decimal(5,2) DEFAULT NULL,
  `mark7` decimal(5,2) DEFAULT NULL,
  `mark8` decimal(5,2) DEFAULT NULL,
  `mark9` decimal(5,2) DEFAULT NULL,
  `mark10` decimal(5,2) DEFAULT NULL,
  `total_marks` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `academic_year_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `student_id` (`student_id`),
  KEY `term_id` (`term_id`),
  KEY `academic_year_id` (`academic_year_id`),
  CONSTRAINT `class_score_marks_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `class_score_marks_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `class_score_marks_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `class_score_marks_ibfk_4` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`),
  CONSTRAINT `class_score_marks_ibfk_5` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`),
  CONSTRAINT `fk_exam_score_marks_term` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(50) NOT NULL,
  `class_teacher_id` int(11) DEFAULT NULL,
  `academic_year` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_title` varchar(255) NOT NULL,
  `event_type` enum('Academic','Sports','Cultural','Holiday','Meeting','Examination','Other') NOT NULL,
  `event_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `academic_year_id` int(11) NOT NULL,
  `academic_year` varchar(20) DEFAULT NULL,
  `term_id` int(11) NOT NULL,
  `term_name` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `academic_year_id` (`academic_year_id`),
  KEY `term_id` (`term_id`),
  CONSTRAINT `events_ibfk_1` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`),
  CONSTRAINT `events_ibfk_2` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `exam_score_marks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `term_id` int(11) DEFAULT NULL,
  `mark1` decimal(5,2) DEFAULT NULL,
  `mark2` decimal(5,2) DEFAULT NULL,
  `mark3` decimal(5,2) DEFAULT NULL,
  `mark4` decimal(5,2) DEFAULT NULL,
  `mark5` decimal(5,2) DEFAULT NULL,
  `mark6` decimal(5,2) DEFAULT NULL,
  `mark7` decimal(5,2) DEFAULT NULL,
  `mark8` decimal(5,2) DEFAULT NULL,
  `mark9` decimal(5,2) DEFAULT NULL,
  `mark10` decimal(5,2) DEFAULT NULL,
  `total_marks` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `academic_year_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `student_id` (`student_id`),
  KEY `term_id` (`term_id`),
  KEY `academic_year_id` (`academic_year_id`),
  CONSTRAINT `exam_score_marks_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `exam_score_marks_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `exam_score_marks_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `exam_score_marks_ibfk_4` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`),
  CONSTRAINT `exam_score_marks_ibfk_5` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `student_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `invoice_type` enum('tuition','transport','meals','books','uniform','other') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('paid','unpaid','overdue','cancelled','deleted') DEFAULT 'unpaid',
  `payment_date` date DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `academic_year_id` (`academic_year_id`),
  KEY `term_id` (`term_id`),
  KEY `created_by` (`created_by`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_student_id` (`student_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due_date` (`due_date`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`),
  CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`),
  CONSTRAINT `invoices_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `marks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `term` enum('First','Second','Third','Final') NOT NULL,
  `marks` decimal(5,2) NOT NULL,
  `grade` varchar(2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `marks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `marks_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `marks_weights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mid_weight` int(11) DEFAULT 10,
  `class_weight` int(11) DEFAULT 20,
  `exam_weight` int(11) DEFAULT 70,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_type` enum('all_teachers','all_parents','specific_class','specific_teacher','specific_parent') NOT NULL,
  `message_type` enum('event_reminder','meeting_invitation','exam_schedule','fee_reminder','general_announcement') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `specific_class_id` int(11) DEFAULT NULL,
  `specific_teacher_id` int(11) DEFAULT NULL,
  `specific_student_id` int(11) DEFAULT NULL,
  `send_email` tinyint(1) DEFAULT 0,
  `send_sms` tinyint(1) DEFAULT 0,
  `status` enum('Draft','Pending','Processing','Sent','Failed') DEFAULT 'Draft',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `specific_class_id` (`specific_class_id`),
  KEY `specific_teacher_id` (`specific_teacher_id`),
  KEY `specific_student_id` (`specific_student_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`specific_class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`specific_teacher_id`) REFERENCES `teachers` (`id`),
  CONSTRAINT `messages_ibfk_4` FOREIGN KEY (`specific_student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `midterm_marks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `term_id` int(11) DEFAULT NULL,
  `mark1` decimal(5,2) DEFAULT NULL,
  `mark2` decimal(5,2) DEFAULT NULL,
  `mark3` decimal(5,2) DEFAULT NULL,
  `mark4` decimal(5,2) DEFAULT NULL,
  `mark5` decimal(5,2) DEFAULT NULL,
  `mark6` decimal(5,2) DEFAULT NULL,
  `mark7` decimal(5,2) DEFAULT NULL,
  `mark8` decimal(5,2) DEFAULT NULL,
  `mark9` decimal(5,2) DEFAULT NULL,
  `mark10` decimal(5,2) DEFAULT NULL,
  `total_marks` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `academic_year_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `class_id` (`class_id`),
  KEY `subject_id` (`subject_id`),
  KEY `student_id` (`student_id`),
  KEY `term_id` (`term_id`),
  KEY `academic_year_id` (`academic_year_id`),
  CONSTRAINT `midterm_marks_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  CONSTRAINT `midterm_marks_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  CONSTRAINT `midterm_marks_ibfk_3` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `midterm_marks_ibfk_4` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`),
  CONSTRAINT `midterm_marks_ibfk_5` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) DEFAULT NULL,
  `receipt_no` varchar(20) NOT NULL,
  `student_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `received_by` int(11) NOT NULL,
  `payment_type` varchar(50) DEFAULT NULL,
  `term_id` int(11) DEFAULT NULL,
  `academic_year_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','Cheque','Bank Transfer','Mobile Money') NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('Paid','Part','Pending') DEFAULT 'Paid',
  `description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `collected_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_no` (`receipt_no`),
  KEY `student_id` (`student_id`),
  KEY `term_id` (`term_id`),
  KEY `fk_payments_academic_year` (`academic_year_id`),
  KEY `idx_invoice_id` (`invoice_id`),
  KEY `idx_payment_date` (`payment_date`),
  KEY `idx_payment_method_id` (`payment_method_id`),
  KEY `idx_received_by` (`received_by`),
  CONSTRAINT `fk_payments_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_invoice_id` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_payment_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`),
  CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `remarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `grade` varchar(5) NOT NULL,
  `min_mark` int(11) NOT NULL,
  `max_mark` int(11) NOT NULL,
  `remark` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `report_remarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `term_id` int(11) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `attendance` varchar(50) DEFAULT NULL,
  `conduct` varchar(100) DEFAULT NULL,
  `attitude` varchar(100) DEFAULT NULL,
  `promoted_to` varchar(50) DEFAULT NULL,
  `teacher_remark` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_term_year` (`student_id`,`term_id`,`academic_year_id`),
  KEY `term_id` (`term_id`),
  KEY `academic_year_id` (`academic_year_id`),
  CONSTRAINT `report_remarks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  CONSTRAINT `report_remarks_ibfk_2` FOREIGN KEY (`term_id`) REFERENCES `terms` (`id`),
  CONSTRAINT `report_remarks_ibfk_3` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `school_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_name` varchar(255) NOT NULL,
  `school_short_name` varchar(100) DEFAULT NULL,
  `address` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `headmaster_name` varchar(255) DEFAULT NULL,
  `app_password` varchar(50) NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `headmaster_signature` varchar(500) DEFAULT NULL,
  `motto` text DEFAULT NULL,
  `favicon` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `student_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_number` varchar(20) NOT NULL,
  `student_id` int(11) NOT NULL,
  `account_type` varchar(50) NOT NULL,
  `current_balance` decimal(10,2) DEFAULT 0.00,
  `status` enum('active','inactive','closed') DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `last_transaction_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_number` (`account_number`),
  KEY `student_id` (`student_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `student_accounts_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  CONSTRAINT `student_accounts_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `dob` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `address` text DEFAULT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_contact` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `class_id` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive','Graduated') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `academic_year_id` int(11) DEFAULT NULL,
  `class_status` enum('active','promoted','repeated','probation','graduated') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `student_id` (`student_id`),
  KEY `idx_students_class_status` (`class_status`),
  KEY `fk_students_academic_year` (`academic_year_id`),
  CONSTRAINT `fk_students_academic_year` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_years` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subject_name` varchar(100) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `subject_code` (`subject_code`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `teacher_id` varchar(20) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `teacher_id` (`teacher_id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_teachers_user_id` (`user_id`),
  CONSTRAINT `fk_teachers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `terms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term_name` varchar(50) NOT NULL,
  `term_order` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `term_name` (`term_name`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tuition_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `billing_id` int(11) NOT NULL,
  `sub_fee_name` varchar(100) NOT NULL,
  `sub_fee_amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `billing_id` (`billing_id`),
  CONSTRAINT `tuition_details_ibfk_1` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=190 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','teacher','staff') NOT NULL DEFAULT 'staff',
  `signature` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

