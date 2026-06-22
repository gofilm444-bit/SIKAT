<?php
require_once __DIR__ . '/includes/security_headers.php';
require_once __DIR__ . '/includes/session_hardening.php';
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';

$DB_HOST = $DB_HOST ?? '127.0.0.1';
$DB_USER = $DB_USER ?? 'root';
$DB_PASS = $DB_PASS ?? ''; // default XAMPP
$DB_NAME = $DB_NAME ?? 'ski_db'; // samakan dengan nama DB kamu

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die('Koneksi database gagal: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// Bootstrap schema (tambahkan tabel/kolom yang dibutuhkan aplikasi).
$schemaBootstrapCandidates = [
    __DIR__ . '/schema_bootstrap.php',
    __DIR__ . '/db/schema_bootstrap.php',
];
foreach ($schemaBootstrapCandidates as $schemaBootstrap) {
    if (is_file($schemaBootstrap)) {
        require_once $schemaBootstrap;
        break;
    }
}
