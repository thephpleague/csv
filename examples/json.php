<?php

use League\Csv\Reader;

require '../vendor/autoload.php';

$inputCsv = Reader::createFromPath('data/prenoms.csv');
$inputCsv->setDelimiter(';');
$inputCsv->setEncodingFrom('ISO-8859-15');
$inputCsv->setFlags(SplFileObject::DROP_NEW_LINE|SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
$res = json_encode($inputCsv, JSON_PRETTY_PRINT|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS);
if (JSON_ERROR_NONE != json_last_error()) {
    die(json_last_error_msg());
}
header('Content-Type: application/json; charset="utf-8"');
die($res);
