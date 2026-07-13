-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 12, 2026 at 11:17 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `proctad_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `other_examination_personnel`
--

CREATE TABLE `other_examination_personnel` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `oep_id` varchar(30) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `sex` varchar(10) NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `agency` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `personnel_type` varchar(30) NOT NULL,
  `field_office_id` bigint(20) UNSIGNED DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_assignment_confirmations`
--

CREATE TABLE `proctad_assignment_confirmations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `exam_assignment_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(30) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_audit_logs`
--

CREATE TABLE `proctad_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `auditable_type` varchar(255) NOT NULL,
  `auditable_id` bigint(20) UNSIGNED NOT NULL,
  `field_office_id` bigint(20) UNSIGNED DEFAULT NULL,
  `changes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`changes`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_cache`
--

CREATE TABLE `proctad_cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_cache_locks`
--

CREATE TABLE `proctad_cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_certificates`
--

CREATE TABLE `proctad_certificates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `certificate_no` varchar(40) DEFAULT NULL,
  `type` varchar(30) NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `field_office_id` bigint(20) UNSIGNED NOT NULL,
  `certifiable_type` varchar(255) NOT NULL,
  `certifiable_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `requested_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `disapproval_remarks` varchar(255) DEFAULT NULL,
  `signatory_name` varchar(255) DEFAULT NULL,
  `signatory_position` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_email_logs`
--

CREATE TABLE `proctad_email_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `recipient_email` varchar(100) NOT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `email_type` varchar(30) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_by` bigint(20) UNSIGNED DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_email_templates`
--

CREATE TABLE `proctad_email_templates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` text NOT NULL,
  `body_plain` text DEFAULT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_examinations`
--

CREATE TABLE `proctad_examinations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(100) NOT NULL,
  `exam_type_id` bigint(20) UNSIGNED DEFAULT NULL,
  `exam_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_examination_school`
--

CREATE TABLE `proctad_examination_school` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `examination_id` bigint(20) UNSIGNED NOT NULL,
  `school_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_exam_assignments`
--

CREATE TABLE `proctad_exam_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `examination_id` bigint(20) UNSIGNED NOT NULL,
  `examination_school_id` bigint(20) UNSIGNED DEFAULT NULL,
  `exam_room_id` bigint(20) UNSIGNED DEFAULT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `role` varchar(40) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `confirmation_sent_at` timestamp NULL DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `decline_reason` varchar(255) DEFAULT NULL,
  `field_office_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_confirmed_at` timestamp NULL DEFAULT NULL,
  `attendance_confirmed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `performance_rating` varchar(30) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_exam_assignment_attendances`
--

CREATE TABLE `proctad_exam_assignment_attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `exam_assignment_id` bigint(20) UNSIGNED NOT NULL,
  `examination_school_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'present',
  `scan_method` varchar(10) NOT NULL DEFAULT 'qr',
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scanned_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_exam_assignment_schools`
--

CREATE TABLE `proctad_exam_assignment_schools` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `exam_assignment_id` bigint(20) UNSIGNED NOT NULL,
  `examination_school_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_exam_rooms`
--

CREATE TABLE `proctad_exam_rooms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `examination_school_id` bigint(20) UNSIGNED NOT NULL,
  `room_number` varchar(50) NOT NULL,
  `capacity` smallint(5) UNSIGNED NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_exam_types`
--

CREATE TABLE `proctad_exam_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_failed_jobs`
--

CREATE TABLE `proctad_failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_fee_schedules`
--

CREATE TABLE `proctad_fee_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payee_type` varchar(255) NOT NULL,
  `payee_value` varchar(255) NOT NULL,
  `amount_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_field_offices`
--

CREATE TABLE `proctad_field_offices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(20) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_jobs`
--

CREATE TABLE `proctad_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_job_batches`
--

CREATE TABLE `proctad_job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_letterheads`
--

CREATE TABLE `proctad_letterheads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_members`
--

CREATE TABLE `proctad_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `proctad_id` varchar(30) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `sex` varchar(10) NOT NULL,
  `email` varchar(255) NOT NULL,
  `mobile_number` varchar(20) NOT NULL,
  `agency` varchar(255) NOT NULL,
  `position` varchar(255) DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `field_office_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `disqualification_remarks` text DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `proctad_member_attendance_history`
-- (See below for the actual view)
--
CREATE TABLE `proctad_member_attendance_history` (
);

-- --------------------------------------------------------

--
-- Table structure for table `proctad_member_requirements`
--

CREATE TABLE `proctad_member_requirements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `requirement` varchar(50) NOT NULL,
  `complied` tinyint(1) NOT NULL DEFAULT 0,
  `file_path` varchar(255) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_migrations`
--

CREATE TABLE `proctad_migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_nep_assignments`
--

CREATE TABLE `proctad_nep_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `non_exam_personnel_id` bigint(20) UNSIGNED NOT NULL,
  `examination_school_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'confirmed',
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_nep_attendances`
--

