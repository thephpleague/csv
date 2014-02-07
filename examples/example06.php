<?php

error_reporting(-1);
ini_set('display_errors', 'On');

use Bakame\Csv\Writer;

require '../vendor/autoload.php';

$rawCsv = <<<EOF
Anatole;31;M;2004
Andre;13;M;2004
Andrea;33;F;2004
Andrea;20;F;2004
Andy;19;M;2004
Ange;15;M;2004
Angela;9;F;2004
Angèle;29;F;2004
Angelina;8;F;2004
Angelina;7;F;2004
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

$writer = Writer::createFromString($rawCsv); //we are creating a CSV from a raw string
$writer->setDelimiter(';');
$writer->insertOne('Ben;7;M;2004'); //because we specified the delimiter to ";" the string delimiter MUST also be ";"
$writer->insertAll([
    'Benjamin;118;M;2004',
    ['Benoit', '6', 'M', '2004'] //because we a inserting an array the delimiter is not necessary
]);

//we create a Reader object from the Writer object to filter the resulting CSV
$reader = $writer->getReader();
$names = $reader
    ->setSortBy(function ($row1, $row2) {
        return strcmp($row1[0], $row2[0]); //we are sorting the name
    })
    ->fetchCol(0); //we only return the name column
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Example 2</title>
</head>
<body>
<h1>Example 4: Using Writer object with Strings</h1>
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
