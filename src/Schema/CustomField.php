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

use Closure;
use ValueError;

use function preg_match;

/**
 * @template T
 */
final class CustomField extends FieldEvaluator implements Field
{
    private readonly FieldParser $fieldParser;
    /** @var non-empty-string */
    private readonly string $fieldTypeName;

    public function __construct(
        FieldParser|Closure|callable $fieldParser,
        string $fieldTypeName,
        float $confidenceThreshold = 0.8
    ) {
        ('' !== $fieldTypeName && 1 === preg_match('/^[a-z]+(?:_[a-z0-9]+)*$/', $fieldTypeName)) || throw new ValueError('The name "'.$fieldTypeName.'" is not a valid snake case variable name.');
        $fieldParser = self::resolveFieldParser($fieldParser);
        parent::__construct($confidenceThreshold);

        $this->fieldParser = $fieldParser;
        $this->fieldTypeName = $fieldTypeName;
    }

    private static function resolveFieldParser(FieldParser|Closure|callable $parser): FieldParser
    {
        return $parser instanceof FieldParser ? $parser : new CallbackFieldParser($parser);
    }

    public function type(): FieldType
    {
        return FieldType::Custom;
    }

    public function name(): string
    {
        return FieldType::Custom->value.'('.$this->fieldTypeName.')';
    }

    /**
     * @return ?T
     */
    public function parse(mixed $value): mixed
    {
        return $this->fieldParser->parse($value);
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata();
    }
}
