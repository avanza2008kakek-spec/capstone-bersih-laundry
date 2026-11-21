-- ============================================================================
-- SKRIP SQL LENGKAP PEMBUATAN DATABASE & TABEL "BERSIH LAUNDRY" (LARAVEL 12)
-- ============================================================================
-- Cara penggunaan di phpMyAdmin / HeidiSQL / MySQL CLI:
-- 1. Buat atau pilih database MySQL Anda (misal: `laundry` atau database hosting).
-- 2. Buka tab "SQL" atau "Import", lalu jalankan/unggah file ini.
-- 3. Menggunakan CREATE TABLE IF NOT EXISTS sehingga aman dijalankan pada
--    database kosong maupun database yang sudah memiliki sebagian tabel.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- 1. TABEL `users` (Akun Staf: Owner, Kasir, Operator, Kurir)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(255)    NOT NULL,
  `email`             VARCHAR(255)    NOT NULL,
  `phone`             VARCHAR(30)     NULL,
  `role`              VARCHAR(20)     NOT NULL DEFAULT 'kasir', -- owner | kasir | operator | kurir
  `email_verified_at` TIMESTAMP       NULL,
  `password`          VARCHAR(255)    NOT NULL,
  `remember_token`    VARCHAR(100)    NULL,
  `created_at`        TIMESTAMP       NULL,
  `updated_at`        TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. TABEL `customers` (Data Pelanggan & Titik Peta Rumah)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `customers` (
  `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`              VARCHAR(255)    NOT NULL,
  `phone`             VARCHAR(30)     NOT NULL,
  `address`           TEXT            NULL,
  `maps_url`          VARCHAR(500)    NULL,
  `phone_verified_at` TIMESTAMP       NULL,
  `created_at`        TIMESTAMP       NULL,
  `updated_at`        TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `customers_phone_index` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. TABEL `phone_verifications` (Kode Verifikasi OTP 6 Digit WhatsApp)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `phone_verifications` (
  `id`             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `phone`          VARCHAR(20)      NOT NULL,               -- Nomor WA format 628xxx
  `name`           VARCHAR(100)     NOT NULL,               -- Nama pendaftar
  `code_hash`      VARCHAR(255)     NOT NULL,               -- Hash kode OTP 6 digit
  `attempts`       TINYINT UNSIGNED NOT NULL DEFAULT 0,     -- Jumlah percobaan salah
  `hub_request_id` VARCHAR(64)      NULL,                   -- ID permintaan dari Verification Hub
  `expires_at`     DATETIME         NOT NULL,               -- Waktu kedaluwarsa kode OTP
  `verified_at`    TIMESTAMP        NULL,                   -- Waktu berhasil diverifikasi
  `created_at`     TIMESTAMP        NULL,
  `updated_at`     TIMESTAMP        NULL,
  PRIMARY KEY (`id`),
  KEY `phone_verifications_phone_index` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. TABEL `service_categories` (Kategori Layanan Laundry)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_categories` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)    NOT NULL,
  `description` VARCHAR(255)    NULL,
  `created_at`  TIMESTAMP       NULL,
  `updated_at`  TIMESTAMP       NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. TABEL `services` (Paket Layanan, Satuan, Tarif & Durasi Pengerjaan)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `services` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`    BIGINT UNSIGNED NOT NULL,
  `name`           VARCHAR(255)    NOT NULL,
  `unit`           VARCHAR(20)     NOT NULL DEFAULT 'kg',   -- kg | pcs | meter | pasang
  `price`          DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `duration_hours` INT UNSIGNED    NOT NULL DEFAULT 48,     -- Estimasi selesai dalam jam
  `is_active`      TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP       NULL,
  `updated_at`     TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `services_category_id_foreign` (`category_id`),
  CONSTRAINT `services_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. TABEL `orders` (Kepala Nota Transaksi Cucian)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number`  VARCHAR(50)     NOT NULL,               -- Contoh: INV-20261117-001
  `customer_id`     BIGINT UNSIGNED NOT NULL,
  `user_id`         BIGINT UNSIGNED NOT NULL,               -- Kasir/Owner pembuat nota
  `order_date`      DATETIME        NOT NULL,
  `estimate_date`   DATETIME        NOT NULL,
  `completion_date` DATETIME        NULL,
  `total_amount`    DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `discount`        DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `final_amount`    DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `paid_amount`     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `payment_status`  VARCHAR(20)     NOT NULL DEFAULT 'unpaid',   -- unpaid | partial | paid
  `order_status`    VARCHAR(30)     NOT NULL DEFAULT 'received', -- received | washing | drying | ironing | ready_for_pickup | completed
  `notes`           TEXT            NULL,
  `created_at`      TIMESTAMP       NULL,
  `updated_at`      TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `orders_invoice_number_unique` (`invoice_number`),
  KEY `orders_customer_id_foreign` (`customer_id`),
  KEY `orders_user_id_foreign` (`user_id`),
  KEY `orders_order_status_index` (`order_status`),
  KEY `orders_payment_status_index` (`payment_status`),
  CONSTRAINT `orders_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. TABEL `order_items` (Rincian Layanan & Berat Aktual per Nota)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`   BIGINT UNSIGNED NOT NULL,
  `service_id` BIGINT UNSIGNED NOT NULL,
  `quantity`   DECIMAL(8,2)    NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `subtotal`   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `notes`      VARCHAR(255)    NULL,
  `created_at` TIMESTAMP       NULL,
  `updated_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_service_id_foreign` (`service_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. TABEL `order_status_logs` (Riwayat Perubahan Tahap Status Cucian)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_status_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`   BIGINT UNSIGNED NOT NULL,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `status`     VARCHAR(50)     NOT NULL,
  `notes`      VARCHAR(255)    NULL,
  `created_at` TIMESTAMP       NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_status_logs_order_id_foreign` (`order_id`),
  KEY `order_status_logs_user_id_foreign` (`user_id`),
  CONSTRAINT `order_status_logs_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_status_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. TABEL `payments` (Riwayat Pembayaran Masuk: Tunai, QRIS, Transfer)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_id`       BIGINT UNSIGNED NOT NULL,
  `user_id`        BIGINT UNSIGNED NOT NULL,
  `payment_date`   DATETIME        NOT NULL,
  `amount`         DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(20)     NOT NULL DEFAULT 'cash',         -- cash | qris | transfer
  `payment_type`   VARCHAR(30)     NOT NULL DEFAULT 'full_payment', -- down_payment | full_payment | settlement
  `proof_image`    VARCHAR(255)    NULL,
  `notes`          VARCHAR(255)    NULL,
  `created_at`     TIMESTAMP       NULL,
  `updated_at`     TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `payments_order_id_foreign` (`order_id`),
  KEY `payments_user_id_foreign` (`user_id`),
  KEY `payments_payment_date_index` (`payment_date`),
  CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 10. TABEL `expense_categories` (Kategori Biaya Operasional)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100)    NOT NULL,
  `created_at` TIMESTAMP       NULL,
  `updated_at` TIMESTAMP       NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 11. TABEL `expenses` (Pengeluaran Kas Operasional Harian)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `expense_category_id` BIGINT UNSIGNED NOT NULL,
  `user_id`             BIGINT UNSIGNED NOT NULL,
  `expense_date`        DATE            NOT NULL,
  `amount`              DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `description`         TEXT            NOT NULL,
  `proof_image`         VARCHAR(255)    NULL,
  `created_at`          TIMESTAMP       NULL,
  `updated_at`          TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_expense_category_id_foreign` (`expense_category_id`),
  KEY `expenses_user_id_foreign` (`user_id`),
  KEY `expenses_expense_date_index` (`expense_date`),
  CONSTRAINT `expenses_expense_category_id_foreign` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `expenses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 12. TABEL `reservations` (Reservasi Jemput dari Pelanggan + Perkiraan Berat & Harga)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reservations` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id`      BIGINT UNSIGNED NOT NULL,
  `service`          VARCHAR(100)    NOT NULL,
  `estimated_weight` VARCHAR(50)     NULL,                  -- Perkiraan berat/jumlah dari pelanggan
  `estimated_price`  VARCHAR(50)     NULL,                  -- Perkiraan harga cucian otomatis
  `pickup_date`      DATE            NOT NULL,
  `address`          TEXT            NOT NULL,
  `maps_url`         VARCHAR(500)    NULL,
  `pickup_fee`       VARCHAR(100)    NULL,                  -- Ongkos jemput dari jarak peta
  `notes`            VARCHAR(200)    NULL,
  `status`           VARCHAR(20)     NOT NULL DEFAULT 'new',-- new | confirmed | picked_up | cancelled
  `handled_by`       BIGINT UNSIGNED NULL,
  `handled_at`       TIMESTAMP       NULL,
  `created_at`       TIMESTAMP       NULL,
  `updated_at`       TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `reservations_customer_id_index` (`customer_id`),
  KEY `reservations_status_index` (`status`),
  CONSTRAINT `reservations_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 13. TABEL `courier_tasks` (Tugas Kurir Jemput & Antar)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `courier_tasks` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`           VARCHAR(10)     NOT NULL,                    -- pickup | delivery
  `reservation_id` BIGINT UNSIGNED NULL,
  `order_id`       BIGINT UNSIGNED NULL,
  `customer_id`    BIGINT UNSIGNED NOT NULL,
  `courier_id`     BIGINT UNSIGNED NOT NULL,                    -- Akun user role kurir
  `assigned_by`    BIGINT UNSIGNED NOT NULL,                    -- Kasir/Owner yang menugaskan
  `scheduled_date` DATE            NOT NULL,
  `address`        TEXT            NOT NULL,
  `maps_url`       VARCHAR(500)    NULL,
  `notes`          VARCHAR(255)    NULL,
  `status`         VARCHAR(20)     NOT NULL DEFAULT 'assigned', -- assigned | on_the_way | done | failed | cancelled
  `courier_notes`  VARCHAR(255)    NULL,
  `started_at`     TIMESTAMP       NULL,
  `finished_at`    TIMESTAMP       NULL,
  `handover_at`    TIMESTAMP       NULL,                        -- Waktu serah terima kurir <-> kasir
  `created_at`     TIMESTAMP       NULL,
  `updated_at`     TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `courier_tasks_reservation_id_index` (`reservation_id`),
  KEY `courier_tasks_order_id_index` (`order_id`),
  KEY `courier_tasks_courier_id_index` (`courier_id`),
  KEY `courier_tasks_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 14. TABEL `courier_photos` (4 Bukti Foto Kamera Kurir & Kasir — MEDIUMBLOB)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `courier_photos` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `courier_task_id` BIGINT UNSIGNED NOT NULL,
  `kind`            VARCHAR(20)     NOT NULL DEFAULT 'customer', -- customer | handover
  `mime`            VARCHAR(30)     NOT NULL DEFAULT 'image/jpeg',
  `size`            INT UNSIGNED    NOT NULL,
  `data`            MEDIUMBLOB      NOT NULL,
  `created_at`      TIMESTAMP       NULL,
  `updated_at`      TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `courier_photos_task_id_idx` (`courier_task_id`),
  UNIQUE KEY `courier_photos_task_kind_unique` (`courier_task_id`, `kind`),
  CONSTRAINT `courier_photos_task_foreign` FOREIGN KEY (`courier_task_id`) REFERENCES `courier_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 15. TABEL `activity_logs` (Log Aktivitas Semua User + Jenis Perangkat & IP)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NULL,
  `customer_id` BIGINT UNSIGNED NULL,
  `actor_name`  VARCHAR(120)    NOT NULL,
  `actor_role`  VARCHAR(30)     NOT NULL, -- owner | kasir | operator | kurir | pelanggan
  `category`    VARCHAR(40)     NOT NULL,
  `action`      VARCHAR(120)    NOT NULL,
  `description` TEXT            NULL,
  `subject_url` VARCHAR(255)    NULL,
  `ip_address`  VARCHAR(45)     NULL,
  `device`      VARCHAR(150)    NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `activity_logs_user_id_index` (`user_id`),
  KEY `activity_logs_customer_id_index` (`customer_id`),
  KEY `activity_logs_actor_role_index` (`actor_role`),
  KEY `activity_logs_category_index` (`category`),
  KEY `activity_logs_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- TABEL PENDUKUNG BAWAAN LARAVEL (Sessions, Cache, Jobs, Migrations)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email`      VARCHAR(255) NOT NULL,
  `token`      VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP    NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id`            VARCHAR(255)    NOT NULL,
  `user_id`       BIGINT UNSIGNED NULL,
  `ip_address`    VARCHAR(45)     NULL,
  `user_agent`    TEXT            NULL,
  `payload`       LONGTEXT        NOT NULL,
  `last_activity` INT             NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache` (
  `key`        VARCHAR(255) NOT NULL,
  `value`      MEDIUMTEXT   NOT NULL,
  `expiration` INT          NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key`        VARCHAR(255) NOT NULL,
  `owner`      VARCHAR(255) NOT NULL,
  `expiration` INT          NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migrations` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` VARCHAR(255) NOT NULL,
  `batch`     INT          NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- DATA AWAL (SEEDER OPSIONAL: Kategori Layanan, Paket Layanan & Kategori Biaya)
-- Hanya diisi jika tabel masih kosong
-- ----------------------------------------------------------------------------
INSERT INTO `service_categories` (`id`, `name`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
  SELECT 1 AS `id`, 'Laundry Kiloan' AS `name`, 'Layanan cuci harian dihitung per kilogram' AS `description`, NOW() AS `created_at`, NOW() AS `updated_at`
  UNION ALL
  SELECT 2, 'Laundry Satuan', 'Pakaian khusus, jas, bedcover, dan sepatu dihitung per potong/pasang', NOW(), NOW()
) AS `tmp`
WHERE NOT EXISTS (SELECT 1 FROM `service_categories` LIMIT 1);

INSERT INTO `services` (`category_id`, `name`, `unit`, `price`, `duration_hours`, `is_active`, `created_at`, `updated_at`)
SELECT * FROM (
  SELECT 1 AS `category_id`, 'Cuci Kering Lipat Reguler' AS `name`, 'kg' AS `unit`, 6000.00 AS `price`, 48 AS `duration_hours`, 1 AS `is_active`, NOW() AS `created_at`, NOW() AS `updated_at`
  UNION ALL
  SELECT 1, 'Cuci Kering Setrika Reguler', 'kg', 8000.00, 48, 1, NOW(), NOW()
  UNION ALL
  SELECT 1, 'Cuci Kilat Express 6 Jam', 'kg', 15000.00, 6, 1, NOW(), NOW()
  UNION ALL
  SELECT 1, 'Setrika Uap Saja', 'kg', 5000.00, 24, 1, NOW(), NOW()
  UNION ALL
  SELECT 2, 'Bedcover / Selimut Tebal', 'pcs', 25000.00, 72, 1, NOW(), NOW()
  UNION ALL
  SELECT 2, 'Jas / Blazer Setelan', 'pcs', 30000.00, 72, 1, NOW(), NOW()
  UNION ALL
  SELECT 2, 'Sepatu Sneakers Deep Clean', 'pasang', 35000.00, 72, 1, NOW(), NOW()
) AS `tmp`
WHERE NOT EXISTS (SELECT 1 FROM `services` LIMIT 1);

INSERT INTO `expense_categories` (`id`, `name`, `created_at`, `updated_at`)
SELECT * FROM (
  SELECT 1 AS `id`, 'Deterjen & Pewangi' AS `name`, NOW() AS `created_at`, NOW() AS `updated_at`
  UNION ALL
  SELECT 2, 'Listrik & Air', NOW(), NOW()
  UNION ALL
  SELECT 3, 'Plastik & Kemasan', NOW(), NOW()
  UNION ALL
  SELECT 4, 'Gaji & Operasional Kurir', NOW(), NOW()
) AS `tmp`
WHERE NOT EXISTS (SELECT 1 FROM `expense_categories` LIMIT 1);

SET FOREIGN_KEY_CHECKS = 1;
