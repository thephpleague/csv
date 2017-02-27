---
layout: default
title: Examples
---

# Examples

## Parsing a document

A simple example to show you how to parse a CSV document.

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

$csv = Reader::createFromPath('/path/to/your/csv/file.csv');

//set the CSV header offset
$csv->setHeaderOffset(0);

//get 25 rows starting from the 11th row
$stmt = (new Statement())
    ->offset(10)
    ->limit(25)
;

$res = $csv->select($stmt)->fetchAll();
~~~

## Exporting a database table as a CSV document

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

## Importing a CSV into a database table

A simple example to show you how to import some CSV data into a database using a `PDOStatement` object

~~~php
<?php

use League\Csv\Reader;
use League\Csv\Statement;

//We are going to insert some data into the users table
$sth = $dbh->prepare(
    "INSERT INTO users (firstname, lastname, email) VALUES (:firstname, :lastname, :email)"
);

$csv = Reader::createFromPath('/path/to/your/csv/file.csv');


$stmt = (new Statement())
    // we don't want to insert the header
    ->offset(1)
;

foreach ($csv->select($stmt) as $record) {
    //Do not forget to validate your data before inserting it in your database
    $sth->bindValue(':firstname', $row[0], PDO::PARAM_STR);
    $sth->bindValue(':lastname', $row[1], PDO::PARAM_STR);
    $sth->bindValue(':email', $row[2], PDO::PARAM_STR);
    $sth->execute();
}
~~~

## Converting a UTF-16 CSV file contents to UTF-8

When importing csv files, you don't know whether the file is encoded with `UTF-8`, `UTF-16` or anything else. The below example tries to determine the encoding and convert to `UTF-8` using the iconv extension.

~~~php
<?php

use League\Csv\Reader;

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');
$reader->setHeaderOffset(0);

$input_bom = $reader->getInputBOM();

if ($input_bom === Reader::BOM_UTF16_LE || $input_bom === Reader::BOM_UTF16_BE) {
    $reader->addStreamFilter('convert.iconv.UTF-16/UTF-8');
}

echo json_encode($reader->select(), JSON_PRETTY_PRINT), PHP_EOL;
~~~