<?php

namespace App\Services\DataQuality;

use RuntimeException;

final class ManualContactReviewCsvRepairService
{
    private const SCHEMAS = [
        'sheet1' => [
            'delimiter' => ',',
            'headers' => ['Назва закладу', 'Місто', 'Веб сайт Офіційний', 'Вулиця', 'Номер буд', 'Поштовий індекс', 'Номер телефону', 'Email', 'URL-адреса(и) джерела', 'Примітки'],
            'indexes' => ['name' => 0, 'city' => 1, 'website' => 2, 'address' => 3, 'building' => 4, 'postal_code' => 5, 'phone' => 6, 'email' => 7, 'source' => 8, 'notes' => 9],
        ],
        'sheet2' => [
            'delimiter' => ';',
            'headers' => ['facility_id', 'current_name', 'current_address', 'current_postal_code', 'current_city', 'current_phone(+49..)', 'current_email', 'current_website(https://)', 'data source(url)', 'Notes', 'Column1'],
            'indexes' => ['facility_id' => 0, 'name' => 1, 'address' => 2, 'postal_code' => 3, 'city' => 4, 'phone' => 5, 'email' => 6, 'website' => 7, 'source' => 8, 'notes' => 9],
        ],
    ];

    /** @return array<string, mixed> */
    public function repair(string $sheetOne, string $sheetTwo, string $outputDirectory): array
    {
        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0775, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException('Unable to create CSV repair output directory.');
        }

        $files = [
            'sheet1' => ['input' => $sheetOne, 'output' => $outputDirectory.'/manual-review-sheet1-clean.csv'],
            'sheet2' => ['input' => $sheetTwo, 'output' => $outputDirectory.'/manual-review-sheet2-clean.csv'],
        ];
        $audit = [];
        $summary = [
            'created_at' => date(DATE_ATOM),
            'files' => [],
            'detected_shifted_rows' => ['safe_auto_repair' => 0, 'manual_review' => 0, 'invalid' => 0],
            'repairs' => ['rows' => 0, 'shifted_rows' => 0, 'excel_text_prefixes' => 0],
        ];

        foreach ($files as $sheet => $paths) {
            $parsed = $this->parse($paths['input'], self::SCHEMAS[$sheet]);
            $cleanRows = [];
            foreach ($parsed['rows'] as $row) {
                $assessment = $this->assess($sheet, $row['values']);
                $values = $assessment['values'];
                if ($assessment['status'] !== 'EXACT') {
                    $audit[] = [
                        'file' => basename($paths['input']),
                        'original_row' => $row['record'],
                        'repair_status' => $assessment['status'],
                        'original_values' => $this->json($row['values']),
                        'repaired_values' => $assessment['status'] === 'SAFE_AUTO_REPAIR' ? $this->json($values) : '',
                        'reason' => $assessment['reason'],
                        'confidence' => $assessment['confidence'],
                    ];
                }
                if ($assessment['status'] === 'SAFE_AUTO_REPAIR') {
                    $summary['repairs']['rows']++;
                    $summary['detected_shifted_rows']['safe_auto_repair']++;
                    $summary['repairs'][$assessment['kind']]++;
                } elseif ($assessment['status'] === 'MANUAL_REVIEW') {
                    $summary['detected_shifted_rows']['manual_review']++;
                } elseif ($assessment['status'] === 'INVALID_ROW') {
                    $summary['detected_shifted_rows']['invalid']++;
                }
                if ($row['field_count'] !== $parsed['header_count'] || $row['malformed_quoting']) {
                    continue;
                }
                $cleanRows[] = $values;
            }

            $this->writeCsv($paths['output'], $parsed['headers'], $cleanRows, $parsed['delimiter']);
            $summary['files'][$sheet] = [
                'input_path' => realpath($paths['input']),
                'input_sha256' => hash_file('sha256', $paths['input']),
                'output_path' => realpath($paths['output']),
                'output_sha256' => hash_file('sha256', $paths['output']),
                'delimiter' => $parsed['delimiter'],
                'header_columns' => $parsed['header_count'],
                'rows' => count($parsed['rows']),
                'clean_rows' => count($cleanRows),
                'row_field_count_distribution' => $parsed['field_count_distribution'],
                'too_few_columns' => $parsed['too_few_columns'],
                'too_many_columns' => $parsed['too_many_columns'],
                'malformed_quoting' => $parsed['malformed_quoting'],
                'multiline_fields' => $parsed['multiline_fields'],
            ];
        }

