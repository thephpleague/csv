<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv\Schema;

use League\Csv\InvalidArgument;
use League\Csv\Statement;
use League\Csv\SyntaxError;
use League\Csv\TabularData;
use ValueError;

use function arsort;
use function is_string;
use function trim;

use const SORT_NUMERIC;

final readonly class Inspector
{
    /**
     * @param positive-int $sampleLimit
     */
    public function __construct(
        public int $sampleLimit = 10,
        public FieldList $fieldList = new FieldList(),
    ) {
        1 <= $this->sampleLimit || throw new ValueError('A sample size must be greater or equal to 1.');
    }

    /**
     * @param positive-int $sampleLimit
     */
    public function withSampleLimit(int $sampleLimit): self
    {
        return $sampleLimit === $this->sampleLimit ? $this : new self($sampleLimit, $this->fieldList);
    }

    public function withFields(FieldList $fieldList): self
    {
        return new self($this->sampleLimit, $fieldList);
    }

    /**
     * @param positive-int $sampleLimit
     */
    public static function default(int $sampleLimit = 10): self
    {
        return new self($sampleLimit, FieldList::default());
    }

    /**
     * @throws InvalidArgument
     * @throws SyntaxError
     * @throws \League\Csv\Exception
     */
    public function schema(TabularData $tabularData, array $header = []): Schema
    {
        $score = [];
        $counted = [];
        foreach ((new Statement())->limit($this->sampleLimit)->process($tabularData, $header) as $record) {
            foreach ($record as $column => $value) {
                $counted[$column] ??= 0;
                $score[$column] ??= [];
                if (is_string($value)) {
                    $value = trim($value);
                }

                if (null === $value || '' === $value) {
                    continue;
                }

                $counted[$column]++;
                foreach ($this->fieldList as $offset => $field) {
                    $score[$column][$offset] ??= 0;
                    if (1 === $field->evaluate($value)) {
                        $score[$column][$offset]++;
                    }
                }
            }
        }

        $result = [];
        foreach ($score as $column => $fields) {
            $result[$column] = new StringField();
            $total = $counted[$column] ?? 0;
            if (0 === $total) {
                continue;
            }

            $normalized = [];
            foreach ($fields as $offset => $validCount) {
                $normalized[$offset] = $validCount / $total;
            }

            arsort($normalized, SORT_NUMERIC);

            foreach ($normalized as $offset => $scoreValue) {
                $field = $this->fieldList->get($offset);
                if ($scoreValue >= $field->confidenceThreshold()) {
                    $result[$column] = $field;
                    break;
                }
            }
        }

        return new Schema($result);
    }
}
