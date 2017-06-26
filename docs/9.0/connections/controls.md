---
layout: default
title: Csv character controls
---

# CSV character controls

To correctly parse a CSV document you are required to set the character controls to be used by the `Reader` or the `Writer` object.

<p>On error the following the character control setter method will throw a <code>LengthException</code> exception if the submitted string length is not equal to <code>1</code>.</p>


## The delimiter character.

### Description

~~~php
<?php

public AbstractCsv::setDelimiter(string $delimiter): self
public AbstractCsv::getDelimiter(void): string
~~~

### Example

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setDelimiter(';');
$delimiter = $csv->getDelimiter(); //returns ";"
~~~

<p class="message-info">The default delimiter character is <code>,</code>.</p>

## The enclosure character

### Description

~~~php
<?php

public AbstractCsv::setEnclosure(string $enclosure): self
public AbstractCsv::getEnclosure(void): string
~~~

### Example

~~~php
<?php

use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$csv->setEnclosure('|');
$enclosure = $csv->getEnclosure(); //returns "|"
~~~

<p class="message-info">The default enclosure character is <code>"</code>.</p>

## The escape character

### Description

~~~php
<?php

public AbstractCsv::setEscape(string $escape): self
public AbstractCsv::getEscape(void): string
~~~

### Example

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$csv->setEscape('\\');
$escape = $csv->getEscape(); //returns "\"
~~~

<p class="message-info">The default escape character is <code>\</code>.</p>

<p class="message-notice">To produce RFC4180 compliant CSV documents you should use the <a href="/9.0/interoperability/rfc4180-field/">RFC4180Field</a> stream filter to work around bugs associated with the use of the escape character.</p>

## Inherited character controls

When using a `SplFileObject`, the underlying CSV controls from the submitted object are inherited by the return `AbstractCsv` object.

~~~php
<?php

$file = new SplTempFileObject();
$file->setFlags(SplFileObject::READ_CSV);
$file->setCsvControl('|');

$csv = Reader::createFromFileObject($file);

echo $csv->getDelimiter(); //display '|'
~~~

## Detecting the delimiter character

~~~php
<?php

function League\Csv\delimiter_detect(Reader $csv, array $delimiters, $limit = 1): array
~~~

The `delimiter_detect` function helps detect the possible delimiter character used by the CSV document. This function returns the number of CSV fields found in the document depending on the submitted delimiters given.

The function takes three (3) arguments:

* a [Reader](/9.0/reader/) object
* an array containing the delimiters to check;
* an integer which represents the number of CSV records to scan (default to `1`);

and returns an associated array whose keys are the submitted delimiters characters and whose values represents the field numbers found depending on the delimiter value.

~~~php
<?php

use function League\Csv\delimiter_detect;
use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/file.csv');
$reader->setEnclosure('"');
$reader->setEscape('\\');

$result = delimiter_detect($reader, [' ', '|'], 10);
// $result can be the following
// [
//     '|' => 20,
//     ' ' => 0,
// ]
// This seems to be a consistent CSV with:
// - 20 fields were counted with the "|" delimiter in the 10 first records;
// - in contrast no field was detected for the " " delimiter;
~~~
If the submitted delimiter **is invalid or not found** in the document, `0` will be returned as its associated value.

<p class="message-info">To detect the delimiters stats on the full CSV document you need to set <code>$limit</code> to <code>-1</code>.</p>
<p class="message-notice">This function only returns hints. Only the CSV providers will validate the real CSV delimiter character.</p>
<p class="message-warning">This function only test the delimiters you gave it.</p>