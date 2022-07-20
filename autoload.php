<?php

require __DIR__ . '/src/functions_include.php';

spl_autoload_register(static function (string $class_name): void {

    $prefix = 'League\Csv\\';
    if (!str_starts_with($class_name, $prefix)) {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class_name, 11)) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});
