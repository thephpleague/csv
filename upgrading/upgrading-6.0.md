---
layout: default
title: Upgrading from 5.x to 6.x
permalink: upgrading/6.0/
---

# Upgrading from 5.x to 6.x

## Installation

If you are using composer then you should update the require section of your `composer.json` file.

~~~
composer require league/csv:~6.0
~~~

This will edit (or create) your `composer.json` file.

## New Features

### Stream Filter API

The Stream Filter API is introduced. Please [refer to the documentation](/filtering/) for more information

## Added Methods

### Named Constructors

The new preferred way to instantiate a CSV object is to use the [named constructors](/overview/#instantiation):

Two new named constructors are added to complement the already present `createFromString` method.

* `createFromPath`;
* `createFromFileObject`;

You can still use the class constructor for backward compatibility.

## Backward Incompatible Changes

### Detecting CSV Delimiters

The `detectDelimiter` method is removed and replaced by the `detectDelimiterList` method.

The difference between both methods is that the latter always return an `array` as the former was throwing `RuntimeException` when multiple delimiters where found (*ie.*: the CSV was inconsistent)

Old code:

~~~php
<?php

use League\Csv\Reader;

$reader = new Reader('/path/to/your/csv/file.csv');

try {
    $delimiter = $reader->detectDelimiter(10, [' ', '|']);
    if (is_null($delimiter)) {
        //no delimiter found
    }
} catch(RuntimeException $e) {
    //inconsistent CSV the found delimiters were given in $e->getMessage();
}

~~~

New code:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$delimiters_list = $reader->detectDelimiterList(10, [' ', '|']);
if (! $delimiters_list) {
    //no delimiter found
} elseif (1 == count($delimiters_list)) {
    $delimiter = array_shift($delimiters); // the found delimiter
} else {
    //inconsistent CSV
    var_dump($delimiters_list); // all the delimiters found
}

~~~

### Transcoding Charset Property

`setEncoding`/`getEnconding`: the `$encondingFrom` property setter and getter are renamed `setEncodingFrom`/`getEncondingFrom` to avoid any ambiguity.

**The library always assume that the output is in `UTF-8`** so when transcoding your CSV you should always transcode from the `$encondingFrom` charset into an UTF-8 compatible charset.

You need to upgrade your code accordingly.

Old code:

~~~php
<?php

use League\Csv\Reader;

$reader = new Reader('/path/to/your/csv/file.csv');
$reader->setEncoding('SJIS');
$charset = $reader->getEncoding(); //returns 'SJIS'
$reader->output();

~~~

New code:

~~~php
<?php

use League\Csv\Reader;

$reader = new Reader('/path/to/your/csv/file.csv');
$reader->setEncodingFrom('SJIS');
$charset = $reader->getEncodingFrom(); //returns 'SJIS'
$reader->output();

~~~

### Creating New Instances From Existing CSV Objects

`League\Csv\Writer::getReader` and `League\Csv\Reader::getWriter` are removed. 

The new methods `newWriter` and `newReader` are available on **both** classes. This means you can create a CSV reader and/or a CSV writer object from any given object.

* `newWriter` behaves exactly like `getWriter`;
* `newReader` behaves exactly like `getReader`;

Old code:

~~~php
<?php

use League\Csv\Reader;

$reader = new Reader('/path/to/your/csv/file.csv');
$writer = $reader->getWriter('a+');

$another_reader = $writer->getReader();
~~~

New code:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$writer = $reader->newWriter('a+');

$another_writer = $writer->newWriter('rb+');
$another_reader1 = $writer->newReader();
$another_reader2 = $reader->newReader();
~~~

## Already deprecated methods in 5.0 series, removed in 6.0

- `setSortBy`: deprecated since version 5.2 and replaced by `addSortBy`.
- `setFilter`: deprecated since version 5.1 and replaced by `addFilter`.
