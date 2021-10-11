---
layout: default
title: Upgrading from 8.x to 9.x
redirect_from: /upgrading/9.0/
---

# Upgrading from 8.x to 9.x

`League\Csv 9.0` is a new major version that comes with backward compatibility breaks.

This guide will help you migrate from a 8.x version to 9.0. It will only explain backward compatibility breaks, it will not present the new features ([read the documentation for that](/9.0/)).

## Installation

If you are using composer then you should update the require section of your `composer.json` file.

~~~
composer require league/csv:^9.0
~~~

This will edit (or create) your `composer.json` file.

## PHP version requirement

`League\Csv 9.0` requires a PHP version greater or equal than 7.0.0 (was previously 5.5.0).

<p class="message-warning">The library is not tested on <code>HHVM</code></p>

## The Writer class

### Stricter argument type

The `Writer::insertOne` and `Writer::insertAll` methods no longer accept string as possible CSV records

Before:

~~~php
use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplTempFileObject());
$str = 'john,doe,john.doe@example.com';
$writer->insertOne($str);
$writer->insertAll([$str]);

~~~

After:

~~~php
use League\Csv\Reader;
use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplTempFileObject());
$reader = Reader::createFromString('john,doe,john.doe@example.com');
$writer->insertOne($reader->fetchOne());
$writer->insertAll($reader);
~~~

### Reduced method chaining

The `Writer::insertOne` and `Writer::insertAll` methods are no longer chainable.

Before:

~~~php
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
use League\Csv\Writer;

$writer = Writer::createFromFileObject(new SplTempFileObject());
$record = ['john', 'doe', 'john.doe@example.com'];
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

### Removed methods

- `Reader::each`
- `Reader::fetch`
- `Reader::fetchAll`
- `Reader::fetchAssoc`
- `Reader::fetchDelimitersOccurrence`
- `Reader::fetchPairsWithoutDuplicates`

#### Reader::fetchAssoc

The `Reader::fetchAssoc` features are now accessible using the new `Reader::getRecords`.

You are required to specify the CSV header using `Reader::setHeaderOffset`.

Before:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
foreach ($reader->fetchAssoc() as $records) {
    //The CSV first row is implicitly used as the CSV header
    //and as the index of each found record
    //the CSV header offset is removed from iteration
}
~~~

After:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$reader->setHeaderOffset(0); //explicitly sets the CSV document header record
foreach ($reader->getRecords() as $records) {
    //The CSV first row is used as the CSV header
    //and as the index of each found record
    //the CSV header offset is removed from iteration
}
~~~

or you can use the optional `$header` argument from the `Reader::getRecords` method.

Before:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$records = $reader->fetchAssoc(['firstname', 'lastname', 'email']);
~~~

After:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$records = $reader->getRecords(['firstname', 'lastname', 'email']);
~~~

Last but not least if you are using query filters then use the optional `$header` argument from the `Statement::process` method.

Before:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$records = $reader
    ->limit(5)
    ->offset(3)
    ->fetchAssoc(['firstname', 'lastname', 'email'])
;
~~~

After:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$stmt = (new Statement())
    ->limit(5)
    ->offset(3)
;

$records = $stmt->process($reader, ['firstname', 'lastname', 'email']);
~~~

#### Reader::fetchAll, Reader::fetch, Reader::each

Theses methods are removed because the `Reader` and the `ResultSet` implements the `IteratorAggregate` interface

Before:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
foreach ($reader->fetchAll() as $key => $value) {
    // do something here
}
~~~

After:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
foreach ($reader as $record) {
    // do something here
}
~~~

Before:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');

$func = function (array $record) {
    return array_map('strtoupper', $record);
};

$records = $reader
    ->setOffset(3)
    ->setLimit(2)
    ->fetch($func)
;

foreach ($records as $record) {
    // do something here
}
~~~

After:

~~~php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$stmt = (new Statement())
    ->offset(3)
    ->limit(2)
;
$records = $stmt->process($reader);
foreach ($records as $record) {
    $res = array_map('strtoupper', $record);
    // do something here
}
~~~

