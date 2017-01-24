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

## Added features

### Reader::fetchPairs and Reader::fetchPairsWithoutDuplicates

To complements the Reader extract methods the following methods are added:

- `Reader:fetchPairs`
- `Reader:fetchPairsWithoutDuplicates`

Please [refer to the documentation](/reading/) for more information.

## Backward Incompatible Changes

### PHP required version

`Csv` 8.0.0 is the first major version to remove support for `PHP 5.4`.

### Remove optional argument to createFromString

In version 8.0 the optional second argument from `createFromString` is removed. If your code relied on it you can use the following snippet:

**Old code:**

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromString($str, "\r\n");
$writer->insertOne(["foo", null, "bar"]);
~~~

**New code:**

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromString($str);
$writer->setNewline("\r\n")
$writer->insertOne(["foo", null, "bar"]);
~~~

### Remove SplFileObject flags usage

In version 8.0 you can no longer set `SplFileObject` flags the following methods are remove:

- `setFlags`
- `getFlags`

The `SplFileObject` flags are normalized to have a normalized CSV filtering independent of the underlying PHP engine use (`HHVM` or `Zend`).

**Old code:**

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setFlags(SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
$csv->fetchAssoc(); //empty lines where removed
~~~

**New code:**

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->fetchAssoc(); //empty lines are automatically removed
~~~

### fetchAssoc and fetchColumn return Iterator

`Reader::fetchAssoc` and `Reader::fetchColumn` no longer return an array but instead an `Iterator`.

**Old code:**

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$res = $csv->fetchAssoc(['lastname', 'firstname']);

echo $res[0]['lastname']; //would return the first row 'lastname' index
~~~

**New code:**

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$res = $csv->fetchAssoc(['lastname', 'firstname']);

echo iterator_to_array($res, false)[0]['lastname'];
~~~

### fetchAssoc callable argument

The optional callable argument from `Reader::fetchAssoc` now expects its first argument to be an `array` indexed by the submitted array keys. In all previous versions, the indexation was made after the callable had manipulated the CSV row.

**Old code:**

~~~php
<?php

use League\Csv\Reader;

$func = function (array $row) {
    $row[1] = strtoupper($row[1]);
    $row[2] = strtolower($row[2]);

    return $row;
};

$csv = Reader::createFromPath('/path/to/file.csv');
$res = $csv->fetchAssoc(['lastname', 'firstname'], $func);
~~~

**New code:**

~~~php
<?php

use League\Csv\Reader;

$func = function (array $row) {
    $row['lastname'] = strtoupper($row['lastname']);
    $row['firstname'] = strtolower($row['firstname']);

    return $row;
};

$csv = Reader::createFromPath('/path/to/file.csv');
$res = $csv->fetchAssoc(['lastname', 'firstname'], $func);
~~~

### fetchColumn callable argument

The optional callable argument from `Reader::fetchColumn` now expects its first argument to be the selected column value. In all previous versions, the callable first argument was an array.

**Old code:**

~~~php
<?php

use League\Csv\Reader;

$func = function (array $row) {
    $row[2] = strtoupper($row[2]);

    return $row;
};

$csv = Reader::createFromPath('/path/to/file.csv');
$res = $csv->fetchColumn(2, $func);
~~~

**New code:**

~~~php
<?php

use League\Csv\Reader;

$func = function ($value) {
    return strtoupper($value);
};

$csv = Reader::createFromPath('/path/to/file.csv');
$res = $csv->fetchColum(2, $func);
~~~

## Deprecated methods in 7.0 series, removed in 8.0

- `Controls::detectDelimiterList` replaced by `Controls::fetchDelimitersOccurence`
- `Reader::query` replaced by `Reader::fetch`

## Removed methods in 8.0.0

- `Controls::setFlags`
- `Controls::getFlags`
- `QueryFilter::hasFilter`
- `QueryFilter::removeFilter`
- `QueryFilter::clearFilter`
- `QueryFilter::hasSortBy`
- `QueryFilter::removeSortBy`
- `QueryFilter::clearSortBy`