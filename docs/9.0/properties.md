---
layout: default
title: Csv properties
---

# CSV properties

Once your connection is [instantiated](/9.0/connections/) you can optionally set or retrieve several attributes. The following attributes can be applied on both the `Reader` and the `Writer` connections.

~~~php
<?php

public AbstractCsv::getDelimiter(void): string
public AbstractCsv::getEnclosure(void): string
public AbstractCsv::getEscape(void): string
public AbstractCsv::getInputBOM(void): string
public AbstractCsv::getOutputBOM(void): string
public AbstractCsv::hasStreamFilter(string $filtername): bool
public AbstractCsv::supportsStreamFilter(void): bool
public AbstractCsv::setDelimiter(string $delimiter): AbstractCsv
public AbstractCsv::setEnclosure(string $delimiter): AbstractCsv
public AbstractCsv::setEscape(string $delimiter): AbstractCsv
public AbstractCsv::setOutputBOM(string $sequence): AbstractCsv
public AbstractCsv::addStreamFilter(string $filtername): AbstractCsv
~~~

## CSV character controls

### The CSV delimiter character.

#### Description

~~~php
<?php

public AbstractCsv::setDelimiter(string $delimiter): AbstractCsv
public AbstractCsv::getDelimiter(void): string
~~~

#### Example

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setDelimiter(';');
$delimiter = $csv->getDelimiter(); //returns ";"
~~~

<p class="message-info">The default delimiter character is <code>,</code>.</p>

### The enclosure character

#### Description

~~~php
<?php

public AbstractCsv::setEnclosure(string $delimiter): AbstractCsv
public AbstractCsv::getEnclosure(void): string
~~~

#### Example

~~~php
<?php

use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->setEnclosure('|');
$enclosure = $csv->getEnclosure(); //returns "|"
~~~

<p class="message-info">The default enclosure character is <code>"</code>.</p>

### The escape character

<p class="message-warning"><strong>Warning:</strong> The library does not attempt to workaround <a href="https://bugs.php.net/bug.php?id=55413" target="_blank">a reported bug</a>, <strong>Data using the escape character are correctly escaped but the escape character is not removed from the CSV content</strong>.</p>


#### Description

~~~php
<?php

public AbstractCsv::setEscape(string $delimiter): AbstractCsv
public AbstractCsv::getEscape(void): string
~~~

#### Example

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setEscape('\\');
$escape = $csv->getEscape(); //returns "\"
~~~

<p class="message-info">The default escape character is <code>\</code>.</p>

### Inherited controls

When using a `SplFileObject`, the underlying CSV controls from the submitted object are inherited by the return `AbstractCsv` object.

~~~php
<?php

$file = new SplTempFileObject();
$file->setFlags(SplFileObject::READ_CSV);
$file->setCsvControl('|');

$csv = Reader::createFromFileObject($file);

echo $csv->getDelimiter(); //display '|'
~~~

<p class="message-warning">The escape character is only inherited starting with <code>PHP 7.0.10</code>.</p>

## BOM sequences

To improve interoperability with programs interacting with CSV, you can manage the presence of the <abbr title="Byte Order Mark">BOM</abbr> sequence in your CSV content.

To ease BOM sequence manipulation, the `AbstractCsv` class provides the following constants :

* `AbstractCsv::BOM_UTF8` : `UTF-8` `BOM`;
* `AbstractCsv::BOM_UTF16_BE` : `UTF-16` `BOM` with Big-Endian;
* `AbstractCsv::BOM_UTF16_LE` : `UTF-16` `BOM` with Little-Endian;
* `AbstractCsv::BOM_UTF32_BE` : `UTF-32` `BOM` with Big-Endian;
* `AbstractCsv::BOM_UTF32_LE` : `UTF-32` `BOM` with Little-Endian;

### Detect the currently used BOM sequence

~~~php
<?php

public AbstractCsv::getInputBOM(void): string
~~~

Detect the current BOM character is done using the `getInputBOM` method. This method returns the currently used BOM character or an empty string if none is found or recognized.

~~~php
<?php

use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$bom = $csv->getInputBOM();
~~~

### Set the outputting BOM sequence

~~~php
<?php

public AbstractCsv::setOutputBOM(string $sequence): AbstractCsv
public AbstractCsv::getOutputBOM(void): string
~~~

- `setOutputBOM`: sets the outputting BOM you want your CSV to be associated with.
- `getOutputBOM`: get the outputting BOM you want your CSV to be associated with.

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setOutputBOM(Reader::BOM_UTF8);
$bom = $csv->getOutputBOM(); //returns "\xEF\xBB\xBF"
~~~

