<?php
require_once __DIR__ . '/../includes/security_headers.php';
require_once __DIR__ . '/../includes/session_hardening.php';
include_once __DIR__ . '/../config/env.php';
include_once __DIR__ . '/../config/database.php';

// Koneksi database untuk file terpisah
$host = $DB_HOST ?? 'localhost';
$user = $DB_USER ?? 'root';
$pass = $DB_PASS ?? '';
$db = $DB_NAME ?? 'ski_db'; // pastikan ini sama persis dengan nama database di phpMyAdmin/MySQL
$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die('Koneksi database gagal: ' . mysqli_connect_error());
}
function query($sql) {
    global $conn;
    $res = mysqli_query($conn, $sql);
    if ($res === true || $res === false) return $res;
    $rows = [];
    while($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    return $rows;
}

$schemaBootstrap = __DIR__ . '/schema_bootstrap.php';
if (is_file($schemaBootstrap)) {
    require_once $schemaBootstrap;
}
// --- Refresh akses user di session agar menu/topbar selalu update ---
if (session_status() === PHP_SESSION_NONE) { @session_start(); }

if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $uid = (int)($_SESSION['user']['id'] ?? 0);
    if ($uid > 0) {
        $sql = "SELECT id, nama, username, peran, status, akses_dashboard, akses_pelaporan, akses_review
                FROM pengguna WHERE id=? LIMIT 1";
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "i", $uid);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $fresh = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);

            if ($fresh) {
                $_SESSION['user'] = array_merge($_SESSION['user'], $fresh);
            }
        }
    }
}

?>

