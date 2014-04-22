<?php

use League\Csv\Reader;
use lib\FilterTranscode;

require '../vendor/autoload.php';
require 'lib/FilterTranscode.php';

stream_filter_register(FilterTranscode::$name."*", "FilterTranscode");

$reader = new Reader('path/to/chinese/file.csv');
$reader->appendStreamFilter(FilterTranscode::$name."big5:utf8");
$reader->setOffset(1);
$reader->setLimit(10);
$res = $reader->fetchAll();

print_r($res); //the data is transcoded by the Stream Filter from BIG5 to UTF-8
