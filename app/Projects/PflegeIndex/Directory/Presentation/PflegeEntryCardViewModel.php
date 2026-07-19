<?php

declare(strict_types=1);

namespace App\Projects\PflegeIndex\Directory\Presentation;

final readonly class PflegeEntryCardViewModel
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $type,
        public PflegeEntryLocationViewModel $city,
        public ?string $address,
        public ?string $postal_code,
        public ?string $phone,
        public string $url,
    ) {}

    public function formattedPhone(): ?string
    {
        if ($this->phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $this->phone);

        if (! is_string($digits) || ! str_starts_with($digits, '49')) {
            return $this->phone;
        }

        $groups = str_split(substr($digits, 2), 3);
        $lastGroup = end($groups);

        if (count($groups) > 1 && is_string($lastGroup) && strlen($lastGroup) < 3) {
            $tail = array_pop($groups);
            $groups[array_key_last($groups)] .= $tail;
        }

        return '+49 '.implode(' ', $groups);
    }
}
