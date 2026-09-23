<?php

namespace App\Console\Commands;

use App\Models\Facility;
use App\Services\FacilityEnrichment\FacilityAttributeUpserter;
use App\Services\FacilityEnrichment\FacilityEnrichmentCrawler;
use App\Services\FacilityEnrichment\FacilityRelationEvaluator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Throwable;

final class RunMassFacilityEnrichment extends Command
{
    protected $signature = 'pflegeindex:enrichment-mass {--region= : brandenburg or sachsen} {--limit=0 : Max facilities} {--after-id=0 : Resume after facility id} {--batch=100 : Progress batch size} {--persist : Store local candidates}';

    protected $description = 'Batch enrichment of active local facilities. Persisting is blocked in production.';

    public function handle(FacilityEnrichmentCrawler $crawler, FacilityRelationEvaluator $relation, FacilityAttributeUpserter $upserter): int
    {
        if (! $this->option('persist')) {
            $this->error('Use --persist for a local mass run; dry-run belongs to the single discovery command.');

            return self::FAILURE;
        }
        if (app()->environment('production')) {
            $this->error('Mass enrichment persist is blocked in production.');

            return self::FAILURE;
        }
        $region = (string) $this->option('region');
        if ($region !== '' && ! in_array($region, ['brandenburg', 'sachsen'], true)) {
            $this->error('Invalid region.');

            return self::FAILURE;
        }
        $started = microtime(true);
        $limit = max(0, (int) $this->option('limit'));
        $after = max(0, (int) $this->option('after-id'));
        $batch = max(1, min(200, (int) $this->option('batch')));
        $query = Facility::query()->with('city')->where('is_active', true)->whereNotNull('website')->where('website', '!=', '')->where('id', '>', $after)
            ->when($region !== '', fn (Builder $query) => $query->whereHas('city', fn (Builder $city) => $city->where('state_slug', $region)))->orderBy('id');
        if ($limit > 0) {
            $query->limit($limit);
        }
        $stats = ['processed' => 0, 'facts' => 0, 'created' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'errors' => 0, 'no_useful_source' => 0, 'last_id' => $after, 'error_samples' => []];
        foreach ($query->cursor() as $facility) {
            $stats['processed']++;
            $stats['last_id'] = $facility->id;
            try {
                $candidates = $crawler->discover($facility);
                if ($candidates === []) {
                    $stats['no_useful_source']++;
                }
                foreach ($candidates as $candidate) {
                    $match = $relation->evaluate($facility, $candidate);
                    $confidence = $match['confidence'];
                    $stats[strtolower($confidence)]++;
                    $stats['facts']++;
                    unset($candidate['source_text']);
                    $attribute = $upserter->store($facility, [...$candidate, 'confidence' => $confidence, 'relation_reason' => $match['relation_reason']]);
                    if ($attribute->wasRecentlyCreated) {
                        $stats['created']++;
                    }
                }
            } catch (Throwable $exception) {
                $stats['errors']++;
                if (count($stats['error_samples']) < 20) {
                    $stats['error_samples'][] = ['facility_id' => $facility->id, 'message' => mb_strimwidth($exception->getMessage(), 0, 180, '…')];
                }
            }
            if ($stats['processed'] % $batch === 0) {
                $this->line(json_encode($stats));
            }
        }
        $stats['runtime_seconds'] = round(microtime(true) - $started, 2);
        $stats['average_seconds_per_facility'] = $stats['processed'] ? round($stats['runtime_seconds'] / $stats['processed'], 2) : 0;
        $stats['region'] = $region ?: 'all';
        File::ensureDirectoryExists(storage_path('app/reports'));
        $path = storage_path('app/reports/mass-enrichment-'.($region ?: 'all').'-'.now()->format('Ymd-His').'.json');
        File::put($path, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info(json_encode($stats));
        $this->info("Report: {$path}");

        return self::SUCCESS;
    }
}
