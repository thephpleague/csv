<?php

error_reporting(-1);
ini_set('display_errors', 1);

use League\Csv\Reader;
use League\Csv\Writer;
use lib\UppercaseFilter;

require '../vendor/autoload.php';
require 'lib/UppercaseFilter.php'; //a class that implements the \League\Csv\Stream\FilterInterface

$csv = new Reader('data/prenoms.csv', 'r', new UppercaseFilter);
$csv->setDelimiter(';');
$res = $csv->setLimit(5)->fetchAll();
var_dump($res); //the csv is transform into an uppercase only CSV on the fly

$csv = new Writer('/tmp/test.csv', 'w', new UppercaseFilter);
$csv->insertOne(['prenoms', 'nombre', 'sexe']);

$csv = new Reader('/tmp/test.csv');
echo $csv; //test.csv document only contains uppercase characters.
