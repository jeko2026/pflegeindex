<?php

namespace App\Services;

use App\Models\ContactSuggestion;
use App\Models\Facility;
use App\Support\HttpUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

class ContactSuggestionImporter
{
    /** @return array{created: int, updated: int, unknown: int, rejected_urls: int, pending: int} */
    public function import(string $path): array
    {
        $results = $this->readResults($path);
        $summary = ['created' => 0, 'updated' => 0, 'unknown' => 0, 'rejected_urls' => 0, 'pending' => 0];

        DB::transaction(function () use ($results, &$summary): void {
            foreach ($results as $result) {
                $facility = Facility::query()->where('source_id', $result['facilityId'] ?? null)->first();

                if ($facility === null) {
                    $summary['unknown']++;

                    continue;
                }

                $fingerprint = $this->fingerprint($result);
                $urls = [];

                foreach (['website' => 'website', 'phone_source' => 'phoneSource', 'email_source' => 'emailSource'] as $column => $field) {
                    $value = $result[$field] ?? null;
                    $urls[$column] = HttpUrl::normalize($value);

                    if ($value !== null && $urls[$column] === null) {
                        $summary['rejected_urls']++;
                    }
                }

                $suggestion = ContactSuggestion::query()->where('fingerprint', $fingerprint)->first();
                $isNew = $suggestion === null;
                $matchesPublishedContact = $this->matchesPublishedContact($facility, $result);
                $suggestion ??= new ContactSuggestion([
                    'facility_id' => $facility->id,
                    'fingerprint' => $fingerprint,
                    'decision' => $matchesPublishedContact ? 'accepted' : 'pending',
                    'reviewed_at' => $matchesPublishedContact ? now() : null,
                ]);
                $suggestion->fill([
                    'facility_id' => $facility->id,
                    'parser_status' => (string) ($result['status'] ?? 'unknown'),
                    'phone' => $result['phone'] ?? null,
                    'email' => $result['email'] ?? null,
                    ...$urls,
                    'confidence' => isset($result['confidence']) ? (int) $result['confidence'] : null,
                    'checked_at' => isset($result['checkedAt']) ? Carbon::parse($result['checkedAt']) : null,
                    'raw_payload' => $result,
                ])->save();

                $isNew ? $summary['created']++ : $summary['updated']++;
            }
        });

        $summary['pending'] = ContactSuggestion::query()->where('decision', 'pending')->count();

        return $summary;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    private function readResults(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read parser result file: {$path}");
        }

        $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $results = is_array($payload) && array_is_list($payload) ? $payload : ($payload['results'] ?? null);

        if (! is_array($results) || ! array_is_list($results)) {
            throw new RuntimeException('Parser result file must contain a results array.');
        }

        return $results;
    }

    /** @param array<string, mixed> $result */
    private function fingerprint(array $result): string
    {
        $relevant = array_map(
            fn (string $key) => $result[$key] ?? null,
            ['facilityId', 'status', 'phone', 'email', 'website', 'phoneSource', 'emailSource'],
        );

        return hash('sha256', json_encode($relevant, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $result */
    private function matchesPublishedContact(Facility $facility, array $result): bool
    {
        if (($result['status'] ?? null) !== 'verified' || $facility->contact_status !== 'verified') {
            return false;
        }

        foreach (['phone', 'email', 'website'] as $field) {
            if (($result[$field] ?? null) !== null && $result[$field] !== $facility->{$field}) {
                return false;
            }
        }

        return ($result['phone'] ?? null) !== null;
    }
}