        $auditPath = $outputDirectory.'/csv-repair-audit.csv';
        $this->writeAssociativeCsv($auditPath, ['file', 'original_row', 'repair_status', 'original_values', 'repaired_values', 'reason', 'confidence'], $audit, ';');
        $summary['audit_path'] = realpath($auditPath);
        $summary['audit_sha256'] = hash_file('sha256', $auditPath);
        $summaryPath = $outputDirectory.'/csv-repair-summary.json';
        file_put_contents($summaryPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

        return $summary;
    }

    /** @param array<string, mixed> $schema @return array<string, mixed> */
    private function parse(string $path, array $schema): array
    {
        $resolved = realpath($path);
        if ($resolved === false || ! is_readable($resolved)) {
            throw new RuntimeException('CSV is not readable: '.$path);
        }
        $handle = fopen($resolved, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Unable to open CSV: '.$path);
        }
        try {
            $delimiter = $schema['delimiter'];
            $headerStart = ftell($handle);
            $headers = fgetcsv($handle, 0, $delimiter);
            $headerEnd = ftell($handle);
            if (! is_array($headers) || $headerStart === false || $headerEnd === false) {
                throw new RuntimeException('CSV header is missing: '.$path);
            }
            $headers = array_map(fn (mixed $value): string => $this->clean((string) $value), $headers);
            if ($headers !== $schema['headers']) {
                throw new RuntimeException('Unexpected CSV headers in '.basename($path).'.');
            }

            $rows = [];
            $distribution = [];
            $record = 1;
            $tooFew = 0;
            $tooMany = 0;
            $malformed = 0;
            $multiline = 0;
            while (true) {
                $start = ftell($handle);
                $values = fgetcsv($handle, 0, $delimiter);
                $end = ftell($handle);
                if ($values === false) {
                    break;
                }
                if ($values === [null] && $start !== false && $end !== false) {
                    continue;
                }
                $record++;
                $raw = '';
                if ($start !== false && $end !== false) {
                    fseek($handle, $start);
                    $raw = (string) fread($handle, $end - $start);
                    fseek($handle, $end);
                }
                $fieldCount = count($values);
                $distribution[$fieldCount] = ($distribution[$fieldCount] ?? 0) + 1;
                $tooFew += $fieldCount < count($headers) ? 1 : 0;
                $tooMany += $fieldCount > count($headers) ? 1 : 0;
                $badQuoting = ! $this->validQuoting($raw, $delimiter);
                $malformed += $badQuoting ? 1 : 0;
                $hasMultiline = substr_count(rtrim($raw, "\r\n"), "\n") > 0;
                $multiline += $hasMultiline ? 1 : 0;
                $rows[] = [
                    'record' => $record,
                    'values' => array_map(fn (mixed $value): string => (string) $value, $values),
                    'field_count' => $fieldCount,
                    'malformed_quoting' => $badQuoting,
                    'multiline' => $hasMultiline,
                ];
            }
        } finally {
            fclose($handle);
        }
        ksort($distribution);

        return [
            'delimiter' => $delimiter,
            'headers' => $headers,
            'header_count' => count($headers),
            'rows' => $rows,
            'field_count_distribution' => $distribution,
            'too_few_columns' => $tooFew,
            'too_many_columns' => $tooMany,
            'malformed_quoting' => $malformed,
            'multiline_fields' => $multiline,
        ];
    }