CREATE TABLE `proctad_nep_attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `non_exam_personnel_id` bigint(20) UNSIGNED NOT NULL,
  `examination_school_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'present',
  `scan_method` varchar(10) NOT NULL DEFAULT 'qr',
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scanned_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_non_exam_personnel`
--

CREATE TABLE `proctad_non_exam_personnel` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nep_id` varchar(30) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `sex` varchar(10) NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `agency` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `personnel_type` varchar(30) NOT NULL,
  `field_office_id` bigint(20) UNSIGNED DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_notifications`
--

CREATE TABLE `proctad_notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) UNSIGNED NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_oep_assignments`
--

CREATE TABLE `proctad_oep_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `other_examination_personnel_id` bigint(20) UNSIGNED NOT NULL,
  `examination_school_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'confirmed',
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_oep_attendances`
--

CREATE TABLE `proctad_oep_attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `other_examination_personnel_id` bigint(20) UNSIGNED NOT NULL,
  `examination_school_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'present',
  `scan_method` varchar(10) NOT NULL DEFAULT 'qr',
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `scanned_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_other_examination_personnel`
--

CREATE TABLE `proctad_other_examination_personnel` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `oep_id` varchar(30) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `sex` varchar(10) NOT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `agency` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `personnel_type` varchar(30) NOT NULL,
  `field_office_id` bigint(20) UNSIGNED DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_password_reset_tokens`
--

CREATE TABLE `proctad_password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_schools`
--

CREATE TABLE `proctad_schools` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `field_office_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `municipality` varchar(100) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `proctad_school_statistics`
-- (See below for the actual view)
--
CREATE TABLE `proctad_school_statistics` (
);

-- --------------------------------------------------------

--
-- Table structure for table `proctad_sessions`
--

CREATE TABLE `proctad_sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_settings`
--

CREATE TABLE `proctad_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_signatories`
--

CREATE TABLE `proctad_signatories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `field_office_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `position` varchar(255) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_trainings`
--

CREATE TABLE `proctad_trainings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(20) NOT NULL,
  `training_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `proctad_training_assignments`
--

CREATE TABLE `proctad_training_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `training_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` bigint(20) UNSIGNED NOT NULL,
  `field_office_id` bigint(20) UNSIGNED NOT NULL,
  `attendance_confirmed_at` timestamp NULL DEFAULT NULL,
  `attendance_confirmed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `proctad_training_attendance_stats`
-- (See below for the actual view)
--
CREATE TABLE `proctad_training_attendance_stats` (
);

-- --------------------------------------------------------

--
-- Table structure for table `proctad_users`
--

CREATE TABLE `proctad_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `first_name` varchar(255) DEFAULT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `role` varchar(30) NOT NULL DEFAULT 'member',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `field_office_id` bigint(20) UNSIGNED DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(64) DEFAULT NULL,
  `mobile_number` varchar(20) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `failed_login_attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `google_avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_available_proctads_for_exam`
-- (See below for the actual view)
--
CREATE TABLE `view_available_proctads_for_exam` (
);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `other_examination_personnel`
--
ALTER TABLE `other_examination_personnel`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `proctad_assignment_confirmations`
--
ALTER TABLE `proctad_assignment_confirmations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proctad_assignment_confirmations_exam_assignment_id_foreign` (`exam_assignment_id`),
  ADD KEY `proctad_assignment_confirmations_action_index` (`action`);

--
-- Indexes for table `proctad_audit_logs`
--
ALTER TABLE `proctad_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proctad_audit_logs_user_id_foreign` (`user_id`),
  ADD KEY `proctad_audit_logs_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`);

--
-- Indexes for table `proctad_cache`
--
ALTER TABLE `proctad_cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `proctad_cache_expiration_index` (`expiration`);

