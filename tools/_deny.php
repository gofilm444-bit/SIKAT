<?php
if (!function_exists('forbidden_response')) {
    function forbidden_response(string $message = 'Akses ditolak.'): void {
        http_response_code(403);
        $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        $wantsJson = stripos($accept, 'application/json') !== false;
        if ($wantsJson) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'ok' => false,
                'error' => 'forbidden',
                'message' => $message,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>403 Forbidden</title>';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<style>body{font-family:Arial,sans-serif;margin:40px;color:#222}';
        echo '.box{max-width:520px;padding:16px 20px;border:1px solid #e0e0e0;border-radius:8px;background:#fafafa}';
        echo 'h1{font-size:18px;margin:0 0 8px}p{margin:6px 0}</style></head><body>';
        echo '<div class="box"><h1>403 Forbidden</h1>';
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p>Halaman ini hanya tersedia untuk administrator lokal.</p></div></body></html>';
        exit;
    }
}
