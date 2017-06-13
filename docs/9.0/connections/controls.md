---
layout: default
title: Csv character controls
---

# CSV character controls

To correctly parse a CSV document you are required to set the character controls to be used by the `Reader` or the `Writer` object.

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

<p class="message-notice">To produce RFC4180 compliant CSV documents you should use the <a href="/9.0/interoperability/#rfc4180-compliance">RFC4180Field</a> stream filter to work around bugs associated with the use of the escape character.</p>

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