<p class="message-info">The default output <code>BOM</code> character is set to an empty string.</p>

## Stream Filter

To ease performing operations on the CSV as it is being read from or written to, you can add PHP stream filters to the `Reader` and `Writer` connections .

### Detect stream filter supports

~~~php
<?php

public AbstractCsv::supportsStreamFilter(void): bool
~~~

Tells whether the stream filter API is supported

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->supportsStreamFilter(); //return true

$writer = Writer::createFromFileObject(new SplTempFileObject());
$writer->supportsStreamFilter(); //return false the API can not be use
~~~

<p class="message-warning"><strong>Warning:</strong> A <code>LogicException</code> exception may be thrown if you try to use the API under certain circumstances without prior validation using <code>supportsStreamFilter</code></p>

### Add a PHP stream filter

~~~php
<?php

public AbstractCsv::addStreamFilter(string $filtername): AbstractCsv
public AbstractCsv::hasStreamFilter(string $filtername): bool
~~~

The `$filtername` parameter is a string that represents the filter as registered using php `stream_filter_register` function or one of PHP internal stream filter.

The `AbstractCsv::addStreamFilter` method adds a stream filter to the connection.

<p class="message-notice">Because of the way PHP stream filters are added, you will add multiple times the same filter if you call this method with the same argument.</p>

The `AbstractCsv::hasStreamFilter`: tells whether a stream filter is already attached to the connection.

~~~php
<?php

use League\Csv\Reader;
use MyLib\Transcode;

stream_filter_register('convert.utf8decode', Transcode::class);
// 'MyLib\Transcode' is a class that extends PHP's php_user_filter class

$reader = Reader::createFromPath('/path/to/my/chinese.csv');
if ($reader->supportsStreamFilter()) {
	$reader->addStreamFilter('convert.utf8decode');
	$reader->addStreamFilter('string.toupper');
}

$reader->hasStreamFilter('string.toupper'); //returns true
$reader->hasStreamFilter('string.tolower'); //returns false

foreach ($reader as $row) {
	// each row cell now contains strings that have been:
	// first UTF8 decoded and then uppercased
}
~~~

<p class="message-info">To clear any attached stream filter you need to call the <code>__destruct</code> method.</p>

~~~php
<?php

use League\Csv\Reader;

$fp = fopen('/path/to/my/chines.csv', 'r');
$reader = Reader::createFromStream($fp);
if ($reader->supportsStreamFilter()) {
	$reader->addStreamFilter('convert.utf8decode');
	$reader->addStreamFilter('string.toupper');
}

$reader = null;
//only the filters attached using addStreamFilter to `$fp` are removed.
~~~

<p class="message-warning">Only the filters added by the package are removed, filters added to the resource prior to being used in the library are not affected.</p>

## Software dependency

Depending on your operating system and on the software you are using to read/import your CSV you may need to adjust the encoding character and add its corresponding BOM character to your CSV.

<p class="message-warning">Out of the box, <code>League\Csv</code> assumes that your are using a <code>UTF-8</code> encoded CSV.</p>

In the examples below we will be using an existing CSV as a starting point. The code may vary if you are creating the CSV from scratch.

### MS Excel on Windows

On Windows, MS Excel, expects an UTF-8 encoded CSV with its corresponding `BOM` character. To fullfill this requirement, you simply need to add the `UTF-8` `BOM` character if needed as explained below:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setOutputBOM(Reader::BOM_UTF8);
//BOM detected and adjusted for the output
echo $reader->__toString();

~~~

### MS Excel on MacOS

On a MacOS system, MS Excel requires a CSV encoded in `UTF-16 LE` using the `tab` character as delimiter. Here's an example on how to meet those requirements using the `League\Csv` package.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

//the current CSV is ISO-8859-15 encoded with a ";" delimiter
$origin = Reader::createFromPath('/path/to/franch.csv', 'r')
    ->setDelimiter(';')
;

//let's use stream resource
//let's set the output BOM
//we set the tab as the delimiter character
$writer = Writer::createFromStream(fopen('php://temp', 'r+'))
    ->setOutputBOM(Reader::BOM_UTF16_LE)
    ->setDelimiter("\t")
    ->addStreamFilter('convert.iconv.ISO-8859-15/UTF-16')
;

//we insert csv data
$writer->insertAll($origin);
//all is good let's output the results
$writer->output('mycsvfile.csv');
~~~
