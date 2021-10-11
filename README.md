# CSV

[![Latest Version](https://img.shields.io/github/release/thephpleague/csv.svg?style=flat-square)](https://github.com/thephpleague/csv/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build](https://github.com/thephpleague/csv/workflows/build/badge.svg)](https://github.com/thephpleague/csv/actions?query=workflow%3A%22build%22)
[![Total Downloads](https://img.shields.io/packagist/dt/league/csv.svg?style=flat-square)](https://packagist.org/packages/league/csv)

Csv is a simple library to ease CSV parsing, writing and filtering in
PHP. The goal of the library is to be powerful while remaining lightweight,
by utilizing PHP native classes whenever possible.

## Highlights

- Simple API
- Read and Write to CSV documents in a memory efficient and scalable way
- Support PHP stream filtering capabilities
- Transform CSV documents into popular format (JSON, XML or HTML)
- Fully documented
- Fully unit tested
- Framework-agnostic

## Documentation

Full documentation can be found at [csv.thephpleague.com](https://csv.thephpleague.com).

## System Requirements

You need **PHP >= 7.3** and the `mbstring` extension to use `Csv` but the latest stable version of PHP is recommended.

## Install

Install `Csv` using Composer.

```bash
composer require league/csv
```

## Configuration

**Warning:** If your CSV document was created or is read on a Macintosh computer, add the following lines before using the library to help [PHP detect line ending](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

```php
if (!ini_get("auto_detect_line_endings")) {
    ini_set("auto_detect_line_endings", '1');
}
```

## Testing

`League\Csv` has a :

- a [PHPUnit](https://phpunit.de) test suite
- a coding style compliance test suite using [PHP CS Fixer](https://cs.symfony.com/).
- a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).

To run the tests, run the following command from the project folder.

```bash
composer test
```

## Contributing

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](CONDUCT.md) for details.

## Security

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/thephpleague/csv/graphs/contributors)

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
