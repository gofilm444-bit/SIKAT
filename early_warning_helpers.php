<?php
if (!function_exists('early_warning_level_order')) {
    function early_warning_level_order(): array {
        return ['black', 'red', 'yellow', 'green'];
    }
}

if (!function_exists('early_warning_adjust_code')) {
    function early_warning_adjust_code(string $code, int $boost): string {
        $boost = max(0, $boost);
        if ($boost === 0) {
            return $code;
        }
        $order = early_warning_level_order();
        $index = array_search($code, $order, true);
        if ($index === false) {
            $index = 0;
        }
        $target = min(count($order) - 1, $index + $boost);
        return $order[$target];
    }
}

if (!function_exists('early_warning_label')) {
    function early_warning_label(string $code, int $warnThresholdDays): string {
        switch ($code) {
            case 'green':
                return 'Hijau';
            case 'yellow':
                return $warnThresholdDays >= 10 ? 'Perlu perhatian' : 'Mendekati tenggat';
            case 'red':
                return 'Merah';
            default:
                return 'Hitam';
        }
    }
}

if (!function_exists('early_warning_color')) {
    function early_warning_color(string $code): string {
        switch ($code) {
            case 'green':
                return '#16a34a';
            case 'yellow':
                return '#f59e0b';
            case 'red':
                return '#dc2626';
            default:
                return '#111827';
        }
    }
}

if (!function_exists('early_warning_base_level')) {
    function early_warning_base_level(int $diff, int $warnThresholdDays): array {
        if ($diff > $warnThresholdDays) {
            $descSuffix = $diff === 1 ? 'Jatuh tempo 1 hari lagi' : 'Jatuh tempo '.$diff.' hari lagi';
            return ['green', 'Aman - '.$descSuffix];
        }
        if ($diff >= 0) {
            if ($diff === 0) {
                $desc = 'Jatuh tempo hari ini';
            } elseif ($diff === 1) {
                $desc = 'Jatuh tempo 1 hari lagi';
            } else {
                $desc = 'Jatuh tempo '.$diff.' hari lagi';
            }
            return ['yellow', $desc];
        }

        $over = abs($diff);
        if ($diff >= -5) {
            $desc = $over === 1 ? 'Lewat 1 hari' : 'Lewat '.$over.' hari';
            return ['red', $desc];
        }
        return ['black', 'Lewat '.$over.' hari'];
    }
}
