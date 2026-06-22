<?php
return [
    'public' => [
        'login.php',
        'logout.php',
        'proses_login.php',
        'asset/*',
        'assets/*',
        'css/*',
        'js/*',
        'images/*',
        'favicon.ico',
    ],
    'admin_only' => [
        'pengguna.php',
        'hash.php',
    ],
    'auth_only' => [
        '*',
    ],
];
