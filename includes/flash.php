<?php
if (!isset($flash_messages) || !is_array($flash_messages)) {
    $flash_messages = [];
}
if ($flash_messages === []) {
    if (!empty($_SESSION['flash_success'])) { $flash_messages[] = ['type' => 'success', 'message' => (string)$_SESSION['flash_success']]; unset($_SESSION['flash_success']); }
    if (!empty($_SESSION['flash_error'])) { $flash_messages[] = ['type' => 'danger', 'message' => (string)$_SESSION['flash_error']]; unset($_SESSION['flash_error']); }
    if (!empty($_SESSION['flash_info'])) { $flash_messages[] = ['type' => 'info', 'message' => (string)$_SESSION['flash_info']]; unset($_SESSION['flash_info']); }
    if (!empty($_SESSION['flash_warning'])) { $flash_messages[] = ['type' => 'warning', 'message' => (string)$_SESSION['flash_warning']]; unset($_SESSION['flash_warning']); }
}
if ($flash_messages === []) {
    return;
}
foreach ($flash_messages as $item) {
    if (!is_array($item)) { continue; }
    $type = (string)($item['type'] ?? 'info');
    $message = (string)($item['message'] ?? '');
    if ($message === '') { continue; }
    if ($type === 'error') { $type = 'danger'; }
    if ($type === 'warn') { $type = 'warning'; }
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $safe = str_replace(['&lt;b&gt;', '&lt;/b&gt;'], ['<b>', '</b>'], $safe);
    echo '<div class="alert alert-' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '">' . $safe . '</div>';
}
