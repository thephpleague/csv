---
layout: default
title: Converting a CSV into a XML DOMDocument object
---

# XML conversion

`XMLConverter` converts a CSV records collection into a PHP `DOMDocument` by implementing the [Converter interface](/9.0/converter/#converter-interface) and using the [inputEncoding method](/9.0/converter/#records-input-encoding).

## Settings

Prior to converting your records collection into XML, you may wish to configure the element and its associated attribute names. To do so `XMLConverter` provides methods to setup theses settings.

<p class="message-warning">Because we are building a <code>DOMDocument</code>, the <code>XMLConverter</code> object throws <code>DOMException</code> exceptions that do not implements <a href="/9.0/connections/exceptions/">CsvException</a>.</p>

### XMLConverter::rootElement

~~~php
<?php

public XMLConverter::rootElement(string $node_name): self
~~~

This method sets the XML root name which defaults to `csv`.

### XMLConverter::recordElement

~~~php
<?php

public XMLConverter::recordElement(string $node_name, string $record_offset_attribute_name = ''): self
~~~

This method sets the XML record name which defaults to `row`.

Optionnally you can preserve the record offset by providing a name for its attribute on the XML record element using the `$record_offset_attribute_name` argument. If this argument is empty, the offset attribute information won't be added. By default, the attribute is not provided.

### XMLConverter::fieldElement

~~~php
<?php

public XMLConverter::fieldElement(string $node_name, string $fieldname_attribute_name = ''): self
~~~

This method sets the XML field name which defaults to `cell`.

Optionnally you can preserve the field name by providing a name for its attribute on the XML field element using the `$fieldname_attribute_name` argument. If this argument is empty, the fieldname information won't be added. By default, the attribute is not provided.

## Conversion

~~~php
<?php

public XMLConverter::convert(iterable $records): DOMDocument
~~~

The `XMLConverter::convert` accepts an `iterable` which represents the records collection and returns a `DOMDocument` object.

~~~php
<?php

use League\Csv\XMLConverter;
use League\Csv\Statement;
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/prenoms.csv')
    ->setDelimiter(';')
    ->setHeaderOffset(0)
    ->addStreamFilter('convert.iconv.ISO-8859-1/UTF-8')
;

$stmt = (new Statement())
    ->where(function (array $record) {
        return 'Anaïs' === $record['prenoms'];
    })
    ->offset(0)
    ->limit(2)
;

$converter = (new XMLConverter())
    ->rootElement('csv')
    ->recordElement('record', 'offset')
    ->fieldElement('field', 'name')
;

$records = $stmt->process($csv);
$records->preserveRecordOffset(true);

$dom = $converter->convert($records);
$dom->formatOutput = true;

echo '<pre>', PHP_EOL;
echo htmlentities($dom->saveXML());
// <?xml version="1.0" encoding="UTF-8"?>
// <csv>
//   <record offset="71">
//     <field name="prenoms">Anaïs</field>
//     <field name="nombre">137</field>
//     <field name="sexe">F</field>
//     <field name="annee">2004</field>
//   </record>
//   <record offset="1099">
//     <field name="prenoms">Anaïs</field>
//     <field name="nombre">124</field>
//     <field name="sexe">F</field>
//     <field name="annee">2005</field>
//   </record>
// </csv>
~~~
