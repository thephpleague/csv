---
layout: default
---

# Overview

[![Author](//img.shields.io/badge/author-@nyamsprod-blue.svg?style=flat-square)](//twitter.com/nyamsprod)
[![Source Code](//img.shields.io/badge/source-league/csv-blue.svg?style=flat-square)](//github.com/thephpleague/csv)
[![Latest Version](//img.shields.io/github/release/thephpleague/csv.svg?style=flat-square)](//github.com/thephpleague/csv/releases)
[![Software License](//img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](//github.com/thephpleague/csv/blob/master/LICENSE)<br>
[![Build Status](//img.shields.io/travis/thephpleague/csv/master.svg?style=flat-square)](//travis-ci.org/thephpleague/csv)
[![Coverage Status](//img.shields.io/scrutinizer/coverage/g/thephpleague/csv.svg?style=flat-square)](//scrutinizer-ci.com/g/thephpleague/csv/code-structure)
[![Quality Score](//img.shields.io/scrutinizer/g/thephpleague/csv.svg?style=flat-square)](//scrutinizer-ci.com/g/thephpleague/csv)
[![Total Downloads](//img.shields.io/packagist/dt/league/csv.svg?style=flat-square)](//packagist.org/packages/league/csv)

`Csv` is a simple library to ease CSV parsing, writing and filtering in
PHP. The goal of the library is to be powerful while remaining lightweight,
by utilizing PHP native classes whenever possible.

## Examples

### Parsing a document

A simple example to show you how to parse a CSV document.

~~~php
<?php

use League\Csv\Reader;

$csv = Reader::createFromPath('/path/to/your/csv/file.csv', 'r');

//get the first row, usually the CSV header
$headers = $csv->fetchOne();

//get 25 rows starting from the 11th row
$res = $csv->setOffset(10)->setLimit(25)->fetchAll();
~~~

### Exporting a database table as a CSV document

A simple example to show you how to create and download a CSV from a `PDOStatement` object

~~~php
<?php

use League\Csv\Writer;

//we fetch the info from a DB using a PDO object
$sth = $dbh->prepare(
    "SELECT firstname, lastname, email FROM users LIMIT 200"
);
//because we don't want to duplicate the data for each row
// PDO::FETCH_NUM could also have been used
$sth->setFetchMode(PDO::FETCH_ASSOC);
$sth->execute();

//we create the CSV into memory
$csv = Writer::createFromFileObject(new SplTempFileObject());

//we insert the CSV header
$csv->insertOne(['firstname', 'lastname', 'email']);

// The PDOStatement Object implements the Traversable Interface
// that's why Writer::insertAll can directly insert
// the data into the CSV
$csv->insertAll($sth);

// Because you are providing the filename you don't have to
// set the HTTP headers Writer::output can
// directly set them for you
// The file is downloadable
$csv->output('users.csv');
die;
~~~

### Importing a CSV into a database table

A simple example to show you how to import some CSV data into a database using a `PDOStatement` object

~~~php
<?php

use League\Csv\Reader;

//We are going to insert some data into the users table
$sth = $dbh->prepare(
    "INSERT INTO users (firstname, lastname, email) VALUES (:firstname, :lastname, :email)"
);

$csv = Reader::createFromPath('/path/to/your/csv/file.csv', 'r');
$csv->setOffset(1); //because we don't want to insert the header
$nbInsert = $csv->each(function ($row) use (&$sth) {
    //Do not forget to validate your data before inserting it in your database
    $sth->bindValue(':firstname', $row[0], PDO::PARAM_STR);
    $sth->bindValue(':lastname', $row[1], PDO::PARAM_STR);
    $sth->bindValue(':email', $row[2], PDO::PARAM_STR);

    return $sth->execute(); //if the function return false then the iteration will stop
});
~~~

## Converting a UTF-16 CSV file contents to UTF-8

When importing csv files, you don't know whether the file is encoded with `UTF-8`, `UTF-16` or anything else.
The below example tries to determine the encoding and convert to `UTF-8` using the iconv extension.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv', 'r');

$input_bom = $reader->getInputBOM();

if ($input_bom === Reader::BOM_UTF16_LE || $input_bom === Reader::BOM_UTF16_BE) {
    $reader->appendStreamFilter('convert.iconv.UTF-16/UTF-8');
}

foreach ($reader->fetchAssoc(0) as $row) {
    echo json_encode($row, JSON_PRETTY_PRINT), PHP_EOL;
}
~~~

## More Examples

- [Selecting specific rows in the CSV](https://github.com/thephpleague/csv/tree/8.2.3/examples/extract.php)
- [Querying a CSV](https://github.com/thephpleague/csv/tree/8.2.3/examples/filtering.php)
- [Creating a CSV](https://github.com/thephpleague/csv/tree/8.2.3/examples/writing.php)
- [Merging 2 CSV documents](https://github.com/thephpleague/csv/tree/8.2.3/examples/merge.php)
- [Switching between modes from Writer to Reader mode](https://github.com/thephpleague/csv/tree/8.2.3/examples/switchmode.php)
- [Downloading the CSV](https://github.com/thephpleague/csv/tree/8.2.3/examples/download.php)
- [Converting the CSV into a Json String](https://github.com/thephpleague/csv/tree/8.2.3/examples/json.php)
- [Converting the CSV into a XML file](https://github.com/thephpleague/csv/tree/8.2.3/examples/xml.php)
- [Converting the CSV into a HTML Table](https://github.com/thephpleague/csv/tree/8.2.3/examples/table.php)
- [Using stream Filter on the CSV](https://github.com/thephpleague/csv/tree/8.2.3/examples/stream.php)

> The CSV data use for the examples are taken from [Paris Opendata](//opendata.paris.fr/opendata/jsp/site/Portal.jsp?document_id=60&portlet_id=121)
