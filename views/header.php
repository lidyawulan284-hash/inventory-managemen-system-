<?php
/**
 * Layout Header & Navigasi Aplikasi
 * Sesuai Standar Pertemuan 4: Layout Template, Session & Role Authorization
 */

require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/validation.php';

// Proteksi Halaman Internal: Wajib Login
requireLogin();

$user = currentUser();
$flashMessages = getFlashMessages();

// Tentukan basis URL relatif
$baseUrl = '/project%20php%20native/public';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>Inventory System</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 flex flex-col">

    <!-- Top Navigation Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-xs">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <!-- Brand & Main Links -->
                <div class="flex items-center gap-8">
                    <a href="<?= $baseUrl ?>/dashboard.php" class="flex items-center gap-2.5 font-bold text-slate-800 hover:text-indigo-600 transition">
                        <div class="w-9 h-9 bg-indigo-600 text-white rounded-xl flex items-center justify-center shadow-md shadow-indigo-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <span class="text-base font-bold tracking-tight">Inventory<span class="text-indigo-600">Pro</span></span>
                    </a>

                    <!-- Nav Items -->
                    <nav class="hidden md:flex items-center gap-1 text-sm font-medium">
                        <a href="<?= $baseUrl ?>/dashboard.php" 
                           class="px-3 py-2 rounded-lg <?= ($activeNav ?? '') === 'dashboard' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?> transition">
                            Dashboard
                        </a>
                        <a href="<?= $baseUrl ?>/products/index.php" 
                           class="px-3 py-2 rounded-lg <?= ($activeNav ?? '') === 'products' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?> transition">
                            Produk & Stok
                        </a>
                        <a href="<?= $baseUrl ?>/stock/index.php" 
                           class="px-3 py-2 rounded-lg <?= ($activeNav ?? '') === 'stock' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?> transition">
                            Mutasi Barang
                        </a>
                        <?php if (hasRole('admin')): ?>
                            <a href="<?= $baseUrl ?>/categories/index.php" 
                               class="px-3 py-2 rounded-lg <?= ($activeNav ?? '') === 'categories' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?> transition">
                                Kategori
                            </a>
                            <a href="<?= $baseUrl ?>/suppliers/index.php" 
                               class="px-3 py-2 rounded-lg <?= ($activeNav ?? '') === 'suppliers' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?> transition">
                                Supplier
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>

                <!-- User Profile & Logout -->
                <div class="flex items-center gap-4">
                    <div class="hidden sm:flex items-center gap-2.5 pl-4 border-l border-slate-200">
                        <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-xs uppercase">
                            <?= substr(e($user['username']), 0, 2) ?>
                        </div>
                        <div class="text-left text-xs">
                            <p class="font-semibold text-slate-800 leading-tight"><?= e($user['full_name']) ?></p>
                            <span class="inline-block mt-0.5 px-1.5 py-0.2 rounded text-[10px] font-semibold uppercase tracking-wider <?= $user['role'] === 'admin' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700' ?>">
                                <?= e($user['role']) ?>
                            </span>
                        </div>
                    </div>

                    <a href="<?= $baseUrl ?>/logout.php" 
                       onclick="return confirm('Apakah Anda yakin ingin keluar?');"
                       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium text-slate-600 hover:text-rose-600 hover:bg-rose-50 border border-slate-200 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        <span>Keluar</span>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Flash Messages Notification Container -->
        <?php if (!empty($flashMessages)): ?>
            <div class="space-y-3 mb-6">
                <?php foreach ($flashMessages as $type => $messages): ?>
                    <?php 
                        $bgColor = 'bg-slate-100 border-slate-300 text-slate-800';
                        if ($type === 'success') $bgColor = 'bg-emerald-50 border-emerald-200 text-emerald-800';
                        if ($type === 'error') $bgColor = 'bg-rose-50 border-rose-200 text-rose-800';
                        if ($type === 'warning') $bgColor = 'bg-amber-50 border-amber-200 text-amber-800';
                        if ($type === 'info') $bgColor = 'bg-sky-50 border-sky-200 text-sky-800';
                    ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="p-4 rounded-xl border <?= $bgColor ?> text-sm flex items-center justify-between shadow-xs">
                            <div class="flex items-center gap-3">
                                <?php if ($type === 'success'): ?>
                                    <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707-9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <?php elseif ($type === 'error'): ?>
                                    <svg class="h-5 w-5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                <?php endif; ?>
                                <span><?= e($msg) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
