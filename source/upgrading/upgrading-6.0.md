---
layout: layout
title: Upgrading from 5.x to 6.x
permalink: upgrading/6.0/
---

# Upgrading from 5.x to 6.x

## Added methods

### Named Constructors

The new preferred way to instantiate a CSV object is to use the [named constructors](/overview/#instantiation): `createFromPath`, `createFromFilObject`, `createFromString`. You can still use the class constructor for backward compatibility.

### Stream Filter API

The Stream Filter API is introduced. Please [refer to the documentation](/filtering/) for more information

## Remove methods

### detectDelimiter 

This method has been replaced by the `detectDelimiterList` method. The difference between both methods is that the latter always return an array as the former was throwing `RuntimeException` when multiple delimiters where found (ie: the CSV was inconsistent)

Old code:

~~~.language-php
$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);


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

~~~.language-php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$reader->setEnclosure('"');
$reader->setEscape('\\');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);

$delimiters_list = $reader->detectDelimiterList(10, [' ', '|']);
if (! $delimiters_list) {
	//no delimiter found
} elseif (1 > count($delimiters_list)) {
	//inconsistent CSV 
} else {
	$delimiter = $delimiters_list[0]; // the found delimiter
}

~~~

### Transcoding properties

`setEncoding`/`getEnconding`: the encondingFrom setter and getter are renamed `setEncodingFrom`/`getEncondingFrom` to remove any ambiguitee. **The `League\Csv` always assume that the output is in `UTF-8`** so when transcoding your CSV you should always transcode into an UTF-8 compatible charset.

### Creating new instances

`getReader` was specific to the `Writer` class while `getWriter` was specific to the Reader class. Starting with version 6.0 the new methods `newWriter` and `newReader` are available on **both** class. This means you can create a CSV reader and/or a CSV writer object from any given object.

Of course you:

* `newWriter` behaves exactly like `getWriter`;
* `newReader` behaves exactly like `getReader`;

Old code:

~~~.language-php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$writer = $reader->getWriter('a+');

~~~

New code:

~~~.language-php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$writer = $reader->newWriter('a+');

//but you can now as well do this
$another_writer = $writer->newWriter('rb+');
$another_reader = $writer->newReader();
~~~

### Already deprecated methods

- `setSortBy`: the method was already deprecated since version 5.2.
- `setFilter`: the method was already deprecated since version 5.1.


## Installing this version

~~~.language-javascript
{
    "require": {
        "league/csv": "6.*"
    }
}
~~~