---
layout: default
title: Loading CSV documents
---

# Document loading

Because CSV documents come in different forms we use named constructors to offer several ways to load them.

## Loading from a string

~~~php
<?php

public static AbstractCsv::createFromString(string $str): self
~~~

Creates a new object from a given string.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromString('john,doe,john.doe@example.com');
$writer = Writer::createFromString('john,doe,john.doe@example.com');
~~~

## Loading from a file path

~~~php
<?php

public static AbstractCsv::createFromPath(
	string $path,
	string $open_mode = 'r+',
	resource $context = null
): self
~~~

Creates a new object *à la* `fopen`.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;


$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$writer = Writer::createFromPath('/path/to/your/csv/file.csv', 'w');
~~~

<p class="message-info"> The <code>$open_mode</code> default to <code>r+</code> if none is supplied.</p>

## Loading from a resource stream

~~~php
<?php

public static AbstractCsv::createFromStream(resource $stream): self
~~~

Creates a new object from a stream resource.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromStream(fopen('/path/to/the/file.csv', 'r+'));
$writer = Writer::createFromStream(tmpfile());
~~~

<p class="message-warning"> The resource stream <strong>MUST</strong> be seekable otherwise an <code>RuntimeException</code> exception is thrown.</p>

## Loading from a SplFileObject object

~~~php
<?php

public static AbstractCsv::createFromFileObject(SplFileObject $obj): self
~~~

Creates a new object from a `SplFileObject` object.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Writer;

$reader = Reader::createFromFileObject(new SplFileObject('/path/to/your/csv/file.csv'));
$writer = Writer::createFromFileObject(new SplTempFileObject());
~~~

<p class="message-warning"> The <code>SplFileObject</code> <strong>MUST</strong> be seekable otherwise a <code>RuntimeException</code> exception may be thrown.</p>