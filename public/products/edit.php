<?php
/**
 * Edit Produk
 * Hak Akses: Admin Saja (Slide 24)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

requireRole('admin');

$pageTitle = 'Edit Produk';
$activeNav = 'products';
$pdo = getDbConnection();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    setFlash('error', 'ID Produk tidak valid.');
    header('Location: index.php');
    exit;
}

// Ambil Data Produk
$stmtProduct = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
$stmtProduct->execute(['id' => $id]);
$product = $stmtProduct->fetch();

if (!$product) {
    setFlash('error', 'Produk tidak ditemukan.');
    header('Location: index.php');
    exit;
}

$errors = [];
$name = $product['name'];
$categoryId = $product['category_id'];
$price = $product['price'];
$minimumStock = $product['minimum_stock'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token keamanan tidak valid. Silakan coba lagi.';
    } else {
        $name = sanitizeString($_POST['name'] ?? '');
        $categoryId = validatePositiveInt($_POST['category_id'] ?? 0);
        $price = validateNonNegativeFloat($_POST['price'] ?? -1);
        $minimumStock = validatePositiveInt($_POST['minimum_stock'] ?? 5, 0);

        if ($name === '') $errors[] = 'Nama produk wajib diisi.';
        if (!$categoryId) $errors[] = 'Kategori produk wajib dipilih.';
        if ($price === false) $errors[] = 'Harga produk harus berupa angka non-negatif.';
        if ($minimumStock === false) $errors[] = 'Batas minimum stok tidak boleh negatif.';

        if (empty($errors)) {
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE products 
                    SET category_id = :category_id, 
                        name = :name, 
                        price = :price, 
                        minimum_stock = :minimum_stock 
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    'category_id'   => $categoryId,
                    'name'          => $name,
                    'price'         => $price,
                    'minimum_stock' => $minimumStock,
                    'id'            => $id
                ]);

                setFlash('success', "Data produk '{$name}' berhasil diperbarui.");
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('Update Product Error: ' . $e->getMessage());
                $errors[] = 'Terjadi kesalahan saat memperbarui data produk.';
            }
        }
    }
}

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-800">Edit Produk: <?= e($product['sku']) ?></h1>
            <p class="text-slate-500 text-xs mt-1">Perbarui informasi dan batas stok produk</p>
        </div>
        <a href="index.php" class="text-xs text-slate-500 hover:text-slate-800 font-medium">&larr; Kembali</a>
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
        <form method="POST" action="edit.php?id=<?= $id ?>" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">SKU (Kode Unik)</label>
                    <input type="text" disabled value="<?= e($product['sku']) ?>"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 bg-slate-100 text-slate-500 font-mono cursor-not-allowed">
                    <p class="text-[10px] text-slate-400 mt-1">SKU bersifat permanen untuk integritas histori.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Kategori <span class="text-rose-500">*</span></label>
                    <select name="category_id" required class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
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
                <input type="text" name="name" required value="<?= e($name) ?>"
                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Harga Satuan (Rp) <span class="text-rose-500">*</span></label>
                    <input type="number" name="price" min="0" step="1000" required value="<?= e($price) ?>"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Batas Minimum Stok</label>
                    <input type="number" name="minimum_stock" min="0" value="<?= e($minimumStock) ?>"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-3">
                <a href="index.php" class="px-4 py-2 rounded-xl border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50">Batal</a>
                <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition">
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
