<?php

declare(strict_types=1);

namespace App\Platform\DirectoryCore\ReadModel;

use App\Platform\DirectoryCore\Domain\EntryIdentifier;
use App\Platform\DirectoryCore\Domain\LocationScope;

final readonly class EntrySummary
{
    public function __construct(
        public EntryIdentifier $id,
        public string $name,
        public string $slug,
        public ?string $categoryIdentifier,
        public ?string $categoryLabel,
        public ?LocationScope $locationScope,
        public ?string $locationName,
        public ?string $address,
        public ?string $postalCode,
        public ?string $telephone,
    ) {}
}
