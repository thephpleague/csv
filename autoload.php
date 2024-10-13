<?php

require __DIR__ . '/src/functions_include.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'League\Csv\\')) {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 11)).'.php';
    if (is_readable($file)) {
        require $file;
    }
});
