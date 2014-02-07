Examples
==========


* [Converting the CSV into a HTML Table](example00.php) with the `toHTML` method
* [Converting the CSV into a Json](example01.php) string
* [Downloading the CSV](example02.php) using the `output` method
* [Selecting a specific row in the CSV](example03.php)
* [Filtering a CSV](example05.php) using the `Bakame\Csv\Reader` class
* [Creating a CSV](example05.php) using the `Bakame\Csv\Writer` class
* [Passing a CSV from writing mode to Reader mode](example06.php)

The CSV use for the example is from [Paris Opendata](http://opendata.paris.fr/opendata/jsp/site/Portal.jsp?document_id=60&portlet_id=121)

Tips
------

When creating a file using the `Bakame\Csv\Writer` class, first use the `insert*` methods and manipulate your CSV afterwards. If you manipulate your data before you may change the file cursor position and get unexpected results.