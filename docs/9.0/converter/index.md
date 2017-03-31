---
layout: default
title: Records conversion in popular formats
---

# Records conversion

## Converter classes

The package provides classes which convert any collection of CSV records into:

- another collection encoded using the [CharsetConverter](/9.0/converter/charset/) class;
- a `DOMDocument` object using the [XMLConverter](/9.0/converter/xml/) class;
- a HTML table using the [HTMLConverter](/9.0/converter/html/) class;
- a Json string using the [JsonConverter](/9.0/converter/json/) class;

All theses classes expose a common `convert` method defined as follow:

~~~php
<?php

public Converter::convert(iterable $records): mixed
~~~

The `$records` argument can be:

- a [Reader](/9.0/reader/) object
- a [RecordSet](/9.0/reader/records/) object;
- or any `array` or `Traversable` object which represents a collection of CSV like records;

<p class="message-warning"><strong>Warning:</strong> A <code>League\Csv\Writer</code> object can not be converted.</p>

The returned value type will depend on the converter object used.

## Converters are immutable

Before conversion, you may want to configure your converter object. Each provided converter exposes additional methods to correctly convert your records.

When building a converter object, the methods do not need to be called in any particular order, and may be called multiple times. Because all provided converters are immutable, each time their setter methods are called they will return a new object without modifying the current one.