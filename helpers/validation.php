<?php
/**
 * Helper Validasi Input dan Flash Notification
 * Sesuai Standar Pertemuan 4: Input Validation & Sanitization
 */

/**
 * Menyimpan pesan notifikasi sementara (flash message) ke dalam session
 *
 * @param string $type Tipe pesan ('success', 'error', 'warning', 'info')
 * @param string $message Isi pesan
 */
function setFlash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash_messages'][$type][] = $message;
}

/**
 * Mengambil dan menghapus seluruh pesan flash dari session
 *
 * @return array
 */
function getFlashMessages(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Sanitasi string input dari whitespace berlebih
 *
 * @param mixed $input
 * @return string
 */
function sanitizeString($input): string
{
    return trim((string) $input);
}

/**
 * Validasi integer positif (misal untuk kuantitas atau ID)
 *
 * @param mixed $val
 * @param int $min
 * @return int|false
 */
function validatePositiveInt($val, int $min = 1)
{
    $filtered = filter_var($val, FILTER_VALIDATE_INT);
    if ($filtered === false || $filtered < $min) {
        return false;
    }
    return $filtered;
}

/**
 * Validasi harga atau nilai desimal non-negatif
 *
 * @param mixed $val
 * @return float|false
 */
function validateNonNegativeFloat($val)
{
    $filtered = filter_var($val, FILTER_VALIDATE_FLOAT);
    if ($filtered === false || $filtered < 0) {
        return false;
    }
    return (float) $filtered;
}
