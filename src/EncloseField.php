<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.1.4
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace League\Csv;

use InvalidArgumentException;
use php_user_filter;

/**
 * A stream filter to improve enclosure character usage
 *
 * @see https://tools.ietf.org/html/rfc4180#section-2
 * @see https://bugs.php.net/bug.php?id=38301
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class EncloseField extends php_user_filter
{
    const FILTERNAME = 'convert.league.csv.enclosure';

    /**
     * the filter name used to instantiate the class with
     *
     * @var string
     */
    public $filtername;

    /**
     * Contents of the params parameter passed to stream_filter_append
     * or stream_filter_prepend functions
     *
     * @var mixed
     */
    public $params;

    /**
     * Default sequence
     *
     * @var string
     */
    protected $sequence;

    /**
     * Characters that triggers enclosure in PHP
     *
     * @var string
     */
    protected static $force_enclosure = "\n\r\t ";

    /**
     * Static method to return the stream filter filtername
     *
     * @return string
     */
    public static function getFiltername(): string
    {
        return self::FILTERNAME;
    }

    /**
     * Static method to register the class as a stream filter
     */
    public static function register()
    {
        if (!in_array(self::FILTERNAME, stream_get_filters(), true)) {
            stream_filter_register(self::FILTERNAME, __CLASS__);
        }
    }

    /**
     * Static method to add the stream filter to a {@link Writer} object
     *
     * @param Writer $csv
     * @param string $sequence
     *
     * @throws InvalidArgumentException if the sequence is malformed
     *
     * @return Writer
     */
    public static function addTo(Writer $csv, string $sequence): Writer
    {
        self::register();

        if (!self::isValidSequence($sequence)) {
            throw new InvalidArgumentException('The sequence must contain at least one character to force enclosure');
        }

        $formatter = function (array $record) use ($sequence) {
            foreach ($record as &$value) {
                $value = $sequence.$value;
            }
            unset($value);

            return $record;
        };

        return $csv
            ->addFormatter($formatter)
            ->addStreamFilter(self::FILTERNAME, ['sequence' => $sequence]);
    }

    /**
     * Filter type and sequence parameters
     *
     * - The sequence to force enclosure MUST contains one of the following character ("\n\r\t ")
     *
     * @param string $sequence
     *
     * @return bool
     */
    protected static function isValidSequence(string $sequence): bool
    {
        return strlen($sequence) != strcspn($sequence, self::$force_enclosure);
    }

    /**
     * {@inheritdoc}
     */
    public function onCreate()
    {
        return isset($this->params['sequence'])
            && $this->isValidSequence($this->params['sequence']);
    }

    /**
     * {@inheritdoc}
     */
    public function filter($in, $out, &$consumed, $closing)
    {
        while ($res = stream_bucket_make_writeable($in)) {
            $res->data = str_replace($this->params['sequence'], '', $res->data);
            $consumed += $res->datalen;
            stream_bucket_append($out, $res);
        }

        return PSFS_PASS_ON;
    }
}
