<?php
/**
 * Pencatatan Transaksi Mutasi Barang Masuk (IN) & Barang Keluar (OUT)
 * Menerapkan Database Transaction (beginTransaction, commit, rollBack)
 * Sesuai Standar Pertemuan 4: Slide 25, 26, 27
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

requireLogin();

$pageTitle = 'Catat Mutasi Stok';
$activeNav = 'stock';
$pdo = getDbConnection();

$errors = [];
$productId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT) ?: '';
$type = strtoupper(trim((string) ($_GET['type'] ?? 'IN')));
if (!in_array($type, ['IN', 'OUT'], true)) {
    $type = 'IN';
}
$quantity = '';
$notes = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    } else {
        $productId = validatePositiveInt($_POST['product_id'] ?? 0);
        $type = strtoupper(trim((string) ($_POST['type'] ?? 'IN')));
        $quantity = validatePositiveInt($_POST['quantity'] ?? 0);
        $notes = sanitizeString($_POST['notes'] ?? '');

        if (!$productId) {
            $errors[] = 'Silakan pilih produk yang akan dimutasi.';
        }
        if (!in_array($type, ['IN', 'OUT'], true)) {
            $errors[] = 'Tipe mutasi tidak valid (harus IN atau OUT).';
        }
        if ($quantity === false || $quantity <= 0) {
            $errors[] = 'Jumlah mutasi (quantity) harus lebih besar dari 0.';
        }

        // Eksekusi Logika Bisnis dengan Database Transaction (Slide 26 & 27)
        if (empty($errors)) {
            // 1. Memulai Transaksi Database
            $pdo->beginTransaction();

            try {
                // 2. Kunci baris data produk dengan FOR UPDATE untuk mencegah Race Condition
                $stmtCheck = $pdo->prepare("
                    SELECT id, name, sku, stock 
                    FROM products 
                    WHERE id = :id 
                    FOR UPDATE
                ");
                $stmtCheck->execute(['id' => $productId]);
                $product = $stmtCheck->fetch();

                if (!$product) {
                    throw new Exception('Produk tidak ditemukan dalam database.');
                }

                $currentStock = (int) $product['stock'];

                // 3. Validasi Bisnis: Mencegah Stok Negatif pada Barang Keluar (OUT)
                if ($type === 'OUT') {
                    if ($quantity > $currentStock) {
                        throw new Exception("Transaksi GAGAL: Stok tidak mencukupi! Stok saat ini untuk '{$product['name']}' adalah {$currentStock}, sedangkan permintaan pengeluaran adalah {$quantity}.");
                    }
                    $newStock = $currentStock - $quantity;
                } else {
                    // Barang Masuk (IN)
                    $newStock = $currentStock + $quantity;
                }

                // 4. Update Stok Produk
                $stmtUpdate = $pdo->prepare("
                    UPDATE products 
                    SET stock = :new_stock 
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    'new_stock' => $newStock,
                    'id'        => $productId
                ]);

                // 5. Catat Histori ke Tabel stock_movements
                $stmtMovement = $pdo->prepare("
                    INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
                    VALUES (:product_id, :user_id, :type, :quantity, :notes)
                ");
                $stmtMovement->execute([
                    'product_id' => $productId,
                    'user_id'    => $_SESSION['user']['id'],
                    'type'       => $type,
                    'quantity'   => $quantity,
                    'notes'      => $notes !== '' ? $notes : null
                ]);

                // 6. Commit Perubahan jika seluruh langkah sukses
                $pdo->commit();

                setFlash('success', "Mutasi {$type} berhasil disimpan! Stok '{$product['name']}' ({$product['sku']}) kini berjumlah {$newStock} unit.");
                header('Location: index.php');
                exit;

            } catch (Throwable $e) {
                // 7. Batalkan seluruh perubahan jika terjadi error apa pun (RollBack)
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Ambil Seluruh Produk untuk Dropdown Form
$allProducts = $pdo->query("SELECT id, sku, name, stock FROM products ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Pencatatan Mutasi Stok Barang</h1>
            <p class="text-slate-500 text-xs mt-1">Menggunakan Database Transaction &amp; Validasi Anti-Stok Negatif</p>
        </div>
        <a href="index.php" class="text-xs text-slate-500 hover:text-slate-800 font-medium">&larr; Kembali ke Riwayat</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-5 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
            <strong class="font-bold flex items-center gap-1.5">
                <svg class="w-4 h-4 text-rose-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                Terjadi Kesalahan Mutasi:
            </strong>
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-xs">
        <form method="POST" action="create.php" class="space-y-5">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <!-- Tipe Mutasi: IN atau OUT -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-2">Jenis Mutasi <span class="text-rose-500">*</span></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="cursor-pointer border rounded-xl p-3 flex items-center gap-3 transition <?= $type === 'IN' ? 'border-emerald-500 bg-emerald-50/50' : 'border-slate-200 hover:bg-slate-50' ?>">
                        <input type="radio" name="type" value="IN" <?= $type === 'IN' ? 'checked' : '' ?> class="text-emerald-600 focus:ring-emerald-500">
                        <div>
                            <p class="text-xs font-bold text-emerald-800">Barang Masuk (+IN)</p>
                            <p class="text-[11px] text-slate-500">Penambahan persediaan dari supplier / retur</p>
                        </div>
                    </label>

                    <label class="cursor-pointer border rounded-xl p-3 flex items-center gap-3 transition <?= $type === 'OUT' ? 'border-rose-500 bg-rose-50/50' : 'border-slate-200 hover:bg-slate-50' ?>">
                        <input type="radio" name="type" value="OUT" <?= $type === 'OUT' ? 'checked' : '' ?> class="text-rose-600 focus:ring-rose-500">
                        <div>
                            <p class="text-xs font-bold text-rose-800">Barang Keluar (-OUT)</p>
                            <p class="text-[11px] text-slate-500">Penjualan, distribusi, atau pemakaian divisi</p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Pilih Produk -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Pilih Produk <span class="text-rose-500">*</span></label>
                <select name="product_id" required class="w-full px-3 py-2.5 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                    <option value="">-- Pilih Produk yang Dimutasi --</option>
                    <?php foreach ($allProducts as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= $productId == $p['id'] ? 'selected' : '' ?>>
                            [<?= e($p['sku']) ?>] <?= e($p['name']) ?> (Stok Saat Ini: <?= (int) $p['stock'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Jumlah / Quantity -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Jumlah Mutasi (Quantity) <span class="text-rose-500">*</span></label>
                <input type="number" name="quantity" min="1" required value="<?= e($quantity) ?>" placeholder="Contoh: 10"
                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <p class="text-[11px] text-slate-400 mt-1">Harus berupa bilangan bulat positif lebih dari nol.</p>
            </div>

            <!-- Catatan / Keterangan -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Keterangan / Nomor Referensi (Opsional)</label>
                <textarea name="notes" rows="3" placeholder="Contoh: Penerimaan PO-2026-004 atau Pengeluaran untuk Departemen Keuangan"
                          class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"><?= e($notes) ?></textarea>
            </div>

            <!-- Tombol Aksi -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="index.php" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50">Batal</a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Simpan Transaksi Mutasi
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
