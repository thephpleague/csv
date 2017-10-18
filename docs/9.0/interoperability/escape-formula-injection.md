---
layout: default
title: CSV Formula Injection
---

# Prevents CSV Formula Injection

<p class="message-notice">Available since <code>version 9.1.0</code></p>

~~~php
<?php
class EscapeFormula
{
    public function __construct(string $escape = "\t", array $special_chars = [])
    public function __invoke(array $record): array
    public function escapeRecord(array $record): array
    public function getEscape(): string
    public function getSpecialCharacters(): array
}
~~~


The `EscapeFormula` Formatter formats CSV records to reduce [CSV Formula Injection](http://georgemauer.net/2017/10/07/csv-injection.html) in imported Spreadsheet programs.

## Usage with Writer objects

The `EscapeFormula` class uses the formatter capabilities of the `Writer` object to escape formula injection.

~~~php
<?php

public function __construct(string $escape = "\t", array $special_chars = [])
public function __invoke(array $record): array
~~~

The `EscapeFormula::__construct` method takes two (2) arguments:

- the `$escape` parameter which will be used to prepend the record field, which default to `\t`;
- the `$special_chars` parameter which is an `array` with additionals characters that need to be escaped. By default the following characters if found at the start of any record field content will be escaped `+`,`-`,`=`,`@`;

~~~php
<?php

use League\Csv\EscapeFormula;
use League\Csv\Writer;

$writer = Writer::createFromPath('php://temp', 'r+');
$writer->addFormatter(new EscapeFormula());
$writer->insertOne(['2', '2017-07-25', 'Important Client', '=2+5', 240, null]);
$writer->getContent();
//outputting a CSV Document with all CSV Formula Injection escaped
//"2,2017-07-25,\"Important Client\",\"\t=2+5\",240,\n"
~~~

## Usage with PHP stream resources

You can use the `EscapeFormula` to format your records before callng `fputcsv` or `SplFileObject::fputcsv`.

~~~php
<?php

use League\Csv\EscapeFormula;

$resource = fopen('/path/to/my/file', 'r+');
$formatter = new EscapeFormula("`");
foreach ($iterable_data as $record) {
    fputcsv($resource, $formatter->escapeRecord($record));
}
~~~

<p class="message-warning">Even though we provide the <code>EscapeFormula</code> formatter I must stress out that this is in no way a bulletproof method. This prevention mechanism only works if <strong>you know how the CSV export will be consumed</strong>. In any other cases, you are better of leaving the filtering to the consuming client and report any found security concern to their respective security channel.</p>