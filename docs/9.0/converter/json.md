---
layout: default
title: Converting a CSV into a JSON string
---

# JSON conversion

`JsonConverter` converts a CSV records collection into a JSON string using PHP's `json_encode` function.

## Settings

### JsonConverter::options

~~~php
<?php

public JsonConverter::options(int $options): self
~~~

This method sets PHP's `json_encode` optional flags.

## Conversion

~~~php
<?php
public JsonConverter::convert(iterable $records): string
~~~

The `JsonConverter::convert` accepts an `iterable` which represents the records collection and returns a string.

<p class="message-warning">If a error occurs during the convertion an <code>RuntimeException</code> exception is thrown with additional information regarding the error.</p>

<p class="message-warning"><strong>Warning:</strong> To convert an iterator, <code>iterator_to_array</code> is used, which could lead to a performance penalty if you convert a large <code>Iterator</code>.</p>

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

<p class="message-info"><strong>Tip:</strong> if needed you can use the <a href="/9.0/converter/charset/">CharsetConverter</a> object to correctly encode your CSV records before conversion.</p>