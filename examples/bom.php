<?php

error_reporting(-1);
ini_set('display_errors', '1');

use League\Csv\Reader;
use League\Csv\Writer;
use lib\FilterTranscode;

require '../vendor/autoload.php';

stream_filter_register(FilterTranscode::FILTER_NAME."*", "\lib\FilterTranscode");

$csv = Reader::createFromPath(__DIR__.'/data/prenoms.csv');
$csv->setBOMOnOutput(Reader::BOM_UTF16_LE);
$csv->appendStreamFilter(FilterTranscode::FILTER_NAME."UTF-8:UTF-16LE");
$csv->output('test.csv');
