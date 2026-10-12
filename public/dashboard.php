<?php
/**
 * Dashboard Utama Inventory Management System
 * Sesuai Standar Pertemuan 4: Aggregate Queries & Dashboard Reporting
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/validation.php';

// Memastikan hanya user yang sudah login yang dapat mengakses
requireLogin();

$pageTitle = 'Dashboard Ringkasan';
$activeNav = 'dashboard';

$pdo = getDbConnection();

// 1. Total Produk
$stmtTotalProducts = $pdo->query("SELECT COUNT(*) FROM products");
$totalProducts = (int) $stmtTotalProducts->fetchColumn();

// 2. Total Nilai Inventory (SUM(stock * price))
$stmtInventoryValue = $pdo->query("SELECT COALESCE(SUM(stock * price), 0) FROM products");
$totalInventoryValue = (float) $stmtInventoryValue->fetchColumn();

// 3. Produk Stok Rendah (stock <= minimum_stock)
$stmtLowStockCount = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= minimum_stock");
$lowStockCount = (int) $stmtLowStockCount->fetchColumn();

// 4. Total Barang Masuk & Keluar dari stock_movements
$stmtMovementsSummary = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END), 0) AS total_out
    FROM stock_movements
");
$movementsSummary = $stmtMovementsSummary->fetch();
$totalIn = (int) ($movementsSummary['total_in'] ?? 0);
$totalOut = (int) ($movementsSummary['total_out'] ?? 0);

// 5. Daftar Produk dengan Stok Rendah (Perlu Restock)
$stmtLowStockList = $pdo->query("
    SELECT p.id, p.sku, p.name, p.stock, p.minimum_stock, c.name AS category_name
    FROM products p
    JOIN categories c ON c.id = p.category_id
    WHERE p.stock <= p.minimum_stock
    ORDER BY p.stock ASC
    LIMIT 5
");
$lowStockProducts = $stmtLowStockList->fetchAll();

// 6. Mutasi Terakhir (Histori Transaksi Masuk/Keluar)
$stmtRecentMovements = $pdo->query("
    SELECT sm.id, sm.type, sm.quantity, sm.notes, sm.created_at,
           p.name AS product_name, p.sku,
           COALESCE(u.full_name, u.name, u.username) AS user_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    JOIN users u ON u.id = sm.user_id
    ORDER BY sm.created_at DESC
    LIMIT 5
");
$recentMovements = $stmtRecentMovements->fetchAll();

require_once __DIR__ . '/../views/header.php';
?>

<!-- Header Title -->
<div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Dashboard Inventory</h1>
        <p class="text-slate-500 text-sm mt-1">Selamat datang kembali, <span class="font-semibold text-slate-700"><?= e($user['full_name']) ?></span> (Role: <span class="capitalize font-semibold text-indigo-600"><?= e($user['role']) ?></span>)</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="stock/create.php?type=IN" class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Barang Masuk (+IN)
        </a>
        <a href="stock/create.php?type=OUT" class="inline-flex items-center gap-2 px-4 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-semibold shadow-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
            Barang Keluar (-OUT)
        </a>
    </div>
</div>

<!-- Widgets Ringkasan Statistik (Slide 29) -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-5 mb-8">
    <!-- 1. Total Produk -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Produk</span>
            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-slate-800"><?= number_format($totalProducts) ?></div>
        <p class="text-xs text-slate-400 mt-1">Item dalam database</p>
    </div>

    <!-- 2. Nilai Inventory -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Nilai Inventory</span>
            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-slate-800"><?= formatRupiah($totalInventoryValue) ?></div>
        <p class="text-xs text-slate-400 mt-1">Stok &times; Harga jual</p>
    </div>

    <!-- 3. Stok Rendah -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Stok Menipis</span>
            <div class="w-8 h-8 rounded-lg <?= $lowStockCount > 0 ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500' ?> flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
        </div>
        <div class="text-2xl font-bold <?= $lowStockCount > 0 ? 'text-amber-600' : 'text-slate-800' ?>"><?= number_format($lowStockCount) ?></div>
        <p class="text-xs text-slate-400 mt-1">&le; minimum stok</p>
    </div>

    <!-- 4. Barang Masuk (IN) -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Barang Masuk</span>
            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-emerald-600">+<?= number_format($totalIn) ?></div>
        <p class="text-xs text-slate-400 mt-1">Akumulasi inflow</p>
    </div>

    <!-- 5. Barang Keluar (OUT) -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Barang Keluar</span>
            <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
            </div>
        </div>
        <div class="text-2xl font-bold text-rose-600">-<?= number_format($totalOut) ?></div>
        <p class="text-xs text-slate-400 mt-1">Akumulasi outflow</p>
    </div>
</div>

<!-- Grid 2 Kolom: Peringatan Stok Rendah & Mutasi Terbaru -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Peringatan Stok Rendah -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="font-bold text-slate-800 text-sm">Produk Perlu Restock</h2>
                <p class="text-xs text-slate-400">Stok berada di bawah ambang batas minimum</p>
            </div>
            <a href="products/index.php?filter_stock=low" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                Lihat Semua &rarr;
            </a>
        </div>
        <div class="p-5">
            <?php if (empty($lowStockProducts)): ?>
                <div class="p-6 text-center text-slate-400 text-xs">
                    <svg class="w-8 h-8 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Semua stok produk berada dalam kondisi aman (&gt; min stok).
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($lowStockProducts as $lp): ?>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <div>
                                <p class="text-xs font-semibold text-slate-800"><?= e($lp['name']) ?></p>
                                <p class="text-[11px] text-slate-400">SKU: <span class="font-mono"><?= e($lp['sku']) ?></span> &bull; Kategori: <?= e($lp['category_name']) ?></p>
                            </div>
                            <div class="text-right">
                                <span class="px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-100 text-amber-800">
                                    Stok: <?= (int) $lp['stock'] ?> / Min: <?= (int) $lp['minimum_stock'] ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Riwayat Transaksi Mutasi Terakhir -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between">
            <div>
                <h2 class="font-bold text-slate-800 text-sm">Histori Mutasi Terkini</h2>
                <p class="text-xs text-slate-400">5 transaksi mutasi stok barang terakhir</p>
            </div>
            <a href="stock/index.php" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                Lihat Semua &rarr;
            </a>
        </div>
        <div class="p-5">
            <?php if (empty($recentMovements)): ?>
                <div class="p-6 text-center text-slate-400 text-xs">
                    Belum ada riwayat mutasi stok.
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recentMovements as $mv): ?>
                        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100">
                            <div class="flex items-center gap-3">
                                <span class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold <?= $mv['type'] === 'IN' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' ?>">
                                    <?= e($mv['type']) ?>
                                </span>
                                <div>
                                    <p class="text-xs font-semibold text-slate-800"><?= e($mv['product_name']) ?></p>
                                    <p class="text-[11px] text-slate-400"><?= formatTanggal($mv['created_at']) ?> &bull; Oleh: <?= e($mv['user_name']) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-xs font-bold <?= $mv['type'] === 'IN' ? 'text-emerald-600' : 'text-rose-600' ?>">
                                    <?= $mv['type'] === 'IN' ? '+' : '-' ?><?= (int) $mv['quantity'] ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../views/footer.php'; ?>
