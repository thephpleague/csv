<?php

header('Content-type: text/html; charset=utf-8');

error_reporting(-1);
ini_set('display_errors', 1);

use League\Csv\Reader;
use League\Csv\Writer;
use lib\FilterTranscode;

require '../vendor/autoload.php';
require 'lib/FilterTranscode.php';

//BETWEEN fetch* call you CAN update/remove/add stream filter

stream_filter_register(FilterTranscode::FILTER_NAME."*", "\lib\FilterTranscode");
$reader = new Reader('data/prenoms.csv');
$reader->appendStreamFilter(FilterTranscode::FILTER_NAME."iso-8859-1:utf-8");
$reader->appendStreamFilter('string.toupper');
$reader->prependStreamFilter('string.rot13');
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

//BETWEEN insert* call you CAN NOT update/remove/add stream filter you MUST call a new Writer instance
// This is a side effect because we don't want to mess with the file cursor position during all your
// insertion
touch('/tmp/test.csv');
$writer = new Writer(new SplFileInfo('/tmp/test.csv'), 'w');
$writer->appendStreamFilter('string.toupper');
$writer->insertOne('je,suis,toto,le,héros');
$writer->appendStreamFilter('string.rot13');
$writer->insertOne('je,suis,toto,le,héros');
/*
the data is :
 - uppercased only
*/
$writer = new Writer(new SplFileInfo('/tmp/test.csv'), 'a+');
$writer->appendStreamFilter('string.toupper');
$writer->appendStreamFilter('string.rot13');
$writer->insertOne('je,suis,toto,le,héros');
/*
the data is :
 - uppercased
 - rot13 transform
*/
$reader = new Reader('/tmp/test.csv');
$reader->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
var_dump($reader->fetchAll());
