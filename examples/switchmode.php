<?php

use League\Csv\Writer;

require '../vendor/autoload.php';

$rawCsv = <<<EOF
Anatole;31;M;2004
Andre;13;M;2004
Andrea;33;F;2004
Andrea;20;F;2004
Andy;19;M;2004
Ange;15;M;2004
Angela;9;F;2004
Kelyan;6;M;2011
Kenan;11;M;2011
Kenny;8;M;2011
Kenza;33;F;2011
Kenzi;5;M;2011
Angelique;13;F;2004
Angelo;9;M;2004
Ania;7;F;2004
Anis;33;M;2004
Anissa;21;F;2004
Anna;117;F;2004
Annabelle;14;F;2004
Anne;10;F;2004
Anouk;48;F;2004
Anthony;41;M;2004
Antoine;248;M;2004
Anton;16;M;2004
EOF;

 //we are creating a CSV from a raw string
$writer = Writer::createFromString($rawCsv);

//because we raw string delimiter is ";"
//the string delimiter MUST also be ";"
$writer->setDelimiter(';');

$writer->insertOne('Ben;7;M;2004');
$writer->insertAll([
    'Benjamin;118;M;2004',
    ['Benoit', '6', 'M', '2004'],
]);

//we create a Reader object from the Writer object
$reader = $writer->newReader();
$names = $reader
    ->addSortBy(function ($row1, $row2) {
        return strcmp($row1[0], $row2[0]); //we are sorting the name
    })
    ->fetchColumn(); //we only return the name column
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>League\Csv\Writer and League\Csv\Reader switching mode</title>
    <link rel="stylesheet" href="example.css">
</head>
<body>
<h1>Using createFromString method and converting the League\Csv\Writer into a League\Csv\Reader</h1>
<h3>The table representation of the csv to be save</h3>
<?=$writer->toHTML();?>
<h3>The Raw CSV as it will be saved</h3>
<pre>
<?=$writer?>
</pre>
<h3>Here's the firstname ordered list</h3>
<ol>
<?php foreach ($names as $firstname) : ?>
    <li><?=$firstname?>
    <?php
endforeach;
?>
</ol>
</body>
</html>
