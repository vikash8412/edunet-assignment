<?php

namespace App\Services\Schema;

class SchemaValidationResult
{
    /**
     * @param list<string> $errors human-readable messages, JSON-pointer prefixed where possible
     */
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
    ) {
    }

    public static function ok(): self
    {
        return new self(true);
    }

    public static function fail(array $errors): self
    {
        return new self(false, $errors);
    }
}
