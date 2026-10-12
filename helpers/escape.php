<?php
/**
 * Helper Output Encoding dan Format Tampilan
 * Sesuai Standar Pertemuan 4: XSS & Output Encoding
 */

/**
 * Melakukan encoding karakter khusus ke entitas HTML untuk mencegah Cross-Site Scripting (XSS).
 *
 * @param mixed $value
 * @return string
 */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Format angka ke format mata uang Rupiah
 *
 * @param float|int $amount
 * @return string
 */
function formatRupiah($amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

/**
 * Format tanggal Indonesia
 *
 * @param string|null $datetime
 * @return string
 */
function formatTanggal(?string $datetime): string
{
    if (!$datetime) {
        return '-';
    }
    $timestamp = strtotime($datetime);
    return date('d M Y, H:i', $timestamp);
}
