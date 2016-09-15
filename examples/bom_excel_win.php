<?php

use League\Csv\Reader;

require '../vendor/autoload.php';

$csv = Reader::createFromPath(__DIR__.'/data/prenoms.csv');
$csv->setOutputBOM(Reader::BOM_UTF8);
//if the current BOM setting differs with
//the one supplied it will be automatically updated
$csv->output('test.csv');
