<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

class InvalidSchemaException extends InvalidArgumentException
{
    public static function emptySchema(): self
    {
        return new self('Schema must not be empty.');
    }

    public static function malformedJson(): self
    {
        return new self('Schema JSON is malformed or could not be decoded.');
    }

    public static function notAnArray(): self
    {
        return new self('Schema must decode to a JSON array.');
    }
}
