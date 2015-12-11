<?php

header('Content-type: text/html; charset=utf-8');

use League\Csv\Reader;
use League\Csv\Writer;
use lib\FilterTranscode;

require '../vendor/autoload.php';  //load all the necessary classes when using composer install in dev mode

//you must register your class for it to be usable by the CSV Lib
stream_filter_register(FilterTranscode::FILTER_NAME."*", "\lib\FilterTranscode");
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>League\Csv Stream Filter API example</title>
    <link rel="stylesheet" href="example.css">
</head>
<body>

<p>Stream Filters can only be used if the <code><strong>isActiveStreamFilter</strong></code> method returns <code><strong>true</strong></code></p>
<h3>Using the Reader class</h3>

<pre><code>$reader = Reader::createFromPath(__DIR__.'/data/prenoms.csv');
if ($reader->isActiveStreamFilter()) {
    $reader->appendStreamFilter(FilterTranscode::FILTER_NAME."iso-8859-1:utf-8");
    $reader->appendStreamFilter('string.toupper');
    $reader->appendStreamFilter('string.rot13');
}
$reader->setDelimiter(';');
$reader->setOffset(6);
$reader->setLimit(3);
$res = $reader->fetchAssoc(['Prenom', 'Occurences', 'Sexe', 'Annee']);
</code></pre>

<p>the data is :</p>
<ol>
<li>transcoded by the Stream Filter from ISO-8859-1 to UTF-8</li>
<li>uppercased</li>
<li>rot13 transformed</li>
</ol>
<?php

//BETWEEN fetch* call you CAN update/remove/add stream filter
$reader = Reader::createFromPath(__DIR__.'/data/prenoms.csv');
if ($reader->isActiveStreamFilter()) {
    $reader->appendStreamFilter(FilterTranscode::FILTER_NAME."iso-8859-1:utf-8");
    $reader->appendStreamFilter('string.toupper');
    $reader->appendStreamFilter('string.rot13');
}
$reader->setDelimiter(';');
$reader->setOffset(6);
$reader->setLimit(3);
$res = $reader->fetchAssoc(['Prenom', 'Occurences', 'Sexe', 'Annee']);

var_dump(iterator_to_array($res, false));
?>
<p>Let's remove the <code><strong>string.toupper</strong></code> stream filter</p>
<pre><code>if ($reader->isActiveStreamFilter()) {
    $reader->removeStreamFilter('string.toupper');
}
$reader->setOffset(6);
$reader->setLimit(3);
$res = $reader->fetchAssoc(['Prenom', 'Occurences', 'Sexe', 'Annee']);

var_dump(iterator_to_array($res, false));</code></pre>

<?php
if ($reader->isActiveStreamFilter()) {
    $reader->removeStreamFilter('string.toupper');
}
$reader->setOffset(6);
$reader->setLimit(3);
$res = $reader->fetchAssoc(['Prenom', 'Occurences', 'Sexe', 'Annee']);

var_dump(iterator_to_array($res, false));
?>
<h3>Using the Writer class</h3>

<p><strong>You can not add/remove/update stream filters between inserts calls</strong>
<pre><code>$writer = Writer::createFromPath('/tmp/test.csv', 'w');
if ($writer->isActiveStreamFilter()) {
    $writer->appendStreamFilter('string.toupper');
}
$writer->insertOne('je,suis,toto,le,héros');
</code></pre>
<?php
$writer = Writer::createFromPath('/tmp/test.csv', 'w');
if ($writer->isActiveStreamFilter()) {
    $writer->appendStreamFilter('string.toupper');
}
$writer->insertOne('je,suis,toto,le,héros');
?>
<p>When the first insert call is done... the stream filter status is
frozen and can no longer be updated !! Any added row will be uppercased only no matter what.</p>
<?php
if ($writer->isActiveStreamFilter()) {
    $writer->appendStreamFilter('string.rot13');
    $writer->removeStreamFilter('string.toupper');
}
$writer->insertOne('je,suis,toto,le,héros');
?>

<p>To update the filters you need to:</p>
<ol>
<li> create a new Writer object <em>don't forget to update the <code>$open_mode</code></em></li>
<li>apply the new stream filters</li>
</ol>

<pre><code>$writer = $writer->newWriter('a+');
if ($writer->isActiveStreamFilter()) {
    $writer->appendStreamFilter('string.rot13');
    $writer->prependStreamFilter('string.strip_tags');
}
$writer->insertAll([
    'je,suis,toto,le,héros',
    'je,&lt;strong&gt;suis&lt;/strong&gt;,toto,le,héros'
]);
echo $writer->newReader()->toHTML();
</code></pre>

<?php
$writer = $writer->newWriter('a+');
if ($writer->isActiveStreamFilter()) {
    $writer->appendStreamFilter('string.rot13');
    $writer->prependStreamFilter('string.strip_tags');
}
$writer->insertAll([
    'je,suis,toto,le,héros',
    'je,<strong>suis</strong>,toto,le,héros',
]);

echo $writer->newReader()->toHTML(), PHP_EOL;
?>

</body>
</html>
