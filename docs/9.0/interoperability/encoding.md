---
layout: default
title: CSV document charset encoding
---

# BOM and CSV encoding

Depending on the software you are using to read your CSV you may need to adjust the CSV document outputting script.

<p class="message-warning">To work as intended you CSV object needs to support the <a href="/9.0/connections/filters/">stream filter API</a></p>

## MS Excel on Windows

On Windows, MS Excel expects an UTF-8 encoded CSV with its corresponding `BOM` character. To fulfill this requirement, you simply need to add the `UTF-8` `BOM` character if needed as explained below:

```php
use League\Csv\Bom;
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
//let's set the output BOM
$reader->setOutputBOM(Bom::Utf8);
//let's convert the incoming data from iso-88959-15 to utf-8
$reader->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');
//BOM detected and adjusted for the output
echo $reader->getContent();
```

<p class="message-info">The conversion is done with the <code>iconv</code> extension using its bundled stream filters.</p>

## MS Excel on MacOS

On a MacOS system, MS Excel requires a CSV encoded in `UTF-16 LE` using the `tab` character as delimiter. Here's an example on how to meet those requirements using the `League\Csv` package.

```php
use League\Csv\Bom;
use League\Csv\CharsetConverter;
use League\Csv\Reader;
use League\Csv\Writer;

//the current CSV is ISO-8859-15 encoded with a ";" delimiter
$origin = Reader::createFromPath('/path/to/french.csv', 'r');
$origin->setDelimiter(';');

//let's use stream resource
$writer = Writer::createFromStream(fopen('php://temp', 'r+'));
//let's set the output BOM
$writer->setOutputBOM(Bom::Utf16Le);
//we set the tab as the delimiter character
$writer->setDelimiter("\t");
//let's convert the incoming data from iso-88959-15 to utf-16
CharsetConverter::addTo($writer, 'ISO-8859-15', 'UTF-16');
//we insert csv data
$writer->insertAll($origin);
//all is good let's output the results
$writer->output('mycsvfile.csv');
```

<p class="message-info">The conversion is done with the <code>mbstring</code> extension using the <a href="/9.0/converter/charset/">League\Csv\CharsetConverter</a>.</p>

## Skipping The BOM sequence with the Reader class

<p class="message-info">Since version <code>9.9.0</code>.</p>

In order to ensure the correct removal of the sequence and avoid bugs while parsing the CSV, the filter can skip the
BOM sequence completely when using the `Reader` class and convert the CSV content from the BOM sequence encoding charset
to UTF-8. To work as intended call the `Reader::includeInputBOM` method to ensure the default BOM removal behaviour is disabled
and add the stream filter to you reader instance using the static method `CharsetConverter::skipBOM` method;

```php
<?php

use League\Csv\Bom;
use League\Csv\Reader;
use League\Csv\CharsetConverter;

$input = Bom::Utf16Be->value."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
$document = Reader::createFromString($input);
$document->includeInputBOM(); // de-activate the default skipping mechanism
CharsetConverter::addBOMSkippingTo($document);
var_dump([...$document]);
// returns the document content without the skipped BOM sequence 
// [
//     ['john', 'doe', 'john.doe@example.com'],
//     ['jane', 'doe', 'jane.doe@example.com'],
// ]
```

<p class="message-warning">Once the filter is applied, the <code>Reader</code> instance looses all information regarding its
own BOM sequence. <strong>The sequence is still be present but the instance is no longer able to detect it</strong>.</p>