--
-- Indexes for table `proctad_cache_locks`
--
ALTER TABLE `proctad_cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `proctad_cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `proctad_certificates`
--
ALTER TABLE `proctad_certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_certificates_type_certifiable_type_certifiable_id_unique` (`type`,`certifiable_type`,`certifiable_id`),
  ADD UNIQUE KEY `proctad_certificates_certificate_no_unique` (`certificate_no`),
  ADD KEY `proctad_certificates_member_id_foreign` (`member_id`),
  ADD KEY `proctad_certificates_field_office_id_foreign` (`field_office_id`),
  ADD KEY `proctad_certificates_certifiable_type_certifiable_id_index` (`certifiable_type`,`certifiable_id`),
  ADD KEY `proctad_certificates_requested_by_foreign` (`requested_by`),
  ADD KEY `proctad_certificates_approved_by_foreign` (`approved_by`),
  ADD KEY `proctad_certificates_status_index` (`status`);

--
-- Indexes for table `proctad_email_logs`
--
ALTER TABLE `proctad_email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proctad_email_logs_sent_by_foreign` (`sent_by`),
  ADD KEY `proctad_email_logs_recipient_email_index` (`recipient_email`),
  ADD KEY `proctad_email_logs_email_type_index` (`email_type`),
  ADD KEY `proctad_email_logs_status_index` (`status`);

--
-- Indexes for table `proctad_email_templates`
--
ALTER TABLE `proctad_email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_email_templates_code_unique` (`code`);

--
-- Indexes for table `proctad_examinations`
--
ALTER TABLE `proctad_examinations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proctad_examinations_exam_date_index` (`exam_date`),
  ADD KEY `proctad_examinations_exam_type_id_foreign` (`exam_type_id`);

--
-- Indexes for table `proctad_examination_school`
--
ALTER TABLE `proctad_examination_school`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_examination_school_examination_id_school_id_unique` (`examination_id`,`school_id`),
  ADD KEY `proctad_examination_school_school_id_foreign` (`school_id`),
  ADD KEY `proctad_examination_school_assigned_by_foreign` (`assigned_by`);

--
-- Indexes for table `proctad_exam_assignments`
--
ALTER TABLE `proctad_exam_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_exam_assignments_examination_id_member_id_unique` (`examination_id`,`member_id`),
  ADD KEY `proctad_exam_assignments_member_id_foreign` (`member_id`),
  ADD KEY `proctad_exam_assignments_field_office_id_foreign` (`field_office_id`),
  ADD KEY `proctad_exam_assignments_attendance_confirmed_by_foreign` (`attendance_confirmed_by`),
  ADD KEY `proctad_exam_assignments_examination_school_id_foreign` (`examination_school_id`),
  ADD KEY `proctad_exam_assignments_exam_room_id_foreign` (`exam_room_id`),
  ADD KEY `proctad_exam_assignments_status_index` (`status`);

--
-- Indexes for table `proctad_exam_assignment_attendances`
--
ALTER TABLE `proctad_exam_assignment_attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `exam_assignment_attendances_unique` (`exam_assignment_id`,`examination_school_id`),
  ADD KEY `eaa_examination_school_id_foreign` (`examination_school_id`),
  ADD KEY `proctad_exam_assignment_attendances_scanned_by_foreign` (`scanned_by`);

--
-- Indexes for table `proctad_exam_assignment_schools`
--
ALTER TABLE `proctad_exam_assignment_schools`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `exam_assignment_schools_unique` (`exam_assignment_id`,`examination_school_id`),
  ADD KEY `proctad_exam_assignment_schools_examination_school_id_foreign` (`examination_school_id`);

--
-- Indexes for table `proctad_exam_rooms`
--
ALTER TABLE `proctad_exam_rooms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proctad_exam_rooms_examination_school_id_index` (`examination_school_id`);

--
-- Indexes for table `proctad_exam_types`
--
ALTER TABLE `proctad_exam_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `proctad_failed_jobs`
--
ALTER TABLE `proctad_failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_failed_jobs_uuid_unique` (`uuid`),
  ADD KEY `proctad_failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`);

--
-- Indexes for table `proctad_fee_schedules`
--
ALTER TABLE `proctad_fee_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_fee_schedules_payee_type_payee_value_unique` (`payee_type`,`payee_value`),
  ADD KEY `proctad_fee_schedules_updated_by_foreign` (`updated_by`);

--
-- Indexes for table `proctad_field_offices`
--
ALTER TABLE `proctad_field_offices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_field_offices_code_unique` (`code`);

--
-- Indexes for table `proctad_jobs`
--
ALTER TABLE `proctad_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proctad_jobs_queue_index` (`queue`);

--
-- Indexes for table `proctad_job_batches`
--
ALTER TABLE `proctad_job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `proctad_letterheads`
--
ALTER TABLE `proctad_letterheads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proctad_letterheads_uploaded_by_foreign` (`uploaded_by`),
  ADD KEY `proctad_letterheads_is_active_index` (`is_active`);

