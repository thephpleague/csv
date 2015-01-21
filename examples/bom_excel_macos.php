<?php

error_reporting(-1);
ini_set('display_errors', '1');

use League\Csv\Reader;
use League\Csv\Writer;
use lib\FilterTranscode;

require '../vendor/autoload.php';

//the current CSV is UTF-8 encoded with a ";" delimiter
$csv = Reader::createFromPath(__DIR__.'/data/prenoms.csv');

//To be open in MacOS Excel a CSV must
// - be encoded in UTF16-LE
// - use tab delimiter

//let's convert the CSV to be UTF-16_LE encoded with a tab delimiter.

//we must use `createFromPath` to be able to use the stream capability
//we must use a temp file to be able to rewind the cursor file without loosing
//the modification
$writer = Writer::createFromPath('/tmp/toto.csv', 'w');
$writer->setNullHandlingMode(Writer::NULL_AS_EMPTY);

// we register a Transcode Filter class to convert the CSV into the proper encoding charset
stream_filter_register(FilterTranscode::FILTER_NAME."*", "\lib\FilterTranscode");
$writer->appendStreamFilter(FilterTranscode::FILTER_NAME."UTF-8:UTF-16LE");

//we set the tab as the delimiter character
$writer->setDelimiter("\t");

//we insert csv data
$writer->insertAll($csv);

//let's switch to the Reader object
//Writer::output will failed because of the open mode
$reader = $writer->newReader();
//detect and adjust the output BOM to be used
if (Reader::BOM_UTF16_LE != $reader->getInputBOM()) {
    $reader->setOutputBOM(Reader::BOM_UTF16_LE);
}
//let's add the corresponding BOM
$reader->output('toto le héros.csv');
