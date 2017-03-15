---
layout: default
title: BOM sequence detection and addition
---

# BOM sequences


## Detecting the BOM sequence

~~~php
<?php

const BOM::UTF8 = "\xEF\xBB\xBF";
const BOM::UTF16_BE = "\xFE\xFF";
const BOM::UTF16_LE = "\xFF\xFE";
const BOM::UTF32_BE = "\x00\x00\xFE\xFF";
const BOM::UTF32_LE = "\xFF\xFE\x00\x00";

public static BOM::match(string $str): string
public static BOM::isValid(string $sequence): bool
~~~

To improve interoperability with programs interacting with CSV, the package now provide a Enum like class `BOM` to help you detect the presence of the <abbr title="Byte Order Mark">BOM</abbr> sequence in any string.

### Constants

To ease BOM sequence manipulation, the `BOM` class provides the following constants :

* `BOM::UTF8` : `UTF-8` `BOM`;
* `BOM::UTF16_BE` : `UTF-16` `BOM` with Big-Endian;
* `BOM::UTF16_LE` : `UTF-16` `BOM` with Little-Endian;
* `BOM::UTF32_BE` : `UTF-32` `BOM` with Big-Endian;
* `BOM::UTF32_LE` : `UTF-32` `BOM` with Little-Endian;

### Methods

~~~php
<?php

public static BOM::match(string $str): string
public static BOM::isValid(string $sequence): bool
~~~

If you have a string and wonders if it begins with a known BOM sequence you can use the `BOM::match` method. This method will try to detect the correct BOM sequence at the start of your string. If no BOM sequence is found, an empty string is returned.

At any given time you can validate your BOM sequence against the class BOM list.

~~~php
<?php

use League\Csv\BOM;

$str = BOM::UTF8.'The quick brown fox jumps over the lazy dog';
$found = BOM::match($str); //returns "\xEF\xBB\xBF"
BOM::isValid($found); //return true
str2 = 'The quick brown fox jumps over the lazy dog';
$found2 = BOM::match($str2); //returns ""
BOM::isValid($found2); //return false
~~~

## Managing CSV documents BOM sequence

### Detecting the BOM sequence

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

### Setting the outputted BOM sequence

~~~php
<?php

public AbstractCsv::setOutputBOM(string $sequence): AbstractCsv
public AbstractCsv::getOutputBOM(void): string
~~~

- `setOutputBOM`: sets the outputting BOM you want your CSV to be associated with.
- `getOutputBOM`: get the outputting BOM you want your CSV to be associated with.

~~~php
<?php

use League\Csv\BOM;
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setOutputBOM(BOM::UTF8);
$bom = $csv->getOutputBOM(); //returns "\xEF\xBB\xBF"
~~~

<p class="message-info">The default output <code>BOM</code> character is set to an empty string.</p>

