---
layout: default
title: BOM sequence detection and addition
---

# BOM sequences

## Detecting the BOM sequence

To improve interoperability with programs interacting with CSV, the package now provides an enum `Bom` to help you detect the appropriate <abbr title="Byte Order Mark">BOM</abbr> sequence.

### BOM definition

The `Bom` enum provides the following value :

- `Bom::Utf8` : which handles the `UTF-8` `BOM` sequence;
- `Bom::utf16Be` : which handles the `UTF-16` `BOM` with Big-Endian sequence;
- `Bom::utf16Le` : which handles the `UTF-16` `BOM` with Little-Endian sequence;
- `Bom::utf32Be` : which handles the `UTF-32` `BOM` with Big-Endian sequence;
- `Bom::utf32Le` : which handles the `UTF-32` `BOM` with Little-Endian sequence;

### Detecting the BOM from a sequence of characters

```php
Bom::fromSequence(mixed $sequence): Bom
Bom::tryFromSequence(mixed $sequence): ?Bom
```

The `Bom::tryFromSequence` static method expects any type that can be converted to a string and returns the BOM sequence found at its start as a new `Bom` instance or null otherwise.
Instead of returning `null` the `Bom::fromSequence` will throw a `ValueError` exception.

```php
use League\Csv\Bom;

Bom::tryFromSequence('hello world!'); //returns null
Bom::tryFromSequence(Bom::Utf8->value.'hello world!'); //returns Bom::Utf8
Bom::tryFromSequence('hello world!'.Bom::Utf16Le->value); //returns null
```

### Detecting the BOM from the encoding name

```php
Bom::fromEncoding(string $encoding): Bom
Bom::tryFromEncoding(mixed $encoding): ?Bom
```

The `Bom::tryFromEncoding` static method expects a valid encoding name as a string if the encoding name after filtering is find a new `Bom` instance or `null` is returned otherwise.
Instead of returning `null` the `Bom::fromEncoding` will throw a `ValueError` exception.

```php
use League\Csv\Bom;

Bom::tryFromEncoding('utf16'); //returns Bom::Utf16Be
Bom::tryFromEncoding('u_t_F--8'); //returns Bom::Utf8
Bom::tryFromEncoding('foobar'); //returns null
```

### Accessing the BOM properties

Once you have a valid `Bom` instance you can access:

- its actual value via the Enum `value` property;
- its length via the `length()` method;
- the represented encoding name via the `encoding()` method;
- `isUtf8`, `isUtf16`, `isUtf32` method to quicky know which encoding family is used

```php
use League\Csv\Bom;

$bom = Bom::tryFromEncoding('utf16'); //returns Bom::Utf16Be
$bom->value; //returns "\xFE\xFF"
$bom->length(); //returns 2
$bom->encoding(); //returns 'UTF-16BE'
$bom->isUtf8(); //returns false
$bom->isUtf16(); //returns true
$bom->isUtf32(); //returns false
```

## Deprecated features

### bom_match and Info::fetchBOMSequence

<p class="message-warning">Since version <code>9.7</code> <code>bom_match</code> is deprecated and you are encouraged to use <code>Info::fetchBOMSequence</code> instead.</p>
<p class="message-warning">Since version <code>9.16</code> <code>Info::fetchBOMSequence</code> is deprecated and you are encouraged to use <code>Bom::tryFromSequence</code> instead.</p>

```php
function League\Csv\bom_match(string $str): string
League\Csv\Info::fetchBOMSequence(string $str): ?string
```

The `League\Csv\bom_match` function expects a string and returns the BOM sequence found at its start or an empty string otherwise.

```php
use League\Csv\ByteSequence;
use function League\Csv\bom_match;

bom_match('hello world!'); //returns ''
bom_match(ByteSequence::BOM_UTF8.'hello world!'); //returns '\xEF\xBB\xBF'
bom_match('hello world!'.ByteSequence::BOM_UTF16_BE); //returns ''
```

## Managing CSV documents BOM sequence

### Detecting the BOM sequence

```php
public AbstractCsv::getInputBOM(void): string
```

The CSV document current BOM character is detected using the `getInputBOM` method. This method returns the currently used BOM character or an empty string if none is found or recognized. The detection is done using the `Bom::tryFromSequence` static method.

```php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv');
$bom = $csv->getInputBOM();
```

### Setting the outputted BOM sequence

```php
public AbstractCsv::setOutputBOM(Bom|string|null $sequence): self
public AbstractCsv::getOutputBOM(void): string
```

- `setOutputBOM`: sets the outputting BOM you want your CSV to be associated with.
- `getOutputBOM`: gets the outputting BOM you want your CSV to be associated with.

<p class="message-info">All connections classes implement the <code>ByteSequence</code> interface.</p>
<p class="message-notice">Since version <code>9.16</code> the <code>$sequence</code> parameter also accepts a <code>Bom</code> instance or <code>null</code>.</p>

```php
use League\Csv\Bom;
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setOutputBOM(Bom::Utf8);
$bom = $csv->getOutputBOM(); //returns "\xEF\xBB\xBF"
```

<p class="message-info">The default output <code>BOM</code> character is set to an empty string.</p>
<p class="message-warning">The output BOM sequence is <strong>never</strong> saved to the CSV document.</p>

### Controlling Input BOM usage

<p class="message-info">Since version <code>9.4.0</code>.</p>

If your document contains a BOM sequence the following methods control its presence when processing it.

```php
AbstractCsv::skipInputBOM(): self;
AbstractCsv::includeInputBOM(): self;
AbstractCsv::isInputBOMIncluded(): bool;
```

- `skipInputBOM`: enables skipping the input BOM from your CSV document.
- `includeInputBOM`: preserves the input BOM from your CSV document while accessing its content.
- `isInputBOMIncluded`: tells whether skipping or including the input BOM will be done.

<p class="message-notice">By default, and to avoid BC Break, the Input BOM, if present, is skipped.</p>

If your document does not contain any BOM sequence you can speed up the CSV iterator by preserving its presence, which means that no operation to detect and remove it if present will take place.

```php
$raw_csv = Bom::Utf8->value."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
$csv = Reader::createFromString($raw_csv);
$csv->setOutputBOM(Bom::Utf16Le);
$csv->includeInputBOM();
ob_start();
$csv->output();
$document = ob_get_clean();
```

The returned `$document` will contain **2** BOM markers instead of one.

<p class="message-warning">If you are using a <code>stream</code> that can not be seekable you should disable BOM skipping, otherwise an <code>Exception</code> will be triggered.</p>
<p class="message-warning">The BOM sequence is never removed from the CSV document, it is only skipped from the result set.</p>

### Skipping the BOM Sequence

<p class="message-info">Since version <code>9.9.0</code> you can skip the BOM sequence using the <a href="/9.0/interoperability/encoding/">CharsetConverter</a> filter</p>
