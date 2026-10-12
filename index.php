<?php
/**
 * Root Router / Redirector
 * Mengarahkan pengunjung ke halaman public yang sesuai
 */

require_once __DIR__ . '/helpers/auth.php';

if (isLoggedIn()) {
    header('Location: public/dashboard.php');
} else {
    header('Location: public/login.php');
}
exit;
