-- =====================================================================
-- Kolom link lokasi Google Maps untuk alamat penjemputan pelanggan.
-- Jalankan di database website Laundry (u733698878_data), lewat
-- HeidiSQL (tab Query, F9) atau phpMyAdmin (tab SQL).
-- Isinya sama dengan migrasi 2026_10_29_000001_add_maps_url_to_customers_table.php
-- =====================================================================

-- (jika muncul error "Duplicate column", berarti kolom sudah ada; abaikan)
ALTER TABLE `customers` ADD COLUMN `maps_url` VARCHAR(500) NULL;
