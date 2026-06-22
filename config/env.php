<?php
if (!function_exists('env')) {
    function env($key, $default = null) {
        if ($key === null || $key === '') {
            return $default;
        }
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }
        return $default;
    }
}

if (!function_exists('env_load')) {
    function env_load($path) {
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return false;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ($key === '') {
                continue;
            }
            if ((strlen($value) >= 2) && ((($value[0] === '"') && (substr($value, -1) === '"')) || (($value[0] === "'") && (substr($value, -1) === "'")))) {
                $value = substr($value, 1, -1);
            }
            if (getenv($key) !== false || array_key_exists($key, $_ENV) || array_key_exists($key, $_SERVER)) {
                continue;
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
        return true;
    }
}

$__envFile = dirname(__DIR__) . '/.env';
if (is_file($__envFile) && is_readable($__envFile)) {
    env_load($__envFile);
}
