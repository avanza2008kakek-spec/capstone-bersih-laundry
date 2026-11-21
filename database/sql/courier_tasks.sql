-- Fitur KURIR (jemput & antar cucian + foto bukti): jalankan di database u733698878_data (HeidiSQL / phpMyAdmin).
-- Aman dijalankan ulang (tabel yang sudah ada tidak diubah).

-- 1. Pastikan kolom role bisa diisi "kurir" (jika sebelumnya ENUM owner/kasir/operator, diubah menjadi teks biasa)
ALTER TABLE `users` MODIFY `role` VARCHAR(20) NOT NULL DEFAULT 'kasir';

-- 2. Tabel tugas kurir
CREATE TABLE IF NOT EXISTS `courier_tasks` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type`           VARCHAR(10)     NOT NULL,                    -- pickup (jemput) / delivery (antar)
  `reservation_id` BIGINT UNSIGNED NULL,                        -- Untuk tugas jemput
  `order_id`       BIGINT UNSIGNED NULL,                        -- Untuk tugas antar
  `customer_id`    BIGINT UNSIGNED NOT NULL,
  `courier_id`     BIGINT UNSIGNED NOT NULL,                    -- Akun kurir yang ditugaskan
  `assigned_by`    BIGINT UNSIGNED NOT NULL,                    -- Kasir/owner yang menugaskan
  `scheduled_date` DATE            NOT NULL,                    -- Tanggal jemput/antar
  `address`        TEXT            NOT NULL,                    -- Alamat tujuan
  `maps_url`       VARCHAR(500)    NULL,                        -- Lokasi Google Maps tujuan
  `notes`          VARCHAR(255)    NULL,                        -- Pesan kasir untuk kurir
  `status`         VARCHAR(20)     NOT NULL DEFAULT 'assigned', -- assigned / on_the_way / done / failed / cancelled
  `courier_notes`  VARCHAR(255)    NULL,                        -- Catatan kurir / alasan gagal
  `started_at`     TIMESTAMP       NULL,
  `finished_at`    TIMESTAMP       NULL,
  `handover_at`    TIMESTAMP       NULL,                        -- Waktu serah terima antara kurir & kasir
  `created_at`     TIMESTAMP       NULL,
  `updated_at`     TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `courier_tasks_reservation_id_index` (`reservation_id`),
  KEY `courier_tasks_order_id_index` (`order_id`),
  KEY `courier_tasks_courier_id_index` (`courier_id`),
  KEY `courier_tasks_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Foto bukti jemput/antar & serah terima kasir-kurir, disimpan langsung di database (sudah diperkecil ± 50–300 KB per foto)
CREATE TABLE IF NOT EXISTS `courier_photos` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `courier_task_id` BIGINT UNSIGNED NOT NULL,                   -- Tugas kurir terkait
  `kind`            VARCHAR(20)     NOT NULL DEFAULT 'customer',-- customer (di pelanggan) / handover (serah terima kasir & kurir)
  `mime`            VARCHAR(30)     NOT NULL DEFAULT 'image/jpeg',
  `size`            INT UNSIGNED    NOT NULL,                   -- Ukuran foto (byte)
  `data`            MEDIUMBLOB      NOT NULL,                   -- Isi foto
  `created_at`      TIMESTAMP       NULL,
  `updated_at`      TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `courier_photos_task_id_idx` (`courier_task_id`),
  UNIQUE KEY `courier_photos_task_kind_unique` (`courier_task_id`, `kind`),
  CONSTRAINT `courier_photos_task_foreign` FOREIGN KEY (`courier_task_id`) REFERENCES `courier_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
