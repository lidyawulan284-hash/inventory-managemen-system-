<?php
/**
 * Konfigurasi Koneksi Database Menggunakan PDO (PHP Data Objects)
 * 
 * Fitur Keamanan:
 * 1. PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
 *    Memastikan setiap kesalahan query melempar PDOException sehingga dapat
 *    ditangani dengan try/catch dan mendukung Database Transaction secara aman.
 * 2. PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
 *    Mengembalikan data dalam bentuk associative array yang bersih.
 * 3. PDO::ATTR_EMULATE_PREPARES => false
 *    Mematikan emulasi prepared statements agar query prepared statement dikirim
 *    langsung secara native ke server MySQL, mencegah eksploitasi SQL Injection.
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'inventory_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Mendapatkan instance koneksi PDO tunggal (Singleton pattern)
 *
 * @return PDO
 * @throws PDOException
 */
function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Hindari membocorkan kredensial database ke publik
            error_log('Database Connection Error: ' . $e->getMessage());
            die('Gagal terhubung ke database. Silakan periksa konfigurasi MySQL atau hubungi administrator.');
        }
    }

    return $pdo;
}
