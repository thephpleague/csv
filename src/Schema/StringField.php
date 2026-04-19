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

use function is_string;

final class StringField implements Field
{
    public function type(): FieldType
    {
        return FieldType::String;
    }

    public function name(): string
    {
        return FieldType::String->value;
    }

    public function confidenceThreshold(): float
    {
        return 0.0;
    }

    public function score(iterable $values): float
    {
        return 1.0;
    }

    public function parse(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    public function evaluate(mixed $value): int
    {
        return is_string($value) ? 1 : 0;
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata();
    }
}
