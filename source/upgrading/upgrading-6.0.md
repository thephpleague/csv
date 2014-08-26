---
layout: layout
title: Upgrading from 5.x to 6.x
permalink: upgrading/6.0/
---

# Upgrading from 5.x to 6.x

## Added methods

### Named Constructors

The new preferred way to instantiate a CSV object is to use the [named constructors](/overview/#instantiation): `createFromPath`, `createFromFilObject`, `createFromString`. You can still use the class constructor for backward compatibility.

Using the class constructor directly:

~~~.language-php
use League\Csv\Writer;

$csv1 = new Writer('/path/to/your/csv/file.csv');
$csv2 = new Writer(new SplFileObject('/path/to/your/csv/file.csv', 'a+'));
$csv3 = new Writer(new SplFileObject('/path/to/your/csv/file.csv', 'a+'), 'wb+');
~~~
In case of <code>$csv3</code> the object <code>$open_mode</code> will be <code>a+</code> as the constructor will ignore the constructor <code>$open_mode</code> parameter completely!!

Using named constructors:

~~~.language-php
use League\Csv\Writer;

$csv1 = Writer::createFromPath('/path/to/your/csv/file.csv');
$csv2 = Writer::createFromFileObject(new SplFileObject('/path/to/your/csv/file.csv', 'a+'));
$csv3 = Writer::createFromPath(new SplFileObject('/path/to/your/csv/file.csv', 'a+'), 'wb+');
try {
	Writer::createFromPath(new SplTempFileObject);
} catch(InvalidArgumentException $e) {
	echo $e->getMessage(); //you can not use the createFromPath method with a SplTempFileObject
}

~~~

In case of <code>$csv3</code> the object <code>$open_mode</code> will be <code>wb+</code> as the named constructor won't be using the submitted object but only retrieve its file path.

### Stream Filter API

The Stream Filter API is introduced. Please [refer to the documentation](/filtering/) for more information

## Backward compatibility breaks

### detectDelimiter 

This method has been replaced by the `detectDelimiterList` method. The difference between both methods is that the latter always return an array as the former was throwing `RuntimeException` when multiple delimiters where found (ie: the CSV was inconsistent)

Old code:

~~~.language-php
$reader = new Reader('/path/to/your/csv/file.csv');
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

`setEncoding`/`getEnconding`: the `$encondingFrom` property setter and getter are renamed `setEncodingFrom`/`getEncondingFrom` to avoid any ambiguity. **The library always assume that the output is in `UTF-8`** so when transcoding your CSV you should always transcode into an UTF-8 compatible charset.

### Creating new instances

`getReader` was specific to the `Writer` class while `getWriter` was specific to the Reader class. Starting with version 6.0 the new methods `newWriter` and `newReader` are available on **both** class. This means you can create a CSV reader and/or a CSV writer object from any given object.

Of course you:

* `newWriter` behaves exactly like `getWriter`;
* `newReader` behaves exactly like `getReader`;

Old code:

~~~.language-php
use League\Csv\Reader;

$reader = new Reader('/path/to/your/csv/file.csv');
$writer = $reader->getWriter('a+');

$another_reader = $writer->getReader();
~~~

New code:

~~~.language-php
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$writer = $reader->newWriter('a+');

$another_writer = $writer->newWriter('rb+');
$another_reader1 = $writer->newReader();
$another_reader2 = $reader->newReader();
~~~

### Already deprecated methods

- `setSortBy`: the method was already deprecated since version 5.2 and replaced by `addSortBy`.
- `setFilter`: the method was already deprecated since version 5.1 and replaced by `addFilter`.


## Installing this version

~~~.language-javascript
{
    "require": {
        "league/csv": "6.*"
    }
}
~~~