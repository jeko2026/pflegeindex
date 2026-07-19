<?php

declare(strict_types=1);

namespace App\Platform\DirectoryCore\Domain;

use InvalidArgumentException;

final readonly class EntryIdentifier
{
    private string $value;

    public function __construct(string|int $value)
    {
        $normalizedValue = trim((string) $value);

        if ($normalizedValue === '') {
            throw new InvalidArgumentException('Entry identifier cannot be empty.');
        }

        $this->value = $normalizedValue;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
