<?php

declare(strict_types=1);

namespace App\Platform\DirectoryCore\ReadModel;

use App\Platform\DirectoryCore\Domain\EntryIdentifier;

final readonly class EntrySummary
{
    public function __construct(
        public EntryIdentifier $id,
        public string $name,
        public string $slug,
        public ?string $categoryIdentifier,
        public ?string $categoryLabel,
        public ?string $locationIdentifier,
        public ?string $locationName,
        public ?string $address,
        public ?string $postalCode,
        public ?string $telephone,
    ) {}
}
