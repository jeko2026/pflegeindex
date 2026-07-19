<?php

declare(strict_types=1);

namespace App\Platform\DirectoryCore\Domain;

use InvalidArgumentException;

final readonly class LocationScope
{
    public string $identifier;

    public function __construct(
        public LocationScopeType $type,
        string $identifier,
    ) {
        $this->identifier = trim($identifier);

        if ($this->identifier === '') {
            throw new InvalidArgumentException('A location scope identifier must not be empty.');
        }
    }

    public static function city(string $identifier): self
    {
        return new self(LocationScopeType::City, $identifier);
    }

    public static function district(string $identifier): self
    {
        return new self(LocationScopeType::District, $identifier);
    }

    public static function state(string $identifier): self
    {
        return new self(LocationScopeType::State, $identifier);
    }
}