#### Reader::fetchPairsWithoutDuplicates

The `Reader::fetchPairsWithoutDuplicates` is removed as it is redundant with the `fetchPairs` method.

Before:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$pairs_without_duplicates = $reader
    ->setOffset(3)
    ->setLimit(2)
    ->fetchPairsWithoutDuplicates()
;

foreach ($pairs_without_duplicates as $key => $value) {
    // do something here
}
~~~

After:

~~~php
use League\Csv\Reader;
use League\Csv\Statement;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
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

#### Reader::fetchDelimitersOccurrence

Use the `League\Csv\delimiter_detect` function instead with a `Reader` object.

Before:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$stats = $reader->fetchDelimitersOccurrence([',', ';', "\t"], 10);
~~~

After:

~~~php
use League\Csv\Reader;
use function League\Csv\delimiter_detect;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$stats = delimiter_detect($reader, [',', ';', "\t"], 10);
~~~

### Optional callable arguments are removed

The following methods no longer accept an optional callable as argument because they return a iterable object.

- `Reader::fetchColumn`
- `Reader::fetchPairs`

Before:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
$records = $reader->fetchColumn(0, function ($value) {
    return strtoupper($value);
});
~~~

After:

~~~php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv', 'r');
foreach ($records->fetchColumn(0) as $value) {
    $value = strtoupper($value);
};
~~~

## Stream Filtering

### Stream support detection

To detect if PHP stream filters are supported you need to call `AbstractCsv::supportsStreamFilter`

Before:

~~~php
use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->isActiveStreamFilter(); //true
~~~

After:

~~~php
use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->supportsStreamFilter(); //true
~~~

### Stream mode

The filtering mode is fixed and can not be changed:

- a `Writer` class will only accept stream filters in writing mode only
- a `Reader` class will only accept stream filters in reading mode only

Therefore `AbstractCsv::setStreamFilterMode` is removed.

To add a stream filter you will only need the `AbstractCsv::addStreamFilter` method.

Before:

~~~php
use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->appendStreamFilter('string.toupper');
$csv->prependStreamFilter('string.rot13');
~~~

After:

~~~php
use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->addStreamFilter('string.rot13');
$csv->addStreamFilter('string.toupper');
//the insertion order has changed
~~~

### stream filter removal

PHP Stream filters will only be removed on CSV object destruction.

Before:

~~~php
use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->appendStreamFilter('string.toupper');
$csv->prependStreamFilter('string.rot13');
$csv->removeStreamFilter('string.rot13');
$csv->clearStreamFilters();
~~~

After:

~~~php
use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->addStreamFilter('string.rot13');
$csv->addStreamFilter('string.toupper');
$csv = null;
~~~

## Conversion methods

All convertion methods are no longer attached to the `Reader` or the `Writer` classes you need a [Converter](/9.0/reader/converter/) object to convert your CSV. The following methods are removed

- `Writer::jsonSerialize`
- `AbstractCsv::toHTML`
- `AbstractCsv::toXML`

And you can no longer convert a `Writer` class.

Before:

~~~php
use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$dom = $csv->toXML(); //$dom is a DOMDocument
~~~

After:

~~~php
use League\Csv\XMLConverter;
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$dom = (new XMLConverter())->convert($csv); //$dom is a DOMDocument
~~~

## Miscellaneous

### Switching between connections

- `AbstractCsv::newReader`
- `AbstractCsv::newWriter`

You can no longer switch between connection. You are require to explicitly load a new `Reader` and/or `Writer` object.

### Columns consistency Validator

- `League\Csv\Plugin\ColumnConsistencyValidator` is renamed `League\Csv\ColumnConsistency` and is now an immutable object.

Before:

~~~php
use League\Csv\Plugin\ColumnConsistencyValidator;
use League\Csv\Writer;

$validator = new ColumnConsistencyValidator();
$validator->autodetectColumnCount();

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->addValidator($validator, 'columns_consistency');
~~~

After:

~~~php
use League\Csv\ColumnConsistency;
use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->addValidator(new ColumnConsistency(), 'columns_consistency');
~~~