--
-- Indexes for table `proctad_members`
--
ALTER TABLE `proctad_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_members_proctad_id_unique` (`proctad_id`),
  ADD UNIQUE KEY `proctad_members_email_unique` (`email`),
  ADD KEY `proctad_members_user_id_foreign` (`user_id`),
  ADD KEY `proctad_members_field_office_id_foreign` (`field_office_id`),
  ADD KEY `proctad_members_status_index` (`status`);

--
-- Indexes for table `proctad_member_requirements`
--
ALTER TABLE `proctad_member_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_member_requirements_member_id_requirement_unique` (`member_id`,`requirement`);

--
-- Indexes for table `proctad_migrations`
--
ALTER TABLE `proctad_migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `proctad_nep_assignments`
--
ALTER TABLE `proctad_nep_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nep_assignments_nep_venue_unique` (`non_exam_personnel_id`,`examination_school_id`),
  ADD KEY `proctad_nep_assignments_examination_school_id_foreign` (`examination_school_id`),
  ADD KEY `proctad_nep_assignments_assigned_by_foreign` (`assigned_by`),
  ADD KEY `proctad_nep_assignments_status_index` (`status`);

--
-- Indexes for table `proctad_nep_attendances`
--
ALTER TABLE `proctad_nep_attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nep_attendances_nep_venue_unique` (`non_exam_personnel_id`,`examination_school_id`),
  ADD KEY `proctad_nep_attendances_examination_school_id_foreign` (`examination_school_id`),
  ADD KEY `proctad_nep_attendances_scanned_by_foreign` (`scanned_by`);

--
-- Indexes for table `proctad_non_exam_personnel`
--
ALTER TABLE `proctad_non_exam_personnel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_non_exam_personnel_nep_id_unique` (`nep_id`),
  ADD KEY `proctad_non_exam_personnel_field_office_id_foreign` (`field_office_id`),
  ADD KEY `proctad_non_exam_personnel_created_by_foreign` (`created_by`),
  ADD KEY `proctad_non_exam_personnel_personnel_type_index` (`personnel_type`),
  ADD KEY `proctad_non_exam_personnel_is_active_index` (`is_active`);

--
-- Indexes for table `proctad_notifications`
--
ALTER TABLE `proctad_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proctad_notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`);

--
-- Indexes for table `proctad_oep_assignments`
--
ALTER TABLE `proctad_oep_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `oep_assignments_oep_venue_unique` (`other_examination_personnel_id`,`examination_school_id`),
  ADD KEY `proctad_oep_assignments_examination_school_id_foreign` (`examination_school_id`),
  ADD KEY `proctad_oep_assignments_assigned_by_foreign` (`assigned_by`),
  ADD KEY `proctad_oep_assignments_status_index` (`status`);

--
-- Indexes for table `proctad_oep_attendances`
--
ALTER TABLE `proctad_oep_attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `oep_attendances_oep_venue_unique` (`other_examination_personnel_id`,`examination_school_id`),
  ADD KEY `proctad_oep_attendances_examination_school_id_foreign` (`examination_school_id`),
  ADD KEY `proctad_oep_attendances_scanned_by_foreign` (`scanned_by`);

--
-- Indexes for table `proctad_other_examination_personnel`
--
ALTER TABLE `proctad_other_examination_personnel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_other_examination_personnel_oep_id_unique` (`oep_id`),
  ADD KEY `proctad_other_examination_personnel_field_office_id_foreign` (`field_office_id`),
  ADD KEY `proctad_other_examination_personnel_created_by_foreign` (`created_by`),
  ADD KEY `proctad_other_examination_personnel_personnel_type_index` (`personnel_type`),
  ADD KEY `proctad_other_examination_personnel_is_active_index` (`is_active`);

--
-- Indexes for table `proctad_password_reset_tokens`
--
ALTER TABLE `proctad_password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `proctad_schools`
--
ALTER TABLE `proctad_schools`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proctad_schools_field_office_id_foreign` (`field_office_id`),
  ADD KEY `proctad_schools_is_active_index` (`is_active`);

--
-- Indexes for table `proctad_sessions`
--
ALTER TABLE `proctad_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proctad_sessions_user_id_index` (`user_id`),
  ADD KEY `proctad_sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `proctad_settings`
--
ALTER TABLE `proctad_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_settings_key_unique` (`key`),
  ADD KEY `proctad_settings_updated_by_foreign` (`updated_by`);

