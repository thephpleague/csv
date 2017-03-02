---
layout: default
title: CSV document interoperability
---

# Document interoperability

Depending on your operating system and on the software you are using to read/import your CSV you may need to adjust the encoding character and add its corresponding BOM character to your CSV.

<p class="message-warning">Out of the box, <code>League\Csv</code> assumes that your are using a <code>UTF-8</code> encoded CSV.</p>

In the examples below we will be using an existing CSV as a starting point. The code may vary if you are creating the CSV from scratch.

## MS Excel on Windows

On Windows, MS Excel, expects an UTF-8 encoded CSV with its corresponding `BOM` character. To fullfill this requirement, you simply need to add the `UTF-8` `BOM` character if needed as explained below:

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/my/file.csv');
$reader->setOutputBOM(Reader::BOM_UTF8);
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
