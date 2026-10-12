<?php
/**
 * Hapus Produk
 * Hak Akses: Admin Saja
 * Business Rule: Produk yang memiliki histori transaksi tidak boleh dihapus sembarangan (Slide 33)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/validation.php';

requireRole('admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('error', 'ID Produk tidak valid.');
    header('Location: index.php');
    exit;
}

$pdo = getDbConnection();

// 1. Verifikasi Keberadaan Produk
$stmtProduct = $pdo->prepare("SELECT id, name, sku FROM products WHERE id = :id LIMIT 1");
$stmtProduct->execute(['id' => $id]);
$product = $stmtProduct->fetch();

if (!$product) {
    setFlash('error', 'Produk tidak ditemukan.');
    header('Location: index.php');
    exit;
}

// 2. Business Rule: Cek apakah produk memiliki histori di stock_movements
$stmtCheckMovements = $pdo->prepare("SELECT COUNT(*) FROM stock_movements WHERE product_id = :id");
$stmtCheckMovements->execute(['id' => $id]);
$movementCount = (int) $stmtCheckMovements->fetchColumn();

if ($movementCount > 0) {
    setFlash('error', "Produk '{$product['name']}' ({$product['sku']}) tidak dapat dihapus karena memiliki {$movementCount} catatan histori transaksi mutasi stok (Referential Integrity Constraint).");
    header('Location: index.php');
    exit;
}

// 3. Eksekusi Penghapusan jika aman
try {
    $stmtDelete = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $stmtDelete->execute(['id' => $id]);

    setFlash('success', "Produk '{$product['name']}' ({$product['sku']}) berhasil dihapus dari sistem.");
} catch (PDOException $e) {
    error_log('Delete Product Error: ' . $e->getMessage());
    setFlash('error', 'Gagal menghapus produk karena kendala integritas relasi database.');
}

header('Location: index.php');
exit;
