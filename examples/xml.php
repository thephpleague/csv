<?php

use League\Csv\Reader;

require '../vendor/autoload.php';

$csv = Reader::createFromPath('data/prenoms.csv');
$csv->setEncodingFrom('ISO-8859-15');
$csv->setFlags(SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY);
$csv->setDelimiter(';');
//since version 7.0 only 10 rows will be converted using the query options
$csv->setOffset(1);
$csv->setLimit(10);
$csv->addSortBy(function ($row1, $row2) {
    return strcmp($row2[1], $row1[1]); //we order the result according to the number of firstname given
});
$doc = $csv->toXML('csv', 'ligne', 'cellule');
$xml = $doc->saveXML();
header('Content-Type: application/xml; charset="utf-8"');
header('Content-Length: '.strlen($xml));
die($xml);
