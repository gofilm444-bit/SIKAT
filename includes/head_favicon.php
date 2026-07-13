<?php
require_once __DIR__ . '/url_helpers.php';

$esc = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

echo '<link rel="icon" type="image/png" sizes="32x32" href="' . $esc(asset_url('asset/favicon-32.png') . '?v=4') . '">';
echo '<link rel="icon" type="image/png" sizes="16x16" href="' . $esc(asset_url('asset/favicon-16.png') . '?v=4') . '">';
echo '<link rel="apple-touch-icon" sizes="180x180" href="' . $esc(asset_url('asset/logo-sikat-baru-140.png') . '?v=4') . '">';
echo '<link rel="shortcut icon" href="' . $esc(asset_url('asset/favicon.ico') . '?v=4') . '">';
