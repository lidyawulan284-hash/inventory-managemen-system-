<?php
/**
 * Master Data Kategori (Admin Only)
 * Sesuai Standar Pertemuan 4: Slide 14 (Aggregate Query categories with products count)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

requireRole('admin');

$pageTitle = 'Master Kategori';
$activeNav = 'categories';
$pdo = getDbConnection();

// Tambah Kategori Baru (POST)
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $name = sanitizeString($_POST['name'] ?? '');
        $description = sanitizeString($_POST['description'] ?? '');

        if ($name === '') {
            $error = 'Nama kategori wajib diisi.';
        } else {
            try {
                $stmtInsert = $pdo->prepare("INSERT INTO categories (name, description) VALUES (:name, :description)");
                $stmtInsert->execute(['name' => $name, 'description' => $description]);
                setFlash('success', "Kategori '{$name}' berhasil ditambahkan.");
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $error = "Kategori '{$name}' sudah ada.";
                } else {
                    $error = 'Gagal menyimpan kategori: ' . $e->getMessage();
                }
            }
        }
    }
}

// Hapus Kategori (GET ?delete=id)
if (isset($_GET['delete'])) {
    $delId = validatePositiveInt($_GET['delete']);
    if ($delId) {
        // Cek apakah ada produk yang terikat pada kategori ini (Slide 9: Referential Integrity)
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id");
        $stmtCheck->execute(['id' => $delId]);
        if ($stmtCheck->fetchColumn() > 0) {
            setFlash('error', 'Kategori ini tidak dapat dihapus karena masih digunakan oleh beberapa produk (Foreign Key RESTRICT).');
        } else {
            $stmtDel = $pdo->prepare("DELETE FROM categories WHERE id = :id");
            $stmtDel->execute(['id' => $delId]);
            setFlash('success', 'Kategori berhasil dihapus.');
        }
    }
    header('Location: index.php');
    exit;
}

// Query Agregat Kategori & Jumlah Produk (Slide 14)
$stmtCats = $pdo->query("
    SELECT c.id, c.name, c.description, c.created_at,
           COUNT(p.id) AS total_products,
           COALESCE(SUM(p.stock), 0) AS total_stock
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id
    GROUP BY c.id, c.name, c.description, c.created_at
    ORDER BY c.name ASC
");
$categories = $stmtCats->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Master Data Kategori</h1>
        <p class="text-slate-500 text-sm mt-1">Klasifikasi produk dan agregasi stok per kelompok barang</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Form Tambah Kategori -->
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-xs h-fit">
        <h2 class="font-bold text-slate-800 text-sm mb-4">Tambah Kategori Baru</h2>

        <?php if ($error): ?>
            <div class="p-3 mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Kategori <span class="text-rose-500">*</span></label>
                <input type="text" name="name" required placeholder="Contoh: Perangkat Jaringan"
                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Deskripsi (Opsional)</label>
                <textarea name="description" rows="3" placeholder="Keterangan singkat kelompok barang..."
                          class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs rounded-xl shadow-xs transition">
                Simpan Kategori
            </button>
        </form>
    </div>

    <!-- Tabel Daftar Kategori -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800 text-sm">Daftar Kategori Tersedia</h2>
            <span class="text-xs text-slate-400">Total: <?= count($categories) ?> Kategori</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-100 font-semibold">
                    <tr>
                        <th class="py-3 px-4">Nama Kategori</th>
                        <th class="py-3 px-4">Deskripsi</th>
                        <th class="py-3 px-4 text-center">Jumlah Produk</th>
                        <th class="py-3 px-4 text-center">Total Stok</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="5" class="py-8 text-center text-slate-400">Belum ada kategori yang ditambahkan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($categories as $cat): ?>
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="py-3 px-4 font-semibold text-slate-900"><?= e($cat['name']) ?></td>
                                <td class="py-3 px-4 text-slate-500 max-w-xs truncate"><?= e($cat['description'] ?: '-') ?></td>
                                <td class="py-3 px-4 text-center font-bold text-indigo-600"><?= (int) $cat['total_products'] ?></td>
                                <td class="py-3 px-4 text-center font-bold text-slate-800"><?= (int) $cat['total_stock'] ?></td>
                                <td class="py-3 px-4 text-right">
                                    <a href="index.php?delete=<?= (int) $cat['id'] ?>" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus kategori ini?');"
                                       class="text-rose-600 hover:text-rose-800 font-semibold text-[11px]">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
