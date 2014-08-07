<?php

header('Content-type: text/html; charset=utf-8');

error_reporting(-1);
ini_set('display_errors', 1);

use League\Csv\Reader;
use League\Csv\Writer;
use lib\FilterTranscode;

require '../vendor/autoload.php';  //to load the library

//you must register your class for it to be usable by the CSV Lib
stream_filter_register(FilterTranscode::FILTER_NAME."*", "\lib\FilterTranscode");

//BETWEEN fetch* call you CAN update/remove/add stream filter
$reader = new Reader(__DIR__.'/data/prenoms.csv');
$reader->appendStreamFilter(FilterTranscode::FILTER_NAME."iso-8859-1:utf-8");
$reader->appendStreamFilter('string.toupper');
$reader->appendStreamFilter('string.rot13');
$reader->setDelimiter(';');
$reader->setOffset(6);
$reader->setLimit(3);
$res = $reader->fetchAssoc(['Prenom', 'Occurences', 'Sexe', 'Annee']);
/*
the data is :
 - transcoded by the Stream Filter from ISO-8859-1 to UTF-8
 - uppercased
 - rot13 transform
*/
var_dump($res);

$reader->removeStreamFilter('string.toupper');
$reader->setOffset(6);
$reader->setLimit(3);
$res = $reader->fetchAssoc(['Prenom', 'Occurences', 'Sexe', 'Annee']);
/*
the data is :
 - transcoded by the Stream Filter from ISO-8859-1 to UTF-8
 - rot13 transform
*/
var_dump($res);

// because of the limited support for stream filters with the SplFileObject
// BETWEEN insert call **YOU CAN NOT UPDATE** stream filters

touch('/tmp/test.csv');
$writer = new Writer(new SplFileInfo('/tmp/test.csv'), 'w');
$writer->appendStreamFilter('string.toupper');
$writer->insertOne('je,suis,toto,le,héros');
$writer->appendStreamFilter('string.rot13'); //this stream won't be apploed
$writer->insertOne('je,suis,toto,le,héros');
/*
the inserted data is only uppercased only
*/

$writer = new Writer('/tmp/test.csv', 'a+');
$writer->appendStreamFilter('string.toupper');
$writer->appendStreamFilter(FilterTranscode::FILTER_NAME."iso-8859-1:utf-8");
$writer->appendStreamFilter('string.rot13');
$writer->removeStreamFilter(FilterTranscode::FILTER_NAME."iso-8859-1:utf-8");
$writer->insertOne('je,suis,toto,le,héros');
/*
the inserted data is :
 - uppercased
 - rot13 transform
*/
$reader = new Reader('/tmp/test.csv');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
var_dump($reader->fetchAll());
