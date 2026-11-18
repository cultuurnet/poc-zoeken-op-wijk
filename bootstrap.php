<?php

declare(strict_types=1);

if (PHP_VERSION_ID < 80100) {
    fwrite(STDERR, "PHP 8.1 of hoger is vereist.\n");
    exit(1);
}

require __DIR__ . '/src/Exceptions.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'PocWijken\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . substr($class, strlen($prefix)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
