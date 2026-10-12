<?php
/**
 * Riwayat Mutasi Stok (Histori Transaksi Masuk & Keluar)
 * Sesuai Standar Pertemuan 4: Slide 25-28
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

requireLogin();

$pageTitle = 'Riwayat Mutasi Stok';
$activeNav = 'stock';
$pdo = getDbConnection();

// Parameter Filter & Pagination
$typeFilter = trim((string) ($_GET['type'] ?? '')); // 'IN', 'OUT' or ''
$productId = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT) ?: null;

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if (in_array($typeFilter, ['IN', 'OUT'], true)) {
    $whereClauses[] = 'sm.type = :type';
    $params['type'] = $typeFilter;
}

if ($productId) {
    $whereClauses[] = 'sm.product_id = :product_id';
    $params['product_id'] = $productId;
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Hitung Total Data
$countSql = "SELECT COUNT(*) FROM stock_movements sm {$whereSql}";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRows = (int) $stmtCount->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

// Ambil Data Mutasi
$dataSql = "
    SELECT sm.id, sm.type, sm.quantity, sm.notes, sm.created_at,
           p.name AS product_name, p.sku,
           COALESCE(u.full_name, u.name, u.username) AS user_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN users u ON u.id = sm.user_id
    {$whereSql}
    ORDER BY sm.created_at DESC, sm.id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$movements = $stmt->fetchAll();

// Daftar Produk untuk Pilihan Filter
$productsList = $pdo->query("SELECT id, sku, name FROM products ORDER BY name ASC")->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Riwayat Mutasi Stok</h1>
        <p class="text-slate-500 text-sm mt-1">Audit log pergerakan barang masuk (IN) dan keluar (OUT) gudang</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="create.php?type=IN" class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Catat Barang Masuk (+IN)
        </a>
        <a href="create.php?type=OUT" class="inline-flex items-center gap-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
            Catat Barang Keluar (-OUT)
        </a>
    </div>
</div>

<!-- Filter Mutasi -->
<div class="bg-white rounded-2xl border border-slate-100 p-4 mb-6 shadow-xs">
    <form method="GET" action="index.php" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Filter Tipe Mutasi</label>
            <select name="type" class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                <option value="">Semua Tipe (IN &amp; OUT)</option>
                <option value="IN" <?= $typeFilter === 'IN' ? 'selected' : '' ?>>Barang Masuk (+IN)</option>
                <option value="OUT" <?= $typeFilter === 'OUT' ? 'selected' : '' ?>>Barang Keluar (-OUT)</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Filter Produk</label>
            <select name="product_id" class="w-full py-2 px-3 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                <option value="">Semua Produk</option>
                <?php foreach ($productsList as $pr): ?>
                    <option value="<?= (int) $pr['id'] ?>" <?= $productId == $pr['id'] ? 'selected' : '' ?>>
                        <?= e($pr['sku']) ?> - <?= e($pr['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-end gap-2">
            <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition">
                Terapkan Filter
            </button>
            <a href="index.php" class="px-3 py-2 border border-slate-200 text-slate-600 hover:bg-slate-50 text-xs rounded-xl transition">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Tabel Log Mutasi -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-600">
            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-100 font-semibold">
                <tr>
                    <th class="py-3.5 px-4">Waktu Transaksi</th>
                    <th class="py-3.5 px-4">Tipe</th>
                    <th class="py-3.5 px-4">SKU / Nama Produk</th>
                    <th class="py-3.5 px-4 text-center">Jumlah (Qty)</th>
                    <th class="py-3.5 px-4">Operator / User</th>
                    <th class="py-3.5 px-4">Keterangan / Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="6" class="py-10 text-center text-slate-400">
                            Tidak ada data mutasi stok yang ditemukan.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-3 px-4 font-medium text-slate-500"><?= formatTanggal($m['created_at']) ?></td>
                            <td class="py-3 px-4">
                                <?php if ($m['type'] === 'IN'): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                        &darr; MASUK (IN)
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-800">
                                        &uarr; KELUAR (OUT)
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-mono text-slate-500"><?= e($m['sku']) ?></span> &bull; 
                                <span class="font-semibold text-slate-900"><?= e($m['product_name']) ?></span>
                            </td>
                            <td class="py-3 px-4 text-center font-bold text-sm <?= $m['type'] === 'IN' ? 'text-emerald-600' : 'text-rose-600' ?>">
                                <?= $m['type'] === 'IN' ? '+' : '-' ?><?= number_format($m['quantity']) ?>
                            </td>
                            <td class="py-3 px-4 text-slate-700"><?= e($m['user_name']) ?></td>
                            <td class="py-3 px-4 text-slate-500 italic"><?= !empty($m['notes']) ? e($m['notes']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div>
                Halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong> (Total <?= $totalRows ?> transaksi)
            </div>
            <div class="flex items-center gap-1">
                <?php $q = $_GET; ?>
                <?php if ($page > 1): ?>
                    <?php $q['page'] = $page - 1; ?>
                    <a href="?<?= http_build_query($q) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">&larr;</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php $q['page'] = $i; ?>
                    <a href="?<?= http_build_query($q) ?>" class="px-3 py-1.5 rounded-lg <?= $i === $page ? 'bg-indigo-600 text-white font-bold' : 'border border-slate-200 hover:bg-slate-50 text-slate-700' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <?php $q['page'] = $page + 1; ?>
                    <a href="?<?= http_build_query($q) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 hover:bg-slate-50">&rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../views/footer.php'; ?>
