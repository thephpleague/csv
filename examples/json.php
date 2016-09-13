<?php

require '../vendor/autoload.php';

use League\Csv\Reader;
use League\Csv\Statement;

$csv = Reader::createFromPath('data/prenoms.csv');
$csv->setDelimiter(';');
$csv->setHeader(0);
$csv->setInputEncoding('ISO-8859-15');

//we limit the output to max. 10 rows
$stmt = (new Statement())->setLimit(10);
$records = $csv->select($stmt);
$res = json_encode($records, JSON_PRETTY_PRINT|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS);
if (JSON_ERROR_NONE != json_last_error()) {
    die(json_last_error_msg());
}
header('Content-Type: application/json; charset="utf-8"');
die($res);
