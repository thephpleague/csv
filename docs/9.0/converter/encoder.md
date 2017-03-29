---
layout: default
title: Converting Csv records character encoding
---

# Records encoding

~~~php
<?php

public Encoder::inputEncoding(string $input_encoding): self
public Encoder::outputEncoding(string $output_encoding): self
public Encoder::encodeOne(array $record): array
public Encoder::__invoke(array $record): array
public Encoder::encodeAll(iterable $records): iterable
~~~

The `Encoder` class encodes your CSV records using the `mbstring` extension and its [supported character encodings](http://php.net/manual/en/mbstring.supported-encodings.php).

<p class="message-info"><strong>Tips:</strong> If your records came from a <code>Reader</code> object which supports PHP stream filters then it's recommended to use the library <a href="/9.0/connections/filters/">stream filtering mechanism</a> instead.</p>

## Encoding mechanism

### Properties

~~~php
<?php

public Encoder::inputEncoding(string $input_encoding): self
public Encoder::outputEncoding(string $output_encoding): self
~~~

The `inputEncoding` and `outputEncoding` methods sets the object encoding properties. By default, the input encoding and the output encoding are set to `UTF-8`.

When building a encoder object, the methods do not need to be called in any particular order, and may be called multiple times. Because the `Encoder` is immutable, each time its setter methods are called they return a new object without modifying the current one.

### Conversion

~~~php
<?php

public Encoder::encodeOne(array $record): array
public Encoder::__invoke(array $record): array
public Encoder::encodeAll(iterable $records): iterable
~~~

- `Encoder::encodeOne` converts a single record
- `Encoder::encodeAll` converts a collection of records.
- `Encoder::__invoke` is an alias of `Encoder::encodeOne` and enables the `Encoder` object to be used as a `Writer` formatter.

## Encoding CSV records for conversion

Out of the box, all converters assume that your are submitting records on a valid encoding charset. For instance, if your data is not `UTF-8` encoded some unexpected results or exception may be thrown when trying to convert your data in JSON format.

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

## Encoding CSV records for insertion

Using the `Encoder::__invoke` which is an alias of the `Encoder::encodeOne` method, you can register the encoder object as record formatter using `Writer::addFormatter` method.

~~~php
<?php

use League\Csv\Encoder;
use League\Csv\Writer;

$writer = Writer::createFromPath('/path/to/your/csv/file.csv');
$encoder = (new Encoder())
    ->inputEncoding('utf-8')
    ->outputEncoding('iso-8859-15')
;
$writer->addFormatter($encoder);
$writer->insertOne(["foo", "bébé", "jouet"]);
//all 'utf-8' caracters are now automatically encoded into 'iso-8859-15' charset
~~~