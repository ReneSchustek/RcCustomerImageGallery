<?php

declare(strict_types=1);

$pluginAutoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($pluginAutoloader)) {
    require_once $pluginAutoloader;
}

$shopwareAutoloader = dirname(__DIR__, 4) . '/vendor/autoload.php';
if (file_exists($shopwareAutoloader)) {
    require_once $shopwareAutoloader;
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Ruhrcoder\\RcCustomerImageGallery\\Tests\\' => __DIR__ . '/',
        'Ruhrcoder\\RcCustomerImageGallery\\' => dirname(__DIR__) . '/src/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $length = strlen($prefix);
        if (strncmp($class, $prefix, $length) !== 0) {
            continue;
        }
        $file = $baseDir . str_replace('\\', '/', substr($class, $length)) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
