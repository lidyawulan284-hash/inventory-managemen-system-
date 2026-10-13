<?php
/**
 * Script Utility / Seeder: Reset Password Hash & Sinkronisasi Kolom Users
 * Mini Project 3: Inventory Management System
 */

require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDbConnection();

    // 1. Pastikan kolom full_name dan password_hash tersedia di database
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS full_name VARCHAR(100) NULL AFTER name,
        ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER password
    ");

    // Sinkronisasi data kolom yang kosong
    $pdo->exec("UPDATE users SET full_name = name WHERE full_name IS NULL OR full_name = ''");
    $pdo->exec("UPDATE users SET password_hash = password WHERE password_hash IS NULL OR password_hash = ''");

    // 2. Generate hash standar menggunakan password_hash (PASSWORD_BCRYPT)
    $adminHash = password_hash('admin123', PASSWORD_BCRYPT);
    $staffHash = password_hash('staff123', PASSWORD_BCRYPT);

    $stmt = $pdo->prepare("
        UPDATE users 
        SET password_hash = :hash, 
            password = :pass, 
            full_name = :full_name,
            email = :email
        WHERE username = :username
    ");

    // Update Admin
    $stmt->execute([
        'hash'      => $adminHash,
        'pass'      => $adminHash,
        'full_name' => 'Administrator Utama',
        'email'     => 'admin@inventory.com',
        'username'  => 'admin'
    ]);

    // Update Staff
    $stmt->execute([
        'hash'      => $staffHash,
        'pass'      => $staffHash,
        'full_name' => 'Staff Gudang Logistik',
        'email'     => 'staff@inventory.com',
        'username'  => 'staff'
    ]);

    echo "=== Sukses Sinkronisasi & Reset Password User ===" . PHP_EOL;
    echo "Akun Admin : admin (admin@inventory.com) | Password: admin123" . PHP_EOL;
    echo "Akun Staff : staff (staff@inventory.com) | Password: staff123" . PHP_EOL;
    echo "Hash tersimpan menggunakan password_hash() BCRYPT." . PHP_EOL;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}

