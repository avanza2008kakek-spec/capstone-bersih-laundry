-- =====================================================================
-- Tabel untuk pendaftaran pelanggan dengan kode verifikasi WhatsApp
-- (kode dikirim lewat POSAJA Verification Hub).
-- Pakai file ini HANYA jika tidak bisa menjalankan "php artisan migrate"
-- (misal tanpa SSH). Impor lewat phpMyAdmin → pilih database → tab Import.
-- Isinya sama dengan migrasi 2026_10_28_000001_create_phone_verifications_table.php
-- =====================================================================

-- Kode verifikasi (OTP) pendaftaran
CREATE TABLE IF NOT EXISTS `phone_verifications` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `phone`          VARCHAR(20)  NOT NULL,               -- Nomor WA pendaftar 628xxx
  `name`           VARCHAR(100) NOT NULL,               -- Nama pendaftar
  `code_hash`      VARCHAR(255) NOT NULL,               -- Hash kode OTP (kode asli tidak disimpan)
  `attempts`       TINYINT UNSIGNED NOT NULL DEFAULT 0, -- Percobaan salah
  `hub_request_id` VARCHAR(64) NULL,                    -- ID permintaan dari Verification Hub
  `expires_at`     DATETIME NOT NULL,                   -- Batas waktu kode (DATETIME: tidak ikut berubah saat baris di-update)
  `verified_at`    TIMESTAMP NULL,                      -- Waktu berhasil diverifikasi
  `created_at`     TIMESTAMP NULL,
  `updated_at`     TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `phone_verifications_phone_index` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Penanda pelanggan yang nomor WA-nya sudah terverifikasi
-- (jika muncul error "Duplicate column", berarti kolom sudah ada; abaikan)
ALTER TABLE `customers` ADD COLUMN `phone_verified_at` TIMESTAMP NULL;

-- Catat migrasi supaya "php artisan migrate" di kemudian hari tidak membuat ulang tabel ini
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_28_000001_create_phone_verifications_table', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
