---
layout: default
title: Converting a CSV into a JSON string
---

# JSON conversion

`JsonConverter` converts a CSV records collection into a JSON string by implementing the [Converter interface](/9.0/converter/#converter-interface) and using the [inputEncoding method](/9.0/converter/#records-input-encoding).

## Settings

### JsonConverter::options

~~~php
<?php

public JsonConverter::options(int options, int $depth = 512): self
~~~

This method sets PHP's `json_encode` optional arguments.

## Conversion

~~~php
<?php
public JsonConverter::convert(iterable $records): string
~~~

The `JsonConverter::convert` accepts an `iterable` which represents the records collection and returns a string.

<p class="message-warning">If a error occurs during the convertion an <code>RuntimeException</code> exception is thrown with additional information regarding the error.</p>

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
