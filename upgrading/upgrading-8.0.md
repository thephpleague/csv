---
layout: default
title: Upgrading from 7.x to 8.x
permalink: upgrading/8.0/
---

# Upgrading from 7.x to 8.x

## Installation

If you are using composer then you should update the require section of your `composer.json` file.

~~~
$ composer require league/csv:^8.0
~~~

This will edit (or create) your `composer.json` file.

## Improved features

To improving reading capabilities with huges CSV files you can now control the return type for some of the `Reader` extracting methods:

Please [refer to the documentation](/reading/) for more information.

## Backward Incompatible Changes

### PHP required version

`League\Csv` 8.0.0 dropped the support for PHP5.4.

### remove optional argument to createFromString

In version 8.0 the optional second argument from `createFromString` is removed. If your code relied on it you can use the following snippet:

**Old code:**

~~~php
use League\Csv\Writer;

$writer = Writer::createFromString($str, "\r\n");
$writer->insertOne(["foo", null, "bar"]);
~~~

**New code:**

~~~php
use League\Csv\Writer;

$writer = Writer::createFromString($str);
$writer->setNewline("\r\n")
$writer->insertOne(["foo", null, "bar"]);
~~~

### fetchAssoc callable argument

The optional callable argument from fetchAssoc now expects its first argument to be an array indexed by the submitted array keys. In all previous versions, The indexation was made after the callable had manipulated the CSV array.

**Old code:**

~~~php
use League\Csv\Reader;

$func = function (array $row) {
    $row[1] = strtoupper($row[1]);
    $row[2] = strtolower($row[2]);

    return $row;
};

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->fetchAssoc(['lastname', 'firstname'], $func);
~~~

**New code:**

~~~php
use League\Csv\Reader;

$func = function (array $row) {
    $row['lastname'] = strtoupper($row['lastname']);
    $row['firstname'] = strtolower($row['firstname']);

    return $row;
};

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->fetchAssoc(['lastname', 'firstname'], $func);
~~~

### fetchColumn callable argument

The optional callable argument from fetchColumn now expects its first argument to be a single value. In all previous versions, the callable first argument was an array.

**Old code:**

~~~php
use League\Csv\Reader;

$func = function (array $row) {
    $row[2] = strtoupper($row[2]);

    return $row;
};

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->fetchColum(2, $func);
~~~

**New code:**

~~~php
use League\Csv\Reader;

$func = function ($value) {
    return strtoupper($value);
};

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->fetchColum(2, $func);
~~~

## Deprecated methods in 7.0 series, removed in 8.0

- `detectDelimiterList`: deprecated since version 7.2 and replaced by `fetchDelimitersOccurrence`.
