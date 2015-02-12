<?php

use League\Csv\Reader;
use League\Csv\Writer;

require '../vendor/autoload.php';

//we are creating a CSV from a raw string
$rawCsv = <<<EOF
Melodie;6;F;2011
Melody;7;F;2011
Melvil;13;M;2011
Melvin;9;M;2011
Menahem;6;M;2011
Mendel;7;M;2011
Meriem;8;F;2011
Merlin;8;M;2011
Meryam;7;F;2011
EOF;

$writer = Writer::createFromString($rawCsv);
//because we raw string delimiter is ";"
//the string delimiter MUST also be ";"
$writer->setDelimiter(';');

//we are creating a CSV from a raw string
$rawCsv2Merge = <<<EOF
Ben,7,M,2007
Benjamin,78,M,2007
Benoît,17,M,2007
Berenice,19,F,2007
Bertille,9,F,2007
Bianca,18,F,2007
Bilal,26,M,2007
Bilel,7,M,2007
EOF;

$csv2merge = Reader::createFromString($rawCsv2Merge);
//because we raw string delimiter is ";"
//the string delimiter MUST also be ","
$csv2merge->setDelimiter(',');

/*
 When merging multiples CSV documents don't forget to set the main CSV object
 as a `League\Csv\Writer` object with the $open_mode = 'a+' to preserve its content.
 This setting is of course not required when your main CSV object is created from String
*/

?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Merging 2 CSV documents</title>
    <link rel="stylesheet" href="example.css">
</head>
<body>
<h1>Using the League\Csv\Writer class to merge two CSV documents</h1>
<h3>The main Raw CSV</h3>
<p><em>The delimiter is a ";"</em></p>
<pre>
<?=$writer?>
</pre>
<h3>The Raw CSV to be merge</h3>
<p><em>The delimiter is a ";"</em></p>
<pre>
<?=$csv2merge?>
</pre>
<?php $writer->insertAll($csv2merge); //we are merging both documents as simple as that!!?>
<h3>The Raw CSV after merging</h3>
<p><em>Notice that after merging the data is semi-colon ";" separated</em></p>
<pre>
<?=$writer?>
</pre>
<h3>Tips</h3>
<p> When merging multiples CSV documents don't forget to set the main CSV object
 as a <code>League\Csv\Writer</code> object with the <code>$open_mode = 'a+'</code>
 to preserve its content. This setting is of course not required when your main CSV object
 is created from String</p>
</body>
</html>
