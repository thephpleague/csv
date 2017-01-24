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

$csv = Reader::createFromPath('/path/to/your/csv/file.csv');

//get the first row, usually the CSV header
$headers = $csv->fetchOne();

//get 25 rows starting from the 11th row
$res = $csv->setOffset(10)->setLimit(25)->fetchAll();
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

//We are going to insert some data into the users table
$sth = $dbh->prepare(
	"INSERT INTO users (firstname, lastname, email) VALUES (:firstname, :lastname, :email)"
);

$csv = Reader::createFromPath('/path/to/your/csv/file.csv');
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

$reader = Reader::createFromPath('/path/to/your/csv/file.csv');

$input_bom = $reader->getInputBOM();

if ($input_bom === Reader::BOM_UTF16_LE || $input_bom === Reader::BOM_UTF16_BE) {
    $reader->appendStreamFilter('convert.iconv.UTF-16/UTF-8');
}

foreach ($reader->fetchAssoc(0) as $row) {
    echo json_encode($row, JSON_PRETTY_PRINT), PHP_EOL;
}
~~~

## More Examples

* [Selecting specific rows in the CSV](https://github.com/thephpleague/csv/blob/master/examples/extract.php)
* [Querying a CSV](https://github.com/thephpleague/csv/blob/master/examples/filtering.php)
* [Creating a CSV](https://github.com/thephpleague/csv/blob/master/examples/writing.php)
* [Merging 2 CSV documents](https://github.com/thephpleague/csv/blob/master/examples/merge.php)
* [Switching between modes from Writer to Reader mode](https://github.com/thephpleague/csv/blob/master/examples/switchmode.php)
* [Downloading the CSV](https://github.com/thephpleague/csv/blob/master/examples/download.php)
* [Converting the CSV into a Json String](https://github.com/thephpleague/csv/blob/master/examples/json.php)
* [Converting the CSV into a XML file](https://github.com/thephpleague/csv/blob/master/examples/xml.php)
* [Converting the CSV into a HTML Table](https://github.com/thephpleague/csv/blob/master/examples/table.php)
* [Using stream Filter on the CSV](https://github.com/thephpleague/csv/blob/master/examples/stream.php)

> The CSV data use for the examples are taken from [Paris Opendata](http://opendata.paris.fr/opendata/jsp/site/Portal.jsp?document_id=60&portlet_id=121)
