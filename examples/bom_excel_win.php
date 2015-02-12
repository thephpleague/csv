<?php

use League\Csv\Reader;

require '../vendor/autoload.php';

$csv = Reader::createFromPath(__DIR__.'/data/prenoms.csv');
if (Reader::BOM_UTF8 != $reader->getInputBOM()) {
    $reader->setOutputBOM(Reader::BOM_UTF8);
}
$csv->output('test.csv');
