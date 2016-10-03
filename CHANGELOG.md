#Changelog

All Notable changes to `Csv` will be documented in this file

## Next

### Added

- None

### Deprecated

- None

### Fixed

- BOM filtering fix [issue #184](ttps://github.com/thephpleague/csv/issues/184)

### Removed

- None

## 8.1.1 - 2016-09-05

### Added

- None

### Deprecated

- None

### Fixed

- `getInputBOM` method name is now consistent everywhere in the API [PR #171](https://github.com/thephpleague/csv/pull/171)
- preserve fileObject CSV controls [commit #8a20c56](https://github.com/thephpleague/csv/commit/8a20c56144effa552a8cba5d8c626063222975b7)
- Change `output` method header content-type value to `text/csv` [PR #175](https://github.com/thephpleague/csv/pull/175)

### Removed

- None

## 8.1.0 - 2016-05-31

### Added

- The package now includes its own autoloader.
- `Ouput::getInputEncoding`
- `Ouput::setInputEncoding`

### Deprecated

- `Ouput::getEncodingFrom` replaced by `Ouput::getInputEncoding`
- `Ouput::setEncodingFrom` replaced by `Ouput::setInputEncoding`

### Fixed

- Stream Filters are now url encoded before usage [issue #72](https://github.com/thephpleague/csv/issues/72)
- All internal parameters are now using the snake case format

### Removed

- None

## 8.0.0 - 2015-12-11

### Added

- `Reader::fetchPairs`
- `Reader::fetchPairsWithoutDuplicates`

### Deprecated

- None

### Fixed

- `Reader::fetchColumn` and `Reader::fetchAssoc` now return `Iterator`
- `Reader::fetchAssoc` callable argument expects an indexed row using the submitted keys as its first argument
- `Reader::fetchColumn` callable argument expects the selected column value as its first argument
-  Default value on `setOutputBOM` is removed
- `AbstractCsv::getOutputBOM` always return a string
- `AbstractCsv::getInputBOM` always return a string

### Removed

- `Controls::setFlags`
- `Controls::getFlags`
- `Controls::detectDelimiterList`
- `QueryFilter::removeFilter`
- `QueryFilter::removeSortBy`
- `QueryFilter::hasFilter`
- `QueryFilter::hasSortBy`
- `QueryFilter::clearFilter`
- `QueryFilter::clearSortBy`
- `Reader::query`
- The `$newline` argument from `AbstractCsv::createFromString`

## 7.2.0 - 2015-11-02

### Added

- `Reader::fetch` replaces `League\Csv\Reader::query` for naming consistency
- `Controls::fetchDelimitersOccurrence` to replace `Controls::detectDelimiterList` the latter gives erronous results

### Deprecated

- `Controls::detectDelimiterList`
- `Reader::query`
- The `$newline` argument from `AbstractCsv::createFromString`

### Fixed

- Streamming feature no longer trim filter name argument [issue #122](https://github.com/thephpleague/csv/issues/122)
- Fix default `SplFileObject` flags usage [PR #130](https://github.com/thephpleague/csv/pull/130)
- `AbstractCsv::createFromString` no longer trim the submitted string [issue #132](https://github.com/thephpleague/csv/issues/132)

### Removed

- Nothing

## 7.1.2 - 2015-06-10

### Added

- Nothing

### Deprecated

- Nothing

### Fixed

- Enclosures should be removed when a BOM sequence is stripped [issue #102](http://github.com/thephpleague/csv/issues/99)

### Removed

- Nothing

## 7.1.1 - 2015-05-20

### Added

- Nothing

### Deprecated

- Nothing

### Fixed

- `SplFileObject` flags were not always applied using query filter [issue #99](http://github.com/thephpleague/csv/issues/99)

### Removed

- Nothing

## 7.1.0 - 2015-05-06

### Added

- `stripBOM` query filtering method to ease removing the BOM sequence when querying the CSV document.
- All query filters are now accessible in the `Writer` class for conversion methods.

### Deprecated

- Nothing

### Fixed

- Internal code has been updated to take into account [issue #68479](http://bugs.php.net/68479)
- `setFlags` on conversion methods SplFileObject default flags are `SplFileObject::READ_AHEAD|SplFileObject::SKIP_EMPTY`
- `insertOne` now takes into account the escape character when modified after the first insert.

### Removed

- Nothing

## 7.0.1 - 2015-03-23

### Added

- Nothing

### Deprecated

- Nothing

### Fixed

- `setFlags`: `SplFileObject::DROP_NEW_LINE` can be remove using `setFlags` method.

### Removed

- Nothing

## 7.0.0 - 2015-02-19

### Added
- A new flexible mechanism to format and validate a row before its insertion by adding
    - `Writer::addFormatter` to add a formatter to the `Writer` object
    - `Writer::removeFormatter` to remove an already registered formatter
    - `Writer::hasFormatter` to detect the presence of a formatter
    - `Writer::clearFormatters` to clear all registered formatter
    - `Writer::addValidator` to add a validator to the `Writer` object
    - `Writer::removeValidator` to remove an already registered validator
    - `Writer::hasValidator` to detect the presence of a validator
    - `Writer::clearValidators` to clear all registered validator
    - `League\Csv\Exception\InvalidRowException` exception thrown when row validation failed
- Classes to maintain removed features from the `Writer` class
    - `League\Csv\Plugin\ColumnConsistencyValidator` to validate column consistency on insertion
    - `League\Csv\Plugin\ForbiddenNullValuesValidator` to validate `null` value on insertion
    - `League\Csv\Plugin\SkipNullValuesFormatter` to format `null` value on insertion

### Deprecated
- Nothing

### Fixed
- `jsonSerialize`, `toXML` and `toHTML` output can be modified using `Reader` query options methods.
- `AbstractCSV::detectDelimiterList` index keys now represents the occurrence of the found delimiter.
- `getNewline` and `setNewline` are accessible on the `Reader` class too.
- the named constructor `createFromString` now accepts the `$newline` sequence as a second argument to specify the last added new line character to better work with interoperability.
- Default value on CSV controls setter methods `setDelimiter`, `setEnclosure` and `setEscape` are removed
- Default `SplFileObject` flags value is now `SplFileObject::READ_CSV|SplFileObject::DROP_NEW_LINE`
- All CSV properties are now copied when using `newReader` and `newWriter` methods
- BOM addition on output improved by removing if found the existing BOM character
- the `AbstractCSV::output` method now returns the number of bytes send to the output buffer
- `Reader::fetchColumn` will automatically filter out non existing values from the return array

### Removed
- Setting `ini_set("auto_detect_line_endings", true);` is no longer set in the class constructor. Mac OS X users must explicitly set this ini options in their script.
- `Writer` and `Reader` default constructor are removed from public API in favor of the named constructors.
- All `Writer` methods and constant related to CSV data validation and formatting before insertion
    - `Writer::getNullHandlingMode`
    - `Writer::setNullHandlingMode`
    - `Writer::setColumnsCount`
    - `Writer::getColumnsCount`
    - `Writer::autodetectColumnsCount`
    - `Writer::NULL_AS_EXCEPTION`
    - `Writer::NULL_AS_EMPTY`
    - `Writer::NULL_AS_SKIP_CELL`

## 6.3.0 - 2015-01-21

### Added
- `AbstractCSV::setOutputBOM`
- `AbstractCSV::getOutputBOM`
- `AbstractCSV::getInputBOM`

to manage BOM character with CSV.

### Deprecated

- Nothing

### Fixed

- Nothing

### Removed

- Nothing

## 6.2.0 - 2014-12-12

### Added
- `Writer::setNewline` , `Writer::getNewline`  to control the newline sequence character added at the end of each CSV row.

### Deprecated

- Nothing

### Fixed

- Nothing

### Removed

- Nothing

## 6.1.0 - 2014-12-08

### Added
- `Reader::fetchAssoc` now also accepts an integer as first argument representing a row index.

### Deprecated

- Nothing

### Fixed

- Nothing

### Removed

- Nothing

## 6.0.1 - 2014-11-12

### Added

- Nothing

### Deprecated

- Nothing

### Fixed
- Bug Fixed `detectDelimiterList`

### Removed

- Nothing

## 6.0.0 - 2014-08-28

### Added
- Stream Filter API in `League\Csv\AbstractCsv`
- named constructors `createFromPath` and `createFromFileObject` in `League\Csv\AbstractCsv` to ease CSV object instantiation
- `detectDelimiterList` in `League\Csv\AbstractCsv` to replace and remove the use of `RuntimeException` in `detectDelimiter`
- `setEncodingFrom` and `setDecodingFrom` in `League\Csv\AbstractCsv` to replace `setEncoding` and `getEncoding` for naming consistency
- `newWriter` and `newReader` methods in `League\Csv\AbstractCsv` to replace `Writer::getReader` and `Reader::getWriter`

### Deprecated
- Nothing

### Fixed
- `League\Csv\Reader::each` more strict `$callable` MUST returns `true`

### Remove
- `League\Csv\AbstractCsv::detectDelimiter`
- `League\Csv\AbstractCsv::setEncoding` and `League\Csv\AbstractCsv::getEncoding`
- `League\Csv\Reader::setSortBy`
- `League\Csv\Reader::setFilter`
- `League\Csv\Reader::getWriter`
- `League\Csv\Writer::getReader`
- `League\Csv\Reader::fetchCol`

## 5.4.0 - 2014-04-17

### Added

- `League\Csv\Writer::setColumnsCount`, `League\Csv\Writer::getColumnsCount`, `League\Csv\Writer::autodetectColumnsCount` to enable column consistency in writer mode
- `League\Csv\Reader::fetchColumn` replaces `League\Csv\Reader::fetchCol` for naming consistency

### Deprecated
- `League\Csv\Reader::fetchCol`

### Fixed
- Nothing

### Removed

- Nothing

## 5.3.1 - 2014-04-09

### Added
- Nothing

### Deprecated
- Nothing

### Fixed
- `$open_mode` default to `r+` in `League\Csv\AbstractCsv` constructors

### Removed

- Nothing

## 5.3.0 - 2014-03-24

### Added
- `League\Csv\Writer::setNullHandlingMode` and `League\Csv\Writer::getNullHandlingMode` to handle `null` value

### Deprecated
- Nothing

### Fixed
- `setting ini_set("auto_detect_line_endings", true);` no longer needed for Mac OS

### Removed

- Nothing

## 5.2.0 - 2014-03-13

### Added
- `League\Csv\Reader::addSortBy`, `League\Csv\Reader::removeSortBy`, `League\Csv\Reader::hasSortBy`, `League\Csv\Reader::clearSortBy` to improve sorting
- `League\Csv\Reader::clearFilter` to align extract filter capabilities to sorting capabilities

### Deprecated
- `League\Csv\Reader::setSortBy` replaced by a better implementation

### Fixed
- `League\Csv\Reader::setOffset` now default to 0;
- `League\Csv\Reader::setLimit` now default to -1;
- `detectDelimiter` bug fixes

### Removed

- Nothing

## 5.1.0 - 2014-03-11

### Added
- `League\Csv\Reader::each` to ease CSV import data
- `League\Csv\Reader::addFilter`, `League\Csv\Reader::removeFilter`, `League\Csv\Reader::hasFilter` to improve extract filter capabilities
- `detectDelimiter` method to `League\Csv\AbstractCsv` to sniff CSV delimiter character.

### Deprecated
- `League\Csv\Reader::setFilter` replaced by a better implementation

### Fixed
- Nothing

### Removed

- Nothing

## 5.0.0 - 2014-02-28

### Added
- Change namespace from `Bakame\Csv` to `League\Csv`

### Deprecated
- Nothing

### Fixed
- Nothing

### Removed

- Nothing

## 4.2.1 - 2014-02-22

### Fixed
- `$open_mode` validation is done by PHP internals directly

### Removed

- Nothing

## 4.2.0 - 2014-02-17

### Added
- `toXML` method to transcode the CSV into a XML in `Bakame\Csv\AbstractCsv`

### Fixed
- `toHTML` method bug in `Bakame\Csv\AbstractCsv`
- `output` method accepts an optional `$filename` argument
- `Bakame\Csv\Reader::fetchCol` default to `$columnIndex = 0`
- `Bakame\Csv\Reader::fetchOne` default to `$offset = 0`

## 4.1.2 - 2014-02-14

### Added
- Move from `PSR-0` to `PSR-4` to autoload the library

## 4.1.1 - 2014-02-14

### Fixed
- `Bakame\Csv\Reader` methods fixed
- `jsonSerialize` bug fixed

## 4.1.0 - 2014-02-07

### Added
- `getEncoding` and `setEncoding` methods to `Bakame\Csv\AbstractCsv`

### Fixed
- `Bakame\Csv\Writer::insertOne` takes into account CSV controls
- `toHTML` method takes into account encoding

## 4.0.0 - 2014-02-05

### Added

- `Bakame\Csv\Writer`
- `Bakame\Csv\Writer` and `Bakame\Csv\Reader` extend `Bakame\Csv\AbstractCsv`

### Deprecated
- Nothing

### Fixed
- `Bakame\Csv\Reader::fetchOne` is no longer deprecated
- `Bakame\Csv\Reader::fetchCol` no longer accepts a third parameter `$strict`

### Removed
- `Bakame\Csv\Codec` now the library is composer of 2 main classes
- `Bakame\Csv\Reader::getFile`
- `Bakame\Csv\Reader::fetchValue`
- `Bakame\Csv\Reader` no longer implements the `ArrayAccess` interface

## 3.3.0 - 2014-01-28

### Added
- `Bakame\Csv\Reader` implements `IteratorAggregate` Interface
- `Bakame\Csv\Reader::createFromString` to create a CSV object from a raw string
- `Bakame\Csv\Reader::query` accept an optional `$callable` parameter

### Deprecated
- `Bakame\Csv\Reader::getFile` in favor of `Bakame\Csv\Reader::getIterator`

### Removed
- `Bakame\Csv\ReaderInterface` useless interface

### Fixed
- `Bakame\Csv\Reader::fetch*` `$callable` parameter is normalized to accept an array
- `Bakame\Csv\Reader::fetchCol` accepts a third parameter `$strict`

## 3.2.0 - 2014-01-16

### Added
- `Bakame\Csv\Reader` implements the following interfaces `JsonSerializable` and `ArrayAccess`
- `Bakame\Csv\Reader::toHTML` to output the CSV as a HTML table
- `Bakame\Csv\Reader::setFilter`, `Bakame\Csv\Reader::setSortBy`, `Bakame\Csv\Reader::setOffset`, `Bakame\Csv\Reader::setLimit`, `Bakame\Csv\Reader::query` to perform SQL like queries on the CSV content.
- `Bakame\Csv\Codec::setFlags`, `Bakame\Csv\Codec::getFlags`, Bakame\Csv\Codec::__construct : add an optional `$flags` parameter to enable the use of `SplFileObject` constants flags

### Deprecated
- `Bakame\Csv\Reader::fetchOne` replaced by `Bakame\Csv\Reader::offsetGet`
- `Bakame\Csv\Reader::fetchValue` useless method

## 3.1.0 - 2014-01-13

### Added
- `Bakame\Csv\Reader::output` output the CSV data directly in the output buffer
- `Bakame\Csv\Reader::__toString` can be use to echo the raw CSV

## 3.0.1 - 2014-01-10

### Fixed
- `Bakame\Csv\Reader::fetchAssoc` when users keys and CSV row data don't have the same length

## 3.0.0 - 2014-01-10

### Added
- `Bakame\Csv\ReaderInterface`
- `Bakame\Csv\Reader` class

### Fixed
- `Bakame\Csv\Codec::loadString`returns a `Bakame\Csv\Reader` object
- `Bakame\Csv\Codec::loadFile` returns a `Bakame\Csv\Reader` object
- `Bakame\Csv\Codec::save` returns a `Bakame\Csv\Reader` object

## 2.0.0 - 2014-01-09

### Added
- `Bakame\Csv\CsvCodec` class renamed `Bakame\Csv\Codec`

### Deprecated
- Nothing

### Fixed
- Nothing

### Removed
- `Bakame\Csv\Codec::create` from public API

## 1.0.0 - 2013-12-03

Initial Release of `Bakame\Csv`
