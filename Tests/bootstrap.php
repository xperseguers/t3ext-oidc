<?php

declare(strict_types=1);

// Register test namespace autoloader
spl_autoload_register(static function (string $class): void {
    $prefix = 'Causal\\Oidc\\Tests\\';
    $baseDir = __DIR__ . '/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Include Composer autoloader
require dirname(__DIR__, 3) . '/vendor/autoload.php';
