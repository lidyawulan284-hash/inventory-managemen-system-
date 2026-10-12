<?php
/**
 * Tambah Produk Baru
 * Hak Akses: Admin Saja (Slide 24 & 33)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

// Otorisasi: Khusus Admin
requireRole('admin');

$pageTitle = 'Tambah Produk Baru';
$activeNav = 'products';
$pdo = getDbConnection();

$errors = [];
$sku = '';
$name = '';
$categoryId = '';
$price = '';
$stock = '0';
$minimumStock = '5';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    } else {
        $sku = strtoupper(sanitizeString($_POST['sku'] ?? ''));
        $name = sanitizeString($_POST['name'] ?? '');
        $categoryId = validatePositiveInt($_POST['category_id'] ?? 0);
        $price = validateNonNegativeFloat($_POST['price'] ?? -1);
        $stock = validatePositiveInt($_POST['stock'] ?? 0, 0); // initial stock >= 0
        $minimumStock = validatePositiveInt($_POST['minimum_stock'] ?? 5, 0);

        // Validasi input
        if ($sku === '') $errors[] = 'SKU wajib diisi.';
        if ($name === '') $errors[] = 'Nama produk wajib diisi.';
        if (!$categoryId) $errors[] = 'Kategori produk wajib dipilih.';
        if ($price === false) $errors[] = 'Harga produk harus berupa angka non-negatif.';
        if ($stock === false) $errors[] = 'Stok awal tidak boleh bernilai negatif.';
        if ($minimumStock === false) $errors[] = 'Batas minimum stok tidak boleh negatif.';

        // Cek Keunikan SKU (Slide 33: SKU harus unik)
        if (empty($errors)) {
            $stmtCheck = $pdo->prepare("SELECT id FROM products WHERE sku = :sku LIMIT 1");
            $stmtCheck->execute(['sku' => $sku]);
            if ($stmtCheck->fetch()) {
                $errors[] = "SKU '{$sku}' sudah digunakan oleh produk lain. Silakan gunakan SKU yang unik.";
            }
        }

        // Simpan Data jika tidak ada error
        if (empty($errors)) {
            try {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO products (category_id, sku, name, price, stock, minimum_stock)
                    VALUES (:category_id, :sku, :name, :price, :stock, :minimum_stock)
                ");
                $stmtInsert->execute([
                    'category_id'   => $categoryId,
                    'sku'           => $sku,
                    'name'          => $name,
                    'price'         => $price,
                    'stock'         => $stock,
                    'minimum_stock' => $minimumStock
                ]);

                // Catat mutasi awal jika stok awal > 0
                if ($stock > 0) {
                    $newProductId = $pdo->lastInsertId();
                    $stmtMovement = $pdo->prepare("
                        INSERT INTO stock_movements (product_id, user_id, type, quantity, notes)
                        VALUES (:product_id, :user_id, 'IN', :quantity, 'Stok awal penambahan produk baru')
                    ");
                    $stmtMovement->execute([
                        'product_id' => $newProductId,
                        'user_id'    => $_SESSION['user']['id'],
                        'quantity'   => $stock
                    ]);
                }

                setFlash('success', "Produk '{$name}' dengan SKU '{$sku}' berhasil ditambahkan.");
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('Insert Product Error: ' . $e->getMessage());
                $errors[] = 'Terjadi kesalahan saat menyimpan data ke database.';
            }
        }
    }
}

// Ambil Kategori untuk Pilihan Dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Tambah Produk Baru</h1>
            <p class="text-slate-500 text-xs mt-1">Masukkan informasi produk dan stok awal</p>
        </div>
        <a href="index.php" class="text-xs text-slate-500 hover:text-slate-800 font-medium">&larr; Kembali ke Daftar</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-5 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
            <strong class="font-bold">Perhatian:</strong>
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl border border-slate-100 p-6 shadow-xs">
        <form method="POST" action="create.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">SKU (Kode Unik Produk) <span class="text-rose-500">*</span></label>
                    <input type="text" name="sku" required value="<?= e($sku) ?>" placeholder="Contoh: ELK-KB-005"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Kategori <span class="text-rose-500">*</span></label>
                    <select name="category_id" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                        <option value="">Pilih Kategori</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                                <?= e($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Produk <span class="text-rose-500">*</span></label>
                <input type="text" name="name" required value="<?= e($name) ?>" placeholder="Contoh: Logitech Wireless Mouse M331"
                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Harga Satuan (Rp) <span class="text-rose-500">*</span></label>
                    <input type="number" name="price" min="0" step="1000" required value="<?= e($price) ?>" placeholder="0"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Stok Awal</label>
                    <input type="number" name="stock" min="0" value="<?= e($stock) ?>"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Batas Min. Stok</label>
                    <input type="number" name="minimum_stock" min="0" value="<?= e($minimumStock) ?>"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="index.php" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition">
                    Simpan Produk
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
