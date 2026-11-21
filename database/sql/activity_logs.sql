-- Tabel log aktivitas seluruh pengguna (Owner, Kasir, Operator, Kurir, dan Pelanggan).
-- Tabel ini juga otomatis dibuat oleh aplikasi (ActivityLog::ensureSchema) jika belum ada.

CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `customer_id` BIGINT UNSIGNED NULL,
    `actor_name` VARCHAR(120) NOT NULL,
    `actor_role` VARCHAR(30) NOT NULL,
    `category` VARCHAR(40) NOT NULL,
    `action` VARCHAR(120) NOT NULL,
    `description` TEXT NULL,
    `subject_url` VARCHAR(255) NULL,
    `ip_address` VARCHAR(45) NULL,
    `device` VARCHAR(150) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `activity_logs_user_id_index` (`user_id`),
    INDEX `activity_logs_customer_id_index` (`customer_id`),
    INDEX `activity_logs_actor_role_index` (`actor_role`),
    INDEX `activity_logs_category_index` (`category`),
    INDEX `activity_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

