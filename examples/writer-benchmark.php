<?php

//To work the benchmark need PHP5.5+ has it relies on PHP generator

use League\Csv\Writer;

require '../vendor/autoload.php';

function generateRawData($start, $end)
{
    for ($i = $start; $i < $end; $i++) {
        $index = $i;
        yield [
            'cell--'.($index),
            'cell--'.($index+1),
            'cell--'.($index+2),
        ];
    }
}

$start = microtime(true);
$nbrows = 200000;
$csv = Writer::createFromPath('result.csv', 'w'); //to work make sure you have the write permission
$csv->insertAll(generateRawData(0, $nbrows));
$duration = microtime(true) - $start;
$memory = memory_get_peak_usage(true);
echo 'adding '. $nbrows, ' rows in ', $duration, ' seconds using ', $memory, ' bytes', PHP_EOL;
die(0);
