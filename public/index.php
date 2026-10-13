<?php
/**
 * Public Index Router
 * Mengarahkan pengunjung ke Dashboard jika sudah login, atau ke Login jika belum
 */

require_once __DIR__ . '/../helpers/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
