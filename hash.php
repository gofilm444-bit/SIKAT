<?php
require_once __DIR__ . '/bootstrap.php';
echo password_hash('admin123', PASSWORD_DEFAULT);
