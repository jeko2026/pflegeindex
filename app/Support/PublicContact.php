<?php

namespace App\Support;

use App\Services\DataQuality\FacilityContactNormalizer;

final class PublicContact
{
    public static function phone(mixed $value): ?string
    {
        $phone = self::cleanString($value);

        return $phone !== null && mb_strlen($phone) <= 40 ? $phone : null;
    }

    public static function email(mixed $value): ?string
    {
        $email = self::cleanString($value);

        return $email !== null
            && mb_strlen($email) <= 255
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
                ? $email
                : null;
    }

    public static function website(mixed $value): ?string
    {
        return HttpUrl::normalize($value);
    }

    private static function cleanString(mixed $value): ?string
    {
        return is_string($value) ? FacilityContactNormalizer::clean($value) : null;
    }
}
