<?php

namespace App\Rules;

use App\Support\HttpUrl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class AbsoluteHttpUrl implements ValidationRule
{
    public function __construct(private readonly bool $requirePublicTarget = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! HttpUrl::isValid($value, $this->requirePublicTarget)) {
            $fail('Die :attribute muss eine vollständige Webadresse mit http:// oder https:// sein.');
        }
    }
}
