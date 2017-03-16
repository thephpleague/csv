---
layout: default
title: Records conversion in popular formats
---

# Records conversion

The package provides classes which convert any collection of CSV records into:

- a `DOMDocument` object using the [XMLConveter](/9.0/converter/xml/) class;
- a HTML table using the [HTMLConveter](/9.0/converter/html/) class;
- a Json string using the [JsonConverter](/9.0/converter/json/);

All theses classes implements the `Converter` interface to convert the CSV records collection.

~~~php
<?php

public Converter::convert(iterable $records): mixed
~~~

This CSV records collection can be:

- a [Reader](/9.0/reader/) object
- a [RecordSet](/9.0/reader/records/) object;
- or any `array` or `Traversable` object which represents a collection of CSV records;

<p class="message-warning">A <code>League\Csv\Writer</code> object can not be converted.</p>

## Converter are immutable

Before convertion your records, you will want to configure your converter. Each provided converter exposes additional methods to correctly set them.

When building a `Converter` object, the methods do not need to be called in any particular order, and may be called multiple times. Because the `Converter` object is immutable, each time its setter methods are called they will return a new object without modifying the current object.

## Records Input Encoding

~~~php
<?php

public Converter::inputEncoding(string $charset): self
~~~

<p class="message-warning">Out of the box, all converters assume that your are submitting <code>UTF-8</code> encoded records. If your data is not <code>UTF-8</code> encoded some unexpected results or exception may be thrown when trying to convert your data.</p>

If the submitted records comes from a `Reader` object which supports PHP stream filters then it's recommended to use the library [stream filtering mechanism](/9.0/connections/filters/) to convert your data prior to converting it. Otherwise you can fallback to using the `Converter::inputEncoding` method exposed on each provided converter.

~~~php
<?php

use League\Csv\JsonConverter;

$csv = new SplFileObject('/path/to/french.csv', 'r');
$csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

$converter = (new JsonConverter())->inputEncoding('iso-8859-15');

$json = $converter->convert($csv);
~~~

<p class="message-warning"><code>Converter::inputEncoding</code> is not part of the <code>Converter</code> interface.</p>
