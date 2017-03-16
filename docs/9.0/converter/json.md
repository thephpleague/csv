---
layout: default
title: Records conversion in popular formats
---

# JSON conversion

This converter convert a CSV records collection into a JSON string using PHP's `json_encode` function.

## Settings

~~~php
<?php
public JsonConverter::preserveRecordOffset(bool $preserve_offset): self
public JsonConverter::options(int options = 0, int $depth = 512): self
~~~


- `JsonConverter::preserveRecordOffset` tells whether the converter should keep or not the CSV record offset in the JSON output. By default, the record offset are not preserved.

- `JsonConverter::options` sets PHP's `json_encode` optional arguments.


## Convertion

~~~php
<?php
public JsonConverter::convert(iterable $records): string
~~~

If a error occurs during the convertion an `RuntimeException` exception is thrown with additional information regarding the error.

~~~php
<?php

use League\Csv\JsonConverter;

$csv = new SplFileObject('/path/to/french.csv', 'r');
$csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

$json = (new JsonConverter())
    ->options(JSON_PRETTY_PRINT)
    ->convert($csv)
;
//may trigger an error if for instance the
//CSV collection is not in a UTF-8 encoding
~~~
