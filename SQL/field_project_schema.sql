-- ============================================================
-- field_project — Database Schema
-- Generated: 2026-03-17
-- MySQL 8.0 / utf8mb4
-- ============================================================
-- Usage: mysql -u<user> -p <database> < field_project_schema.sql
-- ============================================================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- ============================================================
-- Core lookup tables (no foreign-key dependencies)
-- ============================================================

DROP TABLE IF EXISTS `departments`;
CREATE TABLE `departments` (
  `id`   int          NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Users
-- ============================================================

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`                   int          NOT NULL AUTO_INCREMENT,
  `name`                 varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `username`             varchar(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password`             varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role`                 enum('admin','field','manager') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'field',
  `created_at`           timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `active`               tinyint(1)   DEFAULT '1',
  `department_id`        int          DEFAULT NULL,
  `marker_color`         varchar(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_delete_jobs`      tinyint(1)   DEFAULT '0',   -- legacy column; use user_permissions instead
  `can_manage_departments` tinyint(1) DEFAULT '0',
  `remember_token`       varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Granular permission system (added 2026-03)
-- Each row grants one named permission to one user.
-- Admin role bypasses all checks in hasPermission().
-- Available permission names are defined in includes/permissions.php.
-- ============================================================

DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE `user_permissions` (
  `id`         int          NOT NULL AUTO_INCREMENT,
  `user_id`    int          NOT NULL,
  `permission` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_permission` (`user_id`, `permission`),
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Jobs
-- ============================================================

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id`               int          NOT NULL AUTO_INCREMENT,
  `contract_number`  varchar(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `customer_id_card` varchar(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `assigned_to`      int          DEFAULT NULL,
  `last_updated_by`  int          DEFAULT NULL,
  `imported_by`      int          DEFAULT NULL,
  `product`          varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `location_info`    varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `location_area`    varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `zone`             varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `due_date`         varchar(25)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `overdue_period`   varchar(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `model`            varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `model_detail`     varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `color`            varchar(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `plate`            varchar(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `province`         varchar(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `os`               varchar(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status`           varchar(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `department_id`    int          DEFAULT NULL,
  `created_at`       datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `priority`         varchar(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'normal',
  `remark`           text         CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `job_order`        int          DEFAULT '0',
  `is_favorite`      tinyint(1)   DEFAULT '0',
  `auto_delete_days` tinyint unsigned DEFAULT NULL,
  `auto_delete_at`   datetime     DEFAULT NULL,
  `updated_at`       datetime     DEFAULT NULL,
  -- Return / revision tracking (added 2026-03)
  `returned_by`      int          DEFAULT NULL,
  `returned_at`      datetime     DEFAULT NULL,
  `revision_count`   int          DEFAULT '0',
  `return_reason`    text         CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  KEY `department_id`           (`department_id`),
  KEY `idx_jobs_assigned_to`    (`assigned_to`),
  KEY `idx_jobs_status`         (`status`),
  KEY `idx_auto_delete_at`      (`auto_delete_at`),
  KEY `idx_jobs_product`        (`product`),
  KEY `idx_jobs_contract`       (`contract_number`),
  KEY `idx_jobs_created_at`     (`created_at`),
  KEY `idx_jobs_updated_at`     (`updated_at`),
  CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Work check-in / check-out attendance (added 2026-03)
-- Stores each clock-in and clock-out event for field users.
-- One row per event; summary is computed on the fly.
-- ============================================================

DROP TABLE IF EXISTS `work_checkins`;
CREATE TABLE `work_checkins` (
  `id`         int          NOT NULL AUTO_INCREMENT,
  `user_id`    int          NOT NULL,
  `type`       enum('checkin','checkout') COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude`   decimal(10,8) DEFAULT NULL,
  `longitude`  decimal(11,8) DEFAULT NULL,
  `address`    text         COLLATE utf8mb4_unicode_ci,
  `note`       text         COLLATE utf8mb4_unicode_ci,
  `checked_at` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id`   (`user_id`),
  KEY `idx_checked_at` (`checked_at`),
  KEY `idx_user_date`  (`user_id`, `checked_at`),
  CONSTRAINT `fk_wc_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Department visibility (which department can see which)
-- ============================================================

DROP TABLE IF EXISTS `department_visibility`;
CREATE TABLE `department_visibility` (
  `id`                 int NOT NULL AUTO_INCREMENT,
  `from_department_id` int DEFAULT NULL,
  `from_user_id`       int DEFAULT NULL,
  `to_department_id`   int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `from_department_id` (`from_department_id`),
  KEY `to_department_id`   (`to_department_id`),
  CONSTRAINT `department_visibility_ibfk_1` FOREIGN KEY (`from_department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `department_visibility_ibfk_2` FOREIGN KEY (`to_department_id`)   REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Job activity logs
-- ============================================================

DROP TABLE IF EXISTS `job_logs`;
CREATE TABLE `job_logs` (
  `id`           int          NOT NULL AUTO_INCREMENT,
  `job_id`       int          DEFAULT NULL,
  `user_id`      int          DEFAULT NULL,
  `result`       varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note`         text         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `gps`          varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `images`       text         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at`   timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `log_time`     datetime     DEFAULT CURRENT_TIMESTAMP,
  `marker_color` varchar(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'blue',
  PRIMARY KEY (`id`),
  KEY `idx_joblogs_created_at` (`created_at`),
  KEY `idx_job_logs_job_id`    (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `job_edit_logs`;
CREATE TABLE `job_edit_logs` (
  `id`             int       NOT NULL AUTO_INCREMENT,
  `job_id`         int       NOT NULL,
  `edited_by`      int       NOT NULL,
  `change_summary` text      CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `edited_at`      timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `job_id`    (`job_id`),
  KEY `edited_by` (`edited_by`),
  CONSTRAINT `job_edit_logs_ibfk_1` FOREIGN KEY (`job_id`)    REFERENCES `jobs`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_edit_logs_ibfk_2` FOREIGN KEY (`edited_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `job_deletion_logs`;
CREATE TABLE `job_deletion_logs` (
  `id`             int          NOT NULL AUTO_INCREMENT,
  `job_id`         int          NOT NULL,
  `contract_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `product`        varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `location_info`  varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `assigned_to`    int          DEFAULT NULL,
  `status`         varchar(20)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deleted_by`     int          NOT NULL,
  `deleted_at`     timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delete_reason`  text         CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `deletion_type`  varchar(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'manual',
  `job_data`       longtext     CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  PRIMARY KEY (`id`),
  KEY `idx_deleted_by` (`deleted_by`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `idx_job_id`     (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `job_status_history`;
CREATE TABLE `job_status_history` (
  `id`          int         NOT NULL AUTO_INCREMENT,
  `job_id`      int         NOT NULL,
  `old_status`  varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `new_status`  varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `changed_by`  int         NOT NULL,
  `changed_at`  timestamp   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reason`      text        CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `action_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_job_id`     (`job_id`),
  KEY `idx_changed_by` (`changed_by`),
  KEY `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Job comments / chat
-- ============================================================

DROP TABLE IF EXISTS `job_comments`;
CREATE TABLE `job_comments` (
  `id`           int       NOT NULL AUTO_INCREMENT,
  `job_id`       int       NOT NULL,
  `user_id`      int       NOT NULL,
  `comment`      text      CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `comment_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'comment',
  `is_read`      tinyint(1)  DEFAULT '0',
  `read_at`      timestamp   NULL DEFAULT NULL,
  `created_at`   timestamp   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   timestamp   NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `parent_id`    int         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_job_id`    (`job_id`),
  KEY `idx_user_id`   (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_is_read`   (`is_read`),
  KEY `idx_parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
-- Security / auth
-- ============================================================

DROP TABLE IF EXISTS `login_logs`;
CREATE TABLE `login_logs` (
  `id`         int      NOT NULL AUTO_INCREMENT,
  `user_id`    int      NOT NULL,
  `ip_address` varchar(45)  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `user_agent` text         CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `login_time` datetime     DEFAULT CURRENT_TIMESTAMP,
  `type`       enum('login','logout','login_fail') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'login',
  PRIMARY KEY (`id`),
  KEY `user_id`        (`user_id`),
  KEY `idx_login_time` (`login_time`),
  KEY `idx_user_id`    (`user_id`),
  KEY `idx_type`       (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id`           int          NOT NULL AUTO_INCREMENT,
  `ip_address`   varchar(45)  NOT NULL,
  `username`     varchar(255) DEFAULT NULL,
  `attempted_at` datetime     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `allowed_ips`;
CREATE TABLE `allowed_ips` (
  `id`          int          NOT NULL AUTO_INCREMENT,
  `ip_address`  varchar(45)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at`  timestamp    NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address`   (`ip_address`),
  KEY          `ip_address_2` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `allowed_devices`;
CREATE TABLE `allowed_devices` (
  `id`           int          NOT NULL AUTO_INCREMENT,
  `device_token` varchar(255) NOT NULL,
  `description`  varchar(255) DEFAULT NULL,
  `user_agent`   text,
  `last_used_at` datetime     DEFAULT NULL,
  `created_at`   timestamp    NULL DEFAULT CURRENT_TIMESTAMP,
  `status`       varchar(20)  DEFAULT 'approved',
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_token` (`device_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

DROP TABLE IF EXISTS `device_invites`;
CREATE TABLE `device_invites` (
  `id`         int         NOT NULL AUTO_INCREMENT,
  `code`       varchar(64) NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `expires_at` datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================
-- Views
-- ============================================================

DROP VIEW IF EXISTS `vw_job_comments`;
CREATE VIEW `vw_job_comments` AS
  SELECT
    jc.id,
    jc.job_id,
    jc.user_id,
    jc.comment,
    jc.comment_type,
    jc.is_read,
    jc.read_at,
    jc.created_at,
    jc.updated_at,
    jc.parent_id,
    u.name  AS user_name,
    u.role  AS user_role,
    j.contract_number,
    j.product,
    j.location_info
  FROM job_comments jc
  LEFT JOIN users u ON jc.user_id = u.id
  LEFT JOIN jobs  j ON jc.job_id  = j.id
  ORDER BY jc.created_at;

DROP VIEW IF EXISTS `vw_job_deletion_logs`;
CREATE VIEW `vw_job_deletion_logs` AS
  SELECT
    jdl.id,
    jdl.job_id,
    jdl.contract_number,
    jdl.product,
    jdl.location_info,
    jdl.assigned_to,
    jdl.status,
    jdl.deleted_by,
    jdl.deleted_at,
    jdl.delete_reason,
    jdl.deletion_type,
    jdl.job_data,
    u.name  AS deleted_by_name,
    u.role  AS deleted_by_role,
    u2.name AS assigned_to_name
  FROM job_deletion_logs jdl
  LEFT JOIN users u  ON jdl.deleted_by  = u.id
  LEFT JOIN users u2 ON jdl.assigned_to = u2.id
  ORDER BY jdl.deleted_at DESC;

-- ============================================================
-- Stored Procedures
-- ============================================================

DROP PROCEDURE IF EXISTS `archive_old_job_logs`;
DELIMITER ;;
CREATE PROCEDURE `archive_old_job_logs`()
BEGIN
    -- Archives job_edit_logs older than 180 days into job_edit_logs_archive
    CREATE TABLE IF NOT EXISTS job_edit_logs_archive LIKE job_edit_logs;

    INSERT INTO job_edit_logs_archive
    SELECT * FROM job_edit_logs
    WHERE edited_at < DATE_SUB(NOW(), INTERVAL 180 DAY);

    DELETE FROM job_edit_logs
    WHERE edited_at < DATE_SUB(NOW(), INTERVAL 180 DAY);

    OPTIMIZE TABLE job_edit_logs;
END ;;
DELIMITER ;

DROP PROCEDURE IF EXISTS `archive_old_login_logs`;
DELIMITER ;;
CREATE PROCEDURE `archive_old_login_logs`()
BEGIN
    -- Archives login_logs older than 90 days into login_logs_archive
    CREATE TABLE IF NOT EXISTS login_logs_archive LIKE login_logs;

    INSERT INTO login_logs_archive
    SELECT * FROM login_logs
    WHERE login_time < DATE_SUB(NOW(), INTERVAL 90 DAY);

    DELETE FROM login_logs
    WHERE login_time < DATE_SUB(NOW(), INTERVAL 90 DAY);

    OPTIMIZE TABLE login_logs;
END ;;
DELIMITER ;

-- ============================================================

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