--
-- Indexes for table `proctad_signatories`
--
ALTER TABLE `proctad_signatories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proctad_signatories_field_office_id_foreign` (`field_office_id`);

--
-- Indexes for table `proctad_trainings`
--
ALTER TABLE `proctad_trainings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proctad_trainings_training_date_index` (`training_date`);

--
-- Indexes for table `proctad_training_assignments`
--
ALTER TABLE `proctad_training_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_training_assignments_training_id_member_id_unique` (`training_id`,`member_id`),
  ADD KEY `proctad_training_assignments_member_id_foreign` (`member_id`),
  ADD KEY `proctad_training_assignments_field_office_id_foreign` (`field_office_id`),
  ADD KEY `proctad_training_assignments_attendance_confirmed_by_foreign` (`attendance_confirmed_by`);

--
-- Indexes for table `proctad_users`
--
ALTER TABLE `proctad_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `proctad_users_email_unique` (`email`),
  ADD UNIQUE KEY `proctad_users_google_id_unique` (`google_id`),
  ADD UNIQUE KEY `proctad_users_username_unique` (`username`),
  ADD KEY `proctad_users_field_office_id_foreign` (`field_office_id`),
  ADD KEY `proctad_users_role_index` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `other_examination_personnel`
--
ALTER TABLE `other_examination_personnel`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_assignment_confirmations`
--
ALTER TABLE `proctad_assignment_confirmations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_audit_logs`
--
ALTER TABLE `proctad_audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_certificates`
--
ALTER TABLE `proctad_certificates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_email_logs`
--
ALTER TABLE `proctad_email_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_email_templates`
--
ALTER TABLE `proctad_email_templates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_examinations`
--
ALTER TABLE `proctad_examinations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_examination_school`
--
ALTER TABLE `proctad_examination_school`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_exam_assignments`
--
ALTER TABLE `proctad_exam_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_exam_assignment_attendances`
--
ALTER TABLE `proctad_exam_assignment_attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_exam_assignment_schools`
--
ALTER TABLE `proctad_exam_assignment_schools`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_exam_rooms`
--
ALTER TABLE `proctad_exam_rooms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_exam_types`
--
ALTER TABLE `proctad_exam_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_failed_jobs`
--
ALTER TABLE `proctad_failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_fee_schedules`
--
ALTER TABLE `proctad_fee_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_field_offices`
--
ALTER TABLE `proctad_field_offices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_jobs`
--
ALTER TABLE `proctad_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_letterheads`
--
ALTER TABLE `proctad_letterheads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_members`
--
ALTER TABLE `proctad_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_member_requirements`
--
ALTER TABLE `proctad_member_requirements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_migrations`
--
ALTER TABLE `proctad_migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_nep_assignments`
--
ALTER TABLE `proctad_nep_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_nep_attendances`
--
ALTER TABLE `proctad_nep_attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_non_exam_personnel`
--
ALTER TABLE `proctad_non_exam_personnel`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_oep_assignments`
--
ALTER TABLE `proctad_oep_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_oep_attendances`
--
ALTER TABLE `proctad_oep_attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_other_examination_personnel`
--
ALTER TABLE `proctad_other_examination_personnel`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_schools`
--
ALTER TABLE `proctad_schools`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_settings`
--
ALTER TABLE `proctad_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_signatories`
--
ALTER TABLE `proctad_signatories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_trainings`
--
ALTER TABLE `proctad_trainings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_training_assignments`
--
ALTER TABLE `proctad_training_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `proctad_users`
--
ALTER TABLE `proctad_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `proctad_member_attendance_history`
--
DROP TABLE IF EXISTS `proctad_member_attendance_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `proctad_member_attendance_history`  AS SELECT `pm`.`proctad_id` AS `proctad_id`, concat(`pm`.`first_name`,' ',ifnull(`pm`.`middle_name`,''),' ',`pm`.`last_name`,' ',ifnull(`pm`.`suffix`,'')) AS `member_name`, `pm`.`field_office_id` AS `field_office_id`, count(`ta`.`attendance_id`) AS `total_trainings`, sum(case when `ta`.`attendance_status` = 'present' then 1 else 0 end) AS `trainings_attended`, sum(case when `ta`.`qr_scan_timestamp` is not null then 1 else 0 end) AS `qr_scans_used`, max(`ta`.`created_at`) AS `last_training_date`, round(sum(case when `ta`.`attendance_status` = 'present' then 1 else 0 end) / count(`ta`.`attendance_id`) * 100,2) AS `attendance_rate` FROM (`proctad_members` `pm` left join `proctad_training_attendance` `ta` on(`pm`.`proctad_id` = `ta`.`proctad_id`)) GROUP BY `pm`.`proctad_id`, concat(`pm`.`first_name`,' ',ifnull(`pm`.`middle_name`,''),' ',`pm`.`last_name`,' ',ifnull(`pm`.`suffix`,'')), `pm`.`field_office_id` ;

