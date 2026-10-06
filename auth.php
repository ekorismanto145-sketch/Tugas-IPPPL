<?php
require_once 'koneksi.php';

function require_login(): void
{
    if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
        header('Location: login.php');
        exit;
    }
}

function current_role(): string
{
    return $_SESSION['role'] ?? '';
}

function is_admin(): bool
{
    return current_role() === 'Admin';
}

function is_staff(): bool
{
    return current_role() === 'Staff' || current_role() === 'Admin';
}

function is_user(): bool
{
    return current_role() === 'User';
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        echo "<script>alert('Akses ditolak! Halaman ini hanya untuk Admin.'); window.location.href='dashboard.php';</script>";
        exit;
    }
}

function require_staff(): void
{
    require_login();
    if (is_user()) {
        header('Location: customer_dashboard.php');
        exit;
    }
}

function require_user(): void
{
    require_login();
    if (!is_user()) {
        header('Location: dashboard.php');
        exit;
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_pelanggan_id(int $id): string
{
    return 'PEL-' . str_pad((string)$id, 4, '0', STR_PAD_LEFT);
}

function format_wa_phone(?string $phone): string
{
    if (!$phone) return '';
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($clean, '0')) {
        $clean = '62' . substr($clean, 1);
    }
    return $clean;
}

