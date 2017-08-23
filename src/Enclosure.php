<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 9.0.1
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
declare(strict_types=1);

namespace League\Csv;

use LengthException;
use OutOfRangeException;
use php_user_filter;
use Throwable;

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
class Enclosure extends php_user_filter
{
    const FILTERNAME = 'convert.league.csv.enclosure';

    const ENCLOSE_ALL = 'ENCLOSE_ALL';

    const ENCLOSE_NONE = 'ENCLOSE_NONE';

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
     * Default type
     *
     * @var string
     */
    protected $type = self::ENCLOSE_ALL;

    /**
     * Default sequence
     *
     * @var string
     */
    protected $sequence = "\t\x1f";

    /**
     * Default CSV controls
     *
     * @var string[]
     */
    protected $controls = [',', '"', '\\'];

    /**
     * Default formatter
     *
     * @var string
     */
    protected $formatter = 'forceEnclosure';

    /**
     * Default replace string
     *
     * @var string
     */
    protected $replace;

    /**
     * Enclosure action type
     *
     * @var array
     */
    protected static $type_list = [self::ENCLOSE_ALL => 1, self::ENCLOSE_NONE => 1];

    /**
     * Characters that triggers enclosure in PHP
     *
     * @var string
     */
    protected static $force_enclosure = "\n\r\t ";

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
     * Static method to add the stream filter to a {@link Writer} object
     *
     * @param Writer $csv
     * @param string $type
     * @param string $sequence
     *
     * @return AbstractCsv
     */
    public static function addTo(Writer $csv, string $type, string $sequence): Writer
    {
        self::register();

        $csv->addFormatter((new self())
            ->controls($csv->getDelimiter(), $csv->getEnclosure(), $csv->getEscape())
            ->sequence($type, $sequence));

        return $csv->addStreamFilter(self::FILTERNAME, ['type' => $type, 'sequence' => $sequence]);
    }

    /**
     * Set the CSV controls
     *
     * @param string ...$controls (delimiter, enclosure, escape)
     *
     * @return self
     */
    public function controls(string ...$controls): self
    {
        if ($this->controls === $controls) {
            return $this;
        }

        $check = array_filter($controls, function ($control): bool {
            return 1 !== strlen($control);
        });

        if (count($check)) {
            throw new LengthException(sprintf('%s() expects CSV control with a single character', __METHOD__));
        }

        $clone = clone $this;
        $clone->controls = $controls;

        return $clone;
    }

    /**
     * Set the Sequence to be used to update enclosure usage
     *
     * @param string $type     enclosure usage wanted (self::ENCLOSE_ALL or self::ENCLOSE_NONE)
     * @param string $sequence sequence used to work around fputcsv limitation
     *
     * @return self
     */
    public function sequence(string $type, string $sequence): self
    {
        if ($sequence === $this->sequence && $this->type === $type) {
            return $this;
        }
        $this->filterParams($type, $sequence);

        $clone = clone $this;
        $clone->type = $type;
        $clone->sequence = $sequence;
        $clone->formatter = self::ENCLOSE_ALL == $clone->type ? 'forceEnclosure' : 'escapeWhiteSpace';

        return $clone;
    }

    /**
     * Filter type and sequence parameters
     *
     * - The sequence to force enclosure MUST contains one of the following character ("\n\r\t ")
     * - The sequence to remove enclosure around white space MUST NOT contains one of the following character ("\n\r\t ")
     *
     * @param string $type
     * @param string $sequence
     *
     * @throws OutOfRangeException if the type is not recognized
     * @throws OutOfRangeException if the sequence is invalid
     */
    protected function filterParams(string $type, string $sequence)
    {
        if (!isset(self::$type_list[$type])) {
            throw new OutOfRangeException('The given filter type does not exists');
        }

        static $errors = [
            self::ENCLOSE_ALL => [
                'status' => true,
                'message' => 'The sequence must contain at least one character to force enclosure',
            ],
            self::ENCLOSE_NONE => [
                'status' => false,
                'message' => 'The sequence must not contain a character to force enclosure',
            ],
        ];

        $sequence_status = strlen($sequence) == strcspn($sequence, self::$force_enclosure);
        if ($errors[$type]['status'] == $sequence_status) {
            throw new OutOfRangeException($errors[$type]['message']);
        }
    }

    /**
     * @inheritdoc
     */
    public function __invoke(array $record): array
    {
        return array_map([$this, $this->formatter], $record);
    }

    /**
     * Format the record field to force the addition of the enclosure by fputcsv
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function forceEnclosure($value)
    {
        return $this->sequence.$value;
    }

    /**
     * Format the record field to avoid enclosure around a field with an empty space
     *
     * @param mixed $value
     *
     * @return string
     */
    protected function escapeWhiteSpace($value)
    {
        if (!is_string($value) || false === strpos($value, ' ') || in_array(' ', $this->controls)) {
            return $value;
        }

        return str_replace(' ', $this->sequence, $value);
    }

    /**
     * @inheritdoc
     */
    public function onCreate()
    {
        if (!isset($this->params['type'], $this->params['sequence'])) {
            return false;
        }

        try {
            $this->filterParams($this->params['type'], $this->params['sequence']);
        } catch (Throwable $e) {
            return false;
        }

        $this->replace = '';
        if ($this->params['type'] === self::ENCLOSE_NONE) {
            $this->replace = ' ';
        }
    }

    /**
     * @inheritdoc
     */
    public function filter($in, $out, &$consumed, $closing)
    {
        while ($res = stream_bucket_make_writeable($in)) {
            $res->data = str_replace($this->params['sequence'], $this->replace, $res->data);
            $consumed += $res->datalen;
            stream_bucket_append($out, $res);
        }

        return PSFS_PASS_ON;
    }
}
