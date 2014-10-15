---
layout: default
title: Installation
permalink: installation/
---

# Installation

## System Requirements

You need **PHP >= 5.4.0** or **HHVM >= 3.2** and the `mbstring` extension to use `League\Csv` but the latest stable version of PHP/HHVM is recommended.

## Composer

CSV is available on [Packagist](https://packagist.org/packages/league/csv) and can be installed using [Composer](https://getcomposer.org/):

~~~
composer require league/csv
~~~

This will edit (or create) your `composer.json` file and automatically choose the most recent version, for example: `~6.0`


Most modern frameworks will include Composer out of the box, but ensure the following file is included:

~~~php
<?php

// Include the Composer autoloader
require 'vendor/autoload.php';
~~~

## Going Solo

You can also use CSV without using Composer by registing an autoloader function:

~~~php
spl_autoload_register(function ($class) {
    $prefix = 'League\\Csv\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // no, move to the next registered autoloader
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});
~~~

Or, use any other [PSR-4](http://www.php-fig.org/psr/psr-4/) compatible autoloader.
