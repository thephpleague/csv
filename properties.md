---
layout: default
title: Setting and Accessing CSV settings
---

# CSV properties

Once your object is [instantiated](/instantiation/) you can optionally set several CSV properties. The following methods works on both the `Reader` and the `Writer` class.

<p class="message-notice">Since <code>version 8.1.1</code> The underlying CSV controls from the submitted CSV are inherited by the return <code>AbstractCsv</code> object.</p>


~~~php
<?php

$file = new SplTempFileObject();
$file->setFlags(SplFileObject::READ_CSV);
$file->setCsvControl('|');

$csv = Reader::createFromFileObject($file);

echo $csv->getDelimiter(); //display '|'
~~~

<p class="message-warning">Of note, The escape character is only inherited starting with <code>PHP 5.6.25</code> in the PHP5 line and <code>7.0.10</code> in the PHP7 version.</p>


## Accessing and Setting CSV properties

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

#### Notes

The default delimiter character is `,`.

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

#### Notes

The default enclosure character is `"`.

### The escape character

<p class="message-warning"><strong>Warning:</strong> The library depends on PHP <code>SplFileObject</code> class. Since this class exhibits <a href="https://bugs.php.net/bug.php?id=55413" target="_blank">a reported bug</a>, <strong>Data using the escape character are correctly escaped but the escape character is not removed from the CSV content</strong>.<br>
A possible workaround to this issue while waiting for a PHP bug fix is <a href="/reading/">to register a callable to your extracting method when possible.</a></p>


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

#### Notes

The default escape character is `\`.

### fetchDelimitersOccurrence

This method allow you to find the occurences of some delimiters in a given CSV object.

~~~php
<?php

public AbstractCsv::fetchDelimitersOccurrence(
	array $delimiters,
	int $nbRows = 1
): array
~~~

The method takes two arguments:

* an array containing the delimiters to check;
* an integer which represents the number of rows to scan (default to `1`);

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$reader->setEnclosure('"');
$reader->setEscape('\\');

$delimiters_list = $reader->fetchDelimitersOccurrence([' ', '|'], 10);
// $delimiters_list can be the following
// [
//     '|' => 20,
//     ' ' => 0,
// ]
// This seems to be a consistent CSV with:
// - the delimiter "|" appearing 20 times in the 10 first rows
// - the delimiter " " never appearing
~~~

<p class="message-warning"><strong>Warning:</strong> This method only test the delimiters you gave it.</p>

## Writing mode only properties

The following properties only affect the CSV object when you are writing and/or saving data to it.

<p class="message-notice">The <code>Reader</code> class still have access to them.</p>


### The newline sequence

To improve interoperability with programs interacting with CSV, the newline sequence is appended to each CSV newly inserted line.

#### Description

~~~php
<?php

public AbstractCsv::setNewline(string $sequence): AbstractCsv
public AbstractCsv::getNewline(void): string
~~~

#### Example

~~~php
<?php

use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->setNewline("\r\n");
$newline = $csv->getNewline(); //returns "\r\n"
~~~

#### Notes

The default newline sequence is `\n`;

### The BOM sequence

To improve interoperability with programs interacting with CSV, you can manage the presence of the <abbr title="Byte Order Mark">BOM</abbr> sequence in your CSV content.

#### Detect the currently used BOM sequence

<p class="message-warning"><strong>BC Break:</strong> <code>getInputBOM</code> always return a string</p>

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

#### Set the outputting BOM sequence

~~~php
<?php

public AbstractCsv::setOutputBOM(string $sequence): AbstractCsv
public AbstractCsv::getOutputBOM(void): string
~~~

- `setOutputBOM`: sets the outputting BOM you want your CSV to be associated with.
- `getOutputBOM`: get the outputting BOM you want your CSV to be associated with.

<p class="message-warning"><strong>BC Break:</strong> <code>getOutputBOM</code> always return a string</p>

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setOutputBOM(Reader::BOM_UTF8);
$bom = $csv->getOutputBOM(); //returns "\xEF\xBB\xBF"
~~~

#### Notes

- The default output `BOM` character is set to an empty string.
- The `AbstractCsv` class provide constants to ease BOM sequence manipulation.

<p class="message-info">Please refer to <a href="/bom/">the BOM character dedicated documentation page</a> for more informations on how the library helps you manage this feature.</p>

## Conversion only properties

<p class="message-notice">The following properties and method only works when converting your CSV document into other available formats.</p>

To convert your CSV document into another format it must be encoded in `UTF-8`.

When this is not the case, you should transcode it first using the <a href="/filtering/">library stream filtering mechanism</a>. When this is not applicable you should provide the CSV original encoding charset to the CSV object using the following methods.

### methods

<p class="message-notice">These methods are introduced in version <code>8.1.0</code></p>

~~~php
<?php

public AbstractCsv::setInputEncoding(string $sequence): AbstractCsv
public AbstractCsv::getInputEncoding(void): string
~~~

<p class="message-warning">The following methods are deprecated since version <code>8.1.0</code> and will be remove in the next major release</p>

~~~php
<?php

public AbstractCsv::setEncodingFrom(string $sequence): AbstractCsv
public AbstractCsv::getEncodingFrom(void): string
~~~

- `AbstractCsv::setEncodingFrom` is replaced by `AbstractCsv::setInputEncoding`
- `AbstractCsv::getInputEncoding` is replaced by `AbstractCsv::getEncodingFrom`

#### Example

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setInputEncoding('iso-8859-15');
echo $csv->getInputEncoding(); //returns iso-8859-15;
~~~

#### Notes

By default `getInputEncoding` returns `UTF-8` if `setInputEncoding` was not used.

<div class="message-warning">The encoding properties have no effect when reading or writing to a CSV document. You should instead use <a href="/filtering/">the Stream Filter API</a> or <a href="/inserting/#row-formatting">the Writing Formatter API</a>.</div>

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromFileObject(new SplFileObject('/path/to/bengali.csv'));
//we are using the setInputEncoding method to transcode the CSV into UTF-8
$reader->setInputEncoding('iso-8859-15');
echo json_encode($reader);
//the CSV is transcoded from iso-8859-15 to UTF-8
//before being converted to JSON format;
echo $reader; //outputting the data is not affected by the conversion
~~~
