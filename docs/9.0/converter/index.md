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

<p class="message-warning"><strong>Warning:</strong> A <code>League\Csv\Writer</code> object can not be converted.</p>

## Converters are immutable

Before conversion, you may want to configure your converter object. Each provided converter exposes additional methods to correctly convert your records.

When building a converter object, the methods do not need to be called in any particular order, and may be called multiple times. Because all provided converters are immutable, each time their setter methods are called they will return a new object without modifying the current one.