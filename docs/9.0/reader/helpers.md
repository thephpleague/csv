---
layout: default
title: Bundled Reader helpers
---

# Bundled reader helpers

## Value Converter

<p class="message-info">New since version <code>9.9.0</code></p>

`League\Csv\ValueConverter` will help you encode your records using PHP's type. By default CSV documents contain string or `null` values.  
Using the `ValueConverter` class you can autoconvert those strings into PHP's scalar type and/or into a `DateTimeImmutable` object.

By default, if the type is not recognized a string will be returned.

```php
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\ValueConverter;

$splFielObject = new SplFileObject(__DIR__ . '/test_files/prenoms.csv');
$reader = Reader::createFromFileObject($splFielObject);
$reader->setDelimiter(';');
$reader->setHeaderOffset(0);

$scalarFormatter = ValueConverter::includeDate('Y-m-d');
$reader->addFormatter($scalarFormatter);
$stmt = Statement::create()->limit(5);
// if a date field with the following format is found it will be converted into a DateImmutable object
// the integer field will be converted into a PHP int type
```

If you want to be more precise and reduce error via auto type conversion that may be introduce in the example
above, you can use the following methods:

- `ValueConverter::convertToInteger`;
- `ValueConverter::convertToFloat`;
- `ValueConverter::convertToBoolean`;
- `ValueConverter::convertToDate`;

As shown in the example below:

```php
use League\Csv\Reader;
use League\Csv\Statement;
use League\Csv\ValueConverter;

$splFielObject = new SplFileObject(__DIR__ . '/test_files/prenoms.csv');
$reader = Reader::createFromFileObject($splFielObject);
$reader->setDelimiter(';');
$reader->setHeaderOffset(0);

$scalarFormatter = ValueConverter::includeDate('!Y');
$reader->addFormatter(fn (array $record): array => [
    ...$record,
    ...[
        'annee' => $scalarFormatter->convertToDate($record['annee']),
        'nombre' => $scalarFormatter->formatInteger($record['nombre']),
    ]
]);
$stmt = Statement::create()->limit(5);
```

If the conversion is not possible the input will be return as is.
