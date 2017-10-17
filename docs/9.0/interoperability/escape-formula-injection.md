---
layout: default
title: CSV Formula Injection
---

# Prevents Formula Injection

<p class="message-info">Available since <code>version 9.1.0</code></p>

~~~php
<?php
class EscapeFormulaInjection
{
    public function __construct(string $escape = "\t", array $special_chars = [])
    public function getEscape(): string
    public function getSpecialCharacters(): array
    public function escapeRecord(array $record): array
    public function __invoke(array $record): array
}
~~~


The `EscapeFormulaInjection` formats CSV records prior to their insertions in the CSV documents to reduce [CSV Formula Injection](http://georgemauer.net/2017/10/07/csv-injection.html) in imported Spreadsheet programs.

## Usage with Writer objects

The `EscapeFormulaInjection` class uses the formatter capabilities of the `Writer` object to escape formula injection.

~~~php
<?php

public function __construct(string $escape = "\t", array $special_chars = [])
public function __invoke(array $record): array
~~~

The `EscapeFormulaInjection::__construct` method takes two (2) arguments:

- the `$escape` parameter which will be used to prepend the record field, which default to `\t`;
- the `$special_chars` parameter contains additionals characters that need to be escaped. By default the following characters if found at the start of the field content will be escaped `+`,`-`,`=`,`@`;

~~~php
<?php

use League\Csv\EscapeFormulaInjection;
use League\Csv\Writer;

$writer = Writer::createFromPath('php://temp');
$writer->addFormatter(new EscapeFormulaInjection());
$writer->insertAll($iterable_data);
$writer->output('mycsvfile.csv');
//outputting a CSV Document with all CSV Formula Injection escaped
~~~

## Usage with PHP stream resources

~~~php
<?php

use League\Csv\EscapeFormulaInjection;

$resource = fopen('/path/to/my/file', 'r+');
$formatter = new EscapeFormulaInjection("`");
foreach ($iterable_data as $record) {
    fputcsv($resource, $formatter->escapeRecord($record));
}
~~~