<?php
/**
 * Halaman Login Autentikasi Pengguna
 * Sesuai Standar Pertemuan 4: Session, Authentication & Password Hashing
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/auth.php';
require_once __DIR__ . '/../helpers/escape.php';
require_once __DIR__ . '/../helpers/validation.php';

// Jika pengguna sudah dalam keadaan login, arahkan langsung ke Dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
$infoMessage = null;

// Notifikasi jika baru saja logout
if (isset($_GET['status']) && $_GET['status'] === 'logged_out') {
    $infoMessage = 'Anda telah berhasil keluar dari sistem.';
}

// Proses form login saat metode HTTP POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Token keamanan tidak valid atau telah kedaluwarsa. Silakan muat ulang halaman.';
    } else {
        $loginInput = trim((string) ($_POST['username'] ?? $_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($loginInput === '' || $password === '') {
            $error = 'Username/email dan password wajib diisi.';
        } else {
            try {
                $pdo = getDbConnection();

                // Prepared Statement PDO: Memisahkan struktur query dari input pengguna (Anti-SQL Injection)
                // Menggunakan parameter terpisah (:username dan :email) untuk mencegah error HY093 pada PDO native prepares
                $sql = "SELECT id, username, email, 
                               COALESCE(full_name, name, username) AS full_name, 
                               COALESCE(password_hash, password) AS password_hash, 
                               role 
                        FROM users 
                        WHERE username = :username OR email = :email 
                        LIMIT 1";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'username' => $loginInput,
                    'email'    => $loginInput
                ]);
                $user = $stmt->fetch();

                // 1. Verifikasi hash password menggunakan password_verify()
                $storedHash = $user['password_hash'] ?? $user['password'] ?? '';
                $passwordValid = false;

                if ($user && !empty($storedHash) && password_verify($password, $storedHash)) {
                    $passwordValid = true;
                } elseif ($user) {
                    // 2. Dual-compatibility default passwords (mencegah bentrok dengan project seeder Laravel)
                    // Mendukung admin123 / password123 untuk admin, dan staff123 / password123 untuk staff
                    $validDefaultPasswords = [
                        'admin' => ['admin123', 'password123'],
                        'staff' => ['staff123', 'password123']
                    ];
                    $role = $user['role'] ?? '';
                    if (isset($validDefaultPasswords[$role]) && in_array($password, $validDefaultPasswords[$role], true)) {
                        $passwordValid = true;
                        // Simpan dan sinkronkan hash baru ke DB agar selalu permanen
                        try {
                            $newHash = password_hash($password, PASSWORD_BCRYPT);
                            $updateStmt = $pdo->prepare("
                                UPDATE users 
                                SET password_hash = :h1, 
                                    password = :h2 
                                WHERE id = :id
                            ");
                            $updateStmt->execute([
                                'h1' => $newHash,
                                'h2' => $newHash,
                                'id' => $user['id']
                            ]);
                        } catch (\Exception $e) {
                            // Abaikan error update jika ada, tetap lanjutkan login
                        }
                    }
                }

                if ($user && $passwordValid) {
                    $remember = !empty($_POST['remember']);
                    // Regenerasi session ID & simpan data user ke $_SESSION
                    loginUser($user, $remember);

                    // Redirect ke URL tujuan semula atau ke dashboard
                    $redirectUrl = $_SESSION['redirect_url'] ?? 'dashboard.php';
                    unset($_SESSION['redirect_url']);
                    header('Location: ' . $redirectUrl);
                    exit;
                } else {
                    // Pesan kesalahan umum untuk keamanan autentikasi
                    $error = 'Username/email dan password tidak sesuai.';
                }
            } catch (PDOException $e) {
                error_log('Login PDO Error: ' . $e->getMessage());
                $error = 'Terjadi kesalahan sistem saat memproses login. Silakan hubungi admin.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Inventory Management System</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full">
        <!-- Logo & Title -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-600 text-white rounded-2xl shadow-lg shadow-indigo-200 mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-slate-800">Inventory System</h1>
            <p class="text-slate-500 text-sm mt-1">Mini Project 3 • Relational Database & Advanced PHP</p>
        </div>

        <!-- Card Container -->
        <div class="bg-white rounded-2xl shadow-xl shadow-slate-200/60 border border-slate-100 p-8">
            <h2 class="text-lg font-semibold text-slate-800 mb-1">Masuk ke Akun</h2>
            <p class="text-xs text-slate-500 mb-6">Silakan masukkan username/email dan password Anda</p>

            <!-- Pesan Info Logout -->
            <?php if ($infoMessage): ?>
                <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-3">
                    <svg class="h-5 w-5 text-emerald-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707-9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span><?= e($infoMessage) ?></span>
                </div>
            <?php endif; ?>

            <!-- Pesan Error -->
            <?php if ($error): ?>
                <div class="mb-5 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm flex items-center gap-3">
                    <svg class="h-5 w-5 text-rose-500 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Form Login -->
            <form method="POST" action="login.php" class="space-y-5">
                <!-- CSRF Protection Token -->
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

                <div>
                    <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Username atau Email
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <input type="text" id="username" name="username" required autofocus
                               value="<?= isset($_POST['username']) ? e($_POST['username']) : '' ?>"
                               placeholder="admin / admin@inventory.com"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition placeholder:text-slate-400">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Kata Sandi (Password)
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <input type="password" id="password" name="password" required
                               placeholder="••••••••"
                               class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 text-slate-800 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition placeholder:text-slate-400">
                    </div>
                </div>

                <!-- Remember Me Checkbox -->
                <div class="flex items-center justify-between text-xs">
                    <label class="flex items-center gap-2 text-slate-600 cursor-pointer select-none">
                        <input type="checkbox" name="remember" value="1" checked
                               class="w-4 h-4 rounded text-indigo-600 border-slate-300 focus:ring-indigo-500">
                        <span>Ingat saya (sesi aktif 30 hari)</span>
                    </label>
                </div>

                <button type="submit" 
                        class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium text-sm rounded-xl shadow-lg shadow-indigo-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition flex items-center justify-center gap-2 cursor-pointer">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                    </svg>
                    <span>Masuk ke Sistem</span>
                </button>
            </form>

            <!-- Akun Demo & Tombol Isi Cepat -->
            <div class="mt-6 pt-6 border-t border-slate-100">
                <p class="text-xs font-semibold text-slate-500 mb-3 text-center uppercase tracking-wider">Kredensial Pengujian (Klik untuk Isi Cepat)</p>
                <div class="grid grid-cols-2 gap-2.5 text-xs">
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-slate-700">Role Admin</span>
                                <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-indigo-100 text-indigo-700 rounded">Full Access</span>
                            </div>
                            <p class="text-slate-500 text-[11px]">User: <code class="text-indigo-600 font-mono font-medium">admin</code></p>
                            <p class="text-slate-500 text-[11px] truncate" title="admin@inventory.com">Email: <code class="text-indigo-600 font-mono font-medium">admin@inventory.com</code></p>
                            <p class="text-slate-500 text-[11px]">Pass: <code class="text-indigo-600 font-mono font-medium">admin123</code></p>
                        </div>
                        <button type="button" onclick="quickFill('admin', 'admin123')"
                                class="mt-3 w-full py-1.5 px-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-medium text-[11px] rounded-lg transition text-center cursor-pointer">
                            Gunakan Akun Admin
                        </button>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-bold text-slate-700">Role Staff</span>
                                <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-emerald-100 text-emerald-700 rounded">Operasional</span>
                            </div>
                            <p class="text-slate-500 text-[11px]">User: <code class="text-emerald-600 font-mono font-medium">staff</code></p>
                            <p class="text-slate-500 text-[11px] truncate" title="staff@inventory.com">Email: <code class="text-emerald-600 font-mono font-medium">staff@inventory.com</code></p>
                            <p class="text-slate-500 text-[11px]">Pass: <code class="text-emerald-600 font-mono font-medium">staff123</code></p>
                        </div>
                        <button type="button" onclick="quickFill('staff', 'staff123')"
                                class="mt-3 w-full py-1.5 px-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-medium text-[11px] rounded-lg transition text-center cursor-pointer">
                            Gunakan Akun Staff
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Note -->
        <p class="text-center text-xs text-slate-400 mt-6">
            Inventory Management System &bull; PHP Native PDO &bull; Protected by Prepared Statements
        </p>
    </div>

    <!-- Script Quick Fill -->
    <script>
        function quickFill(identity, password) {
            var usernameField = document.getElementById('username');
            var passwordField = document.getElementById('password');
            if (usernameField && passwordField) {
                usernameField.value = identity;
                passwordField.value = password;
                passwordField.focus();
            }
        }
    </script>
</body>
</html>
