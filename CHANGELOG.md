#Changelog
All Notable changes to `League\Csv` will be documented in this file

## 6.0.1 - 2014-11-12

### Added
- Nothing

### Deprecated
- Nothing

### Fixed
- Bug Fixed `detectDelimiterList`

### Remove
- Nothing

### Security
- Nothing

## 6.0.0 - 2014-08-28

### Added
- Stream Filter API in `League\Csv\AbstractCsv`
- named constructors `createFromPath` and `createFromFileObject` in `League\Csv\AbstractCsv` to ease CSV object instantiation
- `detectDelimiterList` in `League\Csv\AbstractCsv` to replace and remove the use of `RuntimeException` in `detectDelimiter`
- `setEncodingFrom` and `setDecodingFrom` in `League\Csv\AbstractCsv` to replace `setEncoding` and `getEncoding` for naming consistency
- `newWriter` and `newReader` methods in `League\Csv\AbstractCsv` to replace `League\Csv\Writer::getReader` and `League\Csv\Reader::getWriter`

### Deprecated
- Nothing

### Fixed
- `League\Csv\Reader::each` more strict `$callable` MUST returns true

### Remove
- `League\Csv\AbstractCsv::detectDelimiter`
- `League\Csv\AbstractCsv::setEncoding` and `League\Csv\AbstractCsv::getEncoding`
- `League\Csv\Reader::setSortBy`
- `League\Csv\Reader::setFilter`
- `League\Csv\Reader::getWriter`
- `League\Csv\Writer::getReader`

### Security
- Nothing

## 5.4.0 - 2014-04-17

### Added

- `League\Csv\Writer::setColumnsCount`, `League\Csv\Writer::getColumnsCount`, `League\Csv\Writer::autodetectColumnsCount` to enable column consistency in writer mode
- League\Csv\Reader::fetchColumn replaces `League\Csv\Reader::fetchCol` for naming consistency

### Deprecated
- `League\Csv\Reader::fetchCol`

### Fixed
- Nothing

### Remove
- Nothing

### Security
- Nothing

## 5.3.1 - 2014-04-09

### Added
- Nothing

### Deprecated
- Nothing

### Fixed
- `$open_mode` default to `r+` in `League\Csv\AbstractCsv` constructors

### Remove
- Nothing

### Security
- Nothing

## 5.3.0 - 2014-03-24

### Added
- `League\Csv\Writer::setNullHandlingMode` and `League\Csv\Writer::getNullHandlingMode` to handle `null` value

### Deprecated
- Nothing

### Fixed
- `setting ini_set("auto_detect_line_endings", true);` no longer needed for Mac OS

### Remove
- Nothing

### Security
- Nothing

## 5.2.0 - 2014-03-13

### Added
- `League\Csv\Reader::addSortBy`, `League\Csv\Reader::removeSortBy`, `League\Csv\Reader::hasSortBy`, `League\Csv\Reader::clearSortBy` to improve sorting
- `League\Csv\Reader::clearFilter` to align extract filter capabilities to sorting capabilities

### Deprecated
- `League\Csv\Reader::setSortBy` replaced by a better implementation

### Fixed
- `League\Csv\Reader::setOffset` now default to 0;
- `League\Csv\Reader::setLimit` now default to 0-1;
- `detectDelimiter` bug fixes

### Remove
- Nothing

### Security
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

### Remove
- Nothing

### Security
- Nothing

## 5.0.0 - 2014-02-28

### Added
- Change namespace from `Bakame\Csv` to `League\Csv`

### Deprecated
- Nothing

### Fixed
- Nothing

### Remove
- Nothing

### Security
- Nothing

## 4.2.1 - 2014-02-22

### Added
- Nothing

### Deprecated
- Nothing

### Fixed
- `$open_mode` validation is done by PHP interals directly

### Remove
- Nothing

### Security
- Nothing

## 4.2.0 - 2014-02-17