-- --------------------------------------------------------

--
-- Structure for view `proctad_school_statistics`
--
DROP TABLE IF EXISTS `proctad_school_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `proctad_school_statistics`  AS SELECT `es`.`exam_school_id` AS `school_id`, `es`.`exam_id` AS `exam_id`, `sc`.`school_name` AS `school_name`, count(distinct `r`.`exam_room_id`) AS `total_rooms`, sum(`r`.`capacity`) AS `total_capacity`, count(distinct `er`.`exam_room_id`) AS `configured_rooms`, count(distinct `sa`.`assignment_id`) AS `total_assigned`, sum(case when `sa`.`assignment_status` = 'confirmed' then 1 else 0 end) AS `confirmed_assignments`, sum(case when `sa`.`assignment_status` = 'pending' then 1 else 0 end) AS `pending_assignments`, round(count(distinct `sa`.`assignment_id`) / nullif(count(distinct `r`.`exam_room_id`),0) * 100,2) AS `assignment_progress`, CASE WHEN count(distinct `sa`.`assignment_id`) >= count(distinct `r`.`exam_room_id`) THEN 'ready' WHEN count(distinct `sa`.`assignment_id`) >= count(distinct `r`.`exam_room_id`) * 0.5 THEN 'partial' ELSE 'insufficient' END AS `readiness_status` FROM ((((`proctad_exam_schools` `es` join `proctad_schools` `sc` on(`es`.`school_id` = `sc`.`school_id`)) left join `proctad_exam_rooms` `r` on(`es`.`exam_school_id` = `r`.`school_id`)) left join `proctad_exam_rooms` `er` on(`es`.`exam_school_id` = `er`.`school_id`)) left join `proctad_school_assignments` `sa` on(`es`.`exam_school_id` = `sa`.`school_id`)) GROUP BY `es`.`exam_school_id`, `es`.`exam_id`, `sc`.`school_name` ;

-- --------------------------------------------------------

--
-- Structure for view `proctad_training_attendance_stats`
--
DROP TABLE IF EXISTS `proctad_training_attendance_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `proctad_training_attendance_stats`  AS SELECT `tr`.`training_id` AS `training_id`, `tr`.`training_title` AS `training_title`, `tr`.`training_date` AS `training_date`, `tr`.`field_office_id` AS `field_office_id`, count(`ta`.`attendance_id`) AS `total_registered`, sum(case when `ta`.`attendance_status` = 'present' then 1 else 0 end) AS `total_present`, sum(case when `ta`.`attendance_status` = 'absent' then 1 else 0 end) AS `total_absent`, sum(case when `ta`.`qr_scan_timestamp` is not null then 1 else 0 end) AS `qr_scanned`, sum(case when `ta`.`scan_method` = 'manual' then 1 else 0 end) AS `manual_entry`, round(sum(case when `ta`.`attendance_status` = 'present' then 1 else 0 end) / count(`ta`.`attendance_id`) * 100,2) AS `attendance_rate` FROM (`proctad_training_records` `tr` left join `proctad_training_attendance` `ta` on(`tr`.`training_id` = `ta`.`training_id`)) GROUP BY `tr`.`training_id`, `tr`.`training_title`, `tr`.`training_date`, `tr`.`field_office_id` ;

-- --------------------------------------------------------

