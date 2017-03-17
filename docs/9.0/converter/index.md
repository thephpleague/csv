---
layout: default
title: Records conversion in popular formats
---

# Records conversion

## Converter Interface

The package provides classes which convert any collection of CSV records into:

- a `DOMDocument` object using the [XMLConverter](/9.0/converter/xml/) class;
- a HTML table using the [HTMLConverter](/9.0/converter/html/) class;
- a Json string using the [JsonConverter](/9.0/converter/json/) class;

All theses classes implements the `Converter` interface.

~~~php
<?php

public Converter::convert(iterable $records): mixed
~~~

This `$records` argument can be:

- a [Reader](/9.0/reader/) object
- a [RecordSet](/9.0/reader/records/) object;
- or any `array` or `Traversable` object which represents a collection of CSV records;

<p class="message-warning"><strong>Warning:</strong> A <code>League\Csv\Writer</code> object can not be converted.</p>

## Converters are immutable

Before convertion your records, you will want to configure your converter. Each provided converter exposes additional methods to correctly transcode the collection.

When building a converter object, the methods do not need to be called in any particular order, and may be called multiple times. Because all provided converter object are immutable, each time their setter methods are called they will return a new object without modifying the current object.

## Records Input Encoding

~~~php
<?php

public function ConverterObject::inputEncoding(string $charset): self
~~~

Out of the box, all converters assume that your are submitting `UTF-8` encoded records. If your data is not `UTF-8` encoded some unexpected results or exception may be thrown when trying to convert your data.

If your data is not `UTF-8` encoded use the `inputEncoding` method exposed on each provided converter

~~~php
<?php

use League\Csv\JsonConverter;

$csv = new SplFileObject('/path/to/french.csv', 'r');
$csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

$converter = (new JsonConverter())->inputEncoding('iso-8859-15');

$json = $converter->convert($csv);
~~~

<p class="message-info"><strong>Tips:</strong> If your records collection comes from a <code>Reader</code> object which supports PHP stream filters then it's recommended to use the library <a href="/9.0/connections/filters/">stream filtering mechanism</a> to first encode your data in <code>UTF-8</code>.</p>

<p class="message-warning"><strong>Warning:</strong> <code>inputEncoding</code> is not part of the <code>Converter</code> interface.</p>