    /** @param list<string> $values @return array{status:string,values:list<string>,reason:string,confidence:string,kind:string} */
    private function assess(string $sheet, array $values): array
    {
        $schema = self::SCHEMAS[$sheet];
        if (count($values) !== count($schema['headers'])) {
            return $this->assessment('INVALID_ROW', $values, 'CSV field count differs from the header; the row was excluded from CLEAN output.', 'high');
        }
        $indexes = $schema['indexes'];
        $name = trim($values[$indexes['name']]);
        $city = trim($values[$indexes['city']]);
        if ($name === '' || $city === '' || $this->typedContactValue($name) || $this->typedContactValue($city)) {
            return $this->assessment('MANUAL_REVIEW', $values, 'Facility name/city is blank or contains a contact-shaped value.', 'high');
        }
        if (isset($indexes['facility_id']) && preg_match('/^[1-9]\d*$/', trim($values[$indexes['facility_id']])) !== 1) {
            return $this->assessment('INVALID_ROW', $values, 'facility_id is not a positive integer.', 'high');
        }

        $phoneIndex = $indexes['phone'];
        $phone = trim($values[$phoneIndex]);
        if (preg_match("/^'(?=(?:\\+49|0049|0|49)[\\d\\s\/().-]{6,}$)/", $phone) === 1) {
            $candidate = $values;
            $candidate[$phoneIndex] = substr($phone, 1);
            if ($this->contactFieldsValid($candidate, $indexes)) {
                return $this->assessment('SAFE_AUTO_REPAIR', $candidate, 'Removed an Excel text-prefix apostrophe from an otherwise valid German phone number.', 'certain', 'excel_text_prefixes');
            }
        }

        $contactIndexes = [$indexes['phone'], $indexes['email'], $indexes['website'], $indexes['source'], $indexes['notes']];
        $contactValues = array_map(fn (int $index): string => $values[$index], $contactIndexes);
        $candidates = [];
        if ($this->empty($contactValues[0]) && ! $this->containsMarker($contactValues)) {
            $shifted = [...array_slice($contactValues, 1), ''];
            $candidate = $this->replace($values, $contactIndexes, $shifted);
            if ($this->contactFieldsValid($candidate, $indexes) && $this->invalidContactCount($values, $indexes) >= 2) {
                $candidates['shifted_rows:left'] = $candidate;
            }
        }
        if ($this->empty($contactValues[4]) && ! $this->containsMarker($contactValues)) {
            $shifted = ['', ...array_slice($contactValues, 0, 4)];
            $candidate = $this->replace($values, $contactIndexes, $shifted);
            if ($this->contactFieldsValid($candidate, $indexes) && $this->invalidContactCount($values, $indexes) >= 2) {
                $candidates['shifted_rows:right'] = $candidate;
            }
        }
        if (count($candidates) === 1) {
            $direction = array_key_first($candidates);

            $shift = str_ends_with($direction, 'left') ? 'left shift.' : 'right shift.';

            return $this->assessment('SAFE_AUTO_REPAIR', reset($candidates), 'Contact columns were uniquely recoverable by a one-column '.$shift, 'certain', 'shifted_rows');
        }
        if (count($candidates) > 1 || $this->misplacedContactCount($values, $indexes) >= 1) {
            return $this->assessment('MANUAL_REVIEW', $values, 'Contact-shaped values appear in incompatible columns and no unique lossless repair exists.', count($candidates) > 1 ? 'ambiguous' : 'medium');
        }
        if (! $this->contactFieldsValid($values, $indexes)) {
            return $this->assessment('INVALID_ROW', $values, 'One or more contact values fail strict type validation without evidence of a column shift.', 'high');
        }

        return $this->assessment('EXACT', $values, 'All fields are structurally aligned.', 'certain');
    }

    /** @param list<string> $values @param array<string,int> $indexes */
    private function contactFieldsValid(array $values, array $indexes): bool
    {
        return $this->phoneAllowed($values[$indexes['phone']])
            && $this->emailAllowed($values[$indexes['email']])
            && $this->urlAllowed($values[$indexes['website']], 'website')
            && $this->urlAllowed($values[$indexes['source']], 'source');
    }

    /** @param list<string> $values @param array<string,int> $indexes */
    private function invalidContactCount(array $values, array $indexes): int
    {
        return ($this->phoneAllowed($values[$indexes['phone']]) ? 0 : 1)
            + ($this->emailAllowed($values[$indexes['email']]) ? 0 : 1)
            + ($this->urlAllowed($values[$indexes['website']], 'website') ? 0 : 1)
            + ($this->urlAllowed($values[$indexes['source']], 'source') ? 0 : 1);
    }

    /** @param list<string> $values @param array<string,int> $indexes */
    private function misplacedContactCount(array $values, array $indexes): int
    {
        $fields = ['phone', 'email', 'website', 'source'];
        $count = 0;
        foreach ($fields as $field) {
            $value = trim($values[$indexes[$field]]);
            if ($this->empty($value) || $this->markerFor($value, $field)) {
                continue;
            }
            $isForeignType = match ($field) {
                'phone' => $this->isEmail($value) || $this->isUrl($value),
                'email' => $this->isPhone($value) || $this->isUrl($value),
                'website', 'source' => $this->isPhone($value) || $this->isEmail($value),
            };
            $wrongMarker = preg_match('/^(?:NO_[A-Z_]+|NEEDS_REVIEW)$/i', $value) === 1;
            $count += ($isForeignType || $wrongMarker) ? 1 : 0;
        }

        return $count;
    }

