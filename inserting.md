---
layout: default
title: Inserting new data into a CSV
---

# Inserting Data

To create or update a CSV use the following `League\Csv\Writer` methods.

<p class="message-warning">The class has been rewrote for scalability. Some previous supported features have been removed. Please refer to the <a href="/upgrading/7.0/">upgrade section</a> to securely migrate from 6.0 to 7.0 .</p>

<p class="message-info">When creating a file using the library, first insert all the data that need to be inserted before starting manipulating the CSV. If you manipulate your data before insertion, you may change the file cursor position and get unexpected results.</p>

## Adding data to a CSV

### insertOne($row)

`insertOne` inserts a single row. This method can take an `array`, a `string` or
an `object` implementing the `__toString` method.

~~~php
class ToStringEnabledClass
{
    private $str;

    public function __construct($str)
    {
        $this->str = $str;
    }

    public function __toString()
    {
        return $this->str;
    }
}

$writer->insertOne(['john', 'doe', 'john.doe@example.com']);
$writer->insertOne("'john','doe','john.doe@example.com'");
$writer->insertOne(new ToStringEnabledClass("john,doe,john.doe@example.com"))
~~~

### insertAll($rows)

`insertAll` inserts multiple rows. This method can take an `array` or a
`Traversable` object to add several rows to the CSV data.

~~~php
$rows = [
    [1, 2, 3],
    ['foo', 'bar', 'baz'],
    "'john','doe','john.doe@example.com'",
    new ToStringEnabledClass("john,doe,john.doe@example.com")
];

$writer->insertAll($rows); //using an array

$writer->insertAll(new ArrayIterator($rows)); //using a Traversable object
~~~

## Handling newline

Because the php `fputcsv` implementation has a hardcoded `\n`, we need to be able to replace the last `LF` code with one supplied by the developper for more interoperability between CSV packages on different platforms. The newline sequence will be appended to each CSV newly inserted line.

At any given time you can get and modify the `$newline` property using the `getNewline` and `setNewline` methods described in <a href="/properties/">CSV properties documentation page</a>.

~~~php
$writer = Writer::createFromFileObject(new SplFileObject());
$newline = $writer->getNewline(); // equals "\n";
$writer->setNewline("\r\n");
$newline = $writer->getNewline(); // equals "\r\n";
$writer->insertOne(["one", "two"]);
echo $writer; // displays "one,two\r\n";
~~~

<p class="message-info">Please refer to <a href="/bom/">the BOM character dedicated documentation page</a> for more informations on how the library manage the BOM character.</p>

## Formatting before insertion

<p class="message-notice">New to version 7.0</p>

A formatter is a callable which accepts an `array`, usually the supplied array to be inserted, as its single parameter and return a formatted `array` ready to be inserted.

You can attach as many formatters as you want to the `Writer` class to manipulate your data prior to its insertion. The formatters follow the *First In First Out* rule when inserted, deleted and/or applied.

 The formatter API comes with the following methods:

### addFormatter(callable $callable)

Adds a formatter to the formatter collection;

### removeFormatter(callable $callable)

Removes an already registered formatter. If the formatter was registered multiple times, you will have to call `removeFormatter` as often as the formatter was registered. **The first registered copy will be the first to be removed.**

### hasFormatter(callable $callable)

Checks if the formatter is already registered

### clearFormatters()

removes all registered formatters.

~~~php
<?php

use League\Csv\Writer;

$writer->addFormatter(function ($row) {
    return array_map('strtoupper', $row);
});
$writer->insertOne(['john', 'doe', 'john.doe@example.com']);

$writer->__toString();
//will display something like JOHN,DOE,JOHN.DOE@EXAMPLE.COM
~~~

<p class="message-info">Some data formatting can also be achieved using the <a href="/fitering/">Stream Filters capabilities</a>.</p>

If you are relying on the **removed** null handling feature the library comes bundle with the following class to help you migrate to the new version.

- `League\Csv\Exporter\Formatters\NullFormatter` to format `null` values

Please refers to the <a href="/upgrading/7.0/">migration guide</a> for more information.

## Validating before insertion

<p class="message-notice">New to version 7.0</p>

A validator id a `callable` which takes a `array` as its sole argument and return `true` if the supplied row is valid. The callable **must** return `true` to validate the submitted row. Anything else will make the `insertOne` method throw an `InvalidArgumentException`.

As with the new formatter capabilities, you can attach as many validators as you want to your data prior to its insertion. The row data is checked against the validator **after being formatted**.

To manage the validators you have to use the following public API:

### addValidator(callable $callable, $validator_name)

Adds a validator each time it is called. The method takes two parameters:
- The callable validator which takes the current row as its unique parameter;
- The validator name **required**. If another validator was already registered with the given name, it will be overriden.

### removeValidator($validator_name)

Removes an already registered validator by using the validator registrated name

### hasValidator($validator_name)

Checks if the validator is already registered

### clearValidators()

Removes all registered formatter

### getLastValidatorErrorName() and getLastValidatorErrorData()

If the validation failed a `League\Csv\Exception\ValidationException` is thrown by the `Writer` object. In addition, you will have access to the name of the validator which rejected the data and the input data submitted to the validator using two additional methods:

~~~php
<?php

use League\Csv\Writer;
use League\Csv\Exception\ValidationException;

$writer->addValidator(function (array $row) {
    return 10 == count($row);
}, 'row_must_contain_10_cells');
try {
    $writer->insertOne(['john', 'doe', 'john.doe@example.com']);
} catch (ValidationException $e) {
    echo $writer->getLastValidatorErrorName(); //display 'row_must_contain_10_cells'
    $writer->getLastValidatorErrorData();
    //will return the submitted data ['john', 'doe', 'john.doe@example.com']
}
~~~

If you are relying on the **removed features** null handling and the column consistency, the library comes bundle with the following classes to help you migrate to the new version.

- `League\Csv\Exporter\Validators\NullValidator` to validate the absence of the `null` value;
- `League\Csv\Exporter\Validators\ColumnConsistency` to validate the CSV column consistency;

Please refers to the <a href="/upgrading/7.0/">migration guide</a> for more information.
