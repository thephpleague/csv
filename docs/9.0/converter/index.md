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

public Converter::convert(iterable $records): iterable
~~~

The `$records` argument can be:

- a [Reader](/9.0/reader/) object
- a [RecordSet](/9.0/reader/records/) object;
- or any `array` or `Traversable` object which represents a collection of CSV like records;

<p class="message-warning"><strong>Warning:</strong> A <code>League\Csv\Writer</code> object can not be converted.</p>

## Converters are immutable

Before convertion, you may want to configure your converter. Each provided converter exposes additional methods to correctly convert your records.

When building a converter object, the methods do not need to be called in any particular order, and may be called multiple times. Because all provided converters are immutable, each time their setter methods are called they will return a new object without modifying the current one.

## Records encoding

~~~php
<?php

public Encoder::inputEncoding(string $input_encoding): self
public Encoder::outputEncoding(string $output_encoding = 'UTF-8'): self
public Encoder::encodeOne(array $record): array
public Encoder::encodeAll(iterable $records): iterable
~~~

Out of the box, all converters assume that your are submitting records on a valid encoding charset. For instance, if your data is not `UTF-8` encoded some unexpected results or exception may be thrown when trying to convert your data in JSON format.

You can use the `Encoder` class to encode your records prior to converting it

~~~php
<?php

use League\Csv\Encoder;
use League\Csv\JsonConverter;

$csv = new SplFileObject('/path/to/french.csv', 'r');
$csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);

$encoder = (new Encoder())->inputEncoding('iso-8859-15');
$converter = new JsonConverter();

$json = $converter->convert($encoder->encodeAll($csv));
~~~

<p class="message-info"><strong>Tips:</strong> If your records come from a <code>Reader</code> object which supports PHP stream filters then it's recommended to use the library <a href="/9.0/connections/filters/">stream filtering mechanism</a> to first encode your data in <code>UTF-8</code>.</p>

<p class="message-info"><strong>Tips:</strong> The <code>Encoder</code> object can also be use to format records prior to insertion using <a href="/9.0/writer/filtering/">Writer::addFormater</a>.</p>