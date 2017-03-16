---
layout: default
title: Records conversion in popular formats
---

# XML conversion

This converter converts a CSV records collection into a PHP `DOMDocument`.

## Settings

~~~php
<?php

public XMLConverter::rootElement(string $node_name): self
public XMLConverter::recordElement(string $node_name, string $offset_attribute_name = ''): self
public XMLConverter::fieldElement(string $node_name, string $field_attribute_name = ''): self
~~~

`XMLConverter::rootElement` sets the XML root name which defaults to `csv`.

`XMLConverter::recordElement` sets the XML record name which defaults to `row`. Optionnally you can preserve the record offset by providing a name for the its attribute on the XML record element using the `$offset_attribute_name` argument. If this argument is empty or not provided, the offset attribute information won't be added. By default, the attribute is not provided.

`XMLConverter::fieldElement` sets the XML field name which defaults to `cell`. Optionnally you can preserve the field name by providing a name for the its attribute on the XML field element using the `$field_attribute_name` argument. If this argument is empty or not provided, the field name information won't be added. By default, the attribute is not provided.

## Convertion

~~~php
<?php
public XMLConverter::convert(iterable $records): DOMDocument
~~~

All convertion methods only accepts an `iterable` which represents the records collection.

~~~php
<?php

use League\Csv\Converter;
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

$converter = (new Converter())
    ->rootElement('csv')
    ->recordElement('record', 'offset')
    ->fieldElement('field', 'name')
;

$records = $stmt->process($csv);
$records->preserveOffset(true);

$dom = $converter->toXML($records);
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
