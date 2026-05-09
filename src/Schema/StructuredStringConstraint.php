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

use function preg_match;
use function trim;

final readonly class StructuredStringConstraint implements StringConstraint
{
    /**
     * @param non-empty-string $fieldTypeName
     * @param non-empty-string $pattern
     */
    public function __construct(
        public string $fieldTypeName,
        public string $pattern,
    ) {
        ('' !== $pattern && false !== @preg_match($pattern, '')) || throw new ValueError('the regular expression pattern "'.$pattern.'" is not valid. Did you forget the delimiter?');
        ('' !== $fieldTypeName && 1 === preg_match('/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/', $fieldTypeName)) || throw new ValueError('The name "'.$fieldTypeName.'" is not a valid snake case variable name.');
    }

    public static function uuid(): self
    {
        return new self(
            fieldTypeName: 'uuid',
            pattern: '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        );
    }

    public static function ulid(): self
    {
        return new self(
            fieldTypeName: 'ulid',
            pattern: '/^[0-9A-HJKMNP-TV-Z]{26}$/i',
        );
    }

    public static function jwtToken(): self
    {
        return new self(
            fieldTypeName: 'jwt_token',
            pattern: '/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+$/i',
        );
    }

    public static function hexColor(): self
    {
        return new self(
            fieldTypeName: 'hex_color',
            pattern: '/^#(?:[0-9a-fA-F]{3}){1,2}$/i',
        );
    }

    public static function md5(): self
    {
        return new self(
            fieldTypeName: 'md5',
            pattern: '/^[a-fA-F0-9]{32}$/',
        );
    }

    public static function sha1(): self
    {
        return new self(
            fieldTypeName: 'sha1',
            pattern: '/^[a-fA-F0-9]{40}$/',
        );
    }

    public function apply(string $value): ?string
    {
        $value = trim($value);

        return ('' === $value || 1 !== preg_match($this->pattern, $value)) ? null : $value;
    }

    public function fieldTypeName(): string
    {
        return FieldType::String->value.'('.$this->fieldTypeName.')';
    }
}
