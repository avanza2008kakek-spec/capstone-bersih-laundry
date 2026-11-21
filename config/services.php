<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // POSAJA Verification Hub: layanan milik sendiri yang mengirim kode verifikasi ke WhatsApp pelanggan
    'verification_hub' => [
        'url'         => env('VERIFICATION_HUB_URL', 'https://posaja.kantorposbekasi.com/api/kirim-verifikasi.php'), // Endpoint kirim kode
        'purpose'     => env('VERIFICATION_PURPOSE', 'Verifikasi Pendaftaran Pelanggan Laundry'), // Isi kolom "keperluan" saat daftar
        'purpose_login' => env('VERIFICATION_PURPOSE_LOGIN', 'Login Pelanggan Laundry'), // Isi kolom "keperluan" saat masuk
        'otp_minutes' => (int) env('VERIFICATION_OTP_MINUTES', 10), // Lama kode berlaku (menit), dicek di website Laundry
        'timeout'     => 15,                                    // Batas tunggu jawaban Hub (detik)
    ],

    // Data usaha laundry
    'laundry' => [
        'wa_admin' => env('LAUNDRY_WA_ADMIN', '6282115500412'), // Nomor WA admin penerima reservasi (format 628xxx, tanpa +)
        // Kunci sementara untuk halaman /kasir/atur-owner; KOSONGKAN setelah password owner diatur
        'setup_key' => env('KASIR_SETUP_KEY', ''),
    ],

];
