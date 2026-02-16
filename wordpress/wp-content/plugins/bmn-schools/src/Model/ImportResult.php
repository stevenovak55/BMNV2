<?php

declare(strict_types=1);

namespace BMN\Schools\Model;

/**
 * Value object representing the result of a data import operation.
 */
final class ImportResult
{
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly int $skipped,
        public readonly int $errors,
        public readonly array $errorMessages = [],
    ) {}

    public function total(): int
    {
        return $this->created + $this->updated + $this->skipped;
    }
}
