-- Tabel reservasi dari website: jalankan SEKALI di database u733698878_data (HeidiSQL / phpMyAdmin).
-- Dipakai untuk notifikasi & menu "Reservasi" di Area Kasir.
CREATE TABLE IF NOT EXISTS `reservations` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` BIGINT UNSIGNED NOT NULL,                 -- Pelanggan yang reservasi
  `service`     VARCHAR(100)    NOT NULL,                 -- Layanan yang dipilih
  `estimated_weight` VARCHAR(50) NULL,                    -- Perkiraan berat/jumlah dari pelanggan
  `estimated_price`  VARCHAR(50) NULL,                    -- Perkiraan harga cucian
  `pickup_date` DATE            NOT NULL,                 -- Tanggal jemput yang diminta
  `address`     TEXT            NOT NULL,                 -- Alamat jemput saat reservasi dibuat
  `maps_url`    VARCHAR(500)    NULL,                     -- Lokasi Google Maps
  `pickup_fee`  VARCHAR(100)    NULL,                     -- Perkiraan ongkos jemput (teks)
  `notes`       VARCHAR(200)    NULL,                     -- Catatan pelanggan
  `status`      VARCHAR(20)     NOT NULL DEFAULT 'new',   -- new / confirmed / picked_up / cancelled
  `handled_by`  BIGINT UNSIGNED NULL,                     -- Staf yang terakhir memproses
  `handled_at`  TIMESTAMP       NULL,
  `created_at`  TIMESTAMP       NULL,
  `updated_at`  TIMESTAMP       NULL,
  PRIMARY KEY (`id`),
  KEY `reservations_customer_id_index` (`customer_id`),
  KEY `reservations_status_index` (`status`),
  CONSTRAINT `reservations_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jika tabel reservations sudah ada sebelumnya, jalankan perintah ini untuk menambah kolom perkiraan berat & harga:
ALTER TABLE `reservations`
  ADD COLUMN IF NOT EXISTS `estimated_weight` VARCHAR(50) NULL AFTER `service`,
  ADD COLUMN IF NOT EXISTS `estimated_price`  VARCHAR(50) NULL AFTER `estimated_weight`;
