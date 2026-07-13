<?php

if (!function_exists('app_base_path')) {
    function app_base_path(): string {
        $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $script = '/' . ltrim($script, '/');
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        if ($dir === '/' || $dir === '.') {
            return '';
        }
        if (substr($dir, -7) === '/public') {
            $dir = substr($dir, 0, -7);
        }
        return $dir === '/' ? '' : $dir;
    }
}

if (!function_exists('app_url')) {
    function app_url(string $path = '', array $query = [], string $fragment = ''): string {
        $base = app_base_path();
        $path = trim($path);
        if ($path === '') {
            $url = $base !== '' ? $base . '/' : '/';
        } else {
            $path = '/' . ltrim($path, '/');
            $url = $base . $path;
        }
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        if ($fragment !== '') {
            $url .= '#' . rawurlencode(ltrim($fragment, '#'));
        }
        return $url;
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string {
        return app_url(ltrim($path, '/'));
    }
}

if (!function_exists('endpoint_url')) {
    function endpoint_url(string $endpoint, array $query = [], string $fragment = ''): string {
        $endpoint = ltrim(trim($endpoint), '/');
        $allowed = [
            'attachment_download.php',
            'download.php',
            'chr_export.php',
            'chr_export_pdf.php',
            'chr_sop_export.php',
            'chr_sop_export_pdf.php',
            'dokumen_export.php',
            'laporan_export.php',
            'verifikasi_export.php',
        ];
        if (!in_array($endpoint, $allowed, true)) {
            $endpoint = basename($endpoint);
        }
        return app_url($endpoint, $query, $fragment);
    }
}

if (!function_exists('route_url')) {
    function route_url(string $route, array $query = [], string $fragment = ''): string {
        $route = trim($route, '/');
        $map = [
            '' => '',
            'home' => '',
            'login' => 'login',
            'logout' => 'logout',
            'dashboard' => 'dashboard',
            'review' => 'review',
            'pelaporan' => 'pelaporan',
            'pengguna' => 'pengguna',
            'kebijakan' => 'kebijakan',
            'risiko' => 'risiko',
            'self_assessment' => 'self-assessment',
            'self-assessment' => 'self-assessment',
            'settings' => 'settings',
            'public_media' => 'public-media',
            'public_contacts' => 'public-contacts',
            'mail_recipients' => 'mail-recipients',
        ];
        return app_url($map[$route] ?? $route, $query, $fragment);
    }
}

if (!function_exists('review_url')) {
    function review_url(string $tab = '', array $query = [], string $fragment = ''): string {
        $tab = trim($tab);
        $tabMap = [
            '' => '',
            'jadwal' => 'jadwal',
            'asg' => 'penugasan',
            'penugasan' => 'penugasan',
            'dok' => 'dokumen',
            'dokumen' => 'dokumen',
            'chr' => 'chr',
            'laporan' => 'laporan',
            'verifikasi' => 'verifikasi',
            'master' => 'master',
        ];
        $pathTab = $tabMap[$tab] ?? $tab;
        $rid = isset($query['rid']) ? (int)$query['rid'] : 0;
        if ($pathTab === 'chr' && $rid > 0) {
            unset($query['rid']);
            return app_url('review/chr/' . $rid, $query, $fragment);
        }
        $path = $pathTab === '' ? 'review' : 'review/' . $pathTab;
        return app_url($path, $query, $fragment);
    }
}
