---
layout: default
title: Loading CSV documents
---

# Document loading

Because CSV documents come in different forms we use named constructors to offer several ways to load them.

<p class="message-warning">Since version <code>9.1.0</code> non seekable CSV documents can be used but <strong>exceptions will be thrown if features requiring seekable CSV document are used.</strong></p>

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


$reader = Reader::createFromPath('/path/to/your/csv/file.csv', 'r');
$writer = Writer::createFromPath('/path/to/your/csv/file.csv', 'w');
~~~

<div class="message-notice">
Starting with version <code>9.1.0</code>, <code>$open_mode</code> default to:
<ul>
<li><code>r+</code> for the <code>Writer</code> class</li>
<li><code>r</code> for the <code>Reader</code> class</li>
</ul>
</div>

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

<p class="message-notice">Prior to version <code>9.1.0</code>, the method was throwing <code>League\Csv\Exception</code> for non-seekable stream resource.</p>

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