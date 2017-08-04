---
layout: default
title: Bundled Writer helpers
---

# Bundled insertion helpers

## Column consistency validator

~~~php
<?php

class ColumnConsistency
{
    public function __construct(int $column_count = -1)
    public function __invoke(array $record): bool
    public function getColumnCount(): int
}
~~~

The `League\Csv\ColumnConsistency` class validates the inserted record column count consistency.

This class constructor accepts a single argument `$column_count` which sets the column count value and validate each record length against the given value. If the value differs an `CannotInsertRecord` will be thrown.

if `$column_count` equals `-1`, the object will lazy set the column count value according to the next inserted record and therefore will also validate it. On the next insert, if the given value differs a `CannotInsertRecord` exception is triggered.
At any given time you can retrieve the column count value using the `ColumnConsistency::getColumnCount` method.

~~~php
<?php

use League\Csv\Writer;
use League\Csv\ColumnConsistency;


$validator = new ColumnConsistency();
$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$writer->addValidator($validator, 'column_consistency');
$validator->getColumnCount(); //returns -1
$writer->insertOne(["foo", "bar", "baz"]);
$validator->getColumnCount(); //returns 3
$writer->insertOne(["foo", "bar"]); //will trigger a CannotInsertRecord exception
~~~

<p class="message-info">The default column count is set to <code>-1</code>.</p>

## Charset formatter

[League\Csv\CharsetConverter](/9.0/converter/charset/) will help you encode your records depending on your settings.

~~~php
<?php

use League\Csv\CharsetConverter;
use League\Csv\Writer;

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$formatter = (new CharsetConverter())
    ->inputEncoding('utf-8')
    ->outputEncoding('iso-8859-15')
;
$writer->addFormatter($formatter);
$writer->insertOne(["foo", "bébé", "jouet"]);
//all 'utf-8' caracters are now automatically encoded into 'iso-8859-15' charset
~~~

If your `Writer` object supports PHP stream filters then it's recommended to use the `CharsetConverter` object via the library [stream filtering mechanism](/9.0/connections/filters/) instead as shown below.

~~~php
<?php

use League\Csv\CharsetConverter;
use League\Csv\Writer;

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
CharsetConverter::addTo($writer, 'utf-8', 'iso-8859-15');

$writer->insertOne(["foo", "bébé", "jouet"]);
//all 'utf-8' caracters are now automatically encoded into 'iso-8859-15' charset
~~~