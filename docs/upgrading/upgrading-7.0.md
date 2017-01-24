---
layout: default
title: Upgrading from 6.x to 7.x
permalink: upgrading/7.0/
---

# Upgrading from 6.x to 7.x

## Installation

If you are using composer then you should update the require section of your `composer.json` file.

~~~
$ composer require league/csv:~7.0
~~~

This will edit (or create) your `composer.json` file.

## Added features

To improving writing speed you can now control data formatting and validation insertion prior to CSV addition:

Please [refer to the documentation](/inserting/) for more information.

## Improved features

### newline

The newline feature introduced during the 6.X series is completed by

- adding the setter and getter methods to the `Reader` class.
- adding the `$newline` character as a second argument of the `createFromString` named constructor. When set, the method internally use the `setNewline` method to make sure the property is correctly set for future use.

### newReader and newWriter

All the CSV properties are now copied to the new instance when using both methods.

### BOM

When using the `__toString` or `output` methods the input BOM if it exists is stripped from the output.

## Backward Incompatible Changes

### PHP ini settings

**If you are on a Mac OS X Server**, add the following lines before using the library to help [PHP detect line ending in Mac OS X](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

~~~php
<?php

if (! ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}

//the rest of the code continue here...
~~~

### Null Handling

The null handling has been removed from the `Writer` class. If your code relied on it you can use the new validation and formatting capabilities of the `Writer` class and :

- the `League\Csv\Plugin\SkipNullValuesFormatter` class to skip cell using founded `null` values
- the `League\Csv\Plugin\ForbiddenNullValuesValidator` class to validate the absence or `null` values

By default `null` value cells are converted to empty string so the old behavior is preserved.

#### Example 1 : Null value validation

**Old code:**

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$writer->insertOne(["foo", null, "bar"]); //will throw an RuntimeException
~~~

**New code:**

~~~php
<?php

use League\Csv\Writer;
use League\Csv\Plugin\ForbiddenNullValuesValidator;

$validator = new ForbiddenNullValuesValidator();
$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$writer->addValidator($validator, 'null_as_exception');
$writer->insertOne(["foo", null, "bar"]); //will throw an League\Csv\Exception\InvalidRowException
~~~

#### Example 2 : Null value formatting

**Old code:**

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$writer->setNullHandlingMode(Writer::NULL_AS_SKIP_CELL);
$writer->insertOne(["foo", null, "bar"]);
//the actual inserted row will be ["foo", "bar"]
~~~

**New code:**

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

### Row consistency check

Directly checking row consistency has been removed from the `Writer` class. If your code relied on it you can use the new validation and formatting capabilities of the `Writer` class and:

- the `League\Csv\Plugin\ColumnConsistencyValidator` class.

**Old code:**

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$writer->setNullHandlingMode(Writer::NULL_AS_EMPTY);
$writer->autodetectColumnsCount();
$writer->getColumnsCount(); //returns -1

$writer->insertOne(["foo", null, "bar"]);
$nb_column_count = $writer->getColumnsCount(); //returns 3
~~~

**New code:**

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

### CSV conversion methods

`jsonSerialize`, `toXML` and `toHTML` methods returns data is affected by the `Reader` query options methods. You can directly narrow the CSV conversion into a json string, a `DOMDocument` object or a HTML table if you filter your data prior to using the conversion method.

As with any other `Reader` extract method, the query options are resetted after a call to the above methods.

Because prior to version 7.0 the conversion methods were not affected, you may have to update your code accordingly.

**Old behavior:**

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$reader->setOffset(1);
$reader->setLimit(5);
$reader->toHTML(); //would convert the full CSV
~~~

**New behavior:**

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$reader->setOffset(1);
$reader->setLimit(5);
$reader->toHTML(); //will only convert the 5 specified rows
~~~

Of course, since the query options methods do not exist on the `Writer` class. The changed behavior does not affect the latter class.

### CSV Instantiation

You can no longer use `Reader` and `Writer` default constructors. You are required to use the named constructors. This is done in order to clarify `$open_mode` argument usage on instantiation. See below for a concrete example.

**Removed behavior:**

~~~php
<?php

use League\Csv\Writer;

$file = '/path/to/my/file.csv';
$fileObject = new SplFileObject($file, 'r+');

$sol1 = new Writer($file, 'w');
$sol2 = new Writer($fileObject, 'w');
~~~

- `$sol1` open mode will be `w` and new `SplFileObject` object is created internally
- `$sol2` open mode will be `r+` and `$fileObject` is directly used. The provided `$open_mode` argument has no effect.

**Supported behavior**

~~~php
<?php

use League\Csv\Writer;

$file = '/path/to/my/file.csv';
$fileObject = new SplFileObject($file, 'r+');

$sol1 = Writer::createFromPath($file, 'w');
$sol2 = Writer::createFromFileObject($fileObject);
$sol3 = Writer::createFromPath($fileObject, 'w');
~~~

- `$sol1` open mode will be `w` and new `SplFileObject` object is created internally;
- `$sol2` open mode will be `r+` and `$fileObject` is directly used;
- `$sol3` was not possible using default constructor and is equivalent to `$sol1`;

### CSV properties

- The new default `SplFileObject` flags used are `SplFileObject::READ_CSV` and `SplFileObject::DROP_NEW_LINE`. The `SplFileObject::READ_CSV` is the only flag that can not be overidden by the developer to ensure consistency in methods usage.
- CSV properties setter methods no longer provides default values. When used, you are require to provide a value to the method.

### Detecting CSV Delimiters

Starting with version 7.0, each found delimiter index represents the character occurences in the specify CSV data.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$delimiters_list = $reader->detectDelimiterList(10, [' ', '|']);
foreach ($delimiters_list as $occurences => $delimiter) {
    echo "$delimiter appeared $occurences times in 10 CSV row", PHP_EOL;
}
//$occurences can not be less than 1
//since it would mean that the delimiter is not used
//if you are only interested in getting
// the most use delimiter you can still do as follow
if (count($delimiters_list)) {
    $delimiter = array_shift($delimiters);
}
~~~

### Reader::fetchColumn

Prior to version 7.0 when, if the column did not exist in the csv data the method returned an array full of null values.

**Old behavior:**

~~~php
<?php

use League\Csv\Reader;

//this CSV contains only 2 column
$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$arr = $reader->fetchColumn(3);
//$arr is a array containing only null values;
~~~

**New behavior:**

~~~php
<?php

use League\Csv\Reader;

//this CSV contains only 2 column
$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$arr = $reader->fetchColumn(3);
//$arr is empty
~~~

Row with non existing values are skipped from the result set.