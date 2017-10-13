---
layout: default
title: Converting a CSV into a XML DOMDocument object
---

# XML conversion

~~~php
<?php

class XMLConverter
{
    public function convert(iterable $records): DOMDocument
    public function fieldElement(string $node_name, string $fieldname_attribute_name = ''): self
    public function recordElement(string $node_name, string $record_offset_attribute_name = ''): self
    public function rootElement(string $node_name): self
}
~~~

`XMLConverter` converts a CSV records collection into a PHP `DOMDocument`.

## Settings

Prior to converting your records collection into XML, you may wish to configure the element and its associated attribute names. To do so `XMLConverter` provides methods to setup theses settings.

<p class="message-warning">Because we are building a <code>DOMDocument</code> object, the <code>XMLConverter</code> object throws <code>DOMException</code> insted of <code>League\Csv\Exception</code>.</p>

### XMLConverter::rootElement

~~~php
<?php

public XMLConverter::rootElement(string $node_name): self
~~~

This method sets the XML root name.

<p class="message-info">The default root element name is <code>csv</code>.</p>

### XMLConverter::recordElement

~~~php
<?php

public XMLConverter::recordElement(string $node_name, string $record_offset_attribute_name = ''): self
~~~

This method sets the XML record name and optionnally the attribute name for the record offset value if you want this information preserved.

<p class="message-info">The default record element name is <code>row</code>.</p>
<p class="message-info">The default attribute name is the empty string.</p>

### XMLConverter::fieldElement

~~~php
<?php

public XMLConverter::fieldElement(string $node_name, string $fieldname_attribute_name = ''): self
~~~

This method sets the XML field name and optionnally the attribute name for the field name value.

<p class="message-info">The default field element name is <code>cell</code>.</p>
<p class="message-info">The default attribute name is the empty string.</p>

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

$csv = Reader::createFromPath('/path/to/prenoms.csv', 'r');
$csv->setDelimiter(';');
$csv->setHeaderOffset(0);

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
$dom->encoding = 'iso-8859-15';

echo '<pre>', PHP_EOL;
echo htmlentities($dom->saveXML());
// <?xml version="1.0" encoding="iso-8859-15"?>
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

<p class="message-info">If needed you can use the <a href="/9.0/converter/charset/">CharsetConverter</a> object to correctly encode your CSV records before conversion.</p>
