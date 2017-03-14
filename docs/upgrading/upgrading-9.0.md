---
layout: default
title: Upgrading from 8.x to 9.x
permalink: upgrading/9.0/
---

<p class="message-notice">This is the documentation for the upcoming <code>version 9.0</code>. This is a work in progress.</p>

# Upgrading from 8.x to 9.x

`League\Csv 9.0` is a new major version that comes with backward compatibility breaks.

This guide will help you migrate from a 8.x version to 9.0. It will only explain backward compatibility breaks, it will not present the new features ([read the documentation for that](/9.0/)).

## Installation

If you are using composer then you should update the require section of your `composer.json` file.

~~~
composer require league/uri:^9.0
~~~

This will edit (or create) your `composer.json` file.

## PHP version requirement

`League\Csv 9.0` requires a PHP version greater or equal than 7.0.0 (was previously 5.5.0).

<p class="message-warning">The package does not work on <code>HHVM</code></p>

## The Writer class

### Stricter argument type

The `Writer::insertOne` and `Writer::insertAll` methods no longer accept string as possible CSV records

Before:

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplTempFileObject());
$str = 'john,doe,john.doe@example.com';
$writer->insertOne($str);
$writer->insertAll([$str]);

~~~

After:

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplTempFileObject());
$str = 'john,doe,john.doe@example.com';
$record = str_getcsv($str, ',', '"', '\\');
$writer->insertOne($record);
$writer->insertAll(str_getcsv([$record]);
~~~

### Reduced method chaining

The `Writer::insertOne` and `Writer::insertAll` methods are no longer chainable.

Before:

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplTempFileObject());
$record = ['john', 'doe', 'john.doe@example.com'];
$writer
	->insertOne($record)
	->insertAll([$record])
    ->insertOne($record)
;
~~~

After:

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplTempFileObject());
$record = ['john','doe','john.doe@example.com'];
$writer->insertOne($record);
$writer->insertAll([$record]);
$writer->insertOne($record);
~~~

### Removed methods

- `Writer::removeFormatter`
- `Writer::hasFormatter`
- `Writer::clearFormatters`
- `Writer::removeValidator`
- `Writer::hasValidator`
- `Writer::clearValidators`

Validators and Formatters can only be removed on object destruction.

You can no longer iterate over a `Writer` class, use the `Reader` class instead.

## The Reader class

### BOM Handling

The BOM sequence is automatically removed from the records. The `stripBOM` method is removed.

Before:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
foreach ($reader->stripBom(true) as $record) {
    // the BOM sequence is removed
}
~~~

After:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
foreach ($reader as $record) {
    // the BOM sequence is automatically removed if it exists
}
~~~

### Selecting records methods

All extracting and methods are no longer attached to the `Reader` class instead they are expose using the `RecordSet` object.

Before:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = $reader->fetchAll();
~~~

After:

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = (new Statement())->process($reader);
$records->fetchAll();
~~~

### Reader::fetch is removed

The `Reader::fetch` is removed as the `RecordSet` class implements the `IteratorAggregate` interface.

Before:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = $reader
    ->setOffset(3)
    ->setLimit(2)
    ->fetch()
;

foreach ($records as $record) {
    // do something here
}
~~~

After:

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$stmt = (new Statement())
    ->offset(3)
    ->limit(2)
;
$records = $stmt->process($reader);
foreach ($records as $record) {
    // do something here
}
~~~

### Reader::fetchPairsWithoutDuplicates is removed

The `Reader::fetchPairsWithoutDuplicates` is removed as the `RecordSet` class already exposes the `fetchPairs` method.

Before:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
$pairs_without_duplicates = $reader
    ->setOffset(3)
    ->setLimit(2)
    ->fetchfetchPairsWithoutDuplicates()
;

foreach ($pairs_without_duplicates as $key => $value) {
    // do something here
}
~~~

After:

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$stmt = (new Statement())
    ->offset(3)
    ->limit(2)
;
$records = $stmt->process($reader);
$pairs_without_duplicates = iterator_to_array($records->fetchPairs(), true);
foreach ($pairs_without_duplicates as $record) {
    // do something here
}
~~~

### Reader::fetchAssoc is removed

The `Reader::fetchAssoc` is removed instead you are required to specify the header offset.

Before:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
foreach ($reader->fetchAssoc() as $records) {
    //The CSV first row is used as the CSV header
    //and as the index of each found record
    //the CSV header offset is removed from iteration
}
~~~

After:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
$reader->setHeaderOffset(0);
foreach ($reader as $records) {
    //The CSV first row is used as the CSV header
    //and as the index of each found record
    //the CSV header offset is removed from iteration
}
~~~

<p class="message-notice">Since the header offset is a property of the connection any subsequent use/call of the <code>Reader</code> will keep this setting unless you explicitly reset it.</p>

If you want to map the headers for a CSV document which don't have one or add more constraints
you need to use the `Statement` object.

Before:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
$records = $reader->fetchAssoc(['firstname', 'lastname', 'email']);
~~~

After:

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv');
$stmt = (new Statement())->columns(['firstname', 'lastname', 'email']);
$records = $stmt->process($reader)->fetchAll();
~~~

## Miscellanous

### Stream Filtering

To detect if a PHP stream filters are supported you neet to call `AbstractCsv::supportsStreamFilter`

Before:

~~~php
<?php

use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->isActiveStreamFilter(); //true
~~~

After:

~~~php
<?php

use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->supportsStreamFilter(); //true
~~~

The filtering mode is fixed and can not be changed:

- a `Writer` class will only accept stream filters in writing mode only
- a `Reader` class will only accept stream filters in reading mode only

Therefore:

- `AbstractCsv::setStreamFilterMode`
- `AbstractCsv::getStreamFilterMode`

are removed.

To add a stream filter you will only need the `AbstractCsv::addStreamFilter` method.

Before:

~~~php
<?php

use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->appendStreamFilter('string.toupper');
$csv->prependStreamFilter('string.rot13');
~~~

After:

~~~php
<?php

use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->addStreamFilter('string.rot13');
$csv->addStreamFilter('string.toupper');
//the insertion order has changed
~~~

PHP Stream filters are removed once the CSV object is freed.

Before:

~~~php
<?php

use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->appendStreamFilter('string.toupper');
$csv->prependStreamFilter('string.rot13');
$csv->removeStreamFilter('string.rot13');
$csv->clearStreamFilters();
~~~

After:

~~~php
<?php

use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->addStreamFilter('string.rot13');
$csv->addStreamFilter('string.toupper');
$csv = null;
~~~

### Conversion methods

All convertion methods are no longer attached to the `Reader` or the `Writer` classes you need a `RecordSet` object to have access to them. The following methods are removed

- `AbstractCsv::jsonSerialize`
- `AbstractCsv::toHTML`
- `AbstractCsv::toXML`


Before:

~~~php
<?php

use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$records = $csv->toXML();
~~~

After:

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$csv = Reader::createFromPath('/path/to/file.csv');
$records = (new Statement())->process($csv)->toXML();
~~~

### Switching between connections

- `AbstractCsv::newReader`
- `AbstractCsv::newWriter`

You can no longer switch between connection. You are require to explicitly load a new `Reader` and/or `Writer` object.