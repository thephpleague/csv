---
layout: default
title: BOM sequence detection and addition
---

# BOM sequences

~~~php
<?php

const AbstractCsv::BOM_UTF8 = "\xEF\xBB\xBF";
const AbstractCsv::BOM_UTF16_BE = "\xFE\xFF";
const AbstractCsv::BOM_UTF16_LE = "\xFF\xFE";
const AbstractCsv::BOM_UTF32_BE = "\x00\x00\xFE\xFF";
const AbstractCsv::BOM_UTF32_LE = "\xFF\xFE\x00\x00";

public AbstractCsv::getInputBOM(void): string
public AbstractCsv::getOutputBOM(void): string
public AbstractCsv::setOutputBOM(string $sequence): AbstractCsv
~~~

To improve interoperability with programs interacting with CSV, you can manage the presence of the <abbr title="Byte Order Mark">BOM</abbr> sequence in your CSV content.

## Connection constants

To ease BOM sequence manipulation, the `AbstractCsv` class provides the following constants :

* `AbstractCsv::BOM_UTF8` : `UTF-8` `BOM`;
* `AbstractCsv::BOM_UTF16_BE` : `UTF-16` `BOM` with Big-Endian;
* `AbstractCsv::BOM_UTF16_LE` : `UTF-16` `BOM` with Little-Endian;
* `AbstractCsv::BOM_UTF32_BE` : `UTF-32` `BOM` with Big-Endian;
* `AbstractCsv::BOM_UTF32_LE` : `UTF-32` `BOM` with Little-Endian;

## Detect the currently used BOM sequence

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

## Set the outputting BOM sequence

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

