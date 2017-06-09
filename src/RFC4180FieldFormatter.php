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
use Throwable;

/**
 *  A stream filter to conform the written CSV field to RFC4180
 *  This stream filter should be attach to a League\Csv\Writer object
 *
 * @package League.csv
 * @since   9.0.0
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 */
class RFC4180FieldFormatter extends php_user_filter
{
    use ValidatorTrait;

    const STREAM_FILTERNAME = 'rfc4180.league.csv';

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
     * Static method to add the stream filter to a Writer object
     *
     * @param AbstractCsv $csv
     */
    public static function addTo(AbstractCsv $csv)
    {
        if (!in_array(self::STREAM_FILTERNAME, stream_get_filters())) {
            stream_filter_register(self::STREAM_FILTERNAME, __CLASS__);
        }

        $csv->addStreamFilter(self::STREAM_FILTERNAME, [
            'enclosure' => $csv->getEnclosure(),
            'escape' => $csv->getEscape(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function onCreate()
    {
        if (!isset($this->params['enclosure'], $this->params['escape'])) {
            return false;
        }

        try {
            $enclosure = $this->filterControl($this->params['enclosure'], 'enclosure', __METHOD__);
            $escape = $this->filterControl($this->params['escape'], 'escape', __METHOD__);

            $this->search = $escape.$enclosure;
            $this->replace = $escape.$enclosure.$enclosure;

            return true;
        } catch (Throwable $e) {
            return false;
        }
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
