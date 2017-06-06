---
layout: default
title: CSV documents configurations
---

# Overview

~~~php
<?php

abstract class AbstractCsv implements ByteSequence
{
    public function __toString(): string
    public function addStreamFilter(string $filtername, mixed $params = null): self
    public function chunk(int $length): Generator
    public static function createFromFileObject(SplFileObject $obj): self
    public static function createFromPath(string $path, string $open_mode = 'r+', resource $context = null): self
    public static function createFromStream(resource $stream): self
    public static function createFromString(string $str): self
    public function hasStreamFilter(string $filtername): bool
    public function getDelimiter(): string
    public function getEnclosure(): string
    public function getEscape(): string
    public function getInputBOM(): string
    public function getOutputBOM(): string
    public function output(string $filename = null): int
    public function setDelimiter(string $delimiter): self
    public function setEnclosure(string $enclosure): self
    public function setEscape(string $escape): self
    public function setOutputBOM(): self
    public function supportsStreamFilter(): bool
}
~~~

## Connection type

Accessing the CSV document is done using one of the following class:

* `League\Csv\Reader` to connect on a [read only mode](/9.0/reader/)
* `League\Csv\Writer` to connect on a [write only mode](/9.0/writer/)

Both classes extend the `League\Csv\AbstractCsv` class and as such share the following features:

- [Loading CSV document](#instantiation)
- [Setting up the CSV controls characters](/9.0/connections/controls/)
- [Managing the BOM sequence](/9.0/connections/bom/)
- [Adding PHP stream filters](/9.0/connections/filters/)
- [Outputting the CSV document](/9.0/connections/output/)

## OS specificity

If your CSV document was created or is read on a Macintosh computer, add the following lines before using the library to help [PHP detect line ending in Mac OS X](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

~~~php
<?php

if (!ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}

//the rest of the code continues here...
~~~
