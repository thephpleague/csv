---
layout: default
title: Csv character controls
---

# CSV character controls

~~~php
<?php

public AbstractCsv::getDelimiter(void): string
public AbstractCsv::getEnclosure(void): string
public AbstractCsv::getEscape(void): string
public AbstractCsv::setDelimiter(string $delimiter): self
public AbstractCsv::setEnclosure(string $enclosure): self
public AbstractCsv::setEscape(string $escape): self
~~~

To correclty parse a CSV document you are required to set the character controls to be used by the `Reader` or the `Writer` object.

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

<p class="message-warning"><strong>Warning:</strong> The library does not attempt to workaround <a href="https://bugs.php.net/bug.php?id=55413" target="_blank">a reported PHP bug</a>, <strong>Data using the escape character are correctly escaped but the escape character is not removed from the CSV content</strong>.</p>


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

<p class="message-warning">The escape character is only inherited starting with <code>PHP 7.0.10</code>.</p>