### Added
- `toXML` method to transcode the CSV into a XML in `Bakame\Csv\AbstractCsv`

### Deprecated
- Nothing

### Fixed
- `toHTML` method bug in `Bakame\Csv\AbstractCsv`
- `output` method accepts an optional `$filename` argument
- `Bakame\Csv\Reader::fetchCol` default to `$columnIndex = 0` 
- `Bakame\Csv\Reader::fetchOne` default to `$offset = 0`

### Remove
- Nothing

### Security
- Nothing

## 4.1.2 - 2014-02-14

### Added
- Move from `PSR-0` to `PSR-4` to autoload the library

### Deprecated
- Nothing

### Fixed
- Nothing

### Remove
- Nothing

### Security
- Nothing

## 4.1.1 - 2014-02-14

### Added
- Nothing

### Deprecated
- Nothing

### Fixed
- `Bakame\Csv\Reader` methods fixed
- `jsonSerialize` bug fixed

### Remove
- Nothing

### Security
- Nothing

## 4.1.0 - 2014-02-07

### Added 
- `getEncoding` and `setEncoding` methods to `Bakame\Csv\AbstractCsv`

### Deprecated
- Nothing

### Fixed
- `Bakame\Csv\Writer::insertOne` takes into account CSV controls
- `toHTML` method takes into account encoding

### Removed
- Nothing

### Security
- Nothing

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

### Security
- Nothing

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

### Security
- Nothing

## 3.2.0 - 2014-01-16

### Added
- `Bakame\Csv\Reader` implements the following interfaces `JsonSerializable` and `ArrayAccess`
- `Bakame\Csv\Reader::toHTML` to output the CSV as a HTML table
- `Bakame\Csv\Reader::setFilter`, `Bakame\Csv\Reader::setSortBy`, `Bakame\Csv\Reader::setOffset`, `Bakame\Csv\Reader::setLimit`, `Bakame\Csv\Reader::query` to perform SQL like queries on the CSV content.
- `Bakame\Csv\Codec::setFlags`, `Bakame\Csv\Codec::getFlags`, Bakame\Csv\Codec::__construct : add an optional `$flags` parameter to enable the use of `SplFileObject` constants flags

### Deprecated
- `Bakame\Csv\Reader::fetchOne` replaced by `Bakame\Csv\Reader::offsetGet`
- `Bakame\Csv\Reader::fetchValue` useless method 

### Removed
- Nothing

### Fixed
- Nothing

### Security
- Nothing

## 3.1.0 - 2014-01-13

### Added
- `Bakame\Csv\Reader::output` output the CSV data directly in the output buffer
- `Bakame\Csv\Reader::__toString` can be use to echo the raw CSV

### Deprecated
- Nothing

### Removed
- Nothing

### Fixed
- Nothing

### Security
- Nothing

## 3.0.1 - 2014-01-10

### Added
- Nothing

### Deprecated
- Nothing

### Removed
- Nothing

### Fixed
- `Bakame\Csv\Reader::fetchAssoc` when users keys and CSV row data don't have the same length

### Security
- Nothing

## 3.0.0 - 2014-01-10

### Added
- `Bakame\Csv\ReaderInterface`
- `Bakame\Csv\Reader` class

### Deprecated
- Nothing

### Removed
- Nothing

### Fixed
- `Bakame\Csv\Codec::loadString`returns a `Bakame\Csv\Reader` object
- `Bakame\Csv\Codec::loadFile` returns a `Bakame\Csv\Reader` object
- `Bakame\Csv\Codec::save` returns a `Bakame\Csv\Reader` object

### Security
- Nothing

## 2.0.0 - 2014-01-09

### Added
- `Bakame\Csv\CsvCodec` class renamed `Bakame\Csv\Codec`

### Deprecated
- Nothing

### Removed
- `Bakame\Csv\Codec::create` from public API

### Fixed
- Nothing

### Security
- Nothing

## 1.0.0 - 2013-12-03

Initial Release of `Bakame\Csv`
