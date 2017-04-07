---
layout: default
title: Formatting and Validating Records For Insertion
---

# Insertion Filtering

~~~php
<?php

public Writer::addFormatter(callable $callable): Writer
public Writer::addValidator(callable $callable, string $validatorName): Writer
~~~

Sometimes you may want to format and/or validate your records prior to their insertion into your CSV document. the `Writer` class provides a formatter and a validator mechanism to ease these operations.

## Record Formatter

A formatter is a `callable` which accepts an `array` on input and returns a formatted array according to its inner rules.

~~~php
<?php

function(array $record): array
~~~

You can attach as many formatters as you want to the `Writer` class to manipulate your data prior to its insertion use the `Writer::addFormatter` method. The formatters follow the *First In First Out* rule when applied.

~~~php
<?php

public Writer::addFormatter(callable $callable): Writer
~~~

### Example

~~~php
<?php

use League\Csv\Writer;

$formatter = function ($row) {
    return array_map('strtoupper', $row);
};
$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->addFormatter($formatter);
$writer->insertOne(['john', 'doe', 'john.doe@example.com']);

$writer->__toString();
//will display something like JOHN,DOE,JOHN.DOE@EXAMPLE.COM
~~~

## Record Validator

A validator is a `callable` which takes an `array` as its sole argument and returns a boolean.

~~~php
<?php

function(array $record): bool
~~~

The validator **must** return `true` to validate the submitted record.

Any other expression, including thruthy ones like `yes`, `1`,... will make the `insertOne` method throw an `League\Csv\Exception\InsertionException`.

As with the formatter capabilities, you can attach as many validators as you want to your data prior to its insertion using the `Writer::addValidator` method. The record data is checked against your supplied validators **after it has been formatted**.

~~~php
<?php

public Writer::addValidator(callable $callable, string $validatorName): Writer
~~~

`Writer::addValidator` takes two parameters:

- A `callable` which takes an `array` representing a Csv record as its unique parameter;
- The validator name which is **required**. If another validator was already registered with the given name, it will be overriden.

On failure a [League\Csv\Exception\InsertionException](/9.0/connections/exceptions/#runtime-exceptions) exception is thrown by the `Writer` object.

### Example

~~~php
<?php

use League\Csv\Writer;
use League\Csv\Exception\InsertionException;

$writer->addValidator(function (array $row) {
    return 10 == count($row);
}, 'row_must_contain_10_cells');
try {
    $writer->insertOne(['john', 'doe', 'john.doe@example.com']);
} catch (InsertionException $e) {
    echo $e->getName(); //display 'row_must_contain_10_cells'
    $e->getData();//will return the invalid data ['john', 'doe', 'john.doe@example.com']
}
~~~

## Bundled formatter

### Charset Converter

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

If your `Writer` object supports PHP stream filters then it's recommended to use the library [stream filtering mechanism](/9.0/connections/filters/) instead.

~~~php
<?php

use League\Csv\CharsetConverter;
use League\Csv\Writer;

CharsetConverter::registerStreamFilter();

$filtername = CharsetConverter::getFiltername('utf-8', 'iso-8859-15');
$writer = Writer::createFromPath('/path/to/your/csv/file.csv')
    ->addStreamFilter($filtername)
;
$writer->insertOne(["foo", "bébé", "jouet"]);
//all 'utf-8' caracters are now automatically encoded into 'iso-8859-15' charset
~~~

## Bundled validator

### Records columns consistency check

~~~php
<?php

public ColumnConsistency::__construct(int $column_count = -1): void
public ColumnConsistency::getColumnCount(void): int
~~~

The `League\Csv\ColumnConsistency` class validates the inserted record column count consistency.

This class constructor accepts a single argument `$column_count` which sets the column count value and validate each record length against the given value. If the value differs an `InsertionException` will be thrown.  
if `$column_count` equals `-1`, the object will lazy set the column count value according to the next inserted record and therefore will also validate it. On the next insert, if the given value differs a `InsertionException` exception is triggered.
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
$writer->insertOne(["foo", "bar"]); //will trigger a InsertionException exception
~~~

<p class="message-info">The default column count is set to <code>-1</code>.</p>