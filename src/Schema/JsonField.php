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

use ValueError;

use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function trim;

use const JSON_ERROR_NONE;
use const JSON_THROW_ON_ERROR;

final class JsonField extends FieldEvaluator implements Field
{
    public readonly int $flags;
    /** @var int<1, max> */
    public readonly int $depth;

    /**
     * @param int<1, max> $depth
     */
    public function __construct(
        int $flags = 0,
        int $depth = 512,
        float $confidenceThreshold = 0.8
    ) {
        json_encode([], flags: $flags & ~JSON_THROW_ON_ERROR, depth: $depth);
        JSON_ERROR_NONE === ($errorCode = json_last_error()) || throw new ValueError('The flags or the depth given are not valid JSON encoding parameters in PHP; '.json_last_error_msg(), $errorCode);

        parent::__construct($confidenceThreshold);
        $this->flags = $flags;
        $this->depth = $depth;
    }

    public function type(): FieldType
    {
        return FieldType::Json;
    }

    public function name(): string
    {
        return FieldType::Json->value;
    }

    public function parse(mixed $value): mixed
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        $res = json_decode(json: $value, associative: true, depth: $this->depth, flags: $this->flags & ~JSON_THROW_ON_ERROR);

        return JSON_ERROR_NONE === json_last_error() ? $res : null;
    }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata([
            'flags' => $this->flags,
            'depth' => $this->depth,
        ]);
    }
}
