<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.0.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace League\Csv;

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
     * @var string
     */
    protected $search;

    /**
     * The replacement value that replace found $search values
     *
     * @var string
     */
    protected $replace;

    /**
     * Static method to add the stream filter to a {@link AbstractCsv} object
     *
     * @param AbstractCsv $csv
     *
     * @return AbstractCsv
     */
    public static function addTo(AbstractCsv $csv): AbstractCsv
    {
        self::register();

        return $csv->addStreamFilter(self::FILTERNAME, [
            'enclosure' => $csv->getEnclosure(),
            'escape' => $csv->getEscape(),
            'mode' => $csv->getStreamFilterMode(),
        ]);
    }

    /**
     * Static method to register the class as a stream filter
     */
    public static function register()
    {
        if (!in_array(self::FILTERNAME, stream_get_filters())) {
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
     * @inheritdoc
     */
    public function onCreate()
    {
        if (!$this->isValidParams($this->params)) {
            return false;
        }

        $this->search = $this->params['escape'].$this->params['enclosure'];
        $this->replace = $this->params['enclosure'].$this->params['enclosure'];
        if (STREAM_FILTER_WRITE === $this->params['mode']) {
            $this->replace = $this->search.$this->params['enclosure'];
        }

        return true;
    }

    /**
     * Validate params property
     *
     * @param  array $params
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
     * @inheritdoc
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
}
