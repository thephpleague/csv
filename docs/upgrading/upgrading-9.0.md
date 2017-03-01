---
layout: default
title: Upgrading from 8.x to 9.x
permalink: upgrading/9.0/
---

 <p class="message-notice">This is the documentation for the upcoming <code>version 9.0</code>. This documentation is not complete and may be altered.</p>

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
$record = 'john,doe,john.doe@example.com';
$writer->insertOne($record);
$writer->insertAll([$record]);

~~~

After:

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplTempFileObject());
$record = 'john,doe,john.doe@example.com';
$writer->insertOne(str_fgetcsv($record));
$writer->insertAll([str_fgetcsv($record)]);
~~~

### Reduced method chaining

The `Writer::insertOne` method is no longer chainable.

Before:

~~~php
<?php

use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplTempFileObject());
$record = ['john','doe','john.doe@example.com'];
$writer
	->insertOne($record)
	->insertAll([$record])
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


All extracting and methods are no longer attached to the `Reader` class you need to call the `Reader::select` method to have access to them.

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

$reader = Reader::createFromPath('/path/to/file.csv');
$records = $reader->select()->fetchAll();
~~~

### Reader::fetch is removed

The `Reader::fetch` is removed instead you are required to use the `RecordSet` object.

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
$records = $reader->select($stmt);
foreach ($records as $record) {
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
$records = $reader->fetchAssoc(); //The CSV first row is used as the CSV header
~~~

After:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
$reader->setHeaderOffset(0);
$records = $reader->select()->fetchAll();
~~~

If you want to specify the headers for a Csv document which don't have one

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
$records = $reader->select($stmt)->fetchAll();
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

PHP Stream filters are removed once the `Writer` object is freed.

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

All converting methods are no longer attached to the `Reader` or the `Writer` classes you need a RecordSet` object to have access to them.

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

$csv = Reader::createFromPath('/path/to/file.csv');
$records = $csv->select()->toXML();
~~~

### Switching between connections

- `AbstractCsv::newReader`
- `AbstractCsv::newWriter`

You can no longer switch between connection. You are require to explicitly load a new `Reader` and/or `Writer` object.