<?php
/**
 * Master Data Supplier (Admin Only)
 * Sesuai Standar Pertemuan 4: Slide 8 & 10 (Suppliers & Many-to-Many Relationships)
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth.php';
require_once __DIR__ . '/../../helpers/escape.php';
require_once __DIR__ . '/../../helpers/validation.php';

requireRole('admin');

$pageTitle = 'Master Supplier';
$activeNav = 'suppliers';
$pdo = getDbConnection();

// Memastikan kolom contact_person tersedia jika skema database lama belum memilikinya
try {
    $cols = $pdo->query("SHOW COLUMNS FROM suppliers")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('contact_person', $cols)) {
        $pdo->exec("ALTER TABLE suppliers ADD COLUMN contact_person VARCHAR(100) NULL AFTER name");
    }
} catch (Throwable $e) {
    error_log('Suppliers schema check notice: ' . $e->getMessage());
}

$error = null;

// Tambah Supplier Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid.';
    } else {
        $name = sanitizeString($_POST['name'] ?? '');
        $contact = sanitizeString($_POST['contact_person'] ?? '');
        $phone = sanitizeString($_POST['phone'] ?? '');
        $email = sanitizeString($_POST['email'] ?? '');
        $address = sanitizeString($_POST['address'] ?? '');

        if ($name === '') {
            $error = 'Nama supplier wajib diisi.';
        } else {
            try {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO suppliers (name, contact_person, phone, email, address)
                    VALUES (:name, :contact_person, :phone, :email, :address)
                ");
                $stmtInsert->execute([
                    'name'           => $name,
                    'contact_person' => $contact,
                    'phone'          => $phone,
                    'email'          => $email,
                    'address'        => $address
                ]);
                setFlash('success', "Supplier '{$name}' berhasil ditambahkan.");
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Gagal menyimpan supplier: ' . $e->getMessage();
            }
        }
    }
}

// Hapus Supplier
if (isset($_GET['delete'])) {
    $delId = validatePositiveInt($_GET['delete']);
    if ($delId) {
        // Cek Many-to-Many junction table product_suppliers
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM product_suppliers WHERE supplier_id = :id");
        $stmtCheck->execute(['id' => $delId]);
        if ($stmtCheck->fetchColumn() > 0) {
            setFlash('error', 'Supplier ini tidak dapat dihapus karena masih terhubung dengan beberapa produk (Junction Table Constraint).');
        } else {
            $stmtDel = $pdo->prepare("DELETE FROM suppliers WHERE id = :id");
            $stmtDel->execute(['id' => $delId]);
            setFlash('success', 'Supplier berhasil dihapus.');
        }
    }
    header('Location: index.php');
    exit;
}

// Ambil Daftar Supplier beserta Jumlah Produk yang Disuplai (Junction Table Agregasi)
$stmtSuppliers = $pdo->query("
    SELECT s.id, s.name, s.contact_person, s.phone, s.email, s.address,
           COUNT(ps.product_id) AS total_supplied_products
    FROM suppliers s
    LEFT JOIN product_suppliers ps ON ps.supplier_id = s.id
    GROUP BY s.id, s.name, s.contact_person, s.phone, s.email, s.address
    ORDER BY s.name ASC
");
$suppliers = $stmtSuppliers->fetchAll();

require_once __DIR__ . '/../../views/header.php';
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">Master Data Supplier</h1>
        <p class="text-slate-500 text-sm mt-1">Daftar mitra pemasok dan relasi Many-to-Many dengan produk gudang</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Form Tambah Supplier -->
    <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-xs h-fit">
        <h2 class="font-bold text-slate-800 text-sm mb-4">Tambah Supplier Baru</h2>

        <?php if ($error): ?>
            <div class="p-3 mb-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Nama Perusahaan / Supplier <span class="text-rose-500">*</span></label>
                <input type="text" name="name" required placeholder="Contoh: PT Sumber Elektronik Makmur"
                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Contact Person (PIC)</label>
                <input type="text" name="contact_person" placeholder="Contoh: Hendra Wijaya"
                       class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Nomor Telepon</label>
                    <input type="text" name="phone" placeholder="0812xxxx"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Email</label>
                    <input type="email" name="email" placeholder="sales@supplier.com"
                           class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Alamat Kantor / Gudang</label>
                <textarea name="address" rows="2" placeholder="Jl. Raya Industri No..."
                          class="w-full px-3 py-2 text-xs rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>

            <button type="submit" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-xs rounded-xl shadow-xs transition">
                Simpan Data Supplier
            </button>
        </form>
    </div>

    <!-- Tabel Daftar Supplier -->
    <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-100 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-bold text-slate-800 text-sm">Daftar Mitra Pemasok</h2>
            <span class="text-xs text-slate-400">Total: <?= count($suppliers) ?> Supplier</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-100 font-semibold">
                    <tr>
                        <th class="py-3 px-4">Nama Supplier</th>
                        <th class="py-3 px-4">Kontak / PIC</th>
                        <th class="py-3 px-4">Telepon / Email</th>
                        <th class="py-3 px-4 text-center">Produk Disuplai</th>
                        <th class="py-3 px-4 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="5" class="py-8 text-center text-slate-400">Belum ada supplier yang terdaftar.</td></tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $sup): ?>
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="py-3 px-4 font-semibold text-slate-900">
                                    <?= e($sup['name']) ?>
                                    <p class="text-[10px] text-slate-400 font-normal"><?= e($sup['address'] ?: 'Alamat belum diisi') ?></p>
                                </td>
                                <td class="py-3 px-4 text-slate-700"><?= e($sup['contact_person'] ?: '-') ?></td>
                                <td class="py-3 px-4">
                                    <p class="text-slate-700 font-mono text-[11px]"><?= e($sup['phone'] ?: '-') ?></p>
                                    <p class="text-slate-400 text-[10px]"><?= e($sup['email'] ?: '-') ?></p>
                                </td>
                                <td class="py-3 px-4 text-center font-bold text-indigo-600"><?= (int) $sup['total_supplied_products'] ?> item</td>
                                <td class="py-3 px-4 text-right">
                                    <a href="index.php?delete=<?= (int) $sup['id'] ?>" 
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus supplier ini?');"
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
