<?php

namespace App\Services\DataQuality;

use App\Models\Facility;

final class FacilityContactNormalizer
{
    public static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Strip UTF-8 BOM
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            $value = substr($value, 3);
        }

        $patterns = [
            '/\x{200B}/u', // zero-width space
            '/\x{200C}/u', // zero-width non-joiner
            '/\x{200D}/u', // zero-width joiner
            '/\x{FEFF}/u', // zero-width no-break space
            '/[\x00-\x1F\x7F]/', // ASCII control characters
        ];
        
        $cleaned = preg_replace($patterns, '', $value);
        $cleaned = trim($cleaned);

        return $cleaned === '' ? null : $cleaned;
    }

    /**
     * @return array<string, array{old: ?string, new: ?string}>
     */
    public function getChanges(Facility $facility): array
    {
        $fields = ['phone', 'email', 'website'];
        $changes = [];

        foreach ($fields as $field) {
            $oldValue = $facility->$field;
            $newValue = self::clean($oldValue);

            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    public function apply(Facility $facility): bool
    {
        $changes = $this->getChanges($facility);
        if (empty($changes)) {
            return false;
        }

        foreach ($changes as $field => $data) {
            $facility->$field = $data['new'];
        }

        return $facility->save();
    }
}
