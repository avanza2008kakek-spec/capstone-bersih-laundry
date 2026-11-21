<?php

namespace App\Support;

/**
 * Pilihan tema warna web (menu owner → Pengaturan Usaha → Tema).
 * Setiap tema mengisi variabel warna CSS yang dipakai di semua halaman.
 * Nama variabel lama (--teal, --yellow, --purple, ...) tetap dipakai supaya tampilan lama tidak perlu diubah.
 */
class Themes
{
    public const ALL = [
        'pastel' => [
            'name' => 'Pastel Pink & Mint',
            'vars' => [
                '--teal' => '#47a990',      // Warna utama: tombol, harga, aksen (teks putih tetap terbaca)
                '--mint-line' => '#a8e0d2', // Garis tepi kartu & kolom isian
                '--mint' => '#e8f8f3',      // Latar kartu & bagian terang
                '--paper' => '#fdf7f9',     // Latar halaman
                '--purple' => '#e68aa6',    // Judul & label kecil
                '--pink' => '#f4a7bb',      // Aksen ikon
                '--pink-nav' => '#f9d3dc',  // Navbar & footer
                '--yellow' => '#ffd6e0',    // Latar ikon & sorotan
                '--cream' => '#fdeef2',     // Latar bagian kalkulator
                '--ink' => '#4b3f48',       // Warna teks
            ],
        ],
        'laut' => [
            'name' => 'Biru Laut',
            'vars' => [
                '--teal' => '#2a78b0', '--mint-line' => '#a9d3ef', '--mint' => '#e9f4fb', '--paper' => '#f6fafd',
                '--purple' => '#1f6aa0', '--pink' => '#8cc4ea', '--pink-nav' => '#d6ebf8', '--yellow' => '#d6ebf8',
                '--cream' => '#eef6fb', '--ink' => '#23384a',
            ],
        ],
        'hijau' => [
            'name' => 'Hijau Segar',
            'vars' => [
                '--teal' => '#378f51', '--mint-line' => '#b5e0c1', '--mint' => '#ecf8ef', '--paper' => '#f7fcf8',
                '--purple' => '#2f7a45', '--pink' => '#9fd8ae', '--pink-nav' => '#d9f1df', '--yellow' => '#fff1c7',
                '--cream' => '#f1faf3', '--ink' => '#263b2d',
            ],
        ],
        'lavender' => [
            'name' => 'Ungu Lavender',
            'vars' => [
                '--teal' => '#7459c4', '--mint-line' => '#cfc3f0', '--mint' => '#f2eefc', '--paper' => '#faf8fe',
                '--purple' => '#9a4fbd', '--pink' => '#d7b8ef', '--pink-nav' => '#e7dcf8', '--yellow' => '#f3e9ff',
                '--cream' => '#f6f1fd', '--ink' => '#3d3554',
            ],
        ],
        'oranye' => [
            'name' => 'Oranye Ceria',
            'vars' => [
                '--teal' => '#cf661a', '--mint-line' => '#f5cfa8', '--mint' => '#fff3e6', '--paper' => '#fffaf4',
                '--purple' => '#c1521c', '--pink' => '#f7b98a', '--pink-nav' => '#fde3c8', '--yellow' => '#fff0c2',
                '--cream' => '#fff6ec', '--ink' => '#4a3426',
            ],
        ],
    ];

    /** Variabel warna tema tertentu, cth: vars('laut')['--teal'] = '#2a78b0'. */
    public static function vars(string $key): array
    {
        return (self::ALL[$key] ?? self::ALL['pastel'])['vars'];
    }
}
