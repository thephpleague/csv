---
layout: default
title: CSV document interoperability
---

# Document interoperability

Depending on your operating system and on the software you are using to read/import your CSV you may need to adjust:

- the BOM sequence used
- the encoding character
- the field formatting

<p class="message-info">Out of the box, <code>League\Csv</code> connections do not alter the CSV document presentation.</p>

In the examples below we will be using an existing CSV in ISO-8859-15 charset encoding as a starting point. The code will vary if your CSV document is in a different charset.

## MS Excel on Windows

On Windows, MS Excel, expects an UTF-8 encoded CSV with its corresponding `BOM` character. To fullfill this requirement, you simply need to add the `UTF-8` `BOM` character if needed as explained below:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
//let's set the output BOM
$reader->setOutputBOM(Reader::BOM_UTF8);
//let's convert the incoming data from iso-88959-15 to utf-8
$reader->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');
//BOM detected and adjusted for the output
echo $reader->__toString();

~~~

## MS Excel on MacOS

On a MacOS system, MS Excel requires a CSV encoded in `UTF-16 LE` using the `tab` character as delimiter. Here's an example on how to meet those requirements using the `League\Csv` package.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

//the current CSV is ISO-8859-15 encoded with a ";" delimiter
$origin = Reader::createFromPath('/path/to/french.csv', 'r');
$origin->setDelimiter(';');

//let's use stream resource
$writer = Writer::createFromStream(fopen('php://temp', 'r+'));
//let's set the output BOM
$writer->setOutputBOM(Reader::BOM_UTF16_LE);
//we set the tab as the delimiter character
$writer->setDelimiter("\t");
//let's convert the incoming data from iso-88959-15 to utf-16
$writer->addStreamFilter('convert.iconv.ISO-8859-15/UTF-16');
//we insert csv data
$writer->insertAll($origin);
//all is good let's output the results
$writer->output('mycsvfile.csv');
~~~

<p class="message-notice">In the examples aboves, we assumed that the <code>iconv</code> extension is present. Alternatively your can use the <a href="/9.0/converter/charset/">League\CharsetConverter</a> class to register your PHP stream filter.</p>

## RFC4180 compliance

Because `League\Csv` uses PHP's csv native functions, out of the box the library does not follow [RFC4180](https://tools.ietf.org/html/rfc4180#section-2). But you can easily create a compliant CSV document using

- `League\Csv\RFC4180FieldFilter` stream filter.
- `League\Csv\Writer::setNewline` method.

<p class="message-notice">The <code>Writer</code> must supports stream filter</p>

~~~php
<?php

use League\Csv\Reader;
use League\Csv\RFC4180FieldFilter;
use League\Csv\Writer;

//the current CSV is ISO-8859-15 encoded with a ";" delimiter
$origin = Reader::createFromPath('/path/to/french.csv', 'r');
$origin->setDelimiter(';');

$writer = Writer::createFromPath('php://temp');
$writer->setNewline("\r\n"); //RFC4180 Line feed
$writer->setOutputBOM(Reader::BOM_UTF16_LE);
$writer->setDelimiter("\t");
$writer->addStreamFilter('convert.iconv.ISO-8859-15/UTF-16');
RFC4180FieldFilter::addTo($writer); //adding the stream filter to fix field formatting
$writer->insertAll($origin);
$writer->output('mycsvfile.csv'); //outputting a RFC4180 compliant CSV Document
~~~

Conversely, to read a RFC4180 compliant CSV document, when using the `League\Csv\Reader` object, just add the `League\Csv\RFC4180FieldFilter` stream filter as shown below

~~~php
<?php

use League\Csv\Reader;
use League\Csv\RFC4180FieldFilter;

//the current CSV is ISO-8859-15 encoded with a ";" delimiter
$csv = Reader::createFromPath('/path/to/rfc4180-compliant.csv');
$csv->setDelimiter(';');
RFC4180FieldFilter::addTo($csv); //adding the stream filter to fix field formatting

foreach ($csv as $record) {
    //do something meaningful here...
}
~~~