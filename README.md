# CSV

[![Latest Version](https://img.shields.io/github/release/thephpleague/csv.svg?style=flat-square)](https://github.com/thephpleague/csv/releases)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Build](https://github.com/thephpleague/csv/workflows/build/badge.svg)](https://github.com/thephpleague/csv/actions?query=workflow%3A%22build%22)
[![Total Downloads](https://img.shields.io/packagist/dt/league/csv.svg?style=flat-square)](https://packagist.org/packages/league/csv)

Csv is a library to ease parsing, writing and filtering CSV in PHP. 
The library goal is to be powerful while remaining lightweight,
by utilizing PHP native classes whenever possible.

## Highlights

- Easy to use API
- Read and Write to CSV documents in a memory efficient and scalable way
- Support PHP stream filtering capabilities
- Transform CSV documents into popular format (JSON, XML or HTML)
- Fully documented
- Fully unit tested
- Framework-agnostic

## Documentation

Full documentation can be found at [csv.thephpleague.com](https://csv.thephpleague.com).

## System Requirements

You need the `ext-filter` extension to use `Csv` and the latest stable version of PHP is recommended.

Please find below the PHP support for `Csv` version 9.

| Min. Library Version | Min. PHP Version | Max. Supported PHP Version |
|----------------------|------------------|----------------------------|
| 9.0.0                | PHP 7.0.10       | PHP 7.1.x                  |
| 9.1.2                | PHP 7.0.10       | PHP 7.2.x                  |
| 9.2.0                | PHP 7.0.10       | PHP 7.4.x                  |
| 9.6.0                | PHP 7.2.5        | PHP 7.4.x                  |
| 9.6.2                | PHP 7.2.5        | PHP 8.0.x                  |
| 9.7.0                | PHP 7.3.0        | PHP 8.0.x                  |
| 9.7.3                | PHP 7.3.0        | PHP 8.1.x                  |
| 9.8.0                | PHP 7.4.0        | PHP 8.1.x                  |
| 9.9.0                | PHP 8.1.2        | PHP 8.x                    |

## Install

Install `Csv` using Composer.

```bash
composer require league/csv:^9.0
```

## Configuration

> [!WARNING]
> **Starting with PHP8.4 Deprecation notices will be trigger if you do not explicitly set the escape parameter.**
> see [Deprecation for PHP8.4](https://wiki.php.net/rfc/deprecations_php_8_4#deprecate_proprietary_csv_escaping_mechanism) and [CSV and PHP8.4](https://nyamsprod.com/blog/csv-and-php8-4/)

> [!TIP]
> If your CSV document was created or is read on a **Legacy Macintosh computer**, add the following lines before 
using the library to help [PHP detect line ending](http://php.net/manual/en/function.fgetcsv.php#refsect1-function.fgetcsv-returnvalues).

```php
if (!ini_get('auto_detect_line_endings')) {
    ini_set('auto_detect_line_endings', '1');
}
```

> [!WARNING]
> **The ini setting is deprecated since PHP version 8.1 and will be removed in PHP 9.0**

## Testing

The library has:

- a [PHPUnit](https://phpunit.de) test suite.
- a coding style compliance test suite using [PHP CS Fixer](https://cs.symfony.com/).
- a code analysis compliance test suite using [PHPStan](https://github.com/phpstan/phpstan).

To run the tests, run the following command from the project folder.

```bash
composer test
```

## Contributing

Contributions are welcome and will be fully credited. Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](.github/CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email nyamsprod@gmail.com instead of using the issue tracker.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [ignace nyamagana butera](https://github.com/nyamsprod)
- [All Contributors](https://github.com/thephpleague/csv/graphs/contributors)

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
