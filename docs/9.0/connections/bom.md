---
layout: default
title: BOM sequence detection and addition
---

# BOM sequences

## Detecting the BOM sequence

To improve interoperability with programs interacting with CSV, the package now provides an interface `ByteSequence` to help you detect the appropriate <abbr title="Byte Order Mark">BOM</abbr> sequence.

### Constants

The `ByteSequence` interface provides the following constants :

* `ByteSequence::BOM_UTF8` : contains `UTF-8` `BOM` sequence;
* `ByteSequence::BOM_UTF16_BE` : contains `UTF-16` `BOM` with Big-Endian sequence;
* `ByteSequence::BOM_UTF16_LE` : contains `UTF-16` `BOM` with Little-Endian sequence;
* `ByteSequence::BOM_UTF32_BE` : contains `UTF-32` `BOM` with Big-Endian sequence;
* `ByteSequence::BOM_UTF32_LE` : contains `UTF-32` `BOM` with Little-Endian sequence;

### Info::fetchBOMSequence

~~~php
function League\Csv\Info::fetchBOMSequence(string $str): ?string
~~~

The `Info::fetchBOMSequence` static method expects a string and returns the BOM sequence found at its start or null otherwise.

~~~php
use League\Csv\Info;

Info::fetchBOMSequence('hello world!'); //returns null
Info::fetchBOMSequence(Info::BOM_UTF8.'hello world!'); //returns '\xEF\xBB\xBF'
Info::fetchBOMSequence('hello world!'.Info::BOM_UTF16_BE); //returns null
~~~

This 

### bom_match

<p class="message-warning">Since <code>version 9.7</code> this function is deprecated and you are encouraged to use <code>Info::fetchBOMSequence</code> instead.</p>

~~~php
function League\Csv\bom_match(string $str): string
~~~

The `League\Csv\bom_match` function expects a string and returns the BOM sequence found at its start or an empty string otherwise.

~~~php
use League\Csv\ByteSequence;
use function League\Csv\bom_match;

bom_match('hello world!'); //returns ''
bom_match(ByteSequence::BOM_UTF8.'hello world!'); //returns '\xEF\xBB\xBF'
bom_match('hello world!'.ByteSequence::BOM_UTF16_BE); //returns ''
~~~

## Managing CSV documents BOM sequence

### Detecting the BOM sequence

~~~php
public AbstractCsv::getInputBOM(void): string
~~~

The CSV document current BOM character is detected using the `getInputBOM` method. This method returns the currently used BOM character or an empty string if none is found or recognized. The detection is done using the `bom_match` function.

~~~php
use League\Csv\Writer;

$csv = Writer::createFromPath('/path/to/file.csv');
$bom = $csv->getInputBOM();
~~~

### Setting the outputted BOM sequence

~~~php
public AbstractCsv::setOutputBOM(string $sequence): self
public AbstractCsv::getOutputBOM(void): string
~~~

- `setOutputBOM`: sets the outputting BOM you want your CSV to be associated with.
- `getOutputBOM`: get the outputting BOM you want your CSV to be associated with.

<p class="message-info">All connections classes implement the <code>ByteSequence</code> interface.</p>

~~~php
use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/file.csv', 'r');
$csv->setOutputBOM(Reader::BOM_UTF8);
$bom = $csv->getOutputBOM(); //returns "\xEF\xBB\xBF"
~~~

<p class="message-info">The default output <code>BOM</code> character is set to an empty string.</p>
<p class="message-warning">The output BOM sequence is <strong>never</strong> saved to the CSV document.</p>

### Controlling Input BOM usage

<p class="message-info">Since version <code>9.4.0</code>.</p>

If your document contains a BOM sequence by the following methods control its presence when processing it.

~~~php
AbstractCsv::skipInputBOM(): self;
AbstractCsv::includeInputBOM(): self;
AbstractCsv::isInputBOMIncluded(): bool;
~~~

- `skipInputBOM`: enables skipping the input BOM from your CSV document.
- `includeInputBOM`: preserves the input BOM from your CSV document while accessing its content.
- `isInputBOMIncluded`: tells whether skipping or including the input BOM will be done.

<p class="message-notice">By default and to avoid BC Break, the Input BOM, if present, is skipped.</p>

If your document does not contains any BOM sequence you can speed up the CSV iterator by preserving its presence which means
 that no operation to detect and remove it if present will take place.

~~~php
$raw_csv = Reader::BOM_UTF8."john,doe,john.doe@example.com\njane,doe,jane.doe@example.com\n";
$csv = Reader::createFromString($raw_csv);
$csv->setOutputBOM(Reader::BOM_UTF16_BE);
$csv->includeInputBOM();
ob_start();
$csv->output();
$document = ob_get_clean();
~~~

the returned `$document` will contains **2** BOM marker instead of one.

<p class="message-warning">If you are using a <code>stream</code> that can not be seekable you should disabled BOM skipping otherwise an <code>Exception</code> will be triggered.</p>
<p class="message-warning">The BOM sequence is never removed from the CSV document, it is only skipped from the result set.</p>
