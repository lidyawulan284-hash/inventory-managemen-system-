<?php
/**
 * Skrip Logout Pengguna
 * Sesuai Standar Pertemuan 4: Session Termination & Safe Redirect
 */

require_once __DIR__ . '/../helpers/auth.php';

logoutUser();

// Arahkan kembali ke login dengan query string status=logged_out
header('Location: login.php?status=logged_out');
exit;