--
-- Structure for view `view_available_proctads_for_exam`
--
DROP TABLE IF EXISTS `view_available_proctads_for_exam`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY INVOKER VIEW `view_available_proctads_for_exam`  AS SELECT DISTINCT `tr`.`training_id` AS `briefing_id`, `tr`.`linked_exam_id` AS `exam_id`, `m`.`proctad_id` AS `proctad_id`, concat(`m`.`last_name`,', ',`m`.`first_name`,' ',ifnull(`m`.`middle_name`,'')) AS `full_name`, `m`.`agency` AS `agency`, `m`.`position` AS `position`, `m`.`contact_number` AS `contact_number`, `m`.`email` AS `email`, `m`.`field_office_id` AS `field_office_id`, `ta`.`time_in` AS `briefing_time_in`, `ta`.`qr_scan_timestamp` AS `briefing_qr_scan` FROM ((`proctad_training_records` `tr` join `proctad_training_attendance` `ta` on(`tr`.`training_id` = `ta`.`training_id` and `ta`.`attendance_status` = 'present')) join `proctad_members` `m` on(`ta`.`proctad_id` = `m`.`proctad_id`)) WHERE `tr`.`training_type` = 'Briefing' AND `tr`.`linked_exam_id` is not null AND `m`.`accreditation_status` = 'active' AND !exists(select 1 from `proctad_school_assignments` `sa` where `sa`.`proctad_id` = `m`.`proctad_id` AND `sa`.`exam_id` = `tr`.`linked_exam_id` AND `sa`.`assignment_status` in ('pending','confirmed') limit 1) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `proctad_assignment_confirmations`
--
ALTER TABLE `proctad_assignment_confirmations`
  ADD CONSTRAINT `proctad_assignment_confirmations_exam_assignment_id_foreign` FOREIGN KEY (`exam_assignment_id`) REFERENCES `proctad_exam_assignments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctad_audit_logs`
--
ALTER TABLE `proctad_audit_logs`
  ADD CONSTRAINT `proctad_audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_certificates`
--
ALTER TABLE `proctad_certificates`
  ADD CONSTRAINT `proctad_certificates_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `proctad_certificates_field_office_id_foreign` FOREIGN KEY (`field_office_id`) REFERENCES `proctad_field_offices` (`id`),
  ADD CONSTRAINT `proctad_certificates_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `proctad_members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_certificates_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_email_logs`
