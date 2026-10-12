<?php
/**
 * Helper Autentikasi dan Otorisasi Session
 * Sesuai Standar Pertemuan 4: Session, Authentication, Authorization & Security
 */

/**
 * Memulai session PHP dengan parameter keamanan cookie terbaik.
 */
function startAppSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $cookieParams = [
            'lifetime' => 0, // Session cookie berlaku sampai browser ditutup
            'path'     => '/',
            'domain'   => '',
            'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true, // Mencegah akses cookie via JavaScript (Anti-XSS Session Theft)
            'samesite' => 'Lax' // Mengurangi risiko Cross-Site Request Forgery (CSRF)
        ];

        session_set_cookie_params($cookieParams);
        session_start();
    }
}

// Inisialisasi session otomatis saat file ini dimuat
startAppSession();

/**
 * Menyimpan data pengguna ke dalam session setelah verifikasi password berhasil.
 * Menggunakan session_regenerate_id(true) untuk mencegah Session Fixation.
 *
 * @param array $user Data pengguna dari database
 */
function loginUser(array $user): void
{
    // Regenerasi session ID untuk mencegah serangan Session Fixation
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['user'] = [
        'id'        => (int) $user['id'],
        'username'  => (string) $user['username'],
        'full_name' => (string) ($user['full_name'] ?? $user['name'] ?? $user['username']),
        'role'      => (string) $user['role'], // 'admin' atau 'staff'
    ];

    $_SESSION['logged_in_at'] = time();
}

/**
 * Menghapus seluruh data session dan menghancurkan cookie session (Logout aman).
 */
function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Memeriksa apakah pengguna saat ini sedang login.
 *
 * @return bool
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/**
 * Mengambil data pengguna yang sedang login.
 *
 * @return array|null
 */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Memeriksa apakah pengguna memiliki salah satu role yang diizinkan.
 *
 * @param string|array $roles Role atau array role, misal 'admin' atau ['admin', 'staff']
 * @return bool
 */
function hasRole($roles): bool
{
    if (!isLoggedIn()) {
        return false;
    }

    $currentRole = $_SESSION['user']['role'] ?? '';
    if (is_array($roles)) {
        return in_array($currentRole, $roles, true);
    }

    return $currentRole === $roles;
}

/**
 * Memastikan pengguna sudah login. Jika belum, dialihkan ke halaman login.
 *
 * @param string $loginUrl URL halaman login
 */
function requireLogin(string $loginUrl = '/project%20php%20native/public/login.php'): void
{
    if (!isLoggedIn()) {
        // Simpan URL yang sedang diakses untuk redirect kembali setelah login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . $loginUrl);
        exit;
    }
}

/**
 * Memastikan pengguna memiliki role tertentu.
 * Jika tidak berhak, mengembalikan HTTP 403 Forbidden.
 *
 * @param string|array $allowedRoles
 */
function requireRole($allowedRoles): void
{
    requireLogin();

    if (!hasRole($allowedRoles)) {
        http_response_code(403);
        $allowed = is_array($allowedRoles) ? implode(', ', $allowedRoles) : $allowedRoles;
        $current = $_SESSION['user']['role'] ?? 'Guest';
        
        // Tampilkan halaman error 403 yang informatif
        echo "<!DOCTYPE html>
        <html lang='id'>
        <head>
            <meta charset='UTF-8'>
            <title>403 Terlarang - Akses Ditolak</title>
            <script src='https://cdn.tailwindcss.com'></script>
        </head>
        <body class='bg-slate-100 flex items-center justify-center min-h-screen p-4 font-sans'>
            <div class='bg-white p-8 rounded-2xl shadow-xl max-w-md w-full text-center border border-red-100'>
                <div class='w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl font-bold'>
                    403
                </div>
                <h1 class='text-2xl font-bold text-slate-800 mb-2'>Akses Ditolak (Forbidden)</h1>
                <p class='text-slate-600 text-sm mb-4 leading-relaxed'>
                    Role Anda saat ini adalah <strong class='text-slate-900 capitalize'>{$current}</strong>.
                    Halaman ini membutuhkan hak akses: <span class='font-semibold text-red-600 capitalize'>{$allowed}</span>.
                </p>
                <div class='flex gap-3 justify-center'>
                    <a href='/project%20php%20native/public/dashboard.php' class='px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition'>
                        Kembali ke Dashboard
                    </a>
                </div>
            </div>
        </body>
        </html>";
        exit;
    }
}

/**
 * Helper CSRF Token untuk melindungi formulir POST
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $token);
}
