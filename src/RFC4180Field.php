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

use League\Csv\Exception\InvalidArgumentException;
use php_user_filter;
use Throwable;

/**
 *  A stream filter to conform the CSV field to RFC4180
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class RFC4180Field extends php_user_filter
{
    use ValidatorTrait;

    const FILTERNAME = 'league.csv.rfc4180.field';

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
     */
    public static function addTo(AbstractCsv $csv)
    {
        if (!in_array(self::FILTERNAME, stream_get_filters())) {
            stream_filter_register(self::FILTERNAME, __CLASS__);
        }

        $csv->addStreamFilter(self::FILTERNAME, [
            'enclosure' => $csv->getEnclosure(),
            'escape' => $csv->getEscape(),
            'mode' => $csv->getStreamFilterMode(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function onCreate()
    {
        if (!isset($this->params['enclosure'], $this->params['escape'], $this->params['mode'])) {
            return false;
        }

        try {
            $enclosure = $this->filterControl($this->params['enclosure'], 'enclosure', __METHOD__);
            $escape = $this->filterControl($this->params['escape'], 'escape', __METHOD__);
            $mode = $this->filterMode($this->params['mode']);

            $this->search = $escape.$enclosure;
            $this->replace = $enclosure.$enclosure;
            if (STREAM_FILTER_WRITE === $mode) {
                $this->replace = $escape.$this->replace;
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Filter the stream filter mode
     *
     * @param int $mode stream filter mode
     *
     * @throws InvalidArgumentException if the stream filter mode is unknown or unsupported
     *
     * @return int
     */
    protected function filterMode(int $mode)
    {
        static $mode_list = [STREAM_FILTER_READ => 1, STREAM_FILTER_WRITE => 1];
        if (isset($mode_list[$mode])) {
            return $mode;
        }

        throw new InvalidArgumentException(sprintf('The given filter mode `%s` is unknown or unsupported', $mode));
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