--
ALTER TABLE `proctad_email_logs`
  ADD CONSTRAINT `proctad_email_logs_sent_by_foreign` FOREIGN KEY (`sent_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_examinations`
--
ALTER TABLE `proctad_examinations`
  ADD CONSTRAINT `proctad_examinations_exam_type_id_foreign` FOREIGN KEY (`exam_type_id`) REFERENCES `proctad_exam_types` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_examination_school`
--
ALTER TABLE `proctad_examination_school`
  ADD CONSTRAINT `proctad_examination_school_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `proctad_examination_school_examination_id_foreign` FOREIGN KEY (`examination_id`) REFERENCES `proctad_examinations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_examination_school_school_id_foreign` FOREIGN KEY (`school_id`) REFERENCES `proctad_schools` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctad_exam_assignments`
--
ALTER TABLE `proctad_exam_assignments`
  ADD CONSTRAINT `proctad_exam_assignments_attendance_confirmed_by_foreign` FOREIGN KEY (`attendance_confirmed_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `proctad_exam_assignments_exam_room_id_foreign` FOREIGN KEY (`exam_room_id`) REFERENCES `proctad_exam_rooms` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `proctad_exam_assignments_examination_id_foreign` FOREIGN KEY (`examination_id`) REFERENCES `proctad_examinations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_exam_assignments_examination_school_id_foreign` FOREIGN KEY (`examination_school_id`) REFERENCES `proctad_examination_school` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `proctad_exam_assignments_field_office_id_foreign` FOREIGN KEY (`field_office_id`) REFERENCES `proctad_field_offices` (`id`),
  ADD CONSTRAINT `proctad_exam_assignments_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `proctad_members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctad_exam_assignment_attendances`
--
ALTER TABLE `proctad_exam_assignment_attendances`
  ADD CONSTRAINT `eaa_examination_school_id_foreign` FOREIGN KEY (`examination_school_id`) REFERENCES `proctad_examination_school` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_exam_assignment_attendances_exam_assignment_id_foreign` FOREIGN KEY (`exam_assignment_id`) REFERENCES `proctad_exam_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_exam_assignment_attendances_scanned_by_foreign` FOREIGN KEY (`scanned_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_exam_assignment_schools`
--
ALTER TABLE `proctad_exam_assignment_schools`
  ADD CONSTRAINT `proctad_exam_assignment_schools_exam_assignment_id_foreign` FOREIGN KEY (`exam_assignment_id`) REFERENCES `proctad_exam_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_exam_assignment_schools_examination_school_id_foreign` FOREIGN KEY (`examination_school_id`) REFERENCES `proctad_examination_school` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctad_exam_rooms`
--
ALTER TABLE `proctad_exam_rooms`
  ADD CONSTRAINT `proctad_exam_rooms_examination_school_id_foreign` FOREIGN KEY (`examination_school_id`) REFERENCES `proctad_examination_school` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctad_fee_schedules`
--
ALTER TABLE `proctad_fee_schedules`
  ADD CONSTRAINT `proctad_fee_schedules_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_letterheads`
--
ALTER TABLE `proctad_letterheads`
  ADD CONSTRAINT `proctad_letterheads_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_members`
--
ALTER TABLE `proctad_members`
  ADD CONSTRAINT `proctad_members_field_office_id_foreign` FOREIGN KEY (`field_office_id`) REFERENCES `proctad_field_offices` (`id`),
  ADD CONSTRAINT `proctad_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_member_requirements`
--
ALTER TABLE `proctad_member_requirements`
  ADD CONSTRAINT `proctad_member_requirements_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `proctad_members` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctad_nep_assignments`
--
ALTER TABLE `proctad_nep_assignments`
  ADD CONSTRAINT `proctad_nep_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `proctad_nep_assignments_examination_school_id_foreign` FOREIGN KEY (`examination_school_id`) REFERENCES `proctad_examination_school` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_nep_assignments_non_exam_personnel_id_foreign` FOREIGN KEY (`non_exam_personnel_id`) REFERENCES `proctad_non_exam_personnel` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctad_nep_attendances`
--
ALTER TABLE `proctad_nep_attendances`
  ADD CONSTRAINT `proctad_nep_attendances_examination_school_id_foreign` FOREIGN KEY (`examination_school_id`) REFERENCES `proctad_examination_school` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_nep_attendances_non_exam_personnel_id_foreign` FOREIGN KEY (`non_exam_personnel_id`) REFERENCES `proctad_non_exam_personnel` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_nep_attendances_scanned_by_foreign` FOREIGN KEY (`scanned_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_non_exam_personnel`
--
ALTER TABLE `proctad_non_exam_personnel`
  ADD CONSTRAINT `proctad_non_exam_personnel_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `proctad_non_exam_personnel_field_office_id_foreign` FOREIGN KEY (`field_office_id`) REFERENCES `proctad_field_offices` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_oep_assignments`
--
ALTER TABLE `proctad_oep_assignments`
  ADD CONSTRAINT `proctad_oep_assignments_assigned_by_foreign` FOREIGN KEY (`assigned_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `proctad_oep_assignments_examination_school_id_foreign` FOREIGN KEY (`examination_school_id`) REFERENCES `proctad_examination_school` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_oep_assignments_other_examination_personnel_id_foreign` FOREIGN KEY (`other_examination_personnel_id`) REFERENCES `proctad_other_examination_personnel` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctad_oep_attendances`
--
ALTER TABLE `proctad_oep_attendances`
  ADD CONSTRAINT `proctad_oep_attendances_examination_school_id_foreign` FOREIGN KEY (`examination_school_id`) REFERENCES `proctad_examination_school` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_oep_attendances_other_examination_personnel_id_foreign` FOREIGN KEY (`other_examination_personnel_id`) REFERENCES `proctad_other_examination_personnel` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_oep_attendances_scanned_by_foreign` FOREIGN KEY (`scanned_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_other_examination_personnel`
--
ALTER TABLE `proctad_other_examination_personnel`
  ADD CONSTRAINT `proctad_other_examination_personnel_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `proctad_other_examination_personnel_field_office_id_foreign` FOREIGN KEY (`field_office_id`) REFERENCES `proctad_field_offices` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_schools`
--
ALTER TABLE `proctad_schools`
  ADD CONSTRAINT `proctad_schools_field_office_id_foreign` FOREIGN KEY (`field_office_id`) REFERENCES `proctad_field_offices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctad_settings`
--
ALTER TABLE `proctad_settings`
  ADD CONSTRAINT `proctad_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `proctad_signatories`
--
ALTER TABLE `proctad_signatories`
  ADD CONSTRAINT `proctad_signatories_field_office_id_foreign` FOREIGN KEY (`field_office_id`) REFERENCES `proctad_field_offices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctad_training_assignments`
--
ALTER TABLE `proctad_training_assignments`
  ADD CONSTRAINT `proctad_training_assignments_attendance_confirmed_by_foreign` FOREIGN KEY (`attendance_confirmed_by`) REFERENCES `proctad_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `proctad_training_assignments_field_office_id_foreign` FOREIGN KEY (`field_office_id`) REFERENCES `proctad_field_offices` (`id`),
  ADD CONSTRAINT `proctad_training_assignments_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `proctad_members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `proctad_training_assignments_training_id_foreign` FOREIGN KEY (`training_id`) REFERENCES `proctad_trainings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `proctad_users`
--
ALTER TABLE `proctad_users`
  ADD CONSTRAINT `proctad_users_field_office_id_foreign` FOREIGN KEY (`field_office_id`) REFERENCES `proctad_field_offices` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
