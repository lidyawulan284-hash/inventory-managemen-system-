<?php
/**
 * Master Data Produk: Listing dengan Search, Filter, Sorting, dan Pagination
 * Sesuai Standar Pertemuan 4: Slide 16 & 17
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

requireLogin();

$pageTitle = 'Master Data Produk';
$activeNav = 'products';
$pdo = getDbConnection();

// 1. Ambil Parameter Filter, Search, Sort & Pagination
$search = trim((string) ($_GET['search'] ?? ''));
$categoryId = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: null;
$stockFilter = trim((string) ($_GET['filter_stock'] ?? '')); // 'low' or ''

// Allowlist Sorting (Slide 16: Kolom sorting harus dipilih dari allowlist, bukan input mentah)
$allowedSortColumns = [
    'name'       => 'p.name',
    'sku'        => 'p.sku',
    'price'      => 'p.price',
    'stock'      => 'p.stock',
    'created_at' => 'p.created_at'
];
$sortBy = $_GET['sort'] ?? 'name';
$sortColumn = $allowedSortColumns[$sortBy] ?? 'p.name';

$direction = strtoupper((string) ($_GET['direction'] ?? 'ASC'));
$sortDirection = ($direction === 'DESC') ? 'DESC' : 'ASC';

// 2. Pagination (Slide 17)
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

// 3. Bangun Query Dinamis Menggunakan Prepared Statement
$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = '(p.name LIKE :search_name OR p.sku LIKE :search_sku)';
    $params['search_name'] = "%{$search}%";
    $params['search_sku'] = "%{$search}%";
}

if ($categoryId) {
    $whereClauses[] = 'p.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

if ($stockFilter === 'low') {
    $whereClauses[] = 'p.stock <= p.minimum_stock';
}

$whereSql = '';
if (!empty($whereClauses)) {
    $whereSql = 'WHERE ' . implode(' AND ', $whereClauses);
}

// Hitung Total Baris untuk Pagination
$countSql = "SELECT COUNT(*) FROM products p {$whereSql}";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRows = (int) $stmtCount->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

// Ambil Data Produk Terpaginasi
$dataSql = "
    SELECT p.id, p.category_id, p.sku, p.name, p.price, p.stock, p.minimum_stock, p.created_at,
           c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    {$whereSql}
    ORDER BY {$sortColumn} {$sortDirection}
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($dataSql);
foreach ($params as $key => $val) {
    $stmt->bindValue(':' . $key, $val);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$products = $stmt->fetchAll();

// Ambil Seluruh Kategori untuk Dropdown Filter
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Master Data Produk</h1>
        <p class="text-slate-500 text-sm mt-1">Kelola katalog barang, pantau persediaan stok, dan audit SKU</p>
    </div>
    <?php if (hasRole('admin')): ?>
        <a href="create.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Produk Baru
        </a>
    <?php endif; ?>
</div>

<!-- Form Pencarian, Filter & Sorting (Slide 16) -->
<div class="bg-white rounded-2xl border border-slate-100 p-5 mb-6 shadow-xs">
    <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <!-- Search Input -->
        <div class="lg:col-span-2">
            <label class="block text-xs font-semibold text-slate-600 mb-1">Cari Nama / SKU</label>
            <div class="relative">
                <input type="text" name="search" value="<?= e($search) ?>" placeholder="Ketik kata kunci..."
                       class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>
        </div>

        <!-- Filter Kategori -->
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Kategori</label>
            <select name="category_id" class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int) $cat['id'] ?>" <?= $categoryId == $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Filter Kondisi Stok -->
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Kondisi Stok</label>
            <select name="filter_stock" class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                <option value="">Semua Kondisi</option>
                <option value="low" <?= $stockFilter === 'low' ? 'selected' : '' ?>>Stok Menipis (&le; Min)</option>
            </select>
        </div>

        <!-- Urutkan (Sorting) -->
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Urutan</label>
            <div class="flex gap-1.5">
                <select name="sort" class="w-full py-2 px-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                    <option value="name" <?= $sortBy === 'name' ? 'selected' : '' ?>>Nama</option>
                    <option value="sku" <?= $sortBy === 'sku' ? 'selected' : '' ?>>SKU</option>
                    <option value="price" <?= $sortBy === 'price' ? 'selected' : '' ?>>Harga</option>
                    <option value="stock" <?= $sortBy === 'stock' ? 'selected' : '' ?>>Stok</option>
                </select>
                <select name="direction" class="w-20 py-2 px-1 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                    <option value="ASC" <?= $sortDirection === 'ASC' ? 'selected' : '' ?>>ASC</option>
                    <option value="DESC" <?= $sortDirection === 'DESC' ? 'selected' : '' ?>>DESC</option>
                </select>
            </div>
        </div>

        <!-- Tombol Aksi Filter -->
        <div class="lg:col-span-5 flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
            <a href="index.php" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-medium text-slate-600 hover:bg-slate-50">Reset</a>
            <button type="submit" class="px-4 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition">Terapkan Filter</button>
        </div>
    </form>
</div>

<!-- Tabel Master Data Produk -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-100 font-semibold">
                <tr>
                    <th class="py-3.5 px-4">SKU</th>
                    <th class="py-3.5 px-4">Nama Produk</th>
                    <th class="py-3.5 px-4">Kategori</th>
                    <th class="py-3.5 px-4 text-right">Harga Satuan</th>
                    <th class="py-3.5 px-4 text-center">Stok / Min</th>
                    <th class="py-3.5 px-4 text-center">Status</th>
                    <th class="py-3.5 px-4 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="7" class="py-10 text-center text-slate-400">
                            Tidak ada produk yang sesuai dengan kriteria filter atau pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <?php $isLow = ($p['stock'] <= $p['minimum_stock']); ?>
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3 px-4 font-mono font-medium text-slate-700"><?= e($p['sku']) ?></td>
                            <td class="py-3 px-4 font-semibold text-slate-900"><?= e($p['name']) ?></td>
                            <td class="py-3 px-4"><?= e($p['category_name']) ?></td>
                            <td class="py-3 px-4 text-right font-medium text-slate-800"><?= formatRupiah($p['price']) ?></td>
                            <td class="py-3 px-4 text-center font-bold <?= $isLow ? 'text-amber-600' : 'text-slate-800' ?>">
                                <?= (int) $p['stock'] ?> <span class="text-slate-400 font-normal">/ <?= (int) $p['minimum_stock'] ?></span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <?php if ($p['stock'] == 0): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700">Habis</span>
                                <?php elseif ($isLow): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">Menipis</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">Aman</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-right space-x-2">
                                <a href="../stock/create.php?product_id=<?= (int) $p['id'] ?>" class="text-indigo-600 hover:text-indigo-800 font-semibold text-[11px]" title="Mutasi Stok">Mutasi</a>
                                <?php if (hasRole('admin')): ?>
                                    <a href="edit.php?id=<?= (int) $p['id'] ?>" class="text-slate-600 hover:text-slate-900 font-semibold text-[11px]">Edit</a>
                                    <a href="delete.php?id=<?= (int) $p['id'] ?>" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus produk ini?');"
                                       class="text-rose-600 hover:text-rose-800 font-semibold text-[11px]">Hapus</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer (Slide 17: Pertahankan parameter query string) -->
    <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div>
                Menampilkan halaman <strong class="text-slate-800"><?= $page ?></strong> dari <strong class="text-slate-800"><?= $totalPages ?></strong> (Total <?= $totalRows ?> produk)
            </div>
            <div class="flex items-center gap-1">
                <?php 
                    $queryParams = $_GET; 
                ?>
                <!-- Previous Button -->
                <?php if ($page > 1): ?>
                    <?php $queryParams['page'] = $page - 1; ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 transition">&larr; Sebelumnya</a>
                <?php endif; ?>

                <!-- Page numbers -->
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $queryParams['page'] = $i; ?>
                    <a href="?<?= http_build_query($queryParams) ?>" 
                       class="px-3 py-1.5 rounded-lg <?= $i === $page ? 'bg-indigo-600 text-white font-bold' : 'border border-slate-200 hover:bg-slate-50 text-slate-700' ?> transition">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <!-- Next Button -->
                <?php if ($page < $totalPages): ?>
                    <?php $queryParams['page'] = $page + 1; ?>
                    <a href="?<?= http_build_query($queryParams) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50 transition">Berikutnya &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
