---
layout: default
title: Exceptions
---

# Exceptions

Specific exceptions are thrown for errors occuring while using the library.

## Default interface

All exceptions thrown implements the `League\Csv\Exception\CsvException` interface.

~~~php
<?php

use League\Csv\Exception\CsvException;
use League\Csv\Reader;

try {
    $csv = Reader::createFromPath('/path/to/file.csv');
    $csv->setDelimiter('toto');
} catch (CsvException $e) {
    echo $e->getMessage(), PHP_EOL;
}
~~~

## Logic exceptions

### While setting properties

- A `LengthException` exception is triggered by the CSV character control methods if the submitted character length is not equal to `1`.
- A `OutOfRangeException` exception is triggered by the library if the submitted integer is not an acceptable value for a given method.
- A `RuntimeException` exception can also be triggered if the filename cannot be opened like [SplFileObject](http://php.net/manual/en/splfileobject.construct.php).

~~~php
<?php

use League\Csv\Reader;

try {
    $csv = Reader::createFromPath('/path/to/file.csv'); //may trigger a RuntimeException
    $csv->setDelimiter('toto'); //may trigger a LengthException
} catch (LengthException $e) {
    echo $e->getMessage(), PHP_EOL;
} catch (RuntimeException $e) {
    echo $e->getMessage(), PHP_EOL;
}

// in PHP 7.1

try {
    $csv = Reader::createFromPath('/path/to/file.csv');
    $csv->setDelimiter('toto');
} catch (LengthException | RuntimeException $e) {
    echo $e->getMessage(), PHP_EOL;
}
~~~

### While using PHP stream support

If you try to use PHP stream filtering features on an CSV objects which can't use them, a `LogicException` is triggered.

~~~php
<?php

use League\Csv\Writer;

try {
    $csv = Writer::createFromFileObject(new SplTempFileObject());
    $csv->addStreamFilter('string.toupper');
} catch (LogicException $e) {
    echo $e->getMessage(), PHP_EOL;
}
~~~

## Runtime exceptions

### While manipulating records

During reading or writing records `RuntimeException` exception can be thrown if an error occurs.

~~~php
<?php

use League\Csv\Reader;

try {
    $csv = Reader::createFromPath('/path/to/file.csv');
    $csv->setHeaderOffset(1000); //valid offset but the CSV does not contain 1000 records
    $header = $csv->getHeader(); //triggers a Exception
} catch (RuntimeException $e) {
    echo $e->getMessage(), PHP_EOL;
}
~~~

### While inserting records

On error when using the `Writer` class to add new records to your CSV document a `League\Csv\Exception\InsertionException` is thrown. This exception class

- extends PHP SPL's `RuntimeException`
- implements `League\Csv\Exception\CsvException`
- provides additional public methods:
    - `InsertionException::getData` which returns the record responsible for triggering the exception.
    - `InsertionException::getName` which returns the validator registered name which  triggered the exception or an empty string otherwise.

~~~php
<?php

use League\Csv\Writer;
use League\Csv\Exception\InsertionException;

try {
    $writer->insertOne(['john', ['doe'], 'john.doe@example.com']);
} catch (InsertionException $e) {
    echo $e->getName(); //display ''
    $e->getData();//will return the invalid data ['john', ['doe'], 'john.doe@example.com']
}
~~~