    private function phoneAllowed(string $value): bool
    {
        return $this->empty($value) || $this->markerFor($value, 'phone') || $this->isPhone($value);
    }

    private function emailAllowed(string $value): bool
    {
        return $this->empty($value) || $this->markerFor($value, 'email') || $this->isEmail($value);
    }

    private function urlAllowed(string $value, string $field): bool
    {
        return $this->empty($value) || $this->markerFor($value, $field) || $this->isUrl($value);
    }

    private function isPhone(string $value): bool
    {
        $value = trim($value);
        if (preg_match('/^(?:\+49|0049|0|49)[\d\s\/().-]+$/', $value) !== 1) {
            return false;
        }
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) >= 7 && strlen($digits) <= 15;
    }

    private function isEmail(string $value): bool
    {
        return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
    }

    private function isUrl(string $value): bool
    {
        $value = trim($value);
        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && (string) parse_url($value, PHP_URL_HOST) !== '';
    }

    private function markerFor(string $value, string $field): bool
    {
        $value = strtoupper(trim($value));
        if ($value === 'NEEDS_REVIEW') {
            return true;
        }

        return match ($field) {
            'phone' => in_array($value, ['NO_PHONE', 'NO_TEL'], true),
            'email' => $value === 'NO_EMAIL',
            'website' => $value === 'NO_WEBSITE',
            'source' => $value === 'NO_SOURCE',
            default => false,
        };
    }

    private function typedContactValue(string $value): bool
    {
        return $this->isPhone($value) || $this->isEmail($value) || $this->isUrl($value);
    }

    /** @param list<string> $values */
    private function containsMarker(array $values): bool
    {
        foreach ($values as $value) {
            if (preg_match('/^(?:NO_[A-Z_]+|NEEDS_REVIEW)$/i', trim($value)) === 1) {
                return true;
            }
        }

        return false;
    }

    private function empty(string $value): bool
    {
        return trim($value) === '';
    }

    /** @param list<string> $values @param list<int> $indexes @param list<string> $replacement @return list<string> */
    private function replace(array $values, array $indexes, array $replacement): array
    {
        foreach ($indexes as $offset => $index) {
            $values[$index] = $replacement[$offset];
        }

        return $values;
    }

    /** @return array{status:string,values:list<string>,reason:string,confidence:string,kind:string} */
    private function assessment(string $status, array $values, string $reason, string $confidence, string $kind = 'shifted_rows'): array
    {
        return compact('status', 'values', 'reason', 'confidence', 'kind');
    }

    private function validQuoting(string $raw, string $delimiter): bool
    {
        $inQuotes = false;
        $fieldStart = true;
        $afterQuote = false;
        $length = strlen($raw);
        for ($i = 0; $i < $length; $i++) {
            $char = $raw[$i];
            if ($inQuotes) {
                if ($char === '"') {
                    if ($i + 1 < $length && $raw[$i + 1] === '"') {
                        $i++;
                    } else {
                        $inQuotes = false;
                        $afterQuote = true;
                    }
                }

                continue;
            }
            if ($afterQuote) {
                if ($char === $delimiter) {
                    $fieldStart = true;
                    $afterQuote = false;
                } elseif ($char !== "\r" && $char !== "\n") {
                    return false;
                }

                continue;
            }
            if ($char === $delimiter) {
                $fieldStart = true;
            } elseif ($char === '"') {
                if (! $fieldStart) {
                    return false;
                }
                $inQuotes = true;
                $fieldStart = false;
            } elseif ($char !== "\r" && $char !== "\n") {
                $fieldStart = false;
            }
        }

        return ! $inQuotes;
    }

    /** @param list<string> $headers @param list<list<string>> $rows */
    private function writeCsv(string $path, array $headers, array $rows, string $delimiter): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to write CSV: '.$path);
        }
        fputcsv($handle, $headers, $delimiter, '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, $delimiter, '"', '');
        }
        fclose($handle);
    }

    /** @param list<string> $headers @param list<array<string,mixed>> $rows */
    private function writeAssociativeCsv(string $path, array $headers, array $rows, string $delimiter): void
    {
        $values = array_map(fn (array $row): array => array_map(fn (string $header): mixed => $row[$header] ?? '', $headers), $rows);
        $this->writeCsv($path, $headers, $values, $delimiter);
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value);
    }

    /** @param list<string> $values */
    private function json(array $values): string
    {
        return (string) json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
