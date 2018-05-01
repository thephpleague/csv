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
 * A stream filter to conform the CSV field to RFC4180
 *
 * @see https://tools.ietf.org/html/rfc4180#section-2
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class RFC4180Field extends php_user_filter
{
    const FILTERNAME = 'convert.league.csv.rfc4180';

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
     * The value being search for
     *
     * @var string[]
     */
    protected $search;

    /**
     * The replacement value that replace found $search values
     *
     * @var string[]
     */
    protected $replace;

    /**
     * Characters that triggers enclosure with PHP fputcsv
     *
     * @var string
     */
    protected static $force_enclosure = "\n\r\t ";

    /**
     * Static method to add the stream filter to a {@link AbstractCsv} object
     *
     * @param AbstractCsv $csv
     * @param string      $whitespace_replace
     *
     * @return AbstractCsv
     */
    public static function addTo(AbstractCsv $csv, string $whitespace_replace = ''): AbstractCsv
    {
        self::register();

        $params = [
            'enclosure' => $csv->getEnclosure(),
            'escape' => $csv->getEscape(),
            'mode' => $csv->getStreamFilterMode(),
        ];

        if ($csv instanceof Writer && '' != $whitespace_replace) {
            self::addFormatterTo($csv, $whitespace_replace);
            $params['whitespace_replace'] = $whitespace_replace;
        }

        return $csv->addStreamFilter(self::FILTERNAME, $params);
    }

    /**
     * Add a formatter to the {@link Writer} object to format the record
     * field to avoid enclosure around a field with an empty space
     *
     * @param Writer $csv
     * @param string $whitespace_replace
     *
     * @return Writer
     */
    public static function addFormatterTo(Writer $csv, string $whitespace_replace): Writer
    {
        if ('' == $whitespace_replace || strlen($whitespace_replace) != strcspn($whitespace_replace, self::$force_enclosure)) {
            throw new InvalidArgumentException('The sequence contains a character that enforces enclosure or is a CSV control character or is the empty string.');
        }

        $mapper = function ($value) use ($whitespace_replace) {
            if (is_string($value)) {
                return str_replace(' ', $whitespace_replace, $value);
            }

            return $value;
        };

        $formatter = function (array $record) use ($mapper): array {
            return array_map($mapper, $record);
        };

        return $csv->addFormatter($formatter);
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
     * Static method to return the stream filter filtername
     *
     * @return string
     */
    public static function getFiltername(): string
    {
        return self::FILTERNAME;
    }

    /**
     * {@inheritdoc}
     */
    public function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = str_replace($this->search, $this->replace, $bucket->data);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }

    /**
     * {@inheritdoc}
     */
    public function onCreate()
    {
        if (!$this->isValidParams($this->params)) {
            return false;
        }

        $this->search = [$this->params['escape'].$this->params['enclosure']];
        $this->replace = [$this->params['enclosure'].$this->params['enclosure']];
        if (STREAM_FILTER_WRITE != $this->params['mode']) {
            return true;
        }

        $this->search = [$this->params['escape'].$this->params['enclosure']];
        $this->replace = [$this->params['escape'].$this->params['enclosure'].$this->params['enclosure']];
        if ($this->isValidSequence($this->params)) {
            $this->search[] = $this->params['whitespace_replace'];
            $this->replace[] = ' ';
        }

        return true;
    }

    /**
     * Validate params property
     *
     * @param array $params
     *
     * @return bool
     */
    protected function isValidParams(array $params): bool
    {
        static $mode_list = [STREAM_FILTER_READ => 1, STREAM_FILTER_WRITE => 1];

        return isset($params['enclosure'], $params['escape'], $params['mode'], $mode_list[$params['mode']])
            && 1 == strlen($params['enclosure'])
            && 1 == strlen($params['escape']);
    }

    /**
     * Is Valid White space replaced sequence
     *
     * @param array $params
     *
     * @return bool
     */
    protected function isValidSequence(array $params)
    {
        return isset($params['whitespace_replace'])
            && strlen($params['whitespace_replace']) == strcspn($params['whitespace_replace'], self::$force_enclosure);
    }
}
