---
layout: default
title: CSV Formula Injection
---

# Prevents CSV Formula Injection

<p class="message-notice">Available since <code>version 9.1.0</code></p>

The `EscapeFormula` Formatter formats CSV records to reduce [CSV Formula Injection](http://georgemauer.net/2017/10/07/csv-injection.html) in imported Spreadsheet programs.

<p class="message-warning">since <code>version 9.7.4</code> The default values from the class constructor where updated to comply with the latest recommendations from OWASP regarding <a href="https://owasp.org/www-community/attacks/CSV_Injection" target="_blank">CSV injection</a>.  
As this is a security fix, the BC break should be minimal.</p>

## Usage with Writer objects

The `EscapeFormula` class uses the formatter capabilities of the `Writer` object to escape formula injection.

```php
public function __construct(string $escape = "'", array $special_chars = [])
public function __invoke(array $record): array
```

The `EscapeFormula::__construct` method takes two (2) arguments:

- the `$escape` parameter which will be used to prepend the record field, which default to `'`;
- the `$special_chars` parameter which is an `array` with additional characters that need to be escaped. By default the following characters if found at the start of any record field content will be escaped `+`,`-`,`=`,`@`, `\t`, `\r`;
- for more information see [OWASP - CSV Injection](https://owasp.org/www-community/attacks/CSV_Injection)

```php
use League\Csv\EscapeFormula;
use League\Csv\Writer;

$writer = Writer::createFromPath('php://temp', 'r+');
$writer->addFormatter(new EscapeFormula());
$writer->insertOne(['2', '2017-07-25', 'Important Client', '=2+5', 240, null]);
$writer->getContent();
//outputting a CSV Document with all CSV Formula Injection escaped
//"2,2017-07-25,\"Important Client\",\"\t=2+5\",240,\n"
```

## Usage with PHP stream resources

You can use the `EscapeFormula` to format your records before calling `fputcsv` or `SplFileObject::fputcsv`.

```php
use League\Csv\EscapeFormula;

$resource = fopen('/path/to/my/file', 'r+');
$formatter = new EscapeFormula("`");
foreach ($iterable_data as $record) {
    fputcsv($resource, $formatter->escapeRecord($record));
}
```

<p class="message-warning">Even though we provide the <code>EscapeFormula</code> formatter I must stress out that this is in no way a bulletproof method. This prevention mechanism only works if <strong>you know how the CSV export will be consumed</strong>. In any other cases, you are better of leaving the filtering to the consuming client and report any found security concern to their respective security channel.</p>
