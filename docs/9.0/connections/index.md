---
layout: default
title: CSV documents configurations
---

# Overview

## Connection type

Accessing the CSV document is done using one of the following class:

* `League\Csv\Reader` to connect on a [read only mode](/9.0/reader/)
* `League\Csv\Writer` to connect on a [write only mode](/9.0/writer/)

Both classes extend the `League\Csv\AbstractCsv` class and as such share the following features:

- [Loading CSV document](/9.0/connections/instantiation/)
- [Setting up the CSV controls characters](/9.0/connections/controls/)
- [Managing the BOM sequence](/9.0/connections/bom/)
- [Adding PHP stream filters](/9.0/connections/filters/)
- [Outputting the CSV document](/9.0/connections/output/)

## OS specificity

If your CSV document was created or is read on a Macintosh computer, add the following lines before using the library to help [PHP detect line ending in Mac OS X](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

~~~php
if (!ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}

//the rest of the code continues here...
~~~

## Exceptions

The default exception class thrown while using this library is `League\Csv\Exception` which extends PHP `Exception` class.

~~~php
use League\Csv\Exception;
use League\Csv\Reader;

try {
    $csv = Reader::createFromPath('/path/to/file.csv', 'r');
    $csv->setDelimiter('toto');
} catch (Exception $e) {
    echo $e->getMessage(), PHP_EOL;
}
~~~

When using a non-seekable `SplFileObject`, a `RuntimeException` is thrown instead of a `League\Csv\Exception` when using features that requires a seekable CSV document. In the following example a seekable CSV document is required to update the inserted newline.

~~~php
use League\Csv\Exception;
use League\Csv\Writer;

try {
    $csv = Writer::createFromFileObject(new SplFileObject('php://output', 'w');
    $csv->setNewline("\r\n");
    $csv->insertOne(["foo", "bar"]);
} catch (Exception | RuntimeException $e) {
    echo $e->getMessage(), PHP_EOL;
}

//in order to change the CSV document newline a seekable CSV document is required
~~~