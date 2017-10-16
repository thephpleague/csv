---
layout: default
title: CSV document charset encoding
---

# BOM and CSV encoding

Depending on the software you are using to read your CSV you may need to adjust the CSV document outputting script.

<p class="message-warning">To work as intended you CSV object needs to support the <a href="/9.0/connections/filters/">stream filter API</a></p>

## MS Excel on Windows

On Windows, MS Excel, expects an UTF-8 encoded CSV with its corresponding `BOM` character. To fullfill this requirement, you simply need to add the `UTF-8` `BOM` character if needed as explained below:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv', 'r');
//let's set the output BOM
$reader->setOutputBOM(Reader::BOM_UTF8);
//let's convert the incoming data from iso-88959-15 to utf-8
$reader->addStreamFilter('convert.iconv.ISO-8859-15/UTF-8');
//BOM detected and adjusted for the output
echo $reader->getContent();

~~~

<p class="message-info">The conversion is done with the <code>iconv</code> extension using its bundled stream filters.</p>

## MS Excel on MacOS

On a MacOS system, MS Excel requires a CSV encoded in `UTF-16 LE` using the `tab` character as delimiter. Here's an example on how to meet those requirements using the `League\Csv` package.

~~~php
<?php

use League\Csv\CharsetConverter;
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
CharsetConverter::addTo($writer, 'ISO-8859-15', 'UTF-16');
//we insert csv data
$writer->insertAll($origin);
//all is good let's output the results
$writer->output('mycsvfile.csv');
~~~

<p class="message-info">The conversion is done with the <code>mb_string</code> extension using the <a href="/9.0/converter/charset/">League\Csv\CharsetConverter</a>.</p>
