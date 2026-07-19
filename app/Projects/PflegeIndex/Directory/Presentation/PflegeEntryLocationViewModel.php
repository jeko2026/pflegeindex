<?php

declare(strict_types=1);

namespace App\Projects\PflegeIndex\Directory\Presentation;

final readonly class PflegeEntryLocationViewModel
{
    public function __construct(
        public string $name,
        public string $slug,
    ) {}
}
