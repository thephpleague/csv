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

You can no longer:

- iterate over records using the `Writer` class.
- convert the CSV document into anything else.

To do so you will have to use the `Reader` class.

## The Reader class

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

All extracting and methods are no longer attached to the `Reader` or the `Writer` classes you need to call the `Reader::select` method to have access to them.

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