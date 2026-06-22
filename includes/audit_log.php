<?php
declare(strict_types=1);

if (!function_exists('audit_log')) {
    function audit_log(mysqli $conn, string $action, string $entity, ?int $entityId = null, array $details = []): void {
        if ($action === '' || $entity === '') { return; }
        $user = $_SESSION['user'] ?? [];
        $userId = isset($user['id']) ? (int)$user['id'] : null;
        $username = (string)($user['username'] ?? '');
        $role = (string)($user['peran'] ?? '');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (strlen($ua) > 250) { $ua = substr($ua, 0, 250); }
        $detailsJson = null;
        if (!empty($details)) {
            $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($detailsJson === false) { $detailsJson = null; }
        }

        $stmt = $conn->prepare(
            "INSERT INTO audit_log (user_id, username, role, action, entity, entity_id, ip_address, user_agent, details)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) { return; }
        $entityIdParam = $entityId;
        $userIdParam = $userId;
        $stmt->bind_param(
            "issssisss",
            $userIdParam,
            $username,
            $role,
            $action,
            $entity,
            $entityIdParam,
            $ip,
            $ua,
            $detailsJson
        );
        $stmt->execute();
        $stmt->close();
    }
}
