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

On failure a `League\Csv\Exception\InsertionException`  exception is thrown by the `Writer` object.
This exception extends PHP's `RuntimeException` by adding two public getter methods:

~~~php
<?php

public InsertionException::getName(void): string
public InsertionException::getData(void): array
~~~

- `InsertionException::getName`: returns the name of the failed validator
- `InsertionException::getData`: returns the invalid data submitted to the validator

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

## Bundled formatters and validators

### Null value validator

The `League\Csv\Plugin\ForbiddenNullValuesValidator` class validates the absence of `null` values

~~~php
<?php

use League\Csv\Writer;
use League\Csv\Plugin\ForbiddenNullValuesValidator;

$validator = new ForbiddenNullValuesValidator();
$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$writer->addValidator($validator, 'null_as_exception');
$writer->insertOne(["foo", null, "bar"]); //will throw an League\Csv\Exception\InsertionException
~~~

### Null value formatting

The `League\Csv\Plugin\SkipNullValuesFormatter` class skips cell using founded `null` values

~~~php
<?php

use League\Csv\Writer;
use League\Csv\Plugin\SkipNullValuesFormatter;

$formatter = new SkipNullValuesFormatter();

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$writer->addFormatter($formatter);
$writer->insertOne(["foo", null, "bar"]);
//the actual inserted row will be ["foo", "bar"]
~~~

### Records consistency check

~~~php
<?php

public ColumnConsistencyValidator::setColumnsCount(int $count): void
public ColumnConsistencyValidator::getColumnsCount(void): int
public ColumnConsistencyValidator::autodetectColumnsCount(void): void
~~~

The `League\Csv\Plugin\ColumnConsistencyValidator` class validates the inserted record column count consistency.

~~~php
<?php

use League\Csv\Writer;
use League\Csv\Plugin\ColumnConsistencyValidator;

$validator = new ColumnConsistencyValidator();
$validator->autodetectColumnsCount();
$validator->getColumnsCount(); //returns -1

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$writer->addValidator($validator, 'column_consistency');

$writer->insertOne(["foo", null, "bar"]);
$nb_column_count = $validator->getColumnsCount(); //returns 3
~~~

### Records encoder

~~~php
<?php

public Encoder::inputEncoding(string $input_encoding): self
public Encoder::outputEncoding(string $output_encoding): self
public Encoder::__invoke(array $record): array
~~~

This formatter will help you encode your record depending on your settings.

~~~php
<?php

use League\Csv\Encoder;
use League\Csv\Writer;

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$encoder = (new Encoder())
    ->inputEncoding('utf-8')
    ->outputEncoding('iso-8859-15')
;
$writer->addFormatter($encoder);
$writer->insertOne(["foo", "bébé", "jouet"]);
//all 'utf-8' caracters are now automatically encoded into 'iso-8859-15' charset
~~~

<p class="message-info"><strong>Tips:</strong> If your <code>Writer</code> object supports PHP stream filters then it's recommended to use the library <a href="/9.0/connections/filters/">stream filtering mechanism</a> instead.</p>

<p class="message-info"><strong>Tips:</strong> The <code>Encoder</code> object can also be used <a href="/9.0/converter/">to convert CSV records</a> into other transport/exchange format.</p>