---
layout: default
title: Converting a CSV into a JSON string
---

# JSON conversion

`JsonConverter` converts a CSV records collection into a JSON string by implementing the [Converter interface](/9.0/converter/#converter-interface) and using the [inputEncoding method](/9.0/converter/#records-input-encoding).

## Settings

### JsonConverter::preserveRecordOffset

~~~php
<?php

public JsonConverter::preserveRecordOffset(bool $preserve_offset): self
~~~

This method tells whether the converter should keep or not the CSV record offset in the JSON output. By default, record offsets are not preserved.

### JsonConverter::options

~~~php
<?php

public JsonConverter::options(int options = 0, int $depth = 512): self
~~~

This method sets PHP's `json_encode` optional arguments.

## Conversion

~~~php
<?php
public JsonConverter::convert(iterable $records): string
~~~

The `JsonConverter::convert` accepts an `iterable` which represents the records collection and returns a string.

If a error occurs during the convertion an `RuntimeException` exception is thrown with additional information regarding the error.

~~~php
<?php

use League\Csv\JsonConverter;
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r')
   ->setHeaderOffset(0)
;

$json = (new JsonConverter())
    ->options(JSON_PRETTY_PRINT)
    ->convert($csv)
;

echo '<pre>', $json, PHP_EOL;
// [
//     {
//         "firstname": "john",
//         "lastname": "doe",
//         "email": "john.doe@example.com"
//     },
//     {
//         "firstname": "jane",
//         "lastname": "doe",
//         "email": "jane.doe@example.com"
//     },
//     ...
//     {
//         "firstname": "san",
//         "lastname": "goku",
//         "email": "san.goku@dragon-ball.super"
//     }
// ]
~~~